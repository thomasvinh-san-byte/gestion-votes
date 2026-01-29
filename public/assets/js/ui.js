// public/assets/js/ui.js
(function(){
  function ensureRoot(){
    let root = document.getElementById('toast-root');
    if(!root){
      root = document.createElement('div');
      root.id = 'toast-root';
      document.body.appendChild(root);
    }
    return root;
  }

  function toast(title, detail, type){
    const root = ensureRoot();
    const el = document.createElement('div');
    el.className = 'toast ' + (type || 'ok');
    el.innerHTML = '<p class="t"></p><p class="d"></p>';
    el.querySelector('.t').textContent = title || '';
    el.querySelector('.d').textContent = detail || '';
    root.appendChild(el);
    setTimeout(()=>{ el.style.opacity='0'; el.style.transform='translateY(-4px)'; }, 3200);
    setTimeout(()=>{ el.remove(); }, 3800);
  }

  async function fetchJson(url, options){
    const resp = await fetch(url, options || {});
    let body = null;
    try { body = await resp.json(); } catch(e) {}
    if(!resp.ok){
      const msg = (body && (body.error || body.message)) ? (body.error || body.message) : ('HTTP ' + resp.status);
      const detail = (body && body.detail) ? String(body.detail) : '';
      const err = new Error(msg);
      err.detail = detail;
      err.status = resp.status;
      err.body = body;
      throw err;
    }
    return body;
  }

  window.UI = { toast, fetchJson };
})();