<?php
declare(strict_types=1);
// Calendar periods stay separate from collection dates. No historical reconstruction.
function billing_month_add(string $m, int $n): string { return (new DateTimeImmutable($m.'-01'))->modify(($n>=0?'+':'').$n.' months')->format('Y-m'); }
function billing_day(string $m, int $d): string { $last=(int)(new DateTimeImmutable($m.'-01'))->format('t'); return $m.'-'.str_pad((string)min($d,$last),2,'0',STR_PAD_LEFT); }
function billing_expected(array $service, string $period): array {
    $a=$service['automation'];$r=$a['collection'];$next=billing_month_add($period,1);$previous=billing_month_add($period,-1);
    $due=null;$trigger=$period.'-01';$window=null;
    if($r['kind']==='previous_day'){$due=billing_day($previous,$r['day']);$trigger=$due;}
    elseif($r['kind']==='day')$due=billing_day($service['mode']==='arrears'?$next:$period,$r['day']);
    elseif($r['kind']==='window')$window=['start'=>billing_day($next,$r['startDay']),'end'=>billing_day($next,$r['endDay'])];
    $amount=$service['amount'];$tariffs=$service['tariffs']??[];usort($tariffs,fn($x,$y)=>strcmp($x['from'],$y['from']));
    foreach($tariffs as $t)if($t['from']<=$period&&($t['scope']==='forever'||$t['from']===$period))$amount=$t['amount'];
    $q=['id'=>$service['id'].'_'.$period.'_1','serviceId'=>$service['id'],'clientId'=>$service['clientId'],'title'=>$service['name'],'period'=>$period,'start'=>$period.'-01','end'=>billing_day($period,31),'due'=>$due,'part'=>'1','amount'=>$amount,'currency'=>$service['currency'],'cancelled'=>false,'lost'=>false,'registration'=>'automatic-calendar','generationTrigger'=>$trigger];
    if($window!==null)$q['collectionWindow']=$window;
    if(isset($a['payerId'])){$q['payerId']=$a['payerId'];$q['paymentGroup']=$a['paymentGroup'];if($r['kind']==='day')$q['collectionDay']=$r['day'];}
    return $q;
}
function billing_generate(array &$state, string $today): int {
    if(($state['settings']['billingPaused']??true)!==false)return 0;
    $clients=array_column($state['clients'],null,'id');$seen=[];foreach($state['charges'] as $c)$seen[$c['serviceId'].'|'.$c['period']]=true;$count=0;
    foreach($state['services'] as $s){$a=$s['automation']??null;
        if(!$a||($a['enabled']??false)!==true||$s['kind']!=='recurring'||($clients[$s['clientId']]['status']??null)!=='active')continue;
        for($m=$a['fromPeriod'],$i=0;$m<=billing_month_add(substr($today,0,7),1)&&$i<600;$m=billing_month_add($m,1),$i++){
            $q=billing_expected($s,$m);if($q['generationTrigger']>$today||isset($seen[$s['id'].'|'.$m]))continue;
            $active=false;foreach($s['windows']??[['start'=>$s['start'],'end'=>null]] as $w)if($q['start']>=$w['start']&&(!isset($w['end'])||$q['start']<$w['end']))$active=true;
            if(!$active)continue;$state['charges'][]=$q;$seen[$s['id'].'|'.$m]=true;$count++;
        }
    }return $count;
}
