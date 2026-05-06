# Phase 15: Multi-Browser Tests - Context

**Gathered:** 2026-04-09
**Status:** Ready for planning
**Mode:** Auto-generated (infrastructure phase)

<domain>
## Phase Boundary

Etendre l'infrastructure Playwright (Phase 8) pour activer firefox, webkit et mobile-chromium en plus de chromium. Run les 25 critical-path specs sur les 4 browsers et fix toute divergence (CSS prefixes, focus behavior, layout differences).

</domain>

<decisions>
## Implementation Decisions

### Approche
- playwright.config.js a deja les 5 projects (chromium/firefox/webkit/mobile-chrome/tablet) declares depuis Phase 8
- bin/test-e2e.sh hardcode `--project=chromium` — il faut le rendre flexible
- mcr.microsoft.com/playwright:v1.59.1-jammy contient deja les 3 browsers (chromium + firefox + webkit) preinstalle
- Pas de nouveau setup Docker necessaire — juste config + script + execution

### Implementation
1. **bin/test-e2e.sh** : accepter un flag `--project` pour cibler un browser, defaut chromium
2. **Run cross-browser** : execute la suite sur firefox + webkit + mobile-chrome, capture les divergences
3. **Fix les divergences** : selectors qui ne marchent pas en firefox/webkit, focus differences, etc.
4. **Documentation** : produire `.planning/phases/15-multi-browser-tests/15-CROSS-BROWSER-REPORT.md` avec status par browser

### Out of scope
- WebKit (Safari) sur tablet/mobile reel : impossible sans Mac CI
- Internet Explorer / Edge legacy : abandonne
- Lighthouse perf cross-browser : pas dans Phase 15

</decisions>

<code_context>
## Existing Code Insights

### Playwright config
- tests/e2e/playwright.config.js : projects array has 5 entries (chromium, firefox, webkit, mobile-chrome, tablet)
- chromium project has launchOptions args pour disable HTTPS upgrade (Phase 9 fix)

### Script
- bin/test-e2e.sh : currently `--project=chromium` hardcode dans la commande

### Image Docker
- mcr.microsoft.com/playwright:v1.59.1-jammy : contient chromium + firefox + webkit + system deps

</code_context>

<specifics>
## Specific Ideas

- Tester d'abord SI les specs passent en l'etat sur firefox/webkit (run brut)
- Si oui : juste documenter et fermer
- Si non : faire les fixes minimum necessaires
- L'utilisateur a explicitement dit "polish" — pas de redesign cross-browser

</specifics>

<deferred>
None — infrastructure phase.

</deferred>
