<?php
declare(strict_types=1);
function operation_id(): string { return 'op_'.bin2hex(random_bytes(16)); }
function operation_keys(array $args, array $allowed): void {ensure(count(array_diff(array_keys($args),$allowed))===0,'Campo no permitido.');}
function apply_operation(array $state, string $action, array $args): array {
    $today=$state['settings']['today'];
    if($action==='client_upsert'){
        operation_keys($args,['id','name','status','country','contact','phone','email','notes','aliases']);
        $id=$args['id']??operation_id();$index=null;foreach($state['clients'] as $i=>$c)if($c['id']===$id)$index=$i;
        if($index===null){ensure(isset($args['name'])&&is_string($args['name'])&&trim($args['name'])!==''&&isset($args['status']),'Nombre y estado confirmados obligatorios.');foreach($state['clients'] as $c)ensure(mb_safe_lower($c['name'])!==mb_safe_lower($args['name']),'Ya existe ese cliente; utiliza su ID.');$state['clients'][]=['id'=>$id,'name'=>$args['name'],'status'=>$args['status'],'createdAt'=>$today];$index=count($state['clients'])-1;}
        if(isset($args['name']))foreach($state['clients'] as $c)ensure($c['id']===$id||mb_safe_lower($c['name'])!==mb_safe_lower($args['name']),'Ya existe ese cliente; utiliza su ID.');
        foreach($args as $k=>$v)if($k!=='id')$state['clients'][$index][$k]=$v;
    }elseif($action==='project_upsert'){
        operation_keys($args,['id','clientId','name','type','amount','currency','kind','start','frequency','mode','cycle','dueDay','dueDay2','schedulePending','automation','windows','site','brand','tariffs','collaboratorShare','servicePeriodDay','notes']);
        $id=$args['id']??operation_id();$index=null;foreach($state['services'] as $i=>$v)if($v['id']===$id)$index=$i;
        if($index===null){foreach(['clientId','name','amount','currency','kind','start'] as $key)ensure(isset($args[$key]),'Falta información confirmada del proyecto: '.$key);$state['services'][]=['id'=>$id,'tariffs'=>[]];$index=count($state['services'])-1;}
        if(isset($args['automation'])){ $prior=$state['services'][$index]['automation']['fromPeriod']??null;$from=$args['automation']['fromPeriod']??'';$future=(new DateTimeImmutable(substr($today,0,7).'-01'))->modify('+1 month')->format('Y-m');ensure($from===$prior||$from>=$future,'Un calendario nuevo empieza en un mes futuro; no reconstruye histórico.');}
        foreach($args as $k=>$v)if($k!=='id')$state['services'][$index][$k]=$v;
    }elseif($action==='payment'){
        operation_keys($args,['chargeId','amount','receiptDate','receivedMonth','receivedMonthBasis','mxn','note']);record_operation_payment($state,$args);
    }elseif($action==='group_payment'){
        operation_keys($args,['payerId','group','period','amount','receiptDate','receivedMonth','receivedMonthBasis','note']);ensure(in_array($args['group']??null,['SEO','Ads'],true)&&valid_month($args['period']??null),'Grupo y mes obligatorios.');
        $charges=array_values(array_filter($state['charges'],fn($c)=>!($c['cancelled']??false)&&($c['payerId']??null)===($args['payerId']??null)&&($c['paymentGroup']??null)===$args['group']&&$c['period']===$args['period']));ensure(count($charges)>0,'No hay cobros del grupo.');$currencies=array_unique(array_column($charges,'currency'));ensure(count($currencies)===1,'No mezcles monedas.');$left=(float)($args['amount']??0);ensure(valid_amount($left),'Importe no válido.');$balances=[];$total=0;foreach($charges as $c){$b=operation_charge_balance($state,$c);$balances[$c['id']]=$b;$total+=$b;}ensure($left<=round($total,2)+.009,'El pago supera los pendientes del grupo.');
        usort($charges,fn($a,$b)=>strcmp($a['id'],$b['id']));$receiptId=operation_id();foreach($charges as $c){$amount=min($left,$balances[$c['id']]);if($amount<=0)continue;record_operation_payment($state,['chargeId'=>$c['id'],'amount'=>round($amount,2),'receiptDate'=>$args['receiptDate']??null,'note'=>$args['note']??'Pago agrupado confirmado','groupReceiptId'=>$receiptId]+array_intersect_key($args,['receivedMonth'=>1,'receivedMonthBasis'=>1]));$left=round($left-$amount,2);if($left<=0)break;}
    }elseif($action==='charge_create'){
        // Alta manual de un cobro (p. ej. primer ciclo Ads 15→15 con pagador). Moneda y cliente salen del servicio.
        operation_keys($args,['serviceId','period','part','amount','title','start','end','due','collectionWindow','collectionDay','collectionPending','payerId','paymentGroup','note']);
        $svc=$state['services'][operation_index($state['services'],$args['serviceId']??null,'Servicio no encontrado.')];
        ensure(valid_month($args['period']??null),'Periodo YYYY-MM obligatorio.');$part=(string)($args['part']??'1');ensure(preg_match('/^[0-9]{1,3}$/D',$part)===1,'Parte no válida.');
        foreach($state['charges'] as $c)ensure(!($c['serviceId']===$svc['id']&&$c['period']===$args['period']&&(string)($c['part']??'1')===$part&&!($c['cancelled']??false)),'Ya existe un cobro de ese servicio y periodo; edítalo con charge_update o usa otra parte.');
        $c=['id'=>'manual_'.bin2hex(random_bytes(8)),'serviceId'=>$svc['id'],'clientId'=>$svc['clientId'],'title'=>$args['title']??$svc['name'],'period'=>$args['period'],'start'=>$args['start']??$args['period'].'-01','end'=>$args['end']??(new DateTimeImmutable($args['period'].'-01'))->format('Y-m-t'),'due'=>null,'part'=>$part,'amount'=>$args['amount']??$svc['amount'],'currency'=>$svc['currency'],'cancelled'=>false,'lost'=>false,'registration'=>'manual-period','override'=>true];
        operation_charge_patch($c,array_diff_key($args,array_flip(['serviceId','period','part','amount','title','start','end'])));$state['charges'][]=$c;
    }elseif($action==='charge_update'){
        // Editar un cobro existente: importe, fechas, periodo, ventana, pagador o nota. No cambia servicio ni cliente.
        operation_keys($args,['chargeId','amount','title','period','start','end','due','collectionWindow','collectionDay','collectionPending','payerId','paymentGroup','note']);
        $i=operation_index($state['charges'],$args['chargeId']??null,'Cobro no encontrado.');ensure(count($args)>1,'Indica qué cambiar.');
        operation_charge_patch($state['charges'][$i],array_diff_key($args,['chargeId'=>1]));$state['charges'][$i]['override']=true;$state['charges'][$i]['billingEditVersion']=1; // Versión del formato validado, no contador de ediciones.
    }elseif($action==='charge_status'){
        // open = vigente; cancelled = anulado (sin pagos); lost = impago definitivo. No borra el cobro.
        operation_keys($args,['chargeId','status','note']);$i=operation_index($state['charges'],$args['chargeId']??null,'Cobro no encontrado.');
        ensure(in_array($args['status']??null,['open','cancelled','lost'],true),'Estado: open, cancelled o lost.');$id=$state['charges'][$i]['id'];
        if($args['status']==='cancelled')foreach($state['payments'] as $p)ensure($p['chargeId']!==$id,'El cobro tiene pagos; elimínalos antes de anularlo o márcalo como perdido.');
        $state['charges'][$i]['cancelled']=$args['status']==='cancelled';$state['charges'][$i]['lost']=$args['status']==='lost';
        if(isset($args['note'])){ensure(is_string($args['note']),'Nota no válida.');$state['charges'][$i]['note']=trim(($state['charges'][$i]['note']??'')."\n".$today.': '.$args['note']);}
    }elseif($action==='payment_update'){
        // Corregir un pago ya registrado: importe, fecha, mes de recepción, conversión o nota.
        operation_keys($args,['paymentId','amount','receiptDate','receivedMonth','receivedMonthBasis','mxn','note']);$i=operation_index($state['payments'],$args['paymentId']??null,'Pago no encontrado.');ensure(count($args)>1,'Indica qué cambiar.');
        $p=$state['payments'][$i];$charge=$state['charges'][operation_index($state['charges'],$p['chargeId'],'Cobro no encontrado.')];
        if(array_key_exists('amount',$args)){ensure(valid_amount($args['amount'])&&abs($args['amount']-round($args['amount'],2))<.0000001,'Importe no válido.');$p['amount']=$args['amount'];}
        if(array_key_exists('receiptDate',$args)){$d=$args['receiptDate'];ensure($d===null||valid_date($d)&&$d<=$today,'Fecha de recepción inválida.');$p['date']=$d;$p['dateStatus']=$d===null?'unknown':'known';if($d!==null)unset($p['receivedMonth'],$p['receivedMonthBasis'],$p['receivedTiming']);}
        if(array_key_exists('receivedMonth',$args)||array_key_exists('receivedMonthBasis',$args)){unset($p['receivedMonth'],$p['receivedMonthBasis'],$p['receivedTiming']);if(($args['receivedMonth']??null)!==null)operation_received_month($p,$args,$today);}
        if(array_key_exists('note',$args)){ensure(is_string($args['note']),'Nota no válida.');$p['note']=$args['note'];}
        if($charge['currency']==='MXN'){if(array_key_exists('amount',$args)||array_key_exists('receiptDate',$args)||array_key_exists('mxn',$args)){$p['mxn']=$p['amount'];$p['rate']=1;$p['rateDate']=$p['date'];$p['conversionStatus']='known';}}
        elseif(array_key_exists('mxn',$args)){$m=$args['mxn'];ensure($m===null||(is_int($m)||is_float($m))&&$m>0,'Conversión no válida.');ensure(!($m!==null&&$p['date']===null),'Para moneda extranjera sin fecha, conserva conversión desconocida.');$p['mxn']=$m;$p['rate']=$m===null?null:round($m/$p['amount'],8);$p['rateDate']=$m===null?null:$p['date'];$p['conversionStatus']=$m===null?'unknown':'known';}
        elseif(array_key_exists('amount',$args))throw new DomainException('Cambiaste el importe de un pago en moneda extranjera: indica también mxn (importe real recibido) o null.');
        $p['editedAt']=$today;$state['payments'][$i]=$p;
    }elseif($action==='payment_remove'){
        // Eliminar un pago registrado por error. Queda la copia previa (snapshot) y la auditoría.
        operation_keys($args,['paymentId']);$i=operation_index($state['payments'],$args['paymentId']??null,'Pago no encontrado.');array_splice($state['payments'],$i,1);
    }elseif($action==='opening_payment'){
        // Abono a un saldo histórico (deuda o contrato inicial) sin fecha exacta.
        operation_keys($args,['clientId','amount','note','receivedMonth','receivedMonthBasis']);$i=operation_index($state['clients'],$args['clientId']??null,'Cliente no encontrado.');
        $o=$state['clients'][$i]['opening']??null;ensure(is_array($o)&&in_array($o['kind']??null,['debt','contract'],true),'Ese cliente no tiene saldo histórico con importe.');
        $amount=$args['amount']??null;ensure(valid_amount($amount)&&abs($amount-round($amount,2))<.0000001,'Importe no válido.');ensure(is_string($args['note']??null)&&trim($args['note'])!=='','Nota obligatoria.');
        $pay=['id'=>operation_id(),'amount'=>$amount,'date'=>null,'note'=>$args['note']];if(($args['receivedMonth']??null)!==null)operation_received_month($pay,$args,$today);
        $state['clients'][$i]['opening']['payments'][]=$pay;
    }else throw new DomainException('Operación desconocida.');
    validate_state($state);return $state;
}
function operation_index(array $list,mixed $id,string $error): int {foreach($list as $i=>$o)if(($o['id']??null)===$id&&is_string($id))return $i;throw new DomainException($error);}
// Aplica cambios de cobro. null elimina el campo opcional. Una fecha límite y una ventana se excluyen entre sí.
function operation_charge_patch(array &$c,array $a): void {
    foreach(['amount','title','period','start','end','due','collectionWindow','collectionDay','collectionPending','payerId','paymentGroup','note'] as $k){if(!array_key_exists($k,$a))continue;$v=$a[$k];
        if($v===null){ensure(!in_array($k,['amount','title','period','start','end'],true),'Ese campo no se puede vaciar: '.$k);if($k==='due')$c['due']=null;else unset($c[$k]);continue;}
        $c[$k]=$v;}
    if(isset($a['collectionWindow'])&&!array_key_exists('due',$a))$c['due']=null;
    if(isset($a['due'])&&!array_key_exists('collectionWindow',$a))unset($c['collectionWindow']);
    if(isset($a['payerId'])&&!isset($c['paymentGroup']))throw new DomainException('Con pagador indica paymentGroup (SEO o Ads).');
    if(array_key_exists('payerId',$a)&&$a['payerId']===null)unset($c['paymentGroup']);
}
function operation_received_month(array &$p,array $a,string $today): void {
    $m=$a['receivedMonth']??null;ensure(valid_month($m)&&$m<=substr($today,0,7),'Mes de recepción YYYY-MM no válido.');
    ensure(($p['date']??null)===null||substr($p['date'],0,7)===$m,'El mes no coincide con la fecha de recepción.');
    $basis=$a['receivedMonthBasis']??'confirmed';ensure(in_array($basis,['confirmed','approximate'],true),'Base del mes: confirmed o approximate.');
    $p['receivedMonth']=$m;$p['receivedMonthBasis']=$basis;$p['receivedTiming']='Mes de recepción indicado mediante MCP; sin día exacto.';
}
function mb_safe_lower(string $s): string { return function_exists('mb_strtolower')?mb_strtolower(trim($s),'UTF-8'):strtolower(strtr(trim($s),['Á'=>'á','É'=>'é','Í'=>'í','Ó'=>'ó','Ú'=>'ú','Ñ'=>'ñ','Ü'=>'ü'])); }
function operation_charge_balance(array $s,array $c): float { $paid=0;foreach($s['payments'] as $p)if($p['chargeId']===$c['id'])$paid+=$p['amount'];return max(0,round($c['amount']-$paid,2)); }
function record_operation_payment(array &$s,array $a): void {
    $charge=null;foreach($s['charges'] as $c)if($c['id']===($a['chargeId']??null))$charge=$c;ensure($charge!==null&&!($charge['cancelled']??false),'Cobro no encontrado.');
    $amount=$a['amount']??null;ensure(valid_amount($amount)&&abs($amount-round($amount,2))<.0000001&&$amount<=operation_charge_balance($s,$charge)+.009,'Importe inválido o mayor que el saldo.');
    $date=$a['receiptDate']??null;ensure($date===null||valid_date($date)&&$date<=$s['settings']['today'],'Fecha de recepción inválida.');
    $mxn=$a['mxn']??null;ensure($mxn===null||(is_int($mxn)||is_float($mxn))&&$mxn>0,'Conversión no válida.');
    // MXN is already native currency. Foreign currency requires explicit received MXN.
    if($charge['currency']==='MXN')$mxn=$amount;
    $p=['id'=>operation_id(),'chargeId'=>$charge['id'],'amount'=>$amount,'date'=>$date,'dateStatus'=>$date===null?'unknown':'known','note'=>(string)($a['note']??''),'requestId'=>operation_id(),'conversionStatus'=>$mxn===null?'unknown':'known','rate'=>$mxn===null?null:round($mxn/$amount,8),'rateDate'=>$mxn===null?null:$date,'mxn'=>$mxn];
    ensure(!($date===null&&$mxn!==null&&$charge['currency']!=='MXN'),'Para moneda extranjera sin fecha, conserva conversión desconocida.');if(($a['receivedMonth']??null)!==null){ensure($date===null,'Con fecha exacta no hace falta el mes de recepción.');operation_received_month($p,$a,$s['settings']['today']);}if(isset($a['groupReceiptId']))$p['groupReceiptId']=$a['groupReceiptId'];$s['payments'][]=$p;
}
