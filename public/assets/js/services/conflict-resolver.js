/**
 * AG-Vote Conflict Resolver
 *
 * Handles conflicts when syncing offline actions with server state.
 * Implements various resolution strategies.
 */

class ConflictResolver {
  constructor() {
    this.strategies = {
      // Server wins - discard local changes
      SERVER_WINS: 'server_wins',
      // Client wins - force local changes
      CLIENT_WINS: 'client_wins',
      // Merge - combine changes intelligently
      MERGE: 'merge',
      // Manual - prompt user to resolve
      MANUAL: 'manual',
      // Last write wins - based on timestamp
      LAST_WRITE_WINS: 'last_write_wins',
    };

    this.defaultStrategy = this.strategies.LAST_WRITE_WINS;
    this.conflictHandlers = new Map();
    this.pendingConflicts = [];
  }

  /**
   * Register a conflict handler for a specific action type
   */
  registerHandler(actionType, handler) {
    this.conflictHandlers.set(actionType, handler);
  }

  /**
   * Detect if there's a conflict between local and server state
   */
  async detectConflict(localAction, serverState) {
    // No conflict if server state doesn't exist
    if (!serverState) {
      return { hasConflict: false };
    }

    // Extract timestamps
    const localTimestamp = localAction.timestamp || 0;
    const serverTimestamp = new Date(serverState.updated_at || serverState.created_at || 0).getTime();

    // Conflict if server was updated after local action was queued
    if (serverTimestamp > localTimestamp) {
      return {
        hasConflict: true,
        localTimestamp,
        serverTimestamp,
        localData: localAction.body,
        serverData: serverState,
      };
    }

    return { hasConflict: false };
  }

  /**
   * Resolve a conflict using the specified strategy
   */
  async resolveConflict(conflict, strategy = this.defaultStrategy) {
    const { localData, serverData, localTimestamp, serverTimestamp } = conflict;

    switch (strategy) {
      case this.strategies.SERVER_WINS:
        return {
          action: 'discard',
          result: serverData,
          message: 'Conflit résolu : les données du serveur ont été conservées',
        };

      case this.strategies.CLIENT_WINS:
        return {
          action: 'force',
          result: localData,
          message: 'Conflit résolu : vos modifications locales ont été appliquées',
        };

      case this.strategies.LAST_WRITE_WINS:
        if (localTimestamp > serverTimestamp) {
          return {
            action: 'force',
            result: localData,
            message: 'Conflit résolu : modification la plus récente conservée (locale)',
          };
        } else {
          return {
            action: 'discard',
            result: serverData,
            message: 'Conflit résolu : modification la plus récente conservée (serveur)',
          };
        }

      case this.strategies.MERGE:
        const merged = this.mergeData(localData, serverData);
        return {
          action: 'merge',
          result: merged,
          message: 'Conflit résolu : les données ont été fusionnées',
        };

      case this.strategies.MANUAL:
        this.pendingConflicts.push(conflict);
        return {
          action: 'pending',
          result: null,
          message: 'Conflit en attente de résolution manuelle',
        };

      default:
        return this.resolveConflict(conflict, this.defaultStrategy);
    }
  }

  /**
   * Merge two data objects intelligently
   */
  mergeData(localData, serverData) {
    const merged = { ...serverData };

    // Only merge scalar values that were changed locally
    for (const [key, value] of Object.entries(localData)) {
      // Skip metadata fields
      if (['id', 'created_at', 'updated_at', 'tenant_id'].includes(key)) {
        continue;
      }

      // If local value is different and not null, use it
      if (value !== null && value !== serverData[key]) {
        merged[key] = value;
        merged[`_merged_${key}`] = {
          local: value,
          server: serverData[key],
        };
      }
    }

    merged._merged_at = Date.now();
    return merged;
  }

  /**
   * Get pending conflicts for manual resolution
   */
  getPendingConflicts() {
    return this.pendingConflicts;
  }

  /**
   * Resolve a pending conflict manually
   */
  resolvePendingConflict(conflictIndex, resolution) {
    if (conflictIndex >= 0 && conflictIndex < this.pendingConflicts.length) {
      const conflict = this.pendingConflicts.splice(conflictIndex, 1)[0];
      return {
        conflict,
        resolution,
      };
    }
    return null;
  }

  /**
   * Clear all pending conflicts
   */
  clearPendingConflicts() {
    this.pendingConflicts = [];
  }
}

// ==========================================================================
// Conflict-Aware Sync Process
// ==========================================================================

class ConflictAwareSyncManager {
  constructor(storage, resolver) {
    this.storage = storage;
    this.resolver = resolver;
    this.isSyncing = false;
    this.syncResults = [];
  }

  /**
   * Sync an action with conflict detection and resolution
   */
  async syncAction(action) {
    const result = {
      action,
      status: 'pending',
      conflict: null,
      resolution: null,
      error: null,
    };

    try {
      // First, fetch current server state
      const serverState = await this.fetchServerState(action);

      // Detect conflict
      const conflictCheck = await this.resolver.detectConflict(action, serverState);

      if (conflictCheck.hasConflict) {
        console.log('[ConflictSync] Conflict detected:', conflictCheck);
        result.conflict = conflictCheck;

        // Resolve based on action type
        const strategy = this.getStrategyForAction(action);
        result.resolution = await this.resolver.resolveConflict(conflictCheck, strategy);

        if (result.resolution.action === 'discard') {
          result.status = 'discarded';
          return result;
        }

        if (result.resolution.action === 'pending') {
          result.status = 'needs_review';
          return result;
        }

        // Use resolved data for sync
        action.body = result.resolution.result;
      }

      // Perform the sync
      const response = await fetch(action.url, {
        method: action.method,
        headers: {
          ...action.headers,
          'Content-Type': 'application/json',
          'X-Conflict-Resolved': conflictCheck.hasConflict ? 'true' : 'false',
        },
        body: JSON.stringify(action.body),
      });

      if (response.ok) {
        result.status = 'synced';
        result.serverResponse = await response.json();
      } else {
        result.status = 'failed';
        result.error = await response.text();
      }

    } catch (error) {
      result.status = 'error';
      result.error = error.message;
    }

    this.syncResults.push(result);
    return result;
  }

  /**
   * Fetch current server state for conflict detection
   */
  async fetchServerState(action) {
    // Determine the entity type and ID from the action
    const url = new URL(action.url, window.location.origin);
    const body = action.body || {};

    // Different endpoints have different ways to fetch state
    try {
      if (url.pathname.includes('ballots')) {
        // For ballots, we can't really fetch individual state, return null
        return null;
      }

      if (url.pathname.includes('attendances_upsert')) {
        const { meeting_id, member_id } = body;
        if (meeting_id && member_id) {
          const res = await fetch(`/api/v1/attendances.php?meeting_id=${meeting_id}&member_id=${member_id}`);
          if (res.ok) {
            const data = await res.json();
            return data.attendance || null;
          }
        }
      }

      if (url.pathname.includes('motions_open') || url.pathname.includes('motions_close')) {
        const { motion_id } = body;
        if (motion_id) {
          const res = await fetch(`/api/v1/motions.php?id=${motion_id}`);
          if (res.ok) {
            const data = await res.json();
            return data.motion || null;
          }
        }
      }
    } catch (error) {
      console.warn('[ConflictSync] Failed to fetch server state:', error);
    }

    return null;
  }

  /**
   * Determine resolution strategy based on action type
   */
  getStrategyForAction(action) {
    const url = action.url;

    // Votes are critical - last write wins to preserve voter intent
    if (url.includes('ballots')) {
      return this.resolver.strategies.LAST_WRITE_WINS;
    }

    // Attendance updates - server wins to ensure consistency
    if (url.includes('attendances')) {
      return this.resolver.strategies.SERVER_WINS;
    }

    // Motion state changes - require manual review
    if (url.includes('motions_open') || url.includes('motions_close')) {
      return this.resolver.strategies.MANUAL;
    }

    // Default strategy
    return this.resolver.strategies.LAST_WRITE_WINS;
  }

  /**
   * Sync all pending actions with conflict handling
   */
  async syncAllPending() {
    if (this.isSyncing) return;

    this.isSyncing = true;
    this.syncResults = [];

    try {
      const pending = await this.storage.getPendingActions();
      console.log(`[ConflictSync] Processing ${pending.length} pending actions`);

      for (const action of pending) {
        const result = await this.syncAction(action);

        // Update storage based on result
        if (result.status === 'synced' || result.status === 'discarded') {
          await this.storage.markActionComplete(action.id);
        } else if (result.status === 'failed' || result.status === 'error') {
          await this.storage.markActionFailed(action.id, result.error);
        }
        // 'needs_review' actions remain pending
      }

      return {
        total: pending.length,
        synced: this.syncResults.filter(r => r.status === 'synced').length,
        discarded: this.syncResults.filter(r => r.status === 'discarded').length,
        failed: this.syncResults.filter(r => r.status === 'failed' || r.status === 'error').length,
        needsReview: this.syncResults.filter(r => r.status === 'needs_review').length,
        results: this.syncResults,
      };

    } finally {
      this.isSyncing = false;
    }
  }

  /**
   * Get sync results summary
   */
  getSyncResults() {
    return this.syncResults;
  }
}

// ==========================================================================
// Conflict Resolution UI Helper
// ==========================================================================

const ConflictUI = {
  /**
   * Show a conflict resolution dialog
   */
  showConflictDialog(conflict, onResolve) {
    const dialog = document.createElement('dialog');
    dialog.className = 'conflict-dialog';
    dialog.innerHTML = `
      <div class="conflict-header">
        <h2>Conflit de synchronisation</h2>
        <p>Une modification a été effectuée sur le serveur pendant que vous étiez hors ligne.</p>
      </div>

      <div class="conflict-comparison">
        <div class="conflict-local">
          <h3>Vos modifications</h3>
          <pre>${JSON.stringify(conflict.localData, null, 2)}</pre>
          <small>Créé le ${new Date(conflict.localTimestamp).toLocaleString('fr-FR')}</small>
        </div>

        <div class="conflict-server">
          <h3>Version serveur</h3>
          <pre>${JSON.stringify(conflict.serverData, null, 2)}</pre>
          <small>Mis à jour le ${new Date(conflict.serverTimestamp).toLocaleString('fr-FR')}</small>
        </div>
      </div>

      <div class="conflict-actions">
        <button type="button" class="btn-local">Garder mes modifications</button>
        <button type="button" class="btn-server">Garder la version serveur</button>
        <button type="button" class="btn-merge">Fusionner</button>
        <button type="button" class="btn-cancel">Annuler</button>
      </div>
    `;

    // Add styles
    const style = document.createElement('style');
    style.textContent = `
      .conflict-dialog {
        max-width: 800px;
        padding: 1.5rem;
        border: none;
        border-radius: 8px;
        box-shadow: 0 4px 20px rgba(0,0,0,0.15);
      }
      .conflict-dialog::backdrop {
        background: rgba(0,0,0,0.5);
      }
      .conflict-header h2 {
        margin: 0 0 0.5rem;
        color: #d32f2f;
      }
      .conflict-comparison {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 1rem;
        margin: 1rem 0;
      }
      .conflict-local, .conflict-server {
        border: 1px solid #ddd;
        border-radius: 4px;
        padding: 1rem;
      }
      .conflict-local { border-color: #1976d2; }
      .conflict-server { border-color: #388e3c; }
      .conflict-local h3 { color: #1976d2; margin: 0 0 0.5rem; }
      .conflict-server h3 { color: #388e3c; margin: 0 0 0.5rem; }
      .conflict-comparison pre {
        font-size: 0.8rem;
        overflow: auto;
        max-height: 200px;
        background: #f5f5f5;
        padding: 0.5rem;
        border-radius: 4px;
      }
      .conflict-actions {
        display: flex;
        gap: 0.5rem;
        justify-content: flex-end;
        margin-top: 1rem;
      }
      .conflict-actions button {
        padding: 0.5rem 1rem;
        border: none;
        border-radius: 4px;
        cursor: pointer;
      }
      .btn-local { background: #1976d2; color: white; }
      .btn-server { background: #388e3c; color: white; }
      .btn-merge { background: #7b1fa2; color: white; }
      .btn-cancel { background: #757575; color: white; }
    `;
    dialog.appendChild(style);

    // Handle button clicks
    dialog.querySelector('.btn-local').onclick = () => {
      onResolve('client_wins');
      dialog.close();
      dialog.remove();
    };
    dialog.querySelector('.btn-server').onclick = () => {
      onResolve('server_wins');
      dialog.close();
      dialog.remove();
    };
    dialog.querySelector('.btn-merge').onclick = () => {
      onResolve('merge');
      dialog.close();
      dialog.remove();
    };
    dialog.querySelector('.btn-cancel').onclick = () => {
      onResolve(null);
      dialog.close();
      dialog.remove();
    };

    document.body.appendChild(dialog);
    dialog.showModal();

    return dialog;
  },

  /**
   * Show a notification about sync status
   */
  showSyncNotification(syncResult) {
    const notification = document.createElement('div');
    notification.className = 'sync-notification';

    const { synced, discarded, failed, needsReview, total } = syncResult;

    let message = `Synchronisation terminée : ${synced}/${total} actions réussies`;
    let type = 'success';

    if (failed > 0 || needsReview > 0) {
      message = `Synchronisation partielle : ${synced} réussies, ${failed} échouées, ${needsReview} en attente`;
      type = needsReview > 0 ? 'warning' : 'error';
    }

    notification.innerHTML = `
      <span class="sync-icon">${type === 'success' ? '✓' : type === 'warning' ? '⚠' : '✗'}</span>
      <span class="sync-message">${message}</span>
      <button class="sync-dismiss">&times;</button>
    `;

    notification.className = `sync-notification sync-${type}`;

    // Add styles if not already present
    if (!document.querySelector('#sync-notification-styles')) {
      const style = document.createElement('style');
      style.id = 'sync-notification-styles';
      style.textContent = `
        .sync-notification {
          position: fixed;
          bottom: 1rem;
          right: 1rem;
          padding: 1rem 1.5rem;
          border-radius: 8px;
          display: flex;
          align-items: center;
          gap: 0.75rem;
          box-shadow: 0 4px 12px rgba(0,0,0,0.15);
          z-index: 10000;
          animation: slideIn 0.3s ease;
        }
        @keyframes slideIn {
          from { transform: translateX(100%); opacity: 0; }
          to { transform: translateX(0); opacity: 1; }
        }
        .sync-success { background: #e8f5e9; border: 1px solid #4caf50; }
        .sync-warning { background: #fff3e0; border: 1px solid #ff9800; }
        .sync-error { background: #ffebee; border: 1px solid #f44336; }
        .sync-icon { font-size: 1.25rem; }
        .sync-dismiss {
          background: transparent;
          border: none;
          font-size: 1.25rem;
          cursor: pointer;
          opacity: 0.6;
        }
        .sync-dismiss:hover { opacity: 1; }
      `;
      document.head.appendChild(style);
    }

    notification.querySelector('.sync-dismiss').onclick = () => {
      notification.remove();
    };

    document.body.appendChild(notification);

    // Auto-dismiss after 5 seconds
    setTimeout(() => {
      if (notification.parentNode) {
        notification.remove();
      }
    }, 5000);
  }
};

// ==========================================================================
// Global Instance
// ==========================================================================

const conflictResolver = new ConflictResolver();

// Export
window.AgVoteOffline = window.AgVoteOffline || {};
window.AgVoteOffline.ConflictResolver = ConflictResolver;
window.AgVoteOffline.ConflictAwareSyncManager = ConflictAwareSyncManager;
window.AgVoteOffline.ConflictUI = ConflictUI;
window.AgVoteOffline.resolver = conflictResolver;
