# Sincronización del código instalado — 12 de octubre de 2026

Corrección posterior propuesta: el dashboard y el validador PHP rechazan importaciones con ventana y vencimiento simultáneos. Esta diferencia de código respecto al commit sincronizado `4a082ba` no ha sido instalada en HostGator. El manifiesto identifica los archivos de esta versión de GitHub; no certifica coincidencia actual con producción. Ver la revisión adicional en QA.md.

## Fuente

Descarga individual desde el Administrador de archivos de cPanel de la instalación activa de `clientes.cuboseo.com`. Se revisaron las carpetas hermanas `public`, `private` y `scripts`; no se usó un ZIP antiguo como versión vigente. La comparación con el commit anterior confirma que los únicos archivos de código modificados son `private/dashboard.html` y `private/validate.php`.

Se conservaron los otros 14 archivos de código/recursos idénticos. La preparación local retiró espacios finales en el dashboard sin alterar instrucciones. `SOURCE_MANIFEST.sha256` identifica los 16 archivos publicados. README, instalación y QA fueron actualizados; se añadió MIGRACION.md y pruebas sintéticas. El README y .gitignore existentes se editaron en su sitio, sin sustituir el repositorio.

## Funcionalidad incorporada

Ficha histórica; saldos iniciales/abonos sin fecha; contratos con recepción parcial; importe prepagado desconocido y caso sin resolver; mensualidad y recepción separadas; pagos sin fecha o conversión confirmada; cobros agrupados sin duplicación del pagador; ventana SEO y vencimiento Ads; generación pausada; ajustes responsive previamente aprobados. Se sincronizó lógica, no clientes, importes particulares o movimientos de la aplicación.

## Exclusiones

No se abrió ni descargó `config.php`, `.env`, base de datos, exportaciones, logs, cachés, respaldos o copias `before-*`. No se abrió phpMyAdmin, no se generó un dump ni se ejecutaron scripts. Las reglas `.htaccess` de producción siguen fuera de la copia pública y no se modificaron.

`schema.sql` contiene estructura vacía, idéntica a la anterior. Se conserva la exclusión general `*.sql` del .gitignore para impedir dumps adicionales; este esquema ya versionado es la excepción revisada. `config.example.php` es un ejemplo, no la configuración vigente. El dashboard mantiene el marcador SERVER_BOOT, sin datos de sesión insertados; su demo es ficticia.

## Validación y límites

Ver [QA.md](QA.md): 8 regresiones sintéticas aprobadas, sintaxis JavaScript válida, 12 archivos analizados con parser PHP 8.3 y revisión de privacidad/diff. `php -l` y la integración PHP/MySQL no se ejecutaron por falta de intérprete local. No se hicieron nuevas operaciones en producción para suplir ese límite.

Esta sincronización es únicamente GitHub: **no despliega a HostGator ni cambia GitHub Pages**. Las instrucciones para actualización posterior están en MIGRACION.md; no ejecutar el alta ni sustituir configuración/datos en una instalación existente.

## Etapa posterior: corrección instalada de PR #1

El 12 de octubre se revisó e instaló `e641892916039c7e1b8693c26cba777b1f42b361`, tras nueve regresiones JavaScript y una PHP real aprobadas, sin omitir ninguna; `php -l` pasó en los 12 archivos PHP. Esta etapa sí actualizó los dos validadores de producción, con respaldo privado antes/después y API temporalmente retirada para evitar nuevas escrituras. La sincronización inicial descrita arriba fue una etapa anterior de solo lectura.

Ambos archivos instalados coincidieron por SHA-256 con la PR. La API se restituyó sin cambios de contenido; la exportación completa de negocio posterior al QA fue idéntica a la anterior. No se publicaron datos, capturas financieras, configuración, respaldos o credenciales. La PR se fusionó solo después de verificar instalación, datos y responsive. Ver QA.md para resultados y límites reales.


## Actualización operativa — 12 octubre 2026

La versión activa previa se comparó con main; dashboard, validador, API y bootstrap coincidían. Se conserva backup privado previo y posterior de archivos y estado. Se añaden calendario explícito, módulos compartidos de guardado/operaciones y MCP OAuth con sandbox. No se incluyen planes con IDs reales, clientes, pagos, config.php, tokens, propuestas, exports, logs o backups. GitHub Pages no se usa. Ver OPERACION.md y QA.md para ejecución al abrir y límites de la prueba real de ChatGPT.
