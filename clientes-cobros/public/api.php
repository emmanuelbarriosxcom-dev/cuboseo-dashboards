<?php
declare(strict_types=1);
require __DIR__ . '/../private/bootstrap.php';
require __DIR__ . '/../private/validate.php';
require __DIR__ . '/../private/storage.php';
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
    json_response(save_state($input,$user));
} catch (JsonException $ex) {
    if(isset($pdo)&&$pdo->inTransaction())$pdo->rollBack(); json_response(['error'=>'JSON no válido.'],422);
} catch (StorageConflict $ex) {
    json_response(['error'=>$ex->getMessage(),'code'=>'conflict','revision'=>read_state()['revision']],409);
} catch (DomainException $ex) {
    if(isset($pdo)&&$pdo->inTransaction())$pdo->rollBack(); json_response(['error'=>$ex->getMessage()],422);
} catch (Throwable $ex) {
    if(isset($pdo)&&$pdo->inTransaction())$pdo->rollBack(); error_log('Cubo API: '.$ex->getMessage()); json_response(['error'=>'No se pudo confirmar el guardado. Reintenta la operación.'],503);
}
