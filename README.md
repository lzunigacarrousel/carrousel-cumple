# Carrousel Cumple

Aplicación PHP 8.2 / MariaDB para registrar clientes, enviar regalos de cumpleaños por correo y controlar un único canje en toda la red Carrousel. El personal accede por OTP; el cliente recibe un enlace privado a su cupón.

La interfaz, el manual y los tutoriales siguen [Helpdesk Carrousel](https://github.com/FernandoZL/helpdesk-carrousel). [PayOutParques](https://github.com/FernandoZL/PayOutParques) y [Caja Chica Carrousel](https://github.com/FernandoZL/caja-chica-carrousel) son referencias para la operación y sus catálogos. Estas aplicaciones conservan sus configuraciones y bases independientes; Cumple no sincroniza automáticamente sus usuarios.

## Empezar a colaborar

Requisitos: Git, Composer, PHP 8.2 con PDO MySQL, mbstring y OpenSSL, MariaDB 10.4 o superior y Apache con `mod_rewrite`. Para los scripts de comprobación: PowerShell 7 y Node.js para revisar la sintaxis de JavaScript.

```powershell
git clone https://github.com/FernandoZL/carrousel-cumple.git
Set-Location carrousel-cumple
composer install
Copy-Item config/local.php.example config/local.php
```

Configura tu base propia y SMTP en `config/local.php`, genera una `app_key` y sigue [INSTALACION.md](INSTALACION.md) para crear el esquema y la primera cuenta administrativa, o cargar un catálogo autorizado. El repositorio no contiene credenciales, usuarios reales ni una base de datos. Para desarrollo y pruebas se dispone de datos sintéticos.

La carpeta clonada es la fuente de desarrollo. `tools/install-xampp.ps1` actualiza `C:\xampp\htdocs\CarrouselCumple` y conserva la configuración privada del destino. La entrada local es [http://localhost/CarrouselCumple/](http://localhost/CarrouselCumple/). Mantén las modificaciones en la fuente, no solamente en la copia de XAMPP.

Lee [CONTRIBUTING.md](CONTRIBUTING.md) para ramas, pruebas y pull requests; [docs/colaboracion.md](docs/colaboracion.md) describe la arquitectura, el reparto de tareas y los pendientes.

## Cómo funciona

Consulta el [flujo completo del proceso con diagramas](docs/flujo-del-proceso.md): responsables, fechas, envío automático, excepciones y canje entre parques.

1. Administración configura usuarios, roles, centro operativo y una campaña con beneficio, condiciones y correo.
2. Un empleado registra al cliente y su autorización para participar. El registro por sí solo no emite el cupón.
3. El proceso programado emite una vez por cliente, campaña y año dentro de la ventana de cumpleaños y procesa la cola de correo.
4. El parque consulta el código en la base central y confirma el canje antes de entregar el regalo. Quedan registrados cuenta, centro y fecha/hora.

La vigencia es **15 días antes y 15 días después del cumpleaños, ambos extremos incluidos**. No corresponde al mes calendario. Un alta dentro de esa ventana puede recibir el regalo en la siguiente ejecución del proceso; fuera de ella espera a su próxima ventana, sujeto a una campaña activa. El 29 de febrero se considera 28 de febrero en años no bisiestos.

Todos los parques operan contra la misma base central. El parque de registro no limita el parque de canje, y un canje confirmado impide volver a usar el cupón en cualquier parque. El beneficio y las condiciones emitidos se conservan aunque se edite la campaña. La cantidad de tokens la define administración; no existe una oferta comercial predeterminada.

## Verificar un cambio

```powershell
pwsh -File tools/check.ps1
pwsh -File tools/check.ps1 -Integration
```

La primera orden revisa sintaxis; la segunda prepara y ejecuta las pruebas de base de datos y HTTP. La suite usa exclusivamente `carrousel_cumple_test` y correo capturado. **Reinicia los datos de esa base aislada**; no uses una base operativa ni ejecutes otra prueba simultáneamente. Configuración y opciones: [CONTRIBUTING.md](CONTRIBUTING.md#pruebas).

La validación automática complementa la revisión en navegador de registro, selección, guardado y edición. Para cambios visuales, revisar también móvil, modo oscuro, ayuda y permisos del perfil.

## Documentación y estado

- [Instalación, SMTP, proceso programado y publicación](INSTALACION.md).
- [Contrato de la API](docs/api-real.md).
- [Flujo del proceso de cumpleaños](docs/flujo-del-proceso.md).
- [Referencia visual Helpdesk](docs/referencia-visual-helpdesk.md) y [verificación del 25/09/2026](docs/verificacion-visual-helpdesk.md).
- [Colaboración y pendientes](docs/colaboracion.md).

La instalación local no publica la aplicación en Internet. El despliegue en el servidor remoto sigue pendiente. La tarea de envío de esta computadora se confirmó deshabilitada el 30/09/2026: subir el código no la activa. Los documentos antiguos de demo describen antecedentes y no la instalación PHP/MariaDB actual.
