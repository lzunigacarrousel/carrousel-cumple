# Contexto técnico del Portal Carrousel

Inspección de solo lectura: 16 de septiembre de 2026. Fuente principal: `C:\xampp\htdocs\portal`. No se modificó el portal ni las aplicaciones existentes. No se leyeron configuraciones privadas, credenciales, respaldos ni datos de clientes; no se ejecutaron consultas de base de datos. No se encontró un `AGENTS.md` aplicable en los directorios inspeccionados.

## Hallazgo y encaje recomendado

El portal es un catálogo interno de accesos escrito en PHP, HTML, CSS y JavaScript sin framework visible. No es una aplicación contenedora con autenticación compartida. El patrón ya utilizado es alojar aplicaciones independientes en rutas hermanas y añadir una tarjeta de acceso en el portal.

Para el proyecto de cumpleañeros encaja una aplicación independiente, con formulario público y una zona operativa autenticada, integrada mediante una tarjeta del portal. Ruta propuesta, todavía no instalada: `/cumpleaneros/public/`. La tarjeta podría pertenecer a Operación; el formulario público tendría su enlace directo para clientes.

No se encontró un acceso de cumpleaños, cupones, promociones o Google Forms en el catálogo activo. Los dos enlaces de Google Apps Script corresponden a Supervisión Operativa y Auditoría Interna. Tampoco aparecieron referencias de cumpleaños en la copia de index ni en los manuales de portal archivados consultados.

## Evidencias del portal

| Tema | Evidencia |
|---|---|
| Propósito interno y catálogo | `C:\xampp\htdocs\portal\README.md:3`, `:7`, `:11` |
| Ruta productiva `/portal/` | `C:\xampp\htdocs\portal\README.md:39` |
| Catálogo PHP, categorías y aplicaciones | `C:\xampp\htdocs\portal\index.php:5` |
| Ejemplo de aplicación hermana | `C:\xampp\htdocs\portal\index.php:27` — Caja Chica enlaza `/caja_chica_carrousel/public/` |
| Ejemplo con login propio | `C:\xampp\htdocs\portal\index.php:53` — Destrucción Tickets; `:136` — Control IT |
| Apps Script existentes | `C:\xampp\htdocs\portal\index.php:76` — Supervisión; `:90` — Auditoría |
| Convención de extensión | `C:\xampp\htdocs\portal\index.php:1307` — renderiza `name`, `desc`, `url`, `icon`, `disabled`, `external` |
| Escape y apertura de enlaces | `C:\xampp\htdocs\portal\index.php:1325` — `htmlspecialchars`; `:1326` — `noopener noreferrer` para enlaces que abren otra pestaña |
| Zona horaria | `C:\xampp\htdocs\portal\index.php:248` — `America/Guatemala` |
| Anuncios opcionales | `C:\xampp\htdocs\portal\index.php:253` — lee HTML de `/portal/anuncios` |

En `index.php` no hay inicio de sesión, comprobación de roles ni conexión PDO/MySQL. No hay `composer.json`, `package.json` ni esquema SQL del portal entre los archivos públicos inspeccionados. Esto no prueba la ausencia de controles externos del servidor, pero no permite asumir un SSO disponible para reutilizar.

## Convenciones visuales

Se puede conservar la identidad del portal en el panel operativo y emplear una presentación más orientada al cliente para el formulario.

- Logo existente: `C:\xampp\htdocs\portal\assets\img\logo-carrousel.png`; referencia en `index.php:1261`.
- Paleta de marca en `index.php:748`: azul `#1746a2`, morado `#6543ad`, rosa `#ec2e7d`, naranja `#ff6a3d`, amarillo `#ffb800`.
- Fondo claro `#f3f5fb`, paneles blancos, texto `#10182a`, bordes `#dce2ec` (`index.php:754`).
- Degradado de marca (`index.php:782`), tarjetas redondeadas y navegación por categorías.
- Tipografía de sistema: `system-ui, -apple-system, Segoe UI, Roboto, Arial, sans-serif` (`index.php:319`).
- Tema claro/oscuro conservado en `localStorage` con clave `carrousel-theme` (`index.php:277`, `:1386`). Su continuidad solo es automática entre aplicaciones bajo el mismo origen.
- Diseño adaptable y movimiento reducido (`index.php:721`, `:730`, `:1240`).

## Referencias útiles en las aplicaciones ya existentes

Se inspeccionaron documentación y estructura de aplicaciones vecinas para comprobar convenciones; no se propone modificar sus bases ni reutilizar sus sesiones sin una integración explícita.

### Caja Chica

- Stack PHP/MariaDB: `C:\xampp\htdocs\caja_chica_carrousel\README.md:20`.
- Requisitos documentados PHP 8.0.30+, MariaDB 10.4+ o MySQL 8+: `README.md:26`.
- Estructura `app/Core`, `Controllers`, `Services`, `Views`, `public/`, `database/`, `storage/`: `README.md:285`.
- Roles Administrador, Gerencia, Supervisor y Parque: `README.md:52`. Permisos máximos por rol y alcance de supervisor por parques asignados: `:54`, `:83`, `:88`.
- Correo + OTP, límites de intentos, CSRF, sesiones revocables, PDO preparado y alcance validado en backend: `README.md:266`.
- Rutas de autenticación propias: `C:\xampp\htdocs\caja_chica_carrousel\routes.php:14` a `:19`.
- Sesión con nombre propio configurable: `C:\xampp\htdocs\caja_chica_carrousel\bootstrap.php:52`.
- Ejemplo de ruta con permiso: `routes.php:21`; administración de parques: `:99`, `:100`.
- Esquema, sin consultar registros: `C:\xampp\htdocs\caja_chica_carrousel\database\INSTALAR.sql:115` define `parks` con nombre único y estado activo; `:127` define `user_assignments`. Hay tablas de roles, usuarios, permisos, OTP, sesiones y auditoría. No se extrajeron sus datos.

### Control IT y PayOut

- Control IT documenta PHP + PDO, MariaDB y autenticación OTP: `C:\xampp\htdocs\controlit\README.md:7`, `:12`. Los roles y restricciones se verifican en frontend y API (`:28`, `:37`).
- PayOut es otra aplicación independiente enlazada desde el portal: `C:\xampp\htdocs\PayOutParques\README.md:3`, `:8`. Documenta alcance de supervisores por parques (`:89`, `:93`).

## Riesgos concretos de integración

1. **Autenticación:** añadir un enlace al portal no protege los canjes ni la administración. El nuevo módulo debe autenticar a operadores y validar permisos en servidor. No se encontró evidencia de SSO central.
2. **Catálogo de parques:** el portal no contiene una lista de sucursales. Hay catálogos propios en aplicaciones vecinas, pero no está demostrada una fuente maestra común. Antes de la carga real debe definirse un catálogo autorizado y el alcance de cada operador.
3. **Rutas y despliegue:** el portal depende de `/portal/` y sus aplicaciones de rutas hermanas. Las URL base de la aplicación nueva deben ser configurables para funcionar en localhost y en producción sin reemplazar archivos existentes.
4. **Fecha de vigencia:** usar `America/Guatemala`, ya establecido por el portal. El cambio de día y el canje deben calcularse en servidor con una regla uniforme.
5. **Separación entre público e interno:** el cliente necesita un enlace directo al formulario; no necesita navegar por el catálogo de sistemas internos. El enlace del portal debe llevar a la operación autenticada.
6. **Datos y canje:** la ausencia de una BD del portal implica que registro, deduplicación, folio/QR, estado del cupón y canje único necesitan persistencia propia. Un canje simultáneo debe resolverse de forma atómica en BD.

La revisión acredita el patrón técnico de integración y la identidad visual; no verifica disponibilidad del servidor productivo, permisos de despliegue, SMTP, SSO, lista vigente de parques ni condiciones comerciales del cupón.
