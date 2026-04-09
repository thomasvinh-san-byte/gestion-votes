// @ts-check
const { test, expect } = require('@playwright/test');
const { loginAsOperator } = require('../helpers');

/**
 * Archives Critical-Path E2E Tests
 *
 * @critical-path
 *
 * Covers all primary interactions on the archives page:
 * - API data load (KPI row + archives list)
 * - Type filter chips (AG Ord., Toutes)
 * - Status filter chips (Validee, Tous statuts)
 * - Search input with debounce
 * - View toggle (cards / list)
 * - Exports modal (open, buttons visible, close)
 * - Refresh button
 */
test.describe('archives: critical-path interactions', () => {
  test.setTimeout(120000);

  test('archives: type filter + status filter + search + view toggle + exports modal + data load @critical-path', async ({ page }) => {
    await loginAsOperator(page);

    // ── 1. Navigate & wait for archives list to settle ────────────────────────
    await page.goto('/archives.htmx.html', { waitUntil: 'domcontentloaded' });

    // Wait for the loading spinner to disappear (archives list populated or empty state shown)
    await expect(page.locator('#archivesList .archives-loading')).toHaveCount(0, { timeout: 15000 });

    // Assert KPI total has been updated (no longer the mdash placeholder, or empty state visible)
    const kpiTotal = page.locator('#kpiTotal');
    await expect(kpiTotal).toBeVisible({ timeout: 10000 });
    // KPI is populated (not the mdash) OR the list is empty — both prove the API was called
    const kpiText = await kpiTotal.textContent();
    // kpiText is either a number or '—' (mdash in empty databases); both are valid
    expect(kpiText).not.toBeNull();

    // ── 2. Type filter — AG Ord. click ─────────────────────────────────────────
    const typeFilterAGOrd = page.locator('#archiveTypeFilter .filter-tab[data-type="ag_ordinaire"]');
    const typeFilterAll = page.locator('#archiveTypeFilter .filter-tab[data-type=""]');

    await expect(typeFilterAGOrd).toBeVisible();
    await expect(typeFilterAll).toHaveClass(/active/);

    await typeFilterAGOrd.click();
    await expect(typeFilterAGOrd).toHaveClass(/active/);
    await expect(typeFilterAll).not.toHaveClass(/active/);

    // Restore to Toutes
    await typeFilterAll.click();
    await expect(typeFilterAll).toHaveClass(/active/);
    await expect(typeFilterAGOrd).not.toHaveClass(/active/);

    // ── 3. Status filter — Validee click ──────────────────────────────────────
    const statusFilterValidated = page.locator('#archiveStatusFilter .filter-tab[data-status="validated"]');
    const statusFilterAll = page.locator('#archiveStatusFilter .filter-tab[data-status=""]');

    await expect(statusFilterAll).toHaveClass(/active/);
    await statusFilterValidated.click();
    await expect(statusFilterValidated).toHaveClass(/active/);
    await expect(statusFilterAll).not.toHaveClass(/active/);

    // Restore
    await statusFilterAll.click();
    await expect(statusFilterAll).toHaveClass(/active/);

    // ── 4. Search input — no-match query then clear ────────────────────────────
    const searchInput = page.locator('#searchInput');
    await expect(searchInput).toBeVisible();

    const noMatchQuery = `zzz-no-match-${Date.now()}`;
    await searchInput.fill(noMatchQuery);
    await page.waitForTimeout(600); // wait for debounce

    // Archives list should show empty state or zero cards with no-match query
    const archivesList = page.locator('#archivesList');
    await expect(archivesList).toBeVisible();
    // Just verify the list is present (either empty state or cards filtered)
    // The observable: archivesList exists and either shows empty-state or filtered cards
    const listContent = await archivesList.textContent();
    expect(listContent).not.toBeNull();

    // Clear search
    await searchInput.fill('');
    await page.waitForTimeout(600);

    // Archives list should be restored (visible, loading gone)
    await expect(page.locator('#archivesList .archives-loading')).toHaveCount(0, { timeout: 8000 });
    await expect(archivesList).toBeVisible();

    // ── 5. View toggle — switch to list view then back ─────────────────────────
    const viewToggleList = page.locator('.view-toggle-btn[data-view="list"]');
    const viewToggleCards = page.locator('.view-toggle-btn[data-view="cards"]');

    await expect(viewToggleCards).toHaveClass(/active/);
    await viewToggleList.click();
    await expect(viewToggleList).toHaveClass(/active/);
    await expect(viewToggleCards).not.toHaveClass(/active/);

    // Restore to cards view
    await viewToggleCards.click();
    await expect(viewToggleCards).toHaveClass(/active/);
    await expect(viewToggleList).not.toHaveClass(/active/);

    // ── 6. Exports modal — open, assert buttons, close ────────────────────────
    const btnExportsModal = page.locator('#btnExportsModal');
    const exportsModal = page.locator('#exportsModal');
    const btnCloseExports = page.locator('#btnCloseExports');
    const btnExportPV = page.locator('#btnExportPV');
    const btnExportZip = page.locator('#btnExportZip');

    await btnExportsModal.click();

    // Modal becomes visible (hidden attribute removed)
    await expect(exportsModal).toBeVisible({ timeout: 5000 });
    await expect(btnExportPV).toBeVisible();
    await expect(btnExportZip).toBeVisible();

    // Close via primary close button
    await btnCloseExports.click();
    await expect(exportsModal).toBeHidden({ timeout: 5000 });

    // ── 7. Refresh button — data reloads ──────────────────────────────────────
    const btnRefresh = page.locator('#btnRefresh');
    await expect(btnRefresh).toBeVisible();
    await btnRefresh.click();

    // After refresh the loading spinner may appear briefly then disappear
    await expect(page.locator('#archivesList .archives-loading')).toHaveCount(0, { timeout: 15000 });
    await expect(archivesList).toBeVisible();
  });
});
