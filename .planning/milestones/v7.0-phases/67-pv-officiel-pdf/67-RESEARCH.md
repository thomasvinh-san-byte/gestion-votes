# Phase 67: PV Officiel PDF - Research

**Researched:** 2026-04-01
**Domain:** PDF generation with Dompdf, asso loi 1901 template, post-session page wiring
**Confidence:** HIGH

## Summary

Phase 67 is largely an integration and completion task. The core infrastructure already exists: `MeetingReportsController::generatePdf()` is implemented and live at `/api/v1/meeting_generate_report_pdf.php`, Dompdf 3.1 is installed, the post-session page (`postsession.htmx.html`) has the step 3 "Procès-verbal" panel with both a "Générer" button and a "PDF" download link, and the `meeting_reports` table stores HTML+SHA256 snapshots.

The gap is between the existing `generatePdf()` and what the requirements call "asso loi 1901 standard template." The current template is functional but generic — it does not include: (a) the organization header pulled from `tenant_settings.org_name`, (b) explicit quorum section with quorum met/not met callout at the meeting level, (c) formatted signature blocks for both Président and Secrétaire, or (d) a dedicated "Generer PV" entry point that returns an inline-viewable PDF. The current endpoint sends `Content-Disposition: attachment` (download), not `inline` viewing. PV-03 requires both inline viewing and download from the post-session page.

**Primary recommendation:** Extend `generatePdf()` to: (1) pull `org_name` from `SettingsRepository`, (2) upgrade the HTML template to asso loi 1901 layout with quorum block and dual signature lines, (3) add an `?inline=1` query parameter that switches `Content-Disposition` to `inline` for the iframe preview in step 3, and (4) wire the "Generer PV" button in step 3 to this inline endpoint and the existing PDF link to the download endpoint.

<phase_requirements>
## Phase Requirements

| ID | Description | Research Support |
|----|-------------|-----------------|
| PV-01 | After session validation, operator generates PV PDF with: org header (name, date, location), member attendance list (present/represented), quorum reached, resolutions with detailed results (for/against/abstention), signature blocks (president + secretary) | `generatePdf()` handles date/location/motions/attendance/signatures; gaps: org header from `tenant_settings`, quorum section, secretary signature |
| PV-02 | PV PDF uses standard asso loi 1901 template and is generated via Dompdf (already installed) | Dompdf 3.1 installed, used in `generatePdf()`. Template needs upgrade to loi 1901 layout (convocation header, quorum statement, numbered resolutions, signature table) |
| PV-03 | Generated PV viewable inline and downloadable from post-session page | `postsession.htmx.html` step 3 has both `pvPreview` iframe area and `btnExportPDF` link; current endpoint forces `attachment` download only — inline mode missing |
</phase_requirements>

## Standard Stack

### Core
| Library | Version | Purpose | Why Standard |
|---------|---------|---------|--------------|
| dompdf/dompdf | ^3.1 (installed) | HTML-to-PDF conversion | Already required in composer.json, used in `generatePdf()`, DejaVu Sans font configured |
| PHPUnit | ^10.5 (installed) | Unit testing | Project standard, 88 tests passing |

### Supporting
| Library | Version | Purpose | When to Use |
|---------|---------|---------|-------------|
| AgVote\Repository\SettingsRepository | project | Fetch `org_name` from `tenant_settings` | To populate organization header in PDF |
| AgVote\Repository\MeetingReportRepository | project | Store HTML+SHA256 after generation | Audit trail and cache per `upsertFull()` |

### Alternatives Considered
| Instead of | Could Use | Tradeoff |
|------------|-----------|----------|
| Dompdf | TCPDF, mPDF | Dompdf is already installed and in use — switching adds no value |
| tenant_settings.org_name | Hardcoded org name | tenant_settings is the established pattern (see EmailTemplateService) |

**Installation:**
```bash
# Nothing new — Dompdf already in vendor/
# No new dependencies needed for this phase
```

## Architecture Patterns

### Recommended Project Structure
```
app/
└── Controller/
    └── MeetingReportsController.php     # extend generatePdf()
public/
└── assets/js/pages/
    └── postsession.js                   # wire btnGeneratePv button
```

No new files required. This phase is pure extension of existing controller + JS wiring.

### Pattern 1: Dompdf Inline vs Attachment

**What:** Toggle `Content-Disposition` header based on `?inline=1` parameter.
**When to use:** Step 3 "Prévisualisation" needs inline (for iframe embed), step 4 download needs attachment.
**Example:**
```php
// Source: MeetingReportsController.php (existing pattern, extend it)
$disposition = ($isPreview || api_query('inline') === '1') ? 'inline' : 'attachment';
header('Content-Type: application/pdf');
header('Content-Disposition: ' . $disposition . '; filename="' . $filename . '"');
```

### Pattern 2: Org Header from tenant_settings

**What:** Fetch `org_name` key from `SettingsRepository` to populate PDF header.
**When to use:** Every time a PDF is generated for a tenant.
**Example:**
```php
// Source: SettingsRepository.php::get() + established pattern in EmailTemplateService
$orgName = $this->repo()->settings()->get($tenantId, 'org_name') ?? '';
```

### Pattern 3: Asso Loi 1901 PDF HTML Template Structure

**What:** Standard PV template for French associations under loi 1901.
**When to use:** Generating the official PV PDF (non-preview mode).

Required sections in order:
1. **En-tête (header)** — nom organisation, type de séance (AG ordinaire/extraordinaire), date, lieu
2. **Présidence** — nom président, nom secrétaire de séance
3. **Feuille de présence** — membres présents/représentés, quorum atteint (oui/non avec ratio)
4. **Ordre du jour** — numbered resolutions (titles)
5. **Résolutions** — for each: texte/description, table pour/contre/abstention, décision ADOPTÉE/REJETÉE
6. **Clôture** — heure de clôture (use `ended_at` or `validated_at`)
7. **Signatures** — two-column block: "Le Président, [name], date" and "Le Secrétaire, [blank line], date"

```php
// Signature block pattern (Dompdf-safe CSS)
<table style="width:100%;margin-top:40px;border:none">
  <tr>
    <td style="width:50%;text-align:center;padding:20px">
      <p>Le Président de séance</p>
      <p style="margin-top:60px;border-top:1px solid #333">[president_name]</p>
    </td>
    <td style="width:50%;text-align:center;padding:20px">
      <p>Le Secrétaire de séance</p>
      <p style="margin-top:60px;border-top:1px solid #333">&nbsp;</p>
    </td>
  </tr>
</table>
```

### Pattern 4: Post-Session JS Wiring

**What:** The "Générer" button in step 3 currently calls `meeting_generate_report.php` (HTML report). For PV-03, it must also expose the PDF inline.
**When to use:** Step 3 panel in postsession.js.

Current state (postsession.js line 429): `btnGenerateReport` calls `meeting_generate_report.php` and embeds result HTML via `srcdoc` iframe. The `btnExportPDF` already points to `meeting_generate_report_pdf.php`.

Required change: add a dedicated "Generer PV" flow that either:
- Option A (simpler): clicking "Generer" loads the PDF inline using `?inline=1` in an `<iframe src="...">` (browser renders PDF natively), or
- Option B: keep current HTML preview for "Générer", ensure `btnExportPDF` link works for PDF download.

PV-03 says "viewable inline AND downloadable." The pvPreview iframe already exists. Option A is cleanest.

### Anti-Patterns to Avoid
- **Using `$isPreview` for inline toggle:** Preview mode adds a BROUILLON watermark. Inline viewing of the final PV should NOT add a watermark. Use a separate `?inline=1` flag.
- **Fetching org_name inside the HTML template string:** Fetch it before the HTML build block so it's available as a PHP variable.
- **CSS `position:fixed`for watermarks in Dompdf:** Already used in existing code — the `draft-watermark` uses `position:fixed` which Dompdf 3.x supports but with limitations. Keep it as-is for preview; don't use for final PV.
- **Emoji characters in PDF content:** Existing code uses ✅ ❌ ⚪ in the HTML. Dompdf with DejaVu Sans may not render these. Replace with text labels (Adoptée / Rejetée / Abstention) for the PDF template.

## Don't Hand-Roll

| Problem | Don't Build | Use Instead | Why |
|---------|-------------|-------------|-----|
| PDF generation | Custom PDF builder | Dompdf (already installed) | Already integrated, `Options`, `loadHtml()`, `render()`, `output()` all in use |
| Org name persistence | Custom config file | `SettingsRepository::get($tid, 'org_name')` | `tenant_settings` table already exists, key `org_name` established in test |
| Report caching/audit | Custom file store | `MeetingReportRepository::upsertFull()` | Already used in `generatePdf()` |

**Key insight:** All the hard plumbing (Dompdf, report storage, route, auth guard) is done. This phase is template quality + wiring.

## Common Pitfalls

### Pitfall 1: Secretary Name Not in Database
**What goes wrong:** The signature block requires a "Secrétaire de séance" name, but the `meetings` table only has `president_name`. There is no `secretary_name` column.
**Why it happens:** Secretary is not a meeting attribute in the current schema.
**How to avoid:** The signature block for the secrétaire should render as a blank line (print/sign by hand), which is correct for loi 1901 practice. Do not add a DB column — the requirements say "signature blocks president + secretary" but electronic signature is explicitly out of scope.
**Warning signs:** If you see a migration attempt to add `secretary_name` to meetings, challenge it.

### Pitfall 2: Quorum at Meeting Level vs Motion Level
**What goes wrong:** `generatePdf()` currently shows quorum per-motion (via `QuorumEngine`). PV-01 requires quorum displayed at the meeting level ("quorum atteint oui/non").
**Why it happens:** The existing code does motion-level quorum detail, not a meeting-level quorum block.
**How to avoid:** Compute the meeting-level quorum from the attendance data: `$presentPower / $totalPower` vs the meeting's quorum threshold (if any). If no meeting-level quorum policy, state "Quorum non applicable" or derive from the first motion's quorum result.
**Warning signs:** If the generated PDF has no "Quorum de la séance" section, PV-01 will not be satisfied.

### Pitfall 3: Content-Disposition: attachment Forces Download, Breaks Inline
**What goes wrong:** The current `generatePdf()` always sends `Content-Disposition: attachment`. Embedding the PDF in an iframe with `attachment` disposition causes the browser to prompt download instead of showing inline.
**Why it happens:** Existing code was built for download only.
**How to avoid:** Add `?inline=1` parameter check before the `Content-Disposition` header.
**Warning signs:** Testing the iframe embed shows a download prompt or blank iframe.

### Pitfall 4: Dompdf and External Fonts/Images
**What goes wrong:** If you add a logo or image to the org header, `isRemoteEnabled` is set to `false` in the current Options setup — remote URLs will silently fail.
**Why it happens:** Security hardening.
**How to avoid:** Keep the header text-only. No image/logo for now. If a logo is needed, it must be a local file path, not a URL.

### Pitfall 5: Validated-only Guard Already Enforced
**What goes wrong:** Trying to add a second validation check when one already exists.
**Why it happens:** `generatePdf()` at line 346 already rejects non-validated meetings with `api_fail('meeting_not_validated', 409)` unless `?preview=1`.
**How to avoid:** Do not duplicate the guard. The existing logic is: `if (!$isPreview && empty($meeting['validated_at'])) → 409`. The "Generer PV" button in the UI is shown only in step 3, which is only reachable after step 2 (validation). No additional guard needed.

## Code Examples

Verified patterns from existing codebase:

### Fetch org_name from settings
```php
// Source: SettingsRepository.php::get() — established pattern
$orgName = $this->repo()->settings()->get($tenantId, 'org_name') ?? '';
```

### Dompdf initialization (current working pattern)
```php
// Source: MeetingReportsController.php::generatePdf() lines 485-493
$options = new Options();
$options->set('isHtml5ParserEnabled', true);
$options->set('isRemoteEnabled', false);
$options->set('defaultFont', 'DejaVu Sans');

$dompdf = new Dompdf($options);
$dompdf->loadHtml($html, 'UTF-8');
$dompdf->setPaper('A4', 'portrait');
$dompdf->render();
$pdfContent = $dompdf->output();
```

### Inline vs attachment dispatch
```php
// New pattern to add to generatePdf()
$isInline = api_query('inline') === '1';
$disposition = $isInline ? 'inline' : 'attachment';
header('Content-Type: application/pdf');
header('Content-Disposition: ' . $disposition . '; filename="' . $filename . '"');
```

### Meeting-level quorum computation
```php
// Derive from attendance data (same $attendances array already fetched)
$totalPower = array_sum(array_column($attendances, 'voting_power'));
$presentPower = array_sum(array_map(
    fn ($a) => in_array($a['mode'], ['present', 'remote', 'proxy']) ? ($a['voting_power'] ?? 0) : 0,
    $attendances
));
$quorumRatio = ($totalPower > 0) ? ($presentPower / $totalPower) : 0;
```

### postsession.js inline PDF embed (step 3)
```javascript
// Replace srcdoc iframe with src-based iframe pointing to inline PDF endpoint
var pvPreview = document.getElementById('pvPreview');
if (pvPreview) {
  pvPreview.innerHTML = '<iframe src="/api/v1/meeting_generate_report_pdf.php?meeting_id='
    + encodeURIComponent(meetingId) + '&inline=1" style="width:100%;height:600px;border:none"></iframe>';
}
```

## State of the Art

| Old Approach | Current Approach | When Changed | Impact |
|--------------|------------------|--------------|--------|
| Browser print (window.print()) | Dompdf server-side PDF | Already done | Consistent output, no browser dependency |
| HTML-only report (generateReport) | PDF report (generatePdf) | Already done | PDF generation fully working |
| No org header | tenant_settings.org_name | Already in tenant_settings | Just needs to be read in generatePdf |

**Deprecated/outdated:**
- `MeetingReportService::renderHtml()` — still present but the Controller builds its own HTML inline. The service version is simpler (used in tests). The Controller version is the one that produces the PDF. These are divergent; do not merge them in this phase.
- `generateReport()` method (lines 520-587) — produces an older HTML format without Dompdf. The UI calls this for the "Générer" button in step 3 to show an HTML preview. This is separate from `generatePdf()`. Both remain valid in this phase.

## Open Questions

1. **Meeting-level quorum threshold**
   - What we know: Attendance data gives `voting_power` per member and mode. The quorum ratio is computable. But there is no `meeting_quorum_threshold` column — quorum policies are per-motion.
   - What's unclear: Should the meeting-level quorum block say "X% présents" and leave the threshold blank, or pull the threshold from the first motion's quorum policy?
   - Recommendation: Display the ratio as a fact ("Xx% des voix représentées") without asserting a threshold at meeting level. Mark as "Quorum atteint" based on whether any motion had quorum met.

2. **"Generer PV" button label vs "Générer" button**
   - What we know: The success criteria say "operator clicks Generer PV." The current button is labeled "Générer" in step 3.
   - What's unclear: Whether this needs a label change or is already acceptable.
   - Recommendation: Relabel button to "Générer le PV" for clarity; trivial change in postsession.htmx.html.

## Validation Architecture

### Test Framework
| Property | Value |
|----------|-------|
| Framework | PHPUnit 10.5.63 |
| Config file | /home/user/gestion_votes_php/phpunit.xml |
| Quick run command | `vendor/bin/phpunit tests/Unit/MeetingReportsControllerTest.php tests/Unit/MeetingReportServiceTest.php --no-coverage` |
| Full suite command | `vendor/bin/phpunit --no-coverage` |

### Phase Requirements → Test Map
| Req ID | Behavior | Test Type | Automated Command | File Exists? |
|--------|----------|-----------|-------------------|-------------|
| PV-01 | generatePdf() HTML contains org_name, location, date, attendance table, quorum block, signature blocks | unit | `vendor/bin/phpunit tests/Unit/MeetingReportsControllerTest.php --no-coverage --filter generatePdf` | ✅ (extend existing) |
| PV-01 | generatePdf() rejects non-validated meeting | unit | `vendor/bin/phpunit tests/Unit/MeetingReportsControllerTest.php --no-coverage --filter testGeneratePdfRejectsNonValidated` | ✅ existing test coverage |
| PV-02 | PDF template contains loi 1901 sections (en-tête, présence, résolutions, signatures) | unit | `vendor/bin/phpunit tests/Unit/MeetingReportsControllerTest.php --no-coverage` | ✅ (add assertions to existing test) |
| PV-03 | inline=1 sets Content-Disposition: inline | unit | `vendor/bin/phpunit tests/Unit/MeetingReportsControllerTest.php --no-coverage --filter testInlineDisposition` | ❌ Wave 0 |

### Sampling Rate
- **Per task commit:** `vendor/bin/phpunit tests/Unit/MeetingReportsControllerTest.php tests/Unit/MeetingReportServiceTest.php --no-coverage`
- **Per wave merge:** `vendor/bin/phpunit --no-coverage`
- **Phase gate:** Full suite green before `/gsd:verify-work`

### Wave 0 Gaps
- [ ] Add test `testInlineDispositionReturnsInlineHeader` to `MeetingReportsControllerTest.php` — covers PV-03 inline mode
- [ ] Add test `testGeneratePdfIncludesOrgNameHeader` — covers PV-01 org header
- [ ] Add test `testGeneratePdfIncludesSignatureBlocks` — covers PV-01 signature requirement

*(Existing 50 tests in MeetingReportsControllerTest cover auth guards, invalid inputs, and audit logging — no new test files needed, only new test methods in existing file)*

## Sources

### Primary (HIGH confidence)
- Direct code inspection: `/home/user/gestion_votes_php/app/Controller/MeetingReportsController.php` — full generatePdf() implementation, Dompdf usage
- Direct code inspection: `/home/user/gestion_votes_php/app/Services/MeetingReportService.php` — HTML report service
- Direct code inspection: `/home/user/gestion_votes_php/app/Repository/MeetingReportRepository.php` — upsertFull(), findSnapshot()
- Direct code inspection: `/home/user/gestion_votes_php/app/Repository/SettingsRepository.php` — get(tenantId, key) pattern
- Direct code inspection: `/home/user/gestion_votes_php/public/postsession.htmx.html` — step 3 HTML, pvPreview, btnExportPDF, btnGenerateReport
- Direct code inspection: `/home/user/gestion_votes_php/public/assets/js/pages/postsession.js` — btnGenerateReport and PDF link wiring
- Direct code inspection: `/home/user/gestion_votes_php/composer.json` — dompdf/dompdf ^3.1 confirmed installed
- PHPUnit run: MeetingReportServiceTest (38 tests pass), MeetingReportsControllerTest (50 tests pass)

### Secondary (MEDIUM confidence)
- French asso loi 1901 PV template structure — based on standard practice: header, attendance/quorum, numbered resolutions, dual signature block (président + secrétaire). No official government PDF spec was verified; standard practice is well-established.

### Tertiary (LOW confidence)
- None

## Metadata

**Confidence breakdown:**
- Standard stack: HIGH — Dompdf in use, all repos verified by reading source
- Architecture: HIGH — existing endpoints, routes, and HTML files all inspected
- Pitfalls: HIGH — identified from direct code reading (Content-Disposition header, secretary column absence, Dompdf remote disabled)

**Research date:** 2026-04-01
**Valid until:** 2026-05-01 (stable stack — Dompdf, PHPUnit, project PHP patterns)
