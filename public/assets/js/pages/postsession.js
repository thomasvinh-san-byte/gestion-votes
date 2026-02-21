/**
 * postsession.js — Post-session guided workflow controller.
 *
 * Drives the 4-step closing flow:
 *   1. Verification  — summary stats + coherence checklist
 *   2. Validation    — official state transition (closed → validated)
 *   3. Procès-verbal — generate, preview, export PDF
 *   4. Send & Archive — email PV, export data, archive meeting
 */
(function () {
  'use strict';

  // =========================================================================
  // STATE
  // =========================================================================

  var currentStep = 1;
  var meetingId = null;
  var meetingData = null;

  // =========================================================================
  // HELPERS
  // =========================================================================

  function esc(s) { return Utils.escapeHtml(s); }

  function setNotif(type, msg) {
    if (typeof Utils !== 'undefined' && Utils.toast) {
      Utils.toast(type, msg);
    }
  }

  // =========================================================================
  // STEPPER NAVIGATION
  // =========================================================================

  function goToStep(step) {
    if (step < 1 || step > 4) return;
    currentStep = step;

    // Update stepper UI
    document.querySelectorAll('.ps-step').forEach(function (el) {
      var s = parseInt(el.getAttribute('data-step'), 10);
      el.classList.remove('active', 'done');
      el.removeAttribute('aria-current');
      if (s < step) el.classList.add('done');
      else if (s === step) {
        el.classList.add('active');
        el.setAttribute('aria-current', 'step');
      }
    });

    // Update connectors
    document.querySelectorAll('.ps-step-connector').forEach(function (el, i) {
      el.classList.toggle('done', i < step - 1);
    });

    // Show/hide panels
    for (var i = 1; i <= 4; i++) {
      var panel = document.getElementById('panel-' + i);
      if (panel) panel.hidden = i !== step;
    }

    // Load step data
    if (step === 1) loadVerification();
    if (step === 2) loadValidation();
    if (step === 3) loadPV();
    if (step === 4) loadSendArchive();
  }

  // =========================================================================
  // STEP 1: VERIFICATION
  // =========================================================================

  async function loadVerification() {
    if (!meetingId) return;

    try {
      var res = await window.api('/api/v1/meeting_summary.php?meeting_id=' + meetingId);
      var d = res.body;
      if (d && d.ok && d.data) {
        var s = d.data;
        setStatValue('statMembers', s.total_members || 0);
        setStatValue('statPresent', s.present_count || 0);
        setStatValue('statMotions', s.total_motions || 0);
        setStatValue('statAdopted', s.adopted || 0);
        setStatValue('statRejected', s.rejected || 0);
        setStatValue('statBallots', s.total_ballots || 0);
      }
    } catch (e) {
      // Stats remain at their default placeholder values
    }

    // Load readiness checks with retry
    var checklistEl = document.getElementById('verifyChecklist');
    if (checklistEl) {
      await Shared.withRetry({
        container: checklistEl,
        errorMsg: 'Impossible de charger les v\u00e9rifications',
        action: async function () {
          var res2 = await window.api('/api/v1/meeting_ready_check.php?meeting_id=' + meetingId);
          var d2 = res2.body;
          if (!d2 || !d2.ok) throw new Error(d2 && d2.error || 'Erreur');
          var checks = d2.data.checks || [];
          checklistEl.setAttribute('aria-busy', 'false');
          if (checks.length === 0) {
            checklistEl.innerHTML = '<p class="text-muted text-sm">Aucune v\u00e9rification disponible.</p>';
          } else {
            checklistEl.innerHTML = checks.map(function (c) {
              var cls = c.passed ? 'passed' : (c.optional ? '' : 'failed');
              return '<div class="checklist-item ' + cls + '">' +
                '<svg class="icon icon-sm" aria-hidden="true"><use href="/assets/icons.svg#icon-' + (c.passed ? 'check-circle' : 'x-circle') + '"></use></svg>' +
                '<span>' + esc(c.label || '') + '</span>' +
                '</div>';
            }).join('');
          }
          // Enable next button if no critical failures
          var hasCriticalFailure = checks.some(function (c) { return !c.passed && !c.optional; });
          var btn = document.getElementById('btnToStep2');
          if (btn) btn.disabled = hasCriticalFailure;
        }
      });
    }

    // Load anomalies
    try {
      var res3 = await window.api('/api/v1/operator_anomalies.php?meeting_id=' + meetingId);
      var d3 = res3.body;
      if (d3 && d3.ok && d3.data) {
        var items = d3.data.anomalies || d3.data.items || [];
        var alertsCard = document.getElementById('alertsCard');
        var alertsList = document.getElementById('alertsList');
        if (items.length > 0 && alertsCard && alertsList) {
          alertsCard.hidden = false;
          alertsList.innerHTML = items.map(function (a) {
            return '<div class="anomaly-alert">' +
              '<svg class="icon icon-sm" aria-hidden="true"><use href="/assets/icons.svg#icon-alert-triangle"></use></svg>' +
              '<div><strong class="text-sm">' + esc(a.code || 'Anomalie') + '</strong>' +
              '<p class="text-sm text-secondary">' + esc(a.message || a.detail || '') + '</p></div>' +
              '</div>';
          }).join('');
        }
      }
    } catch (e) { /* silent */ }
  }

  function setStatValue(id, val) {
    var el = document.getElementById(id);
    if (el) el.textContent = String(val);
  }

  // =========================================================================
  // STEP 2: VALIDATION
  // =========================================================================

  async function loadValidation() {
    if (!meetingId) return;

    try {
      var res = await window.api('/api/v1/meeting_workflow_check.php?meeting_id=' + meetingId);
      var d = res.body;
      if (d && d.ok && d.data) {
        var state = d.data.current_state || '';
        meetingData = d.data;

        // Update workflow state visual
        var states = ['closed', 'validated', 'archived'];
        var stateIdx = states.indexOf(state);
        states.forEach(function (s, i) {
          var el = document.getElementById('wsStep-' + s);
          if (!el) return;
          el.classList.remove('active', 'done');
          if (i < stateIdx) el.classList.add('done');
          else if (i === stateIdx) el.classList.add('active');
        });

        // Show available transitions
        var actions = document.getElementById('transitionActions');
        var transitions = d.data.available_transitions || [];
        if (actions) {
          if (transitions.length === 0 && state === 'validated') {
            actions.innerHTML = '<p class="text-sm text-success">S\u00e9ance valid\u00e9e. Vous pouvez g\u00e9n\u00e9rer le PV.</p>';
            var btn3 = document.getElementById('btnToStep3');
            if (btn3) btn3.disabled = false;
          } else if (state === 'closed') {
            actions.innerHTML =
              '<button class="btn btn-primary" id="btnValidate">' +
              '<svg class="icon icon-text" aria-hidden="true"><use href="/assets/icons.svg#icon-check-circle"></use></svg>' +
              ' Valider les r\u00e9sultats</button>' +
              '<p class="text-sm text-muted mt-2">Cette action verrouille d\u00e9finitivement les r\u00e9sultats de vote.</p>';
            document.getElementById('btnValidate').addEventListener('click', doValidate);
          } else {
            actions.innerHTML = '<p class="text-sm text-muted">\u00c9tat actuel : <strong>' + esc(state) + '</strong></p>';
            var btn3b = document.getElementById('btnToStep3');
            if (btn3b) btn3b.disabled = false;
          }
        }
      }
    } catch (e) {
      var actions = document.getElementById('transitionActions');
      if (actions) actions.innerHTML = '<p class="text-sm text-muted">Impossible de v\u00e9rifier l\u2019\u00e9tat de la s\u00e9ance.</p>';
    }
  }

  async function doValidate() {
    var btn = document.getElementById('btnValidate');
    if (!btn) return;
    Shared.btnLoading(btn, true);

    try {
      var res = await window.api('/api/v1/meeting_transition.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ meeting_id: meetingId, transition: 'validate' })
      });
      var d = res.body;
      if (d && d.ok) {
        setNotif('success', 'S\u00e9ance valid\u00e9e avec succ\u00e8s');
        var btn3 = document.getElementById('btnToStep3');
        if (btn3) btn3.disabled = false;
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
      exportResultsXlsx: '/api/v1/export_results_xlsx.php?meeting_id=',
      exportFullXlsx: '/api/v1/export_full_xlsx.php?meeting_id='
    };
    Object.keys(links).forEach(function (id) {
      var el = document.getElementById(id);
      if (el) el.href = links[id] + encodeURIComponent(meetingId);
    });
  }

  // =========================================================================
  // EVENT BINDINGS
  // =========================================================================

  function bindNavigation() {
    // Forward buttons
    click('btnToStep2', function () { goToStep(2); });
    click('btnToStep3', function () { goToStep(3); });
    click('btnToStep4', function () { goToStep(4); });

    // Back buttons
    click('btnBackToStep1', function () { goToStep(1); });
    click('btnBackToStep2', function () { goToStep(2); });
    click('btnBackToStep3', function () { goToStep(3); });

    // Generate PV
    click('btnGenerateReport', async function () {
      var btn = document.getElementById('btnGenerateReport');
      Shared.btnLoading(btn, true);
      try {
        var res = await window.api('/api/v1/meeting_generate_report.php', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({ meeting_id: meetingId })
        });
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
        var res = await window.api('/api/v1/meeting_report_send.php', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify(payload)
        });
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
            var res = await window.api('/api/v1/meetings_archive.php', {
              method: 'POST',
              headers: { 'Content-Type': 'application/json' },
              body: JSON.stringify({ meeting_id: meetingId })
            });
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

    // Custom email toggle
    var sendTo = document.getElementById('sendTo');
    if (sendTo) {
      sendTo.addEventListener('change', function () {
        var grp = document.getElementById('customEmailGroup');
        if (grp) grp.hidden = sendTo.value !== 'custom';
      });
    }
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
      if (picker) {
        picker.hidden = false;
        try {
          var res = await window.api('/api/v1/meetings.php');
          var meetings = (res.body && res.body.ok && res.body.data) ? (res.body.data.meetings || []) : [];
          var eligible = meetings.filter(function (m) {
            return m.status === 'closed' || m.status === 'validated' || m.status === 'archived';
          });
          var selectEl = document.getElementById('meetingPickerSelect');
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
        var title = document.getElementById('meetingTitle');
        if (title) title.textContent = res.body.data.title || 'S\u00e9ance';
      }
    } catch (e) {
      var title = document.getElementById('meetingTitle');
      if (title) title.textContent = 'S\u00e9ance';
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
