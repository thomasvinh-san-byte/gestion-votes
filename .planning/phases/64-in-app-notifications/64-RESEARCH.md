# Phase 64: In-App Notifications - Research

**Researched:** 2026-04-01
**Domain:** In-app notification wiring — PHP backend + vanilla JS shell + SSE event streaming
**Confidence:** HIGH

## Summary

Phase 64 is a wiring phase, not a construction phase. The vast majority of required infrastructure already exists and is functional. The PHP backend (`NotificationsController` with `list()` and `markRead()`), the JS bell UI (`createNotifBell()`, `fetchNotifications()`, `renderNotifications()`), the toast system (`AgToast`, `Shared.showToast`), and the SSE broadcaster (`EventBroadcaster`) are all in place.

The work is: fix a data shape mismatch between the API response and the shell renderer, fix the mark-all-read call so it actually sends `{all: true}` in the body, wire SSE events to trigger toasts via `event-stream.js`, and add French human-readable notification labels. A `NotificationsService` class is expected by an existing test suite but does not yet exist — its creation is part of this phase.

The `notification_reads` migration already exists. The `NotificationRepository` already exists. Both API endpoint PHP files exist and dispatch to the controller. The routes are registered. The test coverage for `NotificationsController` already validates the core shape expected.

**Primary recommendation:** Wire the three gaps (data shape, mark-read body, SSE-to-toast) and create the `NotificationsService`. Four focused tasks, no new infrastructure.

---

<user_constraints>
## User Constraints (from CONTEXT.md)

### Locked Decisions
None — all implementation choices at Claude's discretion.

### Claude's Discretion
All implementation choices are at Claude's discretion — infrastructure wiring phase. Key constraints:
- `NotificationsController::list()` already exists with NOTIF_ACTIONS and read/unread state
- `shell.js` already has `createNotifBell()` with bell icon, badge, and panel scaffolding
- `Shared.showToast()` and `ag-toast` Web Component already exist
- `EventBroadcaster` and SSE infrastructure already exist (events.php, SseListener, Redis fan-out)
- `notification_reads` table already exists for read state tracking
- Bell polling at 60s intervals already wired in shell.js
- Mark-as-read endpoint needed (NotificationsController::markRead)
- SSE events to trigger toasts: motion_opened, quorum_met/quorum_updated, meeting_launched/meeting_closed
- Toast messages should be in French, matching the "officiel et confiance" tone

### Deferred Ideas (OUT OF SCOPE)
None.
</user_constraints>

---

<phase_requirements>
## Phase Requirements

| ID | Description | Research Support |
|----|-------------|-----------------|
| NOTIF-01 | Une icone cloche dans le header affiche un badge avec le nombre de notifications non lues | Bell and badge exist in shell.js; `unread_count` returned by API; badge update logic in `renderNotifications()` — needs data shape fix |
| NOTIF-02 | Cliquer sur la cloche affiche la liste des notifications recentes (nouveau vote ouvert, seance bientot, resultats disponibles) | Panel scaffolding exists; `loadNotifications()` / `renderNotifications()` in shell.js — needs human-readable French label mapping and data shape alignment |
| NOTIF-03 | Un toast apparait en temps reel via SSE quand un evenement important survient (vote ouvert, quorum atteint, seance demarree) | `AgToast.show()` exists; `EventStream` SSE client exists; event types already listed — needs `onEvent` handler added to SSE connection that translates event types to French toast messages |
</phase_requirements>

---

## Standard Stack

### Core (already installed)
| Component | Location | Purpose | Status |
|-----------|----------|---------|--------|
| `NotificationsController` | `app/Controller/NotificationsController.php` | API: list + markRead | Complete |
| `NotificationRepository` | `app/Repository/NotificationRepository.php` | DB: meeting_notifications table | Complete |
| `notification_reads` migration | `database/migrations/20260331_notification_reads.sql` | Read state tracking | Complete |
| `createNotifBell()` | `public/assets/js/core/shell.js` L585-677 | Bell, badge, panel, polling | Needs data shape fix |
| `AgToast` Web Component | `public/assets/js/components/ag-toast.js` | Toast notifications | Complete |
| `Shared.showToast()` | `public/assets/js/core/shared.js` L519-523 | Convenience wrapper for AgToast | Complete |
| `EventStream.connect()` | `public/assets/js/core/event-stream.js` | SSE client with reconnection | Complete |
| `EventBroadcaster` | `app/SSE/EventBroadcaster.php` | Server-side SSE event queueing | Complete |

### Missing (must create)
| Component | Location | Purpose |
|-----------|----------|---------|
| `NotificationsService` | `app/Service/NotificationsService.php` | Service layer: emit(), emitReadinessTransitions(), list(), markRead(), markAllRead(), clear() |

The `NotificationsServiceTest.php` test file already exists and fully specifies the expected interface. The service must be created to pass those tests.

---

## Architecture Patterns

### Data Flow: Bell Badge (NOTIF-01)

```
API GET /api/v1/notifications.php
  → NotificationsController::list()
  → Returns: { ok: true, data: { notifications: [...], unread_count: N } }
  → shell.js fetchNotifications() → renderNotifications(b.data)
  → renderNotifications() reads data.items || [] (MISMATCH — should be data.notifications)
  → badge .notif-count updated from unread items count
```

**Fix needed in `renderNotifications()`:** Change `data.items` to `data.notifications`.

### Data Flow: Notification Panel (NOTIF-02)

Each notification item from the API has this shape (from `AuditEventRepository::listRecentByActions`):
```json
{
  "id": "uuid",
  "type": "motion_opened",
  "payload": {...},
  "timestamp": "2026-04-01T12:00:00Z",
  "actor_id": "user-uuid",
  "actor_role": "operator",
  "read": false
}
```

The panel renders `n.message || n.title` — but the API returns `type` (audit action name like `motion_opened`), not a human-readable message. A label mapping function is needed:

```javascript
// Pattern: map audit action type to French display label
const NOTIF_LABELS = {
  'meeting_created':         'Séance créée',
  'meeting_launched':        'Séance démarrée',
  'meeting_closed':          'Séance clôturée',
  'meeting_validated':       'Séance validée',
  'motion_opened':           'Vote ouvert',
  'motion_closed':           'Vote clôturé',
  'attendances_bulk_update': 'Présences mises à jour',
  'member_created':          'Membre ajouté',
  'vote_anomaly':            'Anomalie de vote',
  'meeting_archived':        'Séance archivée',
  'member_imported':         'Import de membres',
  'proxy_created':           'Mandat enregistré',
  'emergency_triggered':     'Procédure d\'urgence',
  'speech_granted':          'Parole accordée',
};
```

Timestamp rendering: the API returns `timestamp` (ISO 8601 string). Shell renders `n.time || n.created_at`. Must use `n.timestamp`.

### Data Flow: Mark-as-Read (NOTIF-01 badge decrement)

**Bug in current `markNotificationsRead()`:**
```javascript
// Current — sends empty body {}
await window.api('/api/v1/notifications_read.php', {}, 'PUT');

// Fix — must send { all: true } body
await window.api('/api/v1/notifications_read.php', { all: true }, 'PUT');
```

The `window.api()` function uses `hasBody = data !== null` — passing `{}` sends a PUT with body `{}`. The controller then checks `$body['all']` which is missing from `{}`, so it falls through to `$body['ids'] ?? []` which is also empty, returning `marked: 0`. The badge never clears.

After marking read, `fetchNotifications()` should be called again to update the badge to 0.

### Data Flow: SSE Toasts (NOTIF-03)

The SSE client (`event-stream.js`) is meeting-scoped — it connects to `/api/v1/events.php?meeting_id=X`. The `onEvent` callback receives `(type, data)` for each SSE event.

Current event types already handled by `EventBroadcaster` that map to toasts:

| SSE Event Type | French Toast | Toast Type |
|---------------|--------------|------------|
| `motion.opened` | `Vote ouvert : {motion title}` | `info` |
| `motion.closed` | `Vote clôturé` | `info` |
| `quorum.updated` | `Quorum atteint` (when quorum.met=true) | `success` |
| `meeting.status_changed` new_status=`live` | `Séance démarrée` | `success` |
| `meeting.status_changed` new_status=`closed` | `Séance clôturée` | `info` |

The SSE toast handler lives in the page JS (operator-exec.js, hub.js, etc.), not shell.js — because SSE is only connected when a meeting is active. The correct wiring point is where `EventStream.connect()` is called on each page.

**Simpler alternative:** Add a global SSE toast handler hook in shell.js that pages can register via `window.Notifications.onSseEvent = (type, data) => ...`. Then each page wires it when they call `EventStream.connect()`.

**Even simpler — already exists in hub.js:**
```javascript
// hub.js L575-576 — already handles SSE toasts:
if (payload && payload.msg && typeof Shared !== 'undefined' && Shared.showToast) {
  Shared.showToast(payload.msg, payload.type || 'success');
}
```

The gap is that `EventBroadcaster` does not set `msg` / `type` fields on event payloads. The fix is either:
1. Add a `toast` key to specific EventBroadcaster event payloads (server-side approach — one place)
2. Add client-side event-type-to-message mapping in a shared location

**Recommendation:** Client-side mapping in `shell.js` or a new `notifications-toast.js`. Keeps server lean.

### NotificationsService Interface (from existing tests)

```php
class NotificationsService {
    public function __construct(MeetingRepository $meetingRepo, NotificationRepository $notifRepo) {}

    // Emit a notification (deduplication via countRecentDuplicates)
    public function emit(
        string $meetingId, string $severity, string $code,
        string $message, array $audience, array $data, string $tenantId
    ): void {}

    // Emit notifications when meeting readiness transitions
    public function emitReadinessTransitions(
        string $meetingId, array $validation, string $tenantId
    ): void {}

    // List notifications since ID (for polling)
    public function list(
        string $meetingId, string $audience, int $sinceId,
        int $limit, string $tenantId
    ): array {}

    // Last N notifications (for init)
    public function recent(
        string $meetingId, string $audience, int $limit, string $tenantId
    ): array {}

    // Mark single notification read
    public function markRead(string $meetingId, int $id, string $tenantId): void {}

    // Mark all notifications read for an audience
    public function markAllRead(string $meetingId, string $audience, string $tenantId): void {}

    // Clear all notifications for an audience
    public function clear(string $meetingId, string $audience, string $tenantId): void {}
}
```

The service stores notifications in `meeting_notifications` (via `NotificationRepository`), separate from the audit-event-based approach used by `NotificationsController`. Phase 64 uses the **audit-event approach** for the bell (existing controller) — the service is for future meeting-scoped notification emit patterns.

---

## Don't Hand-Roll

| Problem | Don't Build | Use Instead |
|---------|-------------|-------------|
| Toast display | Custom toast DOM | `AgToast.show(type, message)` — already exists |
| Toast convenience | Direct AgToast calls in multiple files | `Shared.showToast(message, type)` wrapper |
| SSE connection | New EventSource setup | `EventStream.connect(meetingId, opts)` |
| SSE reconnection | Manual reconnect logic | Built into `EventStream` (MAX_RECONNECT_ATTEMPTS=10) |
| Bell badge update | DOM manipulation outside shell | `window.Notifications.fetch()` — already exported |
| Mark-read PUT | Custom fetch | `window.api('/api/v1/notifications_read.php', {all:true}, 'PUT')` |

---

## Common Pitfalls

### Pitfall 1: Data Shape Mismatch
**What goes wrong:** `renderNotifications()` reads `data.items || []` but the API returns `data.notifications`. Badge never updates, panel always shows "Aucune notification".
**Fix:** Change to `data.notifications || data.items || []` for backward compatibility, and use `data.unread_count` directly for the badge.

### Pitfall 2: Mark-Read Body is Empty Object
**What goes wrong:** `window.api(url, {}, 'PUT')` sends `{}` as body. `$body['all']` is unset. Controller marks 0 notifications. Badge stuck at N after panel opens.
**Fix:** Send `{ all: true }` as the body: `window.api(url, { all: true }, 'PUT')`.

### Pitfall 3: Notification Timestamp Field Name
**What goes wrong:** Shell renders `n.time || n.created_at` but API returns `n.timestamp`. All timestamps show blank.
**Fix:** Use `n.timestamp || n.created_at || n.time` in the render function.

### Pitfall 4: SSE Toast Handler Scope
**What goes wrong:** SSE is meeting-scoped. Wiring SSE toasts in shell.js means they fire on all pages, but `EventStream.connect()` is only called from operator/hub pages. Shell.js cannot directly connect — it doesn't know the meeting ID at load time.
**Fix:** Export a hook from shell.js that page JS calls after connecting SSE. Or pass SSE `onEvent` handler to the EventStream at connect time and have it include toast firing.

### Pitfall 5: Panel Click Propagation
**What goes wrong:** `document.addEventListener('click', () => notifPanel.style.display = 'none')` is already registered. Clicking inside the panel closes it immediately.
**Fix:** Add `e.stopPropagation()` inside the notif panel, or check `e.target.closest('.notif-panel')` before hiding. This pattern is already partially handled via `e.stopPropagation()` on the bell button click.

### Pitfall 6: Notification Read State After markRead
**What goes wrong:** After `markNotificationsRead()` fires (on panel open), the badge count does not visually drop to 0 because `renderNotifications()` is not called again with fresh data.
**Fix:** After `await window.api(... markRead ...)`, immediately call `fetchNotifications()` again to refresh the badge.

### Pitfall 7: `NotificationsService` Test Namespace
**What goes wrong:** `NotificationsServiceTest` uses namespace `AgVote\Tests\Unit` while `NotificationsControllerTest` uses `Tests\Unit`. Creating the service in the wrong directory or namespace will fail tests.
**Fix:** Create `app/Service/NotificationsService.php` with namespace `AgVote\Service`. The test uses `use AgVote\Service\NotificationsService`.

---

## Code Examples

### Correct `renderNotifications()` (fixed)
```javascript
function renderNotifications(data) {
  // API returns { notifications: [...], unread_count: N }
  const items = data.notifications || data.items || [];
  const unread = typeof data.unread_count === 'number'
    ? data.unread_count
    : items.filter(function(n) { return !n.read; }).length;
  notifCount = unread;

  const countEl = document.querySelector('.notif-count');
  if (countEl) {
    countEl.textContent = unread > 9 ? '9+' : String(unread);
    countEl.style.display = unread > 0 ? 'flex' : 'none';
  }

  const list = notifPanel && notifPanel.querySelector('.notif-list');
  if (!list) return;

  if (items.length === 0) {
    list.innerHTML = '<div style="padding:20px;text-align:center;font-size:13px;color:var(--color-text-muted);">Aucune notification</div>';
    return;
  }

  list.innerHTML = items.slice(0, 6).map(function(n) {
    const label = NOTIF_LABELS[n.type] || n.type || 'Notification';
    const ts = n.timestamp || n.created_at || n.time || '';
    const dotColor = n.read ? 'var(--color-border)' : 'var(--color-primary)';
    return '<div class="notif-item">' +
      '<span class="notif-dot" style="background:' + dotColor + '"></span>' +
      '<div class="notif-body">' +
        '<div class="notif-msg">' + esc(label) + '</div>' +
        '<div class="notif-time">' + esc(ts) + '</div>' +
      '</div></div>';
  }).join('');
}
```

### Correct `markNotificationsRead()` (fixed)
```javascript
async function markNotificationsRead() {
  try {
    if (!window.api || notifCount === 0) return;
    await window.api('/api/v1/notifications_read.php', { all: true }, 'PUT');
    // Refresh to drop badge to 0
    await fetchNotifications();
  } catch(e) { /* silent */ }
}
```

### SSE Toast Handler (new — in shell.js Notifications section)
```javascript
const SSE_TOAST_MAP = {
  'motion.opened':          function(data) {
    var title = (data.motion && data.motion.title) ? data.motion.title : '';
    return { type: 'info', msg: title ? 'Vote ouvert : ' + title : 'Vote ouvert' };
  },
  'motion.closed':          function() { return { type: 'info',    msg: 'Vote clôturé' }; },
  'quorum.updated':         function(data) {
    if (data.quorum && data.quorum.met) return { type: 'success', msg: 'Quorum atteint' };
    return null; // no toast if quorum not met
  },
  'meeting.status_changed': function(data) {
    if (data.new_status === 'live')   return { type: 'success', msg: 'Séance démarrée' };
    if (data.new_status === 'closed') return { type: 'info',    msg: 'Séance clôturée' };
    return null;
  },
};

// Called by page JS after EventStream.connect()
window.Notifications = {
  fetch: fetchNotifications,
  handleSseEvent: function(type, data) {
    var handler = SSE_TOAST_MAP[type];
    if (!handler) return;
    var toast = handler(data || {});
    if (toast && Shared && Shared.showToast) {
      Shared.showToast(toast.msg, toast.type);
    }
    // Also refresh bell badge
    fetchNotifications();
  },
};
```

### Page-side SSE wiring (operator-exec.js / hub.js)
```javascript
// When setting up EventStream.connect():
stream = EventStream.connect(meetingId, {
  onEvent: function(type, data) {
    // ... existing event handling ...
    // Add: forward to notification system
    if (window.Notifications && window.Notifications.handleSseEvent) {
      window.Notifications.handleSseEvent(type, data);
    }
  },
});
```

### `NotificationsService` skeleton (matches existing tests)
```php
<?php
declare(strict_types=1);

namespace AgVote\Service;

use AgVote\Repository\MeetingRepository;
use AgVote\Repository\NotificationRepository;

final class NotificationsService {
    public function __construct(
        private readonly MeetingRepository $meetingRepo,
        private readonly NotificationRepository $notifRepo,
    ) {}

    public function emit(
        string $meetingId, string $severity, string $code,
        string $message, array $audience, array $data, string $tenantId
    ): void {
        $meeting = $this->meetingRepo->findByIdForTenant($meetingId, $tenantId);
        if ($meeting === null) return;

        if ($this->notifRepo->countRecentDuplicates($meetingId, $code, $message, $tenantId) > 0) {
            return;
        }

        $audience = array_values(array_unique(array_filter($audience)));
        if (empty($audience)) $audience = ['operator', 'trust'];
        $audLiteral = '{' . implode(',', array_map(fn($a) => '"' . $a . '"', $audience)) . '}';

        $this->notifRepo->insert(
            $tenantId, $meetingId, $severity, $code,
            $message, $audLiteral, json_encode($data) ?: '{}',
        );
    }

    public function list(string $meetingId, string $audience, int $sinceId, int $limit, string $tenantId): array {
        return $this->notifRepo->listSinceId($meetingId, $sinceId, $limit, $audience, $tenantId);
    }

    public function recent(string $meetingId, string $audience, int $limit, string $tenantId): array {
        return $this->notifRepo->listRecent($meetingId, $limit, $audience, $tenantId);
    }

    public function markRead(string $meetingId, int $id, string $tenantId): void {
        $this->notifRepo->markRead($meetingId, $id, $tenantId);
    }

    public function markAllRead(string $meetingId, string $audience, string $tenantId): void {
        $this->notifRepo->markAllRead($meetingId, $audience, $tenantId);
    }

    public function clear(string $meetingId, string $audience, string $tenantId): void {
        $this->notifRepo->clear($meetingId, $audience, $tenantId);
    }

    public function emitReadinessTransitions(string $meetingId, array $validation, string $tenantId): void {
        // ... see tests for full spec ...
    }
}
```

---

## State of the Art

| Was | Is | Impact |
|-----|----|--------|
| `data.items` in renderNotifications | `data.notifications` from API | Must update shell.js reader |
| Empty `{}` body in markRead call | `{ all: true }` body required | Must fix shell.js mark-read |
| Toast triggered by `payload.msg` field | Toast triggered by SSE event type mapping | Need client-side SSE_TOAST_MAP |
| No `NotificationsService` | Expected by existing tests | Must create `app/Service/NotificationsService.php` |

---

## Open Questions

1. **Should bell polling also refresh after SSE events?**
   - What we know: Bell polls every 60s via `setInterval`. SSE events arrive in real-time.
   - What's unclear: Should SSE events also trigger an immediate bell refresh?
   - Recommendation: Yes — call `fetchNotifications()` inside `handleSseEvent()` (included in code examples above) to keep badge in sync with real-time events.

2. **Which pages connect EventStream and need SSE toast wiring?**
   - What we know: `hub.js` already has partial SSE handling. `operator-exec.js` is the main operator page.
   - Recommendation: Wire in both pages — operator-exec.js and hub.js. Both already call `EventStream.connect()`.

3. **`emitReadinessTransitions()` — is it needed for Phase 64?**
   - What we know: Tests exist for it. It manages `meeting_notifications` table transitions.
   - What's unclear: No requirement directly calls for readiness transition notifications in NOTIF-01/02/03.
   - Recommendation: Implement the full `NotificationsService` including `emitReadinessTransitions()` to pass existing tests, but do not wire it to any controller in this phase unless it fits NOTIF-01/02/03 directly.

---

## Validation Architecture

### Test Framework
| Property | Value |
|----------|-------|
| Framework | PHPUnit 10.5 |
| Config file | `phpunit.xml` (root) |
| Quick run command | `./vendor/bin/phpunit tests/Unit/NotificationsControllerTest.php tests/Unit/NotificationsServiceTest.php --no-coverage` |
| Full suite command | `./vendor/bin/phpunit --no-coverage` |

### Phase Requirements → Test Map
| Req ID | Behavior | Test Type | Automated Command | File Exists? |
|--------|----------|-----------|-------------------|-------------|
| NOTIF-01 | Bell badge shows unread_count from API | unit (controller) | `./vendor/bin/phpunit tests/Unit/NotificationsControllerTest.php --no-coverage` | ✅ |
| NOTIF-01 | markRead endpoint returns marked count | unit (controller) | `./vendor/bin/phpunit tests/Unit/NotificationsControllerTest.php --no-coverage` | ✅ |
| NOTIF-02 | Panel renders notification list | manual (browser) | n/a — UI rendering | — |
| NOTIF-03 | SSE event triggers toast | manual (browser) | n/a — SSE/UI | — |
| service | NotificationsService emit/list/markRead | unit (service) | `./vendor/bin/phpunit tests/Unit/NotificationsServiceTest.php --no-coverage` | ✅ (tests exist, impl missing) |

### Sampling Rate
- **Per task commit:** `./vendor/bin/phpunit tests/Unit/NotificationsControllerTest.php tests/Unit/NotificationsServiceTest.php --no-coverage`
- **Per wave merge:** `./vendor/bin/phpunit --no-coverage`
- **Phase gate:** Full suite green before `/gsd:verify-work`

### Wave 0 Gaps
- [ ] `app/Service/NotificationsService.php` — covers NotificationsServiceTest (tests exist, service does not)

---

## Sources

### Primary (HIGH confidence)
- Direct code inspection: `app/Controller/NotificationsController.php` — full controller implementation
- Direct code inspection: `app/Repository/NotificationRepository.php` — full repository with all query methods
- Direct code inspection: `public/assets/js/core/shell.js` L577-679 — notification bell, panel, poll, mark-read
- Direct code inspection: `public/assets/js/components/ag-toast.js` — AgToast.show() API
- Direct code inspection: `public/assets/js/core/shared.js` L519-558 — Shared.showToast() wrapper
- Direct code inspection: `public/assets/js/core/event-stream.js` — EventStream.connect() API and event types
- Direct code inspection: `app/SSE/EventBroadcaster.php` — SSE event types and payload shapes
- Direct code inspection: `tests/Unit/NotificationsControllerTest.php` — controller test coverage
- Direct code inspection: `tests/Unit/NotificationsServiceTest.php` — full service interface spec
- Direct code inspection: `app/routes.php` L293-295 — notifications routes registered

### Secondary (MEDIUM confidence)
- Inferred from hub.js L477-478, 575-576 — Shared.showToast usage pattern

### Tertiary (LOW confidence)
- None

## Metadata

**Confidence breakdown:**
- Standard stack: HIGH — all components directly inspected
- Architecture: HIGH — data flows traced through actual code
- Pitfalls: HIGH — bugs verified by reading actual function bodies
- Service interface: HIGH — test file fully specifies the expected API

**Research date:** 2026-04-01
**Valid until:** 2026-05-01 (stable codebase)
