// @ts-check
const { test, expect } = require('@playwright/test');
const { loginAsOperator } = require('../helpers');

/**
 * CSP Nonce Enforcement — validate zero CSP violations across all pages.
 *
 * Listens for CSP violation console messages ([Report Only] warnings) and
 * page errors across all 22 pages (20 authenticated + 2 public), asserting
 * zero violations. Also verifies the Content-Security-Policy-Report-Only
 * header contains nonce + strict-dynamic.
 *
 * Run: npx playwright test specs/csp-enforcement.spec.js --project=chromium
 * Docker: bin/test-e2e.sh (included in default spec discovery)
 */

// Authenticated pages served through PHP with CSP nonce injection
const AUTHED_PAGES = [
    '/dashboard',
    '/wizard',
    '/hub',
    '/operator',
    '/postsession',
    '/validate',
    '/archives',
    '/meetings',
    '/audit',
    '/members',
    '/users',
    '/analytics',
    '/settings',
    '/admin',
    '/help',
    '/email-templates',
    '/report',
    '/trust',
    '/docs',
];

// Public pages (no auth required)
const PUBLIC_PAGES = [
    '/public',
    '/login',
];

/**
 * Collect CSP violation messages from console and page errors.
 * @param {import('@playwright/test').Page} page
 * @param {Array<{url: string, type: string, message: string}>} violations
 */
function attachCspListeners(page, violations) {
    page.on('console', msg => {
        const text = msg.text();
        if (text.includes('[Report Only]') ||
            text.includes('Content Security Policy') ||
            text.includes('content-security-policy')) {
            violations.push({ url: page.url(), type: 'console', message: text });
        }
    });

    page.on('pageerror', error => {
        if (error.message.includes('Content Security Policy') ||
            error.message.includes('CSP')) {
            violations.push({ url: page.url(), type: 'pageerror', message: error.message });
        }
    });
}

test.describe('CSP Nonce Enforcement', () => {

    test('zero CSP violations on authenticated pages', async ({ page }) => {
        test.setTimeout(120_000); // 2 min for 19 pages

        const violations = [];
        attachCspListeners(page, violations);

        // Login first
        await loginAsOperator(page);

        for (const pageUrl of AUTHED_PAGES) {
            await page.goto(pageUrl, { waitUntil: 'networkidle' });
            // Wait for any deferred script loading
            await page.waitForTimeout(500);
        }

        if (violations.length > 0) {
            console.log('CSP Violations found on authenticated pages:');
            violations.forEach(v => console.log(`  ${v.url}: [${v.type}] ${v.message}`));
        }

        expect(violations).toEqual([]);
    });

    test('zero CSP violations on public pages', async ({ page }) => {
        test.setTimeout(30_000);

        const violations = [];
        attachCspListeners(page, violations);

        for (const pageUrl of PUBLIC_PAGES) {
            await page.goto(pageUrl, { waitUntil: 'networkidle' });
            await page.waitForTimeout(500);
        }

        if (violations.length > 0) {
            console.log('CSP Violations found on public pages:');
            violations.forEach(v => console.log(`  ${v.url}: [${v.type}] ${v.message}`));
        }

        expect(violations).toEqual([]);
    });

    test('CSP report-only header is present on PHP-served pages', async ({ page }) => {
        test.setTimeout(30_000);

        await loginAsOperator(page);

        const response = await page.goto('/dashboard', { waitUntil: 'networkidle' });
        const headers = response.headers();

        // Report-only header must be present
        const cspRO = headers['content-security-policy-report-only'];
        expect(cspRO).toBeDefined();
        expect(cspRO).toContain('strict-dynamic');
        expect(cspRO).toContain('nonce-');

        // script-src in report-only must NOT contain unsafe-inline
        const scriptSrc = cspRO.match(/script-src\s+([^;]+)/);
        expect(scriptSrc).toBeTruthy();
        expect(scriptSrc[1]).not.toContain("'unsafe-inline'");

        // Enforcing CSP must still be present (baseline defense)
        const cspEnforcing = headers['content-security-policy'];
        expect(cspEnforcing).toBeDefined();
        expect(cspEnforcing).toContain("script-src 'self'");
    });

    test('CSP report-only header contains nonce matching inline scripts', async ({ page }) => {
        test.setTimeout(30_000);

        await loginAsOperator(page);

        const response = await page.goto('/dashboard', { waitUntil: 'networkidle' });
        const headers = response.headers();
        const cspRO = headers['content-security-policy-report-only'];

        // Extract nonce from CSP header
        const nonceMatch = cspRO.match(/nonce-([a-f0-9]+)/);
        expect(nonceMatch).toBeTruthy();
        const headerNonce = nonceMatch[1];

        // Verify inline scripts on the page carry the same nonce
        const scriptNonces = await page.$$eval('script[nonce]', scripts =>
            scripts.map(s => s.getAttribute('nonce'))
        );

        // All inline script nonces should match the CSP header nonce
        for (const nonce of scriptNonces) {
            expect(nonce).toBe(headerNonce);
        }
    });
});
