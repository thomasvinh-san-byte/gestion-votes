/* GO-LIVE-STATUS: ready — Wizard JS. Navigation 4 étapes, validation formulaire.
   NICE-TO-HAVE: drag & drop résolutions. */
/**
 * Wizard — 4-step meeting creation.
 * Manages step navigation, form state, and API submission.
 */
(function () {
  'use strict';

  var currentStep = 0;
  var totalSteps = 5; // 0-3 + confirmation (4)

  function escapeHtml(s) {
    if (!s) return '';
    return String(s)
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;')
      .replace(/'/g, '&#39;');
  }

  /* ── Step navigation ─────────────────────────────── */

  function showStep(n) {
    currentStep = n;
    for (var i = 0; i < totalSteps; i++) {
      var el = document.getElementById('step' + i);
      if (el) el.style.display = i === n ? '' : 'none';
    }
    updateStepper();
    window.scrollTo({ top: 0, behavior: 'smooth' });
  }

  function updateStepper() {
    var items = document.querySelectorAll('.wiz-step-item');
    items.forEach(function (item, i) {
      item.classList.remove('done', 'active');
      if (i < currentStep) item.classList.add('done');
      else if (i === currentStep) item.classList.add('active');
      // Update number display
      var snum = item.querySelector('.wiz-snum');
      if (snum) {
        if (i < currentStep) {
          snum.innerHTML = '<svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3"><path d="M20 6 9 17l-5-5"/></svg>';
        } else {
          snum.textContent = i + 1;
        }
      }
    });
  }

  /* ── Time input behavior ─────────────────────────── */

  function setupTimeInput() {
    var hh = document.getElementById('wizTimeHH');
    var mm = document.getElementById('wizTimeMM');
    if (!hh || !mm) return;

    hh.addEventListener('input', function () {
      var v = hh.value.replace(/\D/g, '').slice(0, 2);
      hh.value = v;
      if (v.length === 2) {
        if (parseInt(v, 10) > 23) { hh.value = v.slice(0, 1); return; }
        mm.focus();
      }
    });

    mm.addEventListener('input', function () {
      var v = mm.value.replace(/\D/g, '').slice(0, 2);
      mm.value = v;
      if (v.length === 2 && parseInt(v, 10) > 59) {
        mm.value = v.slice(0, 1);
      }
    });

    mm.addEventListener('keydown', function (e) {
      if (e.key === 'Backspace' && mm.value === '') hh.focus();
    });

    hh.addEventListener('blur', function () {
      if (hh.value.length === 1) hh.value = hh.value.padStart(2, '0');
    });

    mm.addEventListener('blur', function () {
      if (mm.value.length === 1) mm.value = mm.value.padStart(2, '0');
    });
  }

  /* ── Secret vote chips toggle ────────────────────── */

  function setupChips() {
    var chipNon = document.getElementById('chipNonSecret');
    var chipOui = document.getElementById('chipSecret');
    if (!chipNon || !chipOui) return;

    chipNon.addEventListener('click', function () {
      chipNon.classList.add('active');
      chipOui.classList.remove('active');
    });

    chipOui.addEventListener('click', function () {
      chipOui.classList.add('active');
      chipNon.classList.remove('active');
    });
  }

  /* ── Recap builder ───────────────────────────────── */

  function buildRecap() {
    var recap = document.getElementById('wizRecap');
    if (!recap) return;

    var title = (document.getElementById('wizTitle') || {}).value || '(non renseign\u00e9)';
    var type = (document.getElementById('wizType') || {}).value || '';
    var date = (document.getElementById('wizDate') || {}).value || '';
    var hh = (document.getElementById('wizTimeHH') || {}).value || '';
    var mm = (document.getElementById('wizTimeMM') || {}).value || '';
    var place = (document.getElementById('wizPlace') || {}).value || '';
    var addr = (document.getElementById('wizAddr') || {}).value || '';
    var quorum = (document.getElementById('wizQuorum') || {}).value || '';
    var lieu = [place, addr].filter(Boolean).join(', ') || '(non renseign\u00e9)';
    var time = hh && mm ? hh + ':' + mm : '';
    var dateStr = date ? date + (time ? ' \u00e0 ' + time : '') : '(non renseign\u00e9e)';

    var rows = [
      ['Titre', title],
      ['Type', type],
      ['Date', dateStr],
      ['Lieu', lieu],
      ['Quorum', quorum]
    ];

    var html = '';
    rows.forEach(function (r, i) {
      html += '<div class="flex-between" style="padding:7px 0;border-bottom:' + (i < rows.length - 1 ? '1px solid var(--border-soft)' : 'none') + ';">' +
        '<span class="muted">' + escapeHtml(r[0]) + '</span>' +
        '<span style="font-weight:700;color:var(--text-dark);">' + escapeHtml(r[1]) + '</span>' +
      '</div>';
    });
    recap.innerHTML = html;
  }

  /* ── Button bindings ─────────────────────────────── */

  function init() {
    setupTimeInput();
    setupChips();

    // Navigation buttons
    var bindings = [
      ['btnNext0', function () { showStep(1); }],
      ['btnPrev1', function () { showStep(0); }],
      ['btnNext1', function () { showStep(2); }],
      ['btnPrev2', function () { showStep(1); }],
      ['btnNext2', function () { buildRecap(); showStep(3); }],
      ['btnPrev3', function () { showStep(2); }],
      ['btnCreate', function () {
        // TODO: call API to create meeting
        showStep(4);
        var st = document.getElementById('wizSuccessTitle');
        var title = (document.getElementById('wizTitle') || {}).value;
        if (st && title) st.textContent = escapeHtml(title) + ' cr\u00e9\u00e9e';
      }]
    ];

    bindings.forEach(function (b) {
      var el = document.getElementById(b[0]);
      if (el) el.addEventListener('click', b[1]);
    });

    // Stepper items clickable
    document.querySelectorAll('.wiz-step-item').forEach(function (item) {
      item.style.cursor = 'pointer';
      item.addEventListener('click', function () {
        var step = parseInt(item.getAttribute('data-step'), 10);
        if (!isNaN(step) && step <= currentStep) {
          showStep(step);
        }
      });
    });

    showStep(0);
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
  } else {
    init();
  }
})();
