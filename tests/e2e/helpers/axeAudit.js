// @ts-check
const AxeBuilder = require('@axe-core/playwright').default;

/**
 * Run an axe-core audit on the current page and assert zero critical/serious
 * WCAG 2.0 A/AA violations. Returns the full results for inspection.
 *
 * color-contrast is disabled here because contrast values depend on the
 * design-token layer and are tuned separately (TEST-03 scope is structural
 * accessibility, not visual contrast ratios).
 *
 * Per-page waivers (Phase 16 D-10) can be passed via
 * `options.extraDisabledRules` — each rule is merged with the default
 * `['color-contrast']` disable list. Use sparingly and always justify with a
 * `A11Y-WAIVER` comment at the call site (D-09).
 *
 * @param {import('@playwright/test').Page} page
 * @param {string} pageName - label used in error messages
 * @param {{ extraDisabledRules?: string[] }} [options]
 * @param {string[]} [options.extraDisabledRules] - rule ids to disable on top
 *   of the default `color-contrast` (e.g. `['region']` for pages embedding a
 *   third-party iframe). Empty array or omitted = default behavior.
 * @returns {Promise<import('axe-core').AxeResults>}
 *
 * @example
 *   await axeAudit(page, '/trust.htmx.html', { extraDisabledRules: ['region'] });
 */
async function axeAudit(page, pageName, options = {}) {
  const { extraDisabledRules = [] } = options;
  const results = await new AxeBuilder({ page })
    .withTags(['wcag2a', 'wcag2aa'])
    .disableRules(['color-contrast', ...extraDisabledRules]) // tuned separately via design tokens
    .analyze();

  const blockers = results.violations.filter(
    (v) => v.impact === 'critical' || v.impact === 'serious'
  );

  if (blockers.length > 0) {
    const msg = blockers
      .map((v) => {
        const nodeList = v.nodes
          .slice(0, 5)
          .map((n) => `      → ${n.target.join(' ')} | ${(n.html || '').slice(0, 80)}`)
          .join('\n');
        return `  - [${v.impact}] ${v.id}: ${v.help} (${v.nodes.length} nodes)\n${nodeList}`;
      })
      .join('\n');
    throw new Error(`Axe audit failed on ${pageName}:\n${msg}`);
  }

  return results;
}

module.exports = { axeAudit };
