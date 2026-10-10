# Instalación dirigida en HostGator

Destino propuesto: `https://clientes.cuboseo.com`. Plan reportado: Emprendedor, Linux compartido, cPanel, Percona 5.7.44-48. Confirmar que el nombre no esté ocupado y que la carpeta elegida no pertenezca a una instalación existente.

## 1. Preparación

Descargar desde cPanel una copia de la base y archivos de la web principal antes de manipular el panel. Este paquete no necesita modificar WordPress ni sus bases.

Si Emmanuel ya cargó clientes reales en el prototipo, abrir **la misma copia y navegador donde los cargó**, exportar JSON desde Ajustes y conservar el original. No inferir que la copia de otro dispositivo contiene todos los datos. No importar los ejemplos ficticios ni modificar sus pagos para hacerlos pasar por datos reales.

## 2. Carpetas y subdominio

Extraer el paquete en una carpeta propia de la cuenta, preferentemente fuera de `public_html`, por ejemplo `/home/CUENTA/cubo-cobros/`.

Crear el subdominio `clientes.cuboseo.com` y asignar como raíz pública **solo** `/home/CUENTA/cubo-cobros/public`. `private` y `scripts` deben ser carpetas hermanas de `public`, fuera de esa raíz. No subir todo el paquete a una carpeta pública.

Si cPanel impide usar esa ubicación, mover la aplicación manteniendo su estructura y verificar que las carpetas privadas queden inaccesibles mediante HTTP. Si no puede garantizarse esta separación, detener la instalación y reportar el límite; no depender únicamente de que PHP o `.htaccess` oculten los secretos.

Permisos orientativos: directorios de código 755 y archivos 644; `config.php` y copias 600 cuando el proceso PHP de la cuenta pueda leerlos. Confirmar la lectura antes de aplicar esos permisos. No usar 777.

## 3. PHP y HTTPS

Seleccionar PHP 8.3 para el subdominio desde MultiPHP; conservar la versión de la web principal. Comprobar `pdo_mysql`, sesiones y `password_hash`/`password_verify`.

Activar y comprobar AutoSSL para el subdominio. Esperar DNS y certificado correctos antes de iniciar el alta. No usar un `phpinfo()` público; `setup.php` verifica la conexión real y solo muestra información mínima.

## 4. Base exclusiva y configuración

Crear una base y un usuario MySQL exclusivos. Asignar a ese usuario los permisos sobre **esa base**, incluidos los necesarios para crear las seis tablas del paquete y trabajar con transacciones. No reutilizar la base o el usuario de WordPress.

Copiar `private/config.example.php` a `private/config.php` y completar en el servidor: host, puerto, nombre de base, usuario y contraseña. Configurar `app_origin` exactamente con `https://clientes.cuboseo.com`, sin barra final.

La zona incluida es `America/Tijuana`; confirmar con Emmanuel si la operación debe usar esa zona o `Europe/Moscow` antes de registrar datos. Todos los dispositivos usarán esa misma fecha financiera.

Generar una clave de instalación aleatoria de al menos 32 caracteres y guardarla en `setup_key`. No usar el texto de ejemplo, mostrarla en el informe, ponerla en la URL o subirla a GitHub. Mantener `allow_http_local` en `false`.

## 5. Alta inicial

Abrir por HTTPS `setup.php`. Si se presenta el formulario, la conexión de PDO MySQL ya funcionó en el PHP web real. Completar clave de instalación y usuario.

Emmanuel debe elegir y escribir su contraseña privada directamente; el agente puede entregarle el control del navegador para hacerlo. No incluir credenciales en el chat, capturas o documentos. La contraseña debe tener entre 12 y 72 bytes.

La creación utiliza INSERT y no reemplaza una instalación previa. Si aparece «Alta inicial cerrada», usar la cuenta existente; no borrar tablas para ejecutar el alta otra vez.

Después de entrar, retirar `public/setup.php` del servidor y dejar `setup_key` vacío en la configuración. Conservar el archivo original solo en el código privado para nuevas instalaciones.

## 6. Respaldos diarios

Comprobar el binario PHP CLI disponible en la cuenta y que cargue PDO MySQL. Ejecutar manualmente `scripts/backup.php` con ese PHP y comprobar que se creó un JSON válido en `private/backups/`.

Si cPanel ofrece Cron Jobs, configurar ejecución diaria con las rutas reales. Ejemplo que debe ajustarse al servidor:

```sh
/RUTA/REAL/php /home/CUENTA/cubo-cobros/scripts/backup.php
```

Elegir una hora de baja actividad y registrar la zona horaria del cron. No copiar a ciegas `/usr/bin/php`: puede ser otra versión. Confirmar una ejecución programada. Si Cron Jobs no está disponible, reportarlo; el respaldo automático externo sigue pendiente y no debe anunciarse como activado.

Descargar periódicamente una copia fuera del hosting. Usar cPanel para respaldar también la base SQL completa y la configuración privada, guardándolas de forma segura.

## 7. Prueba antes de cargar datos reales

Entrar, crear un cliente identificado como prueba, añadir un proyecto con anticipo y saldo al entregar, registrar un abono y comprobarlo tras recargar. Repetir la consulta desde otro dispositivo o sesión. Verificar computadora, móvil e iPad.

Abrir dos sesiones sobre los mismos datos: guardar un cambio en una y comprobar que la otra no pueda sobrescribirlo con una versión anterior.

Comprobar cambio de contraseña, cierre de sesión, redirección HTTP a HTTPS y que usuarios sin sesión no puedan acceder al dashboard ni a sus datos.

Comprobar respuestas 403/404 para rutas privadas, incluyendo `private/config.php`, `private/dashboard.html`, `private/schema.sql`, respaldos y scripts. No publicar los contenidos para comprobarlos.

Exportar copia de la prueba. En una base temporal o mediante una importación explícitamente revisada, comprobar la recuperación. Antes de datos reales, restaurar la copia vacía inicial mediante Ajustes para retirar todos los registros de prueba; este paso solo se permite mientras no haya datos reales.

## 8. Migración de datos reales

Revisar el JSON original: clientes reales, identificadores, importes, monedas, fechas, relaciones y pagos. La instalación rechaza demostraciones y simulación. Si una copia genuina antigua carece de `dateMode`, el dashboard normaliza el formato al importarla; no se deben alterar fechas de pagos para eludir la validación.

Importar desde Ajustes. Comparar cantidades de clientes/servicios/pagos y saldos por moneda antes y después. Guardar otra exportación y comprobar que el respaldo diario incluya esos datos.

## 9. Prueba con el agente

Desde el chat con navegador autorizado, pedir al agente que consulte un cliente y un saldo. Después registrar una operación de prueba claramente identificada y verificarla desde otra sesión. Borrar o deshacer únicamente esa operación de prueba después de comprobarla.

No activar pagos bancarios, enviar mensajes a clientes ni contratar APIs: este flujo solo registra información en la herramienta.

## 10. Entrega

Informar URL final, PHP seleccionado, prueba real de PDO, SSL, aislamiento de carpetas, acceso privado, persistencia entre sesiones, respaldo y recuperación. Distinguir lo verificado de lo pendiente. Indicar si se configuró y comprobó el cron; no afirmar que está activo por haber subido el script.

GitHub queda como siguiente paso de mantenimiento: repositorio privado del código y exclusión de secretos. La publicación de la aplicación funciona sin esperar ese repositorio.

## Recuperación

Para error de datos: exportar la situación actual, extraer una de las últimas 30 versiones con `scripts/recover-snapshot.php REVISION DESTINO_PRIVADO.json`, revisar e importar desde Ajustes. Sin terminal, extraer `payload` de la revisión elegida en `cubo_snapshots` mediante phpMyAdmin y guardarlo como JSON válido. No editar directamente los importes en SQL para corregir un pago.

Para pérdida de base completa: restaurar el SQL desde cPanel o crear una base nueva, ejecutar el alta con credenciales nuevas e importar la copia JSON de negocio. La copia JSON por sí sola no restaura auditoría ni cuentas.

Para recuperar acceso: usar `scripts/reset-password.php USUARIO`, proporcionando la contraseña por stdin privado, nunca como argumento del comando ni en el chat. La recuperación invalida las otras sesiones. Si no hay terminal, preparar con el responsable un hash nuevo y actualizar únicamente la cuenta elegida desde phpMyAdmin.

Para revertir código: restaurar la versión anterior de archivos públicos/privados de código conservando configuración y datos; no ejecutar `setup.php` sobre la base existente.
