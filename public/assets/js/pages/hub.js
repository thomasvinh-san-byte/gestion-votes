/* GO-LIVE-STATUS: ready — Hub JS. 3-item checklist, quorum bar, lifecycle CTAs. */
/**
 * Hub — Fiche séance.
 * Loads session data from wizard_status API, renders 3-item prerequisite checklist,
 * quorum bar, motions preview, and wires lifecycle CTA buttons.
 * Phase 47-02: updated for new HTML structure (hub-hero + two-column body).
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

  /* ── 3-item checklist rendering ────────────────── */

  function renderChecklist(sessionData, invitationStats, workflowData) {
    var container = document.getElementById('hubChecklist');
    if (!container) return;
    container.removeAttribute('aria-busy');

    var meetingStatus = sessionData.status || '';
    var isFrozenOrLater = ['frozen', 'live', 'paused', 'closed', 'validated', 'archived'].indexOf(meetingStatus) !== -1;

    // Item 1: convocations
    var convocDone = !!(invitationStats && invitationStats.sent > 0 && invitationStats.pending === 0);
    var convocBlocked = false;
    var convocReason = '';
    if (!convocDone && invitationStats && invitationStats.pending > 0) {
      convocReason = invitationStats.pending + ' convocation(s) encore en attente';
    } else if (!convocDone && invitationStats && invitationStats.sent === 0) {
      convocReason = 'Aucune convocation envoy\u00e9e';
    }

    // Item 2: quorum
    var quorumDone = !!(sessionData.quorumMet === true);
    var quorumReason = '';
    if (!quorumDone && sessionData.presentCount !== undefined && sessionData.quorumRequired !== undefined) {
      quorumReason = sessionData.presentCount + ' pr\u00e9sent(s) sur ' + sessionData.quorumRequired + ' requis';
    }

    // Item 3: agenda locked
    var agendaDone = isFrozenOrLater;
    var agendaReason = '';
    if (!agendaDone) {
      // Check workflow data for issues
      if (workflowData && workflowData.transitions && workflowData.transitions.frozen) {
        var frozenTransition = workflowData.transitions.frozen;
        if (!frozenTransition.can_proceed && frozenTransition.issues && frozenTransition.issues.length > 0) {
          agendaReason = frozenTransition.issues[0].msg || '';
        }
      }
    }

    var items = [
      { check: 'convocation', done: convocDone, blocked: convocBlocked, reason: convocReason },
      { check: 'quorum',      done: quorumDone, blocked: false,         reason: quorumReason },
      { check: 'agenda',      done: agendaDone, blocked: false,         reason: agendaReason }
    ];

    var doneCount = 0;
    items.forEach(function(item) { if (item.done) doneCount++; });

    // Update progress badge
    var progressEl = document.getElementById('hubChecklistProgress');
    if (progressEl) progressEl.textContent = doneCount + '/3';

    // Update each checklist item in the pre-rendered HTML
    items.forEach(function(item) {
      var el = container.querySelector('[data-check="' + item.check + '"]');
      if (!el) return;

      // Remove all state modifier classes
      el.classList.remove('hub-checklist-item--done', 'hub-checklist-item--blocked', 'hub-checklist-item--pending');
      var dot = el.querySelector('.hub-checklist-dot');
      if (dot) {
        dot.classList.remove('hub-checklist-dot--done', 'hub-checklist-dot--blocked', 'hub-checklist-dot--pending');
      }
      var badge = el.querySelector('.hub-checklist-badge');
      if (badge) {
        badge.classList.remove('hub-checklist-badge--done', 'hub-checklist-badge--blocked', 'hub-checklist-badge--pending');
      }
      var reasonEl = el.querySelector('.hub-checklist-reason');

      if (item.done) {
        el.classList.add('hub-checklist-item--done');
        if (dot) dot.classList.add('hub-checklist-dot--done');
        if (badge) { badge.classList.add('hub-checklist-badge--done'); badge.textContent = 'Fait'; }
        if (reasonEl) reasonEl.setAttribute('hidden', '');
      } else if (item.blocked) {
        el.classList.add('hub-checklist-item--blocked');
        if (dot) dot.classList.add('hub-checklist-dot--blocked');
        if (badge) { badge.classList.add('hub-checklist-badge--blocked'); badge.textContent = 'Bloqu\u00e9'; }
        if (reasonEl) {
          if (item.reason) {
            reasonEl.textContent = item.reason;
            reasonEl.removeAttribute('hidden');
          } else {
            reasonEl.setAttribute('hidden', '');
          }
        }
      } else {
        el.classList.add('hub-checklist-item--pending');
        if (dot) dot.classList.add('hub-checklist-dot--pending');
        if (badge) { badge.classList.add('hub-checklist-badge--pending'); badge.textContent = 'En attente'; }
        if (reasonEl) {
          if (item.reason) {
            reasonEl.textContent = item.reason;
            reasonEl.removeAttribute('hidden');
          } else {
            reasonEl.setAttribute('hidden', '');
          }
        }
      }
    });
  }

  /* ── Quorum progress bar ─────────────────────────── */

  function renderQuorumBar(sessionData) {
    var section = document.getElementById('hubQuorumSection');
    var bar = document.getElementById('hubQuorumBar');
    var pctEl = document.getElementById('hubQuorumPct');
    if (!bar || !section) return;
    var total = sessionData.memberCount || 0;
    var required = sessionData.quorumRequired || 0;
    var current = sessionData.presentCount || 0;
    if (total === 0) { section.setAttribute('hidden', ''); return; }
    section.removeAttribute('hidden');
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
    // Update percentage display
    if (pctEl) {
      var presentPct = total > 0 ? Math.round(current / total * 100) : 0;
      pctEl.textContent = presentPct + '%';
      pctEl.className = 'hub-quorum-pct';
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

  /* ── Motions list with doc badges ───────────────── */

  function renderMotionsList(motions, meetingId) {
    var section = document.getElementById('hubMotionsSection');
    var list = document.getElementById('hubMotionsList');
    var countEl = document.getElementById('hubMotionsCount');
    var voirTout = document.getElementById('hubMotionsVoirTout');
    if (!list || !section) return;
    if (!motions || !motions.length) { section.setAttribute('hidden', ''); return; }
    section.removeAttribute('hidden');
    if (countEl) countEl.textContent = String(motions.length);
    if (voirTout) voirTout.href = '/operator/' + encodeURIComponent(meetingId);
    // Display first 3 motions only
    var displayMotions = motions.slice(0, 3);
    var html = '';
    displayMotions.forEach(function(m, i) {
      html += '<div class="hub-motion-item">' +
        '<span class="hub-motion-num">' + (i + 1) + '</span>' +
        '<span class="hub-motion-title">' + escapeHtml(m.title || m.name || '') + '</span>' +
        '<span class="doc-badge doc-badge--empty" data-motion-doc-badge data-motion-id="' + escapeHtml(String(m.id || '')) + '">Aucun document</span>' +
      '</div>';
    });
    list.innerHTML = html;
    loadDocBadges(displayMotions, meetingId);
  }

  /* ── Convocation send button ─────────────────────── */

  function setupConvocationBtn(invitationStats, sessionId) {
    var section = document.getElementById('hubConvocationSection');
    var btn = document.getElementById('btnSendConvocations');
    if (!btn || !section) return;
    // Hide if already all sent, or no stats
    if (!invitationStats || (invitationStats.sent > 0 && invitationStats.pending === 0)) {
      section.setAttribute('hidden', '');
      return;
    }
    section.removeAttribute('hidden');
    // Remove previous listener by cloning
    var newBtn = btn.cloneNode(true);
    btn.parentNode.replaceChild(newBtn, btn);
    newBtn.addEventListener('click', function() {
      if (!window.AgConfirm) return;
      window.AgConfirm.ask({
        title: 'Envoyer les convocations',
        message: 'Envoyer les convocations manquantes\u202f?',
        confirmLabel: 'Envoyer',
        variant: 'info'
      }).then(function(ok) {
        if (!ok) return;
        newBtn.disabled = true;
        newBtn.textContent = 'Envoi en cours\u2026';
        window.api('/api/v1/invitations_send_bulk', { meeting_id: sessionId, only_unsent: true }, 'POST')
          .then(function(res) {
            var sent = (res && res.body && res.body.data) ? (res.body.data.sent || 0) : 0;
            AgToast.show('success', 'Convocations envoy\u00e9es\u202f: ' + sent);
            section.setAttribute('hidden', '');
            loadData();
          })
          .catch(function() {
            newBtn.disabled = false;
            newBtn.textContent = 'Envoyer les convocations';
            AgToast.show('error', 'Erreur lors de l\u2019envoi des convocations');
          });
      });
    });
  }

  /* ── Resolution document badges ──────────────────── */

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

  /* ── Meeting attachments ─────────────────────────── */

  function loadMeetingAttachments(sessionId) {
    window.api('/api/v1/meeting_attachments_public?meeting_id=' + encodeURIComponent(sessionId))
      .then(function(resp) {
        renderMeetingAttachments(resp && resp.attachments ? resp.attachments : []);
      })
      .catch(function() {
        renderMeetingAttachments([]);
      });
  }

  function renderMeetingAttachments(attachments) {
    var section = document.getElementById('hubAttachmentsSection');
    var list = document.getElementById('hubAttachmentsList');
    var count = document.getElementById('hubAttachmentsCount');
    if (!section || !list) return;

    if (!attachments || attachments.length === 0) {
      section.hidden = true;
      return;
    }

    section.hidden = false;
    if (count) count.textContent = String(attachments.length);

    var html = '';
    attachments.forEach(function(att) {
      html += '<div class="hub-attachment-row" data-attach-id="' + escapeHtml(att.id) + '"' +
        ' data-attach-name="' + escapeHtml(att.original_name || 'document.pdf') + '">' +
        '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' +
        '<path d="M15 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V7Z"/>' +
        '<path d="M14 2v4a2 2 0 0 0 2 2h4"/>' +
        '</svg>' +
        '<span class="hub-attachment-name">' + escapeHtml(att.original_name || 'document.pdf') + '</span>' +
        '</div>';
    });
    list.innerHTML = html;

    list.querySelectorAll('.hub-attachment-row').forEach(function(row) {
      row.addEventListener('click', function() {
        openAttachmentViewer(this.dataset.attachId, this.dataset.attachName);
      });
    });
  }

  function openAttachmentViewer(attachId, attachName) {
    var viewer = document.getElementById('meetingAttachViewer');
    if (!viewer) {
      viewer = document.createElement('ag-pdf-viewer');
      viewer.setAttribute('id', 'meetingAttachViewer');
      viewer.setAttribute('mode', 'panel');
      viewer.setAttribute('allow-download', '');
      document.body.appendChild(viewer);
    }
    viewer.setAttribute('src', '/api/v1/meeting_attachment_serve?id=' + encodeURIComponent(attachId));
    viewer.setAttribute('filename', attachName || 'document.pdf');
    if (typeof viewer.open === 'function') viewer.open();
  }

  /* ── Load invitation stats ───────────────────────── */

  function loadInvitationStats(meetingId) {
    return window.api('/api/v1/invitations_stats?meeting_id=' + encodeURIComponent(meetingId))
      .then(function(res) {
        var statsRaw = (res && res.body && res.body.stats) ? res.body.stats : (res && res.stats ? res.stats : null);
        if (!statsRaw) return { sent: 0, pending: 0 };
        return {
          sent: statsRaw.sent || 0,
          pending: statsRaw.pending || 0
        };
      })
      .catch(function() {
        return { sent: 0, pending: 0 };
      });
  }

  /* ── Load workflow check ─────────────────────────── */

  function loadWorkflowCheck(meetingId) {
    return window.api('/api/v1/meeting_workflow_check?meeting_id=' + encodeURIComponent(meetingId))
      .then(function(res) {
        return (res && res.body) ? res.body : (res || null);
      })
      .catch(function() {
        return null;
      });
  }

  /* ── Apply session data to DOM ───────────────────── */

  function applySessionToDOM(sessionData, sessionId) {
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

    // Wire operator button
    var operatorBtn = document.getElementById('hubOperatorBtn');
    if (operatorBtn) {
      operatorBtn.href = '/operator/' + encodeURIComponent(sessionId);
    }

    // Wire main CTA button based on meeting status
    var mainBtn = document.getElementById('hubMainBtn');
    if (mainBtn) {
      var status = sessionData.status || '';
      if (status === 'draft' || status === 'scheduled') {
        mainBtn.textContent = 'Geler l\u2019ordre du jour';
        mainBtn.removeAttribute('href');
        mainBtn.setAttribute('data-action', 'freeze');
      } else if (status === 'frozen') {
        mainBtn.textContent = 'Ouvrir la s\u00e9ance';
        mainBtn.removeAttribute('href');
        mainBtn.setAttribute('data-action', 'open');
      } else if (status === 'live' || status === 'paused') {
        mainBtn.textContent = 'Aller \u00e0 la console';
        mainBtn.href = '/operator/' + encodeURIComponent(sessionId);
        mainBtn.removeAttribute('data-action');
      } else if (status === 'closed' || status === 'validated' || status === 'archived') {
        mainBtn.textContent = 'Voir l\u2019archive';
        mainBtn.href = '/postsession/' + encodeURIComponent(sessionId);
        mainBtn.removeAttribute('data-action');
      } else {
        mainBtn.textContent = 'Ouvrir la s\u00e9ance';
        mainBtn.removeAttribute('href');
        mainBtn.setAttribute('data-action', 'open');
      }
    }

    // Wire data-action buttons (freeze / open)
    setupLifecycleBtn(sessionId);
  }

  /* ── Lifecycle button (freeze / open) ───────────── */

  function setupLifecycleBtn(sessionId) {
    var mainBtn = document.getElementById('hubMainBtn');
    if (!mainBtn) return;
    var action = mainBtn.getAttribute('data-action');
    if (!action) return; // href-based navigation, no JS needed

    mainBtn.addEventListener('click', function() {
      var toStatus = action === 'freeze' ? 'frozen' : 'live';
      mainBtn.disabled = true;
      var origText = mainBtn.textContent;
      mainBtn.textContent = 'En cours\u2026';
      window.api('/api/v1/meeting_transition', { meeting_id: sessionId, to_status: toStatus }, 'POST')
        .then(function(res) {
          if (res && res.body && res.body.ok) {
            AgToast.show('success', 'Statut mis \u00e0 jour');
            loadData();
          } else {
            var issues = (res && res.body && res.body.data && res.body.data.issues) ? res.body.data.issues : [];
            var msg = issues.length > 0 ? issues[0].msg : 'Impossible de changer le statut';
            AgToast.show('error', msg);
            mainBtn.disabled = false;
            mainBtn.textContent = origText;
          }
        })
        .catch(function() {
          AgToast.show('error', 'Erreur lors du changement de statut');
          mainBtn.disabled = false;
          mainBtn.textContent = origText;
        });
    });
  }

  /* ── Map API data to session object ──────────────── */

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

    var dateDisplay = data.date || '';
    if (data.scheduled_at) {
      try {
        var d = new Date(data.scheduled_at);
        dateDisplay = d.toLocaleDateString('fr-FR', { day: 'numeric', month: 'long', year: 'numeric', hour: '2-digit', minute: '2-digit' });
      } catch (e) { /* keep fallback */ }
    } else if (data.date && data.time) {
      dateDisplay = data.date + ' \u00e0 ' + data.time;
    }

    return {
      title: data.title || '',
      date: data.scheduled_at || data.date || '',
      dateDisplay: dateDisplay,
      place: data.location || [data.place, data.address].filter(Boolean).join(', ') || '',
      memberCount: memberCount,
      resolutionCount: resolutionCount,
      quorumRequired: data.quorum_required || (data.has_president ? Math.ceil(memberCount * 0.5) + 1 : 0),
      quorumMet: data.quorum_met === true,
      presentCount: data.present_count || 0,
      status: data.status || data.meeting_status || '',
      type_label: data.meeting_type ? data.meeting_type.replace(/_/g, ' ').toUpperCase() : '',
      status_label: data.meeting_status ? data.meeting_status.charAt(0).toUpperCase() + data.meeting_status.slice(1) : '',
      motions: Array.isArray(data.resolutions) ? data.resolutions : (Array.isArray(data.motions) ? data.motions : [])
    };
  }

  function showHubError() {
    if (window.Shared && Shared.showToast) {
      Shared.showToast('Impossible de charger la s\u00e9ance.', 'error');
    }
    var content = document.getElementById('main-content') || document.querySelector('.hub-main');
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
    var sessionId = params.get('id') || params.get('meeting_id');
    // Clean URL: /hub/UUID
    if (!sessionId) {
      var pathMatch = window.location.pathname.match(/^\/hub\/([0-9a-f-]+)$/i);
      if (pathMatch) sessionId = pathMatch[1];
    }

    if (!sessionId) {
      sessionStorage.setItem('ag-vote-toast', JSON.stringify({
        msg: 'Identifiant de s\u00e9ance manquant', type: 'error'
      }));
      window.location.href = '/dashboard';
      return;
    }

    var attempt = 0;
    async function tryLoad() {
      attempt++;
      try {
        // Load wizard_status, invitation stats, and workflow check in parallel
        var results = await Promise.all([
          window.api('/api/v1/wizard_status?meeting_id=' + encodeURIComponent(sessionId)),
          loadInvitationStats(sessionId),
          loadWorkflowCheck(sessionId)
        ]);

        var res = results[0];
        var invitationStats = results[1];
        var workflowData = results[2];

        if (res && res.body && res.body.ok && res.body.data) {
          var data = res.body.data;
          var sessionData = mapApiDataToSession(data);
          applySessionToDOM(sessionData, sessionId);
          renderChecklist(sessionData, invitationStats, workflowData);
          renderQuorumBar(sessionData);
          // Load motions list separately (wizard_status only returns count)
          window.api('/api/v1/motions_for_meeting?meeting_id=' + encodeURIComponent(sessionId))
            .then(function(mRes) {
              var items = (mRes && mRes.body && mRes.body.data && mRes.body.data.items) ? mRes.body.data.items : [];
              renderMotionsList(items, sessionId);
            })
            .catch(function() {
              renderMotionsList([], sessionId);
            });
          setupConvocationBtn(invitationStats, sessionId);
          loadMeetingAttachments(sessionId);
          return;
        }
        if (res && res.body && res.body.error === 'meeting_not_found') {
          sessionStorage.setItem('ag-vote-toast', JSON.stringify({
            msg: 'S\u00e9ance introuvable', type: 'error'
          }));
          window.location.href = '/dashboard';
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

  /* ── Toast pickup from wizard redirect ───────────── */

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

  /* ── Init ─────────────────────────────────────────── */

  function init() {
    checkToast();
    loadData().catch(function() { /* silent */ });
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
  } else {
    init();
  }
})();
