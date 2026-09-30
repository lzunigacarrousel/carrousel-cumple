# Encargo para el practicante: adaptar el proceso de cumpleaños

**Responsable de implementación:** practicante de desarrollo, por asignar en GitHub.

**Revisor y responsable de decisiones comerciales:** FernandoZL.

**Estado:** encargo preparado; cambios funcionales pendientes de implementación. Trabajar sobre [Carrousel Cumple](https://github.com/FernandoZL/carrousel-cumple), conservando su diseño Helpdesk, permisos y base central. No comenzar otra aplicación.

## Objetivo y proceso recibido

Adaptar la aplicación al proceso de registro, generación y canje que compartió negocio. Se conservan como referencia el [documento original en PDF](referencias/proceso-cumpleaneros.pdf) y su [versión Markdown](referencias/proceso-cumpleaneros.md). Son documentos de requisitos: los ejemplos no son catálogos reales ni instrucciones para acceder a servicios externos.

**Decisión confirmada por el propietario el 30/09/2026:** cambiar la vigencia a **15 días antes, día del cumpleaños y 14 días después**, extremos incluidos: 30 fechas calendario. Esta decisión sustituye el acuerdo anterior de −15/+15 para la nueva implementación. El código actual todavía usa −15/+15; este documento no acredita que ya se haya cambiado.

Cumpleaños de ejemplo: **5 de diciembre de 2026**. Inicio esperado: **20 de noviembre de 2026**. Último día válido: **19 de diciembre de 2026**. El 20 de diciembre debe rechazarse. No ajustar silenciosamente los cupones ya emitidos: presentar su tratamiento antes de migrar; conservar por defecto las fechas ofrecidas.

## Qué existe y qué falta

| Tema | Base existente | Trabajo del practicante |
| --- | --- | --- |
| Acceso del personal | OTP por correo, roles, cuentas y centros | Reutilizar; mantener permisos y revocación de sesiones |
| Registro | Nombre, día/mes, correo, teléfono opcional y tutor obligatorio si se marca menor | Incorporar nacimiento completo, cálculo de edad y validaciones del flujo de niños/tutores |
| Fechas | Ventana −15/+15 | Implementar −15/+14 en nuevas emisiones y actualizar pruebas, textos, manual y tutorial |
| Identidad del parque | Centro desde la cuenta; catálogo con código y referencia importada | Confirmar con negocio cuál es el ID SAP y documentar su relación, sin equipararlo a un ID interno por suposición |
| Cupón | Código único, emisión por campaña/año, enlace privado y QR | Revisar presentación; definir y entregar la imagen del cupón solicitada por el proceso |
| Correo | Cola SMTP con estados, reintentos y revisión de resultados inciertos | Conservarla y probar la nueva presentación sin mensajes reales |
| Canje | Validación central y actualización atómica de un único uso | Incorporar factura obligatoria y el flujo POS/SemnoX antes de confirmar |
| Beneficio | Configurable por campaña; el cupón conserva lo emitido | Representar la propuesta 15 tokens / Q45 sin fijarla en código ni activar una campaña real |
| Trazabilidad | Cuenta, centro y fechas de registro/canje, auditoría | Completar factura, referencia SAP y beneficio efectivamente entregado |
| Ayuda | Manual y tutoriales dentro de la app | Actualizar por perfil y mantener la línea gráfica Helpdesk |

## Entregas, en orden

### 1. Preparar entorno y presentar diseño del cambio

- Leer [CONTRIBUTING.md](../CONTRIBUTING.md), [INSTALACION.md](../INSTALACION.md) y [AGENTS.md](../AGENTS.md).
- Clonar e instalar con Composer. Trabajar con la base aislada y datos ficticios.
- Ejecutar `pwsh -File tools/check.ps1 -Integration` y adjuntar resultado inicial.
- Entregar propuesta breve de campos, migración, pantallas y flujo de facturación. Identificar las decisiones abiertas de este documento.
- Reutilizar los componentes del Helpdesk; no rediseñar navegación, tarjetas, formularios o manuales desde cero.

**Aceptación:** entorno reproducible, pruebas de base verificadas y propuesta revisable antes de modificar canje/facturación.

### 2. Registro, edad y relación SAP

- Capturar fecha completa; rechazar fechas futuras o imposibles y calcular edad a partir de esa fecha, sin un campo editable para la edad.
- Para el flujo de niños, recoger nombre del tutor, teléfono y correo; validar en servidor además del navegador.
- Conservar autorización de participación y separar la aceptación opcional de otras promociones.
- Guardar cuenta, centro y fecha/hora del alta desde el servidor, sin selector de parque para el operador.
- Verificar el catálogo autorizado de SAP. Los códigos de los ejemplos del documento son ilustrativos y no deben importarse como verdad; incluso las tablas del ejemplo asignan distintos códigos a un mismo nombre.
- Diseñar migración repetible. Los clientes existentes solo tienen día y mes: **no inventar su año de nacimiento**; documentar cómo se completarán sin perder registros ni alterar identidades por accidente.
- Conservar ceros iniciales si los identificadores SAP los usan. Evitar que cambiar el nombre o código de un centro destruya la trazabilidad histórica.

**Aceptación:** caso de cumpleaños anterior/posterior a la fecha actual, fecha imposible, cliente existente sin año, tutor incompleto y suplantación de centro; todos con resultado esperado documentado.

### 3. Vigencia, emisión y presentación del regalo

- Cambiar la ventana de nuevas emisiones a −15/+14 y mantener el tratamiento del 29 de febrero existente, salvo nueva decisión explícita.
- Mantener una emisión por cliente, campaña y año. Guardar el registro no debe enviar o crear el cupón dentro de esa misma petición.
- Alta dentro de la ventana: emitir en la siguiente ejecución habilitada. Alta fuera: esperar su siguiente ventana, sujeto a campaña activa y sus fechas.
- Mantener código global único, beneficio y condiciones conservados en cada cupón.
- Preparar cupón e imagen con código, regalo, vigencia, condiciones y QR/enlace. No incluir teléfono, correo del tutor ni datos del personal en una vista pública.
- Conservar la oferta configurable. La propuesta del documento requiere aclarar si tokens y Q45 son alternativas según el parque; no tratarlos como dos regalos acumulables.

**Aceptación:** fechas del ejemplo, cambio de año, 29 de febrero, alta en el último día, alta posterior, repetición del proceso y cambio de campaña. Capturar un correo ficticio con el cupón para revisión.

### 4. Validación, factura y confirmación del canje

Flujo solicitado: **consultar cupón → validar → facturar en POS/SemnoX → introducir factura → confirmar canje → entregar regalo**.

- Mostrar mensajes diferenciados: código inexistente, aún no vigente, vencido, anulado y ya canjeado.
- Validar factura obligatoria en API/servidor, incluso si alguien omite el formulario. No marcar canjeado cuando falta o falla ese dato.
- Conservar factura como texto para admitir series y ceros; acordar formato y alcance de unicidad según el sistema emisor. No asumir que un número es único entre todos los parques.
- Confirmar en una misma transacción factura, estado, cuenta, parque, referencia SAP, beneficio y fecha/hora. Revalidar estado y vigencia al confirmar.
- Mantener un solo canje exitoso aun con peticiones simultáneas de dos parques. Un reintento por pérdida de respuesta debe reconocer el resultado previo sin duplicar entrega ni modificar la factura original.
- Mostrar los datos permitidos del canje anterior, sin ampliar acceso a información personal de otro parque.
- No implementar una reversión CANJEADO → VIGENTE como operación ordinaria.

**Punto de diseño obligatorio:** consultar un cupón válido no lo reserva. Dos parques podrían facturar antes de que uno confirme; la transacción actual impide dos canjes pero no puede deshacer una factura externa. Presentar cómo se coordinará ese intervalo: por ejemplo, una reserva temporal con vencimiento y resolución de abandonos, o un procedimiento acordado de conciliación/cancelación en POS. El revisor debe aceptar la solución antes de operar. No mantener una transacción SQL abierta mientras el cajero factura.

**Aceptación:** factura vacía, fallo al guardar, dos parques simultáneos, doble clic, respuesta perdida, cupón que vence durante la facturación y ausencia de conexión central. Probar además la solución acordada para factura emitida sin canje confirmado.

### 5. Trazabilidad, ayuda y entrega final

- Mostrar/exportar, según permisos: registro, código, parque de registro y de canje con sus referencias SAP, cuenta de cada operación, fechas de generación/envío/canje, factura y beneficio utilizado.
- Revisar que guardar el número de factura no se presente como una integración automática con SAP o POS: esa integración no existe en el alcance actual.
- Actualizar ayuda, tutoriales, contrato de API, instalación y flujo del proceso. Conservar `form.getAttribute('id')` en los formularios administrativos.
- Entregar PR con migración, capturas ficticias de escritorio/móvil, pruebas, pasos de actualización y recuperación, decisiones pendientes y limitaciones.

**Aceptación:** un revisor puede instalar desde cero, actualizar una base de prueba existente y reproducir registro → emisión → correo capturado → factura → canje en otro parque → bloqueo del segundo uso.

## Decisiones que debe resolver con el responsable

1. **Edad elegible:** el documento habla de niños, pero no fija un límite. No inventar una edad máxima ni excluir clientes existentes automáticamente.
2. **SAP:** confirmar el catálogo oficial y la correspondencia con centros. `source_park_id`, código de centro y código SAP no son necesariamente lo mismo.
3. **Beneficio por tecnología:** confirmar el uso de 15 tokens o Q45 y cómo identificar los parques aplicables. Administración seguirá configurando la oferta.
4. **Factura externa:** definir serie/número/sistema/centro necesarios y qué hacer si se factura pero el canje no puede confirmarse. Crear el producto PROMOCIÓN CUMPLEAÑERO en POS/SemnoX requiere coordinación del responsable con el proveedor.
5. **Imagen:** confirmar si se necesita una imagen adjunta al correo, descargable desde el cupón o ambas; revisar la propuesta antes de implementar ese formato.

Estas decisiones no impiden preparar entorno, migración propuesta o pruebas de la nueva ventana. No activar beneficios, crear productos externos ni enviar promociones para resolverlas por ensayo.

## Forma de trabajo y revisión

Crear una rama `codex/practicante-cumpleaneros` desde `main`, con entregas pequeñas revisables mediante PR. El practicante implementa; FernandoZL revisa el alcance y autoriza la puesta en operación. Adjuntar evidencia real de los comandos ejecutados; no marcar una tarea terminada porque solo existe el formulario.

Usar únicamente `carrousel_cumple_test` para las pruebas que reinician datos y correo capturado. No usar credenciales de producción, modificar la instalación operativa, activar su tarea programada ni enviar correo real durante el desarrollo.

GitHub Actions está configurado, pero su ejecución quedó bloqueada por facturación/límite de cuenta en la verificación del 30/09/2026. Mientras se resuelve, adjuntar resultados locales; no afirmar que la validación remota pasó.

### Lista de revisión final

- [ ] Migración conserva registros existentes y no inventa años ni IDs SAP.
- [ ] Fecha de nacimiento, edad y tutor se validan en servidor.
- [ ] Nuevos cupones cumplen −15/+14 y se define la transición de los anteriores.
- [ ] Oferta configurable y datos del cupón conservados.
- [ ] Factura obligatoria guardada junto con el canje atómico.
- [ ] Concurrencia entre parques y reintentos comprobados.
- [ ] Resuelto el intervalo entre validación y facturación externa.
- [ ] Trazabilidad, permisos y privacidad verificados.
- [ ] Correo/imagen y ayuda revisados con datos sintéticos.
- [ ] Instalación y actualización reproducibles, PR revisado antes de operar.
