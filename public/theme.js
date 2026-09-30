(() => {
  const read = (key, fallback) => { try { return localStorage.getItem(key) || fallback; } catch { return fallback; } };
  const set = (theme) => {
    document.documentElement.dataset.theme = theme;
    try { localStorage.setItem('carrousel-theme', theme); } catch { /* preferences are optional */ }
  };
  const preference = read('carrousel-theme', 'system');
  document.documentElement.dataset.theme = preference === 'system' ? (matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light') : preference;
  const drawerMode = () => matchMedia('(max-width: 900px)').matches;
  function closeDrawer() {
    document.querySelector('#app-sidebar')?.classList.remove('open');
    const backdrop = document.querySelector('[data-sidebar-backdrop]');
    if (backdrop) backdrop.hidden = true;
    if (drawerMode()) document.body.classList.remove('sidebar-collapsed');
    syncNavigation();
  }
  function syncNavigation() {
    const expanded = drawerMode() ? !!document.querySelector('#app-sidebar.open') : !document.body.classList.contains('sidebar-collapsed');
    const toggle = document.querySelector('[data-sidebar-toggle]');
    toggle?.setAttribute('aria-expanded', String(expanded));
    toggle?.setAttribute('aria-label', expanded ? 'Ocultar menú' : 'Mostrar menú');
  }
  document.addEventListener('click', (event) => {
    if (event.target.closest('[data-theme-toggle]')) set(document.documentElement.dataset.theme === 'dark' ? 'light' : 'dark');
    const toggle = event.target.closest('[data-sidebar-toggle]');
    if (toggle) {
      const mobile = drawerMode();
      const sidebar = document.querySelector('#app-sidebar');
      if (mobile) {
        const open = sidebar.classList.toggle('open');
        toggle.setAttribute('aria-expanded', String(open));
        document.querySelector('[data-sidebar-backdrop]').hidden = !open;
      } else {
        const collapsed = document.body.classList.toggle('sidebar-collapsed');
        toggle.setAttribute('aria-expanded', String(!collapsed));
        toggle.setAttribute('aria-label', collapsed ? 'Mostrar menú' : 'Ocultar menú');
      }
      syncNavigation();
    }
    if (event.target.closest('[data-sidebar-backdrop], .side-link')) {
      closeDrawer();
    }
  });
  document.addEventListener('keydown', (event) => {
    if ((event.ctrlKey || event.metaKey) && event.key.toLowerCase() === 'k') {
      const search = document.querySelector('.topbar-search input');
      if (search?.getClientRects().length && !document.querySelector('[role="dialog"]:not([hidden])')) { event.preventDefault(); search.focus(); }
    }
    if (event.key === 'Escape') {
      closeDrawer();
    }
  });
  window.addEventListener('resize', closeDrawer);
  window.CumpleTheme = {syncNavigation};
})();
