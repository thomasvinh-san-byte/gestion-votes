// @ts-check
const { test, expect } = require('@playwright/test');
const { loginAsOperator, E2E_MEETING_ID } = require('../helpers');

/**
 * E2E v2.6 / Phase 1 / TEST-V26-02 — SSE meeting.heartbeat delivery
 *
 * Lève la dette HEARTBEAT-V25-04 : verrouille la livraison réelle du tick
 * heartbeat sur le stream SSE.
 *
 * Stratégie:
 *  - Auth opérateur via fixture cached PHPSESSID (loginAsOperator) — pas de
 *    login form, pas de risque rate-limit (10 req / 300 s).
 *  - Ouvre une EventSource sur /api/v1/events.php?meeting_id={E2E_MEETING_ID}
 *    via page.evaluate (le cookie PHPSESSID est envoyé automatiquement par
 *    le browser context).
 *  - Écoute l'event 'meeting.heartbeat' (addEventListener nommé, pas onmessage
 *    — onmessage ne capture que les events sans 'event:' field).
 *  - Attend 13 secondes : la boucle SSE serveur émet le 1er heartbeat dans
 *    la première itération (time() - $lastHeartbeat=0 >= 10 ⇒ true), donc
 *    typiquement à T+1s. Marge confortable.
 *  - Valide : ≥1 event capturé, payload contient meeting_id (string non vide)
 *    et server_time (ISO-8601 shape).
 *
 * Cadence serveur (verified from public/api/v1/events.php):
 *  - $maxDuration = 30s (le worker est tenu 30s max, puis EventSource
 *    auto-reconnecte côté client).
 *  - $heartbeatInterval = 10s.
 *  - Boucle sleep(1) entre les polls.
 *
 * Test budget : CLAUDE.md mandate max 3 Playwright executions per task.
 */

test.describe('@regression SSE meeting.heartbeat delivery (Plan 01.2)', () => {

  test('emits at least 1 meeting.heartbeat event within 13 seconds', async ({ page }) => {
    test.setTimeout(60000);

    await loginAsOperator(page);

    // Navigate to any authenticated page first to establish cookie context.
    // /operator.htmx.html is the natural landing for the operator role and
    // ensures PHPSESSID is set on the page document before the EventSource
    // is created from the same origin.
    await page.goto('/operator.htmx.html', { waitUntil: 'domcontentloaded' });

    // Open the SSE stream in the page context and capture events for 13s.
    // We use page.evaluate so the EventSource runs inside the browser with
    // the same-origin cookie automatically attached. The window.__sseCapture
    // object is populated by the listener and read back via evaluate.
    const captured = await page.evaluate(async ({ meetingId, waitMs }) => {
      return await new Promise((resolve) => {
        /** @type {{ events: Array<{name: string, data: any}>, errors: string[] }} */
        const capture = { events: [], errors: [] };

        const url = `/api/v1/events.php?meeting_id=${encodeURIComponent(meetingId)}`;
        const es = new EventSource(url, { withCredentials: true });

        es.addEventListener('meeting.heartbeat', (e) => {
          try {
            const data = JSON.parse(/** @type {MessageEvent} */ (e).data);
            capture.events.push({ name: 'meeting.heartbeat', data });
          } catch (err) {
            capture.errors.push('parse meeting.heartbeat: ' + String(err));
          }
        });

        es.addEventListener('error', () => {
          // EventSource readyState 0=CONNECTING, 1=OPEN, 2=CLOSED
          capture.errors.push('EventSource error, readyState=' + es.readyState);
        });

        setTimeout(() => {
          es.close();
          resolve(capture);
        }, waitMs);
      });
    }, { meetingId: E2E_MEETING_ID, waitMs: 13000 });

    // Assert at least one heartbeat event was captured.
    expect(
      captured.events.length,
      `Expected ≥1 meeting.heartbeat in 13s, got ${captured.events.length}. Errors: ${captured.errors.join(' | ')}`,
    ).toBeGreaterThanOrEqual(1);

    // Validate payload conformity on the first captured heartbeat.
    const first = captured.events[0];
    expect(first.name).toBe('meeting.heartbeat');
    expect(first.data, 'heartbeat payload must be an object').toBeTruthy();
    expect(typeof first.data.meeting_id, 'meeting_id must be a string').toBe('string');
    expect(first.data.meeting_id.length, 'meeting_id must be non-empty').toBeGreaterThan(0);

    // server_time must be ISO-8601 (matches PHP date('c') format).
    expect(typeof first.data.server_time, 'server_time must be a string').toBe('string');
    expect(
      first.data.server_time,
      'server_time must match ISO-8601 (date("c")) shape',
    ).toMatch(/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}[+\-]\d{2}:\d{2}$/);
  });

});
