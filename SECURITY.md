# Seguridad y datos privados

Comunica una vulnerabilidad al propietario FernandoZL por un canal privado autorizado. No abras una incidencia pública con credenciales, datos de clientes, enlaces de cupones, códigos OTP o volcados de base de datos. Incluye la versión afectada y pasos de reproducción con datos ficticios.

La configuración real está en `config/local.php`, fuera de Git. Cada entorno debe tener sus propias claves, cuenta de base de datos y configuración SMTP. Los ejemplos no contienen credenciales operativas. Los respaldos deben almacenarse fuera del directorio web y del repositorio.

Las pruebas usan exclusivamente `carrousel_cumple_test` y capturan el correo. Nunca apuntes una suite a la base de operación. GitHub Actions no necesita secretos de producción ni acceso al servidor real.

Si se publica una credencial por accidente, revócala y cámbiala en el servicio antes de limpiar el historial: borrar el archivo en un commit posterior no elimina las copias anteriores. Coordina la reparación con el propietario.

Solo instala cambios revisados desde `main`. La configuración de Apache debe bloquear `config`, `storage`, `database`, herramientas internas y archivos Git. Las actualizaciones de dependencias propuestas por Dependabot también requieren revisión y pruebas.
