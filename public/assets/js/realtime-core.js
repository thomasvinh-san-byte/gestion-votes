// /assets/js/realtime-core.js
// Core temps réel partagé entre les différentes consoles (trust, operator, etc.)
(function () {
  const state = {
    meeting: null,
    motions: [],
    currentMotionId: null,
    lastRefresh: null,
    loading: false,
    error: null,
  };

  const listeners = new Set();
  let started = false;

  async function api(url, data) {
    const opts = {};
    if (data) {
      opts.method = "POST";
      opts.headers = { "Content-Type": "application/json" };
      opts.body = JSON.stringify(data);
    }
    const res = await fetch(url, opts);
    const body = await res.json().catch(() => null);
    return { status: res.status, body };
  }

  async function refresh() {
    if (state.loading) return;
    state.loading = true;
    state.error = null;

    try {
      // 1) Statut de séance
      const statusRes = await api("/api/v1/meeting_status.php");

      if (!statusRes.body || !statusRes.body.ok) {
        if (statusRes.body && statusRes.body.error === "no_live_meeting") {
          state.meeting = null;
          state.motions = [];
          state.currentMotionId = null;
          state.lastRefresh = new Date();
          state.error = "no_live_meeting";
          notify();
          return;
        }
        state.error = (statusRes.body && statusRes.body.error) || "status_error";
        notify();
        return;
      }

      state.meeting = statusRes.body.data || null;

      // 2) Motions de la séance (si on a bien une meeting_id)
      state.motions = [];
      state.currentMotionId = null;

      if (state.meeting && state.meeting.meeting_id) {
        const motionsRes = await api(
          "/api/v1/motions_for_meeting.php?meeting_id=" +
            encodeURIComponent(state.meeting.meeting_id)
        );

        if (!motionsRes.body || !motionsRes.body.ok) {
          state.motions = [];
          state.currentMotionId = null;
          state.error = (motionsRes.body && motionsRes.body.error) || "motions_error";
        } else {
          state.motions = motionsRes.body.data.motions || [];
          state.currentMotionId =
            motionsRes.body.data.current_motion_id || null;
        }
      }

      state.lastRefresh = new Date();
      notify();
    } catch (e) {
      state.error = e.message || "network_error";
      notify();
    } finally {
      state.loading = false;
    }
  }

  function notify() {
    const snapshot = {
      ...state,
      motions: [...state.motions],
      lastRefresh: state.lastRefresh ? new Date(state.lastRefresh) : null,
    };
    listeners.forEach((cb) => {
      try {
        cb(snapshot);
      } catch (e) {
        console.error("Realtime listener error:", e);
      }
    });
  }

  function subscribe(cb) {
    listeners.add(cb);
    cb({
      ...state,
      motions: [...state.motions],
      lastRefresh: state.lastRefresh ? new Date(state.lastRefresh) : null,
    });
    return () => listeners.delete(cb);
  }

  function start(intervalMs = 3000) {
    if (started) return;
    started = true;

    refresh();
    setInterval(refresh, intervalMs);

    document.addEventListener("visibilitychange", () => {
      if (!document.hidden) refresh();
    });
  }

  function getState() {
    return {
      ...state,
      motions: [...state.motions],
      lastRefresh: state.lastRefresh ? new Date(state.lastRefresh) : null,
    };
  }

  window.Realtime = {
    subscribe,
    start,
    refresh,
    getState,
  };
})();