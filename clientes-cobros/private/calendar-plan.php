<?php
declare(strict_types=1);
function apply_calendar_plan(array $state,array $plan): array {
 operation_keys($plan,['expectedCounts','services','aliases','activate']);ensure(($plan['activate']??null)===true,'Activación explícita requerida.');
 foreach(['clients','services','charges','payments'] as $k)ensure(count($state[$k])===($plan['expectedCounts'][$k]??null),'La cantidad actual cambió: '.$k);
 $by=array_column($state['services'],null,'id');ensure(is_array($plan['services']??null)&&count($plan['services'])<=100,'Plan inválido.');$seen=[];
 foreach($plan['services'] as $patch){operation_keys($patch,['id','amount','currency','automation','name','type']);$id=$patch['id']??'';ensure(isset($by[$id])&&!isset($seen[$id])&&$by[$id]['amount']===$patch['amount']&&$by[$id]['currency']===$patch['currency'],'Servicio o tarifa cambió.');$seen[$id]=true;foreach($state['services'] as &$s)if($s['id']===$id){$s['automation']=$patch['automation'];foreach(['name','type'] as $key)if(isset($patch[$key]))$s[$key]=$patch[$key];}unset($s);}
 foreach($plan['aliases']??[] as $p){operation_keys($p,['id','aliases']);$found=false;foreach($state['clients'] as &$c)if($c['id']===($p['id']??null)){$found=true;$c['aliases']=array_values(array_unique(array_merge($c['aliases']??[],$p['aliases'])));}unset($c);ensure($found,'Alias sin ficha existente.');}
 $state['settings']['billingPaused']=false;validate_state($state);return $state;
}
