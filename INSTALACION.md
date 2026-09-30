# Instalación y operación de Carrousel Cumple

## Direcciones y base central

| Entorno | Dirección |
| --- | --- |
| Apache local | http://localhost/CarrouselCumple/ |
| Servidor por IP | http://94.74.71.96/CarrouselCumple/ |
| URL pública de los correos | https://portal.carrousel-apps.com/CarrouselCumple/ |

La carpeta local es `C:\xampp\htdocs\CarrouselCumple`. Los hosts están contemplados en la configuración, pero los accesos remotos requieren instalar la app en el servidor que atiende ese dominio/IP y publicar su ruta en Apache o el proxy. Instalar en esta computadora no hace ese despliegue remoto.

Todos los parques deben abrir la misma instalación central del portal. No instalar una base independiente en cada parque. Usar HTTPS para la operación pública y redirigir la IP al dominio cuando esté publicado. Los correos usan siempre `app_url` HTTPS, nunca localhost.

## Requisitos

PHP 8.2 con PDO MySQL, mbstring y OpenSSL; MariaDB 10.4 o superior con InnoDB; Apache con mod_rewrite y AllowOverride All para esta carpeta. Git y Composer para obtener el código y sus dependencias. PHPMailer 6.12.0 queda fijado en `composer.lock`; ejecutar `composer install --no-dev` para instalarlo con su licencia. `vendor` no se sube a Git.

Encender Apache y MySQL en XAMPP. El proceso automático necesita PHP, MySQL y acceso al SMTP aunque nadie navegue. `storage` requiere escritura para sesiones/logs; la cuenta del servicio debe poder leer la configuración privada. No habilitar listado de directorios.

## Instalación local preparada

Base propia: `carrousel_cumple`; usuario técnico exclusivo, con contraseña privada en `config/local.php`. Esa contraseña no se usa para ingresar a la web. Se importaron 64 cuentas y 296 centros con código de costo del SQL suministrado. No se importaron clientes ni campañas de prueba.

Algunas cuentas de administración/supervisión no tienen un centro operativo único. Revisar en **Usuarios** especialmente las cuentas de parque sin centro: no podrán registrar/canjear hasta asignarles un centro activo. El importador no adivina asignaciones. Estos catálogos son propios de Cumple; no existe sincronización automática posterior con PayOut.

Para actualizar desde el workspace, ejecutar `tools\install-xampp.ps1`. Solo acepta una subcarpeta de htdocs y no sobreescribe carpetas ajenas sin el marcador de instalación. Conserva la configuración privada del destino. No copia la SQLite ni datos de pruebas.

## Instalar desde cero en otro servidor

1. Clonar `https://github.com/FernandoZL/carrousel-cumple.git` y ejecutar `composer install --no-dev` en esa carpeta. Para instalar en XAMPP desde otra carpeta, completar primero la configuración del paso 3 y ejecutar `tools/install-xampp.ps1`. El instalador copia el código y dependencias, crea `storage/sessions` y conserva la configuración privada existente; no crea la base ni activa correos. En otro servidor, conservar `.htaccess`, `index.php`, `app`, `public`, `database`, `bin`, `tools`, `vendor` y la configuración privada propia; crear `storage/sessions` con permisos de escritura. No desplegar tests ni datos de pruebas.
2. Crear la base y una cuenta exclusiva en phpMyAdmin o MariaDB. Sustituir el texto de contraseña por una clave nueva y privada:

```sql
CREATE DATABASE carrousel_cumple CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'carrousel_cumple'@'localhost' IDENTIFIED BY 'REEMPLAZAR_POR_CLAVE_PRIVADA';
GRANT SELECT, INSERT, UPDATE, DELETE, CREATE, ALTER, INDEX, REFERENCES
ON carrousel_cumple.* TO 'carrousel_cumple'@'localhost';
```

3. Copiar `config/local.php.example` a `config/local.php`. Definir db_host, db_port, db_name, db_user y db_pass. Generar app_key con `php -r "echo bin2hex(random_bytes(32));"` y guardarla solo en la configuración privada. Para pruebas locales usar `app_url` local; antes de emitir regalos reales configurar la dirección pública HTTPS. Zona configurada: America/Guatemala (UTC−6). El ejemplo mantiene `automation_enabled=false` hasta terminar la configuración.
4. Instalar tablas y crear la primera cuenta administrativa con un correo propio autorizado:

```powershell
Set-Location 'C:\xampp\htdocs\CarrouselCumple'
& 'C:\xampp\php\php.exe' bin\console.php migrate
& 'C:\xampp\php\php.exe' bin\create-admin.php --name="Nombre de administración" --email="correo@empresa.com"
& 'C:\xampp\php\php.exe' bin\console.php health
```

`create-admin.php` solo funciona si no existe ningún usuario, no envía correo y no asigna un centro inventado. Configurar SMTP antes de ingresar por OTP; después crear centros, roles y cuentas desde la aplicación.

Como alternativa al administrador inicial, si tienes un catálogo autorizado puedes ejecutar `php bin/console.php import-catalog RUTA_SQL` después de `migrate`. El importador lee roles, usuarios, parques con código y asignaciones vigentes. No ejecuta el SQL completo ni modifica Caja Chica. Repetirlo no duplica registros existentes ni sobreescribe ajustes realizados en Cumple. Si ya importaste usuarios no ejecutes `create-admin.php`. Guardar dumps/respaldos fuera de htdocs, nunca en Git.

5. Abrir la web y comprobar que `/CarrouselCumple/config/local.php`, `/storage/` y `/database/schema.sql` devuelvan 403. Corregir Apache antes de publicar si no se bloquean.

## SMTP y acceso OTP

La configuración local reutiliza el SMTP autorizado del Helpdesk. Campos: smtp_host, smtp_port, smtp_secure, smtp_username, smtp_password, mail_from y mail_from_name. No publicar la configuración en Git ni copiar contraseñas a manuales. Conservar TLS/SSL con validación de certificados.

Prueba de conexión y autenticación sin enviar mensajes:

```powershell
& 'C:\xampp\php\php.exe' bin\check-smtp.php
```

El personal ingresa el correo de su cuenta activa, recibe un código de seis dígitos y lo introduce en el mismo navegador. Vence en diez minutos, admite hasta cinco intentos y es de un solo uso. Cambiar cuenta/rol revoca sesiones y códigos pendientes. El OTP se envía al solicitarlo, sin esperar al proceso de campañas.

El cliente no usa OTP: recibe el enlace privado al cupón/QR. Quien posea el enlace puede consultarlo; no debe publicarse fuera del beneficiario.

## Roles y centros

- **Administrador:** usuarios, centros, roles, campañas, envíos y supervisión global. La administración de usuarios/roles/centros está reservada a este rol. Sin centro operativo no puede registrar ni canjear.
- **Parque:** altas, consulta de código y canje en el centro de su cuenta. No hay selector de parque en esas operaciones.
- **Supervisor:** consultas de los centros asignados.
- **Gerencia:** consultas de toda la red y bitácora según la matriz.

Pueden crearse roles de operación/supervisión con permisos específicos. Dar alcance a varios centros no permite elegir el centro de un canje. El servidor verifica cada acción. Conservar al menos un administrador activo. Una cuenta compartida identifica la cuenta del parque; para identificar al empleado, crear cuentas individuales del mismo centro.

## Configurar campaña y beneficio

En **Campañas**, crear un borrador y definir nombre, tipo de beneficio (tokens, producto, descuento u otro), descripción, cantidad cuando aplique, condiciones y asunto/texto del correo. Para tokens se exige una cantidad entera positiva; no hay cantidad comercial predeterminada.

Las fechas opcionales limitan cuándo puede emitir la campaña. La vigencia del cupón permanece −15/+15 respecto al cumpleaños, inclusive. Activar cuando el beneficio y la URL pública estén listos. Varias campañas activas pueden dar varios regalos a un cliente: uno por campaña/año.

Editar la campaña no modifica regalos emitidos. Pausar detiene nuevas emisiones; los cupones existentes conservan validez y cola de correo. Para retirar un cupón, anularlo con motivo.

El correo lleva cliente, regalo/cantidad, vigencia, condiciones, código y enlace al cupón/QR. No se acredita automáticamente saldo en tarjetas de juego. El parque entrega o acredita el regalo tras confirmar el canje.

El alta recoge autorización para el programa y, por separado, aceptación opcional de otras promociones. Las campañas implementadas son de cumpleaños; no existe envío masivo genérico por marcar otras promociones.

## Envío automático cada cinco minutos

La tarea **CarrouselCumple-Campanas** ejecuta `bin\console.php run`:

1. Busca clientes activos y campañas activas dentro de sus fechas.
2. Dentro de la ventana de cumpleaños, crea cupón y correo pendiente en una transacción. Un alta dentro de la ventana se procesa en la siguiente ejecución; fuera de ella espera al próximo cumpleaños.
3. Emite solo una vez por cliente/campaña/año y procesa hasta 50 mensajes por ejecución.
4. Reclama cada correo de forma exclusiva. Reintenta fallos confirmados con espera y hasta cinco intentos; los resultados inciertos requieren revisión antes de reintentar.

Cuando SMTP, URL pública y campaña estén listos, cambiar `automation_enabled` a `true` en la configuración privada. Instalar desde **PowerShell como administrador**:

```powershell
Set-Location 'C:\xampp\htdocs\CarrouselCumple'
& .\tools\schedule.ps1
Start-ScheduledTask -TaskName 'CarrouselCumple-Campanas'
Get-ScheduledTaskInfo -TaskName 'CarrouselCumple-Campanas'
```

La tarea principal usa SYSTEM y funciona sin sesión abierta. `-CurrentUser` es una alternativa que solo corre con sesión iniciada. El equipo debe estar encendido, sin suspensión, y MySQL disponible. En Linux programar el mismo comando PHP en el planificador del servidor central.

Verificación manual:

```powershell
& 'C:\xampp\php\php.exe' bin\console.php run
Get-Content .\storage\worker.log -Tail 10
```

**Envíos** muestra pendientes, aceptados por SMTP, fallidos, cancelados e inciertos. Aceptado por SMTP confirma aceptación del proveedor, no lectura ni llegada a bandeja principal. No hay seguimiento de aperturas/rebotes del proveedor. Un reintento conserva el cupón; ante resultado incierto revisar primero el servidor para evitar repetir el mensaje.

`automation_enabled=false` detiene el comando programado. El botón administrativo de emisión prepara cupones/cola; el envío lo hace el proceso programado. No se requiere mantener la página abierta.

## Canje y publicación

El empleado consulta el código, revisa estado/beneficio/vigencia y confirma. Entrega después de la respuesta exitosa. La transacción central bloquea canjes simultáneos y conserva cuenta, centro y fecha/hora. Sin conexión central no se autoriza canje. Cámara QR depende del navegador, permiso y HTTPS; siempre hay ingreso manual.

Para publicar: instalar el paquete en el servidor del dominio/IP, configurar su base central y SMTP, publicar `/CarrouselCumple/` con HTTPS, programar allí el envío y comprobar el enlace desde un dispositivo externo. La instalación local no agrega automáticamente el acceso al portal existente.

Respaldar base y configuración privada de manera protegida. Una restauración antigua puede reabrir cupones ya usados: detener operación y conciliar canjes antes de reanudar. No borrar cupones usados para reutilizarlos. Manual y tutoriales por perfil disponibles dentro de la app.

## Estado comprobado de esta computadora — 22/09/2026

Instalación Apache y base listas; 64 usuarios, 296 centros, cero clientes/campañas/cupones. Conexión TLS y autenticación SMTP correctas sin correo real de prueba. La tarea cada cinco minutos quedó creada con la cuenta de Windows actual (Interactive), y su ejecución terminó con código 0. Windows denegó registrar SYSTEM incluso al solicitar elevación al entorno. Para que corra sin sesión abierta, ejecutar tools/schedule.ps1 desde PowerShell como administrador. No se ha publicado aún en el servidor remoto del portal/IP.

**Actualización del 30/09/2026:** la tarea local se encontró **deshabilitada**. La publicación del código en GitHub no la habilita ni despliega la aplicación en el servidor remoto. Los conteos anteriores son una evidencia histórica, no el estado actual de clientes. Consultar el [flujo del proceso](docs/flujo-del-proceso.md) antes de activar campañas.
