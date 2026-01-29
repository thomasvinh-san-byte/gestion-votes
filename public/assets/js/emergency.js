(function(){
  function meetingId(){
    try { return Utils.getMeetingId(); } catch(e){ return null; }
  }

  async function toggle(box){
    const mid = meetingId();
    if(!mid) return;
    const proc = box.getAttribute('data-proc');
    const idx = Number(box.getAttribute('data-idx')||'0');
    const checked = box.checked ? 1 : 0;
    try{
      await Utils.apiPost('/api/v1/emergency_check_toggle.php', {
        meeting_id: mid,
        procedure_code: proc,
        item_index: idx,
        checked
      });
    }catch(e){
      box.checked = !box.checked;
      if (Utils.toast) Utils.toast('Erreur enregistrement checklist', 'danger');
    }
  }

  document.addEventListener('change', (e) => {
    const t = e.target;
    if(t && t.classList && t.classList.contains('emgChk')) toggle(t);
  });
})();
