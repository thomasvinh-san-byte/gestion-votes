// /assets/js/archive-meeting.js

/**
 * Archive la séance actuellement sélectionnée dans #meeting_select.
 * Effet :
 *  - statut = 'archived' côté backend (via meetings_update.php)
 *  - la séance disparaît de "Séances actives"
 *  - reste visible dans l'historique
 */

async function archiveCurrentMeeting() {
    if (!state || !state.currentMeetingId) {
        setNotif('error', "Aucune séance sélectionnée à archiver.");
        return;
    }

    if (!confirm("Archiver cette séance ? Elle sera déplacée dans l'historique et ne sera plus modifiable.")) {
        return;
    }

    const payload = {
        meeting_id: state.currentMeetingId,
        status: 'archived'
    };

    try {
        const res = await api("/api/v1/meetings_update.php", payload);

        if (!res.body || !res.body.ok) {
            setNotif('error', "Erreur archivage séance : " + (res.body && res.body.error || res.status));
            return;
        }

        setNotif('success', "Séance archivée. Elle est désormais dans l'historique.");

        // Rafraîchir les listes de séances : à adapter selon ton code existant
        if (typeof loadMeetings === 'function') {
            await loadMeetings();
        } else if (typeof refreshMeetings === 'function') {
            await refreshMeetings();
        }

        // Réinitialiser la sélection
        const select = document.getElementById('meeting_select');
        if (select) {
            select.value = "";
        }
        state.currentMeetingId = null;

        const btnArchive = document.getElementById('btn_archive_meeting');
        if (btnArchive) {
            btnArchive.style.display = 'none';
        }

        const odjList = document.getElementById('agendas_motions_list');
        if (odjList) {
            odjList.innerHTML = "";
        }
        const presDiv = document.getElementById('selected_meeting_president');
        if (presDiv) {
            presDiv.textContent = "";
        }
    } catch (e) {
        setNotif('error', "Erreur réseau lors de l'archivage : " + e.message);
    }
}

// Affichage/masquage du bouton selon la sélection de séance
function updateArchiveButtonVisibility() {
    const btnArchive = document.getElementById('btn_archive_meeting');
    const select     = document.getElementById('meeting_select');
    if (!btnArchive || !select) return;

    const meetingId = select.value || '';
    if (meetingId) {
        btnArchive.style.display = 'inline-block';
        state.currentMeetingId = meetingId;
    } else {
        btnArchive.style.display = 'none';
        state.currentMeetingId = null;
    }
}

document.addEventListener('DOMContentLoaded', () => {
    const btnArchive = document.getElementById('btn_archive_meeting');
    if (btnArchive) {
        btnArchive.addEventListener('click', (e) => {
            e.preventDefault();
            archiveCurrentMeeting();
        });
    }

    const select = document.getElementById('meeting_select');
    if (select) {
        // on recale l'affichage du bouton à chaque changement
        select.addEventListener('change', updateArchiveButtonVisibility);
        // et une fois au chargement
        updateArchiveButtonVisibility();
    }
});
