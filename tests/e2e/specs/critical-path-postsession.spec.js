// @ts-check
const { test, expect } = require('@playwright/test');
const { loginAsOperator } = require('../helpers');

/**
 * E2E-POSTSESSION: Post-session page critical path
 *
 * Asserts primary postsession interactions produce real observable results:
 *   1. Page mount + stepper present (4 segments, step 1 active)
 *   2. Panel 1 (Vérification) visible initially
 *   3. Footer nav buttons wired (Suivant visible, Précédent hidden)
 *   4. Step navigation — programmatic advance to panel 3
 *   5. Panel 3 — Signataire inputs present and readonly
 *   6. Panel 3 — Observations/Réserves textareas are editable
 *   7. Panel 3 — eIDAS chip selector toggles
 *   8. Panel 3 — Generate/Export PDF buttons present
 *   9. Panel 4 — export anchors + send-to customisation
 *  10. Width — no horizontal overflow
 *
 * Hybrid auth: cookie injection via loginAsOperator (no rate limit hit).
 * Re-runnable: read-only assertions only — no DB writes.
 * Meeting context: fetched from API via addInitScript; test skips if no meeting available.
 * Tagged @critical-path for `--grep="@critical-path"` filtering.
 *
 * NOTE: #btnArchive and #btnSendReport are intentionally NOT clicked (mutative).
 */

test.describe('E2E-POSTSESSION Post-session page critical path', () => {

  test('postsession: stepper + panels + signataires + chips + preview + exports @critical-path', async ({ page }) => {
    test.setTimeout(120000);

    // ── Auth ──────────────────────────────────────────────────────────────────
    await loginAsOperator(page);

    // ── Meeting context setup ─────────────────────────────────────────────────
    // Fetch the first available meeting from the API so the page has context.
    // The meeting-context.js service reads from localStorage key 'meeting_id'.
    let meetingId = null;
    try {
      const resp = await page.request.get('/api/v1/meetings.php');
      if (resp.ok()) {
        const body = await resp.json();
        const meetings = body?.data?.items || body?.data || [];
        if (Array.isArray(meetings) && meetings.length > 0) {
          meetingId = meetings[0].id || meetings[0].meeting_id || null;
        }
      }
    } catch (_e) {
      // API unavailable — will skip below
    }

    if (!meetingId) {
      test.skip(true, 'No meeting available in test environment — skipping postsession spec');
      return;
    }

    // Inject meeting_id into sessionStorage before the page loads.
    // MeetingContext service reads from sessionStorage (key: 'meeting_id').
    await page.addInitScript((id) => {
      sessionStorage.setItem('meeting_id', id);
    }, meetingId);

    // ── Navigate ──────────────────────────────────────────────────────────────
    await page.goto('/postsession.htmx.html', { waitUntil: 'domcontentloaded' });

    // ── Interaction 1: Page mount + stepper present ───────────────────────────
    const stepper = page.locator('#stepper');
    await expect(stepper).toBeVisible({ timeout: 10000 });

    // All 4 stepper segments exist
    await expect(page.locator('.ps-seg[data-step="1"]')).toBeVisible();
    await expect(page.locator('.ps-seg[data-step="2"]')).toBeVisible();
    await expect(page.locator('.ps-seg[data-step="3"]')).toBeVisible();
    await expect(page.locator('.ps-seg[data-step="4"]')).toBeVisible();

    // Step 1 is active initially
    await expect(page.locator('.ps-seg[data-step="1"]')).toHaveClass(/active/, { timeout: 5000 });

    // Step counter mentions "1"
    const stepCounter = page.locator('#psStepCounter');
    await expect(stepCounter).toBeVisible();
    const counterText = await stepCounter.textContent();
    expect(counterText).toMatch(/1/);

    // ── Interaction 2: Panel 1 visible initially ──────────────────────────────
    await expect(page.locator('#panel-1')).toBeVisible({ timeout: 5000 });

    // Panels 2-4 must be hidden
    await expect(page.locator('#panel-2')).toBeHidden();
    await expect(page.locator('#panel-3')).toBeHidden();
    await expect(page.locator('#panel-4')).toBeHidden();

    // Result cards container exists in panel-1
    await expect(page.locator('#resultCardsContainer')).toBeAttached();

    // ── Interaction 3: Footer nav buttons wired ───────────────────────────────
    // Suivant is visible (may be disabled, but present)
    await expect(page.locator('#btnSuivant')).toBeVisible();

    // Précédent has hidden attribute at step 1
    await expect(page.locator('#btnPrecedent')).toHaveAttribute('hidden', { timeout: 5000 });

    // ── Interaction 4: Programmatically advance to panel 3 ───────────────────
    // Show panel 3 by programmatic DOM manipulation + wait for paint.
    await page.evaluate(() => {
      document.querySelectorAll('.ps-panel').forEach((p) => { p.hidden = true; });
      const panel3 = document.getElementById('panel-3');
      if (panel3) panel3.hidden = false;
    });
    // Brief wait to ensure DOM update is painted before assertions.
    await page.waitForTimeout(300);

    await expect(page.locator('#panel-3')).toBeVisible({ timeout: 5000 });
    await expect(page.locator('#panel-1')).toBeHidden();

    // ── Interaction 5: Signataire inputs present and readonly ─────────────────
    const sigPresident  = page.locator('#sigPresident');
    const sigSecretary  = page.locator('#sigSecretary');
    const sigScrutateur1 = page.locator('#sigScrutateur1');
    const sigScrutateur2 = page.locator('#sigScrutateur2');

    await expect(sigPresident).toBeAttached();
    await expect(sigSecretary).toBeAttached();
    await expect(sigScrutateur1).toBeAttached();
    await expect(sigScrutateur2).toBeAttached();

    // All four inputs have the readonly attribute
    await expect(sigPresident).toHaveAttribute('readonly');
    await expect(sigSecretary).toHaveAttribute('readonly');
    await expect(sigScrutateur1).toHaveAttribute('readonly');
    await expect(sigScrutateur2).toHaveAttribute('readonly');

    // ── Interaction 6: Observations/Réserves textareas are editable ───────────
    const obsTextarea = page.locator('#pvObservations');
    const resTextarea = page.locator('#pvReserves');

    await expect(obsTextarea).toBeVisible();
    await expect(resTextarea).toBeVisible();

    await obsTextarea.fill('Test observation');
    await expect(obsTextarea).toHaveValue('Test observation');

    await resTextarea.fill('Test réserve');
    await expect(resTextarea).toHaveValue('Test réserve');

    // Clear both fields
    await obsTextarea.fill('');
    await resTextarea.fill('');

    // ── Interaction 7: eIDAS chip selector toggles ────────────────────────────
    const chipAdvanced   = page.locator('.chip[data-eidas="advanced"]');
    const chipQualified  = page.locator('.chip[data-eidas="qualified"]');
    const chipManuscript = page.locator('.chip[data-eidas="manuscript"]');

    // Advanced chip is active by default (static HTML)
    await expect(chipAdvanced).toHaveClass(/active/, { timeout: 5000 });

    // LOOSE-02: natural Playwright clicks now that chip delegation is panel-visibility
    // independent (document-level handler in postsession.js).
    await page.locator('.chip[data-eidas="qualified"]').click();
    await expect(chipQualified).toHaveClass(/active/, { timeout: 5000 });
    await expect(chipAdvanced).not.toHaveClass(/active/, { timeout: 5000 });

    await page.locator('.chip[data-eidas="manuscript"]').click();
    await expect(chipManuscript).toHaveClass(/active/, { timeout: 5000 });
    await expect(chipQualified).not.toHaveClass(/active/, { timeout: 5000 });

    await page.locator('.chip[data-eidas="advanced"]').click();
    await expect(chipAdvanced).toHaveClass(/active/, { timeout: 5000 });

    // ── Interaction 8: Generate/Export PDF buttons present ───────────────────
    await expect(page.locator('#btnGenerateReport')).toBeVisible();
    await expect(page.locator('#btnExportPDF')).toBeAttached();
    // Do NOT click btnGenerateReport — it triggers API/PDF generation (mutative)

    // ── Interaction 9: Panel 4 — exports + send-to customisation ─────────────
    await page.evaluate(() => {
      document.querySelectorAll('.ps-panel').forEach((p) => { p.hidden = true; });
      const panel4 = document.getElementById('panel-4');
      if (panel4) panel4.hidden = false;
    });

    await expect(page.locator('#panel-4')).toBeVisible({ timeout: 5000 });

    // All 6 export anchors exist
    await expect(page.locator('#exportPvPdf')).toBeAttached();
    await expect(page.locator('#exportEmargement')).toBeAttached();
    await expect(page.locator('#exportAttendanceCsv')).toBeAttached();
    await expect(page.locator('#exportVotesCsv')).toBeAttached();
    await expect(page.locator('#exportResultsCsv')).toBeAttached();
    await expect(page.locator('#exportAuditCsv')).toBeAttached();

    // sendTo select has at least 3 options
    const sendToSelect = page.locator('#sendTo');
    await expect(sendToSelect).toBeVisible();
    const optionCount = await sendToSelect.locator('option').count();
    expect(optionCount).toBeGreaterThanOrEqual(3);

    // Selecting "custom" reveals #customEmailGroup
    await page.selectOption('#sendTo', 'custom');
    await expect(page.locator('#customEmailGroup')).not.toHaveAttribute('hidden', { timeout: 5000 });

    // Reset to "all"
    await page.selectOption('#sendTo', 'all');

    // Confirm mutative buttons are present (but do NOT click)
    await expect(page.locator('#btnSendReport')).toBeVisible();
    await expect(page.locator('#btnArchive')).toBeVisible();

    // ── Interaction 10: Width — no horizontal overflow ────────────────────────
    // Return to panel 1 before width check
    await page.evaluate(() => {
      document.querySelectorAll('.ps-panel').forEach((p) => { p.hidden = true; });
      const panel1 = document.getElementById('panel-1');
      if (panel1) panel1.hidden = false;
    });

    const noHorizontalOverflow = await page.evaluate(() => {
      return document.documentElement.scrollWidth <= document.documentElement.clientWidth + 1;
    });
    expect(noHorizontalOverflow).toBe(true);
  });

});
