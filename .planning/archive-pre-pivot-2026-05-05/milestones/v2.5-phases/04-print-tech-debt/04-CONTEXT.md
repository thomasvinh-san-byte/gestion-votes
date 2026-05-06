# Phase 4: Print + Tech Debt Résiduel — Context

**Gathered:** 2026-05-04
**Status:** Ready for planning
**Milestone:** v2.4 Polish & Robustness

<domain>
## Phase Boundary

Finaliser le polish print/PDF + assainir la dette tokens visuels :
- Export PDF (dompdf) gagne header répété + footer pagination via CSS `@page` rules
- Borders + box-shadow CSS migrés vers tokens — ratio tokenized ≥ 95 % strict

**Hors scope** : refonte palette colors hex (différé v2.5+), shadows tokens définitions (utiliser tokens existants `--shadow-xs/sm/md/lg`), typography refactor.

</domain>

<decisions>
## Implementation Decisions

### Granularité (D-01)

- **D-01**: **2 plans atomiques parallèles** (zones disjointes) :
  - **04.1** TECH-V24-01 (PDF header/footer) — touche `app/Services/*Pdf*.php` + templates HTML PDF + CSS `@page` rules
  - **04.2** TECH-V24-02 (borders/shadows tokens) — touche CSS files only

### TECH-V24-01 — dompdf header/footer (D-02..D-04)

- **D-02**: **CSS `@page` rules** approach (déclaratif). dompdf 3.1 supporte `@page { @top-center { content: ... } @bottom-center { content: counter(page) ... } }`. Pas de hook PHP API — pas de modification des services PDF, juste les templates/styles HTML émis.
- **D-03**: **Header content** : titre séance + date (format français `JJ/MM/YYYY`). Variables passées via template HTML, injectées en `string` literal dans `@page @top-center { content: "..."; }` ou via `running()` element.
- **D-04**: **Footer content** : `Page X sur Y` (français). Utilise `counter(page)` + `counter(pages)` natifs dompdf.
- **Validation** : 3 PVs longs (≥10 pages) générés et inspectés visuellement. Documenter dans SUMMARY (capture d'écran 1ère/dernière page de chaque PV) ou défère dev-machine si non praticable sandbox.

### TECH-V24-02 — borders/shadows tokens (D-05..D-09)

- **D-05**: **Scope strict** : `border` + `box-shadow` properties uniquement. Pas de migration colors hex inline (différé v2.5+), pas de outline/filter/text-shadow.
- **D-06**: **Audit grep régulier** (le pattern dans `EXPLORE-PATTERNS.md` créé v2.4 P3 est applicable) :
  ```bash
  # Hardcoded borders (numeric width, hex color, rgb)
  grep -rnE "(^|[^a-z])border:\\s+[0-9]+(px|rem|em)\\s+[a-z]+\\s+(#[0-9a-f]+|rgba?\\()" \
    public/assets/css/ | grep -v "design-system.css"
  
  # Hardcoded box-shadow (with rgba/hex, not var())
  grep -rnE "box-shadow:\\s+(?!var\\()" public/assets/css/ | grep -v "design-system.css"
  ```
  Audit produit `04.2-AUDIT.md` avec : file:line, current value, classification (migrate-token | new-token-needed | keep-justified).
- **D-07**: **Cible stricte ≥95 %** : `tokenized / (tokenized + hardcoded) >= 0.95`. Métrique calculée dans audit final. Hardcoded résiduels documentés avec rationale (ex: vendor-prefix, animation-only, debug-only).
- **D-08**: **Tokens cibles** : utiliser tokens existants livrés v2.3 TECH-01 quick (`--shadow-xs/sm/md/lg`, `--border-default/subtle/strong/dashed/focus`). Pas de nouveaux tokens créés sauf si gap critique identifié dans audit (cas exceptionnel, justifié dans SUMMARY).
- **D-09**: **Atomic commits per fichier** comme spécifié dans le req. 1 commit = 1 fichier CSS migré. Permet revert ciblé si régression visuelle.

### Validation visuelle (D-10)

- **D-10**: 
  - **PDF** (TECH-01) : génération 3 PVs longs sur dev-machine, inspection visuelle. Sandbox-deferred si dompdf rendering pas praticable.
  - **Borders/shadows** (TECH-02) : pas de test gardien obligatoire (le ratio 95 % EST le test). Optionnel : screenshots before/after sur 2-3 pages affectées.

### Branche & timing (D-11)

- **D-11**: Phase 4 sur branche `feat/v2.4-cockpit-polish` (réutilisée). Démarre après Phase 3 done. Le ROADMAP indiquait possibilité de paralléliser P1-P3 mais P4 vient en dernier (effort opportuniste).

### Claude's Discretion

- Format exact header (`Séance du [date]` vs `[Tenant] - [date]`)
- Exact tokens à utiliser pour cas borderline (ex: `--border-default` vs `--border-subtle` selon contexte visuel)
- Si 95 % atteint mais pas 100 %, lister les 5 % résiduels dans SUMMARY avec ETA migration v2.5

</decisions>

<canonical_refs>
## Canonical References

### TECH-V24-01 sources
- `app/Services/ProcurationPdfService.php` — pattern dompdf (procurations)
- `app/Services/MeetingReportsService.php` — pattern dompdf (PV/rapport)
- `app/Services/MeetingReportService.php` (singulier — vérifier si doublon ou autre rôle)
- `app/Services/ReportGenerator.php` — service extrait v1.5
- Templates HTML PDF : à identifier dans audit (`app/Templates/pdf/*.php` ou inline string PHP)

### TECH-V24-02 sources
- `public/assets/css/design-system.css` — tokens définitions (UNTOUCHED, c'est la source)
- `public/assets/css/*.css` (hors design-system.css) — cibles migration
- `.planning/codebase/EXPLORE-PATTERNS.md` (v2.4 P3) — patterns scan anti-faux-positifs
- v2.3 TECH-01 SUMMARY : référence migrations déjà faites (ne pas refaire)

### dompdf docs
- dompdf 3.1 supporte CSS Paged Media Module : `@page`, `@top-center`, `@bottom-center`, `counter(page)`, `counter(pages)`
- Référence : https://github.com/dompdf/dompdf (composer.json déjà présent)

</canonical_refs>

<code_context>
## Existing Code Insights

### Reusable Assets

- **dompdf installé** via composer (`composer.json` → `dompdf/dompdf ^3.1`)
- **Templates HTML PDF** existent (à identifier dans audit Plan 04.1)
- **Tokens design-system.css** complet (livré v2.3 TECH-01)

### Established Patterns
- Pattern PDF : services PHP construisent HTML string puis dompdf render
- CSS print : `@media print` déjà utilisé v2.3 P2 (pages éditoriales)

### Audit baseline (à confirmer dans plans)
- 179 grep hits totaux (`border|box-shadow|#hex` regex large)
- 165 dans `design-system.css` (definitions tokens, légitimes — UNTOUCHED)
- ~14 hits réels dans autres CSS files (scope migration estimé)
- v2.3 TECH-01 quick avait migré ~234 borders sur 25 files — ce phase finalise le residuel

### Integration Points

- **ProcurationPdfService::renderHtml()** ou équivalent — point d'injection CSS @page
- **CSS imports HTML PDF** : vérifier si templates incluent déjà un stylesheet, sinon ajouter `<style>@page {...}</style>` inline

</code_context>

<specifics>
## Specific Ideas

- **dompdf @page rules** : déclaratif > impératif. Évite de toucher les services PHP (encapsulation préservée). Code change isolé aux templates/styles.
- **EXPLORE-PATTERNS.md applicable** : utiliser le pattern correct hyphenated tokens pour scan (insight v2.4 P3). Évite faux-positifs `border-radius` si on cherche `border:`.
- **Atomic commits per fichier** : facilite peer review (1 fichier = 1 contexte, pas de mega-commit cross-file).
- **Cible 95 % strict** : la métrique chiffrée force la rigueur. Si l'audit révèle 30 sites et 27 migrés = 90 % → identifier les 3 résiduels et migrer ou justifier.

</specifics>

<deferred>
## Deferred Ideas

- **Migration colors hex inline** (hors borders/shadows) — différé v2.5+ (refactor majeur)
- **Refactor outline/filter/text-shadow** — différé v2.5+
- **Nouveaux tokens shadows** (si gap identifié) — différé v2.5+ sauf si critique
- **dompdf images embedded** (logo PDF, etc.) — hors scope, déjà géré
- **PDF accessibility** (PDF/UA tags) — différé v2.6+ accessibility milestone

</deferred>

---

*Phase: 04-print-tech-debt*
*Context gathered: 2026-05-04*
