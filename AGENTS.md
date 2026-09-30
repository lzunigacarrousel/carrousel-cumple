# Convenciones de Carrousel Cumple

## Referencia principal del usuario

El usuario designó `https://github.com/FernandoZL/helpdesk-carrousel` como la base principal de sus aplicaciones: diseño, navegación, componentes, manuales, ayuda contextual y tutoriales. En este proyecto esa referencia prevalece sobre el estilo de la primera demo.

Copia local comprobada: `C:/xampp/htdocs/HelpdeskCarrousel`. Consultar su documentación y componentes en modo de solo lectura; no modificar ese proyecto ni copiar configuraciones privadas, bases de datos, sesiones o credenciales.

Aplicar su estándar visual y sus patrones reales de interacción. Mantener la lógica específica de cumpleaños. La documentación de origen está en `docs/ESTANDAR_VISUAL_CARROUSEL.md`, las vistas comunes en `app/Views/shared/`, el manual en `app/Views/help/manual.php` y los tutoriales en `public/assets/js/help-tour.js` del Helpdesk.

## Requisitos confirmados

- Beneficiarios: clientes de Carrousel. Un empleado autenticado registra a cada cliente dentro de esta nueva aplicación. No hay autorregistro público ni importación de listas en el alcance confirmado.
- El registro inscribe al cliente en el programa. No solicita ni genera un cupón como resultado inmediato del formulario.
- El regalo se emite automáticamente para los clientes registrados y se envía por correo electrónico.
- Vigencia objetivo confirmada el 30/09/2026: 15 días antes y 14 días después, ambos extremos incluidos (30 fechas calendario). Sustituye el acuerdo previo de −15/+15. El código todavía usa la regla anterior; la adaptación está encargada al practicante, ver `docs/encargo-practicante.md`.
- Administración configura el tipo, cantidad, descripción y condiciones del beneficio por campaña. Tokens requieren cantidad entera positiva; no inventar la oferta comercial ni confundir la cantidad con el identificador del cupón.
- El empleado consulta el cupón presentado y valida vigencia y uso previo en toda la red antes de confirmar el canje. Un uso en cualquier parque bloquea todos los siguientes.
- Separar parque de registro y parque de canje. El parque de origen no limita el canje del regalo nuevo: el empleado opera en su parque autorizado y consulta el estado global del código.
- Cada parque tiene su cuenta. El servidor toma de la cuenta activa el parque de registro y el de canje; no se eligen en formularios. Administración global supervisa y configura; las cuentas de parque registran clientes y entregan regalos.
- La operación real requiere un registro central compartido por los parques. Bases independientes por parque no satisfacen el control global; sin validación central no se autoriza canje.
- Conservar canje atómico, permisos y trazabilidad de empleado, parque y fecha/hora tanto del registro como del canje.
- Manuales y tutoriales deben describir funciones existentes y adaptarse al perfil; no basta con documentos Markdown externos.

## Autorización y estado al 22 de septiembre de 2026

El usuario solicitó después reutilizar el SMTP de las aplicaciones compartidas e importar usuarios/correos/centros desde su SQL. Esa autorización explícita se aplicó a la configuración privada de Cumple, sin imprimir secretos ni modificar las aplicaciones de origen. PayOutParques también es referencia principal para roles y centros; Helpdesk sigue siendo la base visual y de OTP.

La aplicación actual usa PHP/MariaDB central, acceso OTP real, roles y centros, registro independiente, campañas múltiples configurables, emisión anual, cola SMTP, canje global atómico y auditoría. Instalada en C:/xampp/htdocs/CarrouselCumple, entrada http://localhost/CarrouselCumple/. Base carrousel_cumple con 64 cuentas y 296 centros importados; sin clientes, campañas ni cupones ficticios. Configuración privada local.php, nunca versionarla. Los catálogos son una copia inicial, no sincronización permanente.

SMTP: conexión TLS y autenticación comprobadas sin enviar correo real. Tarea CarrouselCumple-Campanas cada 5 minutos instalada para usuario Interactive; ejecución comprobada con salida 0. Crear como SYSTEM fue denegado por Windows: para operar sin sesión abierta ejecutar tools/schedule.ps1 como administrador, según INSTALACION.md. El dominio/IP remotos están configurados pero no se ha desplegado en el servidor remoto. No afirmar publicación pública completada.

Pruebas actuales: production.php (29), production-edges.php (25), production-races.php (3), review-regressions.php (24), http-production.ps1 (32), catalog.php (importación selectiva idempotente). Usan únicamente carrousel_cumple_test y transporte capturado. production.php reinicia esa base aislada. La SQLite, código y pruebas antiguas se conservan como antecedentes, no se instalan ni son la fuente actual.

Manuales y tutoriales dentro de la app, por permiso. Registro/canje usan el centro de la cuenta sin selector. Las sesiones y OTP pendientes se revocan al editar cuenta/rol. Los cupones conservan el beneficio emitido; pausar campañas impide nuevas emisiones, no invalida cupones anteriores. Consultas de lista limitadas a 500 con aviso; estadísticas calculadas sobre todo el alcance. Los resultados SMTP inciertos exigen revisión antes del reintento.

La carpeta del workspace es la fuente de desarrollo; tools/install-xampp.ps1 actualiza la instalación Apache sin sustituir la configuración privada del destino. No editar solo la copia instalada sin reflejar el cambio aquí. Documentación vigente: INSTALACION.md, docs/api-real.md y docs/superpowers/specs/2026-09-21-instalacion-real-design.md.

## Revisión visual al 25 de septiembre de 2026

La interfaz usa 13 hojas originales del Helpdesk, incluida `layout-density-v25.css` al final de la cascada antes de `data-tables.css`. Acceso OTP, menú por secciones, cabecera con buscador/Portal, tarjetas, tablas, formularios compactos y manual por perfil siguen sus componentes reales. El manual incluye tareas, índice y FAQ desplegables. Las cuentas sin centro operativo no tienen accesos de registro/canje en menú, inicio ni tareas del manual.

Los formularios administrativos contienen un control `name="id"`, que oculta la propiedad nativa `form.id` en el navegador: el enrutamiento de submit debe leer `form.getAttribute("id")`. Conservar esta corrección al modificar formularios. Ver `docs/referencia-visual-helpdesk.md` y `docs/verificacion-visual-helpdesk.md`.

Verificación de instalación del 25 de septiembre: base real con 64 cuentas, 296 centros, 2 clientes existentes, 0 campañas y 0 cupones. Las pruebas visuales modificaron únicamente la base aislada. Entrada Apache/hoja de densidad HTTP 200 y configuración privada HTTP 403.

## Encargo al practicante — 30 de septiembre de 2026

El usuario quiere que el practicante implemente el proceso recibido. Se preparó `docs/encargo-practicante.md` con brechas, entregas y criterios de aceptación; los originales están en `docs/referencias/`. No confundir requisitos nuevos con funciones ya terminadas: faltan fecha de nacimiento completa/edad, correspondencia SAP verificada, factura obligatoria, presentación de imagen y ajuste de ventana. Conservar beneficios configurables y no inventar edades elegibles ni códigos SAP. Resolver con el responsable el intervalo entre validación y facturación externa antes de operar. Preparar el encargo no autoriza a modificar producción ni a enviar correos.
