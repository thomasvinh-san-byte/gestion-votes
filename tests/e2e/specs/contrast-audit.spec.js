// @ts-check
const { test } = require('@playwright/test');
const AxeBuilder = require('@axe-core/playwright').default;
const fs = require('fs');
const path = require('path');
const { loginAsOperator, loginAsAdmin, loginAsVoter } = require('../helpers');

// Manual-only: run via `CONTRAST_AUDIT=1 npx playwright test specs/contrast-audit.spec.js --project=chromium`
// Gated so default CI (bin/test-e2e.sh) does NOT execute this — contrast is tuned separately.
// Produces .planning/v1.3-CONTRAST-AUDIT.json for report aggregation (plan 16-05).

test.describe.configure({ mode: 'serial' });

test.describe('Contrast audit (manual, one-shot)', () => {
  test.skip(!process.env.CONTRAST_AUDIT, 'manual only — set CONTRAST_AUDIT=1 to run');

  test('collect color-contrast violations across 22 pages', async ({ page }) => {
    test.setTimeout(300_000); // 5 min — 22 pages x login x axe

    // Duplicated from accessibility.spec.js PAGES — keep in sync manually.
    const PAGES = [
      { path: '/login.html',                loginFn: null,            requiredLocator: '#email' },
      { path: '/dashboard.htmx.html',       loginFn: loginAsOperator, requiredLocator: 'main, [data-page]' },
      { path: '/meetings.htmx.html',        loginFn: loginAsOperator, requiredLocator: 'main, [data-page]' },
      { path: '/members.htmx.html',         loginFn: loginAsOperator, requiredLocator: 'main, [data-page]' },
      { path: '/operator.htmx.html',        loginFn: loginAsOperator, requiredLocator: 'main, [data-page]' },
      { path: '/settings.htmx.html',        loginFn: loginAsAdmin,    requiredLocator: 'main, [data-page]' },
      { path: '/audit.htmx.html',           loginFn: loginAsAdmin,    requiredLocator: 'main, [data-page]' },
      { path: '/admin.htmx.html',           loginFn: loginAsAdmin,    requiredLocator: 'main, [data-page]' },
      { path: '/analytics.htmx.html',       loginFn: loginAsOperator, requiredLocator: 'main, [data-page]' },
      { path: '/archives.htmx.html',        loginFn: loginAsOperator, requiredLocator: 'main, [data-page]' },
      { path: '/docs.htmx.html',            loginFn: loginAsOperator, requiredLocator: 'main, [data-page]' },
      { path: '/email-templates.htmx.html', loginFn: loginAsAdmin,    requiredLocator: 'main, [data-page]' },
      { path: '/help.htmx.html',            loginFn: null,            requiredLocator: 'h1' },
      { path: '/hub.htmx.html',             loginFn: loginAsOperator, requiredLocator: 'main, [data-page]' },
      { path: '/postsession.htmx.html',     loginFn: loginAsOperator, requiredLocator: 'main, [data-page]' },
      { path: '/public.htmx.html',          loginFn: null,            requiredLocator: '.projection-header, main, [data-page]' },
      { path: '/report.htmx.html',          loginFn: loginAsOperator, requiredLocator: 'main, [data-page]' },
      { path: '/trust.htmx.html',           loginFn: loginAsAdmin,    requiredLocator: 'main, [data-page]' },
      { path: '/users.htmx.html',           loginFn: loginAsAdmin,    requiredLocator: 'main, [data-page]' },
      { path: '/validate.htmx.html',        loginFn: loginAsOperator, requiredLocator: 'main, [data-page]' },
      { path: '/vote.htmx.html',            loginFn: loginAsVoter,    requiredLocator: '#meetingSelect, [data-page], main' },
      { path: '/wizard.htmx.html',          loginFn: loginAsOperator, requiredLocator: 'main, [data-page]' },
    ];

    const report = {
      generatedAt: new Date().toISOString(),
      runner: '@axe-core/playwright withRules([color-contrast])',
      wcagLevel: 'wcag2aa',
      pages: [],
    };

    for (const p of PAGES) {
      try {
        if (p.loginFn) await p.loginFn(page);
        await page.goto(p.path, { waitUntil: 'domcontentloaded' });
        await page.waitForSelector(p.requiredLocator.split(',')[0].trim(), { timeout: 10_000 }).catch(() => {});
        const results = await new AxeBuilder({ page })
          .withTags(['wcag2aa'])
          .withRules(['color-contrast'])
          .analyze();

        report.pages.push({
          path: p.path,
          violationCount: results.violations.length,
          violations: results.violations.map(v => ({
            id: v.id,
            impact: v.impact,
            help: v.help,
            nodes: v.nodes.slice(0, 20).map(n => ({
              target: n.target,
              html: (n.html || '').slice(0, 120),
              contrastRatio: n.any?.[0]?.data?.contrastRatio ?? null,
              fgColor: n.any?.[0]?.data?.fgColor ?? null,
              bgColor: n.any?.[0]?.data?.bgColor ?? null,
              fontSize: n.any?.[0]?.data?.fontSize ?? null,
              fontWeight: n.any?.[0]?.data?.fontWeight ?? null,
            })),
          })),
        });
      } catch (err) {
        report.pages.push({
          path: p.path,
          error: String(err?.message || err),
        });
      }
    }

    const out = path.resolve(__dirname, '../../../.planning/v1.3-CONTRAST-AUDIT.json');
    fs.writeFileSync(out, JSON.stringify(report, null, 2));
    console.log(`Contrast audit written: ${out} (${report.pages.length} pages)`);
  });
});
