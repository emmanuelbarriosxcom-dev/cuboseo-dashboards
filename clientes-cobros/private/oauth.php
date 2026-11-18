<?php
declare(strict_types=1);
// OAuth has its own cookie: a cross-site top-level entry must not replace
// the application's Strict session. CSRF and Origin checks still protect POST.
function oauth_session_boot(): void {
    if (session_status() === PHP_SESSION_ACTIVE) return;
    $c=config();session_name('CUBOOAUTHSESSID');
    ini_set('session.use_strict_mode','1');ini_set('session.use_only_cookies','1');
    ini_set('session.gc_maxlifetime','28800');
    session_set_cookie_params(['lifetime'=>0,'path'=>'/oauth.php','secure'=>!($c['allow_http_local']??false),'httponly'=>true,'samesite'=>'Lax']);
    session_start();
    if(isset($_SESSION['last_activity'])&&time()-$_SESSION['last_activity']>28800){$_SESSION=[];session_regenerate_id(true);}
    $_SESSION['last_activity']=time();$_SESSION['csrf']??=bin2hex(random_bytes(32));
}
function oauth_pending_begin(array $request): void {
    $_SESSION['oauth_pending']=oauth_authorization($request);
    $_SESSION['oauth_flow']=bin2hex(random_bytes(24));
    $_SESSION['oauth_started']=time();
}
function oauth_pending_get(?string $flow=null): array {
    $a=$_SESSION['oauth_pending']??null;
    ensure(is_array($a),'Falta la sesión de autorización. Inicia una conexión nueva desde el chat.');
    ensure(isset($_SESSION['oauth_started'])&&time()-$_SESSION['oauth_started']<=1200,'La solicitud de autorización caducó. Inicia una conexión nueva desde el chat.');
    if($flow!==null)ensure(isset($_SESSION['oauth_flow'])&&hash_equals($_SESSION['oauth_flow'],$flow),'La solicitud cambió en otra pestaña. Inicia una conexión nueva desde el chat.');
    return oauth_authorization($a);
}
function oauth_pending_clear(): void {unset($_SESSION['oauth_pending'],$_SESSION['oauth_flow'],$_SESSION['oauth_started']);}
function oauth_metadata(): array {$o=config()['app_origin'];return ['issuer'=>$o,'authorization_endpoint'=>$o.'/oauth.php?action=authorize','token_endpoint'=>$o.'/oauth.php?action=token','registration_endpoint'=>$o.'/oauth.php?action=register','revocation_endpoint'=>$o.'/oauth.php?action=revoke','response_types_supported'=>['code'],'grant_types_supported'=>['authorization_code','refresh_token'],'code_challenge_methods_supported'=>['S256'],'token_endpoint_auth_methods_supported'=>['none'],'scopes_supported'=>['cubo:read','cubo:write'],'authorization_response_iss_parameter_supported'=>true];}
// Callbacks oficiales permitidos: ChatGPT y Claude (claude.ai y claude.com).
function oauth_redirect_allowed(mixed $u): bool {if(!is_string($u))return false;$p=parse_url($u);if(!is_array($p)||($p['scheme']??'')!=='https'||isset($p['user'])||isset($p['pass'])||isset($p['query'])||isset($p['fragment'])||isset($p['port']))return false;$host=$p['host']??'';$path=$p['path']??'';
    if($host==='chatgpt.com')return $path==='/connector_platform_oauth_redirect'||preg_match('~^/connector/oauth/[a-zA-Z0-9_-]{1,100}$~D',$path)===1;
    if($host==='claude.ai'||$host==='claude.com')return $path==='/api/mcp/auth_callback';
    return false;}
function oauth_client_label(mixed $u): string {$h=is_string($u)?(parse_url($u,PHP_URL_HOST)??''):'';return $h==='chatgpt.com'?'ChatGPT':(($h==='claude.ai'||$h==='claude.com')?'Claude':'el cliente');}
function oauth_resources(): array {return [config()['app_origin'].'/mcp.php',config()['app_origin'].'/mcp-test.php'];}
function oauth_authorization(array $a): array {
    $client=mcp_store(fn(&$s)=>$s['clients'][$a['client_id']??'']??null);ensure($client&&in_array($a['redirect_uri']??null,$client['redirect_uris'],true),'Cliente o redirect URI no autorizado.');
    ensure(($a['response_type']??null)==='code'&&($a['code_challenge_method']??null)==='S256'&&preg_match('/^[A-Za-z0-9_-]{43}$/D',$a['code_challenge']??'')===1,'PKCE S256 obligatorio.');
    ensure(in_array($a['resource']??null,oauth_resources(),true),'Recurso OAuth no permitido.');$scopes=array_values(array_unique(explode(' ',trim($a['scope']??'cubo:read'))));ensure(count($scopes)>0&&count(array_diff($scopes,['cubo:read','cubo:write']))===0,'Scopes no permitidos.');
    ensure(is_string($a['state']??null)&&strlen($a['state'])>=8&&strlen($a['state'])<=512,'State OAuth obligatorio.');return $a+['scopes'=>$scopes];
}
function oauth_issue_code(array $a,array $user): string {
    $code=mcp_random();$q=db()->prepare('SELECT password_hash FROM cubo_users WHERE id=?');$q->execute([$user['id']]);$stamp=hash('sha256',(string)$q->fetchColumn());
    $item=['clientId'=>$a['client_id'],'redirect'=>$a['redirect_uri'],'resource'=>$a['resource'],'scopes'=>$a['scopes'],'challenge'=>$a['code_challenge'],'userId'=>$user['id'],'stamp'=>$stamp,'expires'=>time()+300];mcp_store(function(&$s)use($code,$item){$s['codes'][hash('sha256',$code)]=$item;});return $code;
}
const OAUTH_ACCESS_TTL=3600;const OAUTH_REFRESH_TTL=2592000; // 1 hora / 30 días
// Emite un access token y un refresh token rotativo. Se ejecuta dentro de mcp_store.
function oauth_issue_tokens(array &$s,array $c): array {
    $token=mcp_random();$refresh=mcp_random();
    $t=$c;$t['expires']=time()+OAUTH_ACCESS_TTL;$s['tokens'][hash('sha256',$token)]=$t;
    $r=$c;$r['expires']=time()+OAUTH_REFRESH_TTL;$s['refresh'][hash('sha256',$refresh)]=$r;
    return ['access_token'=>$token,'token_type'=>'Bearer','expires_in'=>OAUTH_ACCESS_TTL,'refresh_token'=>$refresh,'scope'=>implode(' ',$c['scopes'])];
}
function oauth_exchange(array $a): array {
    if(($a['grant_type']??null)==='refresh_token')return oauth_refresh($a);
    ensure(($a['grant_type']??null)==='authorization_code'&&is_string($a['code']??null)&&preg_match('/^[A-Za-z0-9._~-]{43,128}$/D',$a['code_verifier']??'')===1,'Solicitud de token inválida.');
    return mcp_store(function(&$s)use($a){$hash=hash('sha256',$a['code']);$c=$s['codes'][$hash]??null;ensure($c&&$c['expires']>=time()&&$c['clientId']===($a['client_id']??null)&&$c['redirect']===($a['redirect_uri']??null)&&$c['resource']===($a['resource']??null),'Código OAuth inválido.');$challenge=rtrim(strtr(base64_encode(hash('sha256',$a['code_verifier'],true)),'+/','-_'),'=');ensure(hash_equals($c['challenge'],$challenge),'PKCE no coincide.');unset($s['codes'][$hash]);unset($c['challenge']);return oauth_issue_tokens($s,$c);});
}
// Refresh token de un solo uso: se rota en cada renovación. Cambiar la contraseña lo invalida.
function oauth_refresh(array $a): array {
    ensure(is_string($a['refresh_token']??null)&&preg_match('/^[A-Za-z0-9_-]{40,100}$/D',$a['refresh_token'])===1,'Solicitud de token inválida.');
    $q=db()->prepare('SELECT password_hash FROM cubo_users WHERE id=?');
    return mcp_store(function(&$s)use($a,$q){$hash=hash('sha256',$a['refresh_token']);$c=$s['refresh'][$hash]??null;
        ensure(is_array($c)&&$c['expires']>=time()&&$c['clientId']===($a['client_id']??null),'Refresh token inválido.');
        ensure(!isset($a['resource'])||$a['resource']===''||$a['resource']===$c['resource'],'Recurso OAuth no permitido.');
        unset($s['refresh'][$hash]);
        $q->execute([$c['userId']]);ensure(hash_equals($c['stamp'],hash('sha256',(string)$q->fetchColumn())),'Autorización revocada.');
        if(isset($a['scope'])&&is_string($a['scope'])&&trim($a['scope'])!==''){$req=array_values(array_unique(explode(' ',trim($a['scope']))));ensure(count(array_diff($req,$c['scopes']))===0,'Scopes no permitidos.');$c['scopes']=$req;}
        return oauth_issue_tokens($s,$c);});
}
