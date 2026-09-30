# Verificación de instalación real — 22/09/2026

## Evidencia

- MariaDB 10.4.32 / PHP 8.2. Base real carrousel_cumple: 64 usuarios, 296 centros, cero clientes/campañas/cupones.
- Instalación Apache en C:/xampp/htdocs/CarrouselCumple. Inicio, app.js, CSS, QR y bootstrap responden 200; config/local.php, storage, database/schema.sql y public/router.php responden 403.
- No se instaló config/test.php, carpeta tests ni la SQLite antigua.
- SMTP: conexión cifrada y autenticación correctas con la configuración autorizada, sin envío de mensajes reales. Correo funcional probado con transporte capturado en base aislada.
- Tarea CarrouselCumple-Campanas: repetición cinco minutos, cuenta Interactive del usuario, última ejecución 0. Log confirma issued=0, accepted=0, failed=0, uncertain=0, canceled=0. Windows denegó modalidad SYSTEM; requiere ejecutar schedule.ps1 como administrador para trabajar sin sesión.

## Pruebas de aplicación

- production.php: 29 comprobaciones; registro separado, campañas, snapshot del beneficio, permisos, OTP y canje global.
- production-edges.php: 25 comprobaciones; extremos inclusivos, cambio de año, 29 de febrero, correo incierto/reintentos, OTP y centro inactivo.
- production-races.php: 3 comprobaciones con procesos PHP independientes; emisión única, un canje entre dos centros y reclamación exclusiva de correo.
- review-regressions.php: 24 comprobaciones; privacidad de idempotencia, revocación OTP/sesiones tras cambiar correo/centro/rol, permisos dependientes, cantidad en ambos cuerpos del correo, estadísticas superiores a 500 y filtro previo al límite.
- http-production.ps1: 32 comprobaciones; CSRF, OTP capturado, roles, alta, campaña, cola, consulta/canje cruzado y cierre de sesión.
- catalog.php: importación selectiva, relación con centro e idempotencia; ignora la tabla de gastos.
- Sintaxis de PHP y JavaScript correcta.

El primer pase de concurrencia dejó su cola vacía al canjear su único regalo; se corrigió la preparación de la prueba con un cliente independiente para envío. La ejecución posterior comprobó tres escenarios exitosos. Las 15 regresiones inicialmente fallidas de la revisión pasaron después de corregir su causa; se agregó una comprobación de dependencias de permisos.

## Navegador

Acceso OTP con cuentas ficticias de administración y parque; menú según permisos; panel y campaña; manual con búsqueda/temas, ayuda y tutorial de pasos; vista móvil de 390 px sin desbordamiento horizontal; modo oscuro; cupón público por enlace con QR, vigencia y condiciones sin contactos privados. No se probó cámara física ni entrega a un buzón real.

Se corrigió el router exclusivo de pruebas para propagar return false y servir correctamente sus recursos estáticos. La instalación Apache usa reglas propias comprobadas.

## Revisión y pendientes externos

La revisión independiente señaló cinco hallazgos en storage/review/production-review.md. Se corrigieron privacidad en idempotencia, invalidación OTP con bloqueo de cuenta antes de códigos, cantidad del correo, agregados completos y filtros SQL. La revisión final de las correcciones fue realizada por el coordinador y respaldada por las regresiones anteriores; no se obtuvo una segunda revisión independiente.

No se ha desplegado en el servidor remoto de portal.carrousel-apps.com / 94.74.71.96. La URL pública está configurada para los enlaces; no se activó una campaña real ni se eligió un beneficio comercial. La promoción debe definirse desde Administración antes de comenzar envíos. El SQL suministrado ya no estaba disponible en su ruta original al repetir la comprobación; los catálogos previamente importados siguen presentes y se verificaron sus totales. La idempotencia se validó con un SQL sintético aislado.
