# Despliegue del conector Claude MCP — 18 noviembre 2026

Base: `f63fc9ec69816aa3a3bc4d258d835b952778b097`.

## Alcance e integridad

Los cinco archivos instalados se leyeron desde el Administrador de archivos de cPanel y coincidieron byte por byte con el commit base. No fue necesario mezclar cambios posteriores. Se aplicó el parche aprobado sin cambios adicionales de lógica.

Se subieron exclusivamente:

- `private/oauth.php`
- `public/oauth.php`
- `public/mcp.php`
- `public/mcp-approve.php`
- `public/.well-known/oauth-authorization-server`

Tras la carga, los cuatro PHP se volvieron a leer desde cPanel; el JSON se descargó desde su URL pública. Los cinco coincidieron byte por byte con la versión probada (SHA-256).

No se modificaron config.php, .htaccess, datos, reglas WAF ni la aprobación obligatoria de escrituras. No se registraron pagos ni movimientos en producción.

## Copia privada y reversión

Antes de subir código se copiaron los cinco originales y `private/mcp-runtime/security.json` dentro del directorio privado del hosting:

- `private/backups/claude-mcp-20261118-private/`: OAuth privado y copia de security.json.
- `private/backups/claude-mcp-20261118-public/`: los cuatro originales públicos; el JSON se conserva aquí con su nombre original.

El directorio padre backups conserva permisos 0700 y security.json 0600. El estado de seguridad solo se copió en el hosting: no se abrió, descargó ni incorporó al repositorio.

Rollback de código: copiar oauth.php del primer respaldo a private/; copiar oauth.php, mcp.php y mcp-approve.php del segundo a public/; copiar oauth-authorization-server del segundo a public/.well-known/. No restaurar security.json automáticamente ni sobrescribir el runtime activo. Volver a comprobar los endpoints después de la reversión.

## Pruebas de la versión desplegada

PHP CLI 8.5.12, binario oficial php.new/Herd descargado a un directorio temporal local, con PDO SQLite. No se cambió el PHP del hosting.

Cada orden de lint se ejecutó en un proceso separado:

```sh
php -l private/oauth.php
php -l public/oauth.php
php -l public/mcp.php
php -l public/mcp-approve.php
php tests/operation-backend.php "$PWD"
node --test tests/oauth-session.cjs tests/financial-regressions.cjs tests/operation-regressions.cjs
```

Resultado: cuatro lint correctos, `BACKEND OK`, 18 pruebas aprobadas, 0 fallidas y 0 omitidas. El fixture SQLite emitió un aviso de deprecación de PDO::sqliteCreateFunction en PHP 8.5; no falló ninguna aserción. Todas las pruebas con datos sintéticos se ejecutaron localmente y en almacenamiento aislado.

## Verificación pública sin credenciales

Último archivo desplegado: 2026-11-18 00:33:23 UTC (03:33:23 MSK; 2026-11-17 18:33:23 CDMX), según Last-Modified del JSON público.

Comprobación: 2026-11-18 00:33:34–35 UTC.

| Solicitud | Resultado |
| --- | --- |
| GET `/.well-known/oauth-authorization-server` | 200; grant_types_supported contiene authorization_code y refresh_token |
| POST JSON-RPC initialize a `/mcp-test.php`, sin Authorization | 401; cuerpo «OAuth requerido.» |
| WWW-Authenticate | Bearer resource_metadata="https://clientes.cuboseo.com/oauth.php?action=resource&sandbox=1", scope="cubo:read cubo:write" |

No se observó 406 y no se desactivó ni relajó ModSecurity.

## Validación pendiente del propietario

El despliegue técnico está verificado. El consentimiento, lectura y flujo de aprobación/escritura desde Claude quedan pendientes de la prueba del propietario en `https://clientes.cuboseo.com/mcp-test.php`. Instalar el código no acredita por sí solo una conexión de extremo a extremo desde Claude.
