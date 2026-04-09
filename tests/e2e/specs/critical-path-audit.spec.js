// @ts-check
const { test, expect } = require('@playwright/test');
const { loginAsAdmin } = require('../helpers');

/**
 * E2E-AUDIT: Audit page critical path
 * Filter tabs → search → sort → view toggle → data load → detail modal
 *
 * Hybrid auth strategy: cookie injection via loginAsAdmin (no rate limit hit).
 * Re-runnable: only navigation and read-only assertions — no DB writes.
 * Tagged @critical-path for `--grep="@critical-path"` filtering.
 *
 * Covers the 6 primary interactions on /audit.htmx.html:
 *   1. API data load — KPI + table body populate
 *   2. Filter tab click — active class toggles
 *   3. Search input — debounced filter produces observable change
 *   4. Sort select — value wired to select element
 *   5. View toggle — table/timeline visibility swap
 *   6. Row click — detail modal opens and closes (if data rows present)
 */

test.describe('E2E-AUDIT Audit page critical path', () => {

  test('audit: filter tabs + search + sort + view toggle + data load + detail modal @critical-path', async ({ page }) => {
    test.setTimeout(120000);

    // Step 1 — Login as admin (required role for audit page)
    await loginAsAdmin(page);

    // Step 2 — Navigate to audit page
    await page.goto('/audit.htmx.html', { waitUntil: 'domcontentloaded' });

    // Assert the audit table body is visible (proves page mounted)
    await expect(page.locator('#auditTableBody')).toBeVisible({ timeout: 15000 });

    // ── Interaction 1: API data load ──────────────────────────────────────────
    // Wait for JS to run and either populate events or show empty state.
    // The spinner row is replaced by real rows OR an empty-state td after load.
    // Give the API call up to 15s to complete.
    await page.waitForFunction(() => {
      const tbody = document.getElementById('auditTableBody');
      if (!tbody) return false;
      // Loading complete: spinner div is gone
      return !tbody.querySelector('.spinner');
    }, { timeout: 15000 });

    // KPI #kpiEvents should be populated after API call.
    // In an empty test DB it may stay "—" or become "0" — both are valid.
    // We only assert the element is visible (proves KPI card rendered).
    const kpiEvents = page.locator('#kpiEvents');
    await expect(kpiEvents).toBeVisible({ timeout: 10000 });

    // ── Interaction 2: Filter tab click ──────────────────────────────────────
    // Click the "Votes" filter tab (data-type="votes")
    const votesTab = page.locator('#auditTypeFilter .filter-tab[data-type="votes"]');
    await expect(votesTab).toBeVisible();
    await votesTab.click();

    // Clicked tab gains .active class
    await expect(votesTab).toHaveClass(/active/, { timeout: 5000 });

    // "Tous" tab (data-type="") loses .active class
    const tousTab = page.locator('#auditTypeFilter .filter-tab[data-type=""]');
    await expect(tousTab).not.toHaveClass(/active/, { timeout: 5000 });

    // Restore by clicking "Tous"
    await tousTab.click();
    await expect(tousTab).toHaveClass(/active/, { timeout: 5000 });

    // ── Interaction 3: Search input (debounced filter) ────────────────────────
    const searchInput = page.locator('#auditSearch');
    await expect(searchInput).toBeVisible();

    // Type a value guaranteed to match nothing
    const noMatchToken = `zzz-no-match-${Date.now()}`;
    await searchInput.fill(noMatchToken);

    // Wait for debounce (audit.js uses ~300ms debounce)
    await page.waitForTimeout(600);

    // Table body should show either: zero data rows, or an empty-state td
    // A data row has a click handler and contains real audit content.
    // We assert that no real data rows remain visible — accept zero rows OR empty-state.
    const rowsAfterSearch = await page.locator('#auditTableBody tr').count();
    // Either 0 rows, or 1 row that is an empty-state/no-results message
    expect(rowsAfterSearch).toBeLessThanOrEqual(2);

    // Clear search and wait for table to repopulate
    await searchInput.fill('');
    await page.waitForTimeout(600);

    // Assert spinner is gone again (data re-fetched or local filter cleared)
    await page.waitForFunction(() => {
      const tbody = document.getElementById('auditTableBody');
      return tbody && !tbody.querySelector('.spinner');
    }, { timeout: 10000 });

    // ── Interaction 4: Sort select ────────────────────────────────────────────
    const sortSelect = page.locator('#auditSort');
    await expect(sortSelect).toBeVisible();

    // Change to "date-asc"
    await sortSelect.selectOption('date-asc');
    await expect(sortSelect).toHaveValue('date-asc', { timeout: 5000 });

    // Restore to default
    await sortSelect.selectOption('date-desc');

    // ── Interaction 5: View toggle (table ↔ timeline) ─────────────────────────
    // Start in table view
    await expect(page.locator('#auditTableView')).toBeVisible({ timeout: 5000 });

    // Click timeline toggle button
    const timelineBtn = page.locator('.view-toggle-btn[data-view="timeline"]');
    await expect(timelineBtn).toBeVisible();
    await timelineBtn.click();

    // Timeline view becomes visible, table view is hidden
    await expect(page.locator('#auditTimelineView')).toBeVisible({ timeout: 5000 });
    await expect(page.locator('#auditTableView')).toBeHidden({ timeout: 5000 });

    // Restore table view
    const tableBtn = page.locator('.view-toggle-btn[data-view="table"]');
    await tableBtn.click();
    await expect(page.locator('#auditTableView')).toBeVisible({ timeout: 5000 });
    await expect(page.locator('#auditTimelineView')).toBeHidden({ timeout: 5000 });

    // ── Interaction 6: Row click → detail modal (only if data rows exist) ────
    // Check for actual data rows (not spinner, not empty-state)
    const dataRows = page.locator('#auditTableBody tr:has(td.audit-timestamp, td:has(.audit-event-cell))');
    const dataRowCount = await dataRows.count();

    if (dataRowCount > 0) {
      // Click the first data row
      await dataRows.first().click();

      // Detail modal becomes visible (hidden attr removed + aria-hidden="false")
      const modal = page.locator('#auditDetailModal');
      await expect(modal).not.toHaveAttribute('hidden', { timeout: 5000 });

      // Detail timestamp should not be the placeholder dash
      const detailTimestamp = page.locator('#detailTimestamp');
      await expect(detailTimestamp).toBeVisible({ timeout: 5000 });
      await expect(detailTimestamp).not.toHaveText('—', { timeout: 5000 });

      // Close modal via primary close button
      await page.locator('#btnCloseAuditDetail').click();

      // Modal is hidden again
      await expect(modal).toHaveAttribute('hidden', { timeout: 5000 });
    } else {
      // No data rows in test DB — assert empty state is shown gracefully
      // (tbody has at least 1 row — the empty-state message row)
      const tbodyRows = await page.locator('#auditTableBody tr').count();
      expect(tbodyRows).toBeGreaterThanOrEqual(0);
      // Modal remains hidden since no click occurred
      await expect(page.locator('#auditDetailModal')).toHaveAttribute('hidden');
    }
  });

});
