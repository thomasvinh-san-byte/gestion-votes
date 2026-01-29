(function(){
  function isOpen(modal){ return modal && modal.style && modal.style.display !== 'none'; }

  function focusFirst(modal){
    const focusables = modal.querySelectorAll('button, [href], input, select, textarea, [tabindex]:not([tabindex="-1"])');
    for (const el of focusables){
      if (!el.hasAttribute('disabled') && el.getAttribute('aria-hidden') !== 'true'){
        el.focus();
        return;
      }
    }
  }

  function trap(modal, e){
    if (e.key !== 'Tab') return;
    const focusables = Array.from(modal.querySelectorAll('button, [href], input, select, textarea, [tabindex]:not([tabindex="-1"])'))
      .filter(el => !el.hasAttribute('disabled') && el.offsetParent !== null);
    if (!focusables.length) return;

    const first = focusables[0];
    const last = focusables[focusables.length - 1];
    const active = document.activeElement;

    if (e.shiftKey && active === first){ e.preventDefault(); last.focus(); }
    else if (!e.shiftKey && active === last){ e.preventDefault(); first.focus(); }
  }

  document.addEventListener('keydown', (e) => {
    const modal = document.querySelector('.modal');
    if (!modal || !isOpen(modal)) return;

    if (e.key === 'Escape'){
      const close = modal.querySelector('[data-close="1"]');
      if (close){ close.click(); }
      return;
    }
    trap(modal, e);
  });

  const obs = new MutationObserver(() => {
    const modal = document.querySelector('.modal');
    if (!modal) return;
    if (isOpen(modal)) focusFirst(modal);
  });
  obs.observe(document.documentElement, { attributes:true, subtree:true, attributeFilter:['style','class'] });
})();
