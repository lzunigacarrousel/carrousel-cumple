(() => {
  'use strict';
  const esc = value => String(value ?? '').replaceAll('&','&amp;').replaceAll('<','&lt;').replaceAll('>','&gt;').replaceAll('"','&quot;').replaceAll("'",'&#039;');
  let page = 'panel', user = null, focusBefore = null, tour = null;
  const can = p => !!user?.permissions?.includes(p);
  const guides = {
    acceso: ['Acceso del personal', [['.auth-card-v2','Correo','Solicita un código al correo asignado a tu cuenta.'],['.auth-form-v2','Código','El código tiene seis dígitos, dura diez minutos y solo se usa una vez.']]],
    panel: ['Inicio', [['.metrics','Resumen','Consulta totales visibles para tu perfil.'],['.dashboard-quick-actions','Tareas','Los accesos muestran tus funciones autorizadas.']]],
    buscar: ['Buscar', [['#search-page-form','Buscar información','Busca un nombre, correo o código. Los resultados respetan los permisos y centros de tu cuenta.'],['.search-results','Resultados','Abre el cliente o cupón que necesitas consultar.']]],
    clientes: ['Clientes', [['#customers-search','Buscar','Busca clientes registrados visibles para tu cuenta.'],['.data-table','Ficha','Consulta contacto, centro y fecha de alta.']]],
    registro: ['Registrar cliente', [['#customer-form','Alta','Captura nombre, cumpleaños y correo. No se crea un cupón al registrar.'],['.program-terms','Consentimiento','Explica la emisión anual por correo, la vigencia, el canje único y la baja por personal. Otras promociones se autorizan por separado.'],['.account-context','Centro','El servidor toma el centro operativo de tu cuenta.']]],
    cupones: ['Cupones', [['#coupons-search','Filtros','Busca por nombre o código y filtra por estado.'],['.data-table','Estado','Abre un código para revisar vigencia y uso previo.']]],
    canjear: ['Validar y canjear', [['#lookup-form','Consultar','Escribe o escanea el código. La consulta no consume el regalo.'],['#lookup-result','Revisar','Comprueba estado, beneficio, vigencia y canje previo.'],['.confirm-box','Confirmar','Confirma el canje con el servidor y entrega el regalo solo cuando aparezca la confirmación. Un uso bloquea toda la red.']]],
    campanas: ['Campañas', [['.page-heading .btn','Crear o editar','Usa Nueva campaña para abrir el formulario, o Editar en una campaña existente.'],['.data-table','Campañas','Puedes crear varias y editar cada una.'],['#campaign-form','Beneficio','Define regalo, cantidad, condiciones y contenido del correo.']]],
    envios: ['Envíos', [['.real-details','Estado','Revisa configuración visible y última tarea.'],['.data-table','Cola','Aceptado por SMTP no equivale a entregado.']]],
    usuarios: ['Usuarios', [['.page-heading .btn','Dar acceso','Abre Nuevo usuario para asignar el correo de trabajo y el perfil.'],['.admin-user-toolbar','Encontrar una cuenta','Combina texto, rol y estado para filtrar la lista.'],['.data-table','Cuentas','Consulta roles, centros y estados. Usa Editar para abrir la ficha.'],['#user-form','Asignación','Define centro operativo y alcance de consulta.']]],
    roles: ['Roles y permisos', [['.page-heading .btn','Crear un rol','Abre Nuevo rol para definir un perfil con permisos específicos.'],['.data-table','Roles','ADMINISTRADOR es raíz protegida.'],['#role-form','Permisos','Marca las funciones permitidas.']]],
    centros: ['Centros de costo', [['.page-heading .btn','Mantener el catálogo','Abre Nuevo centro o edita uno existente desde la lista.'],['.data-table','Centros','Consulta el catálogo.'],['#center-form','Editar','Mantén código, nombre, parque y estado.']]],
    bitacora: ['Bitácora', [['.data-table','Eventos','Revisa responsable, fecha y operación.']]],
    cupon: ['Cupón público', [['.coupon-main','Regalo','Revisa beneficio, vigencia y condiciones.'],['.coupon-bottom','Código','Presenta el código o QR al personal.']]],
    manual: ['Manual', [['.manual-search','Buscar','Encuentra una tarea.'],['.manual-topic-filter','Filtrar','Selecciona un tema.']]]
  };
  function widget(active, currentUser) {
    page = guides[active] ? active : 'panel'; user = currentUser;
    const [title, steps] = guides[page];
    return `<button class="help-fab" type="button" data-help-open aria-label="Abrir ayuda" aria-controls="cumple-help-panel" aria-expanded="false">?</button><div class="help-backdrop" data-help-backdrop hidden></div><aside class="help-panel" id="cumple-help-panel" data-help-panel role="dialog" aria-modal="true" aria-labelledby="help-title" aria-hidden="true" hidden><div class="help-panel-head"><div><span class="help-kicker">Ayuda de esta pantalla</span><h2 id="help-title">${esc(title)}</h2></div><button class="help-close" type="button" data-help-close aria-label="Cerrar ayuda">×</button></div><p class="help-intro">Sigue estos pasos o inicia el tutorial.</p><ol class="help-steps">${steps.map(([, , text]) => `<li>${esc(text)}</li>`).join('')}</ol><div class="help-actions"><button class="btn btn-primary" data-tour-start>Iniciar tutorial</button>${user ? '<a class="btn btn-outline-secondary" href="#manual" data-help-close>Abrir manual</a>' : ''}</div><div class="help-shortcuts"><span><kbd>Esc</kbd> Cerrar</span></div></aside>`;
  }
  let sections = [];
  const section = (topic, title, entries, href) => {
    const id = 'manual-section-' + (sections.length + 1);
    sections.push({ id, title });
    const faq = title === 'Preguntas frecuentes';
    return `<section class="manual-section" id="${id}" data-manual-section data-manual-topic="${esc(topic)}"><div class="manual-section-head"><span>${String(sections.length).padStart(2,'0')}</span><div><h2>${esc(title)}</h2></div></div><div class="${faq ? 'manual-faq' : 'manual-cards'}">${entries.map(([heading,text]) => faq ? `<details><summary>${esc(heading)}</summary><div>${esc(text)}</div></details>` : `<article><strong>${esc(heading)}</strong><p>${esc(text)}</p></article>`).join('')}</div>${href ? `<p><a class="btn btn-outline-secondary btn-sm" href="#${href}">Abrir pantalla →</a></p>` : ''}</section>`;
  };
  function manual(currentUser) {
    user = currentUser; sections = [];
    const blocks = [section('acceso','Ingreso por correo',[['Solicitar código','Ingresa tu correo de trabajo. Si tienes una cuenta activa, recibirás un código de seis dígitos.'],['Verificar','El código dura diez minutos, admite hasta cinco intentos y solo se usa una vez.']])];
    if (can('customers.read')) blocks.push(section('operacion','Consultar clientes',[['Datos','Busca clientes y consulta cumpleaños, correo, centro y fecha de alta.'],['Estado','El personal autorizado puede activar o desactivar una ficha. Una ficha inactiva deja de participar en futuras emisiones.']], 'clientes'));
    if (can('customers.write') && currentUser.center_id) blocks.push(section('operacion','Registrar cliente',[['Alta independiente','Captura nombre, cumpleaños, correo, teléfono opcional y responsable si es menor. El formulario no crea un cupón.'],['Consentimiento','La inscripción incluye el envío por correo de los regalos anuales que correspondan. Máximo un regalo por campaña y año, con un canje por cupón. La vigencia es de 15 días antes a 15 días después del cumpleaños, inclusive. Para la baja, solicita apoyo al personal. Otras promociones se autorizan por separado.'],['Centro','El servidor toma el centro operativo de tu cuenta.'],['Regalo automático','Una tarea programada revisa campañas activas y envía regalos por correo cuando corresponde.']], 'registro'));
    if (can('coupons.read')) blocks.push(section('operacion','Consultar cupones',[['Estados','Busca y filtra disponibles, próximos, canjeados, vencidos o anulados.'],['Vigencia','Cada regalo vale desde 15 días antes hasta 15 días después del cumpleaños, inclusive.']], 'cupones'));
    if (can('coupons.redeem') && currentUser.center_id) blocks.push(section('operacion','Canjear un regalo',[['Consulta global','Escribe o escanea el código; comprueba estado y uso anterior. La consulta no registra canje.'],['Confirmación','Confirma primero con el servidor y entrega el regalo solo después de recibir la confirmación. El servidor guarda cuenta, centro y fecha; un uso bloquea toda la red. Sin conexión central no hay canje.'],['Centro','El centro de canje viene de tu cuenta; el centro de registro no limita el canje.']], 'canjear'));
    if (can('campaigns.manage')) blocks.push(section('administracion','Campañas',[['Varias campañas','Configura beneficio, tipo, cantidad, condiciones, asunto e introducción de correo, período y estado.'],['Tokens','La cantidad positiva es obligatoria para tokens y opcional para otros tipos.'],['Emisión','Borrador y pausada detienen nuevas emisiones. Los cupones existentes conservan sus datos. La ventana −15/+15 es fija.']], 'campanas'));
    if (can('mail.manage')) blocks.push(section('administracion','Envíos',[['Cola','Aceptado por SMTP no garantiza llegada al buzón.'],['Reintento','Un resultado incierto requiere revisión y pudo ser aceptado. Reintentar conserva el cupón.'],['Configuración','La interfaz muestra estado, remitente, servidor y URL. Las credenciales SMTP son privadas; el proceso programado del servidor envía los correos.']], 'envios'));
    if (can('users.manage')) blocks.push(section('administracion','Usuarios',[['Cuenta','Asigna nombre, correo, rol, estado y centro operativo fijo.'],['Consulta','El alcance por centros controla los datos consultables.']], 'usuarios'));
    if (can('roles.manage')) blocks.push(section('administracion','Roles y permisos',[['Matriz','Selecciona permisos explícitos.'],['Protección','ADMINISTRADOR no permite editar su matriz.']], 'roles'));
    if (can('centers.manage')) blocks.push(section('administracion','Centros de costo',[['Catálogo','Mantén código, nombre, parque y estado.']], 'centros'));
    if (can('audit.read')) blocks.push(section('administracion','Bitácora',[['Trazabilidad','Consulta quién realizó cada operación, qué entidad afectó y cuándo.']], 'bitacora'));
    blocks.push(section('ayuda','Preguntas frecuentes',[['¿Qué ocurre con los cumpleaños del 29 de febrero?','En un año no bisiesto, el cumpleaños de referencia es el 28 de febrero.'],['¿Por qué el cliente aún no recibe el correo?','Registrar un cliente no garantiza envío inmediato. El proceso programado revisa campañas activas y la ventana del cumpleaños antes de emitir. Administración puede consultar el estado en Envíos.'],['¿Puede canjear en un parque distinto al de registro?','Sí. El centro de registro no limita el canje. El personal consulta el estado central y confirma un único canje, que queda registrado con su centro operativo.'],['¿La vigencia es todo el mes calendario?','No. Empieza 15 días antes del cumpleaños y termina 15 días después, con ambos extremos incluidos.'],['¿Cómo cambio la apariencia?','El botón de modo oscuro conserva tu preferencia en este navegador.']]));
    const tasks = [
      ['customers.write','registro','Trabajo diario','Registrar un cliente','Inscribe al cliente y confirma su correo y cumpleaños.'],
      ['coupons.redeem','canjear','Entrega de regalos','Validar y canjear','Comprueba la vigencia y el uso en toda la red.'],
      ['customers.read','clientes','Seguimiento','Consultar clientes','Encuentra las fichas dentro de tu alcance.'],
      ['coupons.read','cupones','Seguimiento','Consultar cupones','Revisa beneficios, fechas y estado del regalo.'],
      ['campaigns.manage','campanas','Configuración','Preparar una campaña','Define el beneficio, las condiciones y el correo.'],
      ['mail.manage','envios','Seguimiento','Revisar envíos','Consulta la cola y los resultados del correo.'],
      ['users.manage','usuarios','Administración','Administrar accesos','Asigna el perfil y el centro operativo del personal.']
    ].filter(([permission,href]) => can(permission) && (!['registro','canjear'].includes(href) || currentUser.center_id));
    const operating = !!currentUser.center_id && (can('customers.write') || can('coupons.redeem'));
    const workspace = operating ? (can('customers.write') ? 'registro' : 'canjear') : can('campaigns.manage') ? 'campanas' : 'panel';
    const role = currentUser.role_name || currentUser.role_code || 'Personal';
    return `<section class="manual-page"><div class="page-heading"><div><span class="ticket-kicker">Guía de uso</span><h1 class="page-title">Manual</h1><p>Consulta las tareas y funciones disponibles para tu perfil.</p></div><button class="btn btn-outline-secondary" data-tour-start>Ver recorrido guiado</button></div><section class="manual-profile-card"><div><span class="manual-profile-kicker">Tu guía de trabajo</span><strong>Manual para ${esc(role)}</strong><p>${operating ? 'Registra clientes y entrega los regalos con validación central, según los permisos de tu cuenta.' : 'Supervisa la operación y administra las funciones habilitadas para tu cuenta.'}</p></div><span class="manual-profile-badge">${esc(currentUser.center_name || 'Alcance según tu cuenta')}</span></section><section class="manual-profile-guide" aria-label="Cómo empezar"><article><span>1</span><div><strong>Empieza aquí</strong><p>Revisa tu perfil y centro de operación. El menú muestra las funciones que tienes autorizadas.</p></div></article><article><span>2</span><div><strong>Tu espacio principal</strong><p>${operating ? 'Accede a tus tareas de registro y entrega desde el inicio.' : 'Revisa el resumen y las herramientas de gestión.'}</p><a href="#${workspace}">Abrir mi espacio →</a></div></article><article><span>3</span><div><strong>Después</strong><p>Consulta el seguimiento y abre la ayuda de cada pantalla cuando necesites orientación.</p></div></article></section><div class="manual-search" role="search"><input class="form-control" type="search" data-manual-search aria-label="Buscar en el manual" placeholder="Buscar: registrar, canjear, correo, campaña…"><button class="btn btn-outline-secondary" data-manual-clear>Limpiar</button><span class="manual-search-status" role="status" aria-live="polite">Busca una tarea o elige un tema</span></div><nav class="manual-topic-filter" aria-label="Filtrar manual por tema"><button class="manual-topic is-active" data-topic="all" aria-pressed="true">Todo</button><button class="manual-topic" data-topic="acceso" aria-pressed="false">Acceso</button>${operating || can('customers.read') || can('coupons.read') ? '<button class="manual-topic" data-topic="operacion" aria-pressed="false">Operación</button>' : ''}${blocks.some(b => b.includes('data-manual-topic="administracion"')) ? '<button class="manual-topic" data-topic="administracion" aria-pressed="false">Administración</button>' : ''}<button class="manual-topic" data-topic="ayuda" aria-pressed="false">Ayuda</button></nav><section class="manual-quick-start"><h2>¿Qué necesitas hacer?</h2><div class="manual-quick-grid">${tasks.map(([,href,category,title,text]) => `<a class="manual-quick-card" href="#${href}"><span>${category}</span><strong>${title}</strong><small>${text}</small></a>`).join('')}</div></section><nav class="manual-index" aria-label="Índice del manual">${sections.map(({id,title})=>`<a href="#manual" data-manual-jump="${id}">${esc(title)}</a>`).join('')}</nav>${blocks.join('')}<p class="manual-empty" hidden>No hay temas que coincidan.</p></section>`;
  }
  function filterManual() {
    const root = document.querySelector('.manual-page'); if (!root) return;
    const normalize = text => text.normalize('NFD').replace(/[\u0300-\u036f]/g,'').toLowerCase();
    const q = normalize(root.querySelector('[data-manual-search]').value.trim());
    const topic = root.querySelector('[data-topic][aria-pressed="true"]')?.dataset.topic || 'all';
    let count = 0;
    root.querySelectorAll('[data-manual-section]').forEach(el => {
      el.hidden = (topic !== 'all' && el.dataset.manualTopic !== topic) || !normalize(el.textContent).includes(q);
      if (!el.hidden) count++;
    });
    root.querySelectorAll('.manual-quick-card').forEach(el => { el.hidden = !normalize(el.textContent).includes(q); });
    root.querySelectorAll('[data-manual-jump]').forEach(el => { el.hidden = document.getElementById(el.dataset.manualJump)?.hidden; });
    root.querySelector('.manual-quick-start').hidden = ![...root.querySelectorAll('.manual-quick-card')].some(el => !el.hidden);
    root.querySelector('.manual-search-status').textContent = `${count} temas encontrados`;
    root.querySelector('.manual-empty').hidden = count > 0;
  }
  function close(restore = true) {
    document.querySelectorAll('.tour-highlight').forEach(el => el.classList.remove('tour-highlight'));
    document.querySelectorAll('.tour-overlay,.tour-popover').forEach(el => el.remove());
    document.body.classList.remove('tour-open'); tour = null;
    const panel = document.querySelector('[data-help-panel]');
    if (panel) { panel.hidden = true; panel.classList.remove('open'); panel.setAttribute('aria-hidden','true'); }
    const backdrop = document.querySelector('[data-help-backdrop]'); if (backdrop) backdrop.hidden = true;
    document.querySelectorAll('[data-help-open]').forEach(el => el.setAttribute('aria-expanded','false'));
    if (restore && focusBefore?.isConnected) focusBefore.focus();
  }
  function open(trigger) {
    focusBefore = trigger;
    const panel = document.querySelector('[data-help-panel]'); if (!panel) return;
    panel.hidden = false; panel.classList.add('open'); panel.setAttribute('aria-hidden','false');
    document.querySelector('[data-help-backdrop]').hidden = false;
    trigger.setAttribute('aria-expanded','true'); panel.querySelector('[data-help-close]').focus();
  }
  function step(index) {
    if (!tour) return;
    document.querySelectorAll('.tour-highlight').forEach(el => el.classList.remove('tour-highlight'));
    document.querySelector('.tour-popover')?.remove();
    tour.index = index;
    const [selector, heading, text] = tour.steps[index], target = document.querySelector(selector);
    if (!target) return close();
    target.scrollIntoView({ block:'center', behavior:'instant' }); target.classList.add('tour-highlight');
    const popup = document.createElement('section');
    popup.className = 'tour-popover show'; popup.setAttribute('role','dialog'); popup.setAttribute('aria-modal','true'); popup.tabIndex = -1;
    popup.innerHTML = `<button class="icon-button tour-close" data-tour-close aria-label="Cerrar tutorial">×</button><span class="tour-step-count">PASO ${index + 1} DE ${tour.steps.length}</span><h2>${esc(heading)}</h2><p>${esc(text)}</p><div class="tour-controls"><button class="btn btn-outline-secondary" data-tour-previous ${index === 0 ? 'disabled' : ''}>Anterior</button><button class="btn btn-primary" data-tour-next>${index === tour.steps.length - 1 ? 'Finalizar' : 'Siguiente'}</button></div>`;
    document.body.append(popup);
    const rect = target.getBoundingClientRect(), box = popup.getBoundingClientRect();
    popup.style.left = `${Math.max(12,Math.min(rect.left,innerWidth - box.width - 12))}px`;
    popup.style.top = `${rect.bottom + box.height + 12 < innerHeight ? rect.bottom + 12 : Math.max(12,rect.top - box.height - 12)}px`;
    popup.focus();
  }
  function startTour() {
    close(false);
    const steps = (guides[page]?.[1] || []).filter(([selector]) => {
      const el = document.querySelector(selector);
      return el && el.getClientRects().length && !el.closest('[hidden]');
    });
    if (!steps.length) return;
    tour = { steps, index:0 }; document.body.classList.add('tour-open');
    const overlay = document.createElement('div'); overlay.className = 'tour-overlay show'; document.body.append(overlay);
    step(0);
  }
  document.addEventListener('click', event => {
    const trigger = event.target.closest('[data-help-open]'); if (trigger) open(trigger);
    if (event.target.closest('[data-help-close],[data-help-backdrop],[data-tour-close]')) close();
    if (event.target.closest('[data-tour-start]')) startTour();
    if (event.target.closest('[data-tour-next]') && tour) tour.index + 1 === tour.steps.length ? close() : step(tour.index + 1);
    if (event.target.closest('[data-tour-previous]') && tour && tour.index > 0) step(tour.index - 1);
    const topic = event.target.closest('[data-topic]');
    const jump = event.target.closest('[data-manual-jump]');
    if (jump) { event.preventDefault(); document.getElementById(jump.dataset.manualJump)?.scrollIntoView({block:'start', behavior:'smooth'}); }
    if (topic) { document.querySelectorAll('[data-topic]').forEach(el => { const selected = el === topic; el.setAttribute('aria-pressed',String(selected)); el.classList.toggle('is-active',selected); }); filterManual(); }
    if (event.target.closest('[data-manual-clear]')) { const input = document.querySelector('[data-manual-search]'); if (input) { input.value = ''; filterManual(); input.focus(); } }
  });
  document.addEventListener('input', event => { if (event.target.matches('[data-manual-search]')) filterManual(); });
  document.addEventListener('keydown', event => {
    if (event.key === 'Escape') close();
    const dialog = document.querySelector('.tour-popover') || document.querySelector('[data-help-panel]:not([hidden])');
    if (!dialog || event.key !== 'Tab') return;
    const focusable = [...dialog.querySelectorAll('button:not(:disabled),a[href],input')].filter(el => el.getClientRects().length);
    if (!focusable.length) return;
    const first = focusable[0], last = focusable[focusable.length - 1];
    if (event.shiftKey && (document.activeElement === first || document.activeElement === dialog)) { event.preventDefault(); last.focus(); }
    else if (!event.shiftKey && (document.activeElement === last || document.activeElement === dialog)) { event.preventDefault(); first.focus(); }
  });
  window.addEventListener('resize', () => { if (tour) step(tour.index); });
  window.CumpleHelp = { widget, manual, close };
})();
