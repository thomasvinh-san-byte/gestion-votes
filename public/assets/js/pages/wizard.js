/* GO-LIVE-STATUS: ready — Wizard JS. Navigation 4 étapes, validation formulaire,
   localStorage draft, drag-drop résolutions, API wire. */
/**
 * Wizard — 4-step meeting creation.
 * Manages step navigation, form state, localStorage draft, drag-drop, and API submission.
 */
(function () {
  'use strict';

  var currentStep = 0;
  var totalSteps = 4; // 0-3 (no confirmation step — redirects to hub after create)

  var DRAFT_KEY = 'ag-vote-wizard-draft';

  // In-memory state
  var members = [];
  var resolutions = [];

  // Drag-and-drop state
  var dragSrcIdx = null;

  /* ── Escape helper ──────────────────────────────── */

  function escapeHtml(s) {
    if (!s) return '';
    return String(s)
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;')
      .replace(/'/g, '&#39;');
  }

  function getId(id) {
    return document.getElementById(id) || {};
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

  /* ── localStorage draft ──────────────────────────── */

  function saveDraft() {
    try {
      localStorage.setItem(DRAFT_KEY, JSON.stringify({
        step: currentStep,
        s1: {
          title:  (getId('wizTitle').value || ''),
          type:   (getId('wizType').value || ''),
          date:   (getId('wizDate').value || ''),
          hh:     (getId('wizTimeHH').value || ''),
          mm:     (getId('wizTimeMM').value || ''),
          place:  (getId('wizPlace').value || ''),
          addr:   (getId('wizAddr').value || ''),
          quorum: (getId('wizQuorum').value || ''),
          defaultMaj: (getId('wizDefaultMaj').value || '')
        },
        members: members,
        resolutions: resolutions
      }));
    } catch (e) { /* quota exceeded or private browsing */ }
  }

  function restoreDraft() {
    try {
      var raw = localStorage.getItem(DRAFT_KEY);
      if (!raw) return;
      var draft = JSON.parse(raw);
      if (!draft) return;

      // Restore Step 1 fields
      var s1 = draft.s1 || {};
      if (s1.title)      { var t = document.getElementById('wizTitle');      if (t) t.value = s1.title; }
      if (s1.type)       { var ty = document.getElementById('wizType');      if (ty) ty.value = s1.type; }
      if (s1.date)       { var d = document.getElementById('wizDate');       if (d) d.value = s1.date; }
      if (s1.hh)         { var hh = document.getElementById('wizTimeHH');    if (hh) hh.value = s1.hh; }
      if (s1.mm)         { var mm = document.getElementById('wizTimeMM');    if (mm) mm.value = s1.mm; }
      if (s1.place)      { var p = document.getElementById('wizPlace');      if (p) p.value = s1.place; }
      if (s1.addr)       { var a = document.getElementById('wizAddr');       if (a) a.value = s1.addr; }
      if (s1.quorum)     { var q = document.getElementById('wizQuorum');     if (q) q.value = s1.quorum; }
      if (s1.defaultMaj) { var dm = document.getElementById('wizDefaultMaj'); if (dm) dm.value = s1.defaultMaj; }

      // Restore arrays
      members = Array.isArray(draft.members) ? draft.members : [];
      resolutions = Array.isArray(draft.resolutions) ? draft.resolutions : [];

      renderMembersList();
      renderResoList();

      // Resume at saved step
      showStep(draft.step || 0);
    } catch (e) { /* corrupted draft — ignore */ }
  }

  function clearDraft() {
    try { localStorage.removeItem(DRAFT_KEY); } catch (e) {}
  }

  /* ── Validation gating ───────────────────────────── */

  function validateStep(n) {
    if (n === 0) {
      var title = (document.getElementById('wizTitle') || {}).value || '';
      var date  = (document.getElementById('wizDate') || {}).value || '';
      var hh    = (document.getElementById('wizTimeHH') || {}).value || '';
      var mm    = (document.getElementById('wizTimeMM') || {}).value || '';
      return (
        title.trim().length > 0 &&
        date.length > 0 &&
        hh.length === 2 && parseInt(hh, 10) >= 0 && parseInt(hh, 10) <= 23 &&
        mm.length === 2 && parseInt(mm, 10) >= 0 && parseInt(mm, 10) <= 59
      );
    }
    if (n === 1) { return members.length > 0; }
    if (n === 2) { return resolutions.length > 0; }
    return true;
  }

  function showFieldErrors(n) {
    if (n === 0) {
      var title = (document.getElementById('wizTitle') || {}).value || '';
      var date  = (document.getElementById('wizDate') || {}).value || '';
      var hh    = (document.getElementById('wizTimeHH') || {}).value || '';
      var mm    = (document.getElementById('wizTimeMM') || {}).value || '';

      var titleEl = document.getElementById('wizTitle');
      var errTitle = document.getElementById('errWizTitle');
      var dateEl = document.getElementById('wizDate');
      var errDate = document.getElementById('errWizDate');
      var hhEl = document.getElementById('wizTimeHH');
      var mmEl = document.getElementById('wizTimeMM');
      var errTime = document.getElementById('errWizTime');

      var titleOk = title.trim().length > 0;
      var dateOk  = date.length > 0;
      var timeOk  = hh.length === 2 && mm.length === 2 &&
                    parseInt(hh, 10) >= 0 && parseInt(hh, 10) <= 23 &&
                    parseInt(mm, 10) >= 0 && parseInt(mm, 10) <= 59;

      if (titleEl)  titleEl.classList.toggle('field-error', !titleOk);
      if (errTitle) errTitle.classList.toggle('visible', !titleOk);
      if (dateEl)   dateEl.classList.toggle('field-error', !dateOk);
      if (errDate)  errDate.classList.toggle('visible', !dateOk);
      if (hhEl)     hhEl.classList.toggle('field-error', !timeOk);
      if (mmEl)     mmEl.classList.toggle('field-error', !timeOk);
      if (errTime)  errTime.classList.toggle('visible', !timeOk);
    }
    if (n === 1) {
      var errMembers = document.getElementById('errStep1Members');
      if (errMembers) errMembers.classList.toggle('visible', members.length === 0);
    }
    if (n === 2) {
      var errReso = document.getElementById('errStep2Reso');
      if (errReso) errReso.classList.toggle('visible', resolutions.length === 0);
    }
  }

  function clearFieldErrors(n) {
    if (n === 0) {
      var fields = ['wizTitle', 'wizDate', 'wizTimeHH', 'wizTimeMM'];
      fields.forEach(function(id) {
        var el = document.getElementById(id);
        if (el) el.classList.remove('field-error');
      });
      var msgs = ['errWizTitle', 'errWizDate', 'errWizTime'];
      msgs.forEach(function(id) {
        var el = document.getElementById(id);
        if (el) el.classList.remove('visible');
      });
    }
    if (n === 1) {
      var err = document.getElementById('errStep1Members');
      if (err) err.classList.remove('visible');
    }
    if (n === 2) {
      var err2 = document.getElementById('errStep2Reso');
      if (err2) err2.classList.remove('visible');
    }
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
      saveDraft();
    });

    mm.addEventListener('blur', function () {
      if (mm.value.length === 1) mm.value = mm.value.padStart(2, '0');
      saveDraft();
    });
  }

  /* ── Step 1 field blur listeners for auto-save ───── */

  function setupStep1Autosave() {
    var fieldIds = ['wizTitle', 'wizType', 'wizDate', 'wizPlace', 'wizAddr', 'wizQuorum', 'wizDefaultMaj'];
    fieldIds.forEach(function(id) {
      var el = document.getElementById(id);
      if (el) {
        el.addEventListener('blur', saveDraft);
        el.addEventListener('change', saveDraft);
      }
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

  /* ── Members list rendering ──────────────────────── */

  function renderMembersList() {
    var container = document.getElementById('wizMembersList');
    var countEl = document.getElementById('wizMemberCount');
    var voixEl  = document.getElementById('wizVoixTotal');

    if (countEl) countEl.textContent = members.length;

    var totalVoix = members.reduce(function(s, m) { return s + (parseInt(m.voix, 10) || 1); }, 0);
    if (voixEl) voixEl.textContent = totalVoix;

    if (!container) return;

    if (members.length === 0) {
      container.innerHTML = '<div class="muted" style="padding:8px 0;font-size:13px;">Aucun participant ajout&eacute;. Importez un fichier CSV ou ajoutez manuellement.</div>';
      return;
    }

    var html = '';
    members.forEach(function(m, i) {
      html += '<div class="member-row">' +
        '<span class="member-name">' + escapeHtml(m.name || m.nom || '') + '</span>' +
        '<span class="member-lot">' + escapeHtml(m.lot || '') + '</span>' +
        '<span class="member-votes">' + escapeHtml(String(m.voix || 1)) + ' voix</span>' +
        '<span class="member-actions">' +
          '<button class="btn btn-sm" type="button" onclick="(function(){window._wizRemoveMember(' + i + ');})()" aria-label="Supprimer">' +
            '<svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M18 6 6 18"/><path d="m6 6 12 12"/></svg>' +
          '</button>' +
        '</span>' +
      '</div>';
    });
    container.innerHTML = html;
  }

  // Expose remove handler globally (used in inline onclick above)
  window._wizRemoveMember = function(i) {
    members.splice(i, 1);
    renderMembersList();
    saveDraft();
  };

  /* ── CSV import ──────────────────────────────────── */

  function handleCsvFile(file) {
    if (!file) return;
    var reader = new FileReader();
    reader.onload = function(e) {
      var lines = e.target.result.split('\n');
      var rows  = lines.map(function(l) { return l.split(','); });
      // First row = headers; skip it if first cell looks like a label
      var startIdx = 0;
      if (rows.length > 0) {
        var firstCell = (rows[0][0] || '').trim().toLowerCase();
        if (firstCell === 'nom' || firstCell === 'name' || firstCell === 'prenom') {
          startIdx = 1;
        }
      }
      var imported = rows.slice(startIdx).filter(function(r) {
        return r.length >= 1 && (r[0] || '').trim().length > 0;
      }).map(function(r) {
        return {
          nom:   (r[0] || '').trim(),
          name:  (r[0] || '').trim(),
          lot:   r[1] ? r[1].trim() : '',
          email: r[2] ? r[2].trim() : '',
          voix:  r[3] ? parseInt(r[3].trim(), 10) || 1 : 1
        };
      });
      members = members.concat(imported);
      renderMembersList();
      saveDraft();
    };
    reader.readAsText(file);
  }

  function setupCsvImport() {
    var btnImport = document.getElementById('btnImportCSV');
    var fileInput = document.getElementById('csvFileInput');
    var dropZone  = document.getElementById('csvDropZone');
    var btnManual = document.getElementById('btnAddManual');

    if (btnImport && fileInput) {
      btnImport.addEventListener('click', function() { fileInput.click(); });
      fileInput.addEventListener('change', function() {
        if (fileInput.files && fileInput.files[0]) {
          handleCsvFile(fileInput.files[0]);
          fileInput.value = '';
        }
      });
    }

    if (dropZone) {
      dropZone.addEventListener('dragover', function(e) {
        e.preventDefault();
        dropZone.classList.add('dragover');
      });
      dropZone.addEventListener('dragleave', function() {
        dropZone.classList.remove('dragover');
      });
      dropZone.addEventListener('drop', function(e) {
        e.preventDefault();
        dropZone.classList.remove('dragover');
        var file = e.dataTransfer.files[0];
        if (file) handleCsvFile(file);
      });
    }

    if (btnManual) {
      btnManual.addEventListener('click', function() {
        var name = window.prompt('Nom du participant :');
        if (!name || !name.trim()) return;
        var lot  = window.prompt('Num\u00e9ro de lot (optionnel) :') || '';
        var voix = parseInt(window.prompt('Poids de vote (d\u00e9faut : 1) :') || '1', 10) || 1;
        members.push({ nom: name.trim(), name: name.trim(), lot: lot.trim(), voix: voix });
        renderMembersList();
        saveDraft();
      });
    }
  }

  /* ── Resolution list rendering (with drag-drop) ──── */

  function getDefaultMaj() {
    var sel = document.getElementById('wizDefaultMaj');
    return sel ? (sel.value || 'art24') : 'art24';
  }

  function majLabel(val) {
    var map = {
      'art24': 'Majorit\u00e9 simple (art. 24)',
      'art25': 'Majorit\u00e9 absolue (art. 25)',
      'art26': 'Double majorit\u00e9 (art. 26)',
      'unanimite': 'Unanimit\u00e9 (art. 26-1)'
    };
    return map[val] || val || 'Majorit\u00e9 simple (art. 24)';
  }

  function renderResoList() {
    var list = document.getElementById('wizResoList');
    if (!list) return;
    list.innerHTML = '';

    if (resolutions.length === 0) {
      list.innerHTML = '<div class="muted" style="padding:8px 0;font-size:13px;">Aucune r\u00e9solution ajout\u00e9e. Utilisez le formulaire ci-dessus.</div>';
      return;
    }

    resolutions.forEach(function(r, i) {
      var row = document.createElement('div');
      row.className = 'reso-row';
      row.draggable = true;
      row.dataset.index = i;

      var secretLabel = r.secret ? 'Vote secret' : 'Vote public';
      row.innerHTML =
        '<span class="reso-drag-handle" aria-hidden="true">&#x283F;</span>' +
        '<div class="reso-num">' + (i + 1) + '</div>' +
        '<div class="reso-body">' +
          '<div class="reso-title">' + escapeHtml(r.title) + '</div>' +
          (r.desc ? '<div class="reso-desc">' + escapeHtml(r.desc) + '</div>' : '') +
          '<div class="reso-meta">' +
            '<span>' + escapeHtml(majLabel(r.maj)) + '</span>' +
            '<span>' + escapeHtml(r.key || 'Charges g\u00e9n\u00e9rales') + '</span>' +
            '<span>' + escapeHtml(secretLabel) + '</span>' +
          '</div>' +
          '<div class="resolution-documents" data-motion-id="' + escapeHtml(String(r.id || '')) + '">' +
            '<h4>Documents joints</h4>' +
            '<div class="doc-list"></div>' +
            '<input type="file" class="filepond-input" name="filepond" accept="application/pdf">' +
          '</div>' +
        '</div>' +
        '<span class="reso-actions">' +
          '<button class="btn btn-sm" type="button" data-reso-del="' + i + '" aria-label="Supprimer">' +
            '<svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M18 6 6 18"/><path d="m6 6 12 12"/></svg>' +
          '</button>' +
        '</span>';

      // Drag events
      row.addEventListener('dragstart', onDragStart);
      row.addEventListener('dragover', onDragOver);
      row.addEventListener('dragleave', onDragLeave);
      row.addEventListener('drop', onDrop);
      row.addEventListener('dragend', onDragEnd);

      // Delete button (event delegation)
      var delBtn = row.querySelector('[data-reso-del]');
      if (delBtn) {
        delBtn.addEventListener('click', function() {
          var idx = parseInt(this.getAttribute('data-reso-del'), 10);
          resolutions.splice(idx, 1);
          saveDraft();
          renderResoList();
        });
      }

      list.appendChild(row);

      // Initialize FilePond upload for this resolution's document section
      var docsContainer = row.querySelector('.resolution-documents');
      if (docsContainer) {
        var motionId = r.id || '';
        var meetingId = ''; // populated once meeting is saved; pre-creation uploads use motion_id only
        initResolutionPond(docsContainer, motionId, meetingId);
        if (motionId) loadExistingDocs(docsContainer, motionId);
      }
    });
  }

  /* ── Resolution document upload (FilePond) ─────── */

  function initResolutionPond(containerEl, motionId, meetingId) {
    var inputEl = containerEl.querySelector('.filepond-input');
    if (!inputEl || inputEl._pondInitialized) return null;
    if (typeof FilePond === 'undefined') return null;

    FilePond.registerPlugin(
      FilePondPluginFileValidateType,
      FilePondPluginFileValidateSize
    );

    var pond = FilePond.create(inputEl, {
      acceptedFileTypes: ['application/pdf'],
      labelFileTypeNotAllowed: 'Seuls les fichiers PDF sont acceptes',
      fileValidateTypeLabelExpectedTypes: 'Format attendu : PDF',
      maxFileSize: '10MB',
      labelMaxFileSizeExceeded: 'Le fichier depasse 10 Mo',
      labelMaxFileSize: 'Taille maximale : 10 Mo',
      allowMultiple: true,
      server: {
        process: {
          url: '/api/v1/resolution_documents',
          method: 'POST',
          headers: function() {
            var meta = document.querySelector('meta[name="csrf-token"]');
            return {
              'X-Requested-With': 'XMLHttpRequest',
              'X-CSRF-Token': meta ? meta.content : ''
            };
          },
          ondata: function(formData) {
            if (motionId) formData.append('motion_id', motionId);
            if (meetingId) formData.append('meeting_id', meetingId);
            return formData;
          },
          onload: function(response) {
            try {
              var data = JSON.parse(response);
              var listEl = containerEl.querySelector('.doc-list');
              if (listEl && data.document) renderDocCard(listEl, data.document);
              return data.document ? data.document.id : '';
            } catch (e) { return ''; }
          },
          onerror: function(response) {
            try {
              var data = JSON.parse(response);
              if (window.AgToast) window.AgToast.show(data.error || 'Erreur lors du telechargement', 'error');
            } catch (e) {
              if (window.AgToast) window.AgToast.show('Erreur lors du telechargement', 'error');
            }
          }
        },
        revert: null
      },
      labelIdle: 'Glissez un PDF ici ou <span class="filepond--label-action">parcourir</span>'
    });

    inputEl._pondInitialized = true;
    return pond;
  }

  function renderDocCard(listEl, doc) {
    var card = document.createElement('div');
    card.className = 'doc-card';
    card.dataset.docId = doc.id;

    var sizeKb = Math.round((doc.file_size || 0) / 1024);
    var sizeLabel = sizeKb > 1024 ? (sizeKb / 1024).toFixed(1) + ' Mo' : sizeKb + ' Ko';

    card.innerHTML =
      '<span class="doc-card__icon">&#128196;</span>' +
      '<span class="doc-card__name">' + escapeHtml(doc.original_name || doc.filename || '') + '</span>' +
      '<span class="doc-card__size">' + escapeHtml(sizeLabel) + '</span>' +
      '<button class="doc-card__preview btn btn--ghost btn--sm" title="Aper\u00e7u" type="button">' +
        '<span class="icon">&#128065;</span>' +
      '</button>' +
      '<button class="doc-card__delete btn btn--ghost btn--sm btn--danger" title="Supprimer" type="button">' +
        '<span class="icon">&times;</span>' +
      '</button>';

    // Preview button — open ag-pdf-viewer in panel mode
    card.querySelector('.doc-card__preview').addEventListener('click', function() {
      var viewer = document.querySelector('ag-pdf-viewer') || document.createElement('ag-pdf-viewer');
      if (!viewer.parentElement) {
        viewer.setAttribute('mode', 'panel');
        viewer.setAttribute('allow-download', '');
        document.body.appendChild(viewer);
      }
      viewer.setAttribute('src', '/api/v1/resolution_document_serve?id=' + doc.id);
      viewer.setAttribute('filename', doc.original_name || doc.filename || 'document.pdf');
      viewer.open();
    });

    // Delete button — use ag-confirm dialog
    card.querySelector('.doc-card__delete').addEventListener('click', function() {
      if (!window.AgConfirm) return;
      window.AgConfirm.ask({
        title: 'Supprimer le document',
        message: 'Voulez-vous supprimer \u00ab\u00a0' + escapeHtml(doc.original_name || doc.filename || '') + '\u00a0\u00bb ?',
        confirmLabel: 'Supprimer',
        variant: 'danger'
      }).then(function(confirmed) {
        if (!confirmed) return;
        window.api('/api/v1/resolution_documents', { id: doc.id }, 'DELETE').then(function() {
          card.remove();
          if (window.AgToast) window.AgToast.show('Document supprim\u00e9', 'success');
        }).catch(function() {
          if (window.AgToast) window.AgToast.show('Erreur lors de la suppression', 'error');
        });
      });
    });

    listEl.appendChild(card);
  }

  function loadExistingDocs(containerEl, motionId) {
    var listEl = containerEl.querySelector('.doc-list');
    if (!listEl || !motionId) return;
    window.api('/api/v1/resolution_documents?motion_id=' + motionId).then(function(resp) {
      if (resp && resp.documents) {
        resp.documents.forEach(function(doc) {
          renderDocCard(listEl, doc);
        });
      }
    }).catch(function() { /* ignore — new resolution */ });
  }

  /* ── HTML5 Drag-and-drop handlers ────────────────── */

  function onDragStart(e) {
    dragSrcIdx = parseInt(this.dataset.index, 10);
    this.classList.add('dragging');
    e.dataTransfer.effectAllowed = 'move';
    e.dataTransfer.setData('text/plain', String(dragSrcIdx));
  }

  function onDragOver(e) {
    e.preventDefault();
    e.dataTransfer.dropEffect = 'move';
    this.classList.add('drag-over');
  }

  function onDragLeave() {
    this.classList.remove('drag-over');
  }

  function onDrop(e) {
    e.stopPropagation();
    this.classList.remove('drag-over');
    var targetIdx = parseInt(this.dataset.index, 10);
    if (dragSrcIdx !== null && !isNaN(dragSrcIdx) && dragSrcIdx !== targetIdx) {
      var moved = resolutions.splice(dragSrcIdx, 1)[0];
      resolutions.splice(targetIdx, 0, moved);
      saveDraft();
      renderResoList();
    }
    dragSrcIdx = null;
  }

  function onDragEnd() {
    dragSrcIdx = null;
    renderResoList(); // re-render clears drag states
  }

  /* ── Add resolution ──────────────────────────────── */

  function setupAddReso() {
    var btn = document.getElementById('btnAddReso');
    if (!btn) return;

    btn.addEventListener('click', function() {
      var titleEl = document.getElementById('resoTitle');
      var descEl  = document.getElementById('resoDesc');
      var majEl   = document.getElementById('resoMaj');
      var keyEl   = document.getElementById('resoKey');
      var chipOui = document.getElementById('chipSecret');

      var title = titleEl ? titleEl.value.trim() : '';
      if (!title) {
        if (titleEl) titleEl.classList.add('field-error');
        return;
      }
      if (titleEl) titleEl.classList.remove('field-error');

      resolutions.push({
        title:  title,
        desc:   descEl ? descEl.value.trim() : '',
        maj:    majEl  ? majEl.value  : getDefaultMaj(),
        key:    keyEl  ? keyEl.value  : 'Charges g\u00e9n\u00e9rales (d\u00e9faut)',
        secret: chipOui ? chipOui.classList.contains('active') : false
      });

      // Reset form
      if (titleEl) titleEl.value = '';
      if (descEl)  descEl.value  = '';
      if (majEl)   majEl.value   = getDefaultMaj();
      // Reset secret chip
      var chipNon = document.getElementById('chipNonSecret');
      if (chipNon) chipNon.classList.add('active');
      if (chipOui) chipOui.classList.remove('active');

      saveDraft();
      renderResoList();

      // Clear resolution count error if it was showing
      var errReso = document.getElementById('errStep2Reso');
      if (errReso) errReso.classList.remove('visible');
    });
  }

  /* ── Apply default majority from Step 1 to resoMaj ─ */

  function syncDefaultMaj() {
    var defaultMaj = getDefaultMaj();
    var majEl = document.getElementById('resoMaj');
    if (majEl && defaultMaj) {
      majEl.value = defaultMaj;
    }
  }

  /* ── Recap builder ───────────────────────────────── */

  function buildRecap() {
    var recap = document.getElementById('wizRecap');
    if (!recap) return;

    var title  = getId('wizTitle').value || '(non renseign\u00e9)';
    var type   = getId('wizType').value  || '';
    var date   = getId('wizDate').value  || '';
    var hh     = getId('wizTimeHH').value || '';
    var mm     = getId('wizTimeMM').value || '';
    var place  = getId('wizPlace').value  || '';
    var addr   = getId('wizAddr').value   || '';
    var quorum = getId('wizQuorum').value || '';
    var lieu   = [place, addr].filter(Boolean).join(', ') || '(non renseign\u00e9)';
    var time   = hh && mm ? hh + ':' + mm : '';
    var dateStr = date ? date + (time ? ' \u00e0 ' + time : '') : '(non renseign\u00e9e)';

    var rows = [
      ['Titre',         title],
      ['Type',          type],
      ['Date',          dateStr],
      ['Lieu',          lieu],
      ['Quorum',        quorum],
      ['Participants',  String(members.length) + ' participant(s)'],
      ['R\u00e9solutions', String(resolutions.length) + ' r\u00e9solution(s)']
    ];

    var html = '';
    rows.forEach(function(r, i) {
      html += '<div class="recap-row">' +
        '<span class="recap-label">' + escapeHtml(r[0]) + '</span>' +
        '<span class="recap-value">' + escapeHtml(r[1]) + '</span>' +
      '</div>';
    });
    recap.innerHTML = html;
  }

  /* ── API payload ─────────────────────────────────── */

  function buildPayload() {
    return {
      title:       getId('wizTitle').value  || '',
      type:        getId('wizType').value   || '',
      date:        getId('wizDate').value   || '',
      time:        (getId('wizTimeHH').value || '00') + ':' + (getId('wizTimeMM').value || '00'),
      place:       getId('wizPlace').value  || '',
      address:     getId('wizAddr').value   || '',
      quorum:      getId('wizQuorum').value || '',
      defaultMaj:  getId('wizDefaultMaj').value || '',
      members:     members,
      resolutions: resolutions
    };
  }

  /* ── Button bindings ─────────────────────────────── */

  function init() {
    setupTimeInput();
    setupChips();
    setupStep1Autosave();
    setupCsvImport();
    setupAddReso();

    // Restore draft before first showStep
    restoreDraft();

    // Sync default majority when step 2 becomes visible
    // (this is called when user navigates to step 2, so syncDefaultMaj runs on entry)

    // Navigation button bindings
    var btn0 = document.getElementById('btnNext0');
    if (btn0) {
      btn0.addEventListener('click', function() {
        if (!validateStep(0)) {
          showFieldErrors(0);
          return;
        }
        clearFieldErrors(0);
        saveDraft();
        showStep(1);
      });
    }

    var btnP1 = document.getElementById('btnPrev1');
    if (btnP1) btnP1.addEventListener('click', function() { showStep(0); });

    var btn1 = document.getElementById('btnNext1');
    if (btn1) {
      btn1.addEventListener('click', function() {
        if (!validateStep(1)) {
          showFieldErrors(1);
          return;
        }
        clearFieldErrors(1);
        saveDraft();
        syncDefaultMaj();
        showStep(2);
      });
    }

    var btnP2 = document.getElementById('btnPrev2');
    if (btnP2) btnP2.addEventListener('click', function() { showStep(1); });

    var btn2 = document.getElementById('btnNext2');
    if (btn2) {
      btn2.addEventListener('click', function() {
        if (!validateStep(2)) {
          showFieldErrors(2);
          return;
        }
        clearFieldErrors(2);
        saveDraft();
        buildRecap();
        showStep(3);
      });
    }

    var btnP3 = document.getElementById('btnPrev3');
    if (btnP3) btnP3.addEventListener('click', function() { showStep(2); });

    var btnPdf = document.getElementById('btnDownloadPdf');
    if (btnPdf) {
      btnPdf.addEventListener('click', function() {
        window.print();
      });
    }

    var btnCreate = document.getElementById('btnCreate');
    if (btnCreate) {
      btnCreate.addEventListener('click', function() {
        btnCreate.disabled = true;
        btnCreate.innerHTML =
          '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 6 9 17l-5-5"/></svg>' +
          ' Cr\u00e9ation\u2026';

        var payload = buildPayload();
        api('/api/v1/meetings', payload)
          .then(function(res) {
            if (!res.body || !res.body.ok) {
              var err = new Error(res.body && res.body.error || 'creation_failed');
              if (res.body && res.body.details) err.details = res.body.details;
              throw err;
            }
            clearDraft();
            var d = res.body.data || {};
            var totalMembers = (d.members_created || 0) + (d.members_linked || 0);
            var motions = d.motions_created || 0;
            var msg = 'S\u00e9ance cr\u00e9\u00e9e\u202f\u2022\u202f' + totalMembers + ' membre' + (totalMembers > 1 ? 's' : '') +
              '\u202f\u2022\u202f' + motions + ' r\u00e9solution' + (motions > 1 ? 's' : '');
            try {
              sessionStorage.setItem('ag-vote-toast', JSON.stringify({
                msg: msg,
                type: 'success'
              }));
            } catch (e) {}
            window.location.href = '/hub.htmx.html?id=' + (d.meeting_id || '');
          })
          .catch(function(err) {
            btnCreate.disabled = false;
            btnCreate.innerHTML =
              '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 6 9 17l-5-5"/></svg>' +
              ' Cr\u00e9er la s\u00e9ance';
            var msg = 'Erreur lors de la cr\u00e9ation. Veuillez r\u00e9essayer.';
            if (err && err.details && err.details[0] && err.details[0].message) {
              msg = err.details[0].message;
            }
            if (window.Shared && Shared.showToast) {
              Shared.showToast(msg, 'error');
            }
          });
      });
    }

    // Stepper items clickable (navigate back only)
    document.querySelectorAll('.wiz-step-item').forEach(function (item) {
      item.addEventListener('click', function () {
        var step = parseInt(item.getAttribute('data-step'), 10);
        if (!isNaN(step) && step <= currentStep) {
          showStep(step);
        }
      });
    });

    // Initial render — only show step 0 if no draft was restored
    // restoreDraft() calls showStep() if a draft exists; else show step 0
    if (localStorage.getItem(DRAFT_KEY) === null) {
      showStep(0);
    }
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
  } else {
    init();
  }
})();
