// /assets/js/realtime-operator.js
/**
 * Module temps réel spécifique à la console opérateur
 * - S'abonne au core partagé Realtime
 * - Met à jour l'interface (tally_block + meeting_select)
 */
(function () {
  const operatorState = {
    unsubscribe: null,
    lastMeetingId: null,
    lastCurrentMotionId: null,
  };

  function motionState(motion) {
    if (!motion) return "draft";
    if (motion.closed_at) return "closed";
    if (motion.opened_at) return "open";
    return "draft";
  }

  function decisionLabel(decision) {
    switch (decision) {
      case "adopted": return "Adoptée";
      case "rejected": return "Rejetée";
      case "pending": return "En attente";
      default: return decision || "—";
    }
  }

  function escapeHtml(str) {
    return String(str)
      .replace(/&/g, "&amp;")
      .replace(/</g, "&lt;")
      .replace(/>/g, "&gt;")
      .replace(/"/g, "&quot;")
      .replace(/'/g, "&#39;");
  }

  function syncMeetingSelect(rt) {
    const meetingSelect = document.getElementById("meeting_select");
    if (!meetingSelect) return;

    // si l'utilisateur a déjà choisi une séance, on ne touche pas
    if (meetingSelect.value) return;
    if (!rt.meeting || !rt.meeting.meeting_id) return;

    const meetingId = rt.meeting.meeting_id;
    const opt = Array.from(meetingSelect.options).find(
      (o) => o.value === meetingId
    );
    if (!opt) return;

    meetingSelect.value = meetingId;
    operatorState.lastMeetingId = meetingId;

    const evt = new Event("change", { bubbles: true });
    meetingSelect.dispatchEvent(evt);
  }

  function updateTallyBlock(rt) {
    const tallyBlock = document.getElementById("tally_block");
    if (!tallyBlock) return;

    if (!rt.meeting) {
      tallyBlock.innerHTML = '<p class="muted">Aucune séance en cours.</p>';
      operatorState.lastCurrentMotionId = null;
      return;
    }

    const motions = rt.motions || [];
    const curId = rt.currentMotionId;
    const currentMotion = curId
      ? motions.find((m) => m.motion_id === curId) || null
      : null;

    if (!currentMotion) {
      tallyBlock.innerHTML =
        '<p class="muted">Aucune résolution ouverte actuellement.</p>';
      operatorState.lastCurrentMotionId = null;
      return;
    }

    operatorState.lastCurrentMotionId = curId;

    const st = motionState(currentMotion);
    let statusLabel = "";
    let timeInfo = "";

    if (st === "open") {
      statusLabel = "Vote en cours";
    } else if (st === "closed") {
      if (currentMotion.decision) {
        statusLabel = "Clôturée — " + decisionLabel(currentMotion.decision);
      } else {
        statusLabel = "Clôturée";
      }
    } else {
      statusLabel = "Brouillon";
    }

    if (currentMotion.opened_at) {
      const opened = new Date(currentMotion.opened_at);
      const diffMinutes = Math.floor(
        (Date.now() - opened.getTime()) / 60000
      );
      timeInfo = ` · ouverte il y a ${diffMinutes} min`;
    }
    if (currentMotion.closed_at) {
      const closed = new Date(currentMotion.closed_at);
      timeInfo = ` · clôturée à ${closed.toLocaleTimeString()}`;
    }

    const title = escapeHtml(currentMotion.motion_title || "(sans titre)");

    tallyBlock.innerHTML = `
      <div class="muted">
        Résolution courante (synchro écran Président) :<br />
        <strong>${title}</strong><br />
        <span>${statusLabel}${timeInfo}</span>
      </div>
    `;
  }

  function applyRealtimeToOperator(rt) {
    syncMeetingSelect(rt);
    updateTallyBlock(rt);
  }

  function cleanup() {
    if (operatorState.unsubscribe) {
      operatorState.unsubscribe();
      operatorState.unsubscribe = null;
    }
  }

  function init() {
    if (!window.Realtime) {
      console.warn("Realtime core non chargé sur /operator");
      return;
    }

    operatorState.unsubscribe = Realtime.subscribe(applyRealtimeToOperator);
    Realtime.start(3000);
  }

  if (document.readyState === "loading") {
    document.addEventListener("DOMContentLoaded", init);
  } else {
    init();
  }

  window.OperatorRealtime = {
    syncMeetingSelect,
    updateTallyBlock,
    cleanup,
    getState: () => ({ ...operatorState }),
  };
})();