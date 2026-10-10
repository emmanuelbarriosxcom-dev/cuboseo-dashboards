<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
require __DIR__.'/../private/bootstrap.php';
try {
    if($argc!==2)throw new RuntimeException('Uso: php reset-password.php USUARIO; introducir nueva contraseña por stdin, nunca por argumento.');
    $pass=rtrim((string)fgets(STDIN),"\r\n");
    if(strlen($pass)<12||strlen($pass)>72)throw new RuntimeException('La contraseña debe tener entre 12 y 72 bytes.');
    $q=db()->prepare('UPDATE cubo_users SET password_hash=? WHERE username=?');$q->execute([password_hash($pass,PASSWORD_DEFAULT),$argv[1]]);
    if($q->rowCount()!==1)throw new RuntimeException('Usuario no encontrado.');
    echo "Contraseña actualizada.\n";
}catch(Throwable $ex){fwrite(STDERR,$ex->getMessage()."\n");exit(1);}
