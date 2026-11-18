# Acciones ampliadas MCP — 18 noviembre 2026

Base: `d8fbfecb676bff5eed1be27309fb2e720c0ae41b`.

## Instalación

Los originales `private/operations.php` y `private/mcp-tools.php` se leyeron desde cPanel y coincidieron byte por byte con la base. Antes de desplegar se copiaron únicamente ambos archivos a `private/backups/acciones-mcp-20261118/`, dentro del directorio privado del hosting (backups conserva 0700).

Solo se subieron esos dos PHP. Se volvieron a leer desde cPanel y ambos coincidieron byte por byte con los archivos probados. También se volvió a leer validate.php y su SHA-256 no cambió. No se subieron pruebas, documentación, config.php, .htaccess, runtime ni datos al hosting. No se registraron pagos ni se ejecutaron operaciones financieras en producción.

## Adaptaciones necesarias sobre el parche recibido

El validador instalado es posterior al de GitHub. Se inspeccionó y se usó una copia local para las pruebas, sin modificarlo en el servidor ni incorporarlo al commit.

- Confirmados `receivedMonthBasis=confirmed|approximate`. Producción requiere además `receivedTiming`: las operaciones que registran mes sin día añaden «Mes de recepción indicado mediante MCP; sin día exacto.». Al sustituir esa referencia por una fecha exacta o quitarla se limpia también esta anotación.
- Confirmado el ciclo Ads con vencimiento igual al fin, cuando servicio, servicePeriodDay y automatización lo respaldan. Se añadió una comprobación sintética con el validador instalado.
- Confirmado collaboratorShare con gross/collaboratorAmount/mine/currency/fromPeriod/collaborator/source y servicio de tipo Ads. Se corrigió el tipo de los servicios ficticios de los tests.
- `billingEditVersion` es versión del formato (1), no contador. Se conserva 1 al repetir charge_update y se prueba la segunda edición.
- La instrucción de importe extranjero exige mxn explícito o null: se aplica también si la conversión anterior era desconocida, con prueba de rechazo.

## Pruebas

Ejecutadas localmente con PHP 8.5.12 en procesos separados y almacenamiento SQLite aislado:

```sh
php -l private/operations.php
php -l private/mcp-tools.php
php tests/operation-backend.php "$PWD"
node --test tests/oauth-session.cjs tests/financial-regressions.cjs tests/operation-regressions.cjs
```

Con el validador instalado: dos lint correctos, EXTENDED OK, BACKEND OK y 18/18 pruebas aprobadas. También se repitieron backend y suite con el validador de GitHub: aprobados. Hay un aviso no bloqueante de deprecación de PDO::sqliteCreateFunction en el fixture bajo PHP 8.5.

El test HTTP aislado realiza tools/list autenticado en /mcp-test.php y verifica el enum exacto de diez acciones, sin preparar ni ejecutar ninguna operación financiera:

client_upsert, project_upsert, payment, group_payment, charge_create, charge_update, charge_status, payment_update, payment_remove, opening_payment.

## Verificación en servicio desplegado

18 noviembre 2026, 01:38:30 UTC (04:38:30 MSK; 17 noviembre, 19:38:30 CDMX): POST sin credenciales a /mcp-test.php con tools/list devolvió 401 y WWW-Authenticate con scope cubo:read cubo:write. La autenticación sigue siendo obligatoria; no se observó 406.

La conexión de prueba disponible en Codex exige reautenticación. Emmanuel verificó desde su conexión existente en Claude que prepare_operation aceptó charge_status y creó una propuesta en la revisión 3, sin commit. Es una confirmación autenticada aportada por el propietario de que el servidor acepta una de las nuevas acciones. No se presenta el tools/list local como una captura autenticada del catálogo completo del hosting. El enum de diez acciones se comprobó en el transporte HTTP aislado y en el código desplegado, verificado byte por byte. No se desconectó Claude ni se accedió a tokens privados.

apply_change no está definido en este parche: las escrituras siguen prepare_operation → aprobación privada del propietario → commit_operation. No se atribuye su ausencia a caché ni se promete que aparezca al abrir otro chat.

## Reversión

Desde cPanel, copiar operations.php y mcp-tools.php de private/backups/acciones-mcp-20261118/ a private/, sustituyendo solo esos dos archivos. No restaurar bases, runtime ni configuración. Repetir las comprobaciones de integridad y autenticación. Los datos y las propuestas aprobadas no se reponen con este rollback de código.
