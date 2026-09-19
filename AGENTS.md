# Convenciones de Carrousel Cumple

## Referencia principal del usuario

El usuario designó `https://github.com/FernandoZL/helpdesk-carrousel` como la base principal de sus aplicaciones: diseño, navegación, componentes, manuales, ayuda contextual y tutoriales. En este proyecto esa referencia prevalece sobre el estilo de la primera demo.

Copia local comprobada: `C:/xampp/htdocs/HelpdeskCarrousel`. Consultar su documentación y componentes en modo de solo lectura; no modificar ese proyecto ni copiar configuraciones privadas, bases de datos, sesiones o credenciales.

Aplicar su estándar visual y sus patrones reales de interacción. Mantener la lógica específica de cumpleaños. La documentación de origen está en `docs/ESTANDAR_VISUAL_CARROUSEL.md`, las vistas comunes en `app/Views/shared/`, el manual en `app/Views/help/manual.php` y los tutoriales en `public/assets/js/help-tour.js` del Helpdesk.

## Requisitos confirmados

- Público: clientes de Carrousel. Registro dentro de esta nueva aplicación; no hay importación de listas en el alcance confirmado.
- El registro inscribe al cliente en el programa. No solicita ni genera un cupón como resultado inmediato del formulario.
- El regalo se emite automáticamente para los clientes registrados y se envía por correo electrónico.
- Vigencia relativa al cumpleaños: 15 días antes y 15 días después, ambos extremos incluidos. No equivale al mes calendario.
- El regalo será de tokens de juego; su cantidad sigue sin definir. Nunca inventar una cantidad ni confundirla con el identificador técnico del cupón.
- Conservar canje único, control de vigencia, permisos y trazabilidad.
- Manuales y tutoriales deben describir funciones existentes y adaptarse al perfil; no basta con documentos Markdown externos.

## Estado

La demo original funciona con PHP y SQLite, pero su interfaz y emisión al registrarse corresponden a un flujo anterior. Consultar `docs/superpowers/specs/2026-09-18-regalo-automatico-helpdesk-design.md` para la revisión propuesta; no afirmar que ya está implementada.

Las pruebas previas son evidencia del flujo anterior. La revisión nueva requiere pruebas de registro independiente, emisión anual, ventana -15/+15 y correo antes de considerarse terminada.
