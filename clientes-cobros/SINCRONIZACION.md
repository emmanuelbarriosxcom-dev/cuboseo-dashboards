# Sincronización del código instalado — 12 de octubre de 2026

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
