/* public/assets/js/meeting-hub.js
   Small UI-only helpers for the orchestration page (operator hub).
*/
(function () {
  function $(id) { return document.getElementById(id); }

  const root = document.getElementById('meetingHub');
  const btnToggle = $('toggleHubPanel');
  const btnClose = $('closeHubPanel');
  const panel = $('hubPanel');

  function setPanel(open) {
    if (!root) return;
    root.classList.toggle('with-panel', open);
    if (panel) panel.setAttribute('aria-hidden', open ? 'false' : 'true');
    try { localStorage.setItem('hub_panel_open', open ? '1' : '0'); } catch (e) {}
  }

  function restore() {
    let open = false;
    try { open = localStorage.getItem('hub_panel_open') === '1'; } catch (e) {}
    setPanel(open);
  }

  function selectTab(name) {
    const tabs = document.querySelectorAll('[data-hub-tab]');
    tabs.forEach(t => t.setAttribute('aria-selected', t.getAttribute('data-hub-tab') === name ? 'true' : 'false'));
    const panes = document.querySelectorAll('[data-hub-pane]');
    panes.forEach(p => p.style.display = (p.getAttribute('data-hub-pane') === name) ? '' : 'none');
    try { localStorage.setItem('hub_panel_tab', name); } catch (e) {}
  }

  function restoreTab() {
    let t = 'ATTENDANCE';
    try { t = localStorage.getItem('hub_panel_tab') || 'ATTENDANCE'; } catch (e) {}
    selectTab(t);
  }

  if (btnToggle) btnToggle.addEventListener('click', () => setPanel(!root.classList.contains('with-panel')));
  if (btnClose) btnClose.addEventListener('click', () => setPanel(false));

  document.addEventListener('click', (e) => {
    const el = e.target.closest('[data-hub-tab]');
    if (!el) return;
    selectTab(el.getAttribute('data-hub-tab'));
  });

  restore();
  restoreTab();
})();
