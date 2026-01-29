// public/assets/js/init.js
/**
 * INITIALISATION DE LA CONSOLE OPÉRATEUR
 * Branche les événements DOM et lance le premier chargement.
 */

document.addEventListener('DOMContentLoaded', function () {
    // Champs texte
    const meetingInput = document.getElementById('meeting_title');
    const agendaInput  = document.getElementById('agenda_title');
    const motionInput  = document.getElementById('motion_title');

    const meetingSelect = document.getElementById('meeting_select');
    const agendaSelect  = document.getElementById('agenda_select');

    const totalInput   = document.getElementById('total_voters');
    const forInput     = [document.getElementById('for_votes')];
    const againstInput = document.getElementById('against_votes');
    const abstainInput = document.getElementById('abstention_votes');

    // Soumission par Entrée
    if (meetingInput && typeof createMeeting === 'function') {
        meetingInput.addEventListener('keypress', function (e) {
            if (e.key === 'Enter') {
                e.preventDefault();
                createMeeting();
            }
        });
    }

    if (agendaInput && typeof createAgenda === 'function') {
        agendaInput.addEventListener('keypress', function (e) {
            if (e.key === 'Enter') {
                e.preventDefault();
                createAgenda();
            }
        });
    }

    if (motionInput && typeof createMotion === 'function') {
        motionInput.addEventListener('keypress', function (e) {
            if (e.key === 'Enter') {
                e.preventDefault();
                createMotion();
            }
        });
    }

    // Changement de séance sélectionnée
    if (meetingSelect && typeof onMeetingChange === 'function') {
        meetingSelect.addEventListener('change', onMeetingChange);
    }

    // Changement de point d’ODJ sélectionné
    if (agendaSelect && typeof onAgendaChange === 'function') {
        agendaSelect.addEventListener('change', onAgendaChange);
    }

    // Mise à jour du comptage manuel
    if (typeof calculateVotes === 'function') {
        if (totalInput) {
            totalInput.addEventListener('input', calculateVotes);
        }
        if (forInput) {
            forInput.addEventListener('input', calculateVotes);
        }
        if (againstInput) {
            againstInput.addEventListener('input', calculateVotes);
        }
        if (abstainInput) {
            abstainInput.addEventListener('input', calculateVotes);
        }
    }

    // Premier chargement des séances
    if (typeof loadMeetings === 'function') {
        loadMeetings();
    } else {
        console.warn('loadMeetings() non définie — vérifie que meetings.js est bien chargé avant init.js');
    }
});
