# Instalación real — Implementation Plan

**Goal:** Entregar la app en XAMPP con identidad, campañas, registro independiente y correo automático.
**Architecture:** PHP con servicios separados sobre MariaDB central y cola persistente; frontend conserva CSS y estructura de Helpdesk. Importador aislado de catálogos de Caja Chica. Roles y alcance inspirados en PayOut.
**Tech Stack:** PHP 8.2, PDO MySQL, MariaDB/InnoDB, PHPMailer, Apache, Windows Task Scheduler.
**Spec:** `docs/superpowers/specs/2026-09-21-instalacion-real-design.md`.

## Global Constraints

No modificar proyectos ni bases de origen. No copiar secretos a Git o devolverlos en salidas. Sin cuentas ni campañas demo. Centros del servidor, canje atómico global. Ventana fija −15/+15 inclusiva. Correo de cliente obligatorio. Registro sin emisión. Beneficios solo configurados por administración. Manual por perfil y ayuda funcional. Copia instalada en C:/xampp/htdocs/CarrouselCumple.

## Review Focus

Reclamación de correo después de interrupción, OTP inválido que debe persistir intentos, cuentas sin centro vigente, cambio de rol durante sesión y carreras de canje/emisión. Pruebas de integración cubren estas condiciones.

## Tareas

- [x] 1. Contratos, esquema MariaDB, importación selectiva de SQL y configuración privada. Archivos database/schema.sql, app/Database.php, app/ImportCatalog.php, bin/console.php, config/. Pruebas: importar dos veces sin duplicar; no cargar sesiones ni gastos; aplicación rechaza base no instalada.
- [x] 2. Identidad/roles/OTP y SMTP. Archivos app/Identity.php, app/Mailer.php, app/Web.php. Pruebas: código incorrecto consume intento, expira, no reutiliza, cuenta suspendida bloqueada, permisos/centros comprobados en servidor.
- [x] 3. Clientes, campañas, cupones y cola. Archivos app/Program.php, app/Delivery.php. Pruebas: alta cero cupones; emisión una por campaña/año, ventanas límites/año/bisiesto, reintento, cambio beneficio conserva snapshot, canje entre centros una sola vez concurrente, SMTP capturado.
- [x] 4. Frontend real y ayuda por perfil usando docs/api-real.md. Archivos public/app.js, public/help.js, public/cumple.css, public/index.php. Un implementador independiente trabaja este alcance mientras la sesión principal completa backend e instalación.
- [x] 5. API/integración, pruebas funcionales y revisión independiente final. public/api.php, app/bootstrap.php, tests/production.php, tests/http-production.ps1. Se corrigen hallazgos antes de instalar.
- [x] 6. Instalación XAMPP, importación real, tarea programada y manual. .htaccess, tools/install-xampp.ps1, tools/schedule.ps1, INSTALACION.md. Comprobar URL local y archivos privados denegados, estado de SMTP sin envíos reales no solicitados.

## Ejecución y decisiones

Se procede con la solicitud de implementación existente; no se añade otra autorización de diseño. Se conserva el checkout actual porque contiene todo el trabajo aún no confirmado en Git. No se borra la base SQLite anterior ni se importa su cupón de prueba al sistema real. Los módulos se validan en bases independientes antes de copiarlos a XAMPP. La interfaz se delega con contrato escrito; integración y revisión quedan en esta sesión. Ninguna credencial forma parte de los artefactos de revisión.


Resultado: instalación local verificada. Tarea automática Interactive operativa; modalidad SYSTEM pendiente de ejecutar script como administrador por denegación de Windows. Despliegue remoto del dominio/IP no realizado. Ver INSTALACION.md y docs/verificacion-instalacion-real.md.
