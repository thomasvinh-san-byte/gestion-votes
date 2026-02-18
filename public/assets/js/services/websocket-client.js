/**
 * AG-Vote WebSocket Client
 *
 * Provides real-time updates with automatic reconnection and polling fallback.
 *
 * Usage:
 *   const ws = new AgVoteWebSocket({
 *     url: 'ws://localhost:8080',
 *     tenantId: 'tenant-uuid',
 *     token: 'api-token'
 *   });
 *
 *   ws.subscribe('meeting-uuid');
 *   ws.on('motion.opened', (data) => { ... });
 *   ws.on('vote.cast', (data) => { ... });
 */

class AgVoteWebSocket {
  constructor(options = {}) {
    this.url = options.url || this._detectWebSocketUrl();
    this.tenantId = options.tenantId;
    this.token = options.token;
    this.userId = options.userId;

    this.socket = null;
    this.connected = false;
    this.authenticated = false;
    this.reconnectAttempts = 0;
    this.maxReconnectAttempts = options.maxReconnectAttempts || 10;
    this.reconnectDelay = options.reconnectDelay || 1000;
    this.maxReconnectDelay = options.maxReconnectDelay || 30000;

    this.subscriptions = new Set();
    this.listeners = new Map();
    this.pendingMessages = [];

    // Polling fallback
    this.pollingEnabled = false;
    this.pollingInterval = options.pollingInterval || 3000;
    this.pollingTimer = null;
    this.lastPollTimestamp = null;

    // Event deduplication
    this._seenEventIds = new Set();
    this._maxSeenEvents = 500;

    // Heartbeat
    this.heartbeatInterval = options.heartbeatInterval || 30000;
    this.heartbeatTimer = null;
    this.lastPong = null;

    // Auto-connect
    if (options.autoConnect !== false) {
      this.connect();
    }
  }

  /**
   * Detect WebSocket URL from current location.
   */
  _detectWebSocketUrl() {
    const protocol = window.location.protocol === 'https:' ? 'wss:' : 'ws:';
    const host = window.location.hostname;
    const port = 8080; // Default WS port
    return `${protocol}//${host}:${port}`;
  }

  /**
   * Connect to WebSocket server.
   */
  connect() {
    if (this.socket && this.socket.readyState === WebSocket.OPEN) {
      return Promise.resolve();
    }

    return new Promise((resolve, reject) => {
      try {
        if (window.AG_DEBUG) console.log('[WS] Connecting to', this.url);
        this.socket = new WebSocket(this.url);

        this.socket.onopen = () => {
          if (window.AG_DEBUG) console.log('[WS] Connected');
          this.connected = true;
          this.reconnectAttempts = 0;

          // Stop polling if it was active
          this._stopPolling();

          // Authenticate
          this._authenticate();

          // Start heartbeat
          this._startHeartbeat();

          this._emit('connected', { connectionId: this.socket });
          resolve();
        };

        this.socket.onmessage = (event) => {
          this._handleMessage(event.data);
        };

        this.socket.onclose = (event) => {
          if (window.AG_DEBUG) console.log('[WS] Disconnected', event.code, event.reason);
          this.connected = false;
          this.authenticated = false;
          this._stopHeartbeat();

          this._emit('disconnected', { code: event.code, reason: event.reason });

          // Attempt reconnection
          if (!event.wasClean) {
            this._scheduleReconnect();
          }
        };

        this.socket.onerror = (error) => {
          console.error('[WS] Error:', error);
          this._emit('error', { error });

          // Start polling as fallback
          this._startPolling();
        };
      } catch (error) {
        console.error('[WS] Connection error:', error);
        this._startPolling();
        reject(error);
      }
    });
  }

  /**
   * Disconnect from server.
   */
  disconnect() {
    this._stopHeartbeat();
    this._stopPolling();

    if (this.socket) {
      this.socket.close(1000, 'Client disconnect');
      this.socket = null;
    }

    this.connected = false;
    this.authenticated = false;
  }

  /**
   * Send authentication message.
   */
  _authenticate() {
    if (!this.tenantId) {
      console.warn('[WS] No tenant_id provided, skipping authentication');
      return;
    }

    this._send({
      action: 'authenticate',
      token: this.token,
      tenant_id: this.tenantId,
      user_id: this.userId,
    });
  }

  /**
   * Subscribe to a meeting's events.
   */
  subscribe(meetingId) {
    this.subscriptions.add(meetingId);

    if (this.connected && this.authenticated) {
      this._send({
        action: 'subscribe',
        meeting_id: meetingId,
      });
    }
  }

  /**
   * Unsubscribe from a meeting's events.
   */
  unsubscribe(meetingId) {
    this.subscriptions.delete(meetingId);

    if (this.connected) {
      this._send({
        action: 'unsubscribe',
        meeting_id: meetingId,
      });
    }
  }

  /**
   * Register event listener.
   */
  on(eventType, callback) {
    if (!this.listeners.has(eventType)) {
      this.listeners.set(eventType, new Set());
    }
    this.listeners.get(eventType).add(callback);

    return () => this.off(eventType, callback);
  }

  /**
   * Remove event listener.
   */
  off(eventType, callback) {
    if (this.listeners.has(eventType)) {
      this.listeners.get(eventType).delete(callback);
    }
  }

  /**
   * Send message to server.
   */
  _send(data) {
    if (this.socket && this.socket.readyState === WebSocket.OPEN) {
      this.socket.send(JSON.stringify(data));
    } else {
      this.pendingMessages.push(data);
    }
  }

  /**
   * Handle incoming message.
   */
  _handleMessage(rawData) {
    let data;
    try {
      data = JSON.parse(rawData);
    } catch (e) {
      console.error('[WS] Invalid JSON:', rawData);
      return;
    }

    const type = data.type;

    switch (type) {
      case 'connected':
        if (window.AG_DEBUG) console.log('[WS] Server acknowledged connection');
        break;

      case 'authenticated':
        if (window.AG_DEBUG) console.log('[WS] Authenticated for tenant', data.tenant_id);
        this.authenticated = true;

        // Re-subscribe to meetings
        this.subscriptions.forEach((meetingId) => {
          this._send({ action: 'subscribe', meeting_id: meetingId });
        });

        // Send pending messages
        while (this.pendingMessages.length > 0) {
          this._send(this.pendingMessages.shift());
        }
        break;

      case 'subscribed':
        if (window.AG_DEBUG) console.log('[WS] Subscribed to meeting', data.meeting_id);
        break;

      case 'pong':
        this.lastPong = Date.now();
        break;

      case 'error':
        console.error('[WS] Server error:', data.message);
        this._emit('error', data);
        break;

      default:
        // Deduplicate events by ID if present
        if (data.event_id) {
          if (this._seenEventIds.has(data.event_id)) return;
          this._seenEventIds.add(data.event_id);
          // Trim oldest entries when set grows too large
          if (this._seenEventIds.size > this._maxSeenEvents) {
            const arr = [...this._seenEventIds];
            this._seenEventIds = new Set(arr.slice(-Math.floor(this._maxSeenEvents / 2)));
          }
        }
        // Emit to listeners
        this._emit(type, data.data || data);
    }
  }

  /**
   * Emit event to listeners.
   */
  _emit(eventType, data) {
    // Wildcard listeners
    if (this.listeners.has('*')) {
      this.listeners.get('*').forEach((cb) => cb(eventType, data));
    }

    // Specific listeners
    if (this.listeners.has(eventType)) {
      this.listeners.get(eventType).forEach((cb) => cb(data));
    }
  }

  /**
   * Schedule reconnection attempt.
   */
  _scheduleReconnect() {
    if (this.reconnectAttempts >= this.maxReconnectAttempts) {
      if (window.AG_DEBUG) console.log('[WS] Max reconnect attempts reached, falling back to polling');
      this._startPolling();
      return;
    }

    const delay = Math.min(
      this.reconnectDelay * Math.pow(2, this.reconnectAttempts),
      this.maxReconnectDelay
    );

    if (window.AG_DEBUG) console.log(`[WS] Reconnecting in ${delay}ms (attempt ${this.reconnectAttempts + 1})`);

    setTimeout(() => {
      this.reconnectAttempts++;
      this.connect();
    }, delay);
  }

  /**
   * Start heartbeat timer.
   */
  _startHeartbeat() {
    this._stopHeartbeat();
    this.lastPong = Date.now();

    this.heartbeatTimer = setInterval(() => {
      if (this.connected) {
        // Check if last pong is too old
        if (Date.now() - this.lastPong > this.heartbeatInterval * 2) {
          console.warn('[WS] Heartbeat timeout, reconnecting...');
          this.socket.close();
          return;
        }

        this._send({ action: 'ping' });
      }
    }, this.heartbeatInterval);
  }

  /**
   * Stop heartbeat timer.
   */
  _stopHeartbeat() {
    if (this.heartbeatTimer) {
      clearInterval(this.heartbeatTimer);
      this.heartbeatTimer = null;
    }
  }

  /**
   * Start polling fallback.
   */
  _startPolling() {
    if (this.pollingEnabled) return;

    if (window.AG_DEBUG) console.log('[WS] Starting polling fallback');
    this.pollingEnabled = true;
    this.lastPollTimestamp = Date.now();

    this._poll();
    this.pollingTimer = setInterval(() => this._poll(), this.pollingInterval);

    this._emit('polling_started', {});
  }

  /**
   * Stop polling.
   */
  _stopPolling() {
    if (!this.pollingEnabled) return;

    if (window.AG_DEBUG) console.log('[WS] Stopping polling');
    this.pollingEnabled = false;

    if (this.pollingTimer) {
      clearInterval(this.pollingTimer);
      this.pollingTimer = null;
    }

    this._emit('polling_stopped', {});
  }

  /**
   * Poll for updates.
   */
  async _poll() {
    if (!this.pollingEnabled) return;

    try {
      // Poll each subscribed meeting
      for (const meetingId of this.subscriptions) {
        const response = await fetch(`/api/v1/meeting_status?id=${meetingId}&since=${this.lastPollTimestamp}`);
        if (response.ok) {
          const data = await response.json();
          if (data.events) {
            data.events.forEach((event) => {
              // Deduplicate polled events
              if (event.event_id) {
                if (this._seenEventIds.has(event.event_id)) return;
                this._seenEventIds.add(event.event_id);
              }
              this._emit(event.type, event.data);
            });
          }
        }
      }

      this.lastPollTimestamp = Date.now();
    } catch (error) {
      console.error('[WS] Polling error:', error);
    }
  }

  /**
   * Get connection status.
   */
  get status() {
    if (this.connected && this.authenticated) {
      return 'connected';
    }
    if (this.connected) {
      return 'connecting';
    }
    if (this.pollingEnabled) {
      return 'polling';
    }
    return 'disconnected';
  }

  /**
   * Check if using real-time connection.
   */
  get isRealTime() {
    return this.connected && this.authenticated && !this.pollingEnabled;
  }
}

// Export for module systems
if (typeof module !== 'undefined' && module.exports) {
  module.exports = AgVoteWebSocket;
}

// Global for browser
if (typeof window !== 'undefined') {
  window.AgVoteWebSocket = AgVoteWebSocket;
}
