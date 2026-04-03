/* global FilePond, FilePondPluginFileValidateType, FilePondPluginFileValidateSize */
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

  var MOTION_TEMPLATES = [
    {
      id: 'approbation-comptes',
      label: 'Approbation des comptes',
      title: 'Approbation des comptes de l\u2019exercice',
      desc: 'L\u2019assembl\u00e9e approuve les comptes de l\u2019exercice \u00e9coul\u00e9 tels qu\u2019ils ont \u00e9t\u00e9 pr\u00e9sent\u00e9s par le pr\u00e9sident et le tr\u00e9sorier.'
    },
    {
      id: 'election-conseil',
      label: '\u00c9lection au conseil',
      title: '\u00c9lection des membres du conseil',
      desc: 'L\u2019assembl\u00e9e proc\u00e8de \u00e0 l\u2019\u00e9lection des membres du conseil d\u2019administration pour l\u2019exercice \u00e0 venir.'
    },
    {
      id: 'modification-reglement',
      label: 'Modification du r\u00e8glement',
      title: 'Modification du r\u00e8glement int\u00e9rieur',
      desc: 'L\u2019assembl\u00e9e approuve les modifications propos\u00e9es au r\u00e8glement int\u00e9rieur telles que pr\u00e9sent\u00e9es en s\u00e9ance.'
    }
  ];

  // In-memory state
  var members = [];
  var resolutions = [];

  // Drag-and-drop state
  var dragSrcIdx = null;

  // Unsaved changes dirty state (D-12)
  var _wizardSnapshot = null;
  var _wizardDirty = false;
  var _wizardSubmitted = false; // suppress warning after successful create

  function captureWizardSnapshot() {
    var form = document.querySelector('.wizard-form') || document.getElementById('step' + currentStep);
    if (!form) return;
    try {
      _wizardSnapshot = new FormData(form);
    } catch (e) {
      _wizardSnapshot = null;
    }
    _wizardDirty = false;
  }

  function isWizardDirty() {
    if (_wizardDirty) return true;
    if (!_wizardSnapshot) return false;
    var form = document.querySelector('.wizard-form') || document.getElementById('step' + currentStep);
    if (!form) return false;
    try {
      var current = new FormData(form);
      for (var pair of current) {
        if (_wizardSnapshot.get(pair[0]) !== pair[1]) return true;
      }
    } catch (e) { /* ignore — FormData not supported on this form */ }
    return false;
  }

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

  /* ── Motion template application ─────────────────── */

  function applyTemplate(tpl) {
    var titleEl = document.getElementById('resoTitle');
    var descEl = document.getElementById('resoDesc');
    if (titleEl) titleEl.value = tpl.title;
    if (descEl) descEl.value = tpl.desc;
    if (titleEl) titleEl.focus();
  }

  /* ── Step labels (subtitle + counter) ────────────── */

  var STEP_LABELS = [
    'Informations g\u00e9n\u00e9rales',
    'Participants',
    'R\u00e9solutions',
    'R\u00e9vision'
  ];

  /* ── Step navigation ─────────────────────────────── */

  function showStep(n, skipAnimation) {
    // Hide all error banners when navigating
    ['errBannerStep0', 'errBannerStep1', 'errBannerStep2'].forEach(function(id) {
      var b = document.getElementById(id);
      if (b) b.setAttribute('hidden', '');
    });

    var prev = document.getElementById('step' + currentStep);
    if (prev && n !== currentStep && !skipAnimation) {
      prev.classList.add('slide-out');
      setTimeout(function() {
        prev.classList.remove('active', 'slide-out');
      }, 180);
    } else if (prev && n !== currentStep) {
      // Skip animation (draft restore, first load)
      prev.classList.remove('active');
    }
    currentStep = n;
    var next = document.getElementById('step' + n);
    if (next) next.classList.add('active');

    updateStepper();

    // Update step counter (WIZARD-03: "Etape X sur 4")
    var counter = document.getElementById('stepNavCounter');
    if (counter) counter.textContent = 'Etape ' + (n + 1) + ' sur 4';

    // Update step subtitle in page header (WIZARD-01)
    var sub = document.getElementById('wizStepSubtitle');
    if (sub) sub.textContent = STEP_LABELS[n] || '';

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
          time:   (getId('wizTime').value || ''),
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
      if (s1.time)       { var ti = document.getElementById('wizTime');      if (ti) ti.value = s1.time; }
      // Legacy draft compat: convert hh+mm to time
      if (!s1.time && s1.hh) { var ti2 = document.getElementById('wizTime'); if (ti2) ti2.value = (s1.hh || '18') + ':' + (s1.mm || '00'); }
      if (s1.place)      { var p = document.getElementById('wizPlace');      if (p) p.value = s1.place; }
      if (s1.addr)       { var a = document.getElementById('wizAddr');       if (a) a.value = s1.addr; }
      if (s1.quorum)     { var q = document.getElementById('wizQuorum');     if (q) q.value = s1.quorum; }
      if (s1.defaultMaj) { var dm = document.getElementById('wizDefaultMaj'); if (dm) dm.value = s1.defaultMaj; }

      // Restore arrays
      members = Array.isArray(draft.members) ? draft.members : [];
      resolutions = Array.isArray(draft.resolutions) ? draft.resolutions : [];

      renderMembersList();
      renderResoList();

      // Resume at saved step — skip animation to avoid flash on page load
      showStep(draft.step || 0, true);
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
      var time  = (document.getElementById('wizTime') || {}).value || '';
      return (
        title.trim().length > 0 &&
        date.length > 0 &&
        /^\d{2}:\d{2}$/.test(time)
      );
    }
    if (n === 1) { return true; }
    if (n === 2) { return true; }
    return true;
  }

  function showFieldErrors(n) {
    if (n === 0) {
      var title = (document.getElementById('wizTitle') || {}).value || '';
      var date  = (document.getElementById('wizDate') || {}).value || '';
      var time  = (document.getElementById('wizTime') || {}).value || '';

      var titleEl = document.getElementById('wizTitle');
      var errTitle = document.getElementById('errWizTitle');
      var dateEl = document.getElementById('wizDate');
      var errDate = document.getElementById('errWizDate');
      var timeEl = document.getElementById('wizTime');
      var errTime = document.getElementById('errWizTime');

      var titleOk = title.trim().length > 0;
      var dateOk  = date.length > 0;
      var timeOk  = /^\d{2}:\d{2}$/.test(time);

      if (titleEl)  titleEl.classList.toggle('field-error', !titleOk);
      if (errTitle) errTitle.classList.toggle('visible', !titleOk);
      if (dateEl)   dateEl.classList.toggle('field-error', !dateOk);
      if (errDate)  errDate.classList.toggle('visible', !dateOk);
      if (timeEl)   timeEl.classList.toggle('field-error', !timeOk);
      if (errTime)  errTime.classList.toggle('visible', !timeOk);

      var errors = [];
      if (!titleOk) errors.push('Le titre est obligatoire');
      if (!dateOk)  errors.push('La date est obligatoire');
      if (!timeOk)  errors.push("L'heure est obligatoire");
      var banner0 = document.getElementById('errBannerStep0');
      var bannerText0 = document.getElementById('errBannerStep0Text');
      if (errors.length > 0) {
        if (bannerText0) bannerText0.textContent = errors.join(' \u2022 ');
        if (banner0) banner0.removeAttribute('hidden');
      } else {
        if (banner0) banner0.setAttribute('hidden', '');
      }
    }
  }

  function showBanner(step, message) {
    var banner = document.getElementById('errBannerStep' + step);
    var bannerText = document.getElementById('errBannerStep' + step + 'Text');
    if (banner && message) {
      if (bannerText) bannerText.textContent = message;
      banner.removeAttribute('hidden');
    } else if (banner) {
      banner.setAttribute('hidden', '');
    }
  }

  function clearFieldErrors(n) {
    if (n === 0) {
      var fields = ['wizTitle', 'wizDate', 'wizTime'];
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
  }

  /* ── Time input behavior ─────────────────────────── */

  function setupTimeInput() {
    // Native <input type="time"> — no custom logic needed
    var timeEl = document.getElementById('wizTime');
    if (timeEl) {
      timeEl.addEventListener('change', saveDraft);
    }
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
          email: r[1] ? r[1].trim() : '',
          voix:  r[2] ? parseInt(r[2].trim(), 10) || 1 : 1
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

    var btnInline = document.getElementById('btnAddMemberInline');
    if (btnInline) {
      btnInline.addEventListener('click', function() {
        var nameEl = document.getElementById('wizMemberName');
        var emailEl = document.getElementById('wizMemberEmail');
        var name = nameEl ? nameEl.value.trim() : '';
        if (!name) {
          if (nameEl) nameEl.classList.add('field-error');
          return;
        }
        if (nameEl) nameEl.classList.remove('field-error');
        var email = emailEl ? emailEl.value.trim() : '';
        members.push({ nom: name, name: name, email: email, voix: 1 });
        renderMembersList();
        saveDraft();
        if (nameEl) { nameEl.value = ''; nameEl.focus(); }
        if (emailEl) emailEl.value = '';
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
              AgToast.show('error', data.error || 'Erreur lors du telechargement');
            } catch (e) {
              AgToast.show('error', 'Erreur lors du telechargement');
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

  function initAttachmentPond(meetingId) {
    var containerEl = document.getElementById('wizAttachmentSection');
    var inputEl = document.getElementById('wizAttachmentPondInput');
    if (!containerEl || !inputEl || inputEl._pondInitialized) return null;
    if (typeof FilePond === 'undefined') return null;

    FilePond.registerPlugin(
      FilePondPluginFileValidateType,
      FilePondPluginFileValidateSize
    );

    var pond = FilePond.create(inputEl, {
      name: 'file',  // CRITICAL: matches api_file('file') in MeetingAttachmentController
      acceptedFileTypes: ['application/pdf'],
      labelFileTypeNotAllowed: 'Seuls les fichiers PDF sont acceptes',
      fileValidateTypeLabelExpectedTypes: 'Format attendu : PDF',
      maxFileSize: '10MB',
      labelMaxFileSizeExceeded: 'Le fichier depasse 10 Mo',
      labelMaxFileSize: 'Taille maximale : 10 Mo',
      allowMultiple: true,
      server: {
        process: {
          url: '/api/v1/meeting_attachments',
          method: 'POST',
          headers: function() {
            var meta = document.querySelector('meta[name="csrf-token"]');
            return {
              'X-Requested-With': 'XMLHttpRequest',
              'X-CSRF-Token': meta ? meta.content : ''
            };
          },
          ondata: function(formData) {
            formData.append('meeting_id', meetingId);
            return formData;
          },
          onload: function(response) {
            try {
              var data = JSON.parse(response);
              var listEl = document.getElementById('wizAttachmentList');
              if (listEl && data.attachment) renderAttachmentCard(listEl, data.attachment);
              return data.attachment ? data.attachment.id : '';
            } catch (e) { return ''; }
          },
          onerror: function(response) {
            try {
              var data = JSON.parse(response);
              AgToast.show('error', data.error || 'Erreur lors du telechargement');
            } catch (e) {
              AgToast.show('error', 'Erreur lors du telechargement');
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

  function renderAttachmentCard(listEl, att) {
    var card = document.createElement('div');
    card.className = 'doc-card';
    card.dataset.attId = att.id;
    var sizeKb = Math.round((att.file_size || 0) / 1024);
    var sizeLabel = sizeKb > 1024 ? (sizeKb / 1024).toFixed(1) + ' Mo' : sizeKb + ' Ko';
    card.innerHTML =
      '<span class="doc-card__icon">&#128196;</span>' +
      '<span class="doc-card__name">' + escapeHtml(att.original_name || '') + '</span>' +
      '<span class="doc-card__size">' + escapeHtml(sizeLabel) + '</span>';
    listEl.appendChild(card);
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
          AgToast.show('success', 'Document supprim\u00e9');
        }).catch(function() {
          AgToast.show('error', 'Erreur lors de la suppression');
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

    var addPanel = document.querySelector('.reso-add-panel');
    var triggerDiv = document.getElementById('resoAddTrigger');

    // Wire "Ajouter une resolution" trigger button to show/hide panel
    var triggerBtn = document.getElementById('btnShowResoPanel');
    if (triggerBtn && addPanel && triggerDiv) {
      triggerBtn.addEventListener('click', function() {
        addPanel.style.display = '';
        triggerDiv.style.display = 'none';
        var titleEl = document.getElementById('resoTitle');
        if (titleEl) titleEl.focus();
      });
    }

    btn.addEventListener('click', function() {
      var titleEl = document.getElementById('resoTitle');
      var descEl  = document.getElementById('resoDesc');
      var majEl   = document.getElementById('resoMaj');
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
        key:    'Charges g\u00e9n\u00e9rales',
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

      // Collapse add panel after successful add
      if (addPanel) addPanel.style.display = 'none';
      if (triggerDiv) triggerDiv.style.display = '';

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

  /* ── Review card builder ─────────────────────────── */

  function buildReviewCard() {
    var recap = document.getElementById('wizRecap');
    if (!recap) return;

    var title  = getId('wizTitle').value || '(non renseign\u00e9)';
    var type   = getId('wizType').value  || '';
    var date   = getId('wizDate').value  || '';
    var timeVal = getId('wizTime').value || '';
    var hh = timeVal.split(':')[0] || '';
    var mm = timeVal.split(':')[1] || '';
    var place  = getId('wizPlace').value  || '';
    var addr   = getId('wizAddr').value   || '';
    var dateStr = date ? date + (hh && mm ? ' \u00e0 ' + hh + ':' + mm : '') : '(non renseign\u00e9e)';
    var lieu = [place, addr].filter(Boolean).join(', ') || '(non renseign\u00e9)';

    var warnings = '';
    if (members.length === 0) {
      warnings += '<div class="review-warning">\u26a0 Aucun membre ajout\u00e9 \u2014 les votes ne pourront pas \u00eatre attribu\u00e9s</div>';
    }
    if (resolutions.length === 0) {
      warnings += '<div class="review-warning">\u26a0 Aucune r\u00e9solution \u2014 l\u2019ordre du jour est vide</div>';
    }

    var memberPreview = members.length === 0
      ? '<span class="review-empty">Aucun membre</span>'
      : members.slice(0, 5).map(function(m) { return escapeHtml(m.name || m.nom || ''); }).join(', ') +
        (members.length > 5 ? ' et ' + (members.length - 5) + ' autres' : '');

    var resoPreview = resolutions.length === 0
      ? '<span class="review-empty">Aucune r\u00e9solution</span>'
      : '<ol class="review-reso-list">' + resolutions.map(function(r) {
          return '<li>' + escapeHtml(r.title) + '</li>';
        }).join('') + '</ol>';

    var docCount = 0;
    resolutions.forEach(function(r) { if (r.docs && r.docs.length) docCount += r.docs.length; });

    var html =
      '<div class="review-grid">' +
        '<div>' +
          '<div class="review-section">' +
            '<div class="review-section-header">' +
              '<span class="review-section-title">Informations</span>' +
              '<button class="btn btn-sm btn-ghost review-modifier" type="button" data-goto="0">Modifier</button>' +
            '</div>' +
            '<div class="review-row"><span class="review-label">Titre</span><span class="review-value">' + escapeHtml(title) + '</span></div>' +
            '<div class="review-row"><span class="review-label">Type</span><span class="review-value">' + escapeHtml(type) + '</span></div>' +
            '<div class="review-row"><span class="review-label">Date</span><span class="review-value">' + escapeHtml(dateStr) + '</span></div>' +
            '<div class="review-row"><span class="review-label">Lieu</span><span class="review-value">' + escapeHtml(lieu) + '</span></div>' +
          '</div>' +
          '<div class="review-section">' +
            '<div class="review-section-header">' +
              '<span class="review-section-title">Documents</span>' +
            '</div>' +
            '<div class="review-row"><span class="review-label">Fichiers</span><span class="review-value">' + docCount + ' document' + (docCount !== 1 ? 's' : '') + ' joint' + (docCount !== 1 ? 's' : '') + '</span></div>' +
          '</div>' +
        '</div>' +
        '<div>' +
          '<div class="review-section">' +
            '<div class="review-section-header">' +
              '<span class="review-section-title">Membres</span>' +
              '<button class="btn btn-sm btn-ghost review-modifier" type="button" data-goto="1">Modifier</button>' +
            '</div>' +
            '<div class="review-row"><span class="review-label">Total</span><span class="review-value">' + members.length + ' participant' + (members.length !== 1 ? 's' : '') + '</span></div>' +
            '<div class="review-row"><span class="review-label">Aper\u00e7u</span><span class="review-value">' + memberPreview + '</span></div>' +
          '</div>' +
          '<div class="review-section">' +
            '<div class="review-section-header">' +
              '<span class="review-section-title">R\u00e9solutions</span>' +
              '<button class="btn btn-sm btn-ghost review-modifier" type="button" data-goto="2">Modifier</button>' +
            '</div>' +
            '<div class="review-row"><span class="review-label">Total</span><span class="review-value">' + resolutions.length + ' r\u00e9solution' + (resolutions.length !== 1 ? 's' : '') + '</span></div>' +
            '<div class="review-row review-row-block">' + resoPreview + '</div>' +
          '</div>' +
        '</div>' +
      '</div>' +
      warnings;

    recap.innerHTML = html;

    recap.querySelectorAll('.review-modifier').forEach(function(btn) {
      btn.addEventListener('click', function() {
        var step = parseInt(btn.getAttribute('data-goto'), 10);
        showStep(step);
      });
    });
  }

  /* ── API payload ─────────────────────────────────── */

  function buildPayload() {
    return {
      title:       getId('wizTitle').value  || '',
      type:        getId('wizType').value   || '',
      date:        getId('wizDate').value   || '',
      time:        getId('wizTime').value || '18:00',
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

    // Unsaved changes tracking (D-12)
    document.addEventListener('input', function(e) {
      if (e.target.closest('.wiz-step')) _wizardDirty = true;
    }, true);
    document.addEventListener('change', function(e) {
      if (e.target.closest('.wiz-step')) _wizardDirty = true;
    }, true);

    window.addEventListener('beforeunload', function(e) {
      if (!_wizardSubmitted && (isWizardDirty() || members.length > 0 || resolutions.length > 0)) {
        e.preventDefault();
        e.returnValue = '';
      }
    });

    if (window.Shell && Shell.beforeNavigate) {
      Shell.beforeNavigate(async function() {
        if (!_wizardSubmitted && (isWizardDirty() || members.length > 0 || resolutions.length > 0)) {
          return await AgConfirm.ask({
            title: 'Quitter sans enregistrer ?',
            message: 'Vos modifications seront perdues.',
            confirmLabel: 'Quitter la page',
            variant: 'warning'
          });
        }
        return true;
      });
    }

    // Restore draft before first showStep
    restoreDraft();

    // Capture initial snapshot after draft restore (for dirty tracking)
    setTimeout(captureWizardSnapshot, 0);

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
        saveDraft();
        buildReviewCard();
        showStep(3);
      });
    }

    var btnP3 = document.getElementById('btnPrev3');
    if (btnP3) btnP3.addEventListener('click', function() { showStep(2); });

    // Wire template quick-select buttons
    document.querySelectorAll('.wiz-template-btn').forEach(function(btn) {
      btn.addEventListener('click', function() {
        var id = btn.getAttribute('data-template');
        var tpl = MOTION_TEMPLATES.filter(function(t) { return t.id === id; })[0];
        if (tpl) applyTemplate(tpl);
      });
    });

    var createdMeetingId = null;

    var btnGoToHub = document.getElementById('btnGoToHub');
    if (btnGoToHub) {
      btnGoToHub.addEventListener('click', function() {
        window.location.href = '/hub/' + createdMeetingId;
      });
    }

    var btnCreate = document.getElementById('btnCreate');
    if (btnCreate) {
      btnCreate.addEventListener('click', function() {
        Shared.btnLoading(btnCreate, true, 'Cr\u00e9ation\u2026');

        var payload = buildPayload();
        api('/api/v1/meetings', payload)
          .then(function(res) {
            if (!res.body || !res.body.ok) {
              var err = new Error(res.body && res.body.error || 'creation_failed');
              if (res.body && res.body.details) err.details = res.body.details;
              throw err;
            }
            clearDraft();
            _wizardSubmitted = true;
            var d = res.body.data || {};
            createdMeetingId = d.meeting_id || '';
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

            // Show attachment section instead of immediate redirect
            var attachSection = document.getElementById('wizAttachmentSection');
            if (attachSection) attachSection.hidden = false;

            // Hide recap and warning
            var recap = document.getElementById('wizRecap');
            if (recap) recap.hidden = true;
            var warnRecap = document.querySelector('.alert-warn-recap');
            if (warnRecap) warnRecap.hidden = true;

            // Hide the step-nav bar
            var stepNav = document.querySelector('#step3 .step-nav');
            if (stepNav) stepNav.hidden = true;

            // Initialize attachment pond
            initAttachmentPond(createdMeetingId);
          })
          .catch(function(err) {
            Shared.btnLoading(btnCreate, false);
            var msg = 'Erreur lors de la cr\u00e9ation. Veuillez r\u00e9essayer.';
            if (err && err.details && err.details[0] && err.details[0].message) {
              msg = err.details[0].message;
            }
            AgToast.show('error', msg, 8000);
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
      // Smart defaults — pre-fill date and time on fresh visits only
      var dateEl = document.getElementById('wizDate');
      if (dateEl && !dateEl.value) {
        var today = new Date();
        var y = today.getFullYear();
        var m = String(today.getMonth() + 1).padStart(2, '0');
        var d = String(today.getDate()).padStart(2, '0');
        dateEl.value = y + '-' + m + '-' + d;
      }
      var timeEl = document.getElementById('wizTime');
      if (timeEl && !timeEl.value) timeEl.value = '18:00';
      showStep(0, true);
    }
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
  } else {
    init();
  }
})();
