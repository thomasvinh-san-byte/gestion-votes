/**
 * GESTION DE L’ORDRE DU JOUR & DES RÉSOLUTIONS
 * Chargement et rendu des points et des résolutions.
 */

/**
 * Charge ODJ + résolutions de la séance courante.
 */
async function loadAgendasAndMotions() {
    if (!state.currentMeetingId) {
        const box = document.getElementById("agendas_motions_list");
        if (box) box.textContent = "Aucune séance sélectionnée.";
        updateTallyDisplay();
        return;
    }

    setNotif('', '');
    setLoading(true);

    try {
        const [a, m] = await Promise.all([
            api("/api/v1/agendas.php?meeting_id=" + encodeURIComponent(state.currentMeetingId)),
            api("/api/v1/motions_for_meeting.php?meeting_id=" + encodeURIComponent(state.currentMeetingId))
        ]);

        // --------- ODJ ---------
        if (!a.body || !a.body.ok) {
            setNotif('error', "Erreur chargement ODJ : " + (a.body?.error || a.status));
            log("Erreur load agendas: " + JSON.stringify(a.body));
            state.agendas = [];
            state.currentAgendaId = null;
        } else {
            const rawAgendas = (a.body.data && a.body.data.agendas) || a.body.agendas || [];

            // On remappe les champs de la base vers le format attendu par le front
            state.agendas = rawAgendas.map(ag => {
                const agendaId = ag.agenda_id || ag.id;
                const idxRaw   = (ag.agenda_idx !== undefined ? ag.agenda_idx : ag.idx);
                const title    = ag.agenda_title || ag.title;

                let idx = 0;
                if (typeof idxRaw === "number") {
                    idx = idxRaw;
                } else if (idxRaw != null) {
                    const parsed = parseInt(idxRaw, 10);
                    idx = isNaN(parsed) ? 0 : parsed;
                }

                return {
                    agenda_id: agendaId,
                    agenda_idx: idx,
                    agenda_title: title || "(sans titre)",
                };
            });

            // Gestion de l'agenda courant
            if (!state.agendas.length) {
                state.currentAgendaId = null;
            } else if (!state.currentAgendaId || !state.agendas.some(a => a.agenda_id === state.currentAgendaId)) {
                // Si aucun agenda courant ou l'ancien n'existe plus, on prend le premier
                state.currentAgendaId = state.agendas[0].agenda_id;
            }
        }

        // --------- Résolutions ---------
        if (!m.body || !m.body.ok) {
            setNotif('error', "Erreur chargement résolutions : " + (m.body?.error || m.status));
            log("Erreur load motions: " + JSON.stringify(m.body));
            state.motions = [];
            state.currentMotionId = null;
            state.currentMotion = null;
        } else {
            state.motions = m.body.data.motions || [];
            state.currentMotionId = m.body.data.current_motion_id || null;

            if (state.currentMotionId) {
                state.currentMotion = state.motions.find(mm => mm.motion_id === state.currentMotionId) || null;
            } else {
                state.currentMotion = null;
            }
        }

        fillAgendaSelect();
        renderAgendasAndMotions();
        await loadCurrentMotionTally();
        updateTallyDisplay();
    } catch (error) {
        setNotif('error', "Erreur chargement ODJ / résolutions : " + error.message);
        log("Erreur load agendas & motions: " + (error.stack || error));
        state.agendas = [];
        state.motions = [];
        state.currentAgendaId = null;
        state.currentMotionId = null;
        state.currentMotion = null;
        renderAgendasAndMotions();
        updateTallyDisplay();
    } finally {
        setLoading(false);
    }
}

/**
 * Remplit le <select> des points d’ODJ.
 */
function fillAgendaSelect() {
    const sel = document.getElementById("agenda_select");
    if (!sel) return;
    const previous = sel.value;
    sel.innerHTML = '<option value="">– Sélectionner un point –</option>';

    state.agendas.forEach(a => {
        const opt = document.createElement("option");
        opt.value = a.agenda_id;
        opt.textContent = `(${a.agenda_idx}) ${a.agenda_title}`;
        if (a.agenda_id === previous || a.agenda_id === state.currentAgendaId) opt.selected = true;
        sel.appendChild(opt);
    });
}

/**
 * Handler de changement de point d’ODJ.
 */
function onAgendaChange() {
    const sel = document.getElementById("agenda_select");
    if (!sel) return;
    const id = sel.value;
    state.currentAgendaId = id || null;
    renderAgendasAndMotions();
}

/**
 * Retourne l'état d'une résolution.
 */
function motionState(m) {
    if (m.closed_at) return 'closed';
    if (m.opened_at) return 'open';
    return 'draft';
}

/**
 * Rendu de la liste ODJ + résolutions.
 */
function renderAgendasAndMotions() {
    const box = document.getElementById("agendas_motions_list");
    if (!box) return;
    box.innerHTML = "";

    if (!state.currentMeetingId) {
        box.textContent = "Aucune séance sélectionnée.";
        return;
    }

    if (!state.meetings.length) {
        box.textContent = "Aucune séance disponible.";
        return;
    }

    if (!state.agendas.length) {
        box.textContent = "Aucun point d’ordre du jour pour cette séance.";
        return;
    }

    state.agendas
        .slice()
        .sort((a, b) => (a.agenda_idx || 0) - (b.agenda_idx || 0))
        .forEach(a => {
            const wrapper = document.createElement("div");
            wrapper.className = "item";
            wrapper.style.marginBottom = "8px";

            const header = document.createElement("div");
            header.className = "agenda-title";
            header.textContent = `(${a.agenda_idx}) ${a.agenda_title}`;
            wrapper.appendChild(header);

            const motionsBox = document.createElement("div");
            motionsBox.style.marginLeft = "10px";

            const motions = state.motions.filter(m => m.agenda_id === a.agenda_id);
            if (!motions.length) {
                const li = document.createElement("div");
                li.className = "muted";
                li.textContent = "↳ (aucune résolution)";
                motionsBox.appendChild(li);
            } else {
                motions.forEach(motion => {
                    motionsBox.appendChild(createMotionElement(motion));
                });
            }

            wrapper.appendChild(motionsBox);
            box.appendChild(wrapper);
        });
}

/**
 * Crée un bloc de résolution avec son état et les boutons d’action.
 */
function createMotionElement(motion) {
    const line = document.createElement("div");
    line.className = "motion-line";

    const left = document.createElement("div");

    const title = document.createElement("div");
    // On essaie plusieurs noms de champs possibles
    const titleText = motion.title || motion.motion_title || "(sans titre)";
    title.textContent = titleText;
    left.appendChild(title);

    const status = document.createElement("div");
    status.className = "muted";
    const s = motionState(motion);
    if (s === 'open') {
        status.textContent = "Résolution ouverte au vote.";
    } else if (s === 'closed') {
        status.textContent = "Résolution clôturée.";
    } else {
        status.textContent = "Résolution en brouillon.";
    }
    left.appendChild(status);

    const actions = document.createElement("div");
    actions.className = "motion-actions";

    if (s === 'open') {
        const btnClose = document.createElement("button");
        btnClose.textContent = "Clore";
        btnClose.className = "small secondary";
        btnClose.onclick = (ev) => { ev.stopPropagation(); closeMotion(motion.motion_id); };
        actions.appendChild(btnClose);
    } else {
        const btnOpen = document.createElement("button");
        btnOpen.textContent = "Ouvrir";
        btnOpen.className = "small";
        btnOpen.onclick = (ev) => { ev.stopPropagation(); openMotion(motion.motion_id); };
        actions.appendChild(btnOpen);
    }

    line.appendChild(left);
    line.appendChild(actions);
    return line;
}
