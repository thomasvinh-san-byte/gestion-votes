/**
 * AG-Vote Offline Storage
 *
 * IndexedDB wrapper for offline data storage and sync.
 */

const DB_NAME = 'agvote-offline';
const DB_VERSION = 1;

// Store names
const STORES = {
  MEETINGS: 'meetings',
  MOTIONS: 'motions',
  MEMBERS: 'members',
  ATTENDANCES: 'attendances',
  OFFLINE_QUEUE: 'offline_queue',
  SYNC_STATE: 'sync_state',
};

class OfflineStorage {
  constructor() {
    this.db = null;
    this.initPromise = null;
  }

  /**
   * Initialize the database
   */
  async init() {
    if (this.db) return this.db;
    if (this.initPromise) return this.initPromise;

    this.initPromise = new Promise((resolve, reject) => {
      const request = indexedDB.open(DB_NAME, DB_VERSION);

      request.onerror = () => {
        console.error('[OfflineStorage] Failed to open database:', request.error);
        reject(request.error);
      };

      request.onsuccess = () => {
        this.db = request.result;
        if (window.AG_DEBUG) console.log('[OfflineStorage] Database opened successfully');
        resolve(this.db);
      };

      request.onupgradeneeded = (event) => {
        const db = event.target.result;
        if (window.AG_DEBUG) console.log('[OfflineStorage] Upgrading database schema');

        // Meetings store
        if (!db.objectStoreNames.contains(STORES.MEETINGS)) {
          const meetingsStore = db.createObjectStore(STORES.MEETINGS, { keyPath: 'id' });
          meetingsStore.createIndex('tenant_id', 'tenant_id', { unique: false });
          meetingsStore.createIndex('status', 'status', { unique: false });
        }

        // Motions store
        if (!db.objectStoreNames.contains(STORES.MOTIONS)) {
          const motionsStore = db.createObjectStore(STORES.MOTIONS, { keyPath: 'id' });
          motionsStore.createIndex('meeting_id', 'meeting_id', { unique: false });
          motionsStore.createIndex('status', 'status', { unique: false });
        }

        // Members store
        if (!db.objectStoreNames.contains(STORES.MEMBERS)) {
          const membersStore = db.createObjectStore(STORES.MEMBERS, { keyPath: 'id' });
          membersStore.createIndex('tenant_id', 'tenant_id', { unique: false });
        }

        // Attendances store
        if (!db.objectStoreNames.contains(STORES.ATTENDANCES)) {
          const attendancesStore = db.createObjectStore(STORES.ATTENDANCES, { keyPath: ['meeting_id', 'member_id'] });
          attendancesStore.createIndex('meeting_id', 'meeting_id', { unique: false });
        }

        // Offline queue store
        if (!db.objectStoreNames.contains(STORES.OFFLINE_QUEUE)) {
          const queueStore = db.createObjectStore(STORES.OFFLINE_QUEUE, { keyPath: 'id', autoIncrement: true });
          queueStore.createIndex('timestamp', 'timestamp', { unique: false });
          queueStore.createIndex('status', 'status', { unique: false });
        }

        // Sync state store
        if (!db.objectStoreNames.contains(STORES.SYNC_STATE)) {
          db.createObjectStore(STORES.SYNC_STATE, { keyPath: 'key' });
        }
      };
    });

    return this.initPromise;
  }

  // ==========================================================================
  // Generic CRUD Operations
  // ==========================================================================

  async put(storeName, data) {
    await this.init();
    return new Promise((resolve, reject) => {
      const tx = this.db.transaction(storeName, 'readwrite');
      const store = tx.objectStore(storeName);
      const request = store.put(data);
      request.onsuccess = () => resolve(request.result);
      request.onerror = () => reject(request.error);
    });
  }

  async get(storeName, key) {
    await this.init();
    return new Promise((resolve, reject) => {
      const tx = this.db.transaction(storeName, 'readonly');
      const store = tx.objectStore(storeName);
      const request = store.get(key);
      request.onsuccess = () => resolve(request.result);
      request.onerror = () => reject(request.error);
    });
  }

  async getAll(storeName, indexName = null, indexValue = null) {
    await this.init();
    return new Promise((resolve, reject) => {
      const tx = this.db.transaction(storeName, 'readonly');
      const store = tx.objectStore(storeName);

      let request;
      if (indexName && indexValue !== null) {
        const index = store.index(indexName);
        request = index.getAll(indexValue);
      } else {
        request = store.getAll();
      }

      request.onsuccess = () => resolve(request.result || []);
      request.onerror = () => reject(request.error);
    });
  }

  async delete(storeName, key) {
    await this.init();
    return new Promise((resolve, reject) => {
      const tx = this.db.transaction(storeName, 'readwrite');
      const store = tx.objectStore(storeName);
      const request = store.delete(key);
      request.onsuccess = () => resolve();
      request.onerror = () => reject(request.error);
    });
  }

  async clear(storeName) {
    await this.init();
    return new Promise((resolve, reject) => {
      const tx = this.db.transaction(storeName, 'readwrite');
      const store = tx.objectStore(storeName);
      const request = store.clear();
      request.onsuccess = () => resolve();
      request.onerror = () => reject(request.error);
    });
  }

  // ==========================================================================
  // Meeting Operations
  // ==========================================================================

  async saveMeeting(meeting) {
    meeting._cachedAt = Date.now();
    return this.put(STORES.MEETINGS, meeting);
  }

  async getMeeting(id) {
    return this.get(STORES.MEETINGS, id);
  }

  async getAllMeetings() {
    return this.getAll(STORES.MEETINGS);
  }

  // ==========================================================================
  // Motion Operations
  // ==========================================================================

  async saveMotion(motion) {
    motion._cachedAt = Date.now();
    return this.put(STORES.MOTIONS, motion);
  }

  async saveMotions(motions) {
    for (const motion of motions) {
      await this.saveMotion(motion);
    }
  }

  async getMotion(id) {
    return this.get(STORES.MOTIONS, id);
  }

  async getMotionsForMeeting(meetingId) {
    return this.getAll(STORES.MOTIONS, 'meeting_id', meetingId);
  }

  // ==========================================================================
  // Member Operations
  // ==========================================================================

  async saveMember(member) {
    member._cachedAt = Date.now();
    return this.put(STORES.MEMBERS, member);
  }

  async saveMembers(members) {
    for (const member of members) {
      await this.saveMember(member);
    }
  }

  async getMember(id) {
    return this.get(STORES.MEMBERS, id);
  }

  async getAllMembers() {
    return this.getAll(STORES.MEMBERS);
  }

  // ==========================================================================
  // Attendance Operations
  // ==========================================================================

  async saveAttendance(attendance) {
    attendance._cachedAt = Date.now();
    return this.put(STORES.ATTENDANCES, attendance);
  }

  async saveAttendances(attendances) {
    for (const attendance of attendances) {
      await this.saveAttendance(attendance);
    }
  }

  async getAttendancesForMeeting(meetingId) {
    return this.getAll(STORES.ATTENDANCES, 'meeting_id', meetingId);
  }

  // ==========================================================================
  // Offline Queue Operations
  // ==========================================================================

  async queueAction(action) {
    const queueItem = {
      ...action,
      status: 'pending',
      timestamp: Date.now(),
      retries: 0,
    };
    return this.put(STORES.OFFLINE_QUEUE, queueItem);
  }

  async getPendingActions() {
    const all = await this.getAll(STORES.OFFLINE_QUEUE);
    return all.filter(a => a.status === 'pending').sort((a, b) => a.timestamp - b.timestamp);
  }

  async markActionComplete(id) {
    const action = await this.get(STORES.OFFLINE_QUEUE, id);
    if (action) {
      action.status = 'completed';
      action.completedAt = Date.now();
      await this.put(STORES.OFFLINE_QUEUE, action);
    }
  }

  async markActionFailed(id, error) {
    const action = await this.get(STORES.OFFLINE_QUEUE, id);
    if (action) {
      action.status = 'failed';
      action.error = error;
      action.failedAt = Date.now();
      action.retries = (action.retries || 0) + 1;
      await this.put(STORES.OFFLINE_QUEUE, action);
    }
  }

  async retryFailedActions() {
    const all = await this.getAll(STORES.OFFLINE_QUEUE);
    const failed = all.filter(a => a.status === 'failed' && (a.retries || 0) < 3);
    for (const action of failed) {
      action.status = 'pending';
      await this.put(STORES.OFFLINE_QUEUE, action);
    }
    return failed.length;
  }

  async clearCompletedActions() {
    const all = await this.getAll(STORES.OFFLINE_QUEUE);
    const completed = all.filter(a => a.status === 'completed');
    for (const action of completed) {
      await this.delete(STORES.OFFLINE_QUEUE, action.id);
    }
    return completed.length;
  }

  // ==========================================================================
  // Sync State
  // ==========================================================================

  async setSyncState(key, value) {
    return this.put(STORES.SYNC_STATE, { key, value, updatedAt: Date.now() });
  }

  async getSyncState(key) {
    const result = await this.get(STORES.SYNC_STATE, key);
    return result?.value;
  }

  async getLastSyncTime(entity) {
    return this.getSyncState(`lastSync_${entity}`);
  }

  async setLastSyncTime(entity) {
    return this.setSyncState(`lastSync_${entity}`, Date.now());
  }

  // ==========================================================================
  // Cache Management
  // ==========================================================================

  async getCacheStats() {
    await this.init();
    const stats = {};
    for (const storeName of Object.values(STORES)) {
      const items = await this.getAll(storeName);
      stats[storeName] = items.length;
    }
    return stats;
  }

  async clearAllCaches() {
    for (const storeName of [STORES.MEETINGS, STORES.MOTIONS, STORES.MEMBERS, STORES.ATTENDANCES]) {
      await this.clear(storeName);
    }
  }

  async isStale(entity, maxAgeMs = 5 * 60 * 1000) {
    const lastSync = await this.getLastSyncTime(entity);
    if (!lastSync) return true;
    return (Date.now() - lastSync) > maxAgeMs;
  }
}

// ==========================================================================
// Offline Sync Manager
// ==========================================================================

class OfflineSyncManager {
  constructor(storage) {
    this.storage = storage;
    this.isOnline = navigator.onLine;
    this.isSyncing = false;
    this.listeners = new Map();

    // Listen for online/offline events
    window.addEventListener('online', () => this.handleOnline());
    window.addEventListener('offline', () => this.handleOffline());

    // Listen for Service Worker messages
    if ('serviceWorker' in navigator) {
      navigator.serviceWorker.addEventListener('message', (event) => {
        this.handleServiceWorkerMessage(event.data);
      });
    }
  }

  handleOnline() {
    if (window.AG_DEBUG) console.log('[OfflineSync] Back online');
    this.isOnline = true;
    this.emit('online');
    this.syncPendingActions();
  }

  handleOffline() {
    if (window.AG_DEBUG) console.log('[OfflineSync] Gone offline');
    this.isOnline = false;
    this.emit('offline');
  }

  handleServiceWorkerMessage(data) {
    const { type, action, payload } = data;

    switch (type) {
      case 'OFFLINE_ACTION_QUEUED':
        if (action) {
          this.storage.queueAction(action);
          this.emit('actionQueued', action);
        }
        break;

      case 'SYNC_OFFLINE_ACTIONS':
        this.syncPendingActions();
        break;

      case 'CACHE_STATUS':
        this.emit('cacheStatus', payload);
        break;
    }
  }

  /**
   * Sync all pending offline actions
   */
  async syncPendingActions() {
    if (this.isSyncing || !this.isOnline) return;

    this.isSyncing = true;
    this.emit('syncStart');

    try {
      const pending = await this.storage.getPendingActions();
      if (window.AG_DEBUG) console.log(`[OfflineSync] Syncing ${pending.length} pending actions`);

      let successCount = 0;
      let failCount = 0;

      for (const action of pending) {
        try {
          const response = await fetch(action.url, {
            method: action.method,
            headers: {
              ...action.headers,
              'X-Offline-Sync': 'true',
            },
            body: JSON.stringify(action.body),
          });

          if (response.ok) {
            await this.storage.markActionComplete(action.id);
            successCount++;
            this.emit('actionSynced', action);
          } else {
            const error = await response.text();
            await this.storage.markActionFailed(action.id, error);
            failCount++;
            this.emit('actionFailed', { action, error });
          }
        } catch (error) {
          await this.storage.markActionFailed(action.id, error.message);
          failCount++;
          this.emit('actionFailed', { action, error: error.message });
        }
      }

      if (window.AG_DEBUG) console.log(`[OfflineSync] Sync complete: ${successCount} success, ${failCount} failed`);
      this.emit('syncComplete', { successCount, failCount });

    } finally {
      this.isSyncing = false;
    }
  }

  /**
   * Cache meeting data for offline use
   */
  async cacheMeetingData(meetingId) {
    try {
      // Fetch and cache meeting
      const meetingRes = await fetch(`/api/v1/meetings.php?id=${meetingId}`);
      if (meetingRes.ok) {
        const meetingData = await meetingRes.json();
        if (meetingData.meeting) {
          await this.storage.saveMeeting(meetingData.meeting);
        }
      }

      // Fetch and cache motions
      const motionsRes = await fetch(`/api/v1/motions.php?meeting_id=${meetingId}`);
      if (motionsRes.ok) {
        const motionsData = await motionsRes.json();
        if (motionsData.motions) {
          await this.storage.saveMotions(motionsData.motions);
        }
      }

      // Fetch and cache attendances
      const attendancesRes = await fetch(`/api/v1/attendances.php?meeting_id=${meetingId}`);
      if (attendancesRes.ok) {
        const attendancesData = await attendancesRes.json();
        if (attendancesData.attendances) {
          await this.storage.saveAttendances(attendancesData.attendances);
        }
      }

      await this.storage.setLastSyncTime(`meeting_${meetingId}`);
      if (window.AG_DEBUG) console.log(`[OfflineSync] Cached data for meeting ${meetingId}`);

    } catch (error) {
      console.error('[OfflineSync] Failed to cache meeting data:', error);
    }
  }

  /**
   * Get cached meeting data (offline fallback)
   */
  async getCachedMeetingData(meetingId) {
    const meeting = await this.storage.getMeeting(meetingId);
    const motions = await this.storage.getMotionsForMeeting(meetingId);
    const attendances = await this.storage.getAttendancesForMeeting(meetingId);

    return { meeting, motions, attendances };
  }

  /**
   * Register background sync (if supported)
   */
  async registerBackgroundSync() {
    if ('serviceWorker' in navigator && 'sync' in window.registration) {
      try {
        await navigator.serviceWorker.ready;
        await registration.sync.register('sync-offline-actions');
        if (window.AG_DEBUG) console.log('[OfflineSync] Background sync registered');
      } catch (error) {
        console.warn('[OfflineSync] Background sync not supported:', error);
      }
    }
  }

  // Event system
  on(event, callback) {
    if (!this.listeners.has(event)) {
      this.listeners.set(event, []);
    }
    this.listeners.get(event).push(callback);
    return () => this.off(event, callback);
  }

  off(event, callback) {
    const callbacks = this.listeners.get(event);
    if (callbacks) {
      const index = callbacks.indexOf(callback);
      if (index !== -1) callbacks.splice(index, 1);
    }
  }

  emit(event, data) {
    const callbacks = this.listeners.get(event) || [];
    callbacks.forEach(cb => cb(data));
  }
}

// ==========================================================================
// Service Worker Registration
// ==========================================================================

async function registerServiceWorker() {
  if (!('serviceWorker' in navigator)) {
    console.warn('[SW] Service Workers not supported');
    return null;
  }

  try {
    const registration = await navigator.serviceWorker.register('/sw.js', {
      scope: '/'
    });

    if (window.AG_DEBUG) console.log('[SW] Service Worker registered:', registration.scope);

    // Handle updates
    registration.addEventListener('updatefound', () => {
      const newWorker = registration.installing;
      if (window.AG_DEBUG) console.log('[SW] New Service Worker installing...');

      newWorker.addEventListener('statechange', () => {
        if (newWorker.state === 'installed' && navigator.serviceWorker.controller) {
          if (window.AG_DEBUG) console.log('[SW] New version available');
          // Optionally prompt user to refresh
          if (window.AgVoteOffline?.onUpdateAvailable) {
            window.AgVoteOffline.onUpdateAvailable();
          }
        }
      });
    });

    return registration;
  } catch (error) {
    console.error('[SW] Service Worker registration failed:', error);
    return null;
  }
}

// ==========================================================================
// Global Instance
// ==========================================================================

const offlineStorage = new OfflineStorage();
const offlineSync = new OfflineSyncManager(offlineStorage);

// Initialize on load
if (document.readyState === 'loading') {
  document.addEventListener('DOMContentLoaded', () => {
    offlineStorage.init();
    registerServiceWorker();
  });
} else {
  offlineStorage.init();
  registerServiceWorker();
}

// Export for use in other scripts
window.AgVoteOffline = {
  storage: offlineStorage,
  sync: offlineSync,
  registerServiceWorker,
  onUpdateAvailable: null, // Callback for SW updates
};

// Export stores for direct access
window.AgVoteOffline.STORES = STORES;
