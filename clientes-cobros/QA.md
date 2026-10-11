# QA e instalación de PR #1 — 12 de octubre de 2026

Corrección revisada: `e641892916039c7e1b8693c26cba777b1f42b361`. Rechaza importaciones que combinen `collectionWindow` y una fecha `due` no nula, tanto con pagador como sin él. Mantiene ventanas sin vencimiento y vencimientos exactos válidos de Ads.

## Pruebas realmente ejecutadas

- **PHP CLI nativo 8.3.35** compilado en un directorio temporal aislado desde el tar oficial de php.net. SHA-256 verificado: `e60396276fd57e8210f88919d9acd4aab98d65fbb7f431c13285ca1fb04ff205`. No se alteró PHP del hosting ni se instaló un runtime global en el equipo.
- `php -l`: **12/12 archivos PHP aprobados** de `private`, `public` y `scripts`.
- `node --test tests/financial-regressions.cjs`, con ese PHP real en PATH: **10/10 pruebas aprobadas, cero omitidas**; nueve JavaScript y una PHP. La prueba PHP ejecuta directamente `validate_state` con fixtures sintéticas.
- Conflictos ventana + vencimiento rechazados para SEO, Ads y cobros sin pagador. Ventanas con `due:null` o sin la propiedad `due` aceptadas. Ads con fecha exacta y día coincidente aceptado.
- Regresiones existentes aprobadas: saldos/abonos sin fecha, importes desconocidos, recepción distinta del mes cubierto, FX desconocido, parciales, duplicados/sobrepagos y generación pausada.
- Copia privada del estado vigente aceptada por ambos validadores corregidos, sin conectar a la base real. El respaldo no se incorpora al repositorio.

## Instalación y comprobación

Antes del reemplazo se descargaron los archivos afectados y se exportó el estado de negocio a un respaldo privado. Producción coincidía con la sincronización anterior; solo había espacios finales en el HTML, sin cambios funcionales posteriores.

Se retiró temporalmente `public/api.php` a la carpeta privada para impedir nuevas escrituras durante el reemplazo de la pareja. Se cargaron únicamente `private/dashboard.html` y `private/validate.php`, se descargaron de nuevo y sus SHA-256 coincidieron con la PR. Después se devolvió la API a su ruta original: su contenido quedó idéntico al respaldo previo.

Tras recargar, se confirmó la regla nueva en el JavaScript público servido a la sesión. La exportación de negocio completa posterior al QA es idéntica a la previa: mismas colecciones, IDs, importes, saldos, notas y ajustes. Generación automática detenida; sin operaciones ficticias, importaciones o pagos nuevos en producción. Configuración, credenciales, alta pública deshabilitada, cron, respaldos diarios y web principal conservados. Respaldo privado final guardado.

## Responsive de solo lectura

Navegador integrado Chromium, tamaños emulados comprobados mediante `innerWidth/innerHeight`:

| Tamaño | Pantallas | Resultado |
|---|---|---|
| Teléfono 390 × 844 | Vista universal, Cobros, Clientes | OK |
| iPad 834 × 1194 | Vista universal, Cobros, Clientes | OK |
| Escritorio 1440 × 1000 | Vista universal, Cobros, Clientes | OK |

Sin overflow horizontal de página; navegación operativa, estado «Datos guardados» y sin aviso de error posterior a la recarga. Capturas conservadas privadamente, fuera de GitHub. La API mostró el aviso transitorio esperado durante mantenimiento; desapareció al restablecerla y recargar.

## Límites

No se ejecutó una integración de escritura PHP/MySQL ni se provocó un guardado ficticio en producción. La prueba PHP real valida payloads, no necesita PDO; el CLI aislado mínimo no sustituye la configuración PHP del hosting. No se probaron dispositivos físicos, Safari o teclado virtual iOS. No se modificó GitHub Pages.

Las omisiones de PHP documentadas en la revisión inicial de la PR y en la sincronización anterior quedan superadas por los resultados de esta instalación, no se convierten retroactivamente en pruebas ejecutadas entonces.
