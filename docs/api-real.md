# API Carrousel Cumple — contrato de implementación

Todas las llamadas a `api.php?action=...`. GET para lecturas; POST JSON y cabecera X-CSRF-Token para escrituras. Respuesta `{ok:true,data:...}` o `{ok:false,error:...}` y HTTP 4xx/5xx. Ningún endpoint demo ni login por contraseña.

GET bootstrap: `{csrf,user:null|{id,name,email,role_code,role_name,permissions:string[],center_id,center_name,center_code},today,app_url,mail_ready}`. Autorización por lista de permisos: customers.read/customers.write, coupons.read/coupons.redeem, campaigns.manage, mail.manage, users.manage, roles.manage, centers.manage, audit.read, reports.global. center_id es centro operativo fijo; perfiles globales sin centro no registran/canjean.

POST otp-request `{email}` -> `{message,challenge}` (respuesta genérica, no código); POST otp-verify `{challenge,code}` -> `{user,csrf}`. POST logout `{}` -> `{csrf}`. Refrescar bootstrap tras acceder.

GET dashboard `{stats:{customers,available,redeemed,pending_mail},coupons:Coupon[]}`. GET customers (`q` opcional) -> Customer[]. POST customer `{name,day,month,email,phone,guardian,is_minor,terms_accepted,marketing_opt_in,request_id}` -> `{customer,duplicate}`. Resultado **Cliente registrado**, explica recepción automática; nunca muestra cupón. POST customer-status `{id,active}`. Customer `{id,name,day,month,email,phone,guardian,is_minor,active,center_name,registered_by_name,created_at}`. El alta no acepta selector de centro.

GET coupons (`q,status` opcional) -> Coupon[]. GET lookup (`code`) -> Coupon. POST redeem `{code}` -> Coupon. POST void `{code,reason}` -> Coupon. GET coupon (`token`) -> Cupón público (sin contactos/cuenta). Coupon `{id,code,name,benefit_label,benefit_type,quantity,terms,valid_from,valid_until,status,center_name,registered_by_name,created_at,redeemed_center_name,redeemed_by_name,redeemed_at,void_reason}`. Estados available/upcoming/redeemed/expired/void. Canje: centro actual viene de user, origen no limita. Público incluye code/name/benefit_label/quantity/terms/valid_from/valid_until/status. QR contiene código.

GET campaigns -> Campaign[]. POST campaign `{id? (vacío=nueva),title,benefit_type,benefit_label,quantity,terms,email_subject,email_intro,start_date,end_date,status}` -> Campaign. Tipos tokens/product/discount/other; quantity requerida positiva para tokens, opcional resto. status draft/active/paused. No campaña inicial inventada. Fechas vacías=sin límite. Campaign mismos campos+id/version. Ventana siempre −15/+15, no editable.

GET mail -> `{items:[{id,code,recipient,status,attempts,last_error,created_at,sent_at}],health:{ready,from,host,public_url,worker_last_run}}`. Estados pending/processing/accepted/failed/uncertain/canceled. POST mail-retry `{id}` solo failed/uncertain, conserva código (advertir resultado incierto puede haber sido aceptado). POST run-issuance `{}` -> `{issued}` crea pendientes, no fuerza envío desde navegador. Correos se envían en tarea CLI.

GET users -> User[] `{id,name,email,role_id,role_name,center_id,center_name,active,scope_center_ids:[]}`. POST user `{id?,name,email,role_id,center_id,active,scope_center_ids:[]}`. Administración asigna centro operativo fijo y alcance de consulta por centros, sin moverlo en formularios operativos.
GET roles -> `{roles:[{id,code,name,permissions:string[],is_system}],permissions:{code:label}}`; POST role `{id?,code,name,permissions:[]}`. ADMINISTRADOR es raíz protegida; no se edita su matriz. GET centers -> `[{id,code,name,park_name,active}]`; POST center `{id?,code,name,park_name,active}`.
GET audit -> `[{id,actor_name,event,entity,entity_id,details,created_at}]`.

Menú por permisos: Inicio, Clientes, Registrar cliente, Cupones, Canjear cupón, Campañas, Envíos, Usuarios, Roles y permisos, Centros de costo, Bitácora, Manual. Mantener estilos Helpdesk, sin leyendas de demo ni ofrecimientos inventados. Ayuda y manual deben explicar funciones actuales, SMTP aceptado no es entrega al buzón. No exponer secretos SMTP. UI del correo muestra configuración y estado, configuración privada por archivo e instrucciones de instalación.
