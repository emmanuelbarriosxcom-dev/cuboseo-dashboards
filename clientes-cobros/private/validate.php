<?php
declare(strict_types=1);
function valid_date(mixed $d): bool {
    if (!is_string($d) || !preg_match('/^\d{4}-\d{2}-\d{2}$/D', $d)) return false;
    return checkdate((int)substr($d,5,2), (int)substr($d,8,2), (int)substr($d,0,4));
}
function valid_month(mixed $m): bool { return is_string($m) && preg_match('/^\d{4}-(0[1-9]|1[0-2])$/D', $m) === 1; }
function valid_amount(mixed $n): bool { return (is_int($n)||is_float($n)) && is_finite((float)$n) && $n>0 && $n<=1e9; }
function valid_day(mixed $n): bool { return is_int($n) && $n>=1 && $n<=31; }
function oneof(mixed $x, array $values): bool { return in_array($x,$values,true); }
function text_fields(array $o, array $keys): bool { foreach($keys as $k) if(isset($o[$k]) && (!is_string($o[$k]) || strlen($o[$k])>16000)) return false; return true; }
function ensure(bool $ok, string $error): void { if(!$ok) throw new DomainException($error); }
function indexed(array $list): array {
    $out=[];
    foreach($list as $o) {
        ensure(is_array($o) && isset($o['id']) && is_string($o['id']) && preg_match('/^[a-zA-Z0-9_-]{1,100}$/D',$o['id'])===1 && !isset($out[$o['id']]), 'Identificadores no válidos o duplicados.');
        $out[$o['id']]=$o;
    }
    return $out;
}
function validate_state(mixed $s): array {
    ensure(is_array($s) && ($s['version']??null)===1 && is_array($s['settings']??null), 'Copia incompatible.');
    $settings=$s['settings'];
    ensure(valid_date($settings['today']??null) && valid_date($settings['rateDate']??null) && oneof($settings['dateMode']??null,['real','simulation']), 'Fechas o modo no válidos.');
    // La instalación real no admite datos de demostración ni fechas simuladas.
    ensure($settings['dateMode']==='real' && ($settings['demo']??false)===false, 'La instalación utiliza datos reales. Retira los ejemplos y la fecha simulada antes de importar.');
    ensure(is_array($settings['rates']??null), 'Tasas no válidas.');
    foreach(['MXN','USD','EUR'] as $currency) ensure(valid_amount($settings['rates'][$currency]??null), 'Tasas no válidas.');
    ensure((float)$settings['rates']['MXN']===1.0,'La tasa MXN debe ser 1.');
    foreach(['clients','services','charges','payments','history'] as $k) ensure(is_array($s[$k]??null) && array_is_list($s[$k]) && count($s[$k])<=20000,'Colección no válida.');
    $clients=indexed($s['clients']); $services=indexed($s['services']); $charges=indexed($s['charges']); indexed($s['payments']); indexed($s['history']);
    foreach($clients as $c) ensure(is_string($c['name']??null) && trim($c['name'])!=='' && strlen($c['name'])<=400 && oneof($c['status']??null,['active','paused','inactive']) && text_fields($c,['contact','phone','email','country','category','channel','referral','dni','notes']), 'Cliente no válido.');
    foreach($services as $v) {
        ensure(isset($clients[$v['clientId']??'']) && valid_date($v['start']??null) && valid_amount($v['amount']??null) && oneof($v['currency']??null,['USD','EUR','MXN']) && oneof($v['kind']??null,['once','recurring']) && is_string($v['name']??null) && trim($v['name'])!=='' && text_fields($v,['name','type','brand','site']), 'Servicio no válido.');
        if($v['kind']==='recurring') {
            ensure(oneof($v['frequency']??null,['monthly','fortnightly']) && oneof($v['mode']??null,['advance','arrears']) && oneof($v['cycle']??null,['calendar','anniversary']) && valid_day($v['dueDay']??null),'Acuerdo no válido.');
            if($v['frequency']==='fortnightly') ensure(valid_day($v['dueDay2']??null) && $v['dueDay2']>$v['dueDay'] && $v['cycle']==='calendar','Quincenas no válidas.');
            if(isset($v['initial'])) ensure(oneof($v['initial'],['full','prorate','custom']), 'Primer periodo no válido.');
            if(($v['initial']??'')==='custom') ensure(valid_amount($v['initialAmount']??null), 'Importe inicial no válido.');
        }
        if(isset($v['windows'])) {
            ensure(is_array($v['windows']) && array_is_list($v['windows']), 'Pausas no válidas.');
            $windows=$v['windows']; usort($windows,fn($a,$b)=>strcmp($a['start']??'', $b['start']??''));
            foreach($windows as $i=>$w) {
                ensure(is_array($w) && valid_date($w['start']??null) && (!isset($w['end']) || valid_date($w['end']) && $w['end']>=$w['start']), 'Pausa no válida.');
                if($i>0) ensure(isset($windows[$i-1]['end']) && $windows[$i-1]['end']<=$w['start'], 'Pausas superpuestas.');
                if(isset($w['initial'])) ensure(oneof($w['initial'],['existing','full','prorate','custom']), 'Reactivación no válida.');
                if(($w['initial']??'')==='custom') ensure(valid_amount($w['initialAmount']??null),'Importe de reactivación no válido.');
                if(isset($w['cycleDay'])) ensure(valid_day($w['cycleDay']), 'Día de ciclo no válido.');
            }
        }
        if(isset($v['tariffs'])) {
            ensure(is_array($v['tariffs']) && array_is_list($v['tariffs']), 'Tarifas no válidas.');
            foreach($v['tariffs'] as $t) ensure(is_array($t) && valid_amount($t['amount']??null) && valid_month($t['from']??null) && oneof($t['scope']??null,['once','forever']), 'Tarifa no válida.');
        }
    }
    foreach($charges as $c) {
        $svc=$services[$c['serviceId']??'']??null;
        ensure($svc!==null && isset($clients[$c['clientId']??'']) && $svc['clientId']===$c['clientId'] && valid_amount($c['amount']??null) && ($c['currency']??'')===$svc['currency'] && valid_date($c['start']??null) && valid_date($c['end']??null) && $c['end']>=$c['start'] && valid_month($c['period']??null) && (!isset($c['due']) || valid_date($c['due'])) && is_string($c['title']??null) && strlen($c['title'])<=16000, 'Cobro no válido.');
        foreach(['cancelled','lost'] as $flag) if(isset($c[$flag])) ensure(is_bool($c[$flag]), 'Estado de cobro no válido.');
    }
    $totals=[]; $requests=[];
    foreach($s['payments'] as $p) {
        ensure(isset($charges[$p['chargeId']??'']) && valid_amount($p['amount']??null) && valid_date($p['date']??null) && $p['date']<=$settings['today'] && valid_amount($p['rate']??null) && (!isset($p['rateDate']) || valid_date($p['rateDate'])) && (!isset($p['mxn']) || (is_int($p['mxn'])||is_float($p['mxn'])) && is_finite((float)$p['mxn']) && $p['mxn']>=0 && $p['mxn']<=1e12) && text_fields($p,['note']), 'Pago no válido.');
        if(isset($p['requestId'])) {
            ensure(is_string($p['requestId']) && preg_match('/^[a-zA-Z0-9_-]{1,100}$/D',$p['requestId'])===1 && !isset($requests[$p['requestId']]), 'Referencia de pago duplicada.'); $requests[$p['requestId']]=true;
        }
        $totals[$p['chargeId']]=($totals[$p['chargeId']]??0)+$p['amount'];
    }
    foreach($charges as $c) ensure(round($totals[$c['id']]??0,2)<=$c['amount']+.009,'Los pagos superan el cobro.');
    foreach($s['history'] as $h) ensure(isset($clients[$h['clientId']??'']) && valid_date($h['date']??null) && is_string($h['text']??null) && strlen($h['text'])<=16000, 'Historial no válido.');
    return $s;
}
