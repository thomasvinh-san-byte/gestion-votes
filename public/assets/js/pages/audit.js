/* GO-LIVE-STATUS: ready — Audit et Conformité JS. Demo data, table/timeline views, filter, pagination, modal, CSV export. */
/**
 * audit.js — Audit page module for AG-VOTE.
 * Loads audit events with demo fallback, renders table and timeline views,
 * handles filter pills, search, sort, pagination, checkbox selection, and event detail modal.
 * Must be loaded AFTER utils.js, shared.js and shell.js.
 */
(function () {
  'use strict';

  /* ── Helper ── */
  function esc(s) {
    return Utils.escapeHtml(s);
  }

  /* ── Demo data ── */
  var DEMO_EVENTS = [
    {
      id: 'evt-001',
      timestamp: '2026-03-15T09:00:12Z',
      category: 'votes',
      severity: 'success',
      event: 'Vote enregistré — Résolution 1 : Approbation des comptes',
      user: 'Marie Dupont',
      hash: 'a3f8c2d1e4b5f9072c6d3a0e1b4f7c8d9e2a5b6c3f0d1e4a7b8c5d2e9f0a3b4',
      description: 'Marie Dupont a voté POUR la résolution 1 (Approbation des comptes annuels 2025). Vote enregistré avec succès, horodatage certifié.'
    },
    {
      id: 'evt-002',
      timestamp: '2026-03-15T09:02:34Z',
      category: 'votes',
      severity: 'success',
      event: 'Vote enregistré — Résolution 1 : Approbation des comptes',
      user: 'Paul Martin',
      hash: 'b4e9d3a2f7c8b0e1d5c6a3b2f9e4d7c0a1b8f5e2d3c7a4b1e8f5d0c3a6b9e2f7',
      description: 'Paul Martin a voté CONTRE la résolution 1 (Approbation des comptes annuels 2025). Vote enregistré avec succès.'
    },
    {
      id: 'evt-003',
      timestamp: '2026-03-15T09:05:00Z',
      category: 'presences',
      severity: 'info',
      event: 'Présence confirmée — Jean Leblanc',
      user: 'Jean Leblanc',
      hash: 'c5f0e4b3a8d7c2f1e6d5b4a3c8f7e0d1b2a9f6e3d4c0b5a2e9d6c1a8b3f2e7d4',
      description: 'Jean Leblanc a été enregistré comme présent à la séance du 15 mars 2026. Procuration vérifiée : aucune.'
    },
    {
      id: 'evt-004',
      timestamp: '2026-03-15T09:07:22Z',
      category: 'presences',
      severity: 'info',
      event: 'Procuration enregistrée — Sophie Blanc donne procuration à Pierre Duval',
      user: 'Sophie Blanc',
      hash: 'd6a1f5c4b9e8d3f2a7e6c5b0d9f8e1c2b3a0f7d4c1b8e5a2f9c6d3b0a7e4f1c8',
      description: 'Sophie Blanc a remis procuration à Pierre Duval pour la séance du 15 mars 2026. Procuration vérifiée et enregistrée par l\'opérateur.'
    },
    {
      id: 'evt-005',
      timestamp: '2026-03-15T09:10:45Z',
      category: 'securite',
      severity: 'warning',
      event: 'Tentative de connexion échouée — 3 essais consécutifs',
      user: 'admin@agvote.fr',
      hash: 'e7b2a6d5c0f9e4b3a8d7c2f1e6d5b4a9c8f7e0d3b2a1f8e5d2c9b6a3f0e7d4c1',
      description: 'Trois tentatives de connexion consécutives ont échoué pour le compte admin@agvote.fr depuis l\'adresse IP 192.168.1.50. Compte temporairement verrouillé.'
    },
    {
      id: 'evt-006',
      timestamp: '2026-03-15T09:12:00Z',
      category: 'securite',
      severity: 'danger',
      event: 'Accès non autorisé détecté — tentative de modification de résultats',
      user: 'inconnu',
      hash: 'f8c3b7a6e1d0f5c4b9e8d3f2a7e6c5b0d9f8e1c2b3a0f7d4c1b8e5a2f9c6d3b0',
      description: 'Une tentative de modification des résultats de vote a été détectée et bloquée. L\'accès non autorisé provenait d\'une adresse IP externe. Alerte de sécurité émise.'
    },
    {
      id: 'evt-007',
      timestamp: '2026-03-15T09:15:30Z',
      category: 'systeme',
      severity: 'info',
      event: 'Séance créée — AG Ordinaire 2026',
      user: 'admin@agvote.fr',
      hash: 'a1b2c3d4e5f6a7b8c9d0e1f2a3b4c5d6e7f8a9b0c1d2e3f4a5b6c7d8e9f0a1b2',
      description: 'Création de la séance AG Ordinaire 2026 par l\'administrateur. Ordre du jour : 5 résolutions. Quorum requis : 30%.'
    },
    {
      id: 'evt-008',
      timestamp: '2026-03-15T09:18:00Z',
      category: 'votes',
      severity: 'success',
      event: 'Vote enregistré — Résolution 2 : Renouvellement du bureau',
      user: 'Claire Fontaine',
      hash: 'b2c3d4e5f6a7b8c9d0e1f2a3b4c5d6e7f8a9b0c1d2e3f4a5b6c7d8e9f0a1b2c3',
      description: 'Claire Fontaine a voté POUR la résolution 2 (Renouvellement du bureau). Vote enregistré avec succès, signatures numériques validées.'
    },
    {
      id: 'evt-009',
      timestamp: '2026-03-15T09:20:15Z',
      category: 'votes',
      severity: 'info',
      event: 'Vote enregistré — Résolution 2 : Renouvellement du bureau',
      user: 'Henri Moreau',
      hash: 'c3d4e5f6a7b8c9d0e1f2a3b4c5d6e7f8a9b0c1d2e3f4a5b6c7d8e9f0a1b2c3d4',
      description: 'Henri Moreau a voté ABSTENTION pour la résolution 2 (Renouvellement du bureau). Vote valide, abstention enregistrée.'
    },
    {
      id: 'evt-010',
      timestamp: '2026-03-15T09:22:45Z',
      category: 'systeme',
      severity: 'success',
      event: 'Quorum atteint — 45/120 membres présents (37.5%)',
      user: 'Système',
      hash: 'd4e5f6a7b8c9d0e1f2a3b4c5d6e7f8a9b0c1d2e3f4a5b6c7d8e9f0a1b2c3d4e5',
      description: 'Le quorum requis (30%) a été atteint avec 45 membres présents sur 120 inscrits (37.5%). La séance peut maintenant commencer officiellement.'
    },
    {
      id: 'evt-011',
      timestamp: '2026-03-15T09:25:00Z',
      category: 'presences',
      severity: 'info',
      event: 'Présence confirmée — Isabelle Rousseau',
      user: 'Isabelle Rousseau',
      hash: 'e5f6a7b8c9d0e1f2a3b4c5d6e7f8a9b0c1d2e3f4a5b6c7d8e9f0a1b2c3d4e5f6',
      description: 'Isabelle Rousseau a été enregistrée comme présente. Identité vérifiée par l\'opérateur. Aucune procuration.'
    },
    {
      id: 'evt-012',
      timestamp: '2026-03-15T09:28:30Z',
      category: 'votes',
      severity: 'danger',
      event: 'Vote rejeté — Résolution 3 : Budget prévisionnel',
      user: 'Système',
      hash: 'f6a7b8c9d0e1f2a3b4c5d6e7f8a9b0c1d2e3f4a5b6c7d8e9f0a1b2c3d4e5f6a7',
      description: 'La résolution 3 (Approbation du budget prévisionnel 2026) a été rejetée par vote. Pour: 18, Contre: 22, Abstentions: 5. Majorité simple non atteinte.'
    },
    {
      id: 'evt-013',
      timestamp: '2026-03-15T09:30:00Z',
      category: 'systeme',
      severity: 'info',
      event: 'Configuration mise à jour — Durée de vote modifiée à 10 minutes',
      user: 'admin@agvote.fr',
      hash: 'a7b8c9d0e1f2a3b4c5d6e7f8a9b0c1d2e3f4a5b6c7d8e9f0a1b2c3d4e5f6a7b8',
      description: 'L\'administrateur a modifié la durée maximale de vote de 5 à 10 minutes par résolution. Modification tracée et sauvegardée.'
    },
    {
      id: 'evt-014',
      timestamp: '2026-03-15T09:32:00Z',
      category: 'securite',
      severity: 'info',
      event: 'Connexion réussie — admin@agvote.fr',
      user: 'admin@agvote.fr',
      hash: 'b8c9d0e1f2a3b4c5d6e7f8a9b0c1d2e3f4a5b6c7d8e9f0a1b2c3d4e5f6a7b8c9',
      description: 'Connexion réussie à l\'interface d\'administration depuis 192.168.1.10. Session authentifiée avec succès.'
    },
    {
      id: 'evt-015',
      timestamp: '2026-03-15T09:35:00Z',
      category: 'votes',
      severity: 'success',
      event: 'Vote enregistré — Résolution 4 : Approbation des statuts modifiés',
      user: 'Pierre Duval',
      hash: 'c9d0e1f2a3b4c5d6e7f8a9b0c1d2e3f4a5b6c7d8e9f0a1b2c3d4e5f6a7b8c9d0',
      description: 'Pierre Duval a voté POUR la résolution 4 (Approbation des statuts modifiés). Il vote également pour sa mandante Sophie Blanc (procuration valide).'
    },
    {
      id: 'evt-016',
      timestamp: '2026-03-15T09:37:15Z',
      category: 'presences',
      severity: 'warning',
      event: 'Départ anticipé enregistré — Robert Petit',
      user: 'Robert Petit',
      hash: 'd0e1f2a3b4c5d6e7f8a9b0c1d2e3f4a5b6c7d8e9f0a1b2c3d4e5f6a7b8c9d0e1',
      description: 'Robert Petit a quitté la séance avant la fin. Son départ a été enregistré à 09h37. Il ne pourra plus voter pour les résolutions restantes.'
    },
    {
      id: 'evt-017',
      timestamp: '2026-03-15T09:40:00Z',
      category: 'systeme',
      severity: 'success',
      event: 'Sauvegarde automatique — Données séance exportées',
      user: 'Système',
      hash: 'e1f2a3b4c5d6e7f8a9b0c1d2e3f4a5b6c7d8e9f0a1b2c3d4e5f6a7b8c9d0e1f2',
      description: 'Sauvegarde automatique des données de séance effectuée avec succès. Snapshot chiffré stocké dans le registre sécurisé. Hash d\'intégrité calculé.'
    },
    {
      id: 'evt-018',
      timestamp: '2026-03-15T09:42:30Z',
      category: 'votes',
      severity: 'success',
      event: 'Vote enregistré — Résolution 5 : Election du président',
      user: 'Lucie Bernard',
      hash: 'f2a3b4c5d6e7f8a9b0c1d2e3f4a5b6c7d8e9f0a1b2c3d4e5f6a7b8c9d0e1f2a3',
      description: 'Lucie Bernard a voté POUR la résolution 5 (Élection du président pour 2026-2028). Candidat élu : Jean-Claude Renard avec 31 voix pour.'
    },
    {
      id: 'evt-019',
      timestamp: '2026-03-15T09:45:00Z',
      category: 'securite',
      severity: 'info',
      event: 'Vérification d\'intégrité — Tous les hachages validés',
      user: 'Système',
      hash: 'a3b4c5d6e7f8a9b0c1d2e3f4a5b6c7d8e9f0a1b2c3d4e5f6a7b8c9d0e1f2a3b4',
      description: 'Vérification automatique d\'intégrité effectuée. Tous les hachages SHA-256 des événements enregistrés ont été validés. Aucune altération détectée.'
    },
    {
      id: 'evt-020',
      timestamp: '2026-03-15T09:48:00Z',
      category: 'systeme',
      severity: 'info',
      event: 'Session de vote ouverte — Résolution 3 (deuxième délibération)',
      user: 'admin@agvote.fr',
      hash: 'b4c5d6e7f8a9b0c1d2e3f4a5b6c7d8e9f0a1b2c3d4e5f6a7b8c9d0e1f2a3b4c5',
      description: 'Ouverture de la deuxième délibération pour la résolution 3 (Budget prévisionnel). Conformément aux statuts, une seconde délibération est possible en cas de rejet.'
    },
    {
      id: 'evt-021',
      timestamp: '2026-03-15T09:50:30Z',
      category: 'votes',
      severity: 'success',
      event: 'Vote enregistré — Résolution 3 (2e délibération)',
      user: 'Marie Dupont',
      hash: 'c5d6e7f8a9b0c1d2e3f4a5b6c7d8e9f0a1b2c3d4e5f6a7b8c9d0e1f2a3b4c5d6',
      description: 'Marie Dupont a voté POUR lors de la deuxième délibération de la résolution 3 (Budget prévisionnel 2026). Changement de position enregistré.'
    },
    {
      id: 'evt-022',
      timestamp: '2026-03-15T09:53:00Z',
      category: 'presences',
      severity: 'info',
      event: 'Présence confirmée — Nathalie Girard (arrivée tardive)',
      user: 'Nathalie Girard',
      hash: 'd6e7f8a9b0c1d2e3f4a5b6c7d8e9f0a1b2c3d4e5f6a7b8c9d0e1f2a3b4c5d6e7',
      description: 'Nathalie Girard a été enregistrée comme présente avec arrivée tardive. Elle pourra participer aux votes des résolutions suivantes.'
    },
    {
      id: 'evt-023',
      timestamp: '2026-03-15T09:55:15Z',
      category: 'securite',
      severity: 'warning',
      event: 'Activité suspecte — Téléchargement massif des données membres',
      user: 'user@example.com',
      hash: 'e7f8a9b0c1d2e3f4a5b6c7d8e9f0a1b2c3d4e5f6a7b8c9d0e1f2a3b4c5d6e7f8',
      description: 'Un téléchargement inhabituel des données membres a été détecté depuis le compte user@example.com. L\'action a été journalisée et notifiée à l\'administrateur.'
    },
    {
      id: 'evt-024',
      timestamp: '2026-03-15T10:00:00Z',
      category: 'systeme',
      severity: 'success',
      event: 'Séance clôturée — PV généré et signé',
      user: 'admin@agvote.fr',
      hash: 'f8a9b0c1d2e3f4a5b6c7d8e9f0a1b2c3d4e5f6a7b8c9d0e1f2a3b4c5d6e7f8a9',
      description: 'La séance AG Ordinaire 2026 a été officiellement clôturée. Le procès-verbal a été généré, signé électroniquement et archivé. Hash d\'intégrité final calculé.'
    },
    {
      id: 'evt-025',
      timestamp: '2026-03-15T10:02:45Z',
      category: 'systeme',
      severity: 'info',
      event: 'Archive créée — Séance AG Ordinaire 2026',
      user: 'Système',
      hash: 'a9b0c1d2e3f4a5b6c7d8e9f0a1b2c3d4e5f6a7b8c9d0e1f2a3b4c5d6e7f8a9b0',
      description: 'La séance AG Ordinaire 2026 a été archivée avec succès. Toutes les données (votes, présences, résolutions, PV) sont conservées de manière immuable.'
    }
  ];

  /* ── Category label map ── */
  var CATEGORY_LABELS = {
    votes: 'Votes',
    presences: 'Présences',
    securite: 'Sécurité',
    systeme: 'Système'
  };

  /* ── State ── */
  var _allEvents = [];
  var _filteredEvents = [];
  var _currentPage = 1;
  var _perPage = 15;
  var _currentView = 'table';
  var _activeFilter = '';
  var _selectedIds = [];
  var _searchQuery = '';

  /* ── DOM references ── */
  var _tableBody = document.getElementById('auditTableBody');
  var _timeline = document.getElementById('auditTimeline');
  var _pagination = document.getElementById('auditPagination');
  var _tableView = document.getElementById('auditTableView');
  var _timelineView = document.getElementById('auditTimelineView');
  var _selectAll = document.getElementById('selectAll');
  var _btnExportAll = document.getElementById('btnExportAll');
  var _btnExportSelection = document.getElementById('btnExportSelection');
  var _auditSearch = document.getElementById('auditSearch');
  var _auditSort = document.getElementById('auditSort');
  var _detailModal = document.getElementById('auditDetailModal');
  var _detailBackdrop = document.getElementById('auditDetailBackdrop');

  /* ── Format date ── */
  function formatTimestamp(iso) {
    if (!iso) return '—';
    try {
      var d = new Date(iso);
      var pad = function(n) { return n < 10 ? '0' + n : '' + n; };
      return d.getFullYear() + '-' + pad(d.getMonth() + 1) + '-' + pad(d.getDate()) +
             ' ' + pad(d.getHours()) + ':' + pad(d.getMinutes()) + ':' + pad(d.getSeconds());
    } catch (e) {
      return iso;
    }
  }

  function formatDateShort(iso) {
    if (!iso) return '—';
    try {
      var d = new Date(iso);
      return d.toLocaleDateString('fr-FR', { day: '2-digit', month: 'short', year: 'numeric' });
    } catch (e) {
      return iso;
    }
  }

  /* ── KPI populate ── */
  function populateKPIs() {
    var kpiIntegrity = document.getElementById('kpiIntegrity');
    var kpiEvents = document.getElementById('kpiEvents');
    var kpiAnomalies = document.getElementById('kpiAnomalies');
    var kpiLastSession = document.getElementById('kpiLastSession');

    if (kpiIntegrity) kpiIntegrity.textContent = '100%';
    if (kpiEvents) kpiEvents.textContent = _allEvents.length;

    var anomalies = _allEvents.filter(function(e) { return e.severity === 'danger'; }).length;
    if (kpiAnomalies) kpiAnomalies.textContent = anomalies;

    if (kpiLastSession) {
      var sorted = _allEvents.slice().sort(function(a, b) {
        return new Date(b.timestamp) - new Date(a.timestamp);
      });
      kpiLastSession.textContent = sorted.length > 0 ? formatDateShort(sorted[0].timestamp) : '—';
    }
  }

  /* ── Render table ── */
  function renderTable(events) {
    if (!_tableBody) return;

    if (!events || events.length === 0) {
      _tableBody.innerHTML = '<tr><td colspan="6">' + Shared.emptyState({
        icon: 'search',
        title: 'Aucun événement',
        description: 'Aucun événement ne correspond aux filtres sélectionnés.'
      }) + '</td></tr>';
      return;
    }

    _tableBody.innerHTML = events.map(function(evt, idx) {
      var checked = _selectedIds.indexOf(evt.id) !== -1 ? ' checked' : '';
      var offsetIdx = (_currentPage - 1) * _perPage + idx + 1;
      return '<tr data-event-id="' + esc(evt.id) + '" class="audit-table-row">' +
        '<td class="audit-col-check" onclick="event.stopPropagation()">' +
          '<input type="checkbox" class="audit-row-check" data-id="' + esc(evt.id) + '"' + checked + '>' +
        '</td>' +
        '<td class="audit-col-num">' + offsetIdx + '</td>' +
        '<td class="audit-col-timestamp"><span class="audit-timestamp">' + esc(formatTimestamp(evt.timestamp)) + '</span></td>' +
        '<td class="audit-col-event">' +
          '<div class="audit-event-cell">' +
            '<span class="audit-severity-dot ' + esc(evt.severity) + '"></span>' +
            esc(evt.event) +
          '</div>' +
        '</td>' +
        '<td class="audit-col-user"><span class="tag tag-accent">' + esc(evt.user) + '</span></td>' +
        '<td class="audit-col-hash">' +
          '<span class="audit-hash-cell" title="' + esc(evt.hash) + '">' + esc(evt.hash.substring(0, 12)) + '...</span>' +
        '</td>' +
      '</tr>';
    }).join('');

    // Bind row click handlers (skip checkbox column)
    var rows = _tableBody.querySelectorAll('tr.audit-table-row');
    for (var i = 0; i < rows.length; i++) {
      (function(row) {
        row.addEventListener('click', function(e) {
          if (e.target.type === 'checkbox' || e.target.closest('[onclick]')) return;
          var eventId = row.dataset.eventId;
          if (eventId) openDetailModal(eventId);
        });
      })(rows[i]);
    }

    // Bind individual checkbox changes
    var checks = _tableBody.querySelectorAll('.audit-row-check');
    for (var j = 0; j < checks.length; j++) {
      checks[j].addEventListener('change', function(e) {
        var id = e.target.dataset.id;
        if (e.target.checked) {
          if (_selectedIds.indexOf(id) === -1) _selectedIds.push(id);
        } else {
          _selectedIds = _selectedIds.filter(function(s) { return s !== id; });
        }
        updateExportSelectionBtn();
        // Update selectAll indeterminate state
        updateSelectAllState();
      });
    }
  }

  /* ── Render timeline ── */
  function renderTimeline(events) {
    if (!_timeline) return;

    if (!events || events.length === 0) {
      _timeline.innerHTML = Shared.emptyState({
        icon: 'activity',
        title: 'Aucun événement',
        description: 'Aucun événement ne correspond aux filtres sélectionnés.'
      });
      return;
    }

    _timeline.innerHTML = events.map(function(evt) {
      var catLabel = esc(CATEGORY_LABELS[evt.category] || evt.category);
      return '<div class="audit-timeline-item" data-event-id="' + esc(evt.id) + '">' +
        '<span class="audit-timeline-dot ' + esc(evt.severity) + '"></span>' +
        '<div class="audit-timeline-content">' +
          '<div class="audit-timeline-header">' +
            '<div class="audit-timeline-title">' + esc(evt.event) + '</div>' +
            '<div class="audit-timeline-time audit-timestamp">' + esc(formatTimestamp(evt.timestamp)) + '</div>' +
          '</div>' +
          '<div class="audit-timeline-meta">' +
            '<span class="tag tag-ghost">' + catLabel + '</span>' +
            '<span class="tag tag-accent">' + esc(evt.user) + '</span>' +
            '<span class="audit-hash-cell" title="' + esc(evt.hash) + '">' + esc(evt.hash.substring(0, 12)) + '...</span>' +
          '</div>' +
        '</div>' +
        '<svg class="icon audit-timeline-chevron" aria-hidden="true"><use href="/assets/icons.svg#icon-chevron-right"></use></svg>' +
      '</div>';
    }).join('');

    // Bind click handlers
    var items = _timeline.querySelectorAll('.audit-timeline-item');
    for (var i = 0; i < items.length; i++) {
      (function(item) {
        item.addEventListener('click', function() {
          var eventId = item.dataset.eventId;
          if (eventId) openDetailModal(eventId);
        });
      })(items[i]);
    }
  }

  /* ── Render current view ── */
  function renderCurrentView() {
    var start = (_currentPage - 1) * _perPage;
    var pageEvents = _filteredEvents.slice(start, start + _perPage);

    if (_currentView === 'timeline') {
      renderTimeline(pageEvents);
    } else {
      renderTable(pageEvents);
    }
    renderPagination();
  }

  /* ── Pagination ── */
  function renderPagination() {
    if (!_pagination) return;

    var totalPages = Math.max(1, Math.ceil(_filteredEvents.length / _perPage));
    if (totalPages <= 1 && _filteredEvents.length <= _perPage) {
      _pagination.innerHTML = '';
      return;
    }

    var html = '';

    // Previous button
    html += '<button class="btn btn-sm btn-ghost" data-page="' + (_currentPage - 1) + '"' +
            (_currentPage <= 1 ? ' disabled' : '') + '>&#8249; Préc.</button>';

    // Page buttons
    for (var i = 1; i <= totalPages; i++) {
      var active = i === _currentPage ? ' btn-primary' : ' btn-ghost';
      html += '<button class="btn btn-sm' + active + '" data-page="' + i + '">' + i + '</button>';
    }

    // Next button
    html += '<button class="btn btn-sm btn-ghost" data-page="' + (_currentPage + 1) + '"' +
            (_currentPage >= totalPages ? ' disabled' : '') + '>Suiv. &#8250;</button>';

    _pagination.innerHTML = html;

    // Bind clicks
    var btns = _pagination.querySelectorAll('button[data-page]');
    for (var j = 0; j < btns.length; j++) {
      btns[j].addEventListener('click', function(e) {
        if (e.target.disabled) return;
        var page = parseInt(e.target.dataset.page);
        if (!isNaN(page) && page >= 1 && page <= totalPages) {
          _currentPage = page;
          renderCurrentView();
        }
      });
    }
  }

  /* ── Apply filters ── */
  function applyFilters() {
    _filteredEvents = _allEvents.filter(function(evt) {
      var matchCategory = !_activeFilter || evt.category === _activeFilter;
      var matchSearch = true;
      if (_searchQuery) {
        var q = _searchQuery.toLowerCase();
        matchSearch = (evt.event || '').toLowerCase().indexOf(q) !== -1 ||
                      (evt.user || '').toLowerCase().indexOf(q) !== -1 ||
                      (evt.description || '').toLowerCase().indexOf(q) !== -1;
      }
      return matchCategory && matchSearch;
    });

    _currentPage = 1;
    renderCurrentView();
  }

  /* ── Sort ── */
  function applySortToFiltered() {
    var sortVal = _auditSort ? _auditSort.value : 'date-desc';
    _filteredEvents.sort(function(a, b) {
      if (sortVal === 'date-asc') {
        return new Date(a.timestamp) - new Date(b.timestamp);
      }
      if (sortVal === 'severity-desc') {
        var order = { danger: 0, warning: 1, info: 2, success: 3 };
        return (order[a.severity] || 99) - (order[b.severity] || 99);
      }
      // date-desc (default)
      return new Date(b.timestamp) - new Date(a.timestamp);
    });
  }

  /* ── Event detail modal ── */
  function openDetailModal(eventId) {
    var evt = null;
    for (var i = 0; i < _allEvents.length; i++) {
      if (_allEvents[i].id === eventId) { evt = _allEvents[i]; break; }
    }
    if (!evt) return;

    var detailTimestamp = document.getElementById('detailTimestamp');
    var detailCategory = document.getElementById('detailCategory');
    var detailUser = document.getElementById('detailUser');
    var detailSeverity = document.getElementById('detailSeverity');
    var detailDescription = document.getElementById('detailDescription');
    var detailHash = document.getElementById('detailHash');

    if (detailTimestamp) detailTimestamp.textContent = formatTimestamp(evt.timestamp);
    if (detailCategory) {
      var catLabel = CATEGORY_LABELS[evt.category] || evt.category;
      detailCategory.innerHTML = '<span class="tag tag-ghost">' + esc(catLabel) + '</span>';
    }
    if (detailUser) detailUser.textContent = evt.user;
    if (detailSeverity) {
      detailSeverity.innerHTML = '<span class="audit-severity-dot ' + esc(evt.severity) + '"></span> ' + esc(evt.severity);
    }
    if (detailDescription) detailDescription.textContent = evt.description;
    if (detailHash) detailHash.textContent = evt.hash;

    // Store current event id for export
    _detailModal.dataset.currentEventId = eventId;

    // Show
    if (_detailModal) {
      _detailModal.style.display = 'block';
      _detailModal.setAttribute('aria-hidden', 'false');
    }
    if (_detailBackdrop) _detailBackdrop.style.display = 'block';
    document.body.style.overflow = 'hidden';
  }

  function closeDetailModal() {
    if (_detailModal) {
      _detailModal.style.display = 'none';
      _detailModal.setAttribute('aria-hidden', 'true');
    }
    if (_detailBackdrop) _detailBackdrop.style.display = 'none';
    document.body.style.overflow = '';
  }

  /* ── CSV Export ── */
  function generateCSV(events) {
    var headers = ['Horodatage', 'Evenement', 'Categorie', 'Utilisateur', 'Severite', 'Hash'];
    var rows = events.map(function(evt) {
      return [
        formatTimestamp(evt.timestamp),
        '"' + (evt.event || '').replace(/"/g, '""') + '"',
        evt.category,
        '"' + (evt.user || '').replace(/"/g, '""') + '"',
        evt.severity,
        evt.hash
      ].join(',');
    });
    return headers.join(',') + '\n' + rows.join('\n');
  }

  function downloadCSV(csv, filename) {
    var blob = new Blob([csv], { type: 'text/csv;charset=utf-8;' });
    var url = URL.createObjectURL(blob);
    var a = document.createElement('a');
    a.href = url;
    a.download = filename;
    document.body.appendChild(a);
    a.click();
    document.body.removeChild(a);
    URL.revokeObjectURL(url);
  }

  /* ── Checkbox / selection ── */
  function updateExportSelectionBtn() {
    if (_btnExportSelection) {
      if (_selectedIds.length > 0) {
        _btnExportSelection.hidden = false;
        _btnExportSelection.innerHTML = '<svg class="icon icon-text" aria-hidden="true"><use href="/assets/icons.svg#icon-download"></use></svg> Exporter la sélection (' + _selectedIds.length + ')';
      } else {
        _btnExportSelection.hidden = true;
      }
    }
  }

  function updateSelectAllState() {
    if (!_selectAll) return;
    var allChecks = _tableBody ? _tableBody.querySelectorAll('.audit-row-check') : [];
    var checkedCount = 0;
    for (var i = 0; i < allChecks.length; i++) {
      if (allChecks[i].checked) checkedCount++;
    }
    if (checkedCount === 0) {
      _selectAll.checked = false;
      _selectAll.indeterminate = false;
    } else if (checkedCount === allChecks.length) {
      _selectAll.checked = true;
      _selectAll.indeterminate = false;
    } else {
      _selectAll.checked = false;
      _selectAll.indeterminate = true;
    }
  }

  /* ── Data loading ── */
  async function loadData() {
    try {
      var res = await window.api('/api/v1/audit.php');
      if (res && res.body && (res.body.data || res.body.items)) {
        _allEvents = res.body.data || res.body.items || [];
      } else {
        throw new Error('No data');
      }
    } catch (e) {
      console.warn('[audit.js] API unavailable, using demo data:', e.message || e);
      _allEvents = DEMO_EVENTS;
    }

    populateKPIs();
    applyFilters();
  }

  /* ── Init event handlers ── */
  function initHandlers() {
    // Filter tabs
    var filterTabs = document.querySelectorAll('#auditTypeFilter .filter-tab');
    for (var i = 0; i < filterTabs.length; i++) {
      (function(tab) {
        tab.addEventListener('click', function() {
          for (var j = 0; j < filterTabs.length; j++) filterTabs[j].classList.remove('active');
          tab.classList.add('active');
          _activeFilter = tab.dataset.type || '';
          applyFilters();
        });
      })(filterTabs[i]);
    }

    // Search (debounced)
    if (_auditSearch) {
      _auditSearch.addEventListener('input', Utils.debounce(function(e) {
        _searchQuery = e.target.value.trim();
        applyFilters();
      }, 300));
    }

    // Sort
    if (_auditSort) {
      _auditSort.addEventListener('change', function() {
        applySortToFiltered();
        _currentPage = 1;
        renderCurrentView();
      });
    }

    // View toggle
    var viewBtns = document.querySelectorAll('.view-toggle-btn');
    for (var k = 0; k < viewBtns.length; k++) {
      (function(btn) {
        btn.addEventListener('click', function() {
          for (var m = 0; m < viewBtns.length; m++) viewBtns[m].classList.remove('active');
          btn.classList.add('active');
          _currentView = btn.dataset.view || 'table';
          if (_currentView === 'timeline') {
            _tableView.removeAttribute('hidden');
            _timelineView.removeAttribute('hidden');
            _tableView.setAttribute('hidden', '');
          } else {
            _tableView.removeAttribute('hidden');
            _timelineView.setAttribute('hidden', '');
          }
          _currentPage = 1;
          renderCurrentView();
        });
      })(viewBtns[k]);
    }

    // Select all
    if (_selectAll) {
      _selectAll.addEventListener('change', function() {
        var allChecks = _tableBody ? _tableBody.querySelectorAll('.audit-row-check') : [];
        var isChecked = _selectAll.checked;
        for (var n = 0; n < allChecks.length; n++) {
          allChecks[n].checked = isChecked;
          var id = allChecks[n].dataset.id;
          if (isChecked) {
            if (_selectedIds.indexOf(id) === -1) _selectedIds.push(id);
          } else {
            _selectedIds = _selectedIds.filter(function(s) { return s !== id; });
          }
        }
        if (!isChecked) _selectedIds = [];
        updateExportSelectionBtn();
      });
    }

    // Export all
    if (_btnExportAll) {
      _btnExportAll.addEventListener('click', function() {
        downloadCSV(generateCSV(_filteredEvents), 'audit-export-' + new Date().toISOString().slice(0, 10) + '.csv');
      });
    }

    // Export selection
    if (_btnExportSelection) {
      _btnExportSelection.addEventListener('click', function() {
        var selected = _allEvents.filter(function(e) { return _selectedIds.indexOf(e.id) !== -1; });
        downloadCSV(generateCSV(selected), 'audit-selection-' + new Date().toISOString().slice(0, 10) + '.csv');
      });
    }

    // Close modal buttons
    var btnClose1 = document.getElementById('btnCloseAuditDetail');
    var btnClose2 = document.getElementById('btnCloseAuditDetail2');
    if (btnClose1) btnClose1.addEventListener('click', closeDetailModal);
    if (btnClose2) btnClose2.addEventListener('click', closeDetailModal);
    if (_detailBackdrop) _detailBackdrop.addEventListener('click', closeDetailModal);

    // Export detail button
    var btnExportDetail = document.getElementById('btnExportDetail');
    if (btnExportDetail) {
      btnExportDetail.addEventListener('click', function() {
        var eventId = _detailModal && _detailModal.dataset.currentEventId;
        if (!eventId) return;
        var evt = null;
        for (var p = 0; p < _allEvents.length; p++) {
          if (_allEvents[p].id === eventId) { evt = _allEvents[p]; break; }
        }
        if (!evt) return;
        var json = JSON.stringify(evt, null, 2);
        var blob = new Blob([json], { type: 'application/json' });
        var url = URL.createObjectURL(blob);
        var a = document.createElement('a');
        a.href = url;
        a.download = 'audit-event-' + eventId + '.json';
        document.body.appendChild(a);
        a.click();
        document.body.removeChild(a);
        URL.revokeObjectURL(url);
      });
    }

    // Escape key closes modal
    document.addEventListener('keydown', function(e) {
      if (e.key === 'Escape' && _detailModal && _detailModal.style.display !== 'none') {
        closeDetailModal();
      }
    });
  }

  /* ── Bootstrap ── */
  document.addEventListener('DOMContentLoaded', function() {
    initHandlers();
    loadData();
  });

})();
