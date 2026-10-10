<?php
declare(strict_types=1);
// Copiar como config.php y completar EN EL SERVIDOR. No subir secretos a GitHub.
return [
    'db_host' => 'localhost',
    'db_port' => 3306,
    'db_name' => 'CUENTA_cubocobros',
    'db_user' => 'CUENTA_cubocobros',
    'db_password' => 'COMPLETAR_EN_SERVIDOR',
    'app_origin' => 'https://clientes.cuboseo.com',
    'timezone' => 'America/Tijuana',
    // Clave aleatoria de al menos 32 caracteres; retirar después del alta inicial.
    'setup_key' => 'GENERAR_CLAVE_ALEATORIA_EN_SERVIDOR',
    'allow_http_local' => false,
    'backup_dir' => __DIR__ . '/backups',
];
