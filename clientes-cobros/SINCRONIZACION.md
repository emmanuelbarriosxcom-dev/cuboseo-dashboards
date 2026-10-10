# Sincronización del código instalado — 10 de octubre de 2026

## Fuente y alcance

Copia de los archivos instalados obtenida directamente mediante descargas del Administrador de archivos de cPanel, usando la sesión autorizada. No procede del paquete de preinstalación. El subdominio activo sigue en <https://clientes.cuboseo.com/>; su raíz pública está separada de las carpetas privadas y los scripts administrativos.

Solo se consultaron carpetas y se descargaron archivos: no se editaron archivos del servidor, no se ejecutaron scripts, no se cambiaron ajustes, no se abrió phpMyAdmin ni se exportó la base. GitHub conserva código y documentación; no se despliega la aplicación desde GitHub Pages ni se modifica su configuración.

## Archivos conservados

- `public/`: `api.php`, `auth.css`, `index.php`, `login.php`, `logout.php`, `password.php`.
- `private/`: `bootstrap.php`, `config.example.php`, `dashboard.html`, `logo.svg`, `schema.sql`, `setup.php`, `validate.php`.
- `scripts/`: `backup.php`, `recover-snapshot.php`, `reset-password.php`.
- `INSTALACION.md` y `QA.md`: documentación histórica conservada del servidor. Sus referencias a «pendiente de instalación» y resultados locales describen la preparación original, no el estado actual ni pruebas ejecutadas durante esta sincronización.
- `SOURCE_MANIFEST.sha256`: hashes de los 18 archivos anteriores para verificar la copia.

El `README.md` y `.gitignore` existentes se conservan sin modificaciones. La descripción anterior del README sobre importación pendiente queda superada por este registro de sincronización.

## Exclusiones

No se descargó ni publicó `private/config.php`. No se incluyeron credenciales, claves de instalación reales, `.env`, datos de clientes/pagos, exportaciones JSON, dumps SQL, logs, cachés, respaldos, copias anteriores del dashboard ni configuración del servidor. Las reglas `.htaccess` de producción y el `.gitignore` del servidor no se copiaron; el administrador de archivos ocultaba dotfiles y no se cambió esa preferencia. La búsqueda de solo lectura confirmó dos `.htaccess`, en `public/` y `private/`, sin obtener su contenido actual. Esta copia es para mantenimiento de código, no un respaldo completo ni un paquete que deba desplegarse automáticamente.

El `schema.sql` incluido contiene únicamente seis instrucciones CREATE TABLE IF NOT EXISTS, sin INSERT, registros, usuarios reales, hashes de acceso, USE ni CREATE DATABASE. Es una excepción revisada a la exclusión general de `*.sql` del .gitignore; no debe eliminarse esa exclusión para incorporar volcados.

`config.example.php` contiene solo valores de ejemplo y marcadores. Su zona America/Tijuana es un ejemplo histórico: no refleja ni debe reemplazar la configuración privada vigente Europe/Moscow. No se reabre el alta: `setup.php` se conserva como código en `private/`, fuera de la raíz pública.

El dashboard conserva una función de demostración con nombres explícitamente ficticios y campos de contacto vacíos; no contiene registros reales ni un volcado del estado de la sesión. El HTML contiene el marcador SERVER_BOOT, y el servidor incorpora datos solo al servirlo a una sesión autenticada. No se publicó el HTML autenticado con datos insertados.

## Comprobaciones

- Código descargado: coincide con la versión instalada que contiene las mejoras responsive aprobadas.
- Controles: reglas de al menos 44 × 44 px, incluidos controles secundarios.
- Presentación: Tarjetas iniciales en teléfono; preferencia Tabla/Tarjetas guardada localmente mediante `cubo-cobros-layout-v1`, independiente de los datos financieros del servidor.
- Footer: «Datos guardados en el servidor».
- JavaScript extraído de dashboard.html: `node --check` aprobado.
- Revisión manual y búsqueda de claves privadas, tokens, correos y credenciales embebidas: no se detectaron secretos ni datos personales reales en los archivos incluidos. El hash de reserva del login es un valor de ejemplo del código, no un hash obtenido de la base de producción.
- PHP: 12 archivos incluidos. `php -l` NO EJECUTADO: PHP no está disponible en el entorno local. No se instaló un intérprete ni se ejecutaron scripts en HostGator para suplirlo.
- No se generaron clientes, servicios, cobros ni pagos durante esta sincronización.

## Mantenimiento posterior

Revisar cambios en GitHub no implica desplegarlos. Antes de actualizar HostGator, obtener autorización y respaldo privado, mantener `config.php`, datos y respaldos fuera del repositorio público, y validar PHP en un entorno disponible. Conservar las reglas y permisos de producción; no copiar este directorio completo dentro de una raíz pública ni ejecutar el alta sobre una instalación existente.
