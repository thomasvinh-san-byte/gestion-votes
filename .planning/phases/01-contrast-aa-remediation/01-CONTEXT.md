# Phase 1: Contrast AA Remediation - Context

**Gathered:** 2026-04-09
**Status:** Ready for planning
**Mode:** Auto (--auto flag — decisions pre-sourced from v1.3-A11Y-REPORT.md, v1.3-CONTRAST-AUDIT.json, research/SUMMARY.md, research/PITFALLS.md)

<domain>
## Phase Boundary

L'application atteint WCAG 2.1 AA contrast 4.5:1 sur toutes les paires fg/bg identifiées dans `.planning/v1.3-CONTRAST-AUDIT.json` (316 nœuds → 0), et `v1.3-A11Y-REPORT.md` est re-déclaré "WCAG 2.1 AA CONFORME" (plus "partial"). Le travail porte exclusivement sur les tokens du design-system, leur propagation aux critical-tokens inline blocks (22 .htmx.html), et le nettoyage des fallbacks hex dans les Shadow DOM.

**Non-objectifs de cette phase :** aucune migration HTMX, aucune modification de structure HTML, aucun refactor de composant, aucun changement d'architecture CSS (@layer order). Pur token remediation dual-theme.

</domain>

<decisions>
## Implementation Decisions

### Stratégie de Correction des Tokens
- **4 tokens ciblés en priorité** : `#988d7a` (muted-foreground), `#bdb7a9`, `#9d9381`, `#4d72d8` — identifiés par v1.3-A11Y-REPORT comme responsables de ~71% des 316 violations
- **Valeurs cibles en oklch L* 45-48** pour atteindre ratio ≥ 4.5 sur fond `#f6f5f0` en light mode (réf. v1.3-A11Y-REPORT §3)
- **Jamais renommer** un token existant — seulement ajuster la valeur OU ajouter un alias `--color-X-v2`. Raison : Shadow DOM `@property` `initial-value` swallow les renames (Pitfall #3, v10.0 incident)
- **Itération jusqu'à zéro** : après les 4 tokens principaux, re-run `contrast-audit.spec.js` et traiter les nœuds résiduels un par un si nécessaire

### Propagation Dual-Theme
- **`:root` et `[data-theme="dark"]` dans le MÊME commit** — jamais séparés. Raison : Pitfall #2, v10.0 Phase 84 a dû patcher 21 fichiers rétroactivement
- **Les 22 critical-tokens inline blocks (`<style id="critical-tokens">` dans `public/*.htmx.html`) sont mis à jour dans le MÊME commit** que `design-system.css` — enforcement par vérification pré-commit (grep des paires hex/oklch modifiées)
- **Dark mode validé autant que light** — axe audit doit passer sur les deux thèmes (contrast-audit.spec.js teste les deux)

### Shadow DOM Fallbacks
- **Retrait de tous les `var(--token, #hex)` avec fallback hex** dans les 23 Web Components — les tokens sont garantis présents via load order `shell.js` (précédent v1.3 Phase 14-02)
- **Pattern cible :** `var(--color-foo)` (sans second argument)
- **CI grep gate :** `grep -rE 'var\(--color-[^,)]*,\s*#' public/assets/js/components/` retourne 0 occurrence à la fin de la phase

### Vérification et Sortie
- **Baseline :** `.planning/v1.3-CONTRAST-AUDIT.json` (316 nœuds)
- **Cible :** 0 violation sur les 22 pages × 2 thèmes via `tests/e2e/specs/contrast-audit.spec.js` run via Docker (`bin/test-e2e.sh`) avec `CONTRAST_AUDIT=1`
- **Sortie :** `v1.3-A11Y-REPORT.md` mis à jour — "WCAG 2.1 AA CONFORME" (plus "partial"), timestamp 2026-04-09, référence au commit de remédiation
- **Regressions gate :** `accessibility.spec.js`, `keyboard-nav.spec.js`, `page-interactions.spec.js` doivent rester verts post-changement

### Claude's Discretion
- Choix exact des valeurs L* dans la plage 45-48 oklch (micro-ajustement par re-run axe)
- Ordre d'application des 4 tokens (probablement le plus touché en premier pour un max de progrès par commit)
- Découpage en sub-plans (par token ou par thème) — au planner de décider
- Gestion des nœuds résiduels hors-tokens principaux (cas par cas)

</decisions>

<code_context>
## Existing Code Insights

### Reusable Assets
- `tests/e2e/specs/contrast-audit.spec.js` — runner axe contrast gated par `CONTRAST_AUDIT=1` (v1.3 Phase 16-04)
- `bin/test-e2e.sh` — Docker Playwright runner (v1.3 Phase 16-02, host libs missing)
- `.planning/v1.3-CONTRAST-AUDIT.json` — baseline structurée des 316 violations (42 paires uniques)
- `.planning/v1.3-A11Y-REPORT.md` — rapport de conformité partielle à mettre à jour
- `axeAudit.js` matrix paramétré 22 pages via PAGES array (v1.3 Phase 16-01)

### Established Patterns
- **design-system.css** : 5,258 lignes, @layer base/components/v4, oklch comments trailing sur chaque primitive (v10.0 history)
- **color-mix(in oklch)** utilisé partout pour hover/active derivations (Phase 82-01)
- **Dark mode surface hue 78** (warm-neutral) cohérente avec light (Phase 82-token-foundation-palette-shift)
- **Pas de :root hex fallbacks dans les 23 Web Components** (v1.3 Phase 14-02 — shell.js load order garanti)
- **critical-tokens inline blocks dans 22 .htmx.html** — pattern établi depuis v10.0 Phase 84 (HARD-03)

### Integration Points
- `public/assets/css/design-system.css` — éditions `:root` + `[data-theme="dark"]`
- `public/*.htmx.html` × 22 — blocs `<style id="critical-tokens">`
- `public/assets/js/components/*.js` × 23 — retrait des fallbacks hex `var(--token, #hex)`
- `v1.3-A11Y-REPORT.md` — mise à jour conformance
- Pas de changement dans `app/` (zero PHP impact)

</code_context>

<specifics>
## Specific Ideas

- **4 tokens prioritaires** explicitement listés dans research/SUMMARY.md — démarrer par eux avant tout autre ajustement
- **Pire ratio actuel : 1.83 sur wizard** (v1.3-A11Y-REPORT) — utiliser comme canary : si wizard passe, la majorité passe
- **light et dark doivent évoluer ensemble** — le Phase 82-token-foundation-palette-shift a posé le pattern (surface hue 78 warm-neutral unifié)
- **color-mix(in oklch, base 88%, white) pour hover direction en dark** — pattern établi, ne pas casser

</specifics>

<deferred>
## Deferred Ideas

- **CSP `report-uri` pour violations contrast** — hors périmètre token remediation, à considérer si Phase 5 l'intègre (deferred v1.5+)
- **Visual regression testing avec snapshots de couleurs** — milestone séparé post-v1.4 pour rebaseline post-token-shift
- **Migration complète `color-mix(in srgb)` → `color-mix(in oklch)`** — si des résidus srgb existent hors scope des 4 tokens, les traiter lot par lot en déferré

</deferred>
