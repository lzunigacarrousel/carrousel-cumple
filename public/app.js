(() => {
  'use strict';
  const app = document.querySelector('#app');
  if (!app) return;
  const state = { bootstrap: null, notice: null, challenge: '', accessEmail: '', lookup: null, requestId: null, scanner: null, serial: 0 };
  const esc = value => String(value ?? '').replaceAll('&', '&amp;').replaceAll('<', '&lt;').replaceAll('>', '&gt;').replaceAll('"', '&quot;').replaceAll("'", '&#039;');
  const date = value => value ? esc(String(value).slice(0, 10)) : '—';
  const dateTime = value => value ? esc(String(value).replace('T', ' ').slice(0, 19)) : '—';
  const yes = value => Number(value) === 1 || value === true || value === '1';
  const user = () => state.bootstrap?.user;
  const can = permission => !!user()?.permissions?.includes(permission);
  const hasCenter = () => !!user()?.center_id;
  const labels = {
    available: 'Disponible', upcoming: 'Próximo', redeemed: 'Canjeado', expired: 'Vencido', void: 'Anulado',
    draft: 'Borrador', active: 'Activo', inactive: 'Inactivo', paused: 'Pausado', pending: 'Pendiente', processing: 'En proceso',
    accepted: 'Aceptado por SMTP', failed: 'Fallido', uncertain: 'Resultado incierto', canceled: 'Cancelado'
  };
  const status = value => labels[value] || value || '—';
  const benefit = item => {
    const label = esc(item.benefit_label || '—');
    if (!item.quantity) return label;
    return item.benefit_type === 'tokens'
      ? `${esc(Number(item.quantity))} tokens · ${label}`
      : `${label} · cantidad: ${esc(Number(item.quantity))}`;
  };
  const newRequestId = () => {
    if (globalThis.crypto?.randomUUID) return crypto.randomUUID();
    const bytes = new Uint8Array(16);
    if (globalThis.crypto?.getRandomValues) crypto.getRandomValues(bytes);
    else for (let i = 0; i < bytes.length; i++) bytes[i] = Math.floor(Math.random() * 256);
    bytes[6] = (bytes[6] & 15) | 64;
    bytes[8] = (bytes[8] & 63) | 128;
    const hex = [...bytes].map(byte => byte.toString(16).padStart(2, '0')).join('');
    return `${hex.slice(0,8)}-${hex.slice(8,12)}-${hex.slice(12,16)}-${hex.slice(16,20)}-${hex.slice(20)}`;
  };
  const route = () => {
    const gift = new URLSearchParams(location.search).get('gift');
    const raw = location.hash.slice(1) || (gift ? `cupon?token=${encodeURIComponent(gift)}` : (user() ? 'panel' : 'acceso'));
    const [name, query = ''] = raw.split('?');
    return { name, params: new URLSearchParams(query) };
  };
  const url = (action, params = {}) => {
    const address = new URL('api.php', location.href);
    address.searchParams.set('action', action);
    Object.entries(params).forEach(([key, value]) => {
      if (value !== '' && value !== null && value !== undefined) address.searchParams.set(key, value);
    });
    return address;
  };
  async function api(action, { method = 'GET', body, params } = {}) {
    const init = { method, credentials: 'same-origin', headers: { Accept: 'application/json' } };
    if (method === 'POST') {
      init.headers['Content-Type'] = 'application/json';
      init.headers['X-CSRF-Token'] = state.bootstrap?.csrf || '';
      init.body = JSON.stringify(body || {});
    }
    let response;
    try { response = await fetch(url(action, params), init); }
    catch (_error) { throw new Error('No se pudo conectar con el registro central. Verifica la conexión y vuelve a consultar.'); }
    let payload;
    try { payload = await response.json(); }
    catch (_error) { throw new Error(`El servidor devolvió una respuesta inválida (${response.status}).`); }
    if (!response.ok || !payload?.ok) throw new Error(typeof payload?.error === 'string' ? payload.error : `La operación no pudo completarse (${response.status}).`);
    return payload.data;
  }
  function notice(message, type = 'error') {
    state.notice = { message, type };
    const slot = document.querySelector('#app-notice');
    if (slot) slot.innerHTML = noticeMarkup();
  }
  const noticeMarkup = () => state.notice ? `<div class="alert alert-${state.notice.type === 'success' ? 'success' : 'danger'} notice" role="${state.notice.type === 'error' ? 'alert' : 'status'}"><p>${esc(state.notice.message)}</p><button class="icon-button" type="button" data-dismiss-notice aria-label="Cerrar mensaje">×</button></div>` : '';
  const heading = (eyebrow, title, detail = '', actions = '') => `<div class="page-heading"><div><span class="ticket-kicker">${esc(eyebrow)}</span><h1 class="page-title">${esc(title)}</h1>${detail ? `<p class="page-subtitle">${esc(detail)}</p>` : ''}</div>${actions}</div>`;
  const empty = message => `<div class="empty-state"><strong>Sin registros</strong><p>${esc(message)}</p></div>`;
  const errPage = (title, message) => `<section class="card real-card"><h1 class="page-title">${esc(title)}</h1><p>${esc(message)}</p><button class="btn btn-outline-secondary" data-retry>Reintentar</button></section>`;
  const badge = value => `<span class="badge status--${esc(value)}">${esc(status(value))}</span>`;
  const option = (value, label, selected) => `<option value="${esc(value)}" ${String(value) === String(selected ?? '') ? 'selected' : ''}>${esc(label)}</option>`;
  const field = (label, name, value = '', attrs = '') => `<div class="field"><label class="form-label" for="f-${esc(name)}">${esc(label)}</label><input class="form-control" id="f-${esc(name)}" name="${esc(name)}" value="${esc(value)}" ${attrs}></div>`;
  const checkbox = (label, name, checked = false) => `<label class="choice"><input type="checkbox" name="${esc(name)}" ${checked ? 'checked' : ''}><span>${esc(label)}</span></label>`;
  const table = (headers, rows, emptyText) => rows.length
    ? `<div class="data-table-shell"><div class="data-table-wrap"><table class="data-table"><thead><tr>${headers.map(h => `<th scope="col">${esc(h)}</th>`).join('')}</tr></thead><tbody>${rows.map(row => `<tr>${row.map((cell, i) => `<td data-label="${esc(headers[i])}">${cell}</td>`).join('')}</tr>`).join('')}</tbody></table></div></div>`
    : empty(emptyText);
  function shell(content, active = 'panel') {
    const current = user();
    const helper = window.CumpleHelp?.widget(active, current) || '';
    const footer = `<footer class="app-corporate-footer"><div>© ${new Date().getFullYear()} <strong>Carrousel Guatemala ✨🎠</strong> · Desarrollado por <strong>Luis Fernando Zuniga</strong></div></footer>`;
    document.body.classList.toggle('auth-body-v2', !current && active === 'acceso');
    if (!current) return `<div class="brand-strip"></div><div class="public-shell"><header class="public-header"><a href="#acceso"><img src="assets/logo-carrousel.png" alt="Corporación Carrousel"></a><div class="public-actions"><button class="btn btn-outline-secondary theme-btn" data-theme-toggle aria-label="Cambiar apariencia">◐</button><a class="btn btn-outline-secondary" href="#acceso">Acceso del personal</a></div></header><div id="app-notice">${noticeMarkup()}</div>${content}${footer}</div>${helper}`;
    const pages = [
      ['panel', '⌂', 'Inicio', null],
      ['buscar', '⌕', 'Buscar', null],
      ['clientes', '♙', 'Clientes', 'customers.read'],
      ['registro', '＋', 'Registrar cliente', 'customers.write'],
      ['cupones', '▤', 'Cupones', 'coupons.read'],
      ['canjear', '⌗', 'Canjear cupón', 'coupons.redeem'],
      ['campanas', '⚙', 'Campañas', 'campaigns.manage'],
      ['envios', '✉', 'Envíos', 'mail.manage'],
      ['usuarios', '♟', 'Usuarios', 'users.manage'],
      ['roles', '◇', 'Roles y permisos', 'roles.manage'],
      ['centros', '⌂', 'Centros de costo', 'centers.manage'],
      ['bitacora', '◉', 'Bitácora', 'audit.read'],
      ['manual', '?', 'Manual', null]
    ];
    const link = ([id, icon, label, permission]) => (!permission || can(permission)) && (!['registro','canjear'].includes(id) || hasCenter())
      ? `<a class="side-link ${active === id ? 'active' : ''}" href="#${id}" ${active === id ? 'aria-current="page"' : ''}><span class="side-icon" aria-hidden="true">${icon}</span><span class="side-label">${label}</span></a>`
      : '';
    const title = pages.find(item => item[0] === active)?.[2] || 'Cupón';
    const groups = [['Inicio',['panel','buscar']],['Clientes y regalos',['clientes','registro','cupones','canjear']],['Gestión',['campanas','envios','bitacora']],['Administración',['usuarios','roles','centros']],['Ayuda',['manual']]];
    const navigation = groups.map(([name, ids]) => {
      const links = pages.filter(([id]) => ids.includes(id)).map(link).join('');
      return links ? `<div class="nav-section">${name}</div>${links}` : '';
    }).join('');
    const searchValue = active === 'buscar' ? route().params.get('q') || '' : '';

    return `<div class="brand-strip"></div><div class="app-shell"><aside class="sidebar" id="app-sidebar" aria-label="Navegación principal"><div class="sidebar-brand"><img src="assets/logo-carrousel.png" alt="Carrousel"><div><strong>Cumpleaños</strong><div class="small">Corporación Carrousel</div></div></div><nav>${navigation}<button class="side-link side-link-button" data-help-open><span class="side-icon" aria-hidden="true">?</span><span class="side-label">Ayuda de esta pantalla</span></button></nav></aside><div class="sidebar-backdrop" data-sidebar-backdrop hidden></div><div class="main-wrap"><header class="topbar"><div class="topbar-left"><button class="btn btn-outline-secondary sidebar-toggle" data-sidebar-toggle aria-controls="app-sidebar" aria-label="Mostrar u ocultar menú">☰</button><a class="topbar-title" href="#panel"><span class="topbar-section">Cumpleaños</span><strong class="topbar-page">${esc(title)}</strong></a></div><form class="topbar-search" id="global-search" role="search"><span class="topbar-search-icon" aria-hidden="true">⌕</span><input type="search" name="q" value="${esc(searchValue)}" placeholder="Buscar cliente o cupón…" aria-label="Buscar en Cumpleaños" autocomplete="off"><kbd>Ctrl K</kbd></form><div class="topbar-user"><button class="btn btn-outline-secondary btn-sm theme-btn shell-icon-btn" data-theme-toggle aria-label="Cambiar apariencia">◐</button><a class="btn btn-outline-secondary btn-sm portal-btn" href="https://portal.carrousel-apps.com/" title="Volver al Portal">← Portal</a><div class="text-end user-summary"><strong>${esc(current.name)}</strong><div class="small subtle">${esc(current.role_name || current.role_code)} · ${esc(current.email || '')}</div></div><button class="btn btn-outline-secondary btn-sm logout-btn" data-logout>Salir</button></div></header><main class="content"><div id="app-notice">${noticeMarkup()}</div>${content}${footer}</main></div></div>${helper}`;
  }
  function accessPage() {
    document.body.classList.add('auth-body-v2');
    const verifying = !!state.challenge;
    const form = verifying
      ? `<form id="otp-verify-form" class="auth-form-v2"><label for="otp-code">Código de 6 dígitos<input class="form-control otp-input-v2" id="otp-code" name="code" inputmode="numeric" autocomplete="one-time-code" pattern="[0-9]{6}" maxlength="6" placeholder="000000" required autofocus></label><button class="btn btn-primary auth-primary" type="submit">Ingresar</button></form>`
      : `<form id="otp-request-form" class="auth-form-v2"><label for="otp-email">Correo electrónico<input class="form-control" id="otp-email" name="email" type="email" autocomplete="email" value="${esc(state.accessEmail)}" placeholder="nombre@correo.com" maxlength="160" required autofocus></label><button class="btn btn-primary auth-primary" type="submit">Continuar</button></form>`;
    return `<div class="brand-strip"></div><main class="auth-layout-v2"><section class="auth-brand-v2" aria-label="Cumpleaños Carrousel"><div class="auth-brand-content"><img class="auth-brand-logo" src="assets/logo-carrousel.png" alt="Corporación Carrousel"><span class="auth-product-pill">Cumpleaños Carrousel</span><h1>${verifying ? 'Acceso seguro sin contraseñas permanentes.' : 'Regalos de cumpleaños con seguimiento en toda la red.'}</h1><div class="auth-points-v2">${(verifying ? ['OTP de 6 dígitos','Acceso temporal','Sesión segura'] : ['Clientes','Regalos','Canjes']).map(text => `<span>${text}</span>`).join('')}</div></div></section><section class="auth-panel-v2"><div class="auth-card-v2"><div class="auth-mobile-brand-v2"><img src="assets/logo-carrousel.png" alt="Corporación Carrousel"><strong>Cumpleaños Carrousel</strong></div><span class="auth-eyebrow">${verifying ? 'Verificación' : 'Acceso seguro'}</span><h2>${verifying ? 'Escribe tu código' : 'Ingresa a Cumpleaños'}</h2><p class="auth-lead">${verifying ? `Si tu cuenta está habilitada, recibirás el código en <strong>${esc(state.accessEmail)}</strong>.` : 'Te enviaremos un código temporal a tu correo.'}</p><div id="app-notice">${noticeMarkup()}</div>${form}<div class="auth-helper-v2"><span>i</span><div>${verifying ? 'Si expiró o no llegó, solicita uno nuevo.' : '<strong>¿Aún no tienes acceso?</strong> Administración debe habilitar tu cuenta y asignar tu perfil y centro de operación.'}</div></div><p class="auth-security-v2">El código OTP es temporal. No lo compartas.</p><div class="auth-actions-v2"><div class="auth-action-group">${verifying ? '<button class="btn btn-outline-secondary btn-sm" type="button" data-otp-back>Cambiar correo</button><button class="btn btn-outline-secondary btn-sm" type="button" data-otp-resend>Reenviar código</button>' : '<a class="btn btn-outline-secondary btn-sm" href="https://portal.carrousel-apps.com/">← Portal</a>'}</div><button class="btn btn-outline-secondary btn-sm theme-btn" type="button" data-theme-toggle aria-label="Cambiar apariencia"><span data-theme-icon>◐</span></button></div></div></section></main>`;
  }
  function customerForm() {
    if (!hasCenter()) return shell(errPage('Se requiere un centro operativo', 'Tu cuenta no tiene un centro asignado. Solicita a administración que revise tu acceso.'), 'registro');
    return shell(`${heading('Clientes', 'Registrar cliente', 'El alta inscribe al cliente. Los regalos se emiten automáticamente cuando corresponde.')}
      <div class="registration-layout"><section class="card real-card"><form id="customer-form">
      ${field('Nombre completo', 'name', '', 'required maxlength="120" autocomplete="name"')}
      <fieldset class="birthday-fields"><legend>Cumpleaños</legend><div class="field"><label class="form-label" for="f-day">Día</label><select class="form-control" id="f-day" name="day" required><option value="">Día</option>${Array.from({length:31}, (_, i) => option(i + 1, i + 1, '')).join('')}</select></div><div class="field"><label class="form-label" for="f-month">Mes</label><select class="form-control" id="f-month" name="month" required><option value="">Mes</option>${['Enero','Febrero','Marzo','Abril','Mayo','Junio','Julio','Agosto','Septiembre','Octubre','Noviembre','Diciembre'].map((m, i) => option(i + 1, m, '')).join('')}</select></div></fieldset>
      <div class="field-row">${field('Correo electrónico', 'email', '', 'type="email" required maxlength="160" autocomplete="email"')}${field('Teléfono (opcional)', 'phone', '', 'type="tel" maxlength="30" autocomplete="tel"')}</div>
      ${checkbox('El cliente es menor de edad', 'is_minor')}<div class="guardian-field" hidden>${field('Adulto responsable', 'guardian', '', 'maxlength="120"')}</div>
      <div class="account-context"><strong>${esc(user().center_name)}</strong><p>El servidor registra este centro y tu cuenta. Los regalos emitidos se pueden canjear en cualquier parque autorizado.</p></div>
      <div class="program-terms"><strong>Condiciones del programa</strong><p>El registro permite recibir por correo los regalos anuales de campañas activas que correspondan al cliente. Se emite como máximo un regalo por campaña y año; cada cupón admite un solo canje en toda la red. Su vigencia abarca 15 días antes y 15 días después del cumpleaños, ambos extremos incluidos. Para dar de baja el registro, solicita apoyo al personal.</p></div>
      ${checkbox('Acepto inscribirme al programa y recibir por correo los regalos anuales que me correspondan', 'terms_accepted')}${checkbox('Acepto recibir otras promociones (opcional)', 'marketing_opt_in')}
      <button class="btn btn-primary" type="submit">Registrar cliente</button></form></section>
      <aside class="data-card registration-note"><h2>Después del registro</h2><p>El proceso programado evalúa campañas activas y envía el regalo al correo cuando corresponde. Este formulario no genera un cupón.</p><p>La vigencia de cada regalo es de 15 días antes a 15 días después del cumpleaños, inclusive. Un solo canje en toda la red.</p></aside></div>`, 'registro');
  }
  function customerSuccess(result) {
    const customer = result.customer || result;
    return shell(`<section class="card real-card"><span class="result-check">✓</span>${heading('Clientes', 'Cliente registrado')}<p>${result.duplicate ? 'Ya existía el registro de este cliente. Se muestra la ficha existente.' : 'La ficha quedó registrada para el programa de cumpleaños.'}</p><p>Los regalos de campañas activas se emiten automáticamente cuando corresponda y se envían por correo electrónico.</p><p><strong>${esc(customer.name || '')}</strong> · ${esc(customer.email || '')}</p><div class="real-actions"><a class="btn btn-primary" href="#registro">Registrar otro cliente</a>${can('customers.read') ? '<a class="btn btn-outline-secondary" href="#clientes">Ver clientes</a>' : ''}</div></section>`, 'registro');
  }
  async function dashboardPage() {
    const data = await api('dashboard'), stats = data.stats || {};
    const metric = (title, value, href, detail) => `<a class="dashboard-action-card" href="#${href}"><span>${title}</span><strong>${Number(value || 0)}</strong><small>${detail}</small></a>`;
    const actions = [['registro','Registrar cliente','customers.write'],['canjear','Canjear cupón','coupons.redeem'],['campanas','Campañas','campaigns.manage']].filter(([id, , permission]) => can(permission) && (id === 'campanas' || hasCenter()));
    const cards = [can('customers.read') ? metric('Clientes registrados',stats.customers,'clientes','En tu alcance de consulta') : '',can('coupons.read') ? metric('Regalos disponibles',stats.available,'cupones?status=available','Vigentes y sin canjear') : '',can('coupons.read') ? metric('Regalos canjeados',stats.redeemed,'cupones?status=redeemed','Entrega confirmada') : '',can('mail.manage') ? metric('Correos pendientes',stats.pending_mail,'envios','Pendientes o por revisar') : ''].join('');
    return shell(`<div class="dashboard-page"><div class="dashboard-primary-row">${heading('Cumpleaños', 'Centro de trabajo')}<div class="dashboard-quick-actions">${actions.map(([id,text],index)=>`<a class="btn ${index===0?'btn-primary':'btn-outline-secondary'}" href="#${id}">${text}</a>`).join('')}</div></div><section class="dashboard-action-section"><div class="dashboard-section-head"><div><span class="ticket-kicker">${esc(user().center_name || 'Vista general')}</span><h2>Resumen de la operación</h2></div></div><div class="dashboard-action-grid metrics">${cards}</div></section><section class="card dashboard-activity-card"><div class="dashboard-section-head compact"><div><span class="ticket-kicker">Seguimiento</span><h2>Cupones recientes</h2></div>${can('coupons.read')?'<a href="#cupones">Ver cupones →</a>':''}</div>${table(['Código','Cliente','Beneficio','Estado','Vigencia'],(data.coupons||[]).map(c=>[`<a href="#consulta?code=${encodeURIComponent(c.code)}">${esc(c.code)}</a>`,esc(c.name),benefit(c),badge(c.status),`${date(c.valid_from)} a ${date(c.valid_until)}`]),'Los regalos aparecerán cuando se emitan para tus clientes.')}</section></div>`, 'panel');
  }
  async function searchPage(params) {
    const q = params.get('q') || '';
    let content = '<div class="empty-state">Escribe un nombre, correo o código para buscar.</div>';
    if(q) {
      const [customers,coupons] = await Promise.all([can('customers.read') ? api('customers',{params:{q}}) : [],can('coupons.read') ? api('coupons',{params:{q}}) : []]);
      content = `${can('customers.read')?`<section class="card real-card"><h2>Clientes</h2>${table(['Nombre','Correo','Centro'],customers.map(c=>[`<a href="#clientes?q=${encodeURIComponent(c.name)}">${esc(c.name)}</a>`,esc(c.email),esc(c.center_name)]),'No hay clientes con esa búsqueda.')}</section>`:''}${can('coupons.read')?`<section class="card real-card"><h2>Cupones</h2>${table(['Código','Cliente','Estado'],coupons.map(c=>[`<a href="#consulta?code=${encodeURIComponent(c.code)}">${esc(c.code)}</a>`,esc(c.name),badge(c.status)]),'No hay cupones con esa búsqueda.')}</section>`:''}`;
    }
    return shell(`${heading('Consulta','Buscar','Los resultados respetan el alcance de tu cuenta.')}<form id="search-page-form" class="filters"><input class="form-control" name="q" aria-label="Nombre, correo o código" value="${esc(q)}" placeholder="Nombre, correo o código" required><button class="btn btn-primary">Buscar</button></form><div class="search-results">${content}</div>`,'buscar');
  }
  async function customersPage(params) {
    const q = params.get('q') || '';
    const items = await api('customers', { params: { q } });
    const rows = items.map(c => [esc(c.name), `${esc(c.day)}/${esc(c.month)}`, esc(c.email), esc(c.phone || '—'), esc(c.center_name || '—'), dateTime(c.created_at),
      `${badge(yes(c.active) ? 'active' : 'inactive')} ${can('customers.write') ? `<button class="btn btn-outline-secondary btn-sm" data-customer-status="${esc(c.id)}" data-active="${yes(c.active) ? '0' : '1'}">${yes(c.active) ? 'Desactivar' : 'Activar'}</button>` : ''}`]);
    return shell(`${heading('Clientes', 'Clientes', 'Consulta los registros permitidos para tu perfil. Hasta 500 resultados; usa la búsqueda para acotar.', can('customers.write') ? '<a class="btn btn-primary" href="#registro">Registrar cliente</a>' : '')}<form class="filters" id="customers-search"><input class="form-control" type="search" name="q" value="${esc(q)}" placeholder="Buscar cliente"><button class="btn btn-outline-secondary">Buscar</button></form><section class="data-card">${table(['Cliente','Cumpleaños','Correo','Teléfono','Centro de registro','Registrado','Estado'], rows, 'No hay clientes para esta búsqueda.')}</section>`, 'clientes');
  }
  async function couponsPage(params) {
    const q = params.get('q') || '', filter = params.get('status') || '';
    const items = await api('coupons', { params: { q, status: filter } });
    return shell(`${heading('Regalos', 'Cupones', 'Consulta los regalos y su estado en la red. Hasta 500 resultados; usa los filtros para acotar.')}<form class="filters" id="coupons-search"><input class="form-control" type="search" name="q" value="${esc(q)}" placeholder="Nombre o código"><select class="form-select" name="status">${option('', 'Todos los estados', filter)}${['available','upcoming','redeemed','expired','void'].map(s => option(s, status(s), filter)).join('')}</select><button class="btn btn-outline-secondary">Filtrar</button></form><section class="data-card">${table(['Código','Cliente','Beneficio','Vigencia','Estado','Centro de registro','Centro de canje'], items.map(c => [`<a href="#consulta?code=${encodeURIComponent(c.code)}">${esc(c.code)}</a>`,esc(c.name),benefit(c),`${date(c.valid_from)} a ${date(c.valid_until)}`,badge(c.status),esc(c.center_name || '—'),esc(c.redeemed_center_name || '—')]), 'No hay cupones con esos filtros.')}</section>`, 'cupones');
  }
  const details = pairs => `<dl class="real-details">${pairs.map(([label, value]) => `<div><dt>${esc(label)}</dt><dd>${value}</dd></div>`).join('')}</dl>`;
  function lookupMarkup(c) {
    const current = user();
    const redeemable = c.status === 'available' && can('coupons.redeem') && hasCenter();
    return `<div class="lookup-header"><strong>${esc(c.code)}</strong>${badge(c.status)}</div><h2>${esc(c.name)}</h2><div class="lookup-benefit"><small>Beneficio</small><strong>${benefit(c)}</strong></div>${details([
      ['Vigencia', `${date(c.valid_from)} a ${date(c.valid_until)}`], ['Condiciones', esc(c.terms || '—')],
      ['Centro de registro', esc(c.center_name || '—')], ['Registrado por', esc(c.registered_by_name || '—')],
      ['Fecha de registro', dateTime(c.registered_at)], ['Centro de canje', esc(c.redeemed_center_name || '—')],
      ['Canjeado por', esc(c.redeemed_by_name || '—')], ['Fecha de canje', dateTime(c.redeemed_at)],
      ...(c.void_reason ? [['Motivo de anulación', esc(c.void_reason)]] : [])
    ])}${redeemable ? `<div class="confirm-box"><p>Confirma primero el canje con el servidor. Entrega el regalo solo cuando aparezca la confirmación. Se registrará en <strong>${esc(current.center_name)}</strong> y bloqueará otros canjes en toda la red.</p><button class="btn btn-primary button--wide" data-redeem="${esc(c.code)}">Confirmar canje</button></div>` : `<p class="state-explanation">Este cupón no está disponible para canje${!hasCenter() ? ' con una cuenta sin centro operativo' : ''}. La consulta no registra uso.</p>`}${can('campaigns.manage') && c.status !== 'void' && c.status !== 'redeemed' ? `<details class="void-coupon"><summary>Anular cupón</summary><form id="void-form" data-code="${esc(c.code)}"><div class="field"><label for="void-reason">Motivo de anulación</label><textarea class="form-control" id="void-reason" name="reason" maxlength="500" required></textarea></div><button class="btn btn-danger">Confirmar anulación</button></form></details>` : ''}`;
  }
  function redeemPage(code = '') {
    const available = can('coupons.redeem') && hasCenter();
    return shell(`${heading('Operación', 'Consultar y canjear', 'La consulta valida el estado global; solo la confirmación registra el canje.')}<div class="redeem-grid"><section class="card redeem-search"><h2>Consultar código</h2><p>El centro de canje es ${esc(user().center_name || 'sin asignar')}.</p><form id="lookup-form" class="lookup-control"><input class="form-control" name="code" value="${esc(code)}" required maxlength="100" autocomplete="off" aria-label="Código de cupón" placeholder="Código del cupón"><button class="btn btn-primary">Consultar</button></form><button class="btn btn-outline-secondary" data-start-camera>Leer QR con cámara</button><p id="camera-message" class="camera-message">También puedes escribir el código.</p><div id="camera-stage" class="camera-stage" hidden><video muted playsinline></video><button class="btn btn-outline-secondary" data-stop-camera>Detener cámara</button></div>${!available ? '<p class="state-explanation">Tu cuenta puede consultar, pero requiere permiso de canje y centro operativo para confirmar.</p>' : ''}</section><section class="card lookup-result" id="lookup-result">${state.lookup && state.lookup.code === code ? lookupMarkup(state.lookup) : '<div class="lookup-placeholder"><span>⌕</span><h3>Esperando código</h3><p>Consulta el cupón para revisar su vigencia, condiciones y uso previo.</p></div>'}</section></div>`, 'canjear');
  }
  async function publicCoupon(token) {
    const c = await api('coupon', { params: { token } });
    const content = `<section class="coupon-page"><div class="celebration-copy"><span class="ticket-kicker">Carrousel Cumple</span><h1>Tu regalo de cumpleaños</h1><p>Presenta este código en un parque Carrousel. El personal confirmará su vigencia y uso previo.</p></div><article class="coupon"><div class="coupon-top"><strong>CARROUSEL CUMPLE</strong>${badge(c.status)}</div><div class="coupon-main"><span class="coupon-kicker">PARA</span><h2>${esc(c.name)}</h2><div class="coupon-benefit"><small>REGALO</small><strong>${benefit(c)}</strong></div><div class="coupon-details"><div><small>VÁLIDO</small><strong>${date(c.valid_from)} a ${date(c.valid_until)}</strong></div><div><small>CANJE</small><strong>Un solo uso en toda la red</strong></div></div></div><div class="coupon-bottom"><div class="qr-box" id="coupon-qr"></div><div class="coupon-code"><small>CÓDIGO</small><strong>${esc(c.code)}</strong><p>El QR contiene este código.</p></div></div><div class="coupon-terms"><strong>Condiciones</strong><p class="campaign-terms-text">${esc(c.terms || '')}</p></div><button class="btn btn-primary button--wide print-button" data-print>Imprimir o guardar PDF</button></article></section>`;
    app.innerHTML = shell(content, 'cupon');
    const target = document.querySelector('#coupon-qr');
    if (target && window.QRCode) new window.QRCode(target, { text: String(c.code), width: 138, height: 138 });
  }
  async function campaignsPage(params) {
    const items = await api('campaigns');
    const editing = items.find(c => String(c.id) === params.get('id')) || null;
    const c = editing || {};
    const rows = items.map(item => [esc(item.title), benefit(item), badge(item.status), `${date(item.start_date)} a ${date(item.end_date)}`, esc(item.version || '—'), `<a class="btn btn-outline-secondary btn-sm" href="#campanas?id=${encodeURIComponent(item.id)}">Editar</a>`]);
    return shell(`${heading('Administración', 'Campañas', 'Puedes mantener varias campañas. Los cupones emitidos conservan el beneficio original.', '<a class="btn btn-primary" href="#campanas?new=1">Nueva campaña</a>')}<section class="data-card">${table(['Campaña','Beneficio','Estado','Período','Versión','Acción'], rows, 'Todavía no hay campañas configuradas.')}</section><section class="card real-card real-form-section"><h2>${editing ? 'Editar campaña' : 'Nueva campaña'}</h2><form id="campaign-form"><input type="hidden" name="id" value="${esc(c.id || '')}">${field('Título', 'title', c.title, 'required maxlength="120"')}<div class="field-row"><div class="field"><label class="form-label" for="f-benefit_type">Tipo de regalo</label><select class="form-control" id="f-benefit_type" name="benefit_type" required>${[['tokens','Tokens de juego'],['product','Producto'],['discount','Descuento'],['other','Otro']].map(([v,l]) => option(v,l,c.benefit_type || '')).join('')}</select></div>${field('Cantidad', 'quantity', c.quantity, 'type="number" min="1" step="1"')}</div><p class="field-help">La cantidad es obligatoria para tokens y opcional para otros tipos.</p>${field('Descripción del beneficio', 'benefit_label', c.benefit_label, 'required maxlength="160"')}<div class="field"><label class="form-label" for="f-terms">Condiciones</label><textarea class="form-control" id="f-terms" name="terms" rows="4" required>${esc(c.terms || '')}</textarea></div>${field('Asunto del correo', 'email_subject', c.email_subject, 'required maxlength="160"')}<div class="field"><label class="form-label" for="f-email_intro">Introducción del correo</label><textarea class="form-control" id="f-email_intro" name="email_intro" rows="3" required>${esc(c.email_intro || '')}</textarea></div><div class="field-row">${field('Inicio (opcional)', 'start_date', c.start_date, 'type="date"')}${field('Fin (opcional)', 'end_date', c.end_date, 'type="date"')}</div><div class="field"><label class="form-label" for="f-status">Estado</label><select class="form-control" id="f-status" name="status">${[['draft','Borrador'],['active','Activa'],['paused','Pausada']].map(([v,l]) => option(v,l,c.status || 'draft')).join('')}</select></div><p class="field-help">La ventana de cada cumpleaños es fija: 15 días antes y 15 días después, ambos incluidos.</p><button class="btn btn-primary">Guardar campaña</button></form></section>`, 'campanas');
  }
  async function mailPage() {
    const data = await api('mail');
    const h = data.health || {};
    const rows = (data.items || []).map(m => [esc(m.code), esc(m.recipient), badge(m.status), esc(m.attempts || 0), esc(m.last_error || '—'), dateTime(m.sent_at || m.created_at), ['failed','uncertain'].includes(m.status) ? `<button class="btn btn-outline-secondary btn-sm" data-mail-retry="${esc(m.id)}" data-mail-status="${esc(m.status)}">Reintentar</button>` : '—']);
    return shell(`${heading('Administración', 'Envíos', 'La emisión y el correo se procesan en tareas del servidor.')}
      <section class="card real-card"><h2>Estado de correo</h2>${details([['Disponibilidad', h.ready ? 'Configurado' : 'Requiere configuración'], ['Remitente', esc(h.from || '—')], ['Servidor', esc(h.host || '—')], ['URL pública', esc(h.public_url || '—')], ['Última tarea', dateTime(h.worker_last_run)]])}<p class="field-help">Las credenciales SMTP se configuran en archivos privados durante la instalación. “Aceptado por SMTP” no garantiza entrega en el buzón.</p><button class="btn btn-outline-secondary" data-run-issuance>Ejecutar emisión pendiente</button><p class="field-help">Esta acción crea pendientes. El proceso programado del servidor envía los correos de la cola.</p></section><section class="card real-card"><h2>Cola de correo</h2>${table(['Cupón','Destinatario','Estado','Intentos','Último error','Fecha','Acción'], rows, 'No hay envíos en la cola.')}</section>`, 'envios');
  }
  async function usersPage(params) {
    const [items, rolesData, centers] = await Promise.all([api('users'), api('roles'), api('centers')]);
    const roles = rolesData.roles || [];
    const u = items.find(item => String(item.id) === params.get('id')) || {};
    const scope = (u.scope_center_ids || []).map(String);
    return shell(`${heading('Administración', 'Usuarios', 'El centro operativo se asigna aquí y se valida en el servidor.', '<a class="btn btn-primary" href="#usuarios?new=1">Nuevo usuario</a>')}<section class="data-card">${table(['Nombre','Correo','Rol','Centro operativo','Estado','Acción'], items.map(item => [esc(item.name),esc(item.email),esc(item.role_name),esc(item.center_name || '—'),badge(yes(item.active) ? 'active' : 'inactive'),`<a class="btn btn-outline-secondary btn-sm" href="#usuarios?id=${encodeURIComponent(item.id)}">Editar</a>`]), 'Todavía no hay usuarios.')}</section><section class="card real-card real-form-section"><h2>${u.id ? 'Editar usuario' : 'Nuevo usuario'}</h2><form id="user-form"><input type="hidden" name="id" value="${esc(u.id || '')}">${field('Nombre', 'name', u.name, 'required maxlength="120"')}${field('Correo', 'email', u.email, 'type="email" required maxlength="160"')}<div class="field-row"><div class="field"><label class="form-label" for="f-role_id">Rol</label><select class="form-control" id="f-role_id" name="role_id" required><option value="">Seleccionar</option>${roles.map(r => option(r.id,r.name,u.role_id)).join('')}</select></div><div class="field"><label class="form-label" for="f-center_id">Centro operativo</label><select class="form-control" id="f-center_id" name="center_id"><option value="">Sin centro operativo</option>${centers.map(center => option(center.id,center.name,u.center_id)).join('')}</select></div></div><fieldset class="real-check-list"><legend>Alcance de consulta por centros</legend>${centers.map(center => `<label class="choice"><input type="checkbox" name="scope_center_ids" value="${esc(center.id)}" ${scope.includes(String(center.id)) ? 'checked' : ''}><span>${esc(center.name)}</span></label>`).join('')}</fieldset>${checkbox('Cuenta activa', 'active', u.id ? yes(u.active) : true)}<button class="btn btn-primary">Guardar usuario</button></form></section>`, 'usuarios');
  }
  async function rolesPage(params) {
    const data = await api('roles');
    const roles = data.roles || [], permissions = data.permissions || {};
    const r = roles.find(item => String(item.id) === params.get('id')) || {};
    const protectedRoot = r.code === 'ADMINISTRADOR';
    return shell(`${heading('Administración', 'Roles y permisos', 'La matriz de permisos determina las funciones disponibles.', '<a class="btn btn-primary" href="#roles?new=1">Nuevo rol</a>')}<section class="data-card">${table(['Código','Nombre','Permisos','Acción'], roles.map(item => [esc(item.code),esc(item.name),esc((item.permissions || []).length),item.code === 'ADMINISTRADOR' ? 'Protegido' : `<a class="btn btn-outline-secondary btn-sm" href="#roles?id=${encodeURIComponent(item.id)}">Editar</a>`]), 'Todavía no hay roles.')}</section>${protectedRoot ? '' : `<section class="card real-card real-form-section"><h2>${r.id ? 'Editar rol' : 'Nuevo rol'}</h2><form id="role-form"><input type="hidden" name="id" value="${esc(r.id || '')}">${field('Código', 'code', r.code, 'required maxlength="60"')}${field('Nombre', 'name', r.name, 'required maxlength="120"')}<fieldset class="real-check-list"><legend>Permisos</legend><p>Canjear requiere consultar cupones; registrar requiere consultar clientes. La administración de usuarios, roles y centros está reservada al Administrador.</p>${Object.entries(permissions).filter(([code]) => !['users.manage','roles.manage','centers.manage'].includes(code)).map(([code,label]) => `<label class="choice"><input type="checkbox" name="permissions" value="${esc(code)}" ${(r.permissions || []).includes(code) ? 'checked' : ''}><span>${esc(label)}</span></label>`).join('')}</fieldset><button class="btn btn-primary">Guardar rol</button></form></section>`}`, 'roles');
  }
  async function centersPage(params) {
    const items = await api('centers');
    const c = items.find(item => String(item.id) === params.get('id')) || {};
    return shell(`${heading('Administración', 'Centros de costo', 'Catálogo de centros asignables a cuentas.', '<a class="btn btn-primary" href="#centros?new=1">Nuevo centro</a>')}<section class="data-card">${table(['Código','Centro','Parque','Estado','Acción'], items.map(item => [esc(item.code),esc(item.name),esc(item.park_name || '—'),badge(yes(item.active) ? 'active' : 'inactive'),`<a class="btn btn-outline-secondary btn-sm" href="#centros?id=${encodeURIComponent(item.id)}">Editar</a>`]), 'Todavía no hay centros.')}</section><section class="card real-card real-form-section"><h2>${c.id ? 'Editar centro' : 'Nuevo centro'}</h2><form id="center-form"><input type="hidden" name="id" value="${esc(c.id || '')}">${field('Código', 'code', c.code, 'required maxlength="60"')}${field('Nombre del centro', 'name', c.name, 'required maxlength="120"')}${field('Parque', 'park_name', c.park_name, 'required maxlength="120"')}${checkbox('Centro activo', 'active', c.id ? yes(c.active) : true)}<button class="btn btn-primary">Guardar centro</button></form></section>`, 'centros');
  }
  async function auditPage() {
    const items = await api('audit');
    return shell(`${heading('Administración', 'Bitácora', 'Operaciones y cambios registrados por el servidor.')}<section class="data-card">${table(['Fecha','Responsable','Evento','Entidad','ID','Detalle'], items.map(item => [dateTime(item.created_at),esc(item.actor_name || 'Sistema'),esc(item.event),esc(item.entity),esc(item.entity_id),`<span class="audit-detail">${esc(typeof item.details === 'string' ? item.details : JSON.stringify(item.details || {}))}</span>`]), 'Todavía no hay eventos.')}</section>`, 'bitacora');
  }
  const protectedRoutes = {
    clientes: 'customers.read', registro: 'customers.write', cupones: 'coupons.read', canjear: 'coupons.redeem',
    campanas: 'campaigns.manage', envios: 'mail.manage', usuarios: 'users.manage', roles: 'roles.manage',
    centros: 'centers.manage', bitacora: 'audit.read'
  };
  function prepareAdmin(page) {
    if (!['campanas','usuarios','roles','centros'].includes(page)) return;
    const dataTable = app.querySelector('.data-table');
    if (dataTable) {
      const rows = [...dataTable.querySelectorAll('tbody tr')].filter(row => !row.querySelector('[colspan]'));
      const toolbar = document.createElement('div'); toolbar.className = page === 'usuarios' ? 'admin-user-toolbar' : 'filters admin-list-toolbar';
      toolbar.innerHTML = `<label class="admin-user-search-field"><span class="form-label">Buscar en ${esc(app.querySelector('.page-title').textContent.toLowerCase())}</span><input class="form-control" type="search" aria-label="Buscar en la lista" placeholder="Escribe para filtrar…"></label>`;
      if (page === 'usuarios') {
        for (const [column,label] of [[2,'Rol'],[4,'Estado']]) {
          const choices = [...new Set(rows.map(row => row.cells[column].textContent.trim()))].sort();
          toolbar.insertAdjacentHTML('beforeend', `<label><span class="form-label">${label}</span><select class="form-control" aria-label="Filtrar por ${label.toLowerCase()}" data-column="${column}"><option value="">Todos</option>${choices.map(value => option(value,value,'')).join('')}</select></label>`);
        }
      }
      toolbar.insertAdjacentHTML('beforeend','<button class="btn btn-outline-secondary" type="button">Limpiar</button>');
      const result = document.createElement('p'); result.className = 'field-help admin-list-count'; result.setAttribute('role','status');
      const normalize = text => text.normalize('NFD').replace(/[\u0300-\u036f]/g,'').toLowerCase();
      const filter = () => {
        const q = normalize(toolbar.querySelector('input').value.trim());
        const selects = [...toolbar.querySelectorAll('select')]; let count = 0;
        rows.forEach(row => { row.hidden = !normalize(row.textContent).includes(q) || selects.some(select => select.value && row.cells[Number(select.dataset.column)].textContent.trim() !== select.value); if (!row.hidden) count++; });
        result.textContent = `${count} de ${rows.length} registros${count === 0 ? ' · No hay coincidencias.' : ''}`;
      };
      toolbar.addEventListener('input',filter); toolbar.addEventListener('change',filter);
      toolbar.querySelector('button').addEventListener('click',() => { toolbar.querySelectorAll('input,select').forEach(input => { input.value = ''; }); filter(); toolbar.querySelector('input').focus(); });
      const container = dataTable.closest('.data-table-shell'); container.before(toolbar,result); filter();
    }
    const panel = app.querySelector('.real-form-section');
    if (!panel) return;
    const editing = !!route().params.get('id'), creating = route().params.has('new');
    const title = app.querySelector('.page-heading');
    panel.hidden = !editing && !creating;
    title?.after(panel);
    if (!panel.hidden) {
      const back = document.createElement('a'); back.href = '#' + page; back.className = 'btn btn-outline-secondary'; back.textContent = 'Cancelar';
      const controls = document.createElement('div'); controls.className = 'form-actions';
      const form=panel.querySelector('form'), save=form.querySelector('button:not([type="button"])');
      controls.append(back,save); form.append(controls);
    }
  }
  function stopCamera() {
    if (state.scanner) { state.scanner.active = false; state.scanner.stream?.getTracks().forEach(t => t.stop()); state.scanner = null; }
  }
  async function render() {
    stopCamera();
    window.CumpleHelp?.close(false);
    const serial = ++state.serial;
    const current = route();
    document.querySelectorAll('link[data-shell-style]').forEach(link => { link.disabled = !user() && current.name !== 'cupon'; });
    if (current.name === 'cupon') {
      app.innerHTML = '<div class="boot-screen">Cargando cupón…</div>';
      try { await publicCoupon(current.params.get('token') || ''); }
      catch (error) { if (serial === state.serial) app.innerHTML = shell(errPage('Cupón no disponible', error.message), 'cupon'); }
      return;
    }
    if (!user()) { app.innerHTML = accessPage(); window.CumpleTheme?.syncNavigation?.(); return; }
    if (current.name === 'consulta' && !can('coupons.read') && !can('coupons.redeem')) {
      app.innerHTML = shell(errPage('Sin acceso', 'Tu perfil no permite consultar cupones.'), current.name); return;
    }
    if (protectedRoutes[current.name] && !can(protectedRoutes[current.name])) {
      app.innerHTML = shell(errPage('Sin acceso', 'Tu perfil no permite abrir esta función.'), current.name); return;
    }
    app.innerHTML = shell('<div class="loading-block">Cargando…</div>', current.name);
    try {
      let html;
      switch (current.name) {
        case 'panel': html = await dashboardPage(); break;
        case 'buscar': html = await searchPage(current.params); break;
        case 'clientes': html = await customersPage(current.params); break;
        case 'registro': html = customerForm(); break;
        case 'registrado': html = state.registrationResult ? customerSuccess(state.registrationResult) : shell(errPage('Sin registro reciente', 'Abre el formulario para registrar un cliente.'), 'registro'); break;
        case 'cupones': html = await couponsPage(current.params); break;
        case 'canjear': case 'consulta': html = redeemPage(current.params.get('code') || ''); break;
        case 'campanas': html = await campaignsPage(current.params); break;
        case 'envios': html = await mailPage(); break;
        case 'usuarios': html = await usersPage(current.params); break;
        case 'roles': html = await rolesPage(current.params); break;
        case 'centros': html = await centersPage(current.params); break;
        case 'bitacora': html = await auditPage(); break;
        case 'manual': html = shell(window.CumpleHelp.manual(user()), 'manual'); break;
        default: location.hash = '#panel'; return;
      }
      if (serial !== state.serial) return;
      app.innerHTML = html;
      prepareAdmin(current.name);
      window.CumpleTheme?.syncNavigation?.();
      if ((current.name === 'canjear' || current.name === 'consulta') && current.params.get('code')) await lookup(current.params.get('code'));
    } catch (error) {
      if (serial === state.serial) app.innerHTML = shell(errPage('No se pudo cargar la pantalla', error.message), current.name);
    }
  }
  async function submit(form, action, body, success) {
    const button = form?.matches?.('form') ? form.querySelector('button:not([type="button"])') : form?.matches?.('button') ? form : null;
    if (button) { button.disabled = true; button.setAttribute('aria-busy','true'); }
    try { const result = await api(action, { method: 'POST', body }); await success(result); }
    catch (error) { notice(error.message); if (button) { button.disabled = false; button.removeAttribute('aria-busy'); } }
  }
  const formData = form => Object.fromEntries(new FormData(form));
  async function lookup(code) {
    const result = document.querySelector('#lookup-result');
    if (!result || !String(code).trim()) return;
    result.innerHTML = '<div class="loading-block">Consultando el registro central…</div>';
    try { state.lookup = await api('lookup', { params: { code: String(code).trim() } }); result.innerHTML = lookupMarkup(state.lookup); }
    catch (error) { result.innerHTML = `<div class="state-explanation">${esc(error.message)}</div>`; notice(error.message); }
  }
  async function startCamera() {
    const message = document.querySelector('#camera-message'), stage = document.querySelector('#camera-stage');
    if (!stage || !message) return;
    if (!('BarcodeDetector' in window) || !navigator.mediaDevices?.getUserMedia) { message.textContent = 'La cámara no está disponible. Escribe el código.'; return; }
    stopCamera();
    const scanner = { active: true, stream: null };
    state.scanner = scanner;
    try {
      const detector = new BarcodeDetector({ formats: ['qr_code'] });
      scanner.stream = await navigator.mediaDevices.getUserMedia({ video: { facingMode: 'environment' }, audio: false });
      if (!scanner.active || !stage.isConnected) return stopCamera();
      const video = stage.querySelector('video');
      video.srcObject = scanner.stream;
      stage.hidden = false;
      await video.play();
      const scan = async () => {
        if (!scanner.active) return;
        try {
          const found = await detector.detect(video);
          if (found[0]?.rawValue) {
            const code = found[0].rawValue.trim();
            stopCamera(); stage.hidden = true;
            const input = document.querySelector('#lookup-form [name="code"]');
            if (input) input.value = code;
            await lookup(code); return;
          }
        } catch (_error) { /* Next frame may be readable. */ }
        requestAnimationFrame(scan);
      };
      scan();
    } catch (_error) { stopCamera(); message.textContent = 'No se pudo abrir la cámara. Escribe el código.'; }
  }
  app.addEventListener('submit', async event => {
    const form = event.target;
    if (!form.matches('form')) return;
    event.preventDefault();
    if (!form.reportValidity()) return;
    // A control named "id" shadows HTMLFormElement.id in the admin forms.
    const formId = form.getAttribute('id');
    const data = formData(form);
    if (formId === 'otp-request-form') return submit(form, 'otp-request', { email: data.email.trim() }, result => {
      state.accessEmail = data.email.trim(); state.challenge = result.challenge; notice(result.message || 'Si existe una cuenta activa, recibirás un código.', 'success'); app.innerHTML = accessPage();
    });
    if (formId === 'otp-verify-form') return submit(form, 'otp-verify', { challenge: state.challenge, code: data.code.trim() }, async () => {
      state.challenge = ''; state.notice = null; state.bootstrap = await api('bootstrap'); location.hash = '#panel'; await render();
    });
    if (formId === 'customer-form') {
      const day = Number(data.day), month = Number(data.month);
      const validDay = day >= 1 && day <= new Date(2024, month, 0).getDate();
      if (!validDay) return notice('Revisa el día y el mes del cumpleaños.');
      if (form.elements.is_minor.checked && !data.guardian?.trim()) return notice('Escribe el nombre del adulto responsable.');
      if (!form.elements.terms_accepted.checked) return notice('Confirma la aceptación de los términos.');
      state.requestId ||= newRequestId();
      return submit(form, 'customer', { name: data.name.trim(), day, month, email: data.email.trim(), phone: data.phone.trim(), guardian: form.elements.is_minor.checked ? data.guardian.trim() : '', is_minor: form.elements.is_minor.checked, terms_accepted: true, marketing_opt_in: form.elements.marketing_opt_in.checked, request_id: state.requestId }, result => {
        state.requestId = null; state.registrationResult = result; location.hash = '#registrado'; render();
      });
    }
    if (formId === 'global-search' || formId === 'search-page-form') { location.hash = '#buscar?q=' + encodeURIComponent(data.q.trim()); return render(); }
    if (formId === 'lookup-form') return lookup(data.code);
    if (formId === 'customers-search' || formId === 'coupons-search') {
      const page = formId === 'customers-search' ? 'clientes' : 'cupones';
      const params = new URLSearchParams(data);
      location.hash = `#${page}?${params}`; return render();
    }
    if (formId === 'campaign-form') {
      const quantity = data.quantity === '' ? null : Number(data.quantity);
      if (data.benefit_type === 'tokens' && (!Number.isInteger(quantity) || quantity <= 0)) return notice('Indica una cantidad positiva de tokens.');
      if (quantity !== null && (!Number.isInteger(quantity) || quantity <= 0)) return notice('La cantidad debe ser un entero positivo.');
      if (data.start_date && data.end_date && data.start_date > data.end_date) return notice('La fecha final debe ser posterior a la inicial.');
      return submit(form, 'campaign', { ...data, quantity }, () => { notice('Campaña guardada.', 'success'); location.hash = '#campanas'; render(); });
    }
    if (formId === 'user-form') return submit(form, 'user', { ...data, center_id: data.center_id || null, active: form.elements.active.checked, scope_center_ids: [...form.querySelectorAll('[name="scope_center_ids"]:checked')].map(el => Number(el.value)) }, () => { notice('Usuario guardado.', 'success'); location.hash = '#usuarios'; render(); });
    if (formId === 'role-form') return submit(form, 'role', { ...data, permissions: [...form.querySelectorAll('[name="permissions"]:checked')].map(el => el.value) }, () => { notice('Rol guardado.', 'success'); location.hash = '#roles'; render(); });
    if (formId === 'center-form') return submit(form, 'center', { ...data, active: form.elements.active.checked }, () => { notice('Centro guardado.', 'success'); location.hash = '#centros'; render(); });
    if (formId === 'void-form') return submit(form, 'void', { code: form.dataset.code, reason: data.reason.trim() }, result => {
      state.lookup = result; document.querySelector('#lookup-result').innerHTML = lookupMarkup(result); notice('Cupón anulado.', 'success');
    });
  });
  app.addEventListener('click', async event => {
    const target = event.target.closest('[data-dismiss-notice],[data-logout],[data-otp-back],[data-otp-resend],[data-retry],[data-customer-status],[data-redeem],[data-mail-retry],[data-run-issuance],[data-start-camera],[data-stop-camera],[data-print],[data-sidebar-toggle],[data-sidebar-backdrop]');
    if (!target) return;
    if (target.hasAttribute('data-dismiss-notice')) { state.notice = null; target.closest('.notice')?.remove(); }
    if (target.hasAttribute('data-otp-resend')) return submit(target, 'otp-request', { email: state.accessEmail }, result => { state.challenge = result.challenge; notice(result.message, 'success'); app.innerHTML = accessPage(); });
    if (target.hasAttribute('data-otp-back')) { state.challenge = ''; app.innerHTML = accessPage(); }
    if (target.hasAttribute('data-retry')) render();
    if (target.hasAttribute('data-print')) window.print();
    if (target.hasAttribute('data-start-camera')) startCamera();
    if (target.hasAttribute('data-stop-camera')) { stopCamera(); document.querySelector('#camera-stage').hidden = true; }
    // theme.js owns the responsive sidebar controls.
    if (target.hasAttribute('data-logout')) return submit(target.closest('form') || app, 'logout', {}, async () => {
      state.bootstrap = await api('bootstrap'); state.lookup = null; state.notice = { message: 'Sesión cerrada.', type: 'success' }; location.hash = '#acceso'; render();
    });
    if (target.hasAttribute('data-customer-status')) return submit(target.closest('form') || app, 'customer-status', { id: target.dataset.customerStatus, active: target.dataset.active === '1' }, () => { notice('Estado actualizado.', 'success'); render(); });
    if (target.hasAttribute('data-redeem')) {
      target.disabled = true;
      try { state.lookup = await api('redeem', { method: 'POST', body: { code: target.dataset.redeem } }); document.querySelector('#lookup-result').innerHTML = lookupMarkup(state.lookup); notice('Canje confirmado por el servidor. Ahora entrega el regalo.', 'success'); }
      catch (error) { notice(error.message); target.disabled = false; await lookup(target.dataset.redeem); }
    }
    if (target.hasAttribute('data-mail-retry')) {
      if (target.dataset.mailStatus === 'uncertain' && !confirm('El resultado anterior es incierto: el correo pudo ser aceptado por SMTP. Reintentar conservará el mismo cupón. ¿Continuar?')) return;
      target.disabled = true;
      try { await api('mail-retry', { method: 'POST', body: { id: target.dataset.mailRetry } }); notice('Reintento solicitado.', 'success'); render(); }
      catch (error) { notice(error.message); target.disabled = false; }
    }
    if (target.hasAttribute('data-run-issuance')) {
      target.disabled = true;
      try { const result = await api('run-issuance', { method: 'POST' }); notice(`Emisión revisada: ${Number(result.issued || 0)} regalos pendientes creados. El proceso automático enviará los correos pendientes.`, 'success'); render(); }
      catch (error) { notice(error.message); target.disabled = false; }
    }
  });
  app.addEventListener('change', event => {
    if (event.target.name === 'permissions') {
      const dependencies = { 'coupons.redeem': 'coupons.read', 'customers.write': 'customers.read' };
      const controls = [...event.target.form.querySelectorAll('[name="permissions"]')];
      for (const [write, read] of Object.entries(dependencies)) {
        const operation = controls.find(input => input.value === write), consultation = controls.find(input => input.value === read);
        if (operation?.checked && consultation) consultation.checked = true;
      }
    }
    if (event.target.name === 'is_minor') {
      const box = app.querySelector('.guardian-field');
      if (box) { box.hidden = !event.target.checked; box.querySelector('input').required = event.target.checked; }
    }
    if (event.target.name === 'benefit_type') {
      const quantity = app.querySelector('[name="quantity"]');
      if (quantity) quantity.required = event.target.value === 'tokens';
    }
  });
  document.addEventListener('keydown', event => {
    if ((event.ctrlKey || event.metaKey) && event.key.toLowerCase() === 'k') {
      if (document.querySelector('[role="dialog"]:not([hidden])')) return;
      const search = [...app.querySelectorAll('.topbar-search input,#search-page-form input,#customers-search [name="q"],#coupons-search [name="q"],[data-manual-search]')].find(el => el.getClientRects().length);
      if (search) { event.preventDefault(); search.focus(); }
    }
  });
  window.addEventListener('hashchange', render);
  window.addEventListener('beforeunload', stopCamera);
  (async () => {
    app.innerHTML = '<div class="boot-screen">Cargando Carrousel Cumple…</div>';
    try { state.bootstrap = await api('bootstrap'); await render(); }
    catch (error) { app.innerHTML = errPage('No se pudo iniciar Carrousel Cumple', error.message); }
  })();
})();
