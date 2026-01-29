(function(){
  function toast(type, title, body){
    if (window.toast && typeof window.toast === 'function') {
      window.toast({ type, title, body });
      return;
    }
    if (window.Toast && typeof window.Toast.show === 'function') {
      window.Toast.show({ type, title, body });
      return;
    }
    console.warn(title, body);
  }

  function human(err){
    if (window.Utils && typeof Utils.humanizeError === 'function') return Utils.humanizeError(err);
    return (err && err.message) ? err.message : 'Une erreur est survenue.';
  }

  document.body.addEventListener('htmx:responseError', (e) => {
    const xhr = e.detail && e.detail.xhr;
    let payload = null;
    try { payload = xhr ? JSON.parse(xhr.responseText) : null; } catch(_){}
    const err = { data: payload || null, message: xhr ? xhr.responseText : null };
    toast('danger', 'Action impossible', human(err));
  });

  document.body.addEventListener('htmx:sendError', () => {
    toast('warn', 'Connexion instable', 'Impossible de contacter le serveur. Réessaie dans quelques secondes.');
  });

  function ensureIndicator(){
    document.querySelectorAll('[data-indicator]').forEach(btn => {
      const id = btn.getAttribute('data-indicator');
      if (!id) return;
      const el = document.getElementById(id);
      if (!el) return;
      el.classList.add('htmx-indicator');
    });
  }
  if(document.readyState === 'loading'){
    document.addEventListener('DOMContentLoaded', ensureIndicator);
  } else {
    ensureIndicator();
  }
})();
