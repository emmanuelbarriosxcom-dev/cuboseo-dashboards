# Instalación nueva en PHP/MySQL

Para actualizar una instalación existente, seguir [MIGRACION.md](MIGRACION.md). No ejecutar el alta ni crear de nuevo sus tablas/datos.

1. Preparar una carpeta independiente del sitio principal, con `public`, `private` y `scripts` como hermanas. Asignar al subdominio únicamente `public/` como document root. `private/` y `scripts/` no deben ser accesibles por HTTP.
2. Usar PHP 8.3 y verificar PDO MySQL, sesiones y funciones de contraseñas. Crear una base/usuario exclusivos; mantener WordPress y sus bases intactos. El esquema vacío tiene seis tablas InnoDB/utf8mb4; no importar un dump de clientes desde GitHub.
3. Confirmar HTTPS válido antes del alta. No publicar phpinfo, respaldos, exports, logs, configuración o archivos de diagnóstico.
4. Copiar `private/config.example.php` a `private/config.php` y completar credenciales solo en el servidor. Confirmar origen HTTPS y zona horaria de la operación. El ejemplo America/Tijuana no reemplaza una zona ya aprobada. Mantener `allow_http_local:false`; no subir `config.php` al repositorio.
5. Generar una clave de instalación privada de al menos 32 caracteres. El código de alta se conserva en `private/setup.php`: para una instalación vacía, habilitar temporalmente el punto de entrada público que incluya ese archivo, con supervisión del administrador. No dejarlo habilitado en una instalación existente. El propietario debe introducir su usuario/contraseña directamente, sin compartirlos en chats o capturas.
6. Una vez completada el alta, retirar el punto de entrada público y vaciar `setup_key`. El alta debe permanecer cerrada; no borrar datos para ejecutarla otra vez. Ajustar permisos para que PHP lea los archivos privados sin hacerlos públicos; no utilizar 777.
7. Configurar `scripts/backup.php` con el PHP CLI real que incluya PDO. Si hay cron, confirmar una ejecución diaria y conservar copias privadas fuera del hosting. Los respaldos JSON de negocio no sustituyen un respaldo completo de base, cuentas/auditoría y archivos.
8. Probar una copia aislada: acceso privado, altas normales/históricas, abonos parciales, guardado tras recargar, conflictos entre sesiones, recuperación, móvil y escritorio. Mantener generación automática pausada hasta aprobar calendario. Usar datos ficticios y retirarlos antes de cargar registros reales, únicamente en una instalación vacía de prueba.

## Restauración

- Datos de negocio: revisar una exportación o snapshot y restaurar desde Ajustes con autorización, comprobando cantidades, IDs y saldos por moneda. No inventar fechas o conversiones para pasar la validación.
- Base completa: restaurar SQL y archivos privados en un entorno aislado antes de promoverlos. Un JSON de negocio no recupera cuentas, auditoría o historial de revisiones de la base.
- Código: conservar versiones compatibles de dashboard y validador; ver las advertencias de rollback en MIGRACION.md.
- Acceso: `scripts/reset-password.php` acepta contraseña por entrada privada; no ponerla en argumentos, URL, chat o repositorio. No ejecutarlo durante una sincronización de código.

## Límites de esta copia pública

La sincronización incluye código instalado y documentación, no credenciales, datos ni reglas `.htaccess` privadas del hosting. Conservar las reglas actuales en una actualización; en un hosting nuevo comprobar redirección HTTPS, denegación de archivos privados y ausencia de listado de directorios. No copiar la aplicación completa dentro de una raíz web.

HostGator aloja la aplicación. GitHub es la fuente de mantenimiento; no configurar GitHub Pages para ejecutarla. No se requiere contratar una API de OpenAI.


## Calendario y MCP privado

Ver [OPERACION.md](OPERACION.md). Instalar los módulos nuevos junto al dashboard, validador y API de la misma versión. Conservar `config.php`, `.htaccess` principal, cuentas, alta deshabilitada y cron. No copiar planes con IDs de clientes, exportaciones ni `mcp-runtime/` a Git. Los archivos de `public/.well-known/` son metadatos públicos sin claves; adaptar su origen si se instala en otro dominio. La conexión OAuth requiere autorización del propietario y prueba real desde ChatGPT antes de considerarse terminada.
