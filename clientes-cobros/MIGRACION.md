# Actualización y datos sin fecha

Versión de código sincronizada: 12 de octubre de 2026. Esta documentación describe una actualización futura; **no se desplegó ni se migraron datos durante la sincronización**.

## Instalación existente

1. Obtener respaldo privado de archivos y base, más exportación de negocio. Registrar revisión del servidor y cantidades/saldos por moneda; nunca adjuntar estos archivos al repositorio público.
2. Probar una copia aislada con PHP 8.3/PDO MySQL. Conservar `config.php`, reglas `.htaccess`, permisos, alta deshabilitada y cron. No utilizar `setup.php` sobre la base existente.
3. Actualizar `private/dashboard.html` y `private/validate.php` como una misma versión, en una ventana sin escrituras. Son los únicos archivos de código que cambiaron frente a la sincronización del 10 de octubre. No sustituir configuración o datos ni desplegar todo el directorio a la raíz pública.
4. El esquema de las seis tablas permanece idéntico; el payload JSON conserva `version: 1`. **No hay ALTER, migración SQL ni importación automática que ejecutar.** Los nuevos campos son opcionales para registros anteriores. El SQL del repositorio es solo estructura para una instalación nueva.
5. Recargar las sesiones para evitar un dashboard anterior con el validador nuevo. Comparar IDs, registros e importes con el respaldo; verificar lectura y persistencia en la copia de prueba antes de aprobar una actualización real.
6. Mantener `settings.billingPaused=true` hasta aprobar calendario futuro. Una fecha de vencimiento conocida no autoriza generar meses posteriores.

## Saldos históricos sin fecha

`client.opening` contiene `kind`, `total`, `received`, `currency`, `date:null`, `due:null`, `payments` y `note`.

- `debt`: deuda inicial confirmada. `total` es el importe inicial; `received` y los abonos reducen su saldo.
- `contract`: importe contratado y parte recibida confirmados. Saldo = total − recibido − abonos posteriores.
- `prepaid_unknown`: trabajo prepagado pendiente, sin deuda; total/recibido `null`, moneda MXN en la implementación actual. No convertir el importe desconocido en cero o en un pago.
- `unresolved`: sin importe ni moneda confirmados; total/recibido/moneda `null`. No representa una deuda numérica confirmada.

Los abonos de `opening.payments` llevan `date:null`. No se copian a los pagos mensuales ni al ingreso de un mes y no crean vencimientos. Las notas identifican origen/limitaciones. No transformar resúmenes históricos pagados en servicios o pagos fechados. Un saldo pertenece a su cliente, no también al intermediario indicado en su ficha.

## Mensualidades confirmadas

- `charge.period` es el mes cubierto. `payment.date` es la fecha real de recepción, si se conoce; pueden pertenecer a meses distintos.
- Fecha desconocida: `dateStatus:'unknown'`, `date:null`. Se aplica al saldo del mes cubierto sin asignar un mes de ingreso en caja.
- Conversión desconocida: `conversionStatus:'unknown'`, `rate:null`, `rateDate:null`, `mxn:null`. El pago conserva su moneda, liquida/reduce el cobro y queda excluido de ingresos convertidos a MXN. No usar las tasas de Ajustes para completar estos campos.
- Los saldos por cobrar pueden mostrarse como aproximaciones según las tasas de Ajustes; esto no confirma el tipo de cambio de un pago histórico.
- `firstRegisteredPeriod` no prueba una fecha histórica de contratación. `nextExpectedPayment` tiene `received:false` y es una referencia, no un pago ni un cobro futuro.
- «Confirmar mensualidad y mes» rechaza crear otro acuerdo recurrente o cobro del mismo mes para el cliente. Revisar el existente antes de registrar otro; no usar esta función como generación recurrente automática.
- Las operaciones de pagos y guardado conservan referencias/revisiones para impedir duplicación. No regenerar IDs ni sumar pestañas históricas repetidas.

## Agrupación y vencimientos

`payerId` y `paymentGroup` agrupan cobros existentes por pagador y servicio; no añaden una segunda obligación al pagador.

- Ventana: `collectionWindow:{start,end}`, `due:null`. Es un intervalo de cobro posterior al periodo del servicio, no una fecha límite confirmada.
- Ads con día habitual pero mes no confirmado: `collectionPending:true`, `collectionDay`, `due:null`, sin ventana.
- Ads con vencimiento confirmado: `due:'AAAA-MM-DD'`, `collectionDay` coincidente con el día de esa fecha, sin `collectionPending:true` ni ventana. La fecha debe ser posterior al periodo del servicio. Modalidad/acuerdo y cobro existente deben revisarse por separado.

Editar vencimiento conserva ID e importe del cobro. No registrar pagos recibidos, pagos a colaboradores o meses adicionales por confirmar una fecha. No introducir nombres, tarifas ni registros reales en fixtures públicas.

## Reversión

Antes de aceptar escrituras con campos nuevos, conservar la pareja de archivos anterior para rollback de código. Una vez que existan datos nuevos, **no volver a un validador antiguo que los rechaza**. Probar la compatibilidad de la copia anterior con el payload vigente; si falla, mantener una versión compatible y evaluar recuperación en una copia aislada. Restaurar un respaldo de datos puede perder cambios posteriores y requiere una decisión explícita, no es un rollback automático.

Nunca reabrir el alta ni borrar tablas para resolver una actualización. Conservar respaldos diarios, configuración y web principal.
