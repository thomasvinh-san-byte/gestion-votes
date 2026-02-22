/** report.js — Meeting report/PV page for AG-VOTE. Must be loaded AFTER utils.js, shared.js and shell.js. */
(function() {
    'use strict';

    let currentMeetingId = MeetingContext.get();
    if (!currentMeetingId) {
      setNotif('error', 'Aucune séance sélectionnée');
      setTimeout(() => window.location.href = '/meetings.htmx.html', 2000);
    }

    // Setup URLs
    function setupUrls() {
      const reportUrl = `/api/v1/meeting_report.php?meeting_id=${currentMeetingId}`;
      const pdfUrl = `/api/v1/meeting_generate_report_pdf.php?meeting_id=${currentMeetingId}`;

      // Set iframe src
      document.getElementById('pvFrame').src = reportUrl;

      // Set links
      document.getElementById('btnExportPDF').href = pdfUrl;
      document.getElementById('btnOpenNewTab').href = reportUrl;
      document.getElementById('exportPV').href = reportUrl;

      // CSV exports
      document.getElementById('exportAttendance').href = `/api/v1/export_attendance_csv.php?meeting_id=${currentMeetingId}`;
      document.getElementById('exportVotes').href = `/api/v1/export_votes_csv.php?meeting_id=${currentMeetingId}`;
      document.getElementById('exportMotions').href = `/api/v1/export_motions_results_csv.php?meeting_id=${currentMeetingId}`;
      document.getElementById('exportMembers').href = `/api/v1/export_members_csv.php?meeting_id=${currentMeetingId}`;
      document.getElementById('exportAudit').href = `/api/v1/audit_export.php?meeting_id=${currentMeetingId}`;

      // XLSX exports
      document.getElementById('exportFullXlsx').href = `/api/v1/export_full_xlsx.php?meeting_id=${currentMeetingId}&include_votes=0`;
      document.getElementById('exportFullXlsxWithVotes').href = `/api/v1/export_full_xlsx.php?meeting_id=${currentMeetingId}&include_votes=1`;
      document.getElementById('exportAttendanceXlsx').href = `/api/v1/export_attendance_xlsx.php?meeting_id=${currentMeetingId}`;
      document.getElementById('exportVotesXlsx').href = `/api/v1/export_votes_xlsx.php?meeting_id=${currentMeetingId}`;
      document.getElementById('exportResultsXlsx').href = `/api/v1/export_results_xlsx.php?meeting_id=${currentMeetingId}`;

      // Navigation link
      var archiveLink = document.getElementById('reportToArchives');
      if (archiveLink) archiveLink.href = `/archives.htmx.html?meeting_id=${encodeURIComponent(currentMeetingId)}`;
    }

    // Disable export buttons
    function disableExports() {
      const exportIds = [
        'exportPV', 'exportAttendance', 'exportVotes', 'exportMotions', 'exportMembers', 'exportAudit', 'btnExportPDF',
        'exportFullXlsx', 'exportFullXlsxWithVotes', 'exportAttendanceXlsx', 'exportVotesXlsx', 'exportResultsXlsx'
      ];
      exportIds.forEach(id => {
        const el = document.getElementById(id);
        if (el) {
          el.classList.add('disabled');
          el.style.pointerEvents = 'none';
          el.style.opacity = '0.5';
          el.removeAttribute('href');
          el.title = 'Exports disponibles après validation';
        }
      });
      // Also disable email send
      const btnSend = document.getElementById('btnSendEmail');
      if (btnSend) {
        btnSend.disabled = true;
        btnSend.title = 'Envoi disponible après validation';
      }
    }

    // Load meeting info
    async function loadMeetingInfo() {
      try {
        const { body } = await api(`/api/v1/meetings.php?id=${currentMeetingId}`);
        if (body && body.ok && body.data) {
          document.getElementById('meetingTitle').textContent = body.data.title;
          document.getElementById('meetingName').textContent = body.data.title;
          Shared.show(document.getElementById('meetingContext'), 'flex');

          // Check validation status - disable exports if not validated
          const isValidated = body.data.status === 'validated' || body.data.status === 'archived';
          if (!isValidated) {
            Shared.show(document.getElementById('notValidatedWarning'), 'flex');
            disableExports();
          }
        }
      } catch (err) {
        setNotif('error', 'Erreur chargement informations séance');
      }
    }

    // Send email
    document.getElementById('btnSendEmail').addEventListener('click', async () => {
      const email = document.getElementById('email').value.trim();
      const msgDiv = document.getElementById('emailMsg');

      if (!email || !Utils.isValidEmail(email)) {
        Shared.show(msgDiv, 'block');
        msgDiv.className = 'alert alert-danger';
        msgDiv.textContent = !email ? 'Veuillez saisir une adresse email' : 'Adresse email invalide';
        return;
      }

      const btn = document.getElementById('btnSendEmail');
      Shared.btnLoading(btn, true);
      try {
        const { body } = await api('/api/v1/meeting_report_send.php', {
          meeting_id: currentMeetingId,
          email
        });

        if (body && body.ok) {
          Shared.show(msgDiv, 'block');
          msgDiv.className = 'alert alert-success';
          msgDiv.innerHTML = `${icon('check-circle', 'icon-md icon-success')} PV envoyé avec succès !`;
          setNotif('success', 'Email envoyé');
        } else {
          Shared.show(msgDiv, 'block');
          msgDiv.className = 'alert alert-danger';
          msgDiv.innerHTML = `${icon('x-circle', 'icon-md icon-danger')} Erreur: ${escapeHtml(body?.error || 'Envoi impossible')}`;
        }
      } catch (err) {
        Shared.show(msgDiv, 'block');
        msgDiv.className = 'alert alert-danger';
        msgDiv.innerHTML = `${icon('x-circle', 'icon-md icon-danger')} Erreur: ${escapeHtml(err.message)}`;
      } finally {
        Shared.btnLoading(btn, false);
      }
    });

    // Initialize
    if (currentMeetingId) {
      setupUrls();
      loadMeetingInfo();
    }
  })();
