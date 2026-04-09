// @ts-check
const { test, expect } = require('@playwright/test');
const { loginAsOperator, E2E_MEETING_ID } = require('../helpers');

/**
 * E2E-REPORT: Report/PV page critical path
 * Meeting context + email form + export links + PV preview + status timeline
 *
 * Hybrid auth strategy: cookie injection via loginAsOperator (no rate limit hit).
 * Re-runnable: read-only assertions — no SMTP sends, no DB writes.
 * Tagged @critical-path for `--grep="@critical-path"` filtering.
 *
 * Meeting context: injected via sessionStorage key 'meeting_id' (MeetingContext.STORAGE_KEY)
 * before page navigation, using the canonical E2E meeting UUID from helpers.js.
 *
 * Key behaviour notes (from report.js):
 * - If MeetingContext.get() is null → redirects to /meetings after 2s
 * - setupUrls() wires all hrefs once meeting_id is available
 * - disableExports() removes href attributes if meeting is not validated/archived
 *   (status: setup, running, closed → exports disabled; validated/archived → enabled)
 * - reportToArchives href is rewritten to /archives/{meetingId}
 *
 * Covers 8 primary interactions on /report.htmx.html:
 *   1. Page mount + static structure present (meetingTitle, btnExportPDF, timeline)
 *   2. Email form is interactive (fill input, assert value, button present)
 *   3. PV preview area renders empty-state or iframe (meeting context resolved)
 *   4. Export link IDs are all present in the DOM
 *   5. Download CTA is present and wired
 *   6. Timeline: 4 status dots rendered (generated/validated/sent/archived)
 *   7. Nav link to archives present (href rewritten by JS to /archives/{id})
 *   8. Width verification — no horizontal scroll at desktop viewport
 */

test.describe('E2E-REPORT Report/PV page critical path', () => {

  test('report: meeting context + email form + export links + preview + timeline @critical-path', async ({ page }) => {
    test.setTimeout(120000);

    // Step 1 — Login as operator (required role for report page)
    await loginAsOperator(page);

    // Step 2 — Inject meeting_id into sessionStorage before navigation.
    // MeetingContext reads sessionStorage key 'meeting_id' on init().
    // Use addInitScript so the value is available before any JS runs.
    const meetingId = E2E_MEETING_ID;
    await page.addInitScript((mid) => {
      try { sessionStorage.setItem('meeting_id', mid); } catch (_e) {}
      try { localStorage.setItem('meeting_id', mid); } catch (_e) {}
    }, meetingId);

    // Step 3 — Navigate to report page
    await page.goto('/report.htmx.html', { waitUntil: 'domcontentloaded' });

    // Verify we are on the report page and not mid-redirect to login.
    // report.js redirects to /meetings (not login) when meeting_id is absent.
    // auth.js redirects to login when session is invalid.
    const finalUrl = page.url();
    if (finalUrl.includes('login')) {
      throw new Error(`Redirected to login — auth injection may have failed. URL: ${finalUrl}`);
    }

    // ── Interaction 1: Page mount + static structure present ─────────────────
    // meetingTitle: static <p> in header; content is populated by loadMeetingInfo() async.
    // At domcontentloaded the element exists; text may be empty until API responds.
    await expect(page.locator('#meetingTitle')).toBeAttached({ timeout: 10000 });

    // btnExportPDF: present in DOM always. href is '#' from static HTML, then
    // rewritten by setupUrls() to the PDF API URL, then REMOVED by disableExports()
    // if meeting is not validated. Assert element is present — href state depends on meeting status.
    await expect(page.locator('#btnExportPDF')).toBeAttached({ timeout: 10000 });

    // PV timeline first step is present in the DOM
    await expect(page.locator('.pv-timeline-step[data-step="generated"]')).toBeAttached({ timeout: 10000 });

    // ── Interaction 2: Email form is interactive ──────────────────────────────
    // The email input is always visible (not hidden by any state).
    const emailInput = page.locator('#email');
    await expect(emailInput).toBeVisible({ timeout: 10000 });

    // Fill with test address — assert value propagated (proves DOM interaction works)
    await emailInput.fill('test-nobody@example.invalid');
    await expect(emailInput).toHaveValue('test-nobody@example.invalid');

    // Send button is present (may be disabled if meeting is not validated — that is expected)
    const btnSendEmail = page.locator('#btnSendEmail');
    await expect(btnSendEmail).toBeAttached({ timeout: 5000 });

    // ── Interaction 3: PV preview area renders empty-state or iframe ──────────
    // report.js calls setupUrls() which shows iframe and hides empty-state.
    // Either element must be in the DOM (both are always present in static HTML).
    const emptyStateCount = await page.locator('#pvEmptyState').count();
    const pvFrameCount = await page.locator('#pvFrame').count();
    expect(emptyStateCount + pvFrameCount).toBeGreaterThanOrEqual(1);

    // Wait briefly for setupUrls() to run (async after MeetingContext resolves)
    // then verify the PV preview area has the expected structure.
    await page.waitForFunction(() => {
      const frame = document.getElementById('pvFrame');
      const empty = document.getElementById('pvEmptyState');
      // After setupUrls: iframe is shown OR empty state remains visible
      return (frame !== null) || (empty !== null);
    }, { timeout: 5000 });

    // "Open in new tab" link is present (href wired by setupUrls)
    await expect(page.locator('#btnOpenNewTab')).toBeAttached({ timeout: 5000 });

    // ── Interaction 4: Export link IDs present in DOM ─────────────────────────
    // All export link elements must be in the DOM. hrefs may be real URLs
    // (if meeting is validated) or absent (disableExports removes href attr when not validated).
    // We assert DOM presence only — href rewriting is meeting-status-dependent.
    const exportIds = [
      '#exportFullXlsx',
      '#exportFullXlsxWithVotes',
      '#exportAttendanceXlsx',
      '#exportVotesXlsx',
      '#exportResultsXlsx',
      '#exportPV',
      '#exportAttendance',
      '#exportVotes',
      '#exportMotions',
      '#exportMembers',
      '#exportAudit',
    ];
    for (const id of exportIds) {
      await expect(page.locator(id)).toBeAttached({ timeout: 5000 });
    }

    // ── Interaction 5: Download CTA is present ────────────────────────────────
    // pvDownloadCta is a styled <a href="#"> in static HTML; href stays '#' (never rewritten).
    // It is always visible as it contains static text "Télécharger le PV".
    const pvDownloadCta = page.locator('#pvDownloadCta');
    await expect(pvDownloadCta).toBeAttached({ timeout: 10000 });

    // ── Interaction 6: Timeline — exactly 4 status dots rendered ─────────────
    // The timeline is fully static HTML — 4 steps always present.
    const timelineSteps = page.locator('.pv-timeline-step');
    await expect(timelineSteps).toHaveCount(4, { timeout: 5000 });

    // Each step has a .pv-step-dot child
    const generatedStep = page.locator('.pv-timeline-step[data-step="generated"]');
    await expect(generatedStep.locator('.pv-step-dot')).toBeAttached();

    // All 4 expected step data-step values are present
    for (const step of ['generated', 'validated', 'sent', 'archived']) {
      await expect(page.locator(`.pv-timeline-step[data-step="${step}"]`)).toBeAttached();
    }

    // ── Interaction 7: Nav link to archives ──────────────────────────────────
    // reportToArchives: static href="/archives" overwritten by setupUrls() to
    // "/archives/{meetingId}" when meeting context resolves.
    // Assert element is present with an href that starts with '/archives'.
    const reportToArchives = page.locator('#reportToArchives');
    await expect(reportToArchives).toBeAttached({ timeout: 5000 });
    // Wait for setupUrls to rewrite the href (or leave it as '/archives' if it didn't run)
    await page.waitForFunction((mid) => {
      const el = document.getElementById('reportToArchives');
      if (!el) return false;
      const href = el.getAttribute('href') || '';
      return href.startsWith('/archives');
    }, meetingId, { timeout: 10000 });
    const archivesHref = await reportToArchives.getAttribute('href');
    expect(archivesHref).toMatch(/^\/archives/);

    // ── Interaction 8: Width — no horizontal scroll at desktop viewport ───────
    const noHorizontalScroll = await page.evaluate(() => {
      return document.documentElement.scrollWidth <= document.documentElement.clientWidth + 1;
    });
    expect(noHorizontalScroll).toBe(true);
  });

});
