# Carrousel Cumple: regalo automático y base Helpdesk

Estado al 21 de septiembre de 2026: base visual del Helpdesk aplicada y manual/tutoriales integrados. La demo ya aplica −15/+15 y un único canje en cualquier parque contra la base compartida. Cada parque tiene su cuenta demo; el servidor asigna automáticamente el parque y conserva cuenta y fecha/hora tanto del registro como del canje. El registro independiente, emisión anual, correo, autenticación real y despliegue central de producción siguen pendientes. Ver `docs/referencia-visual-helpdesk.md`.

## Intención y decisiones del usuario

Reconocer el cumpleaños de los clientes de Carrousel mediante un regalo automático. Un empleado registra al cliente una vez en esta nueva aplicación y el cliente recibe su cupón por correo electrónico cuando corresponde. El cliente no solicita el cupón ni llena un formulario de autorregistro. Cuando presenta su regalo en un parque, el empleado consulta su vigencia y el historial global de uso antes de confirmar el canje.

El usuario establece Helpdesk Carrousel como referencia principal de diseño, estructura de experiencia, manuales y tutoriales. También fija la vigencia en -15/+15 días respecto del cumpleaños. La cantidad de tokens de juego permanece deliberadamente sin definir; se representa como configuración pendiente, nunca como cero ni como una oferta inventada.

Confirmaciones de esta conversación:

- Canal: correo electrónico.
- Origen: clientes registrados por empleados autenticados en esta nueva app.
- Audiencia: solo clientes, con adulto responsable cuando el beneficiario es menor.
- Operación: el empleado registra clientes y canjea regalos; el cliente recibe y presenta su cupón.
- Control de uso: un único canje en toda la red, comprobando si se utilizó en cualquier otro parque.
- Cuentas por parque: el formulario no pide escoger ubicación. El parque de registro y el de canje se toman de la cuenta activa, con la política de parque fijo del Helpdesk. Administración global supervisa y configura; la cuenta del parque realiza el registro y la entrega.

## Referencia verificada

Repositorio: https://github.com/FernandoZL/helpdesk-carrousel.

En la inspección inicial del 18 de septiembre se comprobó mediante la conexión GitHub el commit `95b01558ee7385b40ae031d08816a00998029b32`, coincidente con la copia local. En la revisión posterior de estilos y cuentas, la copia local `C:/xampp/htdocs/HelpdeskCarrousel` había avanzado a `e0cc84d9332bb0ac4e42708f2ffeefe730422637`. Las revisiones fueron de código y documentación, sin ejecutar operaciones en su base de datos.

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
| Parque fijo por cuenta y validación en servidor | `app/Services/RequesterLocationPolicyService.php` |
| Cuenta activa y sesión | `app/Core/Auth.php` |
| Asignación administrativa de usuarios | `app/Controllers/AdminController.php` |

Se recomienda reutilizar y adaptar estos componentes sobre el dominio de cupones existente. Una copia completa del Helpdesk añadiría módulos de tickets, SLA y proveedores ajenos al programa; reconstruir otro diseño desde cero perdería la referencia que el usuario acaba de fijar.

## Experiencia visual

Aplicar el mismo azul principal `#213a8f`, lateral `#15255d`, franja multicolor, tipografía de sistema, superficies claras y tratamiento específico de modo oscuro del Helpdesk. La navegación operativa usa menú lateral colapsable, barra superior, perfil, buscador y acceso a ayuda. Las tablas se convierten en registros verticales en móvil siguiendo el componente del Helpdesk.

El formulario de alta usa el patrón compacto de los formularios internos del Helpdesk y requiere sesión del empleado. La acción principal será **Registrar cliente**. La confirmación comunica **Cliente registrado** y explica que el regalo llegará automáticamente a su correo cuando corresponda; no muestra un cupón recién generado. El acceso principal de operación lleva al inicio de sesión, no a una landing de autorregistro.

Adaptar los nombres de los módulos al programa: Inicio, Clientes, Cumpleaños, Cupones, Canjear, Envíos, Campaña, Manual. Mostrar únicamente las opciones correspondientes al perfil y respaldar los permisos en el servidor.

La pantalla del regalo conserva código, QR, beneficiario, cantidad de tokens cuando esté configurada, vigencia, condiciones y estado. El parque donde se registró al cliente es un dato de trazabilidad, no una restricción de canje del regalo nuevo. El QR identifica el cupón; escanearlo no lo consume.

## Registro del cliente

El empleado autenticado captura nombre, día y mes de cumpleaños, correo obligatorio, teléfono y, para menores, nombre del adulto responsable. Para menores, el correo es el del responsable. Varios beneficiarios pueden compartir correo o teléfono. El formulario muestra el parque de la cuenta como información, sin un selector. El servidor vuelve a consultar la cuenta activa y registra su identificador, el parque asignado y la fecha/hora; no acepta que un operador suplante otro parque modificando el formulario. Una cuenta compartida por parque identifica la cuenta operativa, no a una persona física individual.

El empleado informa las condiciones al cliente o responsable y registra su aceptación. Separar los términos del programa y el permiso para recibir el regalo anual de la casilla opcional de novedades comerciales. Conservar la versión, fecha y empleado que registró el consentimiento. El correo del regalo no depende de aceptar publicidad adicional.

El registro crea una ficha persistente con identificador estable. No crea un cupón y no dispara el correo del regalo desde la respuesta del formulario. Un reintento idéntico no duplica clientes. Nombre, contacto y cumpleaños ayudan a detectar registros repetidos en toda la red; cambiar el parque de registro no crea una identidad distinta. La identidad anual de la emisión utiliza el identificador estable del cliente.

El administrador puede consultar, corregir y desactivar fichas con trazabilidad. No se crea un segundo regalo del mismo año al corregir datos. Desactivar un registro detiene futuras emisiones y pendientes de envío; los cupones ya emitidos mantienen su estado salvo anulación explícita.

## Canje por el empleado y control entre parques

1. El cliente presenta el cupón recibido por correo, con su QR o código.
2. El empleado inicia sesión en su parque y consulta el código en el registro central.
3. El servidor verifica que el cupón exista, esté vigente, no esté anulado y no haya sido canjeado en ningún parque. Consultar solo muestra el resultado; no consume el beneficio.
4. Si ya fue usado, la pantalla informa **Cupón ya canjeado**, el parque y la fecha/hora del canje. Si no está vigente o está anulado, muestra ese estado y bloquea la acción.
5. Si está disponible, el empleado confirma el canje. El servidor vuelve a validar el estado y las fechas dentro de la transacción, toma el parque de la cuenta activa, registra cuenta/parque/fecha/hora y devuelve la confirmación. No se selecciona un parque al canjear. La cuenta global de administración solo consulta el estado; no entrega regalos en nombre de un parque.
6. La entrega del regalo se realiza después de recibir la confirmación del servidor. Desde ese momento el cupón queda usado para todos los parques.

El empleado puede validar un código emitido a un cliente registrado en otro parque. Su autorización determina **dónde registra el canje**, no el parque de origen de los códigos que puede consultar. Esa consulta global por código devuelve los datos mínimos del regalo y su uso; no concede acceso a listados completos ni a contactos de clientes de otros parques.

Dos parques que intenten confirmar el mismo cupón simultáneamente deben producir exactamente un canje exitoso y un rechazo. La regla se aplica en una base central compartida, con actualización atómica, no únicamente ocultando un botón. En producción todos los parques deben consultar el mismo servicio; sin conexión para validar y confirmar contra ese servicio no se autoriza el canje. No se contempla canje offline.

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

El correo incluye saludo, regalo, vigencia exacta, condiciones de canje, código y enlace seguro para ver el QR. No restringe el canje al parque de registro. Enviar el mismo cupón al reintentar; nunca generar otro para resolver un problema de correo. No enviar un regalo vencido, anulado o canjeado. Volver a comprobar estado y destinatario al procesar la cola.

Los errores conocidos antes de la aceptación del mensaje admiten reintentos limitados y quedan visibles en Envíos. Si la conexión termina con resultado incierto después de transferir el mensaje, registrar el caso para revisión en lugar de prometer entrega exactamente una vez. Mantener un identificador estable del mensaje y no ejecutar dos trabajadores sobre la misma fila simultáneamente.

En la prueba local, guardar una vista previa de correo sin enviarlo a destinatarios reales. La puesta en marcha real requiere configurar remitente y SMTP propios de esta app, URL accesible al cliente y ejecución programada en el servidor. No copiar secretos del Helpdesk. Estos datos operativos no son necesarios para construir y probar la adaptación local.

## Panel y permisos

- **Administrador:** clientes, cumpleaños próximos, cupones, configuración de campaña, envíos, reintentos, anulación con motivo, reportes y manual administrativo.
- **Empleado/operador:** registro de clientes en su parque; consulta global de un cupón por código y registro del canje en su parque autorizado, más el manual correspondiente. Puede informar de un canje previo en otro parque sin acceder a sus listados de clientes. No cambia la cantidad del regalo, la configuración de correo ni las reglas de emisión.
- **Cliente:** recepción por correo, consulta del regalo mediante enlace opaco, presentación del cupón y ayuda pública. No tiene formulario de autorregistro, no ejecuta el canje y no accede a listados ni a datos de otros clientes.

Se mantienen canje único, CSRF, sesiones y salida pública sin datos de contacto. La validación de parque comprueba que el empleado esté autorizado en el parque donde canjea, reemplazando para los regalos nuevos la restricción de origen de la demo anterior. La emisión pasa a ser una operación interna del proceso automático. El alta de clientes exige sesión y permiso del empleado; el endpoint público anterior no debe permitir registrar personas ni solicitar cupones.

## Manual y tutoriales dentro de la app

Reutilizar los patrones del Helpdesk: manual con perfil visible, búsqueda, filtros por tema, tarjetas de tareas frecuentes, FAQ desplegable y enlaces a funciones reales. No dejar estas funciones como enlaces vacíos ni sustituirlas únicamente por archivos externos.

La ayuda flotante **?** debe mostrar instrucciones de la pantalla actual y permitir iniciar el tutorial. El recorrido destaca controles existentes, permite avanzar, retroceder y cerrar, respeta teclado y foco, se adapta a móvil y omite elementos que el perfil no puede usar.

Contenido mínimo:

- Cliente: correo anual, fechas, recepción del regalo, presentación del QR y solicitud de corrección de datos al personal.
- Empleado/operador: registro del cliente y correo, aceptación del programa, consulta global del cupón, validación de vigencia/uso, confirmación de canje y entrega, cupón usado en otro parque, vencido o anulado.
- Administrador: clientes, cantidad pendiente de tokens, activación, proceso anual, revisión de envíos, incidencias, anulación y reportes.

## Arquitectura y conservación de lo existente

Mantener PHP y una sola base SQLite privada para la demo local. Simular varios parques contra esa misma base para verificar el control global. La instalación real debe usar un servicio central compartido por todos los parques; copiar bases locales independientes a cada parque no cumple el requisito. Añadir fichas de clientes, relación estable cliente-cupón, cantidad de tokens, bandeja de correo, registro de ejecuciones y trazabilidad de empleado/parque de registro y canje. Separar servicios de alta por personal, emisión anual, correo y canje para que cada uno tenga pruebas propias.

La migración es aditiva y versionada. Tras la corrección explícita del usuario del 20 de septiembre, la demo adapta las ventanas existentes a −15/+15 y elimina la restricción al origen; conserva códigos, beneficio, condiciones personalizadas y empleado/parque/fecha de los canjes ya registrados. Solo sustituye el texto genérico antiguo que limitaba al parque de registro. Los cupones siguen siendo de demostración y deberán excluirse de futuros envíos automáticos.

Las cuentas demo de administración, Aurora, Estrella y Cometa están disponibles durante las pruebas locales. La migración aditiva conserva los identificadores existentes y agrega estado activo, clasificación demo y trazabilidad de registro. `app/Accounts.php` adapta la política de parque fijo del Helpdesk; la asignación y los permisos se verifican en el servidor. Una instalación productiva necesita autenticación del personal coherente con el patrón de correo/OTP del Helpdesk, configuración propia y validación de despliegue. Compartir diseño no implica compartir sus sesiones, usuarios o base de datos.

## Criterios de aceptación

1. La interfaz comparte los componentes y patrones inspeccionados del Helpdesk en escritorio, tableta, móvil y modo oscuro.
2. Solo un empleado autorizado puede registrar clientes; el formulario no devuelve ni crea un cupón y los intentos públicos se rechazan.
3. La ficha conserva correo obligatorio, responsable cuando aplica, consentimiento y empleado/parque/fecha del alta.
4. Dos beneficiarios con un mismo responsable pueden registrarse y recibir regalos distintos.
5. El proceso emite dentro de -15/+15 y omite clientes fuera de ventana; no necesita una visita al sitio.
6. Ejecutarlo dos veces, incluso simultáneamente, no duplica cupones ni filas de correo.
7. Se cubren cruces de mes/año, año bisiesto, alta dentro de ventana y ejecución recuperada después de una interrupción.
8. Con cantidad pendiente no se activa una promesa de regalo real ni se inventan tokens.
9. Un error de correo conserva el cupón y permite revisar el resultado sin una nueva emisión.
10. El canje comprueba vigencia inclusiva, permiso del empleado en el parque de atención y uso previo global; dos cajas de parques distintos que confirman simultáneamente solo logran un canje. Se conserva empleado/parque/fecha/hora del ganador.
11. Manual, filtros, ayuda contextual y tutoriales funcionan según perfil; sus enlaces llevan a funciones disponibles.
12. Las pruebas locales no envían correo a clientes reales, y los registros anteriores se conservan.
13. Un regalo nuevo de cliente registrado en el parque A puede consultarse y canjearse por personal autorizado del parque B si sigue disponible; después no puede volver a usarse en A, B ni otro parque.
14. Conocer un código de otro parque no amplía permisos sobre listados o contactos. El parque de canje se determina en el servidor y no puede suplantarse desde la petición.
15. No se entrega una confirmación local de canje cuando falla la consulta o confirmación central. Un reintento verifica el resultado previo y no vuelve a entregar el beneficio.

## Situación de entrega

Esta especificación sustituye el enfoque funcional inicial de solicitud de cupón. Los resultados de pruebas del 18 de septiembre acreditan la demo anterior. Las pruebas del 21 de septiembre cubren las cuentas por parque, trazabilidad, canje global y ventana −15/+15. El registro independiente, emisión anual y correo requieren implementación y pruebas propias antes de considerar completo el flujo acordado.
