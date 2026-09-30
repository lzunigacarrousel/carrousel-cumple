# Base visual de Cumpleaños Carrousel

Actualización: 25 de septiembre de 2026.

La referencia principal es [Helpdesk Carrousel](https://github.com/FernandoZL/helpdesk-carrousel), consultada en `C:/xampp/htdocs/HelpdeskCarrousel` en modo de solo lectura. El diseño usa sus componentes y hojas reales. La aplicación de referencia no se modifica.

## Componentes reutilizados

Las 13 hojas de `public/assets/helpdesk/` conservan los archivos originales: `app.css`, `layout-fixes.css`, `ux-v2.css`, `ui-refresh.css`, `content-priority.css`, `shell-v2.css`, `components.css`, `visual-system.css`, `auth-v2.css`, `dark-refinement.css`, `help-tour-contrast.css`, `layout-density-v25.css` y `data-tables.css`. El inventario SHA-256 está en `source.json`. El logo corporativo también procede del Helpdesk.

`layout-density-v25.css` y `data-tables.css` cierran la cascada, como en `app/Views/shared/app_end.php`. `public/cumple.css` conserva las adaptaciones de cumpleaños y no redefine la identidad visual general.

| Pantalla | Referencia de Helpdesk | Adaptación en Cumple |
| --- | --- | --- |
| Acceso y código OTP | `app/Views/auth/login.php`, `otp.php` | Composición de dos paneles, marca, formulario, ayuda de acceso, código de seis dígitos y reenvío. |
| Menú y cabecera | `app/Views/shared/app_start.php` | Secciones por perfil, búsqueda global, tema, Portal, cuenta y salida. |
| Inicio | `app/Views/dashboard/index.php` | Resumen con tarjetas de actividad, acciones autorizadas y tabla de cupones. |
| Administración | `app/Views/admin/users.php` | Listas con filtros, formulario compacto abierto al crear o editar, Cancelar/Guardar. |
| Tablas | `public/assets/css/data-tables.css` | Contenedores comunes, cabecera y filas compactas; etiquetas de columna en móvil. |
| Manual | `app/Views/help/manual.php` | Guía por perfil, tres pasos iniciales, tareas, búsqueda, filtros, índice y FAQ desplegables. |
| Ayuda y recorridos | `app/Views/shared/help_widget.php`, `public/assets/js/help-tour.js` | Ayuda contextual y tutorial sobre controles reales, con foco, avance, regreso y cierre. |
| Pie de página | `app/Views/shared/app_end.php` | Firma corporativa Carrousel Guatemala y desarrollador. |

La interfaz usa los nombres y funciones de cumpleaños. Administración sin centro operativo no recibe accesos de registro/canje en el menú ni como tareas del manual. Mantiene consulta y gestión según permisos. No hay autorregistro público.

## Comprobación

La revisión de escritorio y móvil usa la base aislada `carrousel_cumple_test` y correo capturado, sin enviar correos reales. Los pasos y resultados están en `docs/verificacion-visual-helpdesk.md`.

El acceso real se comprobó visualmente frente al acceso local del Helpdesk. Se revisan también modo oscuro, filtros, edición, búsqueda global, manual y tutoriales. Los cambios se desarrollan aquí y se instalan con `tools/install-xampp.ps1`; no se edita únicamente la copia de Apache.

## Estado funcional

La aplicación actual es PHP/MariaDB con OTP, roles y centros, registro independiente, campañas configurables, emisión anual, cola SMTP y canje global atómico. La instalación y sus límites operativos están en `INSTALACION.md`; el contrato de datos está en `docs/api-real.md`. Las descripciones antiguas de la demo SQLite son antecedentes, no el funcionamiento vigente.
