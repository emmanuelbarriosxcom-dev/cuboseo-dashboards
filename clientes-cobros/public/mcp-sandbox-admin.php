<?php
declare(strict_types=1);
require __DIR__.'/../private/bootstrap.php';require __DIR__.'/../private/validate.php';require __DIR__.'/../private/storage.php';
try{headers_private();$u=require_user();
 if($_SERVER['REQUEST_METHOD']==='POST'){
 require_csrf($_POST['csrf']??null);$pdo=db();foreach(['state','operations','snapshots','audit'] as $table)$pdo->exec('CREATE TABLE IF NOT EXISTS cubo_mcp_test_'.$table.' LIKE cubo_'.$table);
 $exists=$pdo->query('SELECT COUNT(*) FROM cubo_mcp_test_state')->fetchColumn();ensure((int)$exists===0,'El entorno ya existe. No se reemplazarán sus datos.');
 $s=empty_state();$s['settings']['billingPaused']=true;$s['clients']=[['id'=>'qa_client','name'=>'Cliente ficticio MCP','status'=>'active','notes'=>'Entorno aislado. Sin clientes reales.'],['id'=>'qa_payer','name'=>'Pagador ficticio MCP','status'=>'active','notes'=>'Solo pruebas.']];
 $s['services']=[['id'=>'qa_service','clientId'=>'qa_client','name'=>'SEO ficticio','amount'=>100,'currency'=>'MXN','kind'=>'recurring','start'=>date('Y-m-01'),'frequency'=>'monthly','mode'=>'arrears','cycle'=>'calendar','dueDay'=>15,'windows'=>[['start'=>date('Y-m-01'),'end'=>null]],'tariffs'=>[]]];
 $month=date('Y-m');$end=(new DateTimeImmutable($month.'-01'))->format('Y-m-t');$next=(new DateTimeImmutable($month.'-01'))->modify('+1 month')->format('Y-m');$s['charges']=[['id'=>'qa_charge','serviceId'=>'qa_service','clientId'=>'qa_client','title'=>'SEO ficticio de prueba','amount'=>100,'currency'=>'MXN','period'=>$month,'start'=>$month.'-01','end'=>$end,'due'=>null,'payerId'=>'qa_payer','paymentGroup'=>'SEO','collectionWindow'=>['start'=>$next.'-01','end'=>$next.'-05']]];validate_state($s);
 $pdo->prepare('INSERT INTO cubo_mcp_test_state (id,revision,payload,updated_at) VALUES (1,0,?,UTC_TIMESTAMP())')->execute([json_encode($s,JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR)]);
 page('Entorno MCP de prueba preparado','<p>Dos fichas y un cobro ficticios. Almacenamiento en tablas independientes. No se copiaron ni modificaron datos reales.</p><p>Conecta ChatGPT a <code>'.e(config()['app_origin'].'/mcp-test.php').'</code>.</p>');exit;
 }
 page('Preparar prueba privada de ChatGPT','<p>Creará un entorno vacío independiente con dos fichas y un cobro ficticios. Reutiliza autenticación, pero nunca copia los clientes reales. No reinicia un entorno existente.</p><form method="post"><input type="hidden" name="csrf" value="'.e(csrf()).'"><button>Crear entorno ficticio una vez</button></form>');
}catch(Throwable $e){http_response_code(400);page('Prueba no preparada','<p>'.e($e instanceof DomainException?$e->getMessage():'No se pudo preparar el entorno.').'</p>');}
