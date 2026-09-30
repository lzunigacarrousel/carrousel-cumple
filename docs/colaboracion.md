# Trabajo colaborativo

Este repositorio contiene la fuente de Carrousel Cumple. Cada persona trabaja con su propia configuración local; el código compartido nunca sustituye las credenciales de otra instalación. La copia de XAMPP se actualiza desde la fuente con `tools/install-xampp.ps1`.

## Mapa del proyecto

| Ruta | Responsabilidad |
| --- | --- |
| `app/Program.php` | Registro, campañas, emisión, consulta y canje. |
| `app/Identity.php` | OTP, cuentas, permisos, roles y centros. |
| `app/Delivery.php`, `app/Mailer.php`, `app/TrackedSMTP.php` | Cola y entrega SMTP. |
| `app/Database.php`, `database/schema.sql` | Conexión, esquema y auditoría. |
| `app/ImportCatalog.php` | Importación selectiva de catálogos autorizados. |
| `public/` | Interfaz, API, ayuda y recursos visuales. |
| `bin/` | Comandos de instalación, diagnóstico y proceso automático. |
| `tools/` | Scripts de desarrollo e instalación. |
| `tests/` | Pruebas con base aislada y transporte de correo capturado. |
| `config/*.example` | Plantillas sin secretos para configurar cada entorno. |
| `docs/`, `INSTALACION.md` | Contratos, decisiones, instalación y evidencia fechada. |

El manual y los tutoriales que usa el personal también viven dentro de la aplicación. Cambiar un procedimiento requiere actualizar esa ayuda, no solamente la documentación de este repositorio.

## Reglas que una revisión debe preservar

- Registrar al cliente no emite automáticamente un cupón dentro de la misma petición. El proceso de campañas se ejecuta después.
- La ventana es −15/+15 días inclusive; puede cruzar meses y años. No equivale a todos los cumpleaños de un mes calendario.
- Una campaña activa puede emitir una vez por cliente y año de cumpleaños. Repetir el proceso no crea otro regalo de la misma campaña y año.
- El centro de registro y el de canje proceden de la cuenta autenticada. No se seleccionan libremente en los formularios.
- La consulta del código y el bloqueo del segundo canje son globales. Un fallo de conexión central impide autorizar el canje.
- Los cupones conservan su beneficio y condiciones emitidos. Editar o pausar una campaña no reescribe los regalos anteriores.
- Cambiar cuentas o roles revoca sesiones y OTP pendientes. El servidor verifica permisos en cada operación.
- Helpdesk es la base de diseño y ayuda; PayOut y Caja Chica son referencias de operación. No se comparten sus sesiones ni configuraciones privadas mediante el repositorio.

## Organizar una tarea

Describe un resultado comprobable y sus criterios de aceptación. Antes de empezar, indica qué archivos se modificarán y qué otra tarea podría afectarlos. Usa una rama `codex/` por objetivo y un PR hacia `main`; los comandos están en [CONTRIBUTING.md](../CONTRIBUTING.md).

En un cambio de fechas, por ejemplo, los criterios deberían decir qué ocurre al registrar un cliente el día −15, el día +15 y el día +16, y cómo se evita una segunda emisión. En un formulario, deberían cubrir seleccionar, guardar, volver a abrir y modificar los datos, con los permisos de cada perfil.

Para revisar, proporciona un caso reproducible con datos sintéticos, los resultados de las pruebas y las limitaciones conocidas. El historial de Git explica los cambios; las credenciales, bases y respaldos se gestionan por canales privados separados.

## Comprobaciones antes de operar

Integrar un PR o subir código a GitHub no despliega en el portal. La publicación necesita una actualización del servidor central, su configuración privada, revisión de acceso HTTPS y comprobación del proceso programado. Consulta [INSTALACION.md](../INSTALACION.md).

La tarea de Windows y `automation_enabled` son controles distintos: ambos deben permitir la ejecución para que el envío automático funcione. Los clientes no deben depender de que alguien tenga la página abierta. La cuenta Interactive requiere sesión iniciada; para operar sin sesión se necesita el modo de servicio indicado en instalación.

## Pendientes de seguimiento

| Tema | Resultado pendiente |
| --- | --- |
| Fechas y altas recientes | Completar la revisión solicitada de altas realizadas durante el mes actual: dentro/fuera de la ventana, siguiente ejecución y año siguiente. Las pruebas existentes aportan cobertura, pero no sustituyen esta revisión del flujo completo. |
| Facilidad de los formularios | Revisar con el usuario el llenado, selección, guardado y modificación de clientes y campañas, siguiendo Helpdesk, Caja Chica y PayOut. |
| Proceso automático | La tarea de Windows se observó deshabilitada el 25/09/2026 y volvió a confirmarse deshabilitada el 30/09/2026. Preparar y comprobar la operación continua; subir a Git no activa los envíos. |
| Publicación remota | Desplegar y comprobar el servidor del portal; la instalación local y los hosts configurados no acreditan una publicación pública. |
| Beneficio comercial | Administración debe definir y activar la oferta real de cada campaña. El repositorio no fija una cantidad de tokens. |

Actualiza cada pendiente con evidencia fechada cuando se resuelva. No conviertas una prueba con transporte capturado en una afirmación de entrega a un correo real.
