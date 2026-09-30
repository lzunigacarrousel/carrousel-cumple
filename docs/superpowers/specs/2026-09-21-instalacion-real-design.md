# Carrousel Cumple: instalación y operación real

Solicitud autorizada el 21 de septiembre: sustituir la demo por una app instalable en `C:/xampp/htdocs/CarrouselCumple`, con base MariaDB propia, OTP del personal, campañas de regalos configurables, centros de costo y roles tomando Helpdesk y PayOutParques como fuentes principales. El usuario autoriza reutilizar SMTP y los catálogos del SQL de Caja Chica; esta instrucción actualiza la restricción anterior sobre esas configuraciones. No se modifican las aplicaciones de origen.

## Flujo

- El personal entra por correo/OTP. Se importan identidades, estado, rol y asignación vigente del SQL; nunca sesiones, OTP antiguos, facturas ni gastos.
- Una cuenta operativa tiene un centro fijo, validado en cada petición. Los supervisores consultan sus centros asignados; administración gestiona usuarios, roles, centros, campañas y envíos.
- El registro crea únicamente la ficha del cliente con correo obligatorio, cumpleaños, responsable de menor, aceptación, cuenta, centro y fecha. No emite ni envía un cupón desde el formulario.
- Administración configura varias campañas: nombre, tipo de regalo, cantidad opcional según tipo, descripción, condiciones, asunto e introducción del correo, período y estado borrador/activo/pausado. No se inventa un beneficio. Cada cupón conserva una copia del beneficio emitido.
- Un proceso CLI programado cada cinco minutos detecta cumpleaños en la ventana fija −15/+15 inclusiva, crea un regalo por cliente/campaña/año y añade correo pendiente en la misma transacción. Recupera ejecuciones perdidas mientras la ventana siga vigente. Febrero 29 usa febrero 28 en año no bisiesto.
- La cola usa bloqueo y reclamación exclusiva, reintenta fallos previos al envío y distingue aceptación SMTP de entrega al buzón. Resultado incierto requiere revisión. Reintentar conserva el mismo cupón.
- El cliente recibe correo con beneficio, fechas, código y enlace al QR. URL canónica: `https://portal.carrousel-apps.com/CarrouselCumple/`. Acceso también por localhost y 94.74.71.96 con la misma ruta; esto no implica publicar automáticamente al servidor remoto.
- Canje único en toda la red en InnoDB, con cuenta/centro/fecha del uso. Consulta por código global con contacto privado; sin conexión central no hay confirmación.

## Seguridad y operación

PHP 8.2 / PDO MySQL, PHPMailer del proveedor utilizado en Helpdesk, archivos privados bloqueados por Apache. Configuración SMTP privada y excluida de Git, ningún secreto en HTML, logs o documentación. OTP de 6 dígitos, 10 minutos, 5 intentos, un solo uso, limitación por correo e IP y cookie de sesión propia. Roles con permisos explícitos y alcance de centros; administración estructural protegida y último administrador preservado. Bitácora para cambios y operaciones.

Base `carrousel_cumple`, instalada sin alterar Caja Chica, Helpdesk o PayOut. Importación selectiva e idempotente de los catálogos, reporte de cuentas sin correo/centro válido. Catálogos ambiguos quedan sin operación hasta que administración asigne su centro. Instalación limpia sin campañas, clientes ni cuentas de demostración.

Validación con MariaDB en una base de pruebas independiente y SMTP capturado localmente; no se envían regalos ni códigos de prueba a correos reales. Verificación de XAMPP, bloqueo HTTP de privados, interfaz, concurrencia, OTP, permisos, registro independiente, emisión anual y cola.
