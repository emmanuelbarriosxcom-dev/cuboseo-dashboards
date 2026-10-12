# Operación: histórico, calendario y conexión privada

## Histórico

El histórico original se consulta en la ficha, separado de cobros y pagos mensuales. Los alias confirmados ayudan a buscar una ficha; no crean otro cliente ni fusionan proyectos. Las notas conservan referencias cuya fecha o moneda se desconoce. Una pestaña derivada de la hoja no representa pagos adicionales. Los saldos iniciales reconocidos y abonos sin fecha se mantienen en `opening`, sin inventar vencimientos ni ingresos de un mes.

## Calendario por servicio

La regla `service.automation` contiene `enabled`, `fromPeriod` y `collection`; puede conservar `payerId` y `paymentGroup`. Solo funciona para servicios recurrentes mensuales de mes calendario. Deben estar activos el cliente y la ventana del servicio. Un servicio puntual, pausado, inactivo o sin regla explícita queda excluido.

- `unknown`: pendiente con `due: null`, aunque no se conozca el día.
- `window`: ventana del mes siguiente, sin fecha límite exacta.
- `day`: día confirmado del mes cubierto si es adelantado, o del siguiente si es mes vencido.
- `previous_day`: día del mes anterior que cubre **el mes calendario completo siguiente**.

El inicio configurado es futuro. La migración actual comienza en noviembre de 2026; no reconstruye octubre ni meses anteriores. El anticipo del mes anterior puede preparar noviembre en octubre. Se conserva la tarifa por servicio y las tarifas temporales existentes.

**Se ejecuta al abrir la herramienta, no mediante cron de cobros.** El cron existente sigue siendo exclusivamente de respaldos. Si no se abre durante un tiempo, la siguiente apertura reúne únicamente periodos desde el inicio futuro autorizado, nunca histórico previo. Por servicio/mes no se vuelve a generar un cobro existente, incluso si está anulado. El guardado usa revisión optimista y bloqueo transaccional: dos sesiones no pueden guardar dos versiones del mismo periodo.

En la ficha, usar **Calendario automático** para pausar/reactivar o corregir una regla futura confirmada. Mantener los días desconocidos vacíos. `calendar-admin.php` aplica un plan de migración limitado sobre IDs existentes: no restaura ni reemplaza una base de datos. Muestra revisión antes de activar; no acepta un plan que añada cobros inmediatamente.

Los nuevos acuerdos de mes calendario configuran un inicio futuro explícito. La creación normal puede registrar únicamente su primer periodo actual autorizado. Los ciclos de aniversario y quincenales no se incorporan a esta nueva automatización: requieren un acuerdo específico; no se transforman silenciosamente a calendario.

## MCP privado para ChatGPT

Transporte Streamable HTTP, POST JSON-RPC, con OAuth Authorization Code + PKCE S256 y metadatos de descubrimiento. Sin endpoint anónimo de datos y sin llamadas a modelos/API de OpenAI.

- Producción: `https://clientes.cuboseo.com/mcp.php`.
- Prueba: `https://clientes.cuboseo.com/mcp-test.php`.
- Descubrimiento: `/.well-known/oauth-authorization-server` y `/.well-known/oauth-protected-resource`.
- Scopes: `cubo:read`, `cubo:write`.
- Tokens: una hora; no se implementa refresh token. Al vencer, volver a autorizar si ChatGPT lo solicita.
- Redirects permitidos: exclusivamente los oficiales de ChatGPT configurados en el código; PKCE, `state`, recurso y cliente se verifican.
- Cada escritura pasa por `prepare_operation` → revisión privada del propietario → `commit_operation`.
- Las propuestas vinculan usuario, cliente OAuth, recurso, revisión e ID de operación. Un reintento no duplica movimientos. Un conflicto requiere revisar una propuesta nueva.
- `search_clients`, `get_client`, `list_pending`: lectura.
- `client_upsert`, `project_upsert`, `payment`, `group_payment`: operaciones propuestas. Pagos parciales o completos se asignan al cobro existente; pagos agrupados se distribuyen entre sus cobros, sin crear un cargo adicional en el pagador. `groupReceiptId` identifica las aplicaciones de un mismo pago agrupado; `get_client` del pagador expone esos proyectos y aplicaciones por separado.

`mcp-sandbox-admin.php` inicializa una sola vez dos fichas y un cobro ficticios en tablas independientes `cubo_mcp_test_*`. Comparte únicamente la autenticación; **no copia los clientes reales**. La ruta de prueba fija ese contexto; ningún argumento del chat puede cambiarlo a producción.

El directorio privado `mcp-runtime/` almacena códigos, tokens y propuestas, con permisos 0700/0600, bloqueo y reemplazo atómico. No se publica ni se incluye en Git. No subir una copia privada del estado al repositorio.

### Conexión y criterio de cierre

El propietario debe añadir la app/MCP desde ChatGPT web, con OAuth, y autorizar los permisos de la cuenta. Primero conectar la URL de prueba. Desde un chat, consultar el cliente ficticio, preparar una actualización ficticia, aprobarla en la web y solicitar guardar; consultar nuevamente y repetir el mismo commit para comprobar que no duplica. Solo después conectar producción.

La conexión no está validada únicamente por instalar el endpoint. Debe demostrarse una consulta desde ChatGPT y una escritura en el entorno de prueba. La documentación de OpenAI usada es [Custom MCP servers](https://developers.openai.com/api/docs/guides/custom-mcp-server) y [Authentication](https://developers.openai.com/plugins/build/auth).

## Recuperación

Conservar archivos y exportación privada previos, configuración, alta inicial deshabilitada y cron. Para revertir código, detener temporalmente escrituras, restaurar dashboard/validador/API y los módulos de la misma versión. Para revertir la activación del calendario, pausar `billingPaused` y las reglas; conservar los movimientos ya generados y revisar antes de cualquier restauración de datos. No restaurar un backup viejo sobre operaciones posteriores.

## Corrección de sesión OAuth — 2026-10-12

La entrada desde ChatGPT compartía `CUBOSEOSESSID` (`SameSite=Strict`) con
la aplicación. Una navegación entre sitios puede omitir esa cookie, abrir
una sesión nueva y perder `oauth_pending` en el siguiente POST. No se debe
interpretar siempre ese caso como una caducidad temporal.

OAuth usa ahora una sesión independiente `CUBOOAUTHSESSID`, restringida a
`/oauth.php`, Secure/HttpOnly en HTTPS y SameSite=Lax. La cookie principal
permanece Strict, sin cambios en bootstrap.php. Login conserva la solicitud
al regenerar la sesión. Los formularios tienen destino explícito y un
identificador de flujo; se rechazan formularios de otra solicitud/pestaña.
La solicitud vence explícitamente a los 20 minutos. La ausencia de sesión
y la caducidad real tienen mensajes distintos. CSRF, Origin, PKCE S256,
cliente, recurso y redirect registrado siguen siendo obligatorios.

Validación ejecutada: 18 pruebas Node, sin omisiones, con PHP 8.3 real y
SQLite aislado. La nueva prueba HTTP recorre apertura, login, regeneración,
consentimiento y callback; rechaza CSRF incorrecto, flujo cruzado, recurso
incorrecto y consentimiento repetido. Las pruebas anteriores cubren PKCE,
reuso del código, permisos, revocación y reintentos de escritura.

Reversión: restaurar juntas las copias privadas de `private/oauth.php` y
`public/oauth.php`; iniciar una solicitud nueva desde ChatGPT. No restaurar
base de datos, calendarios ni configuración. La consulta y escritura desde
el chat siguen pendientes de consentimiento del propietario, exclusivamente
en `mcp-test.php`; producción no conectada.
