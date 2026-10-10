# MCP de consulta para Clientes y Cobros

## Alcance de esta rama

`feature/clientes-cobros-mcp` prepara un conector MCP remoto, autenticado y **solo de lectura** para la instalación privada de Cubo SEO. Incluye búsqueda limitada de clientes y consulta individual de cuenta, servicios, cobros, pagos y saldos. No define herramientas para crear, editar o borrar datos.

El alcance de esta implementación es intencionalmente de solo lectura. Los permisos MCP disponibles en ChatGPT dependen del plan y de la configuración del espacio; véase [Developer mode and MCP apps in ChatGPT](https://help.openai.com/en/articles/12584461-developer-mode-and-mcp-apps-in-chatgpt). El servidor usa OAuth para proteger datos privados y no llama a la API de OpenAI.

La app activa sigue en HostGator. GitHub contiene solo código y documentación. Esta rama no se ha desplegado y no incluye `config.php`, clientes, pagos, exports, logs ni respaldos.

## Archivos añadidos

- `public/mcp.php`: endpoint JSON de Streamable HTTP para MCP.
- `public/oauth.php`: registro dinámico de cliente, autorización con PKCE S256, intercambio/rotación de tokens y documentos de descubrimiento.
- `public/.well-known/.htaccess`: rutas de descubrimiento para el subdominio. Integrar estas reglas solo después de revisar las reglas vigentes en HostGator.
- `private/mcp-auth.php`: validación de audiencia, expiración y scope.
- `private/mcp-schema.sql`: migración aditiva para clientes OAuth, códigos y tokens; no contiene registros.

## Migración futura

No ejecutar esta migración en producción desde esta rama. Antes de desplegar, revisar y respaldar HostGator, confirmar las reglas `.htaccess` activas, subir los archivos PHP dentro de `public/` y `private/` conservando la separación de carpetas, y ejecutar `private/mcp-schema.sql` en la base exclusiva de la herramienta. No reemplazar `private/config.php`, datos, copias ni tareas cron.

La base de OAuth guarda hashes de códigos y tokens, no sus valores en claro. El flujo acepta clientes públicos registrados dinámicamente, permite solo el callback HTTPS de ChatGPT, exige authorization code + PKCE S256, valida el parámetro `resource` y limita el scope a `clients:read`. El acceso al endpoint MCP verifica bearer token, audiencia, expiración y permiso en cada llamada. Cambiar la contraseña de Cubo SEO invalida los códigos y tokens existentes. No se aceptan API keys.

## Instalación en ChatGPT

Después de una revisión de seguridad, despliegue autorizado y prueba de la migración, el endpoint será `https://clientes.cuboseo.com/mcp.php`. En ChatGPT Web se agrega como app MCP personalizada con OAuth y registro dinámico de cliente (DCR). La persona autoriza el acceso con el usuario y contraseña de Cubo SEO; las credenciales se ingresan en la pantalla privada del subdominio, nunca en GitHub ni en el chat.

## Validación pendiente

GitHub Actions ya ejecutó correctamente `php -l` en todos los archivos PHP del proyecto y pasó las pruebas smoke de la allowlist OAuth en [la corrida 38086740822](https://github.com/emmanuelbarriosxcom-dev/cuboseo-dashboards/actions/runs/38086740822). No hay conexión a la base de producción, así que todavía hace falta:

1. Pruebas de registro DCR, login/consentimiento, PKCE, audience/resource, vencimiento y rotación de refresh token en una base temporal.
2. Prueba del handshake y llamadas MCP con MCP Inspector.
3. Revisión de `.htaccess` y prueba en staging antes de cualquier actualización de HostGator.
4. Confirmación de qué permisos MCP expone el plan de ChatGPT en el espacio del usuario.

No se debe anunciar como conectado ni listo para producción hasta completar esas comprobaciones.
