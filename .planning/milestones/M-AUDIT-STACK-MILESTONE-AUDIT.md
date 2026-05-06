---
milestone: M-AUDIT-STACK
audited: 2026-05-05
status: passed
scores:
  requirements: 14/14 satisfied
  phases: 1/1 plans shipped
  audit_doc: .planning/STACK-AUDIT.md (722 lignes)
  boundary_check: PASS — git status sur app/ public/ database/ tests/ composer.json composer.lock Dockerfile vide
findings:
  verdict_global: "Voie A (refacto sur place) confirmée"
  composants_audited: 13
  keep: 11
  replace: 2
  remove: 1
  effort_court_terme_estimé: "~2.5 jours dev (M-INFRA-CLEANUP)"
  top_3_priorites_stage_3:
    - "Sessions PHP fichier → Redis (S, 1j) — ferme dette UX dogfood déconnexion redeploy"
    - "PhpSpreadsheet → OpenSpout import (S, 1j) — symétrie + EOL Q4 2026"
    - "Quick-wins : ext-gd remove + Parsedown → league/commonmark (XS+XS, <3h)"
  decouvertes_inattendues:
    - "Doc CLAUDE.md/STACK.md mention 'GD pour pixel email tracking' = faux (pixel = GIF base64 hardcodé)"
    - "Sessions PHP fichier /tmp non documentée comme dette PROJECT.md — bug DOG-001 latent"
    - "Stack XLSX asymétrique : OpenSpout export streaming + PhpSpreadsheet import in-memory"
---

# M-AUDIT-STACK — Milestone Audit

## Verdict

**Status : `passed`**

Stage 2 livré : `.planning/STACK-AUDIT.md` (722 lignes, 13 audits + synthèse). Verdict net : **Voie A (refacto sur place) confirmée**, pas de rebuild infra. 11 keep / 2 replace / 1 remove sur 13 composants. Effort total ~2.5 jours dev pour M-INFRA-CLEANUP.

## Top 3 priorités identifiées

1. **Sessions PHP fichier → Redis** (S, 1j) — bloquant UX dogfood
2. **PhpSpreadsheet → OpenSpout import** (S, 1j) — symétrie XLSX + EOL phpspreadsheet
3. **Quick-wins infra** : `ext-gd` remove + `parsedown` → `league/commonmark` (XS+XS)

## Décision user post-audit (2026-05-05)

**Ordre adopté : infra fix AVANT features 1.0.**

Roadmap révisée :
1. M-DECISION (formalise Voie A + scope fixes)
2. M-INFRA-CLEANUP (~2.5j : Sessions Redis + ext-gd remove + Parsedown→CommonMark + OpenSpout import)
3. M-Signature (Signature électronique PV)
4. M-VoteDistant (Vote distant token)
5. M-Stats (Stats cross-séance)

## Recommandation

`/gsd:complete-milestone M-AUDIT-STACK` — milestone audit-only, 1/1 plans done, 14/14 reqs satisfied, 0 gap.
