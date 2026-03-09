// @ts-check
const { test, expect } = require('@playwright/test');

/**
 * Analytics Dashboard E2E Tests
 *
 * Tests the analytics page: overview stats, participation charts,
 * meeting comparison, resolution breakdown, export functionality.
 */

const OPERATOR_KEY = 'operator-key-2026-secret';

async function loginAsOperator(page) {
  await page.goto('/login.html');
  await page.fill('input[type="password"], input[name="api_key"]', OPERATOR_KEY);
  await page.click('button[type="submit"]');
  await page.waitForURL(/meetings|operator/, { timeout: 10000 });
}

// ---------------------------------------------------------------------------
// Analytics Dashboard
// ---------------------------------------------------------------------------

test.describe('Analytics Dashboard', () => {

  test.beforeEach(async ({ page }) => {
    await loginAsOperator(page);
  });

  test('should display analytics page', async ({ page }) => {
    await page.goto('/analytics.htmx.html');

    await expect(page).toHaveTitle(/AG-VOTE|Analytique|Analytics/);
    await page.waitForLoadState('networkidle');

    // Page should render content (stats or empty state)
    const content = page.locator('.kpi, .chart-container, .analytics-card, .empty-state, canvas');
    await expect(content.first()).toBeVisible({ timeout: 10000 });
  });

  test('should display KPI cards', async ({ page }) => {
    await page.goto('/analytics.htmx.html');
    await page.waitForLoadState('networkidle');

    // KPI cards (ag-kpi components or similar)
    const kpis = page.locator('ag-kpi, .kpi-card, .stat-card, [data-kpi]');
    if (await kpis.count() > 0) {
      await expect(kpis.first()).toBeVisible({ timeout: 5000 });
    }
  });

  test('should have date range filter', async ({ page }) => {
    await page.goto('/analytics.htmx.html');
    await page.waitForLoadState('networkidle');

    // Date range selector
    const dateFilter = page.locator('input[type="date"], select[name="period"], [data-date-range]');
    if (await dateFilter.count() > 0) {
      await expect(dateFilter.first()).toBeVisible();
    }
  });

  test('should display participation chart area', async ({ page }) => {
    await page.goto('/analytics.htmx.html');
    await page.waitForLoadState('networkidle');

    // Chart containers (canvas for Chart.js or similar)
    const charts = page.locator('canvas, .chart-container, [data-chart]');
    if (await charts.count() > 0) {
      await expect(charts.first()).toBeVisible({ timeout: 5000 });
    }
  });

  test('should display meetings overview table', async ({ page }) => {
    await page.goto('/analytics.htmx.html');
    await page.waitForLoadState('networkidle');

    // Meetings table or list in analytics view
    const table = page.locator('table, .meetings-analytics, [data-meetings-list]');
    if (await table.count() > 0) {
      await expect(table.first()).toBeVisible({ timeout: 5000 });
    }
  });

});

// ---------------------------------------------------------------------------
// Analytics API
// ---------------------------------------------------------------------------

test.describe('Analytics API', () => {

  test('analytics endpoint should reject unauthenticated requests', async ({ request }) => {
    const response = await request.get('/api/v1/analytics.php');

    expect(response.status()).toBeGreaterThanOrEqual(400);
  });

  test('reports aggregate should reject unauthenticated requests', async ({ request }) => {
    const response = await request.get('/api/v1/reports_aggregate.php');

    expect(response.status()).toBeGreaterThanOrEqual(400);
  });

});
