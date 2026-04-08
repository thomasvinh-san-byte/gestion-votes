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
 * @param {import('@playwright/test').Page} page
 * @param {string} pageName - label used in error messages
 * @returns {Promise<import('axe-core').AxeResults>}
 */
async function axeAudit(page, pageName) {
  const results = await new AxeBuilder({ page })
    .withTags(['wcag2a', 'wcag2aa'])
    .disableRules(['color-contrast']) // tuned separately via design tokens
    .analyze();

  const blockers = results.violations.filter(
    (v) => v.impact === 'critical' || v.impact === 'serious'
  );

  if (blockers.length > 0) {
    const msg = blockers
      .map((v) => `  - [${v.impact}] ${v.id}: ${v.help} (${v.nodes.length} nodes)`)
      .join('\n');
    throw new Error(`Axe audit failed on ${pageName}:\n${msg}`);
  }

  return results;
}

module.exports = { axeAudit };
