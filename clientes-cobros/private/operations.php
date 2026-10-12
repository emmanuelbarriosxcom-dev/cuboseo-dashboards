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
        operation_keys($args,['id','clientId','name','type','amount','currency','kind','start','frequency','mode','cycle','dueDay','schedulePending','automation','windows','site','brand']);
        $id=$args['id']??operation_id();$index=null;foreach($state['services'] as $i=>$v)if($v['id']===$id)$index=$i;
        if($index===null){foreach(['clientId','name','amount','currency','kind','start'] as $key)ensure(isset($args[$key]),'Falta información confirmada del proyecto: '.$key);$state['services'][]=['id'=>$id,'tariffs'=>[]];$index=count($state['services'])-1;}
        if(isset($args['automation'])){ $prior=$state['services'][$index]['automation']['fromPeriod']??null;$from=$args['automation']['fromPeriod']??'';$future=(new DateTimeImmutable(substr($today,0,7).'-01'))->modify('+1 month')->format('Y-m');ensure($from===$prior||$from>=$future,'Un calendario nuevo empieza en un mes futuro; no reconstruye histórico.');}
        foreach($args as $k=>$v)if($k!=='id')$state['services'][$index][$k]=$v;
    }elseif($action==='payment'){
        operation_keys($args,['chargeId','amount','receiptDate','mxn','note']);record_operation_payment($state,$args);
    }elseif($action==='group_payment'){
        operation_keys($args,['payerId','group','period','amount','receiptDate','note']);ensure(in_array($args['group']??null,['SEO','Ads'],true)&&valid_month($args['period']??null),'Grupo y mes obligatorios.');
        $charges=array_values(array_filter($state['charges'],fn($c)=>!($c['cancelled']??false)&&($c['payerId']??null)===($args['payerId']??null)&&($c['paymentGroup']??null)===$args['group']&&$c['period']===$args['period']));ensure(count($charges)>0,'No hay cobros del grupo.');$currencies=array_unique(array_column($charges,'currency'));ensure(count($currencies)===1,'No mezcles monedas.');$left=(float)($args['amount']??0);ensure(valid_amount($left),'Importe no válido.');$balances=[];$total=0;foreach($charges as $c){$b=operation_charge_balance($state,$c);$balances[$c['id']]=$b;$total+=$b;}ensure($left<=round($total,2)+.009,'El pago supera los pendientes del grupo.');
        usort($charges,fn($a,$b)=>strcmp($a['id'],$b['id']));$receiptId=operation_id();foreach($charges as $c){$amount=min($left,$balances[$c['id']]);if($amount<=0)continue;record_operation_payment($state,['chargeId'=>$c['id'],'amount'=>round($amount,2),'receiptDate'=>$args['receiptDate']??null,'note'=>$args['note']??'Pago agrupado confirmado','groupReceiptId'=>$receiptId]);$left=round($left-$amount,2);if($left<=0)break;}
    }else throw new DomainException('Operación desconocida.');
    validate_state($state);return $state;
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
    ensure(!($date===null&&$mxn!==null&&$charge['currency']!=='MXN'),'Para moneda extranjera sin fecha, conserva conversión desconocida.');if(isset($a['groupReceiptId']))$p['groupReceiptId']=$a['groupReceiptId'];$s['payments'][]=$p;
}
