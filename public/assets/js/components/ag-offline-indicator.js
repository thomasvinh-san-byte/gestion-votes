/**
 * AG Offline Indicator Web Component
 *
 * Shows the current online/offline status and pending sync count.
 *
 * Usage:
 *   <ag-offline-indicator></ag-offline-indicator>
 */

class AgOfflineIndicator extends HTMLElement {
  constructor() {
    super();
    this.attachShadow({ mode: 'open' });
    this.isOnline = navigator.onLine;
    this.pendingCount = 0;
  }

  connectedCallback() {
    this.render();
    this.setupEventListeners();
    this.checkPendingActions();
  }

  disconnectedCallback() {
    window.removeEventListener('online', this._onlineHandler);
    window.removeEventListener('offline', this._offlineHandler);
  }

  setupEventListeners() {
    this._onlineHandler = () => {
      this.isOnline = true;
      this.render();
      this.syncIfNeeded();
    };

    this._offlineHandler = () => {
      this.isOnline = false;
      this.render();
    };

    window.addEventListener('online', this._onlineHandler);
    window.addEventListener('offline', this._offlineHandler);

    // Listen for offline sync events
    if (window.AgVoteOffline?.sync) {
      window.AgVoteOffline.sync.on('actionQueued', () => {
        this.pendingCount++;
        this.render();
      });

      window.AgVoteOffline.sync.on('syncComplete', ({ successCount }) => {
        this.pendingCount = Math.max(0, this.pendingCount - successCount);
        this.render();
      });
    }
  }

  async checkPendingActions() {
    if (window.AgVoteOffline?.storage) {
      try {
        const pending = await window.AgVoteOffline.storage.getPendingActions();
        this.pendingCount = pending.length;
        this.render();
      } catch (e) {
        console.warn('[ag-offline-indicator] Failed to check pending actions:', e);
      }
    }
  }

  async syncIfNeeded() {
    if (this.pendingCount > 0 && window.AgVoteOffline?.sync) {
      window.AgVoteOffline.sync.syncPendingActions();
    }
  }

  render() {
    const statusClass = this.isOnline ? 'online' : 'offline';
    const statusText = this.isOnline ? 'En ligne' : 'Hors ligne';
    const statusIcon = this.isOnline ? '🌐' : '📡';

    const pendingHtml = this.pendingCount > 0
      ? `<span class="pending-badge" title="${this.pendingCount} action(s) en attente de synchronisation">${this.pendingCount}</span>`
      : '';

    this.shadowRoot.innerHTML = `
      <style>
        :host {
          display: inline-flex;
          align-items: center;
          gap: 0.5rem;
        }

        .indicator {
          display: inline-flex;
          align-items: center;
          gap: 0.375rem;
          padding: 0.25rem 0.75rem;
          border-radius: 1rem;
          font-size: 0.8125rem;
          font-weight: 500;
          transition: all 0.3s ease;
        }

        .indicator.online {
          background: #e8f5e9;
          color: #2e7d32;
          border: 1px solid #a5d6a7;
        }

        .indicator.offline {
          background: #fff3e0;
          color: #e65100;
          border: 1px solid #ffcc80;
          animation: pulse 2s infinite;
        }

        @keyframes pulse {
          0%, 100% { opacity: 1; }
          50% { opacity: 0.7; }
        }

        .status-dot {
          width: 8px;
          height: 8px;
          border-radius: 50%;
        }

        .online .status-dot {
          background: #4caf50;
        }

        .offline .status-dot {
          background: #ff9800;
        }

        .pending-badge {
          display: inline-flex;
          align-items: center;
          justify-content: center;
          min-width: 1.25rem;
          height: 1.25rem;
          padding: 0 0.375rem;
          border-radius: 0.625rem;
          background: #1976d2;
          color: white;
          font-size: 0.6875rem;
          font-weight: 600;
          margin-left: 0.25rem;
        }

        .icon {
          font-size: 1rem;
        }

        /* Sync animation */
        .syncing .status-dot {
          animation: syncSpin 1s linear infinite;
        }

        @keyframes syncSpin {
          from { transform: rotate(0deg); }
          to { transform: rotate(360deg); }
        }

        /* Click to sync */
        .indicator.offline {
          cursor: pointer;
        }

        .indicator.offline:hover {
          background: #ffe0b2;
        }
      </style>

      <div class="indicator ${statusClass}" role="status" aria-live="polite">
        <span class="status-dot" aria-hidden="true"></span>
        <span class="icon" aria-hidden="true">${statusIcon}</span>
        <span class="status-text">${statusText}</span>
        ${pendingHtml}
      </div>
    `;

    // Add click handler to trigger sync when offline and coming back online
    const indicator = this.shadowRoot.querySelector('.indicator');
    if (!this.isOnline) {
      indicator.title = 'Cliquez pour réessayer la connexion';
      indicator.onclick = () => {
        // Force a check
        if (navigator.onLine) {
          this.isOnline = true;
          this.render();
          this.syncIfNeeded();
        }
      };
    }
  }
}

// Register the component
customElements.define('ag-offline-indicator', AgOfflineIndicator);

export default AgOfflineIndicator;
