<?php
declare(strict_types=1);
require __DIR__ . '/../private/bootstrap.php';
require __DIR__ . '/../private/validate.php';
require __DIR__ . '/../private/mcp-auth.php';

function oauth_form_error(string $message, int $status = 400): never {
    http_response_code($status);
    page('Acceso MCP', '<p class="error" role="alert">' . e($message) . '</p><p><a href="/">Volver a la herramienta</a></p>');
    exit;
}
function oauth_json_body(): array {
    if (stripos((string)($_SERVER['CONTENT_TYPE'] ?? ''), 'application/json') !== 0) mcp_oauth_error('invalid_request',415,'Se requiere application/json.');
    $raw = file_get_contents('php://input', false, null, 0, 16385);
    if (!is_string($raw) || strlen($raw) > 16384) mcp_oauth_error('invalid_request',413,'La solicitud supera el límite permitido.');
    try { $value = json_decode($raw, true, 32, JSON_THROW_ON_ERROR); }
    catch (JsonException) { mcp_oauth_error('invalid_request',400,'JSON no válido.'); }
    if (!is_array($value) || array_is_list($value)) mcp_oauth_error('invalid_request',400,'Objeto JSON requerido.');
    return $value;
}
function oauth_form_values(array $source): array {
    $keys = ['client_id','redirect_uri','response_type','scope','state','resource','code_challenge','code_challenge_method'];
    $out=[];
    foreach ($keys as $key) {
        $value=$source[$key]??null;
        if ($key==='scope' && $value===null) $value='clients:read';
        if (!is_string($value) || strlen($value)>1000) throw new DomainException('La solicitud de autorización no es válida.');
        $out[$key]=$value;
    }
    return $out;
}
function oauth_render_login(array $request, string $error = ''): never {
    session_boot();
    $msg = $error !== '' ? '<p class="error" role="alert">'.e($error).'</p>' : '';
    $content = $msg . '<p>Cubo SEO solicita permiso para consultar los datos privados de clientes y cobros.</p><form method="post" action="/oauth.php?action=authorize">'
        . '<input type="hidden" name="csrf" value="'.e(csrf()).'"><input type="hidden" name="step" value="login">'
        . mcp_oauth_hidden($request)
        . '<label>Usuario<input name="username" autocomplete="username" required maxlength="80" autocapitalize="none" spellcheck="false"></label>'
        . '<label>Contraseña<input name="password" type="password" autocomplete="current-password" required maxlength="200"></label>'
        . '<button>Continuar</button></form><p class="muted">La autorización se limita a esta herramienta de consulta y puedes revocarla desde la cuenta.</p>';
    page('Autorizar conexión segura', $content);
    exit;
}
function oauth_render_consent(array $request, array $user): never {
    session_boot();
    $permission = e(mcp_scope_label('clients:read'));
    $content = '<p>Has iniciado sesión como <strong>'.e((string)$user['username']).'</strong>.</p>'
        . '<p><strong>'.e((string)$request['client']['client_name']).'</strong> solicita permiso para:</p>'
        . '<ul><li>'.$permission.'</li></ul>'
        . '<p>Esta conexión solo podrá leer información. No puede crear ni modificar registros.</p>'
        . '<form method="post" action="/oauth.php?action=authorize">'
        . '<input type="hidden" name="csrf" value="'.e(csrf()).'"><input type="hidden" name="step" value="consent">'
        . mcp_oauth_hidden($request)
        . '<button name="consent" value="approve">Autorizar lectura</button> '
        . '<button class="secondary" name="consent" value="deny">Denegar</button></form>';
    page('Permiso para Cubo SEO', $content);
    exit;
}
function oauth_redirect_client(string $redirectUri, array $params): never {
    $params['iss']=mcp_issuer();
    header('Location: '.$redirectUri.'?'.http_build_query($params, '', '&', PHP_QUERY_RFC3986), true, 303);
    exit;
}
function oauth_authorize_endpoint(): never {
    session_boot();
    $method=(string)($_SERVER['REQUEST_METHOD']??'GET');
    if ($method==='GET') {
        try { $request=mcp_authorization_request(oauth_form_values($_GET)); }
        catch (Throwable $ex) { oauth_form_error('La solicitud de autorización no pasó la validación.'); }
        $user=mcp_current_session_user();
        if (!$user) oauth_render_login($request);
        oauth_render_consent($request,$user);
    }
    if ($method!=='POST') { header('Allow: GET, POST'); oauth_form_error('Método no permitido.',405); }
    try { require_csrf(is_string($_POST['csrf']??null)?$_POST['csrf']:null); $form=oauth_form_values($_POST); $request=mcp_authorization_request($form); }
    catch (Throwable $ex) { oauth_form_error('La solicitud de autorización no pasó la validación.'); }
    $user=mcp_current_session_user();
    if (($_POST['step']??'')==='login') {
        if ($user) oauth_render_consent($request,$user);
        $user=mcp_authenticate_password((string)($_POST['username']??''),(string)($_POST['password']??''));
        if (!$user) oauth_render_login($request,'El usuario o la contraseña no son correctos. Si hubo varios intentos, espera 15 minutos.');
        oauth_render_consent($request,$user);
    }
    if (($_POST['step']??'')!=='consent' || !$user) oauth_render_login($request);
    if (($_POST['consent']??'')==='deny') oauth_redirect_client($request['redirect_uri'],['error'=>'access_denied','state'=>$request['state']]);
    if (($_POST['consent']??'')!=='approve') oauth_form_error('Selecciona autorizar o denegar.');
    $code=mcp_b64url(random_bytes(32));
    $q=db()->prepare('INSERT INTO cubo_oauth_codes (code_hash,client_id,user_id,redirect_uri,resource,scopes,code_challenge,password_stamp,created_at,expires_at,consumed_at) VALUES (?,?,?,?,?,?,?,?,UTC_TIMESTAMP(),DATE_ADD(UTC_TIMESTAMP(),INTERVAL 5 MINUTE),NULL)');
    $q->execute([hash('sha256',$code),$request['client_id'],$user['id'],$request['redirect_uri'],$request['resource'],$request['scope_text'],$request['code_challenge'],(string)$_SESSION['password_stamp']]);
    oauth_redirect_client($request['redirect_uri'],['code'=>$code,'state'=>$request['state']]);
}
function oauth_register_endpoint(): never {
    if (($_SERVER['REQUEST_METHOD']??'')!=='POST') { header('Allow: POST'); mcp_oauth_error('invalid_request',405); }
    if (!limit_login('mcp-register')) mcp_oauth_error('temporarily_unavailable',429,'Espera antes de volver a registrar un cliente.');
    $input=oauth_json_body();
    $name=$input['client_name']??'Cubo SEO MCP';
    $redirects=$input['redirect_uris']??null;
    ensure(is_string($name) && trim($name)!=='' && strlen($name)<=100,'Nombre del cliente no válido.');
    ensure(is_array($redirects) && array_is_list($redirects) && count($redirects)>=1 && count($redirects)<=3,'Lista de URLs de retorno no válida.');
    foreach ($redirects as $uri) ensure(is_string($uri) && mcp_redirect_allowed($uri),'La URL de retorno no está permitida.');
    $redirects=array_values(array_unique($redirects));
    if (isset($input['token_endpoint_auth_method'])) ensure($input['token_endpoint_auth_method']==='none','Solo se admiten clientes públicos con PKCE.');
    if (isset($input['response_types'])) ensure(is_array($input['response_types']) && in_array('code',$input['response_types'],true),'Solo se admite authorization code.');
    $grants=$input['grant_types']??['authorization_code'];
    ensure(is_array($grants) && array_is_list($grants) && in_array('authorization_code',$grants,true) && array_diff($grants,['authorization_code','refresh_token'])===[],'Tipo de concesión no compatible.');
    $grants=array_values(array_unique($grants));
    $clientId=mcp_b64url(random_bytes(32));
    $q=db()->prepare('INSERT INTO cubo_oauth_clients (client_id,client_name,redirect_uris,grant_types,disabled_at,created_at) VALUES (?,?,?,?,NULL,UTC_TIMESTAMP())');
    $q->execute([$clientId,trim($name),json_encode($redirects,JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR),json_encode($grants,JSON_THROW_ON_ERROR)]);
    mcp_oauth_json(['client_id'=>$clientId,'client_id_issued_at'=>time(),'client_name'=>trim($name),'redirect_uris'=>$redirects,'token_endpoint_auth_method'=>'none','grant_types'=>$grants,'response_types'=>['code'],'scope'=>implode(' ',mcp_supported_scopes())],201);
}
function oauth_token_endpoint(): never {
    if (($_SERVER['REQUEST_METHOD']??'')!=='POST') { header('Allow: POST'); mcp_oauth_error('invalid_request',405); }
    if (stripos((string)($_SERVER['CONTENT_TYPE']??''),'application/x-www-form-urlencoded')!==0) mcp_oauth_error('invalid_request',415,'Se requiere application/x-www-form-urlencoded.');
    if (isset($_POST['client_secret']) || isset($_SERVER['PHP_AUTH_USER'])) mcp_oauth_error('invalid_client',401,'Este servidor acepta clientes públicos con PKCE.');
    $clientId=is_string($_POST['client_id']??null)?$_POST['client_id']:'';
    $resource=is_string($_POST['resource']??null)?$_POST['resource']:'';
    $client=mcp_load_oauth_client($clientId);
    if ($client===null || $resource!==mcp_resource_id()) mcp_oauth_error('invalid_grant');
    $pdo=db();
    try {
        $pdo->beginTransaction();
        if (($_POST['grant_type']??'')==='authorization_code') {
            $code=is_string($_POST['code']??null)?$_POST['code']:'';
            $redirect=is_string($_POST['redirect_uri']??null)?$_POST['redirect_uri']:'';
            $verifier=is_string($_POST['code_verifier']??null)?$_POST['code_verifier']:'';
            if (!preg_match('/^[A-Za-z0-9_-]{40,100}$/D',$code) || preg_match('/^[A-Za-z0-9._~-]{43,128}$/D',$verifier)!==1) { $pdo->rollBack(); mcp_oauth_error('invalid_grant'); }
            $q=$pdo->prepare('SELECT c.client_id,c.user_id,c.redirect_uri,c.resource,c.scopes,c.code_challenge,c.password_stamp,c.consumed_at,c.expires_at,u.password_hash FROM cubo_oauth_codes c JOIN cubo_users u ON u.id=c.user_id WHERE c.code_hash=? FOR UPDATE');
            $q->execute([hash('sha256',$code)]); $row=$q->fetch();
            $challenge=mcp_b64url(hash('sha256',$verifier,true));
            if (!$row || $row['consumed_at']!==null || strtotime((string)$row['expires_at'].' UTC')<=time() || !hash_equals((string)$row['client_id'],$clientId) || !hash_equals((string)$row['redirect_uri'],$redirect) || !hash_equals((string)$row['resource'],$resource) || !hash_equals((string)$row['code_challenge'],$challenge) || !hash_equals((string)$row['password_stamp'],hash('sha256',(string)$row['password_hash']))) { $pdo->rollBack(); mcp_oauth_error('invalid_grant'); }
            $pdo->prepare('UPDATE cubo_oauth_codes SET consumed_at=UTC_TIMESTAMP() WHERE code_hash=? AND consumed_at IS NULL')->execute([hash('sha256',$code)]);
            $tokens=mcp_issue_token_pair($pdo,$clientId,(int)$row['user_id'],$resource,(string)$row['scopes'],in_array('refresh_token',$client['grant_types'],true));
            $pdo->commit();
            mcp_oauth_json($tokens);
        }
        if (($_POST['grant_type']??'')==='refresh_token') {
            if (!in_array('refresh_token',$client['grant_types'],true)) { $pdo->rollBack(); mcp_oauth_error('unsupported_grant_type'); }
            $refresh=is_string($_POST['refresh_token']??null)?$_POST['refresh_token']:'';
            if (!preg_match('/^[A-Za-z0-9_-]{50,100}$/D',$refresh)) { $pdo->rollBack(); mcp_oauth_error('invalid_grant'); }
            $hash=hash('sha256',$refresh);
            $q=$pdo->prepare("SELECT t.family_id,t.client_id,t.user_id,t.resource,t.scopes,t.password_stamp,t.expires_at,t.revoked_at,u.password_hash FROM cubo_oauth_tokens t JOIN cubo_users u ON u.id=t.user_id WHERE t.token_hash=? AND t.token_type='refresh' FOR UPDATE");
            $q->execute([$hash]); $row=$q->fetch();
            if (!$row || !hash_equals((string)$row['client_id'],$clientId) || !hash_equals((string)$row['resource'],$resource)) { $pdo->rollBack(); mcp_oauth_error('invalid_grant'); }
            if ($row['revoked_at']!==null || strtotime((string)$row['expires_at'].' UTC')<=time() || !hash_equals((string)$row['password_stamp'],hash('sha256',(string)$row['password_hash']))) {
                $pdo->prepare('UPDATE cubo_oauth_tokens SET revoked_at=COALESCE(revoked_at,UTC_TIMESTAMP()) WHERE family_id=?')->execute([$row['family_id']]);
                $pdo->commit(); mcp_oauth_error('invalid_grant');
            }
            $pdo->prepare('UPDATE cubo_oauth_tokens SET revoked_at=UTC_TIMESTAMP() WHERE token_hash=? AND revoked_at IS NULL')->execute([$hash]);
            $tokens=mcp_issue_token_pair($pdo,$clientId,(int)$row['user_id'],$resource,(string)$row['scopes'],true,(string)$row['family_id']);
            $pdo->commit();
            mcp_oauth_json($tokens);
        }
        $pdo->rollBack(); mcp_oauth_error('unsupported_grant_type');
    } catch (Throwable $ex) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        error_log('Cubo MCP OAuth token: '.$ex->getMessage());
        mcp_oauth_error('server_error',503);
    }
}

try {
    headers_private();
    $action=(string)($_GET['action']??'');
    if ($action==='metadata') {
        if (($_SERVER['REQUEST_METHOD']??'GET')!=='GET') mcp_oauth_error('invalid_request',405);
        header('Cache-Control: public, max-age=3600');
        mcp_oauth_json(['issuer'=>mcp_issuer(),'authorization_response_iss_parameter_supported'=>true,'authorization_endpoint'=>mcp_origin().'/oauth.php?action=authorize','token_endpoint'=>mcp_origin().'/oauth.php?action=token','registration_endpoint'=>mcp_origin().'/oauth.php?action=register','scopes_supported'=>mcp_supported_scopes(),'response_types_supported'=>['code'],'grant_types_supported'=>['authorization_code','refresh_token'],'token_endpoint_auth_methods_supported'=>['none'],'code_challenge_methods_supported'=>['S256']]);
    }
    if ($action==='resource_metadata') {
        if (($_SERVER['REQUEST_METHOD']??'GET')!=='GET') mcp_oauth_error('invalid_request',405);
        header('Cache-Control: public, max-age=3600');
        mcp_oauth_json(['resource'=>mcp_resource_id(),'authorization_servers'=>[mcp_issuer()],'scopes_supported'=>mcp_supported_scopes()]);
    }
    if ($action==='register') oauth_register_endpoint();
    if ($action==='authorize') oauth_authorize_endpoint();
    if ($action==='token') oauth_token_endpoint();
    mcp_oauth_error('invalid_request',404);
} catch (DomainException $ex) {
    mcp_oauth_error('invalid_request',400,$ex->getMessage());
} catch (Throwable $ex) {
    error_log('Cubo MCP OAuth: '.$ex->getMessage());
    mcp_oauth_error('server_error',503);
}
