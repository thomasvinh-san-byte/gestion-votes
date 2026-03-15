/**
 * postsession.js — Post-session guided workflow controller.
 *
 * Drives the 4-step closing flow:
 *   1. Verification  — success alert + 5-column results table
 *   2. Validation    — official state transition (closed → validated)
 *   3. Procès-verbal — signataires, observations, eIDAS chips, generate, preview, export PDF
 *   4. Send & Archive — email PV, export data, archive meeting
 */
(function () {
  'use strict';

  // =========================================================================
  // STATE
  // =========================================================================

  var _currentStep = 1;
  var meetingId = null;
  var _meetingData = null;
  var _sigCount = 0;

  // =========================================================================
  // HELPERS
  // =========================================================================

  function esc(s) { return Utils.escapeHtml(s); }

  // Use global setNotif() from utils.js (delegates to AgToast.show)

  // =========================================================================
  // STEPPER NAVIGATION
  // =========================================================================

  var CHECK_SVG = '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6 9 17l-5-5"/></svg>';

  var STEP_LABELS = { 1: 'V\u00e9rification', 2: 'Validation', 3: 'Proc\u00e8s-verbal', 4: 'Envoi & Archivage' };

  function goToStep(step) {
    if (step < 1 || step > 4) return;
    _currentStep = step;

    // Update segmented bar stepper UI
    document.querySelectorAll('.ps-seg').forEach(function (el) {
      var s = parseInt(el.getAttribute('data-step'), 10);
      el.classList.remove('active', 'done');
      el.removeAttribute('aria-current');
      if (s < step) {
        el.classList.add('done');
        el.innerHTML = CHECK_SVG + ' ' + STEP_LABELS[s];
      } else if (s === step) {
        el.classList.add('active');
        el.setAttribute('aria-current', 'step');
        el.innerHTML = '<span class="ps-seg-num">' + s + '.</span> ' + STEP_LABELS[s];
      } else {
        el.innerHTML = '<span class="ps-seg-num">' + s + '.</span> ' + STEP_LABELS[s];
      }
    });

    // Show/hide panels
    for (var i = 1; i <= 4; i++) {
      var panel = document.getElementById('panel-' + i);
      if (panel) panel.hidden = i !== step;
    }

    // Update shared footer nav
    updateFooterNav(step);

    // Load step data
    if (step === 1) loadVerification();
    if (step === 2) loadValidation();
    if (step === 3) loadPV();
    if (step === 4) loadSendArchive();
  }

  // =========================================================================
  // SHARED FOOTER NAV
  // =========================================================================

  function updateFooterNav(step) {
    var counter = document.getElementById('psStepCounter');
    var btnPrev = document.getElementById('btnPrecedent');
    var btnNext = document.getElementById('btnSuivant');

    if (counter) counter.textContent = 'Etape ' + step + ' / 4';

    if (btnPrev) {
      btnPrev.hidden = step === 1;
    }

    if (btnNext) {
      if (step === 4) {
        btnNext.hidden = true;
      } else {
        btnNext.hidden = false;
        // Step 1: disabled until results load without critical issues
        // Step 2: disabled until validation completes
        // Step 3: always enabled (PV can be generated at any time)
        if (step === 3) {
          btnNext.disabled = false;
        }
        // Step 1 and 2 disable state is managed by loadVerification/loadValidation
      }
    }
  }

  // =========================================================================
  // STEP 1: VERIFICATION
  // =========================================================================

  async function loadVerification() {
    if (!meetingId) return;

    // Default: disable Suivant until results load
    var btnNext = document.getElementById('btnSuivant');
    if (btnNext) btnNext.disabled = true;

    try {
      var res = await window.api('/api/v1/meeting_motions.php?meeting_id=' + encodeURIComponent(meetingId));
      var d = res.body;
      if (d && d.ok && d.data) {
        var motions = d.data.items || d.data || [];
        loadResultsTable(motions);
        // Show success alert
        var alert = document.getElementById('verifyAlert');
        if (alert) alert.hidden = false;
        // Enable Suivant
        if (btnNext) btnNext.disabled = false;
      }
    } catch (e) {
      // Fallback: try meeting_summary for basic data
      try {
        var res2 = await window.api('/api/v1/meeting_summary.php?meeting_id=' + encodeURIComponent(meetingId));
        var d2 = res2.body;
        if (d2 && d2.ok && d2.data) {
          var alert2 = document.getElementById('verifyAlert');
          if (alert2) alert2.hidden = false;
          if (btnNext) btnNext.disabled = false;
        }
      } catch (e2) { /* silent */ }
    }
  }

  function loadResultsTable(motions) {
    var tbody = document.getElementById('resultsTableBody');
    if (!tbody) return;

    if (!motions || motions.length === 0) {
      tbody.innerHTML = '<tr><td colspan="5" class="text-center text-muted p-4">Aucune r\u00e9solution trouv\u00e9e.</td></tr>';
      return;
    }

    tbody.innerHTML = motions.map(function (m, i) {
      var adopted = m.result === 'adopted' || m.decision === 'adopted' || m.passed === true;
      var resultTag = adopted
        ? '<span class="tag tag-success"><svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3"><path d="M20 6 9 17l-5-5"/></svg> Adopt\u00e9e</span>'
        : '<span class="tag tag-danger"><svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg> Rejet\u00e9e</span>';
      var pour = m.votes_for != null ? m.votes_for : (m.pour != null ? m.pour : '—');
      var contre = m.votes_against != null ? m.votes_against : (m.contre != null ? m.contre : '—');
      var abst = m.votes_abstain != null ? m.votes_abstain : (m.abstentions != null ? m.abstentions : '—');
      var majorite = m.majority_type || m.majorite || '—';
      return '<tr>' +
        '<td class="result-num">' + (i + 1) + '</td>' +
        '<td>' + esc(m.title || m.label || '') + '</td>' +
        '<td>' + resultTag + '</td>' +
        '<td class="result-num">' + pour + ' / ' + contre + ' / ' + abst + '</td>' +
        '<td>' + esc(String(majorite)) + '</td>' +
        '</tr>';
    }).join('');
  }

  // =========================================================================
  // STEP 2: VALIDATION
  // =========================================================================

  async function loadValidation() {
    if (!meetingId) return;

    // Default: disable Suivant until validated
    var btnNext = document.getElementById('btnSuivant');
    if (btnNext) btnNext.disabled = true;

    // Populate step 2 KPIs from summary
    try {
      var summaryRes = await window.api('/api/v1/meeting_summary.php?meeting_id=' + encodeURIComponent(meetingId));
      if (summaryRes.body && summaryRes.body.ok && summaryRes.body.data) {
        var s = summaryRes.body.data;
        setStatValue('vkpiResolutions', s.total_motions || s.motions_count || 0);
        setStatValue('vkpiAdopted', s.adopted || 0);
        setStatValue('vkpiRejected', s.rejected || 0);
        var rate = s.participation_rate || s.present_count;
        setStatValue('vkpiParticipation', rate != null ? rate + (s.participation_rate ? '%' : '') : 0);
      }
    } catch (e) { /* KPI load failure is non-blocking */ }

    try {
      var res = await window.api('/api/v1/meeting_workflow_check.php?meeting_id=' + encodeURIComponent(meetingId));
      var d = res.body;
      if (d && d.ok && d.data) {
        var state = d.data.current_state || '';
        _meetingData = d.data;

        // Update workflow state visual
        var states = ['closed', 'validated', 'archived'];
        var stateIdx = states.indexOf(state);
        states.forEach(function (sv, i) {
          var el = document.getElementById('wsStep-' + sv);
          if (!el) return;
          el.classList.remove('active', 'done');
          if (i < stateIdx) el.classList.add('done');
          else if (i === stateIdx) el.classList.add('active');
        });

        // Show available transitions
        var actions = document.getElementById('transitionActions');
        var transitions = d.data.available_transitions || [];
        if (actions) {
          // Enable/disable static validation buttons based on state
          var staticBtnValidate = document.getElementById('btnValidate');
          var staticBtnReject = document.getElementById('btnReject');

          if (transitions.length === 0 && state === 'validated') {
            actions.innerHTML = '<p class="text-sm text-success">S\u00e9ance valid\u00e9e. Vous pouvez g\u00e9n\u00e9rer le PV.</p>';
            if (staticBtnValidate) staticBtnValidate.disabled = true;
            if (staticBtnReject) staticBtnReject.disabled = true;
            if (btnNext) btnNext.disabled = false;
          } else if (state === 'closed') {
            actions.innerHTML = '<p class="text-sm text-muted">Cette action verrouille d\u00e9finitivement les r\u00e9sultats de vote.</p>';
            if (staticBtnValidate) { staticBtnValidate.disabled = false; staticBtnValidate.onclick = doValidate; }
          } else {
            actions.innerHTML = '<p class="text-sm text-muted">\u00c9tat actuel : <strong>' + esc(state) + '</strong></p>';
            if (staticBtnValidate) staticBtnValidate.disabled = true;
            if (btnNext) btnNext.disabled = false;
          }
        }
      }
    } catch (e) {
      var actionsEl = document.getElementById('transitionActions');
      if (actionsEl) actionsEl.innerHTML = '<p class="text-sm text-muted">Impossible de v\u00e9rifier l\u2019\u00e9tat de la s\u00e9ance.</p>';
    }
  }

  function setStatValue(id, val) {
    var el = document.getElementById(id);
    if (el) el.textContent = String(val);
  }

  async function doValidate() {
    var btn = document.getElementById('btnValidate');
    if (!btn) return;
    Shared.btnLoading(btn, true);

    try {
      var res = await window.api('/api/v1/meeting_transition.php', { meeting_id: meetingId, to_status: 'validated' });
      var d = res.body;
      if (d && d.ok) {
        setNotif('success', 'S\u00e9ance valid\u00e9e avec succ\u00e8s');
        var btnNext = document.getElementById('btnSuivant');
        if (btnNext) btnNext.disabled = false;
        loadValidation(); // refresh state
      } else {
        setNotif('error', d.error || 'Erreur de validation');
      }
    } catch (e) {
      setNotif('error', 'Erreur r\u00e9seau');
    } finally {
      Shared.btnLoading(btn, false);
    }
  }

  // =========================================================================
  // STEP 3: PROCÈS-VERBAL
  // =========================================================================

  async function loadPV() {
    if (!meetingId) return;

    // Set PDF export link
    var pdfLink = document.getElementById('btnExportPDF');
    if (pdfLink) {
      pdfLink.href = '/api/v1/meeting_generate_report_pdf.php?meeting_id=' + encodeURIComponent(meetingId);
    }

    // Load signataire names from meeting data
    try {
      var res = await window.api('/api/v1/meetings.php?id=' + encodeURIComponent(meetingId));
      if (res.body && res.body.ok && res.body.data) {
        var data = res.body.data;
        var roles = {
          sigPresident: data.president_name || data.president || '',
          sigSecretary: data.secretary_name || data.secretary || '',
          sigScrutateur1: data.scrutateur1_name || data.scrutateur1 || '',
          sigScrutateur2: data.scrutateur2_name || data.scrutateur2 || ''
        };
        Object.keys(roles).forEach(function (id) {
          var el = document.getElementById(id);
          if (el && roles[id]) el.value = roles[id];
        });
      }
    } catch (e) { /* signataire names remain as default */ }
  }

  // =========================================================================
  // STEP 4: SEND & ARCHIVE
  // =========================================================================

  function loadSendArchive() {
    if (!meetingId) return;

    // Set export links
    var links = {
      exportAttendanceCsv: '/api/v1/export_attendance_csv.php?meeting_id=',
      exportVotesCsv: '/api/v1/export_votes_csv.php?meeting_id=',
      exportPvPdf: '/api/v1/meeting_generate_report_pdf.php?meeting_id=',
      exportEmargement: '/api/v1/export_attendance_csv.php?meeting_id=',
      exportResultsCsv: '/api/v1/export_motions_results_csv.php?meeting_id=',
      exportAuditCsv: '/api/v1/audit_export.php?meeting_id=',
      exportCorrespondance: '/api/v1/export_correspondance.php?meeting_id=',
      pvSummaryDownload: '/api/v1/meeting_generate_report_pdf.php?meeting_id='
    };
    Object.keys(links).forEach(function (id) {
      var el = document.getElementById(id);
      if (el) el.href = links[id] + encodeURIComponent(meetingId);
    });

    // Populate PV summary card
    if (_meetingData) {
      var pvTitle = document.getElementById('pvSummaryTitle');
      var pvResolutions = document.getElementById('pvSummaryResolutions');
      var pvRate = document.getElementById('pvSummaryRate');
      if (pvTitle) pvTitle.textContent = _meetingData.title || 'S\u00e9ance';
      if (pvResolutions) pvResolutions.textContent = _meetingData.total_motions || '—';
      if (pvRate) pvRate.textContent = _meetingData.adoption_rate != null ? _meetingData.adoption_rate + '%' : '—';
    } else {
      // Try to load summary data for PV card
      window.api('/api/v1/meeting_summary.php?meeting_id=' + encodeURIComponent(meetingId))
        .then(function (res) {
          if (res.body && res.body.ok && res.body.data) {
            var s = res.body.data;
            var pvTitle = document.getElementById('pvSummaryTitle');
            var pvResolutions = document.getElementById('pvSummaryResolutions');
            var pvRate = document.getElementById('pvSummaryRate');
            if (pvTitle && s.title) pvTitle.textContent = s.title;
            if (pvResolutions) pvResolutions.textContent = s.total_motions || s.motions_count || '—';
            if (pvRate) pvRate.textContent = s.adoption_rate != null ? s.adoption_rate + '%' : '—';
          }
        })
        .catch(function () { /* silent */ });
    }
  }

  // =========================================================================
  // EVENT BINDINGS
  // =========================================================================

  function bindNavigation() {
    // Shared footer nav buttons
    click('btnPrecedent', function () { goToStep(_currentStep - 1); });
    click('btnSuivant', function () { goToStep(_currentStep + 1); });

    // Generate PV
    click('btnGenerateReport', async function () {
      var btn = document.getElementById('btnGenerateReport');
      Shared.btnLoading(btn, true);
      try {
        var res = await window.api('/api/v1/meeting_generate_report.php', { meeting_id: meetingId });
        var d = res.body;
        if (d && d.ok) {
          var preview = document.getElementById('pvPreview');
          if (preview && d.data && d.data.html) {
            preview.innerHTML = '<iframe srcdoc="' + esc(d.data.html) + '" sandbox="allow-same-origin"></iframe>';
          } else if (preview) {
            preview.innerHTML = '<p class="text-center text-success p-8">PV g\u00e9n\u00e9r\u00e9 avec succ\u00e8s.</p>';
          }
          // Show hash
          var hashBar = document.getElementById('pvHashBar');
          var hashEl = document.getElementById('pvHash');
          if (d.data && d.data.hash && hashBar && hashEl) {
            hashEl.textContent = d.data.hash;
            hashBar.hidden = false;
          }
          setNotif('success', 'Proc\u00e8s-verbal g\u00e9n\u00e9r\u00e9');
        } else {
          setNotif('error', d.error || 'Erreur de g\u00e9n\u00e9ration');
        }
      } catch (e) {
        setNotif('error', 'Erreur r\u00e9seau');
      } finally {
        Shared.btnLoading(btn, false);
      }
    });

    // Send PV by email
    click('btnSendReport', async function () {
      var btn = document.getElementById('btnSendReport');
      var sendTo = document.getElementById('sendTo').value;
      var payload = { meeting_id: meetingId, recipients: sendTo };

      if (sendTo === 'custom') {
        var email = (document.getElementById('customEmail').value || '').trim();
        if (!email) { setNotif('error', 'Veuillez saisir une adresse email'); return; }
        payload.email = email;
      }

      Shared.btnLoading(btn, true);
      try {
        var res = await window.api('/api/v1/meeting_report_send.php', payload);
        var d = res.body;
        if (d && d.ok) {
          setNotif('success', 'PV envoy\u00e9 avec succ\u00e8s');
        } else {
          setNotif('error', d.error || 'Erreur d\'envoi');
        }
      } catch (e) {
        setNotif('error', 'Erreur r\u00e9seau');
      } finally {
        Shared.btnLoading(btn, false);
      }
    });

    // Archive
    click('btnArchive', function () {
      Shared.openModal({
        title: 'Archiver la s\u00e9ance',
        body: '<p>Archiver d\u00e9finitivement cette s\u00e9ance ?</p>' +
              '<p class="text-sm text-muted">Cette action est irr\u00e9versible. Les donn\u00e9es resteront consultables dans les archives.</p>',
        confirmText: 'Archiver',
        confirmClass: 'btn btn-danger',
        onConfirm: async function() {
          var btn = document.getElementById('btnArchive');
          Shared.btnLoading(btn, true);
          try {
            var res = await window.api('/api/v1/meetings_archive.php', { meeting_id: meetingId });
            var d = res.body;
            if (d && d.ok) {
              setNotif('success', 'S\u00e9ance archiv\u00e9e');
              var banner = document.getElementById('completeBanner');
              if (banner) banner.hidden = false;
              var archiveCard = document.getElementById('archiveCard');
              if (archiveCard) archiveCard.hidden = true;
            } else {
              setNotif('error', d.error || 'Erreur d\'archivage');
            }
          } catch (e) {
            setNotif('error', 'Erreur r\u00e9seau');
          } finally {
            Shared.btnLoading(btn, false);
          }
        }
      });
    });

    // Stepper segments — back-only click navigation
    document.querySelectorAll('.ps-seg').forEach(function (el) {
      el.addEventListener('click', function () {
        var s = parseInt(el.getAttribute('data-step'), 10);
        if (!isNaN(s) && s < _currentStep) {
          goToStep(s);
        }
      });
    });

    // Custom email toggle
    var sendToEl = document.getElementById('sendTo');
    if (sendToEl) {
      sendToEl.addEventListener('change', function () {
        var grp = document.getElementById('customEmailGroup');
        if (grp) grp.hidden = sendToEl.value !== 'custom';
      });
    }

    // eIDAS chip toggle
    var chipGroup = document.getElementById('eidasChips');
    if (chipGroup) {
      chipGroup.addEventListener('click', function (e) {
        var chip = e.target.closest('.chip');
        if (!chip) return;
        chipGroup.querySelectorAll('.chip').forEach(function (c) { c.classList.remove('active'); });
        chip.classList.add('active');
      });
    }

    // Sign button handlers
    click('btnSignPresident', function () {
      var btn = document.getElementById('btnSignPresident');
      if (btn && !btn.dataset.signed) {
        btn.dataset.signed = '1';
        btn.disabled = true;
        _sigCount++;
        updateSigCounter();
        setNotif('success', 'Signature du pr\u00e9sident enregistr\u00e9e');
      }
    });

    click('btnSignSecretary', function () {
      var btn = document.getElementById('btnSignSecretary');
      if (btn && !btn.dataset.signed) {
        btn.dataset.signed = '1';
        btn.disabled = true;
        _sigCount++;
        updateSigCounter();
        setNotif('success', 'Signature du secr\u00e9taire enregistr\u00e9e');
      }
    });
  }

  function updateSigCounter() {
    var counter = document.getElementById('sigCounter');
    if (counter) counter.textContent = _sigCount + '/2 signatures';
  }

  function click(id, fn) {
    var el = document.getElementById(id);
    if (el) el.addEventListener('click', fn);
  }

  // =========================================================================
  // INIT
  // =========================================================================

  async function init() {
    // Wait for MeetingContext
    if (typeof MeetingContext !== 'undefined') {
      meetingId = MeetingContext.get();
    }
    if (!meetingId) {
      var params = new URLSearchParams(window.location.search);
      meetingId = params.get('meeting_id');
    }

    if (!meetingId) {
      // Show meeting picker with available closed/validated meetings
      var picker = document.getElementById('meetingPicker');
      var selectEl = document.getElementById('meetingPickerSelect');
      if (picker) {
        picker.hidden = false;
        try {
          var res = await window.api('/api/v1/meetings.php');
          var meetings = (res.body && res.body.ok && res.body.data) ? (res.body.data.items || []) : [];
          var eligible = meetings.filter(function (m) {
            return m.status === 'closed' || m.status === 'validated' || m.status === 'archived';
          });
          if (selectEl && eligible.length) {
            selectEl.innerHTML = '<option value="">\u2014 S\u00e9lectionner une s\u00e9ance \u2014</option>' +
              eligible.map(function (m) {
                var st = (Shared.MEETING_STATUS_MAP[m.status] || {}).text || m.status;
                return '<option value="' + m.id + '">' + Utils.escapeHtml(m.title) + ' (' + Utils.escapeHtml(st) + ')</option>';
              }).join('');
            selectEl.addEventListener('change', function () {
              if (selectEl.value) {
                window.location.href = '/postsession.htmx.html?meeting_id=' + encodeURIComponent(selectEl.value);
              }
            });
          } else if (selectEl) {
            selectEl.innerHTML = '<option value="">Aucune s\u00e9ance termin\u00e9e</option>';
          }
        } catch (e) {
          if (selectEl) selectEl.innerHTML = '<option value="">Erreur de chargement</option>';
        }
      }
      return;
    }

    // Update meeting title
    try {
      var res = await window.api('/api/v1/meetings.php?id=' + meetingId);
      if (res.body && res.body.ok && res.body.data) {
        var titleEl = document.getElementById('meetingTitle');
        if (titleEl) titleEl.textContent = res.body.data.title || 'S\u00e9ance';
      }
    } catch (e) {
      var titleEl = document.getElementById('meetingTitle');
      if (titleEl) titleEl.textContent = 'S\u00e9ance';
    }

    bindNavigation();
    goToStep(1);
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
  } else {
    init();
  }

})();
