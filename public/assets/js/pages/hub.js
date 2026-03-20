/* GO-LIVE-STATUS: ready — Hub JS. 6 étapes guidées, stepper cliquable, checklist. */
/**
 * Hub — Fiche séance (6-step guided flow).
 * Shows one action at a time, tracks progress.
 * Phase 8.2: horizontal status bar (HUB-01), standalone checklist (HUB-04),
 *             dynamic action card (HUB-02), KPI CSS classes (HUB-03),
 *             documents panel CSS classes (HUB-05), toast pickup from wizard.
 */
(function () {
  'use strict';

  function escapeHtml(s) {
    if (!s) return '';
    return String(s)
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;')
      .replace(/'/g, '&#39;');
  }

  /* ── Step definitions (from wireframe HUB_STEPS) ──── */

  var HUB_STEPS = [
    {
      id: 'preparation', num: 1,
      titre: 'Pr\u00e9parer la s\u00e9ance',
      titreAction: 'Compl\u00e9ter les informations',
      descAction: 'Ajoutez les derniers d\u00e9tails manquants : lieu, heure, r\u00e9solutions restantes.',
      icon: 'edit', color: 'var(--color-primary)', dest: '/wizard.htmx.html',
      btnLabel: 'Ouvrir la pr\u00e9paration'
    },
    {
      id: 'convocations', num: 2,
      titre: 'Envoyer les convocations',
      titreAction: 'Envoyer les 12 convocations restantes',
      descAction: '55 participants ont d\u00e9j\u00e0 re\u00e7u la convocation. Il reste 12 envois \u00e0 d\u00e9clencher avant le 22/02/2026.',
      icon: 'send', color: 'var(--color-warning)', dest: null,
      btnLabel: 'Envoyer les convocations',
      btnPreview: true
    },
    {
      id: 'presences', num: 3,
      titre: 'Pointer les pr\u00e9sences',
      titreAction: 'Accueillir les participants',
      descAction: 'La s\u00e9ance commence. Enregistrez les arriv\u00e9es et les procurations au fur et \u00e0 mesure.',
      icon: 'users', color: 'var(--color-primary)', dest: '/operator.htmx.html',
      btnLabel: 'Ouvrir le pointage'
    },
    {
      id: 'vote', num: 4,
      titre: 'Piloter les votes',
      titreAction: 'La s\u00e9ance est en cours',
      descAction: 'Les votes sont ouverts. Pilotez chaque r\u00e9solution depuis l\u2019espace op\u00e9rateur en direct.',
      icon: 'play', color: 'var(--color-danger)', dest: '/operator.htmx.html',
      btnLabel: 'Rejoindre la s\u00e9ance en direct'
    },
    {
      id: 'cloture', num: 5,
      titre: 'Cl\u00f4turer et envoyer le PV',
      titreAction: 'Finaliser et archiver',
      descAction: 'La s\u00e9ance est termin\u00e9e. Validez le PV, apposez la signature \u00e9lectronique et envoyez.',
      icon: 'file-text', color: 'var(--color-purple)', dest: '/postsession.htmx.html',
      btnLabel: 'G\u00e9n\u00e9rer et envoyer le PV'
    },
    {
      id: 'archive', num: 6,
      titre: 'S\u00e9ance archiv\u00e9e',
      titreAction: 'S\u00e9ance archiv\u00e9e',
      descAction: 'Tout est termin\u00e9. Le PV est envoy\u00e9 et archiv\u00e9.',
      icon: 'archive', color: 'var(--color-success)', dest: '/archives.htmx.html',
      btnLabel: 'Consulter l\u2019archive'
    }
  ];

  var SVG_ICONS = {
    'edit': '<path d="M17 3a2.85 2.83 0 1 1 4 4L7.5 20.5 2 22l1.5-5.5Z"/><path d="m15 5 4 4"/>',
    'send': '<path d="M14.536 21.686a.5.5 0 0 0 .937-.024l6.5-19a.496.496 0 0 0-.635-.635l-19 6.5a.5.5 0 0 0-.024.937l7.93 3.18a2 2 0 0 1 1.112 1.11z"/><path d="m21.854 2.147-10.94 10.939"/>',
    'users': '<path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M22 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/>',
    'play': '<polygon points="6 3 20 12 6 21 6 3"/>',
    'file-text': '<path d="M15 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V7Z"/><path d="M14 2v4a2 2 0 0 0 2 2h4"/><path d="M10 9H8"/><path d="M16 13H8"/><path d="M16 17H8"/>',
    'archive': '<rect width="20" height="5" x="2" y="3" rx="1"/><path d="M4 8v11a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8"/><path d="M10 12h4"/>',
    'check': '<path d="M20 6 9 17l-5-5"/>',
    'checkCircle': '<path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><path d="m9 11 3 3L22 4"/>',
    'circle': '<circle cx="12" cy="12" r="10"/>',
    'file': '<path d="M15 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V7Z"/><path d="M14 2v4a2 2 0 0 0 2 2h4"/>'
  };

  function svgIcon(name, size, color) {
    size = size || 18;
    color = color || 'currentColor';
    var paths = SVG_ICONS[name] || SVG_ICONS['circle'];
    return '<svg width="' + size + '" height="' + size + '" viewBox="0 0 24 24" fill="none" stroke="' + escapeHtml(color) + '" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' + paths + '</svg>';
  }

  var currentStep = 1; // Default to convocations step

  /* ── Horizontal colorful status bar (HUB-01) ─── */

  function renderStatusBar() {
    var bar = document.getElementById('hubStatusBar');
    if (!bar) return;
    var html = '';
    HUB_STEPS.forEach(function (s, i) {
      var isDone = i < currentStep;
      var isActive = i === currentStep;
      // Dynamic color is the only acceptable inline style here (JS-driven)
      var color = isDone ? 'var(--color-success)' : isActive ? s.color : 'var(--color-border)';
      var cls = 'hub-bar-segment' + (isActive ? ' active' : isDone ? ' done' : '');
      html += '<div class="' + cls + '" style="background:' + color + '" title="' + escapeHtml(s.titre) + '">' +
        '<span class="hub-bar-label">' + escapeHtml(s.titre) + '</span>' +
      '</div>';
    });
    bar.innerHTML = html;
  }

  /* ── Standalone preparation checklist (HUB-04) ─── */

  var CHECKLIST_ITEMS = [
    { key: 'title',        label: 'Titre d\u00e9fini',           autoCheck: function (d) { return !!d.title; }, blockedReason: null },
    { key: 'date',         label: 'Date fix\u00e9e',              autoCheck: function (d) { return !!d.date; }, blockedReason: null },
    { key: 'members',      label: 'Membres ajout\u00e9s',         autoCheck: function (d) { return d.memberCount > 0; }, blockedReason: null },
    { key: 'resolutions',  label: 'R\u00e9solutions cr\u00e9\u00e9es',  autoCheck: function (d) { return d.resolutionCount > 0; }, blockedReason: null },
    { key: 'convocations', label: 'Convocations envoy\u00e9es',   autoCheck: function (d) { return d.convocationsSent; },
      blockedReason: function(d) {
        if (!d.memberCount) return 'Disponible apr\u00e8s ajout des membres';
        if (!d.resolutionCount) return 'Disponible apr\u00e8s ajout des r\u00e9solutions';
        return null;
      }
    },
    { key: 'documents',    label: 'Documents attach\u00e9s',      autoCheck: function (d) { return d.documentCount > 0; },
      blockedReason: function(d) {
        if (!d.resolutionCount) return 'Disponible apr\u00e8s ajout des r\u00e9solutions';
        return null;
      }
    }
  ];

  function renderChecklist(sessionData) {
    var container = document.getElementById('hubChecklist');
    if (!container) return;
    container.removeAttribute('aria-busy');

    var done = 0;
    var itemsHtml = '';

    CHECKLIST_ITEMS.forEach(function (item) {
      var checked = item.autoCheck(sessionData);
      if (checked) done++;
      itemsHtml += '<div class="hub-check-item' + (checked ? ' done' : '') + '">' +
        '<div class="hub-check-icon">' + (checked ? svgIcon('check', 12, '#fff') : '') + '</div>' +
        '<span class="hub-check-label">' + escapeHtml(item.label) + '</span>' +
        (function() {
          if (checked) return '<span class="hub-check-done-badge">Fait</span>';
          var reason = item.blockedReason ? item.blockedReason(sessionData) : null;
          if (reason) return '<span class="hub-check-blocked">' + escapeHtml(reason) + '</span>';
          return '<span class="hub-check-todo">\u00c0 faire</span>';
        })() +
      '</div>';
    });

    var pct = Math.round(done / CHECKLIST_ITEMS.length * 100);
    // Dynamic width is the only acceptable inline style here (JS-driven progress)
    container.innerHTML =
      '<div class="hub-checklist-header">' +
        '<span class="hub-checklist-title">Pr\u00e9paration</span>' +
        '<span class="hub-checklist-progress-text">' + done + ' / ' + CHECKLIST_ITEMS.length + '</span>' +
      '</div>' +
      '<div class="hub-checklist-bar">' +
        '<div class="hub-checklist-bar-fill" style="--bar-pct:' + pct + '%"></div>' +
      '</div>' +
      itemsHtml;
  }

  /* ── Render stepper ──────────────────────────────── */

  function renderStepper() {
    var container = document.getElementById('hubStepper');
    if (!container) return;

    var html = '';
    HUB_STEPS.forEach(function (s, i) {
      var isDone = i < currentStep;
      var isActive = i === currentStep;
      var numClass = isDone ? ' done' : isActive ? ' active' : '';
      var numContent = isDone ? svgIcon('check', 16, '#fff') : String(s.num);

      html += '<button class="hub-step-row" data-step="' + i + '"' +
        (isActive ? ' aria-current="step"' : '') +
        ' aria-label="' + (isDone ? '\u00c9tape termin\u00e9e\u00a0: ' : 'Aller \u00e0 ') + escapeHtml(s.titre) + '">' +
        '<div class="hub-step-num' + numClass + '">' + numContent + '</div>' +
        '<div class="hub-step-text">' +
          '<div class="hub-step-title' + (isDone ? ' done' : isActive ? ' active' : '') + '">' + escapeHtml(s.titre) + '</div>' +
          (isActive ? '<div class="hub-step-here">\u25b6 \u00c9tape en cours</div>' : '') +
        '</div>' +
      '</button>';

      if (i < HUB_STEPS.length - 1) {
        html += '<div class="hub-step-line' + (isDone ? ' done' : '') + '"></div>';
      }
    });
    container.innerHTML = html;

    // Bind clicks
    container.querySelectorAll('.hub-step-row').forEach(function (btn) {
      btn.addEventListener('click', function () {
        var step = parseInt(btn.getAttribute('data-step'), 10);
        if (!isNaN(step)) {
          currentStep = step;
          render();
        }
      });
    });
  }

  /* ── Render action card (HUB-02) ──────────────────── */

  function renderAction() {
    var step = HUB_STEPS[currentStep];
    if (!step) return;

    var icon = document.getElementById('hubActionIcon');
    var svg = document.getElementById('hubActionSvg');
    var title = document.getElementById('hubActionTitle');
    var desc = document.getElementById('hubActionDesc');
    var btn = document.getElementById('hubMainBtn');
    var preview = document.getElementById('hubPreviewBtn');

    // Dynamic background color per stage — acceptable inline style from JS
    if (icon) icon.style.background = step.color;
    if (svg) svg.innerHTML = SVG_ICONS[step.icon] || SVG_ICONS['circle'];
    if (title) title.textContent = step.titreAction;
    if (desc) desc.textContent = step.descAction;

    if (btn) {
      btn.textContent = step.btnLabel;
      if (step.dest) {
        btn.href = step.dest;
        btn.style.cursor = '';
      } else {
        btn.removeAttribute('href');
        btn.style.cursor = 'pointer';
      }
    }

    if (preview) {
      preview.hidden = !step.btnPreview;
    }
  }

  /* ── Render KPI cards (HUB-03) ──────────────────── */

  function renderKpis(data) {
    var fields = {
      hubKpiParticipants: data.kpiParticipants || '-',
      hubKpiVoix:         data.kpiVoix || '- voix',
      hubKpiResolutions:  data.kpiResolutions || '-',
      hubKpiResoDetail:   data.kpiResoDetail || '',
      hubKpiQuorum:       data.kpiQuorum || '-',
      hubKpiQuorumDetail: data.kpiQuorumDetail || '',
      hubKpiConvoc:       data.kpiConvoc || '-',
      hubKpiConvocDetail: data.kpiConvocDetail || ''
    };
    Object.keys(fields).forEach(function (id) {
      var el = document.getElementById(id);
      if (el) el.textContent = fields[id];
    });
  }

  /* ── Render documents panel (HUB-05) ─────────────── */

  function renderDocuments(files) {
    var docs = document.getElementById('hubDocsList');
    if (!docs) return;
    if (!files || files.length === 0) {
      docs.innerHTML = '<p class="text-muted" style="font-size:13px;margin:8px 0;">Aucun document attach\u00e9.</p>';
      return;
    }
    var html = '';
    files.forEach(function (f) {
      html += '<div class="hub-doc-item">' +
        svgIcon('file', 14, 'var(--color-primary)') +
        (f.url
          ? '<a class="hub-doc-link" href="' + escapeHtml(f.url) + '" download>' + escapeHtml(f.name) + '</a>'
          : '<span class="hub-doc-link">' + escapeHtml(f.name) + '</span>') +
        '<span class="hub-doc-size">' + escapeHtml(f.size) + '</span>' +
      '</div>';
    });
    docs.innerHTML = html;
  }

  /* ── Quorum progress bar / hero (HUB-04) ───────────────── */

  function renderQuorumBar(sessionData) {
    var section = document.getElementById('hubQuorumSection');
    var bar = document.getElementById('hubQuorumBar');
    var pctEl = document.getElementById('hubQuorumPct');
    if (!bar || !section) return;
    var total = sessionData.memberCount || 0;
    var required = sessionData.quorumRequired || 0;
    var current = sessionData.presentCount || 0;
    if (total === 0) { section.style.display = 'none'; return; }
    section.style.display = '';
    bar.setAttribute('current', String(current));
    bar.setAttribute('required', String(required));
    bar.setAttribute('total', String(total));
    if (required && total) {
      var thresholdPct = Math.round(required / total * 100);
      bar.setAttribute('label',
        'Pr\u00e9sents\u202f: ' + current + '/' + total +
        ' \u2014 Seuil\u202f: ' + thresholdPct + '%\u202f=\u202f' + required + ' membres'
      );
    }
    // Update hero percentage display
    if (pctEl) {
      var presentPct = total > 0 ? Math.round(current / total * 100) : 0;
      pctEl.textContent = presentPct + '%';
      pctEl.className = 'hub-quorum-hero-pct';
      if (required > 0) {
        if (current >= required) {
          pctEl.classList.add('reached');
        } else if (current >= required * 0.75) {
          pctEl.classList.add('partial');
        } else {
          pctEl.classList.add('critical');
        }
      }
    }
  }

  /* ── Motions list with doc badges (WIZ-08) ─────── */

  function renderMotionsList(motions, meetingId) {
    var section = document.getElementById('hubMotionsSection');
    var list = document.getElementById('hubMotionsList');
    if (!list || !section) return;
    if (!motions || !motions.length) { section.style.display = 'none'; return; }
    section.style.display = '';
    var html = '';
    motions.forEach(function(m, i) {
      html += '<div class="hub-motion-item">' +
        '<span class="hub-motion-num">' + (i + 1) + '</span>' +
        '<span class="hub-motion-title">' + escapeHtml(m.title || m.name || '') + '</span>' +
        '<span class="doc-badge doc-badge--empty" data-motion-doc-badge data-motion-id="' + escapeHtml(String(m.id || '')) + '">Aucun document</span>' +
      '</div>';
    });
    list.innerHTML = html;
    loadDocBadges(motions, meetingId);
  }

  /* ── Convocation send button (WIZ-06) ───────────── */

  function setupConvocationBtn(sessionData, sessionId) {
    var section = document.getElementById('hubConvocationSection');
    var btn = document.getElementById('btnSendConvocations');
    if (!btn || !section) return;
    if (sessionData.convocationsSent || !sessionData.memberCount) {
      section.style.display = 'none';
      return;
    }
    section.style.display = '';
    btn.addEventListener('click', function() {
      if (!window.AgConfirm) return;
      window.AgConfirm.ask({
        title: 'Envoyer les convocations',
        message: 'Envoyer les convocations \u00e0 ' + sessionData.memberCount + ' membres\u202f?',
        confirmLabel: 'Envoyer',
        variant: 'info'
      }).then(function(ok) {
        if (!ok) return;
        btn.disabled = true;
        btn.textContent = 'Envoi en cours\u2026';
        window.api('/api/v1/meetings/' + encodeURIComponent(sessionId) + '/convocations', {}, 'POST')
          .then(function() {
            if (window.AgToast) window.AgToast.show('Convocations envoy\u00e9es', 'success');
            section.style.display = 'none';
            loadData();
          })
          .catch(function() {
            btn.disabled = false;
            btn.textContent = 'Envoyer les convocations';
            if (window.AgToast) window.AgToast.show('Erreur lors de l\u2019envoi des convocations', 'error');
          });
      });
    });
  }

  /* ── Resolution document badges (per-motion) ─────── */

  function loadDocBadges(motions, meetingId) {
    if (!motions || !motions.length || !meetingId) return;
    motions.forEach(function(motion) {
      window.api('/api/v1/resolution_documents?motion_id=' + encodeURIComponent(motion.id)).then(function(resp) {
        var count = (resp && resp.documents) ? resp.documents.length : 0;
        renderDocBadge(motion.id, count, resp && resp.documents ? resp.documents : []);
      }).catch(function() {
        renderDocBadge(motion.id, 0, []);
      });
    });
  }

  function renderDocBadge(motionId, docCount, docs) {
    var badges = document.querySelectorAll('[data-motion-doc-badge][data-motion-id="' + motionId + '"]');
    badges.forEach(function(badge) {
      if (docCount === 0) {
        badge.textContent = 'Aucun document';
        badge.className = 'doc-badge doc-badge--empty';
        badge.onclick = null;
        badge.style.cursor = '';
      } else {
        badge.textContent = docCount + (docCount > 1 ? ' documents joints' : ' document joint');
        badge.className = 'doc-badge doc-badge--has-docs';
        badge.style.cursor = 'pointer';
        badge.onclick = function() { openDocViewer(motionId, docs); };
      }
    });
  }

  function openDocViewer(motionId, cachedDocs) {
    function doOpen(docs) {
      if (!docs || docs.length === 0) return;
      var viewer = document.querySelector('ag-pdf-viewer') || document.createElement('ag-pdf-viewer');
      if (!viewer.parentElement) {
        viewer.setAttribute('mode', 'panel');
        viewer.setAttribute('allow-download', '');
        document.body.appendChild(viewer);
      }
      var doc = docs[0];
      viewer.setAttribute('src', '/api/v1/resolution_document_serve?id=' + encodeURIComponent(doc.id));
      viewer.setAttribute('filename', doc.original_name || 'document.pdf');
      if (typeof viewer.open === 'function') viewer.open();
    }

    if (cachedDocs && cachedDocs.length) {
      doOpen(cachedDocs);
    } else {
      window.api('/api/v1/resolution_documents?motion_id=' + encodeURIComponent(motionId)).then(function(resp) {
        doOpen(resp && resp.documents ? resp.documents : []);
      }).catch(function() {});
    }
  }

  function render() {
    renderStatusBar();
    renderStepper();
    renderAction();
  }

  /* ── Details toggle ──────────────────────────────── */

  function setupDetails() {
    var toggle = document.getElementById('hubDetailsToggle');
    var body = document.getElementById('hub-details-body');
    var chevron = document.getElementById('hubDetailsChevron');
    if (!toggle || !body) return;

    toggle.addEventListener('click', function () {
      var open = !body.hidden;
      body.hidden = open;
      toggle.setAttribute('aria-expanded', String(!open));
      if (chevron) {
        chevron.innerHTML = open ? '<path d="m6 9 6 6 6-6"/>' : '<path d="m18 15-6-6-6 6"/>';
      }
    });
  }

  /* ── Load data (real API, no demo fallback) ──────── */

  function applySessionToDOM(sessionData) {
    var titleEl = document.getElementById('hubTitle');
    var dateEl = document.getElementById('hubDate');
    var placeEl = document.getElementById('hubPlace');
    var participantsEl = document.getElementById('hubParticipants');
    var typeTagEl = document.getElementById('hubTypeTag');
    var statusTagEl = document.getElementById('hubStatusTag');

    if (titleEl) titleEl.textContent = sessionData.title || '';
    if (dateEl) dateEl.textContent = sessionData.dateDisplay || '';
    if (placeEl) placeEl.textContent = sessionData.place || '';
    if (participantsEl) participantsEl.textContent = (sessionData.memberCount || 0) + ' participants';
    if (typeTagEl) typeTagEl.textContent = sessionData.type_label || 'AG';
    if (statusTagEl) statusTagEl.textContent = sessionData.status_label || sessionData.status || 'En pr\u00e9paration';
  }

  function mapApiDataToSession(data) {
    var normalized = Object.assign({}, data);
    if (data.meeting_title && !data.title) normalized.title = data.meeting_title;
    if (data.meeting_status && !data.status) normalized.status = data.meeting_status;
    if (typeof data.members_count === 'number' && !data.member_count) normalized.member_count = data.members_count;
    if (typeof data.motions_total === 'number' && !data.resolution_count) normalized.resolution_count = data.motions_total;
    data = normalized;

    var memberCount = 0;
    if (Array.isArray(data.members)) {
      memberCount = data.members.length;
    } else if (typeof data.participants === 'number') {
      memberCount = data.participants;
    } else if (typeof data.member_count === 'number') {
      memberCount = data.member_count;
    }

    var resolutionCount = 0;
    if (Array.isArray(data.resolutions)) {
      resolutionCount = data.resolutions.length;
    } else if (typeof data.resolution_count === 'number') {
      resolutionCount = data.resolution_count;
    }

    var documentCount = 0;
    if (Array.isArray(data.documents)) {
      documentCount = data.documents.length;
    } else if (typeof data.document_count === 'number') {
      documentCount = data.document_count;
    }

    var convocationsSent = !!(data.convocation_status === 'sent' || data.convocations_sent);

    var dateDisplay = data.date || '';
    if (data.date && data.time) {
      dateDisplay = data.date + ' \u00e0 ' + data.time;
    }

    return {
      title: data.title || '',
      date: data.date || '',
      dateDisplay: dateDisplay,
      place: [data.place, data.address].filter(Boolean).join(', ') || '',
      memberCount: memberCount,
      resolutionCount: resolutionCount,
      convocationsSent: convocationsSent,
      documentCount: documentCount,
      quorumRequired: data.quorum_required || (data.quorum_policy ? Math.ceil(memberCount * 0.5) + 1 : 0),
      presentCount: data.present_count || 0,
      motions: Array.isArray(data.resolutions) ? data.resolutions : (Array.isArray(data.motions) ? data.motions : []),
      kpiParticipants: String(memberCount || '-'),
      kpiVoix: data.kpi_voix || (memberCount ? memberCount + ' voix' : '-'),
      kpiResolutions: String(resolutionCount || '-'),
      kpiResoDetail: data.kpi_reso_detail || '',
      kpiQuorum: data.kpi_quorum || '-',
      kpiQuorumDetail: data.kpi_quorum_detail || '',
      kpiConvoc: data.kpi_convoc || (convocationsSent ? memberCount + '/' + memberCount : '-'),
      kpiConvocDetail: data.kpi_convoc_detail || ''
    };
  }

  function showHubError() {
    if (window.Shared && Shared.showToast) {
      Shared.showToast('Impossible de charger la s\u00e9ance.', 'error');
    }
    var content = document.getElementById('hubContent') || document.querySelector('.hub-main');
    if (content) {
      var banner = document.createElement('div');
      banner.className = 'hub-error';
      banner.innerHTML =
        '<p style="margin:0 0 12px;">Impossible de charger les donn\u00e9es de la s\u00e9ance.</p>' +
        '<button class="btn btn-primary" id="hubRetryBtn">R\u00e9essayer</button>';
      content.prepend(banner);
      var retryBtn = document.getElementById('hubRetryBtn');
      if (retryBtn) {
        retryBtn.addEventListener('click', function () {
          banner.remove();
          loadData();
        });
      }
    }
  }

  async function loadData() {
    var params = new URLSearchParams(window.location.search);
    var sessionId = params.get('id');

    if (!sessionId) {
      sessionStorage.setItem('ag-vote-toast', JSON.stringify({
        msg: 'Identifiant de s\u00e9ance manquant', type: 'error'
      }));
      window.location.href = '/dashboard.htmx.html';
      return;
    }

    var attempt = 0;
    async function tryLoad() {
      attempt++;
      try {
        var res = await window.api('/api/v1/wizard_status?meeting_id=' + encodeURIComponent(sessionId));
        if (res && res.body && res.body.ok && res.body.data) {
          var data = res.body.data;
          var sessionData = mapApiDataToSession(data);
          applySessionToDOM(sessionData);
          renderKpis(sessionData);
          renderChecklist(sessionData);
          var files = Array.isArray(data.documents) ? data.documents : [];
          renderDocuments(files);
          renderQuorumBar(sessionData);
          var motions = sessionData.motions || [];
          var meetingId = sessionId;
          renderMotionsList(motions, meetingId);
          setupConvocationBtn(sessionData, sessionId);
          // HUB-01: Propagate meeting_id to operator-bound and postsession action buttons
          HUB_STEPS.forEach(function(s) {
            if (s.dest && (s.dest.indexOf('/operator.htmx.html') === 0 || s.dest.indexOf('/postsession.htmx.html') === 0)) {
              var u = new URL(s.dest, window.location.origin);
              u.searchParams.set('meeting_id', sessionId);
              s.dest = u.pathname + u.search;
            }
          });
          render();
          return;
        }
        if (res && res.body && res.body.error === 'meeting_not_found') {
          sessionStorage.setItem('ag-vote-toast', JSON.stringify({
            msg: 'S\u00e9ance introuvable', type: 'error'
          }));
          window.location.href = '/dashboard.htmx.html';
          return;
        }
        throw new Error('invalid_response');
      } catch (e) {
        if (attempt === 1) {
          setTimeout(tryLoad, 2000);
        } else {
          showHubError();
        }
      }
    }
    await tryLoad();
  }

  /* ── Toast pickup from wizard redirect (WIZ-05 / HUB) ── */

  function checkToast() {
    try {
      var raw = sessionStorage.getItem('ag-vote-toast');
      if (!raw) return;
      sessionStorage.removeItem('ag-vote-toast');
      var payload = JSON.parse(raw);
      if (payload && payload.msg && typeof Shared !== 'undefined' && Shared.showToast) {
        Shared.showToast(payload.msg, payload.type || 'success');
      }
    } catch (e) {
      // Ignore parse errors
    }
  }

  /* ── Init ────────────────────────────────────────── */

  function init() {
    checkToast();
    loadData().catch(function(e) { console.warn('Hub loadData error:', e); });
    render();
    setupDetails();
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
  } else {
    init();
  }
})();
