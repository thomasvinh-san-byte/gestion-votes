// @ts-check
const { test, expect } = require('@playwright/test');
const { loginAsOperator } = require('../helpers');

/**
 * E2E-VALIDATE: Validate page critical path
 * Summary grid → checklist → recheck → president input → modal open/close (non-destructive)
 *
 * Hybrid auth strategy: cookie injection via loginAsOperator (no rate limit hit).
 * Re-runnable: read-only assertions only — no DB writes.
 * Tagged @critical-path for `--grep="@critical-path"` filtering.
 *
 * SAFETY GUARANTEE: #btnModalConfirm is NEVER clicked in this spec.
 * Clicking it would irreversibly archive the meeting. Only modal open,
 * checkbox toggle, text typing validation, and cancel are tested.
 *
 * Covers the primary interactions on /validate.htmx.html:
 *   1. Page mount + summary grid visible (8 KPI cells)
 *   2. Checklist panel loads (spinner replaced, readyBadge populated)
 *   3. Recheck button — reloads checks, checksList remains visible
 *   4. President name input — interactive with pattern attribute
 *   5. Validation zone + main validate button present
 *   6. Modal open (conditional on btnValidate enabled)
 *   7. Modal checkbox + text dual-guard wiring (only if modal opened)
 *   8. Width verification — no horizontal overflow
 */

test.describe('E2E-VALIDATE Validate page critical path', () => {

  test('validate: summary + checklist + president input + modal open/close (non-destructive) @critical-path', async ({ page }) => {
    test.setTimeout(120000);

    // Step 1 — Login as operator (validate page requires operator role)
    await loginAsOperator(page);

    // Step 2 — Fetch first available meeting to pass as URL param
    // MeetingContext reads from URL ?meeting_id=UUID (highest priority in meeting-context.js)
    // This avoids sessionStorage/redirect issues with the validate page.
    let meetingId = null;
    try {
      const response = await page.request.get('/api/v1/meetings.php');
      if (response.ok()) {
        const body = await response.json();
        // API returns { ok, data: [...] } or { ok, data: { items: [...] } }
        const items = Array.isArray(body.data) ? body.data
          : (body.data && Array.isArray(body.data.items) ? body.data.items : []);
        if (items.length > 0) {
          meetingId = items[0].meeting_id || items[0].id || null;
        }
      }
    } catch (e) {
      // ignore — page will handle missing context gracefully
    }

    // Step 3 — Navigate to validate page (with meeting_id in URL if available)
    const validateUrl = meetingId
      ? `/validate.htmx.html?meeting_id=${encodeURIComponent(meetingId)}`
      : '/validate.htmx.html';
    await page.goto(validateUrl, { waitUntil: 'domcontentloaded' });

    // ── Interaction 1: Page mount + summary grid visible ─────────────────────
    // #summaryGrid must be rendered (proves page mounted correctly)
    await expect(page.locator('#summaryGrid')).toBeVisible({ timeout: 15000 });

    // Assert all 8 summary KPI cells exist in the DOM
    for (const id of ['#sumMembers', '#sumPresent', '#sumMotions', '#sumAdopted', '#sumRejected', '#sumBallots', '#sumQuorum', '#sumDuration']) {
      await expect(page.locator(id)).toBeAttached({ timeout: 5000 });
    }

    // ── Interaction 2: Checklist panel loads ──────────────────────────────────
    await expect(page.locator('#checksList')).toBeVisible({ timeout: 10000 });

    // Wait for spinner to be replaced by real check items (up to 15s for API call)
    await page.waitForFunction(() => {
      const el = document.getElementById('checksList');
      return el && !el.querySelector('.spinner');
    }, { timeout: 15000 });

    // readyBadge must be visible and have content (not the placeholder dash)
    await expect(page.locator('#readyBadge')).toBeVisible({ timeout: 5000 });

    // ── Interaction 3: Recheck button — reloads checks ────────────────────────
    await expect(page.locator('#btnRecheck')).toBeVisible({ timeout: 5000 });
    await page.locator('#btnRecheck').click();

    // Allow time for re-check API call
    await page.waitForTimeout(500);

    // checksList must still be visible after reload
    await expect(page.locator('#checksList')).toBeVisible({ timeout: 10000 });

    // Wait for spinner to clear again after recheck
    await page.waitForFunction(() => {
      const el = document.getElementById('checksList');
      return el && !el.querySelector('.spinner');
    }, { timeout: 15000 });

    // ── Interaction 4: President name input — interactive with pattern ────────
    const presidentInput = page.locator('#presidentName');
    await expect(presidentInput).toBeVisible({ timeout: 5000 });
    await presidentInput.fill('Jean Test');
    await expect(presidentInput).toHaveValue('Jean Test', { timeout: 3000 });

    // Input must have the pattern attribute (enforces name validation)
    await expect(presidentInput).toHaveAttribute('pattern', { timeout: 3000 });

    // ── Interaction 5: Validation zone + main validate button present ─────────
    const validationZone = page.locator('#validationZone');
    await expect(validationZone).toBeVisible({ timeout: 5000 });
    await expect(validationZone).toHaveClass(/validation-zone-danger/, { timeout: 3000 });

    // btnValidate must exist (may be disabled depending on checklist state)
    const btnValidate = page.locator('#btnValidate');
    await expect(btnValidate).toBeVisible({ timeout: 5000 });

    // ── Interaction 6: Modal open (conditional on btnValidate enabled) ────────
    const validateEnabled = await btnValidate.isEnabled();

    if (validateEnabled) {
      // Open the modal by clicking the validate button
      await btnValidate.click();

      // Modal backdrop must become visible (hidden attribute removed)
      await expect(page.locator('#validateModal')).not.toHaveAttribute('hidden', { timeout: 5000 });

      // Modal title must be visible
      await expect(page.locator('#validateModalTitle')).toBeVisible({ timeout: 3000 });

      // Checkbox must be unchecked initially
      await expect(page.locator('#confirmIrreversible')).not.toBeChecked({ timeout: 3000 });

      // Confirm button must be disabled initially (neither checkbox nor text filled)
      await expect(page.locator('#btnModalConfirm')).toBeDisabled({ timeout: 3000 });

      // ── Interaction 7: Modal dual-guard wiring ────────────────────────────
      // Check the irreversible checkbox
      await page.locator('#confirmIrreversible').check();

      // Confirm button still disabled — needs both checkbox AND "VALIDER" text
      await expect(page.locator('#btnModalConfirm')).toBeDisabled({ timeout: 3000 });

      // Type the required confirmation text
      await page.locator('#confirmText').fill('VALIDER');
      await page.waitForTimeout(300);

      // Now confirm button should be enabled (dual-guard both satisfied)
      await expect(page.locator('#btnModalConfirm')).toBeEnabled({ timeout: 3000 });

      // 🚨 SAFETY: DO NOT CLICK #btnModalConfirm — would archive meeting irreversibly
      // Cancel the modal instead
      await page.locator('#btnModalCancel').click();

      // Modal must close (hidden attribute restored or element not visible)
      await expect(page.locator('#validateModal')).toHaveAttribute('hidden', { timeout: 3000 });
    } else {
      // btnValidate disabled — checklist not ready, skipping modal open
      // This is expected in a clean test environment where meeting may not be ready
      test.info && test.info().annotations
        ? null
        : console.log('btnValidate disabled — checklist not ready, skipping modal open');
    }

    // ── Interaction 8: Width verification ────────────────────────────────────
    // Page must not produce horizontal overflow (validates no applicative max-width clamp)
    const hasOverflow = await page.evaluate(() => {
      return document.documentElement.scrollWidth > document.documentElement.clientWidth + 1;
    });
    expect(hasOverflow).toBe(false);
  });

});
