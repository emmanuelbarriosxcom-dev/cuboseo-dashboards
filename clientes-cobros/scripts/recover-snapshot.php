<?php
declare(strict_types=1);
// Solo extrae una versión para importarla desde Ajustes; no sobrescribe la base.
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
require __DIR__.'/../private/bootstrap.php';
try {
    if($argc!==3||preg_match('/^\d+$/D',$argv[1])!==1)throw new RuntimeException('Uso: php recover-snapshot.php REVISION /ruta/privada/recuperacion.json');
    $q=db()->prepare('SELECT payload FROM cubo_snapshots WHERE revision=?');$q->execute([(int)$argv[1]]);$data=$q->fetchColumn();
    if($data===false)throw new RuntimeException('Versión no encontrada.');
    // Modo x evita reemplazar archivos por accidente.
    $file=fopen($argv[2],'x');if(!$file)throw new RuntimeException('Destino no disponible.');
    chmod($argv[2],0600);if(fwrite($file,$data)!==strlen($data))throw new RuntimeException('No se escribió la copia completa.');fclose($file);
    echo "Versión exportada. Revisa e importa desde Ajustes.\n";
}catch(Throwable $ex){fwrite(STDERR,$ex->getMessage()."\n");exit(1);}
