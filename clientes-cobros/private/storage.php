<?php
declare(strict_types=1);
function storage_table(string $name): string {return (defined('CUBO_MCP_TEST')?'cubo_mcp_test_':'cubo_').$name;}
class StorageConflict extends DomainException {}
function save_state(array $input, array $user): array {
    $pdo=db();
    try {
    ensure(is_array($input) && is_int($input['revision']??null) && $input['revision']>=0 && is_string($input['requestId']??null) && preg_match('/^[a-zA-Z0-9_-]{1,100}$/D',$input['requestId'])===1,'Solicitud no válida.');
    $state=validate_state($input['state']??null);
    ensure($state['settings']['today']===date('Y-m-d'),'La fecha de trabajo cambió. Actualiza la herramienta antes de guardar.');
    $payload=json_encode($state,JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR);
    $hash=hash('sha256',$payload); $request=$input['requestId'];
    $pdo->beginTransaction();
    $current=$pdo->query('SELECT revision, payload FROM '.storage_table('state').' WHERE id=1'.($pdo->getAttribute(PDO::ATTR_DRIVER_NAME)==='sqlite'?'':' FOR UPDATE'))->fetch();
    if(!$current) throw new RuntimeException('La instalación está incompleta.');
    $q=$pdo->prepare('SELECT user_id,payload_hash,base_revision,revision FROM '.storage_table('operations').' WHERE request_id=?'); $q->execute([$request]); $done=$q->fetch();
    if($done) {
        ensure((int)$done['user_id']===(int)$user['id'] && hash_equals($done['payload_hash'],$hash) && (int)$done['base_revision']===$input['revision'], 'Esta referencia pertenece a otra operación.');
        $pdo->commit(); return ['saved'=>true,'revision'=>(int)$done['revision'],'replayed'=>true];
    }
    if((int)$current['revision']!==$input['revision']) {
        throw new StorageConflict('Hay cambios guardados desde otro dispositivo. Actualiza y vuelve a realizar esta operación.');
    }
    $revision=(int)$current['revision']+1;
    $pdo->prepare(($pdo->getAttribute(PDO::ATTR_DRIVER_NAME)==='sqlite'?'INSERT OR IGNORE':'INSERT IGNORE').' INTO '.storage_table('snapshots').' (revision,payload,created_at) VALUES (?,?,UTC_TIMESTAMP())')->execute([(int)$current['revision'],$current['payload']]);
    $pdo->prepare('UPDATE '.storage_table('state').' SET revision=?,payload=?,updated_at=UTC_TIMESTAMP() WHERE id=1')->execute([$revision,$payload]);
    $pdo->prepare('INSERT INTO '.storage_table('operations').' (request_id,user_id,payload_hash,base_revision,revision,created_at) VALUES (?,?,?,?,?,UTC_TIMESTAMP())')->execute([$request,$user['id'],$hash,$input['revision'],$revision]);
    $before=json_decode($current['payload'],true,512,JSON_THROW_ON_ERROR); $counts=[];
    foreach(['clients','services','charges','payments'] as $k) $counts[$k]=['before'=>count($before[$k]),'after'=>count($state[$k])];
    $pdo->prepare('INSERT INTO '.storage_table('audit').' (user_id,revision,action,summary,created_at) VALUES (?,?,?,?,UTC_TIMESTAMP())')->execute([$user['id'],$revision,substr((string)($input['action']??'Guardar datos'),0,100),json_encode($counts,JSON_THROW_ON_ERROR)]);
    $pdo->prepare('DELETE FROM '.storage_table('snapshots').' WHERE revision < ?')->execute([max(0,$revision-30)]);
    $pdo->commit(); return ['saved'=>true,'revision'=>$revision,'replayed'=>false];
    } catch(Throwable $ex){if($pdo->inTransaction())$pdo->rollBack();throw $ex;}
}
