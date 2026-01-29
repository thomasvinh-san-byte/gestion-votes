// public/assets/js/actions.js
// ============================================================
//  ACTIONS CRUD & GESTION DES RÉSOLUTIONS
//  - Création / renommage / archivage des séances
//  - Création des points d'ODJ et résolutions
//  - Ouverture / clôture des résolutions
//  - Appels à l'API backend + feedback visuel
// ============================================================

// ---------- Séances : renommage / archivage ----------

async function renameMeeting(meetingId) {
    const m = state.meetings.find(x => x.id === meetingId);
    const currentTitle = m?.title || "";
    const newTitle = prompt("Nouveau titre de la séance :", currentTitle);

    if (!newTitle || newTitle.trim() === currentTitle) return;

    const trimmed = newTitle.trim();
    const len = trimmed.length;
    if (len > 50) {
        setNotif('error', "Le titre de la séance doit contenir jusqu'à 50 caractères."); 
        return;
    }

    const { status, body } = await api("/api/v1/meetings_update.php", {
        meeting_id: meetingId,
        title: trimmed
    });

    if (!body || !body.ok) {
        setNotif('error', "Erreur renommage séance : " + (body?.error || status));
        log("Erreur rename meeting: " + JSON.stringify(body));
        return;
    }

    setNotif('success', "Séance renommée.");
    log("Séance renommée: " + meetingId);
    await loadMeetings();
}

async function archiveMeeting(meetingId) {
    if (!confirm("Archiver cette séance ? Elle sera déplacée dans l'historique.")) {
        return;
    }

    const { status, body } = await api("/api/v1/meetings_update.php", {
        meeting_id: meetingId,
        status: "archived"
    });

    if (!body || !body.ok) {
        setNotif('error', "Erreur archivage séance : " + (body?.error || status));
        log("Erreur archive meeting: " + JSON.stringify(body));
        return;
    }

    setNotif('success', "Séance archivée.");
    log("Séance archivée: " + meetingId);

    if (state.currentMeetingId === meetingId) {
        state.currentMeetingId = null;
        state.agendas = [];
        state.motions = [];
        state.currentMotion = null;
        const box = document.getElementById("agendas_motions_list");
        if (box) box.textContent = "Aucune séance sélectionnée.";
        if (typeof updateTallyDisplay === 'function') {
            updateTallyDisplay();
        }
    }

    await loadMeetings();
}

// ---------- Création : séance / point / résolution ----------

async function createMeeting() {
    const input = document.getElementById("meeting_title");
    if (!input) return;
    const title = input.value.trim();

    if (!title) {
        setNotif('error', "Titre obligatoire pour la séance.");
        return;
    }
    const len = title.length;
    if (len > 50) { 
        setNotif('error', "Le titre de la séance doit contenir jusqu'à 50 caractères.");
        return;
    }

    setNotif('', '');
    setLoading(true);

    try {
        const { status, body } = await api("/api/v1/meetings.php", { title });
        if (!body || !body.ok) {
            setNotif('error', "Erreur création séance : " + (body?.error || status));
            log("Erreur création séance: " + JSON.stringify(body));
            return;
        }
        log("Séance créée: " + body.data.meeting_id);
        input.value = "";
        setNotif('success', "Séance créée avec succès !");
        await loadMeetings();
    } finally {
        setLoading(false);
    }
}

async function createAgenda() {
    if (!state.currentMeetingId) {
        setNotif('error', "Sélectionnez d'abord une séance.");
        return;
    }
    const input = document.getElementById("agenda_title");
    if (!input) return;
    const title = input.value.trim();

    if (!title) {
        setNotif('error', "Titre du point obligatoire.");
        return;
    }
    const len = title.length;
    // CORRECTION : ajout des parenthèses manquantes
    if (len > 40) {
        setNotif('error', "Le titre du point doit contenir jusqu'à 40 caractères.");
        return;
    }

    setNotif('', '');
    setLoading(true);

    try {
        const { status, body } = await api("/api/v1/agendas.php", {
            meeting_id: state.currentMeetingId,
            title
        });
        if (!body || !body.ok) {
            setNotif('error', "Erreur ajout point : " + (body?.error || status));
            log("Erreur création ODJ: " + JSON.stringify(body));
            return;
        }
        log("Point ajouté (idx " + body.data.idx + ")");
        input.value = "";
        setNotif('success', "Point ajouté à l'ordre du jour !");
        await loadAgendasAndMotions();
    } finally {
        setLoading(false);
    }
}

async function createMotion() {
    if (!state.currentMeetingId) {
        setNotif('error', "Sélectionnez d'abord une séance.");
        return;
    }
    const agendaSel = document.getElementById("agenda_select");
    if (!agendaSel) return;
    const agendaId = agendaSel.value;
    if (!agendaId) {
        setNotif('error', "Sélectionnez un point d'ordre du jour.");
        return;
    }

    const input = document.getElementById("motion_title");
    if (!input) return;
    const title = input.value.trim();

    if (!title) {
        setNotif('error', "Titre de la résolution obligatoire.");
        return;
    }
    const len = title.length;
    // CORRECTION : condition complète avec parenthèses
    if (len > 50) {
        setNotif('error', "Le titre de la résolution doit contenir jusqu'à 50 caractères.");
        return;
    }

    setNotif('', '');
    setLoading(true);

    try {
        const { status, body } = await api("/api/v1/motions.php", {
            agenda_id: agendaId,
            title
        });
        if (!body || !body.ok) {
            setNotif('error', "Erreur création résolution : " + (body?.error || status));
            log("Erreur création résolution: " + JSON.stringify(body));
            return;
        }
        log("Résolution créée: " + body.data.motion_id);
        input.value = "";
        setNotif('success', "Résolution créée !");
        await loadAgendasAndMotions();
    } finally {
        setLoading(false);
    }
}

// ---------- Ouverture / clôture de résolutions ----------

async function openMotion(motionId) {
    setLoading(true);
    try {
        const { status, body } = await api("/api/v1/motions_open.php", {
            motion_id: motionId
        });

        if (!body || !body.ok) {
            setNotif('error', "Erreur ouverture résolution : " + (body?.error || status));
            log("Erreur ouverture motion: " + JSON.stringify(body));
            return;
        }

        setNotif('success', "Résolution ouverte avec succès.");
        log("Résolution ouverte: " + motionId);
        await loadAgendasAndMotions();
    } finally {
        setLoading(false);
    }
}

async function closeMotion(motionId) {
    if (!confirm("Clôturer cette résolution ? Le vote sera terminé.")) {
        return;
    }

    setLoading(true);
    try {
        const { status, body } = await api("/api/v1/motions_close.php", {
            motion_id: motionId
        });

        if (!body || !body.ok) {
            setNotif('error', "Erreur clôture résolution : " + (body?.error || status));
            log("Erreur clôture motion: " + JSON.stringify(body));
            return;
        }

        setNotif('success', "Résolution clôturée.");
        log("Résolution clôturée: " + motionId);
        await loadAgendasAndMotions();
    } finally {
        setLoading(false);
    }
}