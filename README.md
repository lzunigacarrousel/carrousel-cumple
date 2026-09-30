# Carrousel Cumple

Aplicación PHP 8.2 / MariaDB para registrar clientes, enviar regalos de cumpleaños por correo y controlar un único canje en toda la red Carrousel. El personal accede por OTP; el cliente recibe un enlace privado a su cupón.

La interfaz, el manual y los tutoriales siguen [Helpdesk Carrousel](https://github.com/FernandoZL/helpdesk-carrousel). [PayOutParques](https://github.com/FernandoZL/PayOutParques) y [Caja Chica Carrousel](https://github.com/FernandoZL/caja-chica-carrousel) son referencias para la operación y sus catálogos. Estas aplicaciones conservan sus configuraciones y bases independientes; Cumple no sincroniza automáticamente sus usuarios.

## Estado y trabajo del practicante

`main` reúne el código y la documentación del proyecto. Las actualizaciones revisadas de PHPMailer **7.1.1** y de la acción `setup-php` se integraron conservando sus versiones fijadas. Las ramas de esos PR se eliminan después de la integración; futuras tareas se realizan en ramas temporales y se revisan antes de incorporarlas a `main`.

| Área | Implementado actualmente | Encargo pendiente |
| --- | --- | --- |
| Vigencia | −15/+15, extremos incluidos | Cambiar a **−15/+14 (30 días)**, confirmado por negocio |
| Registro | Día/mes, contacto, tutor y centro desde la cuenta | Nacimiento completo, cálculo de edad y validaciones del proceso recibido |
| Canje | Un uso global, transacción, cuenta/centro/fecha | Factura obligatoria y coordinación con POS/SemnoX |
| POS/SAP | Catálogo inicial de centros importado | Correspondencias validadas según IDS.xlsx y trazabilidad histórica |
| Cupón y correo | Código, enlace privado, QR y cola SMTP | Presentación de imagen según formato acordado |

El [encargo del practicante](docs/encargo-practicante.md) contiene entregas, criterios de aceptación y decisiones pendientes. La [relación POS/SAP](docs/relacion-pos-sap.md) se basa en [IDS.xlsx](docs/referencias/IDS.xlsx): 54 sucursales POS y 299 centros SAP en dos catálogos separados; el archivo no incluye el cruce entre ambos. Los documentos originales del proceso están disponibles en [PDF](docs/referencias/proceso-cumpleaneros.pdf) y [Markdown](docs/referencias/proceso-cumpleaneros.md).

## Empezar a colaborar

Requisitos: Git, Composer, PHP 8.2 con PDO MySQL, mbstring y OpenSSL, MariaDB 10.4 o superior y Apache con `mod_rewrite`. Para los scripts de comprobación: PowerShell 7 y Node.js para revisar la sintaxis de JavaScript.

PHPMailer se instala desde `composer.lock`; no copiar `vendor` de otra aplicación ni ejecutar `composer update` como parte de una instalación ordinaria. Los recursos QR del navegador tienen su propia [referencia y licencia](public/vendor/README.md).

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

**Adaptación encargada al practicante:** negocio confirmó una nueva ventana **−15/+14** y un canje con factura obligatoria. El código aún conserva −15/+15 y no solicita factura. Consulta el [encargo, entregas y criterios de aceptación](docs/encargo-practicante.md); la descripción siguiente corresponde a la versión actual.

Consulta el [flujo completo del proceso con diagramas](docs/flujo-del-proceso.md): responsables, fechas, envío automático, excepciones y canje entre parques.

1. Administración configura usuarios, roles, centro operativo y una campaña con beneficio, condiciones y correo.
2. Un empleado registra al cliente y su autorización para participar. El registro por sí solo no emite el cupón.
3. El proceso programado emite una vez por cliente, campaña y año dentro de la ventana de cumpleaños y procesa la cola de correo.
4. El parque consulta el código en la base central y confirma el canje antes de entregar el regalo. Quedan registrados cuenta, centro y fecha/hora.

La versión actual todavía usa **15 días antes y 15 días después**, ambos extremos incluidos. El objetivo aprobado es **−15/+14** y su implementación corresponde al practicante. Un alta dentro de la ventana puede recibir el regalo en la siguiente ejecución del proceso; fuera de ella espera a su próxima ventana, sujeto a una campaña activa. El 29 de febrero se considera 28 de febrero en años no bisiestos.

Todos los parques operan contra la misma base central. El parque de registro no limita el parque de canje, y un canje confirmado impide volver a usar el cupón en cualquier parque. El beneficio y las condiciones emitidos se conservan aunque se edite la campaña. La cantidad de tokens la define administración; no existe una oferta comercial predeterminada.

## Verificar un cambio

```powershell
pwsh -File tools/check.ps1
pwsh -File tools/check.ps1 -Integration
```

La primera orden revisa sintaxis; la segunda prepara y ejecuta las pruebas de base de datos y HTTP. La suite usa exclusivamente `carrousel_cumple_test` y correo capturado. **Reinicia los datos de esa base aislada**; no uses una base operativa ni ejecutes otra prueba simultáneamente. Configuración y opciones: [CONTRIBUTING.md](CONTRIBUTING.md#pruebas).

La validación automática complementa la revisión en navegador de registro, selección, guardado y edición. Para cambios visuales, revisar también móvil, modo oscuro, ayuda y permisos del perfil.

Verificación local del 30/09/2026 tras actualizar dependencias: **131 comprobaciones aprobadas**, Composer válido y auditoría sin avisos de vulnerabilidad. También se comprobó la construcción MIME con PHPMailer 7.1.1 y `TrackedSMTP` sin enviar correo. Esto valida la versión actual, no los requisitos aún pendientes del practicante.

GitHub Actions está configurado. Su ejecución remota se encontró bloqueada por facturación/límite de cuenta; una marca roja por ese bloqueo no equivale a una prueba ejecutada y fallida de la aplicación. Hasta resolverlo, adjuntar evidencia local a los PR. Dependabot conserva su revisión semanal y puede abrir nuevas ramas cuando encuentre actualizaciones; no se integran automáticamente.

## Documentación y estado

- [Instalación, SMTP, proceso programado y publicación](INSTALACION.md).
- [Contrato de la API](docs/api-real.md).
- [Flujo del proceso de cumpleaños](docs/flujo-del-proceso.md).
- [Referencia visual Helpdesk](docs/referencia-visual-helpdesk.md) y [verificación del 25/09/2026](docs/verificacion-visual-helpdesk.md).
- [Colaboración y pendientes](docs/colaboracion.md).
- [Encargo del practicante](docs/encargo-practicante.md) y [relación POS/SAP](docs/relacion-pos-sap.md).

La instalación local no publica la aplicación en Internet. El despliegue en el servidor remoto sigue pendiente. La tarea de envío de esta computadora se confirmó deshabilitada el 30/09/2026: subir el código no la activa. Los documentos antiguos de demo describen antecedentes y no la instalación PHP/MariaDB actual.
