# Verificación de interfaz Helpdesk

Fecha: 25 de septiembre de 2026.

## Entorno y alcance

Fuente: workspace de Carrousel Cumple. Referencia consultada en modo de solo lectura: `C:/xampp/htdocs/HelpdeskCarrousel`. Pruebas de navegador en `http://127.0.0.1:8787/`, base aislada `carrousel_cumple_test`, cuentas sintéticas y correo capturado por `tests/router.php`. Ninguna oferta de prueba pertenece a la instalación real.

## Resultados

- SHA-256: las 13 hojas copiadas coinciden con el inventario y el Helpdesk local.
- Acceso: comparación visual con login del Helpdesk; solicitud OTP e ingreso administrativo y operativo con transporte capturado. Reenvío de OTP comprobado en la revisión inicial.
- Administración: formularios ocultos en la lista y visibles al crear/editar; cancelar regresa al listado.
- Usuarios: rol Parque más texto Parque B muestra 1 de 3 registros; edición conserva sus datos.
- Guardado: campaña de producto en borrador creada desde la interfaz y visible en la tabla. Usuario, rol Parque y centro existentes guardados sin alterar sus valores.
- Búsqueda global: Cliente QA devuelve resultados separados de clientes y cupones, con enlaces al registro y a la consulta del código.
- Manual: guía administrativa adaptada a cuenta sin centro, tareas, índice, FAQ y búsqueda por 29 de febrero; abre respuesta y permite limpiar. El recorrido avanza y finaliza.
- Perfil de parque: menú solo con clientes, regalos y ayuda; registro con centro fijo de la cuenta, sin selector. Ayuda contextual con las reglas de inscripción y canje. Modo oscuro revisado en móvil.
- Móvil a 390 × 844: menú abre; formulario de campaña y manual sin desbordamiento horizontal. El tutorial queda dentro del viewport y se cierra.
- Sintaxis: `node --check public/app.js`, `public/help.js`, `public/theme.js`; `php -l public/index.php`.
- API: `tests/http-production.ps1`, 32 comprobaciones correctas de OTP, permisos, registro independiente, emisión, canje desde otro centro y rechazo del segundo uso.

## Incidencia detectada y corregida

Reproducción: completar Nueva campaña y pulsar Guardar no procesaba el formulario. Se comprobó en el DOM que `form.id` era un elemento INPUT: el campo oculto `name="id"` oculta la propiedad de HTMLFormElement. El evento submit ahora usa `form.getAttribute('id')`. La misma corrección cubre campañas, usuarios, roles y centros. Tras recargar, los cuatro formularios guardaron y mostraron su confirmación; la nueva campaña apareció en el listado.

## Límites

La comparación visual completa usa fuente local y pantallas propias: no se inició sesión ni se modificaron datos del Helpdesk. Las pruebas de SMTP de esta revisión son capturadas; no acreditan entrega a un buzón real. La actualización de XAMPP no publica la aplicación en el servidor remoto.

## Instalación Apache comprobada

Actualizada `C:/xampp/htdocs/CarrouselCumple` desde el workspace. Los hashes de app.js, help.js, cumple.css, index.php y layout-density-v25.css coinciden. Entrada y hoja de densidad responden HTTP 200; config/local.php devuelve HTTP 403. Lectura de conteos en la base real: 64 cuentas, 296 centros, 2 clientes existentes, 0 campañas y 0 cupones. Esta revisión visual no insertó registros de prueba en esa base.
