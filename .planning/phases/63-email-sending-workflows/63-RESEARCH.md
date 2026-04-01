# Phase 63: Email Sending Workflows - Research

**Researched:** 2026-04-01
**Domain:** PHP backend email dispatch + vanilla JS operator console wiring
**Confidence:** HIGH — full codebase read, no speculative claims

## Summary

Phase 62 built the SMTP/template engine foundation. Phase 63 wires three email sending actions to the operator UI: invitation send (already partially wired), reminder send (new), and results email on session close (new). The backend services exist and are complete — `EmailQueueService`, `MailerService`, `EmailTemplateService`, `InvitationRepository` all work. The operator console HTML already has `btnSendInvitations` wired to `sendInvitations()` in operator-tabs.js. What is missing: (1) a reminder button and handler in tab-controle HTML + JS, (2) a results email backend path (new `scheduleResults()` method + `DEFAULT_RESULTS_TEMPLATE` constant), (3) a hook in `MeetingWorkflowController::transition()` that fires when `to_status === 'closed'`, and (4) send status display (badge on invitation button reflecting invitations_stats counts).

The close-session flow in operator-motions.js calls `POST /api/v1/meeting_transition.php` with `to_status: closed`. The results email hook belongs in the PHP controller `transition()` after the DB commit, following the same fire-and-forget pattern used for `EventBroadcaster::meetingStatusChanged()`.

**Primary recommendation:** Add results email hook in MeetingWorkflowController::transition() after the transaction, add reminder send method to EmailQueueService (reuse scheduleInvitations pattern with a reminder template), add reminder button in operator HTML, wire JS. Keep results email non-blocking (fire and log failures without failing the transition response).

---

<user_constraints>
## User Constraints (from CONTEXT.md)

### Locked Decisions
- "Envoyer les invitations" button in the operator console hub tab — near session details, per-session context
- Results emails triggered automatically when operator closes the session (hook into MeetingWorkflowController::transition() on close)
- Send status shown as badge/counter on the invitation button ("12/15 envoyés") + toast notification on completion
- Invitation link: `{app_url}/vote.htmx.html?token={token}` — already implemented in sendBulk(), uses existing vote token system
- Reminder link: `{app_url}/hub.htmx.html?meeting_id={meeting_id}` — brings member to session hub
- Results link: `{app_url}/postsession.htmx.html?meeting_id={meeting_id}` — shows final results
- Separate templates for invitation (DEFAULT_INVITATION_TEMPLATE) and reminder (DEFAULT_REMINDER_TEMPLATE) — different content, different purpose
- Results email needs a new DEFAULT_RESULTS_TEMPLATE

### Claude's Discretion
- Internal implementation details for the auto-results hook
- Queue processing strategy (immediate vs background)
- Error handling and retry logic

### Deferred Ideas (OUT OF SCOPE)
None — discussion stayed within phase scope.
</user_constraints>

---

<phase_requirements>
## Phase Requirements

| ID | Description | Research Support |
|----|-------------|-----------------|
| EMAIL-01 | L'operateur peut envoyer une invitation par email aux membres d'une seance — l'email contient un lien qui amene le destinataire vers la page de vote | `btnSendInvitations` + `sendInvitations()` in operator-tabs.js already calls `invitations_send_bulk.php`. Needs: reminder button distinction, send status badge wired to `invitations_stats` endpoint |
| EMAIL-02 | L'operateur peut envoyer un rappel par email avant une seance — l'email contient la date, le lieu et un lien vers le hub | `DEFAULT_REMINDER_TEMPLATE` exists but no "send reminder" route or button. Needs: new `email_send_reminder` route in routes.php, new `sendReminder()` method in EmailController, new `btnSendReminder` in tab-controle HTML, JS handler |
| EMAIL-03 | Apres cloture d'une seance, un email de resultats est envoye aux participants avec un lien vers les resultats | `MeetingWorkflowController::transition()` handles `closed` status. Needs: new `DEFAULT_RESULTS_TEMPLATE` constant, new `scheduleResults()` method in EmailQueueService, hook call after `api_transaction()` completes when `$toStatus === 'closed'` |
</phase_requirements>

---

## Standard Stack

### Core (all already installed)
| Library | Purpose | Location |
|---------|---------|---------|
| `symfony/mailer ^8.0` | SMTP transport | `composer.json` — already installed |
| `EmailQueueService` | Queue management + scheduled sends | `app/Services/EmailQueueService.php` |
| `EmailTemplateService` | Template rendering with `{{variable}}` substitution | `app/Services/EmailTemplateService.php` |
| `MailerService` | Symfony Mailer wrapper with `isConfigured()` guard | `app/Services/MailerService.php` |
| `InvitationRepository` | Per-member send status tracking (`sent`, `pending`, `bounced`) | `app/Repository/InvitationRepository.php` |
| `EmailQueueRepository` | Queue persistence with retry + exponential backoff | `app/Repository/EmailQueueRepository.php` |

### No new packages required
Everything needed exists. This phase is purely wiring + thin additions.

---

## Architecture Patterns

### Pattern 1: Results Email Hook (fire-and-forget after transaction)

MeetingWorkflowController::transition() already uses this pattern for SSE broadcast:

```php
// Source: app/Controller/MeetingWorkflowController.php:177-181
try {
    EventBroadcaster::meetingStatusChanged($meetingId, api_current_tenant_id(), $toStatus, $fromStatus);
} catch (Throwable $e) {
    error_log('[SSE] Broadcast failed after meeting transition: ' . $e->getMessage());
}
```

Apply the same pattern for results emails:

```php
// After the SSE broadcast block, add:
if ($toStatus === 'closed') {
    try {
        global $config;
        $emailQueue = new EmailQueueService($config ?? []);
        $emailQueue->scheduleResults($tenantId, $meetingId);
    } catch (Throwable $e) {
        error_log('[Email] Results email scheduling failed: ' . $e->getMessage());
        // Non-blocking: do NOT fail the transition response
    }
}
```

**Key rule:** The results email hook MUST NOT throw or alter the HTTP response. Session close is irreversible and must succeed even when SMTP is unconfigured.

### Pattern 2: scheduleResults() in EmailQueueService

Follow the `scheduleInvitations()` pattern exactly:

```php
// Source: app/Services/EmailQueueService.php — scheduleInvitations() pattern
public function scheduleResults(
    string $tenantId,
    string $meetingId,
    ?string $templateId = null,
): array {
    // 1. If !$mailer->isConfigured() — return early (no error, silent)
    // 2. $members = $this->memberRepo->listActiveWithEmail($tenantId)
    // 3. Render DEFAULT_RESULTS_TEMPLATE with variables per member
    //    — uses $this->templateService->getVariables() with empty token ('')
    //    — results_url = {app_url}/postsession.htmx.html?meeting_id={meeting_id}
    // 4. $this->queueRepo->enqueue() for each member
    // 5. Return ['scheduled' => N, 'skipped' => N, 'errors' => []]
}
```

The results template does NOT need a vote token — it links to the public results page. Pass `''` as token to `getVariables()`, or add a `results_url` variable override.

### Pattern 3: Reminder Send in EmailController

New `sendReminder()` method on EmailController, matching `sendBulk()` structure:

```php
public function sendReminder(): void {
    $input = api_request('POST');
    $meetingId = trim((string) ($input['meeting_id'] ?? ''));
    if ($meetingId === '' || !api_is_uuid($meetingId)) {
        api_fail('missing_meeting_id', 400);
    }
    api_guard_meeting_not_validated($meetingId);

    global $config;
    $service = new EmailQueueService($config ?? []);
    $tenantId = api_current_tenant_id();

    // scheduleInvitations with only_unsent=false (reminder = send to all)
    $result = $service->scheduleInvitations($tenantId, $meetingId, null, null, false);

    audit_log('email.reminder', 'meeting', $meetingId, [
        'scheduled' => $result['scheduled'],
    ], $meetingId);

    api_ok(['scheduled' => $result['scheduled'], 'errors' => $result['errors']]);
}
```

Note: Reminders use `DEFAULT_REMINDER_TEMPLATE` — but `scheduleInvitations()` fetches the default template via `emailTemplateRepo->findDefault($tenantId, 'invitation')`. We need either a `type='reminder'` default lookup or pass the reminder template ID explicitly. Option: add `scheduleReminders()` companion method or pass `type` parameter to `scheduleInvitations()`.

### Pattern 4: JS sendReminder() in operator-tabs.js

Follow the existing `sendInvitations()` function exactly — same modal confirm pattern, same `btnLoading` handling, same `setNotif` for results. The button `btnSendReminder` goes in the same `invitationsCard` section of tab-controle.

### Pattern 5: Invitation Send Status Badge

`loadInvitationStats()` already fetches `/api/v1/invitations_stats.php` and populates `invTotal`, `invSent`, `invOpened`, `invBounced`. To show "12/15 envoyés" on the button:

```javascript
// After loadInvitationStats() updates stats, update button badge:
const invBtn = document.getElementById('btnSendInvitations');
if (invBtn && (inv.sent > 0 || inv.total > 0)) {
    // Update or create a badge span inside the button
    let badge = invBtn.querySelector('.inv-status-badge');
    if (!badge) {
        badge = document.createElement('span');
        badge.className = 'inv-status-badge badge badge-sm ml-1';
        invBtn.appendChild(badge);
    }
    badge.textContent = `${inv.sent}/${inv.total}`;
}
```

`loadInvitationStats()` is already called at startup and after `sendInvitations()` completes. The badge update belongs inside `loadInvitationStats()`.

### Recommended Project Structure (changes only)

```
app/
├── Controller/
│   └── EmailController.php        # add sendReminder() method
├── Services/
│   ├── EmailQueueService.php      # add scheduleResults()
│   └── EmailTemplateService.php   # add DEFAULT_RESULTS_TEMPLATE constant
├── Controller/
│   └── MeetingWorkflowController.php  # add results email hook in transition()
app/routes.php                     # add email_send_reminder route
public/
├── operator.htmx.html             # add btnSendReminder in invitationsCard
└── assets/js/pages/
    └── operator-tabs.js           # add sendReminder() + loadInvitationStats() badge update
tests/Unit/
    └── EmailControllerTest.php    # add sendReminder() tests
```

### Anti-Patterns to Avoid

- **Blocking the close transition on SMTP failure:** Results email hook MUST be inside a try/catch and non-blocking. Session close is irreversible data — never fail it due to email issues.
- **Using invitation `type` column for results:** Results emails are not invitations. Do NOT reuse the invitations table for results tracking. Queue them in `email_queue` without an `invitation_id` link.
- **Sending results emails synchronously in the HTTP request:** Use `queueRepo->enqueue()` (scheduleResults via EmailQueueService), not direct `mailer->send()`. The queue is processed by cron or the existing `processQueue()`.
- **Calling `api_guard_meeting_not_validated()` in the results hook:** The hook fires on close, before validation. This guard is for operator-facing send actions, not the auto-hook.

---

## Don't Hand-Roll

| Problem | Don't Build | Use Instead | Why |
|---------|-------------|-------------|-----|
| SMTP sending | Custom socket/curl SMTP | `MailerService::send()` | Handles STARTTLS, AUTH, MIME — already in production |
| Template variable substitution | Custom regex/sprintf | `EmailTemplateService::render()` | Already handles all 16 template variables |
| Per-member send status | Custom table | `InvitationRepository::upsertBulk()` + `markSent()` | Full status machine: pending/sent/bounced/opened/accepted |
| Queue with retry | Custom retry loop | `EmailQueueRepository::markFailed()` | Exponential backoff (5min * 2^retry_count) already implemented |
| Stuck-processing recovery | Cron cleanup | `queueRepo->resetStuckProcessing(30)` | Already implemented, called at start of processQueue() |

---

## Common Pitfalls

### Pitfall 1: DEFAULT_RESULTS_TEMPLATE needs a results_url variable
**What goes wrong:** `getVariables()` generates `{{vote_url}}` from a token. Results emails need `{{results_url}}` pointing to `postsession.htmx.html?meeting_id=...`.
**How to avoid:** Either (a) add `{{results_url}}` to `AVAILABLE_VARIABLES` and `getVariables()` with a `$meetingId` override, or (b) pass `meeting_id` to a `getResultsVariables()` helper. Option (a) is simpler — add the variable unconditionally with empty value for non-results uses.

### Pitfall 2: scheduleInvitations() uses type 'invitation' for template lookup
**What goes wrong:** `emailTemplateRepo->findDefault($tenantId, 'invitation')` returns the invitation template. Calling it for reminders returns the wrong template.
**How to avoid:** Add a `scheduleReminders()` method (or pass a `$type = 'invitation'` parameter) that calls `findDefault($tenantId, 'reminder')` instead, falling back to `DEFAULT_REMINDER_TEMPLATE`.

### Pitfall 3: Results emails for members without email addresses
**What goes wrong:** `listActiveWithEmail()` only returns members with non-null emails — this is correct. But if called inside the close-transition HTTP request with many members, it could be slow.
**How to avoid:** Keep `scheduleResults()` in the queue pathway (enqueue only, no SMTP call in the HTTP request). The queue worker sends asynchronously. The 50ms queue insertion cost per member is acceptable.

### Pitfall 4: Double-triggering results emails on retry transitions
**What goes wrong:** If an operator somehow triggers close twice (unlikely but possible with concurrent requests), results emails would queue twice.
**How to avoid:** In `scheduleResults()`, check `queueRepo->countByStatusForMeeting()` before enqueueing, or use a `results_sent` flag on the meeting. Simplest: log a warning and return early if results-type emails already exist for this meeting.

### Pitfall 5: `btnSendInvitations` is in `tab-controle` (setup tab), not a hub tab
**What goes wrong:** CONTEXT.md says "hub tab" but the actual button lives in `tab-controle` (the "Contrôle" tab of the setup mode). This is the session preparation view, which IS the hub concept in the UI.
**How to avoid:** Keep new buttons in the same `#invitationsCard` div inside `tab-controle`. Do not add a separate hub tab or create duplicate buttons in the live-mode tabs.

---

## Code Examples

### DEFAULT_RESULTS_TEMPLATE (new constant on EmailTemplateService)
```php
// Add to app/Services/EmailTemplateService.php
public const DEFAULT_RESULTS_TEMPLATE = <<<'HTML'
    <!doctype html>
    <html lang="fr">
    <head>
      <meta charset="utf-8">
      <title>Résultats : {{meeting_title}}</title>
    </head>
    <body style="margin:0; padding:0; background:#f3f4f6;">
      <div style="max-width:640px; margin:0 auto; padding:24px;">
        <div style="background:#ffffff; border:1px solid #e5e7eb; border-radius:16px; padding:22px;">
          <div style="font:700 18px/1.2 system-ui, sans-serif;">
            Résultats de la séance
          </div>
          <div style="margin-top:6px; color:#6b7280; font:14px/1.5 system-ui, sans-serif;">
            Séance : <strong>{{meeting_title}}</strong>
          </div>
          <div style="margin-top:14px; font:14px/1.6 system-ui, sans-serif;">
            Bonjour <strong>{{member_name}}</strong>,
          </div>
          <div style="margin-top:10px; color:#111827; font:14px/1.6 system-ui, sans-serif;">
            La séance <strong>{{meeting_title}}</strong> du <strong>{{meeting_date}}</strong>
            est maintenant clôturée. Les résultats sont disponibles.
          </div>
          <div style="margin-top:18px;">
            <a href="{{results_url}}"
               style="display:inline-block; background:#059669; color:#ffffff; text-decoration:none;
                      padding:10px 16px; border-radius:10px; font:600 14px/1 system-ui, sans-serif;">
              Voir les résultats
            </a>
          </div>
          <hr style="border:none; border-top:1px solid #e5e7eb; margin:18px 0;">
          <div style="color:#6b7280; font:12px/1.5 system-ui, sans-serif;">
            Envoyé par {{tenant_name}} — {{app_url}}
          </div>
        </div>
      </div>
    </body>
    </html>
    HTML;
```

### getVariables() extension for results_url
```php
// Add {{results_url}} to AVAILABLE_VARIABLES constant:
'{{results_url}}' => 'Lien vers les résultats de la séance',

// Add to getVariables() return array:
'{{results_url}}' => rtrim($this->appUrl, '/') . '/postsession.htmx.html?meeting_id=' . rawurlencode($meetingId),
```

### scheduleResults() skeleton
```php
public function scheduleResults(
    string $tenantId,
    string $meetingId,
    ?string $templateId = null,
): array {
    $result = ['scheduled' => 0, 'skipped' => 0, 'errors' => []];

    if (!$this->mailer->isConfigured()) {
        return $result; // silent — SMTP not configured, skip
    }

    $members = $this->memberRepo->listActiveWithEmail($tenantId);

    if (!$templateId) {
        $defaultTemplate = $this->emailTemplateRepo->findDefault($tenantId, 'results');
        $templateId = $defaultTemplate['id'] ?? null;
    }

    foreach ($members as $member) {
        $memberId = (string) $member['id'];
        $email = trim((string) ($member['email'] ?? ''));
        if (isset($member['tenant_id']) && (string) $member['tenant_id'] !== $tenantId) {
            $result['skipped']++; continue;
        }
        if ($email === '') { $result['skipped']++; continue; }

        if ($templateId) {
            $rendered = $this->templateService->renderTemplate(
                $tenantId, $templateId, $meetingId, $memberId, '',
            );
            $subject = $rendered['ok'] ? $rendered['subject'] : 'Résultats de la séance';
            $bodyHtml = $rendered['ok'] ? $rendered['body_html'] : '';
        } else {
            $variables = $this->templateService->getVariables($tenantId, $meetingId, $memberId, '');
            $subject = $this->templateService->render('Résultats de la séance - {{meeting_title}}', $variables);
            $bodyHtml = $this->templateService->render(EmailTemplateService::DEFAULT_RESULTS_TEMPLATE, $variables);
        }

        $queued = $this->queueRepo->enqueue(
            $tenantId, $email, $subject, $bodyHtml, null, null,
            $meetingId, $memberId, null, $templateId, $member['full_name'] ?? null,
        );

        if ($queued) {
            $this->eventRepo->logEvent($tenantId, 'queued', null, $queued['id']);
            $result['scheduled']++;
        } else {
            $result['errors'][] = ['member_id' => $memberId, 'error' => 'queue_insert_failed'];
        }
    }

    return $result;
}
```

### Reminder button in operator.htmx.html (inside #invitationsCard)
```html
<!-- Add after the existing btnSendInvitations row, before invitationsOptions -->
<button class="btn btn-sm btn-secondary flex-1" id="btnSendReminder">
  <svg class="icon icon-text" aria-hidden="true"><use href="/assets/icons.svg#icon-bell"></use></svg>
  Envoyer un rappel
</button>
```

### New route in routes.php
```php
// Add to ── Email ── section:
$router->mapAny("{$prefix}/invitations_send_reminder", EmailController::class, 'sendReminder', $op);
```

### JS sendReminder() in operator-tabs.js
```javascript
async function sendReminder() {
  if (!currentMeetingId) { setNotif('error', 'Aucune séance sélectionnée'); return; }
  const membersWithEmail = membersCache.filter(m => m.email).length;
  if (membersWithEmail === 0) {
    setNotif('error', 'Aucun membre n\'a d\'adresse email.');
    return;
  }
  const confirmed = await O.confirmModal({
    title: 'Envoyer un rappel',
    body: `<p>${membersWithEmail} membre${membersWithEmail > 1 ? 's' : ''} recevront un rappel avec la date, le lieu et un lien vers le hub.</p>`,
    confirmText: 'Envoyer le rappel',
    confirmClass: 'btn-warning'
  });
  if (!confirmed) return;
  const btn = document.getElementById('btnSendReminder');
  Shared.btnLoading(btn, true);
  try {
    const { body } = await api('/api/v1/invitations_send_reminder.php', {
      meeting_id: currentMeetingId
    });
    if (body?.ok) {
      const scheduled = body.data?.scheduled || 0;
      setNotif('success', `${scheduled} rappel${scheduled > 1 ? 's' : ''} envoyé${scheduled > 1 ? 's' : ''}`);
    } else {
      const errMsg = body?.error || 'Erreur envoi rappel';
      if (errMsg === 'smtp_not_configured') {
        setNotif('error', 'Le serveur SMTP n\'est pas configuré.');
      } else {
        setNotif('error', errMsg);
      }
    }
  } catch (err) {
    setNotif('error', err.message);
  } finally {
    Shared.btnLoading(btn, false);
  }
}
document.getElementById('btnSendReminder')?.addEventListener('click', sendReminder);
```

---

## State of the Art

| Old Approach | Current Approach | Notes |
|--------------|------------------|-------|
| Hand-rolled SMTP socket | Symfony Mailer 8.x via MailerService | Already migrated in Phase 62 |
| Hardcoded templates | DB-stored customizable templates with fallback to DEFAULT_* constants | Phase 62 complete |
| No queue | EmailQueueRepository with retry, backoff, stuck-reset | Exists but not fully hooked to UI |

---

## Open Questions

1. **Should `scheduleReminders()` be a dedicated method or reuse `scheduleInvitations()` with a type parameter?**
   - What we know: `scheduleInvitations()` fetches `findDefault($tenantId, 'invitation')`. Reminders need `type='reminder'`.
   - Recommendation: Add `scheduleReminders()` as a separate method (10 lines, mostly copy of scheduleInvitations). Avoids a conditional parameter that would make the existing method harder to test.

2. **Should `{{results_url}}` be added to AVAILABLE_VARIABLES or stay internal to results rendering?**
   - What we know: All 16 current variables are in AVAILABLE_VARIABLES and are available in the template editor UI.
   - Recommendation: Add it to AVAILABLE_VARIABLES so template editors can use it. It will resolve to empty string for non-results contexts (invitation/reminder templates), which is acceptable.

3. **Should the operator see a confirmation after results emails are queued?**
   - What we know: The close transition response returns immediately. The results emails are async.
   - Recommendation: No extra confirmation needed — the close toast ("Séance clôturée avec succès") is sufficient. A follow-up toast for email queuing is noise.

---

## Validation Architecture

### Test Framework
| Property | Value |
|----------|-------|
| Framework | PHPUnit 10.5 |
| Config file | `phpunit.xml` |
| Quick run command | `./vendor/bin/phpunit tests/Unit/EmailControllerTest.php` |
| Full suite command | `./vendor/bin/phpunit` |

### Phase Requirements → Test Map
| Req ID | Behavior | Test Type | Automated Command | File Exists? |
|--------|----------|-----------|-------------------|-------------|
| EMAIL-01 | sendBulk() sends invitation emails with vote token links | unit | `./vendor/bin/phpunit tests/Unit/EmailControllerTest.php` | Yes |
| EMAIL-01 | Send status badge shows correct sent/total counts | source-verification | `./vendor/bin/phpunit tests/Unit/EmailControllerTest.php` | Yes (extend) |
| EMAIL-02 | sendReminder() method exists and requires meeting_id | unit | `./vendor/bin/phpunit tests/Unit/EmailControllerTest.php` | Yes (extend) |
| EMAIL-02 | sendReminder() audits email.reminder event | unit | `./vendor/bin/phpunit tests/Unit/EmailControllerTest.php` | Yes (extend) |
| EMAIL-02 | EmailQueueService::scheduleReminders() enqueues per active-with-email member | unit | `./vendor/bin/phpunit tests/Unit/EmailQueueServiceTest.php` | Yes (extend) |
| EMAIL-03 | EmailQueueService::scheduleResults() exists and is called from transition() | source-verification | `./vendor/bin/phpunit tests/Unit/MeetingWorkflowControllerTest.php` | Yes (extend) |
| EMAIL-03 | scheduleResults() is non-blocking (transition succeeds even if SMTP unconfigured) | unit | `./vendor/bin/phpunit tests/Unit/MeetingWorkflowControllerTest.php` | Yes (extend) |
| EMAIL-03 | DEFAULT_RESULTS_TEMPLATE contains {{results_url}} and {{member_name}} | unit | `./vendor/bin/phpunit tests/Unit/EmailTemplateServiceTest.php` | Yes (extend) |

### Sampling Rate
- **Per task commit:** `./vendor/bin/phpunit tests/Unit/EmailControllerTest.php tests/Unit/EmailQueueServiceTest.php`
- **Per wave merge:** `./vendor/bin/phpunit`
- **Phase gate:** Full suite green before `/gsd:verify-work`

### Wave 0 Gaps
None — existing test infrastructure covers all phase requirements. Test files exist and use `ControllerTestCase` pattern with injectable mock repositories. New test methods extend existing test classes.

---

## Sources

### Primary (HIGH confidence)
- Full read of `app/Controller/EmailController.php` — sendBulk(), schedule(), preview() methods, existing routes
- Full read of `app/Services/EmailQueueService.php` — scheduleInvitations(), sendInvitationsNow(), processQueue()
- Full read of `app/Services/EmailTemplateService.php` — DEFAULT_INVITATION_TEMPLATE, DEFAULT_REMINDER_TEMPLATE, getVariables(), renderTemplate()
- Full read of `app/Controller/MeetingWorkflowController.php` — transition() method, EventBroadcaster hook pattern
- Full read of `app/Repository/InvitationRepository.php` — upsertBulk(), getStatsForMeeting()
- Full read of `app/Repository/EmailQueueRepository.php` — enqueue(), fetchPendingBatch(), markFailed() with exponential backoff
- Full read of `public/operator.htmx.html` lines 680-800 — tab-controle HTML, invitationsCard, btnSendInvitations location
- Full read of `public/assets/js/pages/operator-tabs.js` lines 2800-2970 — sendInvitations(), loadInvitationStats(), createModal pattern
- Full read of `public/assets/js/pages/operator-motions.js` lines 1219-1331 — closeSession(), API call to meeting_transition, success handler
- Full read of `app/routes.php` — email route names: invitations_send_bulk, invitations_schedule
- Full read of `tests/Unit/EmailControllerTest.php` — existing test patterns, ControllerTestCase usage

### Secondary (MEDIUM confidence)
- `.planning/phases/63-email-sending-workflows/63-CONTEXT.md` — locked decisions, integration points
- `.planning/REQUIREMENTS.md` — EMAIL-01, EMAIL-02, EMAIL-03 requirement text
- `.planning/STATE.md` — confirmed Phase 62 completion, accumulated context

## Metadata

**Confidence breakdown:**
- Backend service layer: HIGH — all files read, signatures verified
- Frontend wiring: HIGH — operator-tabs.js and HTML fully read, existing patterns clear
- Test approach: HIGH — ControllerTestCase pattern verified, existing Email*Test files read
- Results email template: HIGH — DEFAULT_INVITATION_TEMPLATE structure used as blueprint

**Research date:** 2026-04-01
**Valid until:** 2026-05-01 (stable vanilla PHP/JS stack, no fast-moving dependencies)
