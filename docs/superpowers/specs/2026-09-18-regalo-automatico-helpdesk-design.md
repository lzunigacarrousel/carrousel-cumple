# Carrousel Cumple: regalo automático y base Helpdesk

Estado: especificación preparada para revisión. El cambio de aplicación todavía no está implementado.

## Intención y decisiones del usuario

Reconocer el cumpleaños de los clientes de Carrousel mediante un regalo automático. El cliente se registra una vez en esta nueva aplicación y recibe su cupón por correo electrónico cuando corresponde. No solicita el cupón ni debe volver a llenar el formulario cada año.

El usuario establece Helpdesk Carrousel como referencia principal de diseño, estructura de experiencia, manuales y tutoriales. También fija la vigencia en -15/+15 días respecto del cumpleaños. La cantidad de tokens de juego permanece deliberadamente sin definir; se representa como configuración pendiente, nunca como cero ni como una oferta inventada.

Confirmaciones de esta conversación:

- Canal: correo electrónico.
- Origen: clientes que se registrarán en esta nueva app.
- Audiencia: solo clientes, con adulto responsable cuando el beneficiario es menor.

## Referencia verificada

Repositorio: https://github.com/FernandoZL/helpdesk-carrousel.

Se comprobó mediante la conexión GitHub que la rama principal apunta a `95b01558ee7385b40ae031d08816a00998029b32`. La copia local `C:/xampp/htdocs/HelpdeskCarrousel` tiene ese mismo commit y no mostró cambios de trabajo al inspeccionarla. La revisión fue de código y documentación, sin ejecutar operaciones en su base de datos.

Fuentes de implementación que deben guiar la adaptación:

| Área | Fuente dentro del Helpdesk |
|---|---|
| Reglas de diseño y contenido | `docs/ESTANDAR_VISUAL_CARROUSEL.md` |
| Estructura, navegación y barra superior | `app/Views/shared/app_start.php` |
| Paleta, controles y superficies | `public/assets/css/app.css` |
| Menú colapsable y barra superior | `public/assets/css/shell-v2.css` |
| Geometría y jerarquía común | `public/assets/css/visual-system.css` |
| Componentes del manual | `public/assets/css/components.css` |
| Tabla adaptable | `public/assets/css/data-tables.css` |
| Modo oscuro | `public/assets/css/dark-refinement.css` |
| Ayuda contextual | `app/Views/shared/help_widget.php` |
| Manual por perfil, tareas, temas y búsqueda | `app/Views/help/manual.php` |
| Tutoriales guiados | `public/assets/js/help-tour.js` |

Se recomienda reutilizar y adaptar estos componentes sobre el dominio de cupones existente. Una copia completa del Helpdesk añadiría módulos de tickets, SLA y proveedores ajenos al programa; reconstruir otro diseño desde cero perdería la referencia que el usuario acaba de fijar.

## Experiencia visual

Aplicar el mismo azul principal `#213a8f`, lateral `#15255d`, franja multicolor, tipografía de sistema, superficies claras y tratamiento específico de modo oscuro del Helpdesk. La navegación operativa usa menú lateral colapsable, barra superior, perfil, buscador y acceso a ayuda. Las tablas se convierten en registros verticales en móvil siguiendo el componente del Helpdesk.

El formulario de inscripción usa el patrón compacto del formulario público del Helpdesk. La acción principal será **Registrarme**. La confirmación comunica **Registro completado** y explica cuándo llegará el regalo; no muestra un cupón recién generado. Se retira el enfoque de landing promocional de la primera demo.

Adaptar los nombres de los módulos al programa: Inicio, Clientes, Cumpleaños, Cupones, Canjear, Envíos, Campaña, Manual. Mostrar únicamente las opciones correspondientes al perfil y respaldar los permisos en el servidor.

La pantalla del regalo conserva código, QR, beneficiario, parque, cantidad de tokens cuando esté configurada, vigencia, condiciones y estado. El QR identifica el cupón; escanearlo no lo consume.

## Registro del cliente

El formulario guarda nombre, día y mes de cumpleaños, correo obligatorio, teléfono, parque y, para menores, nombre del adulto responsable. Para menores, el correo es el del responsable. Varios beneficiarios pueden compartir correo o teléfono.

Separar los términos del programa y el permiso para recibir el regalo anual de la casilla opcional de novedades comerciales. Conservar la versión y fecha del consentimiento. El correo del regalo no depende de aceptar publicidad adicional.

El registro crea una ficha persistente con identificador estable. No crea un cupón y no dispara el correo del regalo desde la respuesta del formulario. Un reintento idéntico no duplica clientes. Nombre, contacto y cumpleaños ayudan a detectar registros repetidos; la identidad anual de la emisión utiliza el identificador estable del cliente.

El administrador puede consultar, corregir y desactivar fichas con trazabilidad. No se crea un segundo regalo del mismo año al corregir datos. Desactivar un registro detiene futuras emisiones y pendientes de envío; los cupones ya emitidos mantienen su estado salvo anulación explícita.

## Vigencia y emisión

Regla confirmada: desde cumpleaños menos 15 días a las 00:00 hasta cumpleaños más 15 días al finalizar el día, en `America/Guatemala`, con ambos extremos incluidos. Son 31 fechas incluidas y pueden abarcar dos meses o dos años.

Ejemplo: cumpleaños el **20 de septiembre** → cupón válido del **5 de septiembre al 5 de octubre**. La fecha de cumpleaños, no el mes calendario, determina el intervalo.

Propuesta operativa para revisión: emitir y preparar el envío al comienzo de esa ventana, 15 días antes del cumpleaños. Si el cliente se registra cuando su ventana ya comenzó, el siguiente proceso automático lo incluye mientras siga vigente. No se prolonga la fecha de vencimiento por registrarse o recibir el correo tarde.

Ejecutar un proceso automático diario independiente de las visitas al sitio. El mismo proceso se puede ejecutar manualmente en la demo para validar fechas. No depender de que el administrador abra el panel.

Cada pasada revisa clientes activos que están dentro de la ventana y aún no tienen regalo de esa campaña y año de cumpleaños. Debe recuperar ejecuciones perdidas mientras la ventana siga abierta. Considerar cumpleaños del año previo o siguiente cuando la ventana cruza diciembre/enero. Para el 29 de febrero se conserva la regla documentada de usar el 28 en años no bisiestos.

La base impide dos regalos para el mismo cliente/campaña/año. Un cupón canjeado o anulado no habilita automáticamente otro. La transacción de emisión crea el cupón y su correo pendiente de forma conjunta. Reejecutar el proceso no crea nuevos códigos ni correos pendientes duplicados.

## Tokens de juego

La configuración tendrá un entero positivo para la cantidad del regalo. Mientras no esté definida, mostrar **Cantidad de tokens pendiente de definir**. La campaña permanece en preparación para emisiones reales. La demo puede probar fechas y generación con datos ficticios, identificando expresamente los ejemplos.

Al emitir se conserva una copia de cantidad, beneficio, condiciones y fechas en el cupón. Un cambio posterior afecta emisiones futuras. La aplicación registra la entrega del regalo; acreditar saldo automáticamente en tarjetas o máquinas requiere una integración independiente y no forma parte de esta revisión.

## Correo y automatización

Crear una bandeja persistente de envíos, separada del estado del cupón. Un registro puede estar emitido sin que todavía se haya enviado su correo. Diferenciar pendiente, procesando, aceptado por el servidor de correo, fallido, resultado incierto y cancelado. No describir la aceptación SMTP como confirmación de entrega al buzón.

El correo incluye saludo, regalo, vigencia exacta, parque, código y enlace seguro para ver el QR. Enviar el mismo cupón al reintentar; nunca generar otro para resolver un problema de correo. No enviar un regalo vencido, anulado o canjeado. Volver a comprobar estado y destinatario al procesar la cola.

Los errores conocidos antes de la aceptación del mensaje admiten reintentos limitados y quedan visibles en Envíos. Si la conexión termina con resultado incierto después de transferir el mensaje, registrar el caso para revisión en lugar de prometer entrega exactamente una vez. Mantener un identificador estable del mensaje y no ejecutar dos trabajadores sobre la misma fila simultáneamente.

En la prueba local, guardar una vista previa de correo sin enviarlo a destinatarios reales. La puesta en marcha real requiere configurar remitente y SMTP propios de esta app, URL accesible al cliente y ejecución programada en el servidor. No copiar secretos del Helpdesk. Estos datos operativos no son necesarios para construir y probar la adaptación local.

## Panel y permisos

- **Administrador:** clientes, cumpleaños próximos, cupones, configuración de campaña, envíos, reintentos, anulación con motivo, reportes y manual administrativo.
- **Operador:** consulta y canje dentro de su parque, más el manual correspondiente. No cambia la cantidad del regalo, el correo ni las reglas de emisión.
- **Cliente:** inscripción, confirmación, consulta del regalo mediante enlace opaco y ayuda pública. No accede a listados ni a datos de otros clientes.

Se mantienen las protecciones verificadas de canje único, CSRF, sesiones, validación de parque y salida pública sin datos de contacto. La emisión pasa a ser una operación interna del proceso automático; el endpoint público anterior no debe seguir permitiendo solicitar cupones.

## Manual y tutoriales dentro de la app

Reutilizar los patrones del Helpdesk: manual con perfil visible, búsqueda, filtros por tema, tarjetas de tareas frecuentes, FAQ desplegable y enlaces a funciones reales. No dejar estas funciones como enlaces vacíos ni sustituirlas únicamente por archivos externos.

La ayuda flotante **?** debe mostrar instrucciones de la pantalla actual y permitir iniciar el tutorial. El recorrido destaca controles existentes, permite avanzar, retroceder y cerrar, respeta teclado y foco, se adapta a móvil y omite elementos que el perfil no puede usar.

Contenido mínimo:

- Cliente: inscripción, correo anual, fechas, recepción del regalo y presentación del QR.
- Operador: consulta, validación de parque/fecha, confirmación de entrega, cupón usado o vencido.
- Administrador: clientes, cantidad pendiente de tokens, activación, proceso anual, revisión de envíos, incidencias, anulación y reportes.

## Arquitectura y conservación de lo existente

Mantener PHP y la base SQLite privada para la demo local. Añadir fichas de clientes, relación estable cliente-cupón, cantidad de tokens, bandeja de correo y registro de ejecuciones. Separar servicios de inscripción, emisión anual, correo y canje para que cada uno tenga pruebas propias.

La migración es aditiva y versionada. Conservar los cupones de demostración emitidos, sus fechas y condiciones; no reescribirlos silenciosamente a -15/+15. Identificarlos como registros del flujo anterior y excluirlos de envíos automáticos. Los nuevos clientes y regalos usan las reglas revisadas.

Las cuentas demo continúan disponibles durante las pruebas locales. Una instalación productiva necesita autenticación del personal coherente con el patrón de correo/OTP del Helpdesk, configuración propia y validación de despliegue. Compartir diseño no implica compartir sus sesiones, usuarios o base de datos.

## Criterios de aceptación

1. La interfaz comparte los componentes y patrones inspeccionados del Helpdesk en escritorio, tableta, móvil y modo oscuro.
2. El formulario registra al cliente sin devolver ni crear un cupón.
3. La ficha conserva el correo obligatorio, responsable cuando aplica y consentimiento del programa.
4. Dos beneficiarios con un mismo responsable pueden registrarse y recibir regalos distintos.
5. El proceso emite dentro de -15/+15 y omite clientes fuera de ventana; no necesita una visita al sitio.
6. Ejecutarlo dos veces, incluso simultáneamente, no duplica cupones ni filas de correo.
7. Se cubren cruces de mes/año, año bisiesto, alta dentro de ventana y ejecución recuperada después de una interrupción.
8. Con cantidad pendiente no se activa una promesa de regalo real ni se inventan tokens.
9. Un error de correo conserva el cupón y permite revisar el resultado sin una nueva emisión.
10. El canje comprueba vigencia inclusiva, parque, permisos y uso previo; dos cajas simultáneas solo logran una entrega.
11. Manual, filtros, ayuda contextual y tutoriales funcionan según perfil; sus enlaces llevan a funciones disponibles.
12. Las pruebas locales no envían correo a clientes reales, y los registros anteriores se conservan.

## Situación de entrega

Esta especificación sustituye el enfoque funcional inicial de solicitud de cupón. Los resultados de pruebas del 18 de septiembre acreditan la demo anterior, no esta adaptación. Tras revisar esta especificación se prepara el plan de implementación y sus pruebas.
