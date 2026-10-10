# QA de la versión de servidor 1.0.0

Fecha: 10 de octubre de 2026. **Pruebas locales; instalación en HostGator pendiente.**

## Resultado

- 47 comprobaciones de integración aprobadas con aplicación PHP real y base de datos real.
- 13 comprobaciones del motor financiero aprobado, conservado en esta versión.
- Los 12 archivos PHP pasan la comprobación de sintaxis.
- JavaScript generado validado con parser ECMAScript; no se usa localStorage para clientes ni pagos.

Entorno: PHP 8.3.6 con PDO MySQL y MariaDB 10.11.14. El SQL utiliza tablas InnoDB, utf8mb4 y funciones compatibles con MySQL/Percona 5.7; **Percona 5.7 de HostGator no se ejecutó en esta prueba local**.

## Integración verificada

Acceso obligatorio al dashboard y datos; creación inicial única; sesión privada; hash de contraseña; protección de solicitudes con CSRF y origen; bloqueo de intentos fallidos; cambio de contraseña con invalidación de otras sesiones; cierre mediante POST y redirección HTTP a HTTPS en configuración de producción.

Guardado conjunto de cliente, servicio y cobro; persistencia tras recargar; pagos parciales, corrección y deshacer; rechazo de sobrepagos, relaciones rotas y simulación; conservación del precio y la moneda del acuerdo; proyectos con anticipo pagado y saldo sin vencimiento; pausa y reactivación sin duplicar el periodo.

Rechazo de una versión desactualizada; reintento de una operación con la misma referencia; rechazo del uso de esa referencia con otro contenido; pérdida de respuesta después de que el servidor confirma el cambio, sin duplicar la revisión; guardado sin conexión retenido para reintento y confirmado al reconectar.

Versiones anteriores y auditoría creadas en cada cambio confirmado; copia JSON diaria válida; extracción de versión para recuperar; retención de 31 archivos diarios; configuración, esquema, código privado y scripts fuera de la raíz web.

## Navegador y revisión visual

Chromium con ventanas y controles táctiles emulados: teléfono 390×844, iPad 834×1194 y escritorio 1440×1000. Se probaron cambios de orientación y altura reducida de 450 px.

Alta de cliente, proyecto puntual, pausa y reactivación, pago, corrección, deshacer, ajustes y acceso privado. Sin errores JavaScript no controlados ni desbordamiento horizontal de página. Las tablas conservan su desplazamiento interno.

Se inspeccionaron las capturas de login, ficha de cliente y formulario de alta. El acceso nuevo utiliza el logo vectorial y tipografía del dashboard aprobado.

Esto no equivale a una prueba física en Safari/iOS o iPadOS; la comprobación real en esos dispositivos forma parte de la entrega instalada.

## Verificación pendiente en HostGator

DNS y AutoSSL del subdominio; raíz pública separada y permisos; PDO en el PHP web y CLI del servidor; funcionamiento sobre Percona 5.7.44-48; límites efectivos de solicitudes y base; respaldo diario activado y ejecutado; recuperación; comportamiento desde dispositivos reales; prueba del agente autorizado mediante el navegador.

No se han creado subdominios, bases o usuarios en HostGator, contratado servicios, cargado datos reales ni creado un repositorio remoto.
