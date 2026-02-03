/** paper_redeem.js — Paper ballot redemption page for AG-VOTE. Must be loaded AFTER utils.js, shared.js and shell.js. */
(function() {
  'use strict';

  const codeInput = document.getElementById('codeInput');
  const voteChoice = document.getElementById('voteChoice');
  const justInput = document.getElementById('justInput');
  const btnSave = document.getElementById('btnSave');
  const btnReset = document.getElementById('btnReset');
  const resultMsg = document.getElementById('resultMsg');
  const recentList = document.getElementById('recentList');
  const voteButtons = document.querySelectorAll('.vote-btn');
  const videoPreview = document.getElementById('videoPreview');
  const overlay = document.getElementById('overlay');
  const btnStart = document.getElementById('btnStart');
  const btnStop = document.getElementById('btnStop');
  const btnScan = document.getElementById('btnScan');

  let stream = null;
  let detector = null;
  let scanInterval = null;
  let recent = [];

  // Vote selection
  voteButtons.forEach(btn => {
    btn.addEventListener('click', () => {
      voteButtons.forEach(b => b.classList.remove('active'));
      btn.classList.add('active');
      voteChoice.value = btn.dataset.choice;
      validate();
    });
  });

  function validate() {
    const ok = codeInput.value.length > 30 && voteChoice.value && justInput.value.length >= 3;
    btnSave.disabled = !ok;
  }

  codeInput.addEventListener('input', validate);
  justInput.addEventListener('input', validate);

  btnReset.addEventListener('click', () => {
    codeInput.value = '';
    voteChoice.value = '';
    justInput.value = '';
    voteButtons.forEach(b => b.classList.remove('active'));
    resultMsg.style.display = 'none';
    validate();
  });

  btnSave.addEventListener('click', async () => {
    btnSave.disabled = true;
    try {
      const { body } = await api('/api/v1/paper_ballot_redeem.php', {
        code: codeInput.value.trim(),
        choice: voteChoice.value,
        justification: justInput.value.trim()
      });
      if (body?.ok) {
        showMsg('success', '✅ Vote enregistré');
        addRecent(codeInput.value, voteChoice.value, justInput.value);
        btnReset.click();
      } else {
        showMsg('error', body?.error || 'Erreur');
      }
    } catch (e) {
      showMsg('error', e.message);
    }
    validate();
  });

  function showMsg(type, text) {
    resultMsg.style.display = 'block';
    resultMsg.className = `alert alert-${type === 'success' ? 'success' : 'danger'} mt-4`;
    resultMsg.textContent = text;
  }

  function addRecent(code, choice, just) {
    const labels = { for: 'Pour', against: 'Contre', abstain: 'Abstention', blank: 'Blanc' };
    recent.unshift({ code: code.slice(0,8)+'...', choice: labels[choice], just, time: new Date().toLocaleTimeString('fr-FR') });
    if (recent.length > 10) recent.pop();
    renderRecent();
  }

  function renderRecent() {
    if (!recent.length) {
      recentList.innerHTML = '<div class="empty-state"><div class="empty-state-icon">📄</div><h3 class="empty-state-title">Aucune saisie</h3></div>';
      return;
    }
    recentList.innerHTML = `<table class="table"><thead><tr><th>Heure</th><th>Code</th><th>Vote</th><th>Justification</th></tr></thead><tbody>${recent.map(r => `<tr><td>${r.time}</td><td><code>${r.code}</code></td><td><span class="badge">${r.choice}</span></td><td class="text-sm">${r.just}</td></tr>`).join('')}</tbody></table>`;
  }

  // Scanner
  async function startScan() {
    if (!('BarcodeDetector' in window)) { showMsg('error', 'BarcodeDetector non disponible'); return; }
    try {
      stream = await navigator.mediaDevices.getUserMedia({ video: { facingMode: 'environment' } });
      videoPreview.srcObject = stream;
      videoPreview.play();
      overlay.classList.add('active');
      btnStart.style.display = 'none';
      btnStop.style.display = 'block';
      detector = new BarcodeDetector({ formats: ['qr_code'] });
      scanInterval = setInterval(async () => {
        try {
          const codes = await detector.detect(videoPreview);
          if (codes.length && codes[0].rawValue.match(/^[0-9a-f-]{36}$/i)) {
            codeInput.value = codes[0].rawValue;
            validate();
            stopScan();
            showMsg('success', 'QR détecté !');
          }
        } catch {}
      }, 400);
    } catch (e) { showMsg('error', 'Caméra: ' + e.message); }
  }

  function stopScan() {
    if (stream) { stream.getTracks().forEach(t => t.stop()); stream = null; }
    if (scanInterval) { clearInterval(scanInterval); scanInterval = null; }
    videoPreview.srcObject = null;
    overlay.classList.remove('active');
    btnStart.style.display = 'block';
    btnStop.style.display = 'none';
  }

  btnStart.addEventListener('click', startScan);
  btnStop.addEventListener('click', stopScan);
  btnScan.addEventListener('click', startScan);

  renderRecent();
})();
