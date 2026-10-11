# Clientes y Cobros — Cubo SEO

Herramienta interna PHP/MySQL de clientes, servicios, cobros y pagos. La aplicación activa se aloja en [clientes.cuboseo.com](https://clientes.cuboseo.com/) con HostGator. Este directorio contiene código y documentación; **GitHub Pages no ejecuta esta aplicación**.

## Versión sincronizada

Código descargado de la instalación activa el **12 de octubre de 2026**, sin modificar producción. Incluye:

- Ficha histórica sin fechas ni cobros, sin reconstruir movimientos históricos.
- Saldos iniciales, contratos con recepción parcial y abonos históricos sin fecha; importes desconocidos conservados como desconocidos.
- Confirmación mensual que separa mes cubierto, fecha de recepción y conversión a MXN. No aplica una conversión supuesta a los pagos confirmados.
- Cobros por proyecto/servicio agrupados por pagador, con ventana de cobro o vencimiento Ads confirmado, sin un segundo cobro en la ficha del intermediario.
- Pausa de generación automática, protección contra duplicados y conflictos de revisión del servidor.
- Controles táctiles de al menos 44 × 44 px, Tarjetas iniciales en teléfono cuando no hay preferencia guardada y footer «Datos guardados en el servidor».

Los registros reales, configuración, credenciales, exportaciones y respaldos quedan fuera de este repositorio público. La función demo contiene exclusivamente datos ficticios y el servidor rechaza importaciones de demostración. El HTML fuente conserva `<!--SERVER_BOOT-->`; no se ha publicado el HTML servido a una sesión con registros insertados.

## Estructura

- `public/`: única raíz web; acceso, API y estilos de autenticación.
- `private/`: dashboard fuente, validación, soporte PHP, logo y ejemplo de configuración. Debe permanecer fuera de la raíz web.
- `scripts/`: mantenimiento mediante CLI, fuera de la raíz web.
- `tests/`: pruebas del motor con fixtures sintéticas, sin conexión ni datos de producción.
- [INSTALACION.md](INSTALACION.md): instalación nueva.
- [MIGRACION.md](MIGRACION.md): actualización y semántica de datos sin fecha.
- [QA.md](QA.md): comprobaciones de esta sincronización y límites.
- [SINCRONIZACION.md](SINCRONIZACION.md): procedencia y exclusiones.

El esquema SQL contiene solo estructura vacía; no incluye registros. `config.example.php` contiene marcadores y una zona horaria de ejemplo; nunca debe sustituir la configuración privada existente.

## Comprobaciones locales

```sh
node --test tests/financial-regressions.cjs
# Si PHP está disponible:
find private public scripts -name '*.php' -exec php -l {} \;
```

No hace falta una API de OpenAI de pago para operar los formularios con una sesión de navegador autorizada. La sincronización de este directorio no despliega código ni activa generación de cobros.
