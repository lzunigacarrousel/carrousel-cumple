# Antecedente histórico: verificación de la demostración local

Este documento describe la demo SQLite retirada. Sus scripts se conservan solo en la copia local original y no forman parte del repositorio MariaDB actual. Para instalar y ejecutar las pruebas vigentes, consulta [INSTALACION.md](../INSTALACION.md) y [CONTRIBUTING.md](../CONTRIBUTING.md).

Aplicación principal: http://127.0.0.1:8786/. Las pruebas HTTP y del navegador utilizan una base separada en el puerto 8787, con datos ficticios.

## Revisión del 21 de septiembre de 2026

- `tests/accounts.php`: cuentas de administración y tres parques; asignación desde la cuenta activa, rechazo de suplantación, trazabilidad y revocación por cuenta/parque desactivado.
- `tests/redemption-rules.php`: ventana fija −15/+15, canje en ambos extremos incluidos, cruces de año, 29 de febrero, canje en otro parque y rechazo del segundo uso global.
- `tests/migrate-rules.php`: actualización versionada de la ventana demo conservando códigos, identificadores, beneficio, condiciones personalizadas y trazabilidad; reejecución sin cambios adicionales.
- `tests/domain.php` y `tests/concurrency.php`: correctos. Dos procesos de parques diferentes disputan el mismo cupón: exactamente uno consigue el canje. Dos registros simultáneos de la misma identidad conservan una sola emisión de prueba.
- `tests/http.ps1`: **42 comprobaciones correctas**. Registro autenticado sin enviar parque, asignación desde la sesión, cuenta de registro no manipulable, permisos administrativos, consulta global, canje desde otro parque sin selector, segundo uso rechazado, privacidad de contactos entre parques, CSV con ambos parques y almacenamiento privado.
- La prueba de privacidad detectó primero que la consulta global devolvía contactos de otro parque. Se corrigieron las respuestas de consulta, canje e historial; las tres comprobaciones pasan. Conocer o canjear un código no amplía el acceso a contactos del parque de origen.
- Navegador: Aurora registra un cliente ficticio sin selector de parque; Estrella consulta y canjea el cupón; Cometa consulta el mismo código y ve el parque, cuenta y fecha/hora del uso previo, sin botón de confirmación. Cumpleaños el 21 de septiembre: vigencia del 6 de septiembre al 6 de octubre.
- Manual de operador actualizado para parque automático; tutorial del registro con cuatro pasos. A 390 × 844, el paso «Tu parque y tu cuenta» y sus controles quedan dentro de la pantalla, sin desbordamiento horizontal.
- Administración muestra la indicación de usar la cuenta del parque al abrir Registro; su manual y tutorial explican esa diferencia y no ofrecen elegir un parque para operar.
- Sintaxis PHP y JavaScript comprobada; los cambios finales de API, permisos y ayuda también pasan sus verificaciones.

Estas pruebas acreditan la demostración de cuentas por parque, canje global y fechas. El alta independiente de clientes, la emisión anual, el correo automático y la autenticación real siguen pendientes. Los datos de prueba no se escribieron en la base principal y no se enviaron mensajes.

## Evidencia histórica del 18 de septiembre de 2026

Verificada en Windows, PHP 8.2 de XAMPP y navegador integrado. Las reglas de parque de esta versión fueron sustituidas por la revisión del 21 de septiembre.

- `php tests/domain.php`: correcto. Registro, duplicados, hermanos con contacto compartido, fechas imposibles, 29 de febrero, cambio de año, vigencia inclusiva, cupones futuros/vencidos/anulados, condiciones conservadas, parque incorrecto/inactivo y caracteres acentuados.
- `php tests/concurrency.php`: correcto. Dos procesos simultáneos logran exactamente un canje y un rechazo. Dos registros de la misma identidad producen un solo cupón.
- `tests/http.ps1`: 17 comprobaciones correctas. Sesión, CSRF, configuración, registro persistente, reintento, salida pública sin contacto, canje único, alcance por parque del operador, exportación y almacenamiento privado.
- Sintaxis: todos los archivos PHP pasan `php -l`; `public/app.js` pasa `node --check`.
- Navegador: registro de menor, QR visible, consulta, canje, bloqueo del segundo canje, anulación con motivo, campaña en borrador que detiene registros, reactivación y cierre de sesión desde Registro. Sin errores de consola en el recorrido final.
- Vista de celular revisada a 390 × 844 sin desbordamiento horizontal.
- Cámara: simulación de rechazo de reproducción, permiso resuelto después de salir y solicitudes simultáneas; los flujos detenidos liberan sus pistas. No se activó ni verificó una cámara física.
- Lanzadores: arranque, reconocimiento de servidor existente, parada y nuevo arranque comprobados con Windows PowerShell. Archivos PowerShell guardados como UTF-8 con BOM para conservar acentos en Windows PowerShell 5.

La campaña de prueba quedó restaurada a su beneficio genérico y estado activo. El servidor de pruebas se detiene al entregar; la demostración principal permanece disponible. No se modificó el portal existente ni se publicó esta aplicación.

## Límites

Esta evidencia corresponde a la demo local con SQLite, perfiles de demostración y parques ficticios. No verifica MariaDB, integración al portal/POS, envíos de WhatsApp o correo, cuentas reales ni carga productiva.
