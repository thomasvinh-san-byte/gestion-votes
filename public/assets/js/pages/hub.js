/**
 * Hub — Fiche séance (6-step guided flow).
 * Shows one action at a time, tracks progress.
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
      icon: 'edit', color: 'var(--accent)', dest: '/wizard.htmx.html',
      btnLabel: 'Ouvrir la pr\u00e9paration',
      checks: [
        ['Titre et date d\u00e9finis', true],
        ['67 participants import\u00e9s', true],
        ['8 r\u00e9solutions pr\u00e9par\u00e9es', true],
        ['Pi\u00e8ces jointes ajout\u00e9es', false]
      ]
    },
    {
      id: 'convocations', num: 2,
      titre: 'Envoyer les convocations',
      titreAction: 'Envoyer les 12 convocations restantes',
      descAction: '55 participants ont d\u00e9j\u00e0 re\u00e7u la convocation. Il reste 12 envois \u00e0 d\u00e9clencher avant le 22/02/2026.',
      icon: 'send', color: 'var(--warn)', dest: null,
      btnLabel: 'Envoyer les convocations',
      btnPreview: true,
      checks: [
        ['55 convocations envoy\u00e9es', true],
        ['12 envois en attente', false],
        ['Date limite : 22/02/2026', true]
      ]
    },
    {
      id: 'presences', num: 3,
      titre: 'Pointer les pr\u00e9sences',
      titreAction: 'Accueillir les participants',
      descAction: 'La s\u00e9ance commence. Enregistrez les arriv\u00e9es et les procurations au fur et \u00e0 mesure.',
      icon: 'users', color: 'var(--accent)', dest: '/operator.htmx.html',
      btnLabel: 'Ouvrir le pointage',
      checks: [
        ['Feuille de pr\u00e9sence pr\u00eate', true],
        ['Procurations v\u00e9rifi\u00e9es', true]
      ]
    },
    {
      id: 'vote', num: 4,
      titre: 'Piloter les votes',
      titreAction: 'La s\u00e9ance est en cours',
      descAction: 'Les votes sont ouverts. Pilotez chaque r\u00e9solution depuis l\u2019espace op\u00e9rateur en direct.',
      icon: 'play', color: 'var(--danger)', dest: '/operator.htmx.html',
      btnLabel: 'Rejoindre la s\u00e9ance en direct'
    },
    {
      id: 'cloture', num: 5,
      titre: 'Cl\u00f4turer et envoyer le PV',
      titreAction: 'Finaliser et archiver',
      descAction: 'La s\u00e9ance est termin\u00e9e. Validez le PV, apposez la signature \u00e9lectronique et envoyez.',
      icon: 'file-text', color: 'var(--purple)', dest: '/postsession.htmx.html',
      btnLabel: 'G\u00e9n\u00e9rer et envoyer le PV'
    },
    {
      id: 'archive', num: 6,
      titre: 'S\u00e9ance archiv\u00e9e',
      titreAction: 'S\u00e9ance archiv\u00e9e',
      descAction: 'Tout est termin\u00e9. Le PV est envoy\u00e9 et archiv\u00e9.',
      icon: 'archive', color: 'var(--success)', dest: '/archives.htmx.html',
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
    'circle': '<circle cx="12" cy="12" r="10"/>'
  };

  function svgIcon(name, size, color) {
    size = size || 18;
    color = color || 'currentColor';
    var paths = SVG_ICONS[name] || SVG_ICONS['circle'];
    return '<svg width="' + size + '" height="' + size + '" viewBox="0 0 24 24" fill="none" stroke="' + escapeHtml(color) + '" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">' + paths + '</svg>';
  }

  var currentStep = 1; // Default to convocations step

  /* ── Render stepper ──────────────────────────────── */

  function renderStepper() {
    var container = document.getElementById('hubStepper');
    if (!container) return;

    var html = '';
    HUB_STEPS.forEach(function (s, i) {
      var isDone = i < currentStep;
      var isActive = i === currentStep;
      var numClass = isDone ? ' done' : isActive ? ' active' : '';
      var titleClass = isDone ? ' done' : isActive ? ' active' : '';
      var numContent = isDone ? svgIcon('check', 16, '#fff') : String(s.num);

      html += '<button class="hub-step-row" data-step="' + i + '"' +
        (isActive ? ' aria-current="step"' : '') +
        ' aria-label="' + (isDone ? '\u00c9tape termin\u00e9e : ' : 'Aller \u00e0 ') + escapeHtml(s.titre) + '">' +
        '<div class="hub-step-num' + numClass + '">' + numContent + '</div>' +
        '<div class="hub-step-text">' +
          '<div class="hub-step-title' + titleClass + '">' + escapeHtml(s.titre) + '</div>' +
          (isActive ? '<div style="font-size:11px;color:var(--accent);font-weight:600;margin-top:2px;">\u2190 Vous \u00eates ici</div>' : '') +
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

  /* ── Render action card ──────────────────────────── */

  function renderAction() {
    var step = HUB_STEPS[currentStep];
    if (!step) return;

    var icon = document.getElementById('hubActionIcon');
    var svg = document.getElementById('hubActionSvg');
    var title = document.getElementById('hubActionTitle');
    var desc = document.getElementById('hubActionDesc');
    var btn = document.getElementById('hubMainBtn');
    var preview = document.getElementById('hubPreviewBtn');

    if (icon) icon.style.background = step.color;
    if (svg) svg.innerHTML = SVG_ICONS[step.icon] || SVG_ICONS['circle'];
    if (title) title.textContent = step.titreAction;
    if (desc) desc.textContent = step.descAction;

    if (btn) {
      btn.textContent = step.btnLabel;
      btn.href = step.dest || '#';
      if (!step.dest) {
        btn.removeAttribute('href');
        btn.style.cursor = 'pointer';
      }
    }

    if (preview) {
      preview.style.display = step.btnPreview ? '' : 'none';
    }

    // Checklist
    var checks = step.checks || [];
    var checkContainer = document.getElementById('hubChecklist');
    if (checkContainer) {
      if (checks.length === 0) {
        checkContainer.style.display = 'none';
      } else {
        checkContainer.style.display = '';
        var done = checks.filter(function (c) { return c[1]; }).length;
        var pct = Math.round(done / checks.length * 100);

        var checkTitle = document.getElementById('hubCheckTitle');
        if (checkTitle) checkTitle.textContent = 'Avancement : ' + done + ' / ' + checks.length + ' points compl\u00e9t\u00e9s';

        var prog = document.getElementById('hubCheckProgress');
        if (prog) {
          prog.style.width = pct + '%';
          prog.style.background = done === checks.length ? 'var(--success)' : 'var(--accent)';
        }

        var items = document.getElementById('hubCheckItems');
        if (items) {
          var html = '';
          checks.forEach(function (c, i) {
            var ok = c[1];
            var border = i < checks.length - 1 ? '1px solid var(--border-soft)' : 'none';
            html += '<div style="display:flex;align-items:center;gap:10px;padding:5px 0;border-bottom:' + border + ';flex-wrap:nowrap;">';
            html += ok ? svgIcon('checkCircle', 18, 'var(--success)') : svgIcon('circle', 18, 'var(--border)');
            html += '<span style="font-size:13px;font-weight:' + (ok ? '500' : '700') + ';color:' + (ok ? 'var(--text-muted)' : 'var(--text-dark)') + ';">' + escapeHtml(c[0]) + '</span>';
            if (!ok) html += '<span style="margin-left:auto;font-size:12px;color:var(--danger);font-weight:700;">\u00c0 faire</span>';
            html += '</div>';
          });
          items.innerHTML = html;
        }
      }
    }
  }

  function render() {
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
      var open = body.style.display !== 'none';
      body.style.display = open ? 'none' : '';
      toggle.setAttribute('aria-expanded', String(!open));
      if (chevron) {
        chevron.innerHTML = open ? '<path d="m6 9 6 6 6-6"/>' : '<path d="m18 15-6-6-6 6"/>';
      }
    });
  }

  /* ── Load data (fallback to demo) ────────────────── */

  function loadData() {
    // Set demo data
    var title = document.getElementById('hubTitle');
    var date = document.getElementById('hubDate');
    var place = document.getElementById('hubPlace');
    var participants = document.getElementById('hubParticipants');

    if (title) title.textContent = 'AG Ordinaire';
    if (date) date.textContent = 'Mercredi 18 f\u00e9vrier 2026 \u00e0 18 h 30';
    if (place) place.textContent = 'Salle des f\u00eates, 12 rue des Mimosas';
    if (participants) participants.textContent = '67 participants';

    // KPIs
    var kpis = {
      hubKpiParticipants: '67', hubKpiVoix: '8 500 voix',
      hubKpiResolutions: '8', hubKpiResoDetail: '3 art.24, 3 art.25, 2 art.26',
      hubKpiQuorum: '34', hubKpiQuorumDetail: '4 251 voix min.',
      hubKpiConvoc: '55/67', hubKpiConvocDetail: '12 en attente'
    };
    Object.keys(kpis).forEach(function (id) {
      var el = document.getElementById(id);
      if (el) el.textContent = kpis[id];
    });

    // Documents
    var docs = document.getElementById('hubDocsList');
    if (docs) {
      var files = [
        { name: 'Convocation_AG_2026.pdf', size: '245 Ko' },
        { name: 'Comptes_2025.pdf', size: '1.2 Mo' },
        { name: 'Devis_ravalement.pdf', size: '890 Ko' }
      ];
      var html = '';
      files.forEach(function (f, i) {
        var border = i < files.length - 1 ? '1px solid var(--border-soft)' : 'none';
        html += '<div style="display:flex;align-items:center;gap:8px;padding:5px 0;border-bottom:' + border + ';flex-wrap:nowrap;">' +
          svgIcon('file-text', 14, 'var(--accent)') +
          '<span style="flex:1;font-size:13px;font-weight:600;color:var(--text-dark);">' + escapeHtml(f.name) + '</span>' +
          '<span class="muted" style="font-size:12px;">' + escapeHtml(f.size) + '</span>' +
        '</div>';
      });
      docs.innerHTML = html;
    }
  }

  /* ── Init ────────────────────────────────────────── */

  function init() {
    loadData();
    render();
    setupDetails();
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
  } else {
    init();
  }
})();
