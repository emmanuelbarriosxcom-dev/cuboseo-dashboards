# QA de sincronización — 12 de octubre de 2026

Esta ronda valida el código descargado, no una instalación nueva ni una modificación de producción.

## Revisión adicional: ventana y vencimiento incompatibles

La regresión añadida reproduce el fallo del commit `4a082ba`: un JSON con ventana de cobro y fecha límite simultáneas era aceptado. Ahora ambos validadores rechazan esa combinación, también en cobros sin pagador. Se conservan ventanas con `due:null` o sin el campo, y vencimientos Ads exactos sin ventana.

- `node --test tests/financial-regressions.cjs`: 9 pruebas JavaScript aprobadas; 1 prueba de validación PHP omitida porque no existe PHP CLI local. Esta última ejercita directamente `validate_state` y se ejecutará automáticamente con el mismo comando cuando PHP esté disponible; no necesita base de datos.
- Sintaxis JavaScript completa comprobada con `node --check`.
- No se ejecutaron `php -l`, parser PHP ni integración PHP/MySQL en esta revisión adicional. Los 12 archivos analizados en la sincronización original corresponden al código anterior a esta corrección.
- Corrección propuesta en GitHub, sin despliegue, acceso a datos o cambios en HostGator. Actualizado el manifiesto para los dos archivos de código modificados.

## Ejecutado

- 16 archivos de código/recursos descargados individualmente de cPanel y comparados con la copia del repositorio. Solo `dashboard.html` y `validate.php` presentan cambios funcionales. Se eliminaron espacios finales del HTML al preparar el commit, sin alterar instrucciones.
- JavaScript completo del dashboard: `node --check` aprobado.
- `node --test tests/financial-regressions.cjs`: **8/8 pruebas aprobadas**, solo fixtures sintéticas. Verifican saldos/abonos sin fecha, importes desconocidos, pagos mensuales sin fecha/FX, fecha de recepción distinta al mes cubierto, no creación de pagos futuros, bloqueo de duplicados/sobrepagos, Ads con vencimiento exacto y ventana SEO distinta de fecha límite. Confirman que generar con facturación pausada no añade cobros ni cambia importes.
- Sintaxis de los **12 archivos PHP** aprobada con `php-parser` configurado para PHP 8.3. No se instaló PHP ni se ejecutaron scripts del hosting.
- Esquema: seis `CREATE TABLE IF NOT EXISTS`, sin registros, INSERT, USE o CREATE DATABASE. Idéntico al esquema anterior.
- Revisión de diff y búsquedas de credenciales, claves privadas, tokens, cuentas/rutas de producción y nombres de clientes reales: no detectados en los archivos publicados. No hay correos embebidos. El ejemplo de configuración contiene únicamente marcadores.
- Código responsive aprobado conservado: 44 × 44 px, Tarjetas iniciales en teléfono respetando preferencia y footer del servidor.
- Manifiesto SHA-256 actualizado para los 16 archivos de código/recursos publicados. `.gitignore` conservado y ampliado para logs, cachés y copias previas.

## No ejecutado / límites

- **`php -l` no disponible**: no hay intérprete PHP local. El parser comprueba sintaxis, no ejecución, extensiones PDO ni comportamiento del validador PHP frente a la base.
- No se ejecutó una prueba de integración PHP/MySQL, alta, envío de pagos o migración. Las pruebas de regresión ejercitan el motor JavaScript y serialización JSON; no sustituyen una prueba de persistencia/revisión de servidor.
- No se accedió a la base, configuración privada, exportaciones o respaldos. Tampoco se hicieron pruebas de navegador que guarden en producción.
- No se modificaron archivos de HostGator, cron, datos, web principal, GitHub Pages ni ajustes del repositorio.
- Safari/iOS y dispositivos físicos no se revisaron en esta ronda de código.
- El commit sincronizado `4a082ba` permitía un objeto importado con ventana y vencimiento simultáneos en algunas rutas, aunque el formulario lo impedía. La revisión adicional documentada arriba corrige los validadores; esta corrección no se presenta como desplegada en producción.

Los resultados históricos del 10 de octubre no se presentan como pruebas nuevas. Antes de cualquier despliegue futuro, seguir MIGRACION.md y completar las pruebas PHP/MySQL disponibles en el entorno de destino.
