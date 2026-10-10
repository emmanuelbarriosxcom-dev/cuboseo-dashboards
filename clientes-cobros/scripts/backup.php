<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
require __DIR__.'/../private/bootstrap.php';
require __DIR__.'/../private/validate.php';
try {
    $folder=config()['backup_dir'];
    if(!is_dir($folder)&&!mkdir($folder,0700,true))throw new RuntimeException('No se puede crear la carpeta de copias.');
    $state=validate_state(read_state()['state']);
    $json=json_encode($state,JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR);
    $path=$folder.'/cubo-cobros-'.date('Y-m-d').'.json';
    $tmp=tempnam($folder,'.backup-');
    if($tmp===false||file_put_contents($tmp,$json,LOCK_EX)===false)throw new RuntimeException('No se pudo escribir la copia.');
    chmod($tmp,0600);
    if(!rename($tmp,$path))throw new RuntimeException('No se pudo finalizar la copia.');
    $files=glob($folder.'/cubo-cobros-????-??-??.json');rsort($files);
    foreach(array_slice($files,31)as $old)unlink($old);
    echo "Copia diaria de datos completada.\n";
}catch(Throwable $ex){fwrite(STDERR,'Cubo backup: '.$ex->getMessage()."\n");exit(1);}
