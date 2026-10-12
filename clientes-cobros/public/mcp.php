<?php
declare(strict_types=1);
require __DIR__.'/../private/bootstrap.php';require __DIR__.'/../private/validate.php';require __DIR__.'/../private/storage.php';require __DIR__.'/../private/operations.php';require __DIR__.'/../private/mcp-security.php';require __DIR__.'/../private/mcp-tools.php';
headers_private();
try{$user=mcp_token_user();}catch(Throwable $e){header('WWW-Authenticate: Bearer resource_metadata="'.config()['app_origin'].'/oauth.php?action=resource'.(defined('CUBO_MCP_TEST')?'&sandbox=1':'').'"');json_response(['error'=>'OAuth requerido.'],401);}
if($_SERVER['REQUEST_METHOD']!=='POST'){header('Allow: POST');json_response(['error'=>'Este transporte utiliza POST JSON.'],405);}
try{
 mcp_rate_limit('mcp-'.$user['id'],500);$raw=file_get_contents('php://input');ensure(strlen($raw)<=100000,'Solicitud demasiado grande.');$r=json_decode($raw,true,32,JSON_THROW_ON_ERROR);ensure(is_array($r)&&($r['jsonrpc']??null)==='2.0'&&is_string($r['method']??null),'JSON-RPC inválido.');$id=$r['id']??null;
 if($r['method']==='notifications/initialized'){http_response_code(202);exit;}
 ensure(array_key_exists('id',$r),'Notificación desconocida.');
 if($r['method']==='initialize'){$result=['protocolVersion'=>'2025-06-18','capabilities'=>['tools'=>new stdClass()],'serverInfo'=>['name'=>'Cubo SEO Clientes y Cobros'.(defined('CUBO_MCP_TEST')?' · PRUEBA':''),'version'=>'2.0.0']];}
 elseif($r['method']==='ping')$result=new stdClass();
 elseif($r['method']==='tools/list'){$tools=mcp_tools();foreach($tools as &$tool)$tool['_meta']['securitySchemes']=$tool['securitySchemes'];unset($tool);$result=['tools'=>$tools];}
 elseif($r['method']==='tools/call'){try{$p=$r['params']??[];ensure(is_string($p['name']??null)&&is_array($p['arguments']??[]),'Herramienta incompleta.');$out=mcp_call($p['name'],$p['arguments']??[],$user);$result=['content'=>[['type'=>'text','text'=>json_encode($out,JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR)]],'structuredContent'=>$out,'isError'=>false];}catch(Throwable $e){$result=['content'=>[['type'=>'text','text'=>$e instanceof DomainException?$e->getMessage():'Operación no disponible. No se modificaron los datos.']],'isError'=>true];}}
 else {json_response(['jsonrpc'=>'2.0','id'=>$id,'error'=>['code'=>-32601,'message'=>'Método no disponible.']],200);}
 json_response(['jsonrpc'=>'2.0','id'=>$id,'result'=>$result]);
}catch(Throwable $e){json_response(['jsonrpc'=>'2.0','id'=>$r['id']??null,'error'=>['code'=>-32600,'message'=>'Solicitud no válida.']],400);}
