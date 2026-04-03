/**
 * event-stream.js - SSE (Server-Sent Events) client for real-time updates.
 *
 * Replaces HTTP polling with a persistent SSE connection. Falls back to polling
 * automatically if SSE is unavailable (no EventSource, auth issues, etc.).
 *
 * Usage:
 *   const stream = EventStream.connect(meetingId, {
 *     onEvent(type, data) { ... },           // All events
 *     onConnect() { ... },                    // Connection established
 *     onDisconnect() { ... },                 // Connection lost
 *   });
 *   stream.close();                           // Cleanup
 *
 * The stream auto-reconnects on disconnect (built into EventSource).
 *
 * @module event-stream
 */
(function () {
  'use strict';

  /** @type {EventSource|null} */
  let source = null;
  let currentMeetingId = null;
  let handlers = {};
  let reconnectAttempts = 0;
  const MAX_RECONNECT_ATTEMPTS = 10;

  /**
   * Connect to the SSE endpoint for a meeting.
   *
   * @param {string} meetingId
   * @param {Object} opts
   * @param {Function} [opts.onEvent] - Called for every event: (type, data)
   * @param {Function} [opts.onConnect] - Called when connection opens
   * @param {Function} [opts.onDisconnect] - Called when connection drops
   * @param {Function} [opts.onFallback] - Called when max reconnects exceeded (polling-only mode)
   * @returns {{ close: Function, isConnected: Function }}
   */
  function connect(meetingId, opts) {
    if (!meetingId) return { close: noop, isConnected: () => false };
    if (!window.EventSource) {
      console.warn('[EventStream] EventSource not supported, using polling fallback');
      return { close: noop, isConnected: () => false };
    }

    // Close any existing connection
    close();

    currentMeetingId = meetingId;
    handlers = opts || {};
    reconnectAttempts = 0;

    openConnection();

    return {
      close: close,
      isConnected: function () {
        return source !== null && source.readyState !== EventSource.CLOSED;
      },
    };
  }

  function showSseWarning() {
    if (document.getElementById('sseWarningBanner')) return;
    var banner = document.createElement('div');
    banner.id = 'sseWarningBanner';
    banner.className = 'sse-warning-banner';
    banner.setAttribute('role', 'status');
    banner.setAttribute('aria-live', 'polite');
    banner.textContent = 'Connexion temps reel interrompue \u2014 actualisation automatique toutes les 30 secondes';
    var main = document.querySelector('.app-main') || document.querySelector('main') || document.body;
    main.prepend(banner);
  }

  function hideSseWarning() {
    var el = document.getElementById('sseWarningBanner');
    if (el) el.remove();
  }

  function openConnection() {
    if (!currentMeetingId) return;

    // Clean up any stale banner when reconnecting
    hideSseWarning();

    var url = '/api/v1/events.php?meeting_id=' + encodeURIComponent(currentMeetingId);
    source = new EventSource(url);

    source.addEventListener('connected', function (_e) {
      reconnectAttempts = 0;
      hideSseWarning();
      if (handlers.onConnect) handlers.onConnect();
    });

    source.addEventListener('reconnect', function () {
      // Server-initiated reconnect (30s timeout) — EventSource handles this automatically
    });

    // Listen for specific event types from EventBroadcaster
    var eventTypes = [
      'motion.opened',
      'motion.closed',
      'motion.updated',
      'vote.cast',
      'vote.updated',
      'attendance.updated',
      'quorum.updated',
      'meeting.status_changed',
      'speech.queue_updated',
      'document.added',
      'document.removed',
    ];

    eventTypes.forEach(function (type) {
      source.addEventListener(type, function (e) {
        var data = {};
        try {
          data = JSON.parse(e.data);
        } catch (_) {
          data = {};
        }
        if (handlers.onEvent) handlers.onEvent(type, data);
      });
    });

    // Catch-all for generic messages
    source.onmessage = function (e) {
      var data = {};
      try {
        data = JSON.parse(e.data);
      } catch (_) {
        data = {};
      }
      if (handlers.onEvent) handlers.onEvent('message', data);
    };

    source.onerror = function () {
      reconnectAttempts++;
      showSseWarning();
      if (handlers.onDisconnect) handlers.onDisconnect();

      if (reconnectAttempts >= MAX_RECONNECT_ATTEMPTS) {
        console.warn('[EventStream] Max reconnect attempts reached, closing.');
        close();
        if (handlers.onFallback) handlers.onFallback();
      }
      // Otherwise EventSource auto-reconnects
    };
  }

  /**
   * Close the SSE connection.
   */
  function close() {
    if (source) {
      source.close();
      source = null;
    }
    currentMeetingId = null;
    handlers = {};
  }

  /**
   * Check if SSE is available and connected.
   */
  function isActive() {
    return source !== null && source.readyState !== EventSource.CLOSED;
  }

  function noop() {}

  // Export
  window.EventStream = {
    connect: connect,
    close: close,
    isActive: isActive,
    showSseWarning: showSseWarning,
    hideSseWarning: hideSseWarning,
  };
})();
