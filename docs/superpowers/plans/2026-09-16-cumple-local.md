# Carrousel Cumple — plan de primera versión local

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development. Ejecutar las tareas y revisar la integración antes de entregar.

**Goal:** Construir una demostración funcional y persistente del registro, cupón y canje para clientes, con beneficio configurable.

**Architecture:** PHP 8.2 con PDO SQLite en almacenamiento privado para una instalación local sin configurar MariaDB. API JSON, interfaz JavaScript sin compilación, datos ficticios y acceso exclusivamente desde loopback. La integración productiva PHP/MariaDB del documento general sigue siendo una etapa posterior.

**Tech Stack:** PHP 8.2, PDO SQLite, HTML, CSS y JavaScript; QRCode.js local con licencia MIT.

**Spec:** `docs/propuesta-carrousel-cumple.md`, con la aclaración del usuario: todo es nuevo y no hay beneficio vigente. Esta entrega concreta el alcance mediante configuración y ejemplos de demostración.

## Global Constraints

- Solo clientes. Menores con adulto responsable, varios hijos pueden compartir contacto.
- Toda pantalla y cupón indican «Demostración · sin valor comercial».
- Solo escucha/acepta loopback; sin despliegue ni cambios al portal real.
- Datos SQLite fuera de `public`, consultas preparadas, CSRF para POST y sesión para acciones internas.
- Fecha `America/Guatemala`; límite propuesto de un cupón por beneficiario/campaña/año en la demostración.
- Canje atómico con control de vigencia y parque, escanear no consume, reintento no duplica.
- Las condiciones se copian al cupón al emitirse; cambios de campaña afectan nuevas emisiones.
- Sin envío real de mensajes, POS ni datos de clientes reales.

## Contratos de integración

`app/Store.php`: `Store::open(string $path): PDO`, crea esquema y campaña/3 parques de ejemplo. Tablas de dominio propias; autenticación `users` y límites HTTP pertenecen a root.

`app/Coupons.php`: clase `Coupons(PDO $db, ?DateTimeImmutable $now = null)`.

- `campaign(): array`; `parks(): array` devuelve parques `{id,name,active}`.
- `saveCampaign(array $input): array`, valida y aumenta versión.
- `register(array $input): array` devuelve `{coupon: array, duplicate: bool}`.
- `byToken(string $token): ?array`; `byCode(string $code): ?array`.
- `listing(array $filters = []): array` con filtros q, park_id, status.
- `stats(array $filters = []): array` con total, available, redeemed, expired, void.
- `redeem(string $code, int $parkId, int $operatorId): array`, devuelve cupón actualizado, rechaza segundo uso con `DomainException`.
- `void(string $code, string $reason, int $operatorId): array`, no admite canjeados.

Campos campaña: `id,title,benefit_label,terms,before_days,after_days,status,version`; estado draft|active. Ventana -7/+7 días de ejemplo; política 29 febrero → 28 febrero en año no bisiesto, explicitada al usuario.

Registro: `name,day,month,phone,email,guardian,is_minor,park_id,terms_accepted,marketing_opt_in,request_id`. Sin año de nacimiento. Teléfono guatemalteco normalizado +502 y 8 dígitos. Fecha válida con 29 febrero admitido.

Cupón completo: `id,token,code,name,day,month,phone,email,guardian,is_minor,park_id,park_name,benefit_label,terms,valid_from,valid_until,birthday_year,status,created_at,redeemed_at,redeemed_by,redeemed_park_id,void_reason`. Estado calculado available|upcoming|redeemed|expired|void. Solo parque de registro válido para canje en demostración; visible en cupón.

API en `public/api.php?action=...`. Respuesta éxito `{ok:true,data:...}`, fallo `{ok:false,error:"..."}` con HTTP apropiado. Todos POST JSON y encabezado `X-CSRF-Token` del bootstrap.

- GET bootstrap: `{csrf,demo:true,today,user:null|{id,name,role,park_id},campaign,parks}`.
- GET coupon&token=...: cupón público sin contacto, token, identificadores de operador ni campos internos.
- GET dashboard (personal): `{stats,coupons}`. Alcance por parque de operador.
- GET lookup&code=... (personal): cupón; restringir operador a su parque.
- GET export (administrador): CSV con filtros q/status/park_id.
- POST register, login, demo-login, logout, redeem, campaign, void.
- POST demo-login elige role admin|operator; operador queda en parque 1. Visible solo en esta demo local.
- Admin configura campaña y anula. Operador consulta/canjea solo parque asignado.

## Task 1 — Dominio y persistencia (agente backend)

Archivos: app/Store.php, app/Coupons.php, tests/domain.php.

- [x] Escribir pruebas reales sobre SQLite temporal: duplicado frente a hermanos, límites de fecha, campaña borrador, snapshot de condiciones, rechazo de parque incorrecto y doble canje.
- [x] Verificar los comportamientos de dominio con pruebas ejecutables.
- [x] Implementar validación y persistencia con transacciones y restricciones únicas.
- [x] Ejecutar pruebas, añadir prueba de dos conexiones y revisar fallos.

Ejemplo de contrato de prueba: registrar a Ana y Luis con mismo teléfono debe producir dos códigos; registrar Ana dos veces en el mismo año conserva uno. `redeem($code,2,10)` de cupón de parque 1 lanza excepción y no modifica estado; dos intentos válidos dejan un solo canje.

## Task 2 — Interfaz de cliente y panel (agente frontend)

Archivos: public/app.js, public/app.css. Consume API anterior; shell raíz `#app` y archivos locales `assets/logo-carrousel.png`, `vendor/qrcode.min.js`. No crear API ni bootstrap.

- [x] Construir formulario adaptable, preview de cupón, validación y confirmación real por API.
- [x] Construir panel con filtros, indicadores, tabla, búsqueda/canje, configuración y acceso demo.
- [x] Escape de texto dinámico y manejo de errores, botón imprimir y QR real.
- [x] Verificar sintaxis; root verifica interacciones y tamaños en navegador.

Rutas hash: `#registro`, `#cupon?token=...`, `#panel`, `#canjear`, `#campana`, `#acceso`. Cambio de estado solo tras respuesta API. Consulta por código precede confirmación de canje. Código manual siempre disponible; lectura por cámara con BarcodeDetector cuando disponible y mensaje claro si no.

## Task 3 — Sesiones, API y arranque (root)

Archivos: app/bootstrap.php, app/Http.php, public/index.php, public/api.php, public/router.php, tools/serve.ps1, INICIAR_CARROUSEL.bat, tests/http.ps1, .gitignore.

- [x] Verificar endpoints sin sesión, CSRF ausente y acceso privado.
- [x] Implementar sesiones, límites de solicitudes, roles y control de origen/loopback.
- [x] Sanitizar salida pública, CSV y mensajes de error; no servir archivos fuera de public.
- [x] Lanzar servidor local oculto, reutilizar proceso si corresponde al proyecto y documentar arranque/parada.

## Task 4 — Integración y entrega

- [x] Ejecutar pruebas de dominio y HTTP; corregir fallos con regresión.
- [x] Revisar formulario, registro, cupón, QR, login, canje, segundo canje y configuración en navegador.
- [x] Revisar código completo con agente independiente y resolver hallazgos materiales.
- [x] Actualizar README con funciones reales, limitaciones y comandos verificados.
- [x] Abrir demostración local para el usuario y entregar acceso y lanzador.

## Decisiones registradas

- Repositorio nuevo sin commits; trabajar en su carpeta dedicada ya aislada del portal productivo. No crear otra copia ni modificar repositorios vecinos.
- SQLite hace la demo portable y permite pruebas de transacciones; no se afirma compatibilidad MariaDB sin una migración posterior.
- No se define un costo real de promoción: beneficio de demostración editable y campaña puede ponerse en borrador.
- Ninguna cuenta demo ni campaña de ejemplo se publicará en Internet.
