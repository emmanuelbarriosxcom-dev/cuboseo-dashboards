<?php
declare(strict_types=1);
require __DIR__ . '/../private/bootstrap.php';
require __DIR__ . '/../private/validate.php';
require __DIR__ . '/../private/mcp-auth.php';

function mcp_http_json(array $message, int $status = 200, string $version = '2025-03-26'): never {
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');
    header('X-Content-Type-Options: nosniff');
    header('MCP-Protocol-Version: '.$version);
    echo json_encode($message, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
    exit;
}
function mcp_rpc_error(mixed $id, int $code, string $message, int $status = 200, string $version = '2025-03-26'): never {
    mcp_http_json(['jsonrpc'=>'2.0','id'=>$id,'error'=>['code'=>$code,'message'=>$message]],$status,$version);
}
function mcp_tool_output(array $data, bool $isError = false, array $meta = []): array {
    $result=['content'=>[['type'=>'text','text'=>json_encode($data,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR)]],'structuredContent'=>$data,'isError'=>$isError];
    if ($meta!==[]) $result['_meta']=$meta;
    return $result;
}
function mcp_auth_error_result(): array {
    $challenge='Bearer resource_metadata="'.mcp_resource_metadata_url().'", scope="clients:read", error="invalid_token", error_description="Autoriza Cubo SEO para consultar esta información."';
    return mcp_tool_output(['error'=>'Se requiere autorización para consultar los datos privados.'],true,['mcp/www_authenticate'=>[$challenge]]);
}
function mcp_tools(): array {
    $scheme=[['type'=>'oauth2','scopes'=>['clients:read']]];
    return [
        ['name'=>'search_clients','title'=>'Buscar clientes','description'=>'Use this when the user asks to find a client by name, email, phone, country, or contact. Returns a short client summary and outstanding balances by currency. Requires an exact or distinctive search; never dump the complete client list.','inputSchema'=>['type'=>'object','properties'=>['query'=>['type'=>'string','minLength'=>2,'maxLength'=>100,'description'=>'Distinctive client name, email, phone, contact, or country fragment.'],'limit'=>['type'=>'integer','minimum'=>1,'maximum'=>25,'default'=>10,'description'=>'Maximum number of matching clients.']],'required'=>['query'],'additionalProperties'=>false],'annotations'=>['readOnlyHint'=>true,'destructiveHint'=>false,'idempotentHint'=>true,'openWorldHint'=>false],'securitySchemes'=>$scheme],
        ['name'=>'get_client_account','title'=>'Consultar cuenta de cliente','description'=>'Use this after identifying one client when the user asks for contact details, project/service details, charges, payments, account history, or balance. Requires the exact client ID returned by search_clients. Returns only that client.','inputSchema'=>['type'=>'object','properties'=>['client_id'=>['type'=>'string','minLength'=>1,'maxLength'=>100,'description'=>'Exact client ID returned by search_clients.']],'required'=>['client_id'],'additionalProperties'=>false],'annotations'=>['readOnlyHint'=>true,'destructiveHint'=>false,'idempotentHint'=>true,'openWorldHint'=>false],'securitySchemes'=>$scheme]
    ];
}
function mcp_lower(string $value): string { return function_exists('mb_strtolower') ? mb_strtolower($value,'UTF-8') : strtolower($value); }
function mcp_payments_by_charge(array $state): array {
    $totals=[];
    foreach (($state['payments']??[]) as $payment) $totals[$payment['chargeId']]=($totals[$payment['chargeId']]??0)+(float)$payment['amount'];
    return $totals;
}
function mcp_charge_open(array $charge, array $paid): float {
    if (!empty($charge['cancelled']) || !empty($charge['lost'])) return 0.0;
    return round(max(0.0,(float)$charge['amount']-(float)($paid[$charge['id']]??0)),2);
}
function mcp_client_balances(string $clientId, array $state, array $paid): array {
    $balances=[];
    foreach (($state['charges']??[]) as $charge) {
        if (($charge['clientId']??'')!==$clientId) continue;
        $open=mcp_charge_open($charge,$paid);
        if ($open<=0) continue;
        $currency=(string)$charge['currency'];
        $balances[$currency]=round(($balances[$currency]??0)+$open,2);
    }
    ksort($balances);
    return $balances;
}
function mcp_search_clients(array $args, array $state): array {
    $query=is_string($args['query']??null)?trim($args['query']):'';
    ensure(strlen($query)>=2 && strlen($query)<=100,'Escribe al menos dos caracteres para buscar.');
    $needle=mcp_lower($query); $limit=max(1,min(25,(int)($args['limit']??10)));
    $paid=mcp_payments_by_charge($state); $matches=[];
    foreach ($state['clients'] as $client) {
        $fields=[];
        foreach (['name','contact','phone','email','country'] as $key) if (isset($client[$key]) && is_string($client[$key])) $fields[]=$client[$key];
        $haystack=mcp_lower(implode(' ', $fields));
        if (strpos($haystack,$needle)===false && strpos(mcp_lower((string)$client['id']),$needle)===false) continue;
        $matches[]=['client_id'=>$client['id'],'name'=>$client['name'],'status'=>$client['status'],'country'=>$client['country']??null,'project_count'=>count(array_filter($state['services'],static fn($s)=>($s['clientId']??'')===$client['id'])),'open_balances'=>mcp_client_balances((string)$client['id'],$state,$paid)];
        if (count($matches)>=$limit) break;
    }
    return ['query'=>$query,'count'=>count($matches),'clients'=>$matches];
}
function mcp_get_client_account(array $args, array $state): array {
    $clientId=is_string($args['client_id']??null)?$args['client_id']:'';
    ensure($clientId!=='' && strlen($clientId)<=100,'Identificador de cliente no válido.');
    $client=null;
    foreach ($state['clients'] as $candidate) if (hash_equals((string)$candidate['id'],$clientId)) { $client=$candidate; break; }
    ensure($client!==null,'No se encontró ese cliente.');
    $services=array_values(array_filter($state['services'],static fn($s)=>($s['clientId']??'')===$clientId));
    $charges=array_values(array_filter($state['charges'],static fn($c)=>($c['clientId']??'')===$clientId));
    $chargeIds=array_fill_keys(array_map(static fn($c)=>(string)$c['id'],$charges),true);
    $payments=array_values(array_filter($state['payments'],static fn($p)=>isset($chargeIds[(string)($p['chargeId']??'')])));
    $paid=mcp_payments_by_charge($state);
    $chargeRows=[];
    foreach ($charges as $charge) {
        $chargeRows[]=['charge_id'=>$charge['id'],'service_id'=>$charge['serviceId'],'title'=>$charge['title'],'period'=>$charge['period'],'start'=>$charge['start'],'end'=>$charge['end'],'due'=>$charge['due']??null,'amount'=>$charge['amount'],'currency'=>$charge['currency'],'paid'=>round((float)($paid[$charge['id']]??0),2),'balance'=>mcp_charge_open($charge,$paid),'cancelled'=>(bool)($charge['cancelled']??false),'lost'=>(bool)($charge['lost']??false)];
    }
    $serviceRows=[];
    foreach ($services as $service) {
        $serviceRows[]=['service_id'=>$service['id'],'name'=>$service['name'],'type'=>$service['type']??null,'brand'=>$service['brand']??null,'site'=>$service['site']??null,'kind'=>$service['kind'],'amount'=>$service['amount'],'currency'=>$service['currency'],'start'=>$service['start'],'frequency'=>$service['frequency']??null,'due_day'=>$service['dueDay']??null,'status'=>$client['status']];
    }
    $paymentRows=[];
    foreach ($payments as $payment) $paymentRows[]=['payment_id'=>$payment['id'],'charge_id'=>$payment['chargeId'],'amount'=>$payment['amount'],'date'=>$payment['date'],'note'=>$payment['note']??null,'rate'=>$payment['rate']??null,'rate_date'=>$payment['rateDate']??null,'mxn'=>$payment['mxn']??null];
    $history=array_values(array_filter($state['history'],static fn($h)=>($h['clientId']??'')===$clientId));
    usort($history,static fn($a,$b)=>strcmp((string)$b['date'],(string)$a['date']));
    $historyRows=[];
    foreach (array_slice($history,0,100) as $entry) $historyRows[]=['date'=>$entry['date'],'text'=>$entry['text']];
    return ['client'=>['client_id'=>$client['id'],'name'=>$client['name'],'status'=>$client['status'],'contact'=>$client['contact']??null,'phone'=>$client['phone']??null,'email'=>$client['email']??null,'country'=>$client['country']??null,'category'=>$client['category']??null,'channel'=>$client['channel']??null,'notes'=>$client['notes']??null],'open_balances'=>mcp_client_balances($clientId,$state,$paid),'services'=>$serviceRows,'charges'=>$chargeRows,'payments'=>$paymentRows,'history'=>$historyRows];
}
function mcp_dispatch_tool(string $name, array $args, array $state): array {
    return match ($name) {
        'search_clients'=>mcp_search_clients($args,$state),
        'get_client_account'=>mcp_get_client_account($args,$state),
        default=>throw new DomainException('Herramienta no disponible.')
    };
}

try {
    headers_private();
    if (($_SERVER['REQUEST_METHOD']??'')!=='POST') { header('Allow: POST'); http_response_code(405); exit; }
    $origin=$_SERVER['HTTP_ORIGIN']??null;
    if ($origin!==null && !in_array($origin,[mcp_origin(),'https://chatgpt.com'],true)) { http_response_code(403); exit; }
    $contentType=strtolower(trim(explode(';',(string)($_SERVER['CONTENT_TYPE']??''))[0]));
    if ($contentType!=='application/json') { http_response_code(415); exit; }
    $accept=(string)($_SERVER['HTTP_ACCEPT']??'');
    if (stripos($accept,'application/json')===false && stripos($accept,'text/event-stream')===false) { http_response_code(406); exit; }
    $raw=file_get_contents('php://input',false,null,0,1024*1024+1);
    if (!is_string($raw) || strlen($raw)>1024*1024) { http_response_code(413); exit; }
    try { $message=json_decode($raw,true,64,JSON_THROW_ON_ERROR); }
    catch (JsonException) { mcp_rpc_error(null,-32700,'Parse error.',400); }
    if (!is_array($message) || array_is_list($message) || ($message['jsonrpc']??null)!=='2.0' || !is_string($message['method']??null)) mcp_rpc_error($message['id']??null,-32600,'Invalid Request.',400);
    $id=$message['id']??null; $hasId=array_key_exists('id',$message);
    $method=$message['method']; $params=is_array($message['params']??null)?$message['params']:[];
    $knownVersions=['2024-11-05','2025-03-26','2025-06-18','2025-11-25'];
    $version=$method==='initialize'?(string)($params['protocolVersion']??'2025-03-26'):(string)($_SERVER['HTTP_MCP_PROTOCOL_VERSION']??'2025-03-26');
    if (!in_array($version,$knownVersions,true)) mcp_rpc_error($hasId?$id:null,-32602,'Unsupported MCP protocol version.',400,'2025-03-26');
    if ($method==='notifications/initialized' || str_starts_with($method,'notifications/')) { http_response_code(202); exit; }
    if (!$hasId || (!is_string($id) && !is_int($id))) mcp_rpc_error(null,-32600,'Request id is required.',400,$version);
    if ($method==='initialize') {
        mcp_http_json(['jsonrpc'=>'2.0','id'=>$id,'result'=>['protocolVersion'=>$version,'capabilities'=>['tools'=>['listChanged'=>false]],'serverInfo'=>['name'=>'cubo-seo-clientes-cobros','version'=>'1.0.0'],'instructions'=>'Conector privado de solo lectura. Busca clientes por datos distintivos y consulta una cuenta por su client_id. No inventes saldos ni expongas la lista completa. Las altas y pagos se hacen en la interfaz autenticada de Cubo SEO.']],200,$version);
    }
    if ($method==='ping') mcp_http_json(['jsonrpc'=>'2.0','id'=>$id,'result'=>(object)[]],200,$version);
    if ($method==='tools/list') mcp_http_json(['jsonrpc'=>'2.0','id'=>$id,'result'=>['tools'=>mcp_tools()]],200,$version);
    if ($method==='tools/call') {
        $name=is_string($params['name']??null)?$params['name']:'';
        $args=$params['arguments']??[];
        if (!is_array($args) || array_is_list($args)) mcp_rpc_error($id,-32602,'Tool arguments must be an object.',200,$version);
        $token=mcp_access_token();
        if (!$token || !mcp_has_scope($token,'clients:read')) mcp_http_json(['jsonrpc'=>'2.0','id'=>$id,'result'=>mcp_auth_error_result()],200,$version);
        try {
            $state=read_state()['state'];
            $data=mcp_dispatch_tool($name,$args,$state);
            mcp_http_json(['jsonrpc'=>'2.0','id'=>$id,'result'=>mcp_tool_output($data)],200,$version);
        } catch (DomainException $ex) {
            mcp_http_json(['jsonrpc'=>'2.0','id'=>$id,'result'=>mcp_tool_output(['error'=>$ex->getMessage()],true)],200,$version);
        }
    }
    mcp_rpc_error($id,-32601,'Method not found.',200,$version);
} catch (Throwable $ex) {
    error_log('Cubo MCP: '.$ex->getMessage());
    if (!headers_sent()) { http_response_code(503); header('Content-Type: application/json; charset=utf-8'); }
    echo json_encode(['jsonrpc'=>'2.0','id'=>null,'error'=>['code'=>-32603,'message'=>'Internal server error.']],JSON_UNESCAPED_UNICODE);
}
