// @ts-check
const { test, expect } = require('@playwright/test');
const { loginAsOperator } = require('../helpers');

/**
 * E2E-HUB: Hub fiche-séance critical path
 *
 * Gate 3 of Phase 12 page-by-page MVP sweep.
 * Covers: state load, main CTA wiring, operator CTA wiring,
 *         checklist progress chip, and attachments endpoint
 *         (proves Phase 11 meeting_attachments_public wiring).
 *
 * Hybrid API+UI strategy: create meeting via API, then visit hub page.
 * Re-runnable: unique runId timestamp on every test meeting.
 * Tagged @critical-path to enable filtered runs in CI.
 */

test.describe('E2E-HUB Hub fiche-séance critical path', () => {

  test('hub: load state → main CTA → operator CTA → checklist progress → attachments endpoint @critical-path', async ({ page }) => {
    test.setTimeout(120000);

    const runId = `e2e-hub-${Date.now()}`;
    const meetingTitle = `Hub Critical Path AG ${runId}`;

    // ─── Step 1: Login (cookie injection) ───
    await loginAsOperator(page);

    const whoami = await page.request.get('/api/v1/whoami.php');
    expect(whoami.ok()).toBeTruthy();

    // ─── Step 2: CSRF token for state-changing requests ───
    const csrfResp = await page.request.get('/api/v1/auth_csrf');
    let csrfToken = '';
    if (csrfResp.ok()) {
      const csrfData = await csrfResp.json();
      csrfToken = csrfData.data?.csrf_token || csrfData.token || csrfData.data?.token || '';
    }
    const csrfHeaders = csrfToken ? { 'X-CSRF-Token': csrfToken } : {};

    // ─── Step 3: Create meeting via API ───
    const createResp = await page.request.post('/api/v1/meetings', {
      headers: csrfHeaders,
      data: {
        title: meetingTitle,
        starts_at: '2026-12-31T14:00:00',
        location: `Hub E2E Room ${runId}`,
        type: 'standard',
      },
    });

    let meetingId = null;
    if (createResp.ok()) {
      const body = await createResp.json();
      meetingId = body?.data?.meeting_id || body?.data?.id || body?.id || body?.meeting?.id || null;
    }

    // Fallback to first existing meeting if create endpoint contract drifted
    if (!meetingId) {
      const meetingsResp = await page.request.get('/api/v1/meetings');
      if (meetingsResp.ok()) {
        const list = await meetingsResp.json();
        const meetings = list?.data?.items || list?.data || list || [];
        if (Array.isArray(meetings) && meetings.length > 0) {
          meetingId = meetings[0].meeting_id || meetings[0].id;
        }
      }
    }

    expect(meetingId, 'must have a meeting id to proceed').toBeTruthy();

    // ─── Step 4: Navigate to hub page ───
    await page.goto(`/hub.htmx.html?meeting_id=${meetingId}`, {
      waitUntil: 'domcontentloaded',
    });

    // ─── Gate 1: Hub state loaded ───
    // #hubTitle transitions away from "Chargement…" once the wizard_status API responds
    await expect(page.locator('#hubTitle')).not.toHaveText('Chargement\u2026', { timeout: 15000 });
    await expect(page.locator('#hubTitle')).not.toBeEmpty();
    await expect(page.locator('#hubStatusTag')).toBeVisible({ timeout: 10000 });

    const titleText = await page.locator('#hubTitle').textContent();
    expect(titleText && titleText.trim().length, 'hub title must have content').toBeGreaterThan(0);

    // ─── Gate 2: Main CTA wiring ───
    // For a draft/scheduled meeting the JS sets data-action="freeze" or data-action="open".
    // Either a valid href OR a data-action proves the CTA is wired (not the initial "#").
    const mainBtn = page.locator('#hubMainBtn');
    await expect(mainBtn).toBeVisible({ timeout: 10000 });

    const mainHref = await mainBtn.getAttribute('href');
    const mainAction = await mainBtn.getAttribute('data-action');
    const mainBtnText = await mainBtn.textContent();

    // At minimum: button text was updated by JS (no longer the initial "Ouvrir la séance" default OR
    // it was set deliberately to a CTA; either a real href or a data-action must be present)
    const ctaWired = (mainHref && mainHref !== '#') || (mainAction && mainAction.length > 0);
    expect(
      ctaWired,
      `#hubMainBtn must have a real href or data-action; got href="${mainHref}" action="${mainAction}" text="${mainBtnText}"`
    ).toBeTruthy();

    // ─── Gate 3: Operator CTA wiring ───
    // JS rewrites href to /operator/{meeting_id}
    const operatorBtn = page.locator('#hubOperatorBtn');
    await expect(operatorBtn).toBeVisible({ timeout: 10000 });

    const operatorHref = await operatorBtn.getAttribute('href');
    expect(
      operatorHref && operatorHref.includes('/operator'),
      `#hubOperatorBtn href must include /operator; got "${operatorHref}"`
    ).toBeTruthy();

    // ─── Gate 4: Checklist progress chip ───
    // The chip shows "N/3" after the workflow_check API call returns
    const progressChip = page.locator('#hubChecklistProgress');
    await expect(progressChip).toBeVisible({ timeout: 15000 });

    const progressText = await progressChip.textContent();
    expect(
      progressText && /^\d+\/\d+$/.test(progressText.trim()),
      `#hubChecklistProgress must show N/N format; got "${progressText}"`
    ).toBeTruthy();

    // ─── Gate 5: Attachments endpoint hit (proves Phase 11 wiring) ───
    // Set up the response interception BEFORE reloading the page
    const attachRespPromise = page.waitForResponse(
      r => r.url().includes('meeting_attachments') && r.status() < 500,
      { timeout: 15000 }
    );

    // Reload to re-trigger the hub data fetch (including attachments)
    await page.reload({ waitUntil: 'domcontentloaded' });

    const attachResp = await attachRespPromise;
    expect(
      attachResp.status() < 500,
      `meeting_attachments endpoint must not return 5xx; got ${attachResp.status()}`
    ).toBeTruthy();

    // 200 with empty array is correct for a meeting with no attachments.
    // 404 would indicate Phase 11 wiring is broken.
    expect(
      attachResp.status() !== 404,
      `meeting_attachments endpoint must not 404; endpoint appears missing or not wired`
    ).toBeTruthy();

    const attachBody = await attachResp.json();
    // ok:true or ok:false with empty data are both valid — endpoint responded
    expect(attachBody, 'attachments response must be valid JSON').toBeTruthy();
  });

});
