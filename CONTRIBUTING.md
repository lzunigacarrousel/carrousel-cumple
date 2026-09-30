# Contribuir a Carrousel Cumple

Trabajamos sobre la fuente del repositorio, con configuración privada por entorno y datos sintéticos para comprobar cambios. La referencia visual principal es Helpdesk Carrousel; consulta [AGENTS.md](AGENTS.md) antes de modificar una regla de negocio o la interfaz.

## Preparar el entorno

1. Clona el repositorio e instala sus dependencias con `composer install`. Usa la versión fijada en `composer.lock`; una actualización de dependencias debe quedar explícita en su PR.
2. Copia `config/local.php.example` a `config/local.php` y configura una base de desarrollo propia. Sigue [INSTALACION.md](INSTALACION.md) para una instalación operativa y su primera cuenta administrativa.
3. Para probar sin catálogos o correo reales, prepara el entorno aislado de la sección siguiente.

No subas `config/local.php`, `config/test.php`, `.env`, dumps, respaldos, sesiones, mensajes OTP, correos capturados ni datos de clientes. Los ejemplos de configuración sí se versionan y deben conservar valores ficticios. `vendor/` se reconstruye con Composer.

## Pruebas

Necesitas PHP 8.2, MariaDB, PowerShell 7 y Node.js. Los comandos `php`, `composer` y `node` deben estar en PATH. Para XAMPP, los scripts admiten `-Php 'C:/xampp/php/php.exe'` si PHP no está en PATH.

La comprobación de sintaxis no requiere una base de datos:

```powershell
pwsh -File tools/check.ps1
```

Para la suite completa, crea una cuenta MariaDB exclusiva con permisos sobre `carrousel_cumple_test`, incluido crear esa base. Configura `CUMPLE_TEST_DB_HOST`, `CUMPLE_TEST_DB_PORT`, `CUMPLE_TEST_DB_USER` y `CUMPLE_TEST_DB_PASS` en tu entorno local. Si no existe `config/test.php`, el preparador lo genera desde `config/test.php.example` y crea una `app_key` aleatoria. Una configuración existente se conserva y valida; las variables del entorno no sustituyen sus credenciales.

```powershell
pwsh -File tools/check.ps1 -Integration
```

La suite crea la base y el esquema de pruebas, comprueba la instalación, y ejecuta `production.php`, `production-edges.php`, `production-races.php`, `review-regressions.php`, `catalog.php` y el flujo HTTP con un servidor temporal propio. **Reinicia los datos de `carrousel_cumple_test`**; no uses una base operativa ni ejecutes otras pruebas simultáneamente contra ella. El nombre de esa base es fijo y solo se admiten conexiones locales; no debe sustituirse por otro.

Para investigar un fallo por separado:

```powershell
php bin/setup-tests.php
php tests/production.php
php tests/production-edges.php
php tests/production-races.php
php tests/review-regressions.php
php tests/catalog.php
```

Ejecuta la secuencia en ese orden: `production.php` prepara las cuentas sintéticas que las suites siguientes necesitan. Las pruebas cubren registro independiente, ventanas inclusivas −15/+15, cambio de año, 29 de febrero, permisos, OTP, cola de correo, conservación del beneficio, importación idempotente y canjes concurrentes.

El router `tests/router.php` fija la configuración de pruebas y captura mensajes en `storage/test-outbox.json`; no envía correo real. Nunca lo publiques como servidor de operación ni apuntes las pruebas HTTP a la instalación real. Para una revisión manual de navegador con XAMPP, tras preparar los datos puedes usar `tools/start-tests.ps1`, que abre el entorno aislado en `http://127.0.0.1:8787/`.

Las pruebas de fechas inyectan una fecha controlada en `Program`; no cambies el reloj de Windows o de una base real para simular cumpleaños. Ante un fallo, registra el comando y el resultado, corrige la causa y vuelve a ejecutar las comprobaciones afectadas. Un resultado anterior no valida un cambio nuevo.

## Ramas y cambios

`main` contiene el trabajo integrado. Crea una rama breve por objetivo:

```powershell
git switch main
git pull --ff-only
git switch -c codex/descripcion-del-cambio
```

Antes de editar, acuerda el alcance en una incidencia o descripción de tarea. Separa problemas independientes y evita que dos personas modifiquen a la vez los mismos archivos. No hace falta abrir una incidencia para una corrección pequeña si el PR explica claramente el problema.

Al preparar el commit, revisa la lista y el contenido exacto:

```powershell
git status --short
git diff
git add RUTAS_DE_LOS_ARCHIVOS_DEL_CAMBIO
git diff --cached --check
git diff --cached
git commit -m "fix: describir el comportamiento corregido"
git push -u origin codex/descripcion-del-cambio
```

Sustituye las rutas y el nombre de rama por los de tu cambio. Usa mensajes concretos; `feat:`, `fix:`, `docs:` y `test:` ayudan a distinguir su propósito. No añadas archivos ajenos al objetivo del PR.

## Pull requests y revisión

Abre un PR hacia `main` con la plantilla del repositorio. Explica el problema, el comportamiento final y los comandos realmente ejecutados. Incluye capturas con datos sintéticos si cambia la interfaz. Si no pudiste hacer una comprobación, indícalo.

La persona que revisa verifica el alcance y la evidencia, especialmente fechas, permisos, datos compartidos y canje único. Espera a que las comprobaciones configuradas en GitHub terminen y resuelve los comentarios antes de integrar. Las protecciones efectivas de rama dependen de la configuración del repositorio; estos archivos no las activan por sí solos.

Cambiar el esquema exige explicar cómo se actualiza una base existente sin perder registros. Cambiar una pantalla exige mantener el manual y el tutorial correspondientes. Conserva el uso de `form.getAttribute('id')`: los formularios administrativos contienen un campo `name="id"` que oculta `form.id` en el navegador.

## Reportar un problema

Usa las plantillas de incidencia para errores, mejoras y tareas. Indica pasos, resultado esperado, resultado observado y entorno. Usa nombres y correos ficticios como `cliente@example.test`; oculta información personal en capturas. No pegues configuraciones privadas, códigos OTP, enlaces privados de cupones, SQL real ni mensajes completos de clientes.

Si el reporte contiene credenciales o una vulnerabilidad que permite acceso indebido, comunícalo por un canal privado con el responsable del repositorio. Si una credencial ya se publicó, retirarla del archivo no la invalida: debe revocarse o cambiarse.
