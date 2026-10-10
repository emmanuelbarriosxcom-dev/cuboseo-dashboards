<?php
declare(strict_types=1);
require_once __DIR__ . '/bootstrap.php';

function mcp_origin(): string { return rtrim((string)config()['app_origin'], '/'); }
function mcp_resource_id(): string { return mcp_origin() . '/mcp.php'; }
function mcp_resource_metadata_url(): string { return mcp_origin() . '/.well-known/oauth-protected-resource/mcp.php'; }
function mcp_issuer(): string { return mcp_origin(); }
function mcp_supported_scopes(): array { return ['clients:read']; }
function mcp_scope_label(string $scope): string { return $scope === 'clients:read' ? 'Consultar clientes, servicios, cobros y pagos' : $scope; }
function mcp_b64url(string $bytes): string { return rtrim(strtr(base64_encode($bytes), '+/', '-_'), '='); }
function mcp_oauth_json(array $body, int $status = 200): never {
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');
    header('Pragma: no-cache');
    echo json_encode($body, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
    exit;
}
function mcp_oauth_error(string $error, int $status = 400, string $description = ''): never {
    $body = ['error'=>$error];
    if ($description !== '') $body['error_description'] = $description;
    mcp_oauth_json($body, $status);
}
function mcp_redirect_allowed(string $uri): bool {
    $p = parse_url($uri);
    if (!is_array($p) || ($p['scheme'] ?? '') !== 'https' || ($p['host'] ?? '') !== 'chatgpt.com' || isset($p['user']) || isset($p['pass']) || isset($p['query']) || isset($p['fragment']) || isset($p['port']) && (int)$p['port'] !== 443) return false;
    $path = $p['path'] ?? '';
    return $path === '/connector_platform_oauth_redirect' || preg_match('~^/connector/oauth/[A-Za-z0-9_-]{1,100}$~D', $path) === 1;
}
function mcp_load_oauth_client(string $clientId): ?array {
    $q = db()->prepare('SELECT client_id,client_name,redirect_uris,grant_types,disabled_at FROM cubo_oauth_clients WHERE client_id=?');
    $q->execute([$clientId]);
    $row = $q->fetch();
    if (!$row || $row['disabled_at'] !== null) return null;
    $redirects = json_decode((string)$row['redirect_uris'], true);
    $grants = json_decode((string)$row['grant_types'], true);
    if (!is_array($redirects) || !array_is_list($redirects) || !is_array($grants) || !array_is_list($grants)) return null;
    $row['redirect_uris'] = $redirects;
    $row['grant_types'] = $grants;
    return $row;
}
function mcp_authorization_request(array $input): array {
    $clientId = $input['client_id'] ?? null;
    $redirect = $input['redirect_uri'] ?? null;
    $state = $input['state'] ?? null;
    $resource = $input['resource'] ?? null;
    $scopeText = $input['scope'] ?? 'clients:read';
    $challenge = $input['code_challenge'] ?? null;
    $clientId = is_string($clientId) ? $clientId : '';
    $redirect = is_string($redirect) ? $redirect : '';
    $state = is_string($state) ? $state : '';
    $resource = is_string($resource) ? $resource : '';
    $scopeText = is_string($scopeText) ? $scopeText : '';
    $challenge = is_string($challenge) ? $challenge : '';
    ensure(($input['response_type'] ?? null) === 'code', 'Tipo de respuesta OAuth no compatible.');
    ensure(strlen($clientId) >= 20 && strlen($clientId) <= 80, 'Cliente OAuth no válido.');
    ensure($state !== '' && strlen($state) <= 512 && preg_match('/^[\x21-\x7E]+$/D', $state) === 1, 'Estado OAuth no válido.');
    ensure($resource === mcp_resource_id(), 'El recurso solicitado no coincide con esta herramienta.');
    ensure(($input['code_challenge_method'] ?? null) === 'S256' && preg_match('/^[A-Za-z0-9_-]{43,128}$/D', $challenge) === 1, 'Se requiere PKCE con S256.');
    $client = mcp_load_oauth_client($clientId);
    ensure($client !== null && mcp_redirect_allowed($redirect) && in_array($redirect, $client['redirect_uris'], true), 'Cliente o URL de retorno no autorizados.');
    $scopes = preg_split('/\s+/', trim($scopeText), -1, PREG_SPLIT_NO_EMPTY) ?: [];
    $scopes = array_values(array_unique($scopes));
    ensure($scopes !== [] && count(array_diff($scopes, mcp_supported_scopes())) === 0, 'El permiso solicitado no está disponible.');
    return ['client'=>$client,'client_id'=>$clientId,'redirect_uri'=>$redirect,'state'=>$state,'resource'=>$resource,'scopes'=>$scopes,'scope_text'=>implode(' ', $scopes),'code_challenge'=>$challenge];
}
function mcp_oauth_hidden(array $request): string {
    $values = ['client_id'=>$request['client_id'],'redirect_uri'=>$request['redirect_uri'],'response_type'=>'code','scope'=>$request['scope_text'],'state'=>$request['state'],'resource'=>$request['resource'],'code_challenge'=>$request['code_challenge'],'code_challenge_method'=>'S256'];
    $html = '';
    foreach ($values as $key=>$value) $html .= '<input type="hidden" name="' . e($key) . '" value="' . e((string)$value) . '">';
    return $html;
}
function mcp_current_session_user(): ?array {
    session_boot();
    if (!isset($_SESSION['user_id'], $_SESSION['password_stamp'])) return null;
    $q = db()->prepare('SELECT id,username,password_hash FROM cubo_users WHERE id=?');
    $q->execute([$_SESSION['user_id']]);
    $user = $q->fetch();
    if (!$user || !hash_equals((string)$_SESSION['password_stamp'], hash('sha256', (string)$user['password_hash']))) {
        unset($_SESSION['user_id'], $_SESSION['password_stamp']);
        return null;
    }
    return ['id'=>(int)$user['id'],'username'=>(string)$user['username']];
}
function mcp_access_token(): ?array {
    $header = (string)($_SERVER['HTTP_AUTHORIZATION'] ?? '');
    if ($header === '' && function_exists('getallheaders')) {
        foreach (getallheaders() as $name=>$value) if (strtolower((string)$name) === 'authorization') { $header=(string)$value; break; }
    }
    if (!preg_match('/^Bearer\s+([A-Za-z0-9_-]{40,100})$/iD', $header, $m)) return null;
    $q = db()->prepare("SELECT t.user_id,t.client_id,t.resource,t.scopes,t.expires_at,t.password_stamp,u.password_hash FROM cubo_oauth_tokens t JOIN cubo_users u ON u.id=t.user_id WHERE t.token_hash=? AND t.token_type='access' AND t.revoked_at IS NULL AND t.expires_at>UTC_TIMESTAMP()");
    $q->execute([hash('sha256', $m[1])]);
    $row = $q->fetch();
    if (!$row || !hash_equals(mcp_resource_id(), (string)$row['resource']) || !hash_equals((string)$row['password_stamp'],hash('sha256',(string)$row['password_hash']))) return null;
    unset($row['password_hash'],$row['password_stamp']);
    return $row;
}
function mcp_has_scope(array $token, string $required): bool {
    $scopes = preg_split('/\s+/', trim((string)($token['scopes'] ?? '')), -1, PREG_SPLIT_NO_EMPTY) ?: [];
    return in_array($required, $scopes, true);
}
function mcp_authenticate_password(string $username, string $password): ?array {
    $name = trim($username);
    if ($name === '' || strlen($name) > 80 || strlen($password) > 200 || !limit_login($name)) return null;
    $q = db()->prepare('SELECT id,username,password_hash FROM cubo_users WHERE username=?');
    $q->execute([$name]);
    $user = $q->fetch();
    $hash = $user['password_hash'] ?? '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2uheWG/igi';
    if (!$user || !password_verify($password, $hash)) return null;
    clear_login_limits($name);
    session_regenerate_id(true);
    $_SESSION['user_id'] = (int)$user['id'];
    $_SESSION['password_stamp'] = hash('sha256', (string)$user['password_hash']);
    $_SESSION['csrf'] = bin2hex(random_bytes(32));
    $_SESSION['last_activity'] = time();
    return ['id'=>(int)$user['id'],'username'=>(string)$user['username']];
}
function mcp_issue_token_pair(PDO $pdo, string $clientId, int $userId, string $resource, string $scopes, bool $allowRefresh, ?string $family = null): array {
    $q=$pdo->prepare('SELECT password_hash FROM cubo_users WHERE id=?');
    $q->execute([$userId]);
    $passwordHash=$q->fetchColumn();
    if (!is_string($passwordHash) || $passwordHash==='') throw new RuntimeException('Cuenta OAuth no disponible.');
    $passwordStamp=hash('sha256',$passwordHash);
    $family ??= bin2hex(random_bytes(32));
    $access = mcp_b64url(random_bytes(32));
    $pdo->prepare("INSERT INTO cubo_oauth_tokens (token_hash,family_id,token_type,client_id,user_id,resource,scopes,password_stamp,created_at,expires_at,revoked_at) VALUES (?,?,'access',?,?,?,?,?,UTC_TIMESTAMP(),DATE_ADD(UTC_TIMESTAMP(),INTERVAL 1 HOUR),NULL)")->execute([hash('sha256',$access),$family,$clientId,$userId,$resource,$scopes,$passwordStamp]);
    $result=['access_token'=>$access,'token_type'=>'Bearer','expires_in'=>3600,'scope'=>$scopes,'resource'=>$resource];
    if ($allowRefresh) {
        $refresh=mcp_b64url(random_bytes(48));
        $pdo->prepare("INSERT INTO cubo_oauth_tokens (token_hash,family_id,token_type,client_id,user_id,resource,scopes,password_stamp,created_at,expires_at,revoked_at) VALUES (?,?,'refresh',?,?,?,?,?,UTC_TIMESTAMP(),DATE_ADD(UTC_TIMESTAMP(),INTERVAL 30 DAY),NULL)")->execute([hash('sha256',$refresh),$family,$clientId,$userId,$resource,$scopes,$passwordStamp]);
        $result['refresh_token']=$refresh;
    }
    return $result;
}
