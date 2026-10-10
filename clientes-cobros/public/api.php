<?php
declare(strict_types=1);
require __DIR__ . '/../private/bootstrap.php';
require __DIR__ . '/../private/validate.php';
try {
    headers_private(); $user = require_user(true); $pdo = db();
    if ($_SERVER['REQUEST_METHOD']==='GET') {
        $data=read_state()+['serverToday'=>date('Y-m-d')]; session_write_close(); json_response($data);
    }
    if ($_SERVER['REQUEST_METHOD']!=='PUT') { header('Allow: GET, PUT'); json_response(['error'=>'Método no permitido.'],405); }
    require_csrf($_SERVER['HTTP_X_CSRF_TOKEN']??null); session_write_close();
    if (stripos($_SERVER['CONTENT_TYPE']??'', 'application/json')!==0) json_response(['error'=>'Formato no permitido.'],415);
    $raw=file_get_contents('php://input',false,null,0,8*1024*1024+1);
    if(strlen($raw)>8*1024*1024) json_response(['error'=>'La copia supera 8 MB.'],413);
    $input=json_decode($raw,true,512,JSON_THROW_ON_ERROR);
    ensure(is_array($input) && is_int($input['revision']??null) && $input['revision']>=0 && is_string($input['requestId']??null) && preg_match('/^[a-zA-Z0-9_-]{1,100}$/D',$input['requestId'])===1,'Solicitud no válida.');
    $state=validate_state($input['state']??null);
    ensure($state['settings']['today']===date('Y-m-d'),'La fecha de trabajo cambió. Actualiza la herramienta antes de guardar.');
    $payload=json_encode($state,JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR);
    $hash=hash('sha256',$payload); $request=$input['requestId'];
    $pdo->beginTransaction();
    $current=$pdo->query('SELECT revision, payload FROM cubo_state WHERE id=1 FOR UPDATE')->fetch();
    if(!$current) throw new RuntimeException('La instalación está incompleta.');
    $q=$pdo->prepare('SELECT user_id,payload_hash,base_revision,revision FROM cubo_operations WHERE request_id=?'); $q->execute([$request]); $done=$q->fetch();
    if($done) {
        ensure((int)$done['user_id']===(int)$user['id'] && hash_equals($done['payload_hash'],$hash) && (int)$done['base_revision']===$input['revision'], 'Esta referencia pertenece a otra operación.');
        $pdo->commit(); json_response(['saved'=>true,'revision'=>(int)$done['revision'],'replayed'=>true]);
    }
    if((int)$current['revision']!==$input['revision']) {
        $pdo->rollBack(); json_response(['error'=>'Hay cambios guardados desde otro dispositivo. Actualiza y vuelve a realizar esta operación.','code'=>'conflict','revision'=>(int)$current['revision']],409);
    }
    $revision=(int)$current['revision']+1;
    $pdo->prepare('INSERT IGNORE INTO cubo_snapshots (revision,payload,created_at) VALUES (?,?,UTC_TIMESTAMP())')->execute([(int)$current['revision'],$current['payload']]);
    $pdo->prepare('UPDATE cubo_state SET revision=?,payload=?,updated_at=UTC_TIMESTAMP() WHERE id=1')->execute([$revision,$payload]);
    $pdo->prepare('INSERT INTO cubo_operations (request_id,user_id,payload_hash,base_revision,revision,created_at) VALUES (?,?,?,?,?,UTC_TIMESTAMP())')->execute([$request,$user['id'],$hash,$input['revision'],$revision]);
    $before=json_decode($current['payload'],true,512,JSON_THROW_ON_ERROR); $counts=[];
    foreach(['clients','services','charges','payments'] as $k) $counts[$k]=['before'=>count($before[$k]),'after'=>count($state[$k])];
    $pdo->prepare('INSERT INTO cubo_audit (user_id,revision,action,summary,created_at) VALUES (?,?,?,?,UTC_TIMESTAMP())')->execute([$user['id'],$revision,substr((string)($input['action']??'Guardar datos'),0,100),json_encode($counts,JSON_THROW_ON_ERROR)]);
    $pdo->prepare('DELETE FROM cubo_snapshots WHERE revision < ?')->execute([max(0,$revision-30)]);
    $pdo->commit(); json_response(['saved'=>true,'revision'=>$revision,'replayed'=>false]);
} catch (JsonException $ex) {
    if(isset($pdo)&&$pdo->inTransaction())$pdo->rollBack(); json_response(['error'=>'JSON no válido.'],422);
} catch (DomainException $ex) {
    if(isset($pdo)&&$pdo->inTransaction())$pdo->rollBack(); json_response(['error'=>$ex->getMessage()],422);
} catch (Throwable $ex) {
    if(isset($pdo)&&$pdo->inTransaction())$pdo->rollBack(); error_log('Cubo API: '.$ex->getMessage()); json_response(['error'=>'No se pudo confirmar el guardado. Reintenta la operación.'],503);
}
