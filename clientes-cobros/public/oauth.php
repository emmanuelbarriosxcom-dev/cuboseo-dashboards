<?php
declare(strict_types=1);
require __DIR__.'/../private/bootstrap.php';require __DIR__.'/../private/validate.php';require __DIR__.'/../private/mcp-security.php';require __DIR__.'/../private/oauth.php';
try {
 headers_private();$action=$_GET['action']??'';
 if($action==='metadata')json_response(oauth_metadata());
 if($action==='resource'){if(($_GET['sandbox']??'')==='1')define('CUBO_MCP_TEST',true);json_response(['resource'=>mcp_resource(),'authorization_servers'=>[config()['app_origin']],'scopes_supported'=>['cubo:read','cubo:write'],'bearer_methods_supported'=>['header']]);}
 if($action==='register'){
  ensure($_SERVER['REQUEST_METHOD']==='POST','Método no permitido.');mcp_rate_limit('register',20);$raw=file_get_contents('php://input');ensure(strlen($raw)<=16000,'Registro demasiado grande.');$a=json_decode($raw,true,32,JSON_THROW_ON_ERROR);ensure(is_array($a)&&is_array($a['redirect_uris']??null)&&count($a['redirect_uris'])>=1&&count($a['redirect_uris'])<=3,'Redirects requeridos.');foreach($a['redirect_uris'] as $url)ensure(oauth_redirect_allowed($url),'Solo redirect oficial ChatGPT.');ensure(($a['token_endpoint_auth_method']??'none')==='none','Este cliente utiliza PKCE público.');$id='chatgpt_'.mcp_random();$c=['client_id'=>$id,'redirect_uris'=>$a['redirect_uris'],'client_name'=>'ChatGPT · Cubo SEO','token_endpoint_auth_method'=>'none','grant_types'=>['authorization_code'],'response_types'=>['code']];mcp_store(function(&$s)use($id,$c){ensure(count($s['clients'])<100,'Registro temporal completo.');$s['clients'][$id]=$c;});json_response($c,201);
 }
 if($action==='token'){ensure($_SERVER['REQUEST_METHOD']==='POST','Método no permitido.');mcp_rate_limit('token',120);json_response(oauth_exchange($_POST));}
 if($action==='revoke'){ensure($_SERVER['REQUEST_METHOD']==='POST','Método no permitido.');mcp_rate_limit('revoke',60);mcp_store(function(&$s){unset($s['tokens'][hash('sha256',$_POST['token']??'')]);});json_response([]);}
 ensure($action==='authorize','Ruta no disponible.');oauth_session_boot();
 if($_SERVER['REQUEST_METHOD']==='GET'){oauth_pending_begin($_GET);}
 $a=oauth_pending_get($_SERVER['REQUEST_METHOD']==='POST'?($_POST['oauth_flow']??''):null);
 // The validated OAuth callback must remain reachable after a consent POST.
 // Other application pages retain their original self-only form policy.
 header("Content-Security-Policy: default-src 'self'; script-src 'self' 'unsafe-inline'; style-src 'self' 'unsafe-inline'; img-src 'self' data:; font-src 'self' data:; connect-src 'self'; object-src 'none'; base-uri 'none'; frame-ancestors 'none'; form-action 'self' ".$a['redirect_uri']);
 if($_SERVER['REQUEST_METHOD']==='POST'){
  require_csrf($_POST['csrf']??null);
  if(isset($_POST['username'])){ensure(limit_login($_POST['username']),'Demasiados intentos. Espera 15 minutos.');$q=db()->prepare('SELECT id,username,password_hash FROM cubo_users WHERE username=?');$q->execute([trim($_POST['username'])]);$u=$q->fetch();ensure($u&&password_verify($_POST['password']??'',$u['password_hash']),'Acceso no válido.');session_regenerate_id(true);$_SESSION['user_id']=(int)$u['id'];$_SESSION['password_stamp']=hash('sha256',$u['password_hash']);clear_login_limits($u['username']);}
  if(($_POST['consent']??'')==='approve'){$u=require_user();$code=oauth_issue_code($a,$u);oauth_pending_clear();header('Location: '.$a['redirect_uri'].'?'.http_build_query(['code'=>$code,'state'=>$a['state'],'iss'=>config()['app_origin']]),true,303);exit;}
  if(($_POST['consent']??'')==='deny'){oauth_pending_clear();header('Location: '.$a['redirect_uri'].'?'.http_build_query(['error'=>'access_denied','state'=>$a['state'],'iss'=>config()['app_origin']]),true,303);exit;}
 }
 if(!isset($_SESSION['user_id'])){page('Autorizar ChatGPT','<p>Accede con tu cuenta existente de Clientes y Cobros. No se crea otra cuenta.</p><form method="post" action="oauth.php?action=authorize"><input type="hidden" name="oauth_flow" value="'.e($_SESSION['oauth_flow']).'"><input type="hidden" name="csrf" value="'.e(csrf()).'"><label>Usuario<input name="username" autocomplete="username" required></label><label>Contraseña<input name="password" type="password" autocomplete="current-password" required></label><button>Continuar</button></form>');exit;}
 $u=require_user();$sandbox=str_ends_with($a['resource'],'/mcp-test.php');page('Conectar ChatGPT','<p>Cuenta: '.e($u['username']).'. Entorno: <strong>'.($sandbox?'PRUEBA · datos ficticios':'PRODUCCIÓN · clientes reales').'</strong>.</p><p>Permisos: '.e(implode(', ',$a['scopes'])).'. Tokens duran una hora. Cada escritura requiere además aprobar su propuesta en esta web. Puedes rechazar la conexión.</p><form method="post" action="oauth.php?action=authorize"><input type="hidden" name="oauth_flow" value="'.e($_SESSION['oauth_flow']).'"><input type="hidden" name="csrf" value="'.e(csrf()).'"><button name="consent" value="approve">Autorizar estos permisos</button><button name="consent" value="deny">Cancelar</button></form>');
} catch(Throwable $e){http_response_code(400);if(in_array($_GET['action']??'',['register','token','revoke'],true))json_response(['error'=>'invalid_request','error_description'=>$e instanceof DomainException?$e->getMessage():'No se pudo completar OAuth.'],400);page('No se pudo autorizar','<p>'.e($e instanceof DomainException?$e->getMessage():'La autorización no está disponible.').'</p>');}
