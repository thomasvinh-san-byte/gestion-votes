(function(){
  const codeInput = document.getElementById('codeInput');
  const voteSelect = document.getElementById('voteSelect');
  const justInput = document.getElementById('justInput');
  const saveBtn = document.getElementById('saveBtn');
  const msg = document.getElementById('msg');

  const startBtn = document.getElementById('startScanBtn');
  const stopBtn = document.getElementById('stopScanBtn');
  const video = document.getElementById('video');
  const canvas = document.getElementById('canvas');
  const ctx = canvas.getContext('2d');

  let stream = null;
  let scanning = false;
  let detector = null;

  function setMsg(text, kind){
    msg.textContent = text || '';
    msg.className = (kind === 'danger') ? 'badge danger' : 'muted tiny';
  }

  async function save(){
    const code = (codeInput.value || '').trim();
    const vote_value = voteSelect.value;
    const justification = (justInput.value || '').trim() || 'vote papier (secours)';

    if(!code){ setMsg('Code manquant', 'danger'); return; }

    saveBtn.disabled = true;
    setMsg('Enregistrement…');
    try{
      await Utils.apiPost('/api/v1/paper_ballot_redeem.php', { code, vote_value, justification });
      setMsg('Enregistré ✅');
      codeInput.value = '';
    }catch(e){
      setMsg('Erreur: bulletin introuvable ou déjà utilisé', 'danger');
    }finally{
      saveBtn.disabled = false;
    }
  }

  saveBtn.addEventListener('click', (e) => { e.preventDefault(); save(); });

  async function startScan(){
    if(scanning) return;
    if(!('BarcodeDetector' in window)){
      setMsg('Scanner QR indisponible (navigateur). Saisie manuelle.', 'danger');
      return;
    }
    try{
      detector = new BarcodeDetector({ formats: ['qr_code'] });
      stream = await navigator.mediaDevices.getUserMedia({ video: { facingMode: 'environment' }, audio:false });
      video.srcObject = stream;
      await video.play();
      scanning = true;
      startBtn.style.display = 'none';
      stopBtn.style.display = '';
      setMsg('Scan en cours…');
      tick();
    }catch(e){
      setMsg('Caméra indisponible. Saisie manuelle.', 'danger');
    }
  }

  function stopScan(){
    scanning = false;
    startBtn.style.display = '';
    stopBtn.style.display = 'none';
    if(stream){ stream.getTracks().forEach(t => t.stop()); stream = null; }
    video.srcObject = null;
  }

  async function tick(){
    if(!scanning) return;
    try{
      const w = video.videoWidth || 640;
      const h = video.videoHeight || 480;
      canvas.width = w; canvas.height = h;
      ctx.drawImage(video, 0, 0, w, h);
      const bmp = await createImageBitmap(canvas);
      const codes = await detector.detect(bmp);
      if(codes && codes.length){
        const raw = (codes[0].rawValue || '').trim();
        if(raw){
          codeInput.value = raw;
          setMsg('Code détecté. Enregistre le vote.');
          stopScan();
          return;
        }
      }
    }catch(e){}
    requestAnimationFrame(tick);
  }

  startBtn.addEventListener('click', (e) => { e.preventDefault(); startScan(); });
  stopBtn.addEventListener('click', (e) => { e.preventDefault(); stopScan(); });

  justInput.value = 'vote papier (secours)';
})();
