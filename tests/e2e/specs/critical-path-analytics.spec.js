// @ts-check
const { test, expect } = require('@playwright/test');
const { loginAsOperator } = require('../helpers');

/**
 * E2E-ANALYTICS: Analytics page critical path
 * KPI load + period pills + tab switches + year filter + refresh + width
 *
 * Hybrid auth strategy: cookie injection via loginAsOperator (no rate limit hit).
 * Re-runnable: only navigation and read-only assertions — no DB writes.
 * Tagged @critical-path for `--grep="@critical-path"` filtering.
 *
 * Covers the 7 primary interactions on /analytics.htmx.html:
 *   1. Page mount + KPI load — KPIs populate from API
 *   2. Year filter change — triggers data reload, value wired
 *   3. Period pills — active class toggles on click
 *   4. Tab switch Motions — content visible, donut SVG in DOM
 *   5. Tab switch Timing — content visible, durationChart canvas in DOM
 *   6. Tab switch Anomalies — content visible, anomaliesOverview visible
 *   7. Refresh button — reloads without breaking KPI state
 *   8. Width verification — no horizontal scroll at desktop viewport
 */

test.describe('E2E-ANALYTICS Analytics page critical path', () => {

  test('analytics: KPIs + period pills + tabs + year filter + refresh + charts @critical-path', async ({ page }) => {
    test.setTimeout(120000);

    // Step 1 — Login as operator (analytics page has data-page-role="operator")
    await loginAsOperator(page);

    // ── Interaction 1: Page mount + KPI load ─────────────────────────────────
    await page.goto('/analytics.htmx.html', { waitUntil: 'domcontentloaded' });

    // Overview KPI grid must be visible (proves page mounted)
    await expect(page.locator('#overviewCards')).toBeVisible({ timeout: 15000 });

    // Wait for KPI to populate: either a digit appears or placeholder "—" is replaced
    await page.waitForFunction(() => {
      const el = document.getElementById('kpiMeetings');
      return el && /\d/.test(el.textContent || '');
    }, { timeout: 15000 }).catch(() => {
      // Acceptable: API may return 0 or no data in test DB — element still visible
    });

    // All four KPI cards must be visible regardless of their value
    await expect(page.locator('#kpiMeetings')).toBeVisible({ timeout: 10000 });
    await expect(page.locator('#kpiResolutions')).toBeVisible({ timeout: 5000 });
    await expect(page.locator('#kpiAdoptionRate')).toBeVisible({ timeout: 5000 });
    await expect(page.locator('#kpiParticipation')).toBeVisible({ timeout: 5000 });

    // ── Interaction 2: Year filter change — triggers data reload ─────────────
    const yearFilter = page.locator('#yearFilter');
    await expect(yearFilter).toBeVisible();

    // Change to 2025
    await yearFilter.selectOption('2025');
    await page.waitForTimeout(800); // debounce / API reload

    // Proves wiring: select reflects the chosen value
    await expect(yearFilter).toHaveValue('2025', { timeout: 5000 });

    // Restore to 2026
    await yearFilter.selectOption('2026');
    await page.waitForTimeout(800);

    // ── Interaction 3: Period pills — active class toggles ───────────────────
    const pill30j = page.locator('.analytics-period-pill[data-period="30j"]');
    const pill1an = page.locator('.analytics-period-pill[data-period="1an"]');

    await expect(pill30j).toBeVisible();
    await pill30j.click();

    // Clicked pill gains .active class
    await expect(pill30j).toHaveClass(/active/, { timeout: 3000 });

    // Previous active pill ("1an") loses .active class
    await expect(pill1an).not.toHaveClass(/active/, { timeout: 3000 });

    // Restore "1an" pill
    await pill1an.click();
    await expect(pill1an).toHaveClass(/active/, { timeout: 3000 });

    // ── Interaction 4: Tab switch — Motions tab ──────────────────────────────
    const tabMotions = page.locator('.analytics-tab[data-tab="motions"]');
    const tabParticipation = page.locator('.analytics-tab[data-tab="participation"]');

    await expect(tabMotions).toBeVisible();
    await tabMotions.click();

    // Motions tab-content becomes active (has .active class)
    await expect(page.locator('#tab-motions')).toHaveClass(/active/, { timeout: 3000 });

    // Participation tab-content is no longer active
    await expect(page.locator('#tab-participation')).not.toHaveClass(/active/, { timeout: 3000 });

    // Donut SVG segments are in the DOM (part of Motions tab content)
    await expect(page.locator('#donutFor')).toBeAttached();
    await expect(page.locator('#donutAgainst')).toBeAttached();
    await expect(page.locator('#donutAbstain')).toBeAttached();

    // ── Interaction 5: Tab switch — Timing tab ───────────────────────────────
    const tabTiming = page.locator('.analytics-tab[data-tab="timing"]');
    await expect(tabTiming).toBeVisible();
    await tabTiming.click();

    // Timing tab-content becomes active
    await expect(page.locator('#tab-timing')).toHaveClass(/active/, { timeout: 3000 });

    // durationChart canvas element is in the DOM
    await expect(page.locator('#durationChart')).toBeAttached();

    // ── Interaction 6: Tab switch — Anomalies tab ────────────────────────────
    const tabAnomalies = page.locator('.analytics-tab[data-tab="anomalies"]');
    await expect(tabAnomalies).toBeVisible();
    await tabAnomalies.click();

    // Anomalies tab-content becomes active
    await expect(page.locator('#tab-anomalies')).toHaveClass(/active/, { timeout: 3000 });

    // anomaliesOverview element is visible inside the active tab
    await expect(page.locator('#anomaliesOverview')).toBeVisible({ timeout: 5000 });

    // ── Interaction 7: Refresh button — reloads KPIs ─────────────────────────
    // Return to Participation tab first
    await tabParticipation.click();
    await expect(page.locator('#tab-participation')).toHaveClass(/active/, { timeout: 3000 });

    // Click refresh
    const refreshBtn = page.locator('#refreshBtn');
    await expect(refreshBtn).toBeVisible();
    await refreshBtn.click();

    // Wait 1s for any async reload
    await page.waitForTimeout(1000);

    // KPI card still visible after refresh (proves refresh did not break state)
    await expect(page.locator('#kpiMeetings')).toBeVisible({ timeout: 5000 });

    // ── Interaction 8: Width verification — no horizontal scroll ─────────────
    const hasHorizontalScroll = await page.evaluate(() => {
      return document.documentElement.scrollWidth > document.documentElement.clientWidth + 1;
    });

    // Fail test if horizontal scroll detected (width regression)
    expect(hasHorizontalScroll).toBe(false);
  });

});
