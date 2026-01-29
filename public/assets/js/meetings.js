/**
 * GESTION DES SÉANCES
 * Chargement, affichage, sélection des séances.
 */

/**
 * Une séance est dite "active" si elle n'est pas archivée.
 */
function isActiveMeeting(m) {
    return m.status !== 'archived';
}

/**
 * Une séance est dans l'historique si elle est close ou archivée.
 */
function isHistoryMeeting(m) {
    return m.status === 'closed' || m.status === 'archived';
}

/**
 * Charge les séances depuis l’API.
 */
async function loadMeetings() {
    setLoading(true);
    try {
        const { status, body } = await api("/api/v1/meetings.php");
        if (!body || !body.ok) {
            setNotif('error', "Erreur chargement séances : " + (body?.error || status));
            log("Erreur load meetings: " + JSON.stringify(body));
            return;
        }
        state.meetings = body.data.meetings || [];
        renderMeetingsLists();
        fillMeetingSelect();
    } finally {
        setLoading(false);
    }
}

/**
 * Met à jour les listes des séances (actives / historiques).
 */
function renderMeetingsLists() {
    const activeDiv  = document.getElementById("active_meetings_list");
    const historyDiv = document.getElementById("history_meetings_list");

    if (!activeDiv || !historyDiv) return;

    activeDiv.innerHTML  = "";
    historyDiv.innerHTML = "";

    const actives = state.meetings.filter(isActiveMeeting);
    const history = state.meetings.filter(isHistoryMeeting);

    // Séances actives
    if (!actives.length) {
        activeDiv.textContent = "Pas de séance active.";
    } else {
        actives.forEach(m => {
            const el = createMeetingElement(m, true);
            activeDiv.appendChild(el);
        });
    }

    // Historique
    if (!history.length) {
        historyDiv.textContent = "Aucune séance dans l’historique.";
    } else {
        history.forEach(m => {
            const el = createMeetingElement(m, false);
            historyDiv.appendChild(el);
        });
    }
}


/**
 * Crée un élément DOM pour une séance.
 */
function createMeetingElement(m, isActive) {
    const el = document.createElement("div");
    el.className = "item" + (m.id === state.currentMeetingId ? " active" : "");
    el.onclick = () => selectMeeting(m.id);
    el.tabIndex = 0;
    el.addEventListener("keypress", (e) => {
        if (e.key === 'Enter' || e.key === ' ') {
            e.preventDefault();
            selectMeeting(m.id);
        }
    });

    let html = `<strong>${escapeHtml(m.title)}</strong>`;
    html += ` <span class="pill status-${m.status}">${m.status}</span>`;
    html += `<br><span class="muted">${m.id}</span>`;

    if (!isActive) {
        if (m.validated_by) {
            const date = m.validated_at ? new Date(m.validated_at).toLocaleString() : 'date inconnue';
            html += `<br><span class="muted">Validée par ${escapeHtml(m.validated_by)} le ${date}</span>`;
        } else {
            html += `<br><span class="muted">Non validée</span>`;
        }
    }

    el.innerHTML = html;

    if (isActive) {
        const actions = document.createElement("div");
        actions.style.marginTop = "4px";

        const btnRename = document.createElement("button");
        btnRename.textContent = "Renommer";
        btnRename.className = "small secondary";
        btnRename.onclick = (ev) => { ev.stopPropagation(); renameMeeting(m.id); };

        const btnArchive = document.createElement("button");
        btnArchive.textContent = "Archiver";
        btnArchive.className = "small";
        btnArchive.onclick = (ev) => { ev.stopPropagation(); archiveMeeting(m.id); };

        actions.appendChild(btnRename);
        actions.appendChild(btnArchive);
        el.appendChild(actions);
    }

    return el;
}

/**
 * Remplit le <select> des séances actives.
 */
function fillMeetingSelect() {
    const sel = document.getElementById("meeting_select");
    if (!sel) return;
    const previous = sel.value;
    sel.innerHTML = '<option value="">– Sélectionner une séance –</option>';

    state.meetings.forEach(m => {
        if (!isActiveMeeting(m)) return;
        const opt = document.createElement("option");
        opt.value = m.id;
        opt.textContent = m.title;
        if (m.id === previous || m.id === state.currentMeetingId) opt.selected = true;
        sel.appendChild(opt);
    });
}

/**
 * Handler <select> des séances.
 */
function onMeetingChange() {
    const sel = document.getElementById("meeting_select");
    if (!sel) return;
    const id = sel.value;
    if (id) selectMeeting(id);
}

/**
 * Sélectionne une séance et charge ODJ + résolutions.
 */
async function selectMeeting(id) {
    state.currentMeetingId = id;
    state.currentAgendaId = null;
    state.currentMotion = null;
    renderMeetingsLists();
    await loadAgendasAndMotions();
}