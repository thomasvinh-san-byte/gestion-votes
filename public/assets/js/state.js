/**
 * ÉTAT GLOBAL DE L'APPLICATION
 * Toutes les données partagées entre les modules.
 */
let state = {
    // Séances
    meetings: [],

    // Points d'ordre du jour de la séance sélectionnée
    agendas: [],

    // Résolutions de la séance sélectionnée
    motions: [],

    // ID de la séance actuellement sélectionnée
    currentMeetingId: null,

    // ID du point d'ODJ actuellement sélectionné
    currentAgendaId: null,

    // ID de la résolution courante (ouverte)
    currentMotionId: null,

    // Données complètes de la résolution courante
    currentMotion: null,

    // Données de comptage manuel pour la résolution courante
    tally: {
        manual_total: 0,    // Nombre total de votants
        manual_against: 0,  // Nombre de votes contre
        manual_abstain: 0,  // Nombre d'abstentions
        manual_for: 0,      // Nombre de votes pour (calculé)
    },
};