/**
 * MEETING MANAGEMENT
 * Loading, display, selection of meetings.
 */

/**
 * A meeting is "active" if it is not archived.
 */
function isActiveMeeting(m) {
    return m.status !== 'archived';
}

/**
 * A meeting is in history if it is closed or archived.
 */
function isHistoryMeeting(m) {
    return m.status === 'closed' || m.status === 'archived';
}

/**
 * Load meetings from the API.
 */
async function loadMeetings() {
    setLoading(true);
    try {
        const { status, body } = await api("/api/v1/meetings.php");
        if (!body || !body.ok) {
            setNotif('error', "Error loading meetings: " + (body?.error || status));
            log("Error load meetings: " + JSON.stringify(body));
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
 * Update the meeting lists (active / history).
 */
function renderMeetingsLists() {
    const activeDiv  = document.getElementById("active_meetings_list");
    const historyDiv = document.getElementById("history_meetings_list");

    if (!activeDiv || !historyDiv) return;

    activeDiv.innerHTML  = "";
    historyDiv.innerHTML = "";

    const actives = state.meetings.filter(isActiveMeeting);
    const history = state.meetings.filter(isHistoryMeeting);

    // Active meetings
    if (!actives.length) {
        activeDiv.textContent = "No active meeting.";
    } else {
        actives.forEach(m => {
            const el = createMeetingElement(m, true);
            activeDiv.appendChild(el);
        });
    }

    // History
    if (!history.length) {
        historyDiv.textContent = "No meetings in history.";
    } else {
        history.forEach(m => {
            const el = createMeetingElement(m, false);
            historyDiv.appendChild(el);
        });
    }
}


/**
 * Create a DOM element for a meeting.
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
    html += `<br><span class="text-muted">${m.id}</span>`;

    if (!isActive) {
        if (m.validated_by) {
            const date = m.validated_at ? new Date(m.validated_at).toLocaleString() : 'unknown date';
            html += `<br><span class="text-muted">Validated by ${escapeHtml(m.validated_by)} on ${date}</span>`;
        } else {
            html += `<br><span class="text-muted">Not validated</span>`;
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
 * Populate the <select> with active meetings.
 */
function fillMeetingSelect() {
    const sel = document.getElementById("meeting_select");
    if (!sel) return;
    const previous = sel.value;
    sel.innerHTML = '<option value="">– Select a meeting –</option>';

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
 * Handler for meetings <select>.
 */
function onMeetingChange() {
    const sel = document.getElementById("meeting_select");
    if (!sel) return;
    const id = sel.value;
    if (id) selectMeeting(id);
}

/**
 * Select a meeting and load agenda + motions.
 */
async function selectMeeting(id) {
    state.currentMeetingId = id;
    state.currentAgendaId = null;
    state.currentMotion = null;
    renderMeetingsLists();
    await loadAgendasAndMotions();
}