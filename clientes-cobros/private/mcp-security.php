<?php
declare(strict_types=1);
function mcp_store(callable $fn): mixed {
    $dir=__DIR__.'/mcp-runtime';if(!is_dir($dir)&&!mkdir($dir,0700,true))throw new RuntimeException('Almacenamiento MCP no disponible.');
    $lock=fopen($dir.'/security.lock','c');if(!$lock||!flock($lock,LOCK_EX))throw new RuntimeException('Bloqueo MCP no disponible.');chmod($dir.'/security.lock',0600);
    try{$path=$dir.'/security.json';$text=is_file($path)?file_get_contents($path):'';$s=$text?json_decode($text,true,512,JSON_THROW_ON_ERROR):['clients'=>[],'codes'=>[],'tokens'=>[],'proposals'=>[],'limits'=>[]];
        foreach(['codes','tokens','proposals','refresh'] as $kind)foreach($s[$kind]??[] as $k=>$v)if(($v['expires']??0)<time()-86400)unset($s[$kind][$k]);
        $out=$fn($s);$json=json_encode($s,JSON_THROW_ON_ERROR);$tmp=tempnam($dir,'atomic_');if(!$tmp||file_put_contents($tmp,$json,LOCK_EX)!==strlen($json))throw new RuntimeException('No se pudo guardar OAuth.');chmod($tmp,0600);if(!rename($tmp,$path))throw new RuntimeException('No se pudo confirmar OAuth.');return $out;
    }finally{flock($lock,LOCK_UN);fclose($lock);}
}
function mcp_random(): string {return rtrim(strtr(base64_encode(random_bytes(32)),'+/','-_'),'=');}
function mcp_resource(): string {return config()['app_origin'].(defined('CUBO_MCP_TEST')?'/mcp-test.php':'/mcp.php');}
function mcp_token_user(): array {
    $auth=$_SERVER['HTTP_AUTHORIZATION']??$_SERVER['REDIRECT_HTTP_AUTHORIZATION']??'';ensure(preg_match('/^Bearer ([A-Za-z0-9_-]{40,100})$/D',$auth,$m)===1,'OAuth requerido.');
    $hash=hash('sha256',$m[1]);$t=mcp_store(fn(&$s)=>$s['tokens'][$hash]??null);ensure(is_array($t)&&$t['expires']>=time()&&$t['resource']===mcp_resource(),'Token vencido o recurso incorrecto.');
    $q=db()->prepare('SELECT id,username,password_hash FROM cubo_users WHERE id=?');$q->execute([$t['userId']]);$u=$q->fetch();ensure($u&&hash_equals($t['stamp'],hash('sha256',$u['password_hash'])),'Autorización revocada.');unset($u['password_hash']);return $u+['scopes'=>$t['scopes'],'oauthClient'=>$t['clientId']];
}
function mcp_scope(array $user,string $scope): void {ensure(in_array($scope,$user['scopes']??[],true),'Permiso OAuth insuficiente.');}
function mcp_rate_limit(string $area,int $limit): void {mcp_store(function(&$s)use($area,$limit){$key=hash('sha256',$area.'|'.($_SERVER['REMOTE_ADDR']??'cli'));$r=$s['limits'][$key]??['at'=>time(),'n'=>0];if($r['at']<time()-3600)$r=['at'=>time(),'n'=>0];ensure($r['n']<$limit,'Límite temporal excedido.');$r['n']++;$s['limits'][$key]=$r;});}

function mcp_read_state(): array {$row=db()->query('SELECT revision,payload,updated_at FROM '.storage_table('state').' WHERE id=1')->fetch();ensure((bool)$row,'El entorno no está inicializado.');return ['revision'=>(int)$row['revision'],'state'=>json_decode($row['payload'],true,512,JSON_THROW_ON_ERROR),'updatedAt'=>$row['updated_at']];}
