---
phase: 01-audit-stack
plan: 01
subsystem: infra
tags: [audit, stack, dompdf, openspout, phpspreadsheet, parsedown, symfony-mailer, redis, postgres, docker, sse, router, logger, idempotency]

requires:
  - phase: M-AUDIT-CHEMIN (Stage 1)
    provides: Audit chemin critique (étape 02 import CSV/XLSX, étape 10 PV PDF) — recoupement utilisé pour AUDIT-STACK-02 et AUDIT-STACK-01
provides:
  - .planning/STACK-AUDIT.md (722 lignes) — 13 audits + 1 synthèse, verdicts keep/replace/remove avec coût/bénéfice et top 3 priorités Stage 3
  - Verdict global "Voie A (refacto sur place) confirmée" pour Stage 3 (M-DECISION)
  - Identification ext-gd inutilisée (doc STACK.md/CLAUDE.md inexactes)
  - Identification dette latente sessions PHP fichier /tmp
affects: [M-DECISION, M-INFRA-CLEANUP, M-Signature, M-VoteDistant, M-Stats]

tech-stack:
  added: []
  patterns:
    - "Audit statique sandbox sans modification production"
    - "Format audit standardisé : Rôle / Sites / Version / Alternatives évaluées / Verdict / Coût / Bénéfice / Recommandation"

key-files:
  created:
    - .planning/STACK-AUDIT.md
    - .planning/phases/01-audit-stack/01-01-PLAN.md
    - .planning/phases/01-audit-stack/01-01-SUMMARY.md
  modified: []

key-decisions:
  - "Voie A (refacto sur place) confirmée : aucun gap structurel ne justifie migration langage/framework"
  - "11 components keep / 2 replace / 1 remove : custom code AgVote (Router/Logger/IdempotencyGuard/Http/SSE) reconnu comme asset, pas dette"
  - "Top 3 priorités Stage 3 : sessions Redis (S), PhpSpreadsheet→OpenSpout import (S), ext-gd remove + Parsedown→commonmark (XS+XS)"
  - "Total effort Stage 3 court-terme : ~2,5 jours dev — non bloquant pour les 3 features 1.0"

patterns-established:
  - "Pattern audit statique sandbox : aucun fichier production touché, verdict + coût + bénéfice quantifiés par composant"
  - "Pattern recoupement Stage N → Stage N+1 : audit chemin (Stage 1) référencé dans audit stack (Stage 2)"

requirements-completed: [AUDIT-STACK-01, AUDIT-STACK-02, AUDIT-STACK-03, AUDIT-STACK-04, AUDIT-STACK-05, AUDIT-STACK-06, AUDIT-STACK-07, AUDIT-STACK-08, AUDIT-STACK-09, AUDIT-STACK-10, AUDIT-STACK-11, AUDIT-STACK-12, AUDIT-STACK-13, AUDIT-STACK-14]

duration: 13 min
completed: 2026-05-06
---

# Phase 01 Plan 01 — Audit stack technique (Stage 2 post-pivot) Summary

**Audit statique sandbox de 13 composants stack (5 deps Composer, 5 customs AgVote, 3 infra) + synthèse : verdict Voie A confirmée, 11 keep / 2 replace / 1 remove, ~2,5 jours dev d'actions Stage 3 identifiées.**

## Performance

- **Duration:** 13 min
- **Started:** 2026-05-06T06:10:19Z
- **Completed:** 2026-05-06T06:23:07Z
- **Tasks:** 14 (13 audits + 1 synthèse)
- **Files modified:** 3 (PLAN.md, STACK-AUDIT.md, SUMMARY.md — tous dans `.planning/`)

## Accomplishments

- **`.planning/STACK-AUDIT.md` livré** (722 lignes) couvrant les 14 reqs AUDIT-STACK-01..14, avec format standardisé (Rôle / Sites / Version / Alternatives / Verdict / Coût / Bénéfice / Recommandation) pour chaque ligne.
- **Verdict global Voie A (refacto sur place) confirmé** sur la base statique : aucun gap structurel justifiant migration langage/framework.
- **3 actions concrètes Stage 3 chiffrées** (~2,5 jours dev cumul) regroupables dans un milestone unique "M-INFRA-CLEANUP" non-bloquant pour les features 1.0.
- **2 découvertes audit non explicitées dans REQUIREMENTS.md initial** : (a) stack XLSX dual-track OpenSpout/PhpSpreadsheet asymétrique, (b) ext-gd installée mais inutilisée (pixel tracking = GIF base64).

## Task Commits

1. **PLAN minimal** — `859654c` (docs)
2. **AUDIT-STACK-01 dompdf** → keep — `7390694` (docs)
3. **AUDIT-STACK-02 phpspreadsheet** → replace par OpenSpout — `cdc1247` (docs)
4. **AUDIT-STACK-03 parsedown** → replace par league/commonmark — `c2454f1` (docs)
5. **AUDIT-STACK-04 symfony/mailer** → keep — `89858b9` (docs)
6. **AUDIT-STACK-05 PHP extensions** → keep 7, remove gd — `7459dc3` (docs)
7. **AUDIT-STACK-06 Router custom** → keep — `397808a` (docs)
8. **AUDIT-STACK-07 Logger custom** → keep — `8399663` (docs)
9. **AUDIT-STACK-08 IdempotencyGuard** → keep — `e86536e` (docs)
10. **AUDIT-STACK-09 Http/* primitives** → keep — `f6effe9` (docs)
11. **AUDIT-STACK-10 SSE custom** → keep — `eb2225e` (docs)
12. **AUDIT-STACK-11 Redis** → keep + recommandation sessions — `7732c73` (docs)
13. **AUDIT-STACK-12 PostgreSQL** → keep — `bff4c54` (docs)
14. **AUDIT-STACK-13 Docker** → keep (FrankenPHP différé) — `6fd6c3b` (docs)
15. **AUDIT-STACK-14 synthèse** → Voie A confirmée — `f6c3d24` (docs)

## Files Created/Modified

- `.planning/STACK-AUDIT.md` (722 lignes) — Livrable principal : audit stack technique complet
- `.planning/phases/01-audit-stack/01-01-PLAN.md` (92 lignes) — Plan minimal contracté (14 tasks)
- `.planning/phases/01-audit-stack/01-01-SUMMARY.md` (ce fichier)

## Decisions Made

### Verdicts par composant

| Composant | Verdict | Rationale |
|---|---|---|
| dompdf v3.1.4 | **keep** | Hardening F16 en place, alternatives (mPDF/Typst/headless Chromium) défavorables au ratio coût/bénéfice |
| PhpSpreadsheet v1.30.2 | **replace** par OpenSpout Reader (S, 1j) | EOL Q4 2026, asymétrie mémoire injustifiée (export streaming OpenSpout / import in-memory PhpSpreadsheet), -5 Mo vendor |
| Parsedown v1.8.0 | **replace** par league/commonmark (XS, <2h) | Projet abandonné (pas de release depuis 2019), deprecation warnings PHP 8.4 actuellement masquées par rustine |
| symfony/mailer v8.0.4 | **keep** | Symfony 8.0 = LTS (release Nov 2025, support 2027), pas bleeding-edge contrairement à la suspicion initiale |
| ext PHP (gd) | **remove** (XS, <1h) | Aucun usage attesté ; STACK.md et CLAUDE.md doc inexactes (tracking pixel = GIF base64, pas GD) |
| ext PHP (7 autres) | **keep** | pdo_pgsql/pgsql/mbstring/redis/intl/zip/opcache toutes utilisées directement ou transitivement |
| Router custom 348L | **keep** | 162 routes à re-câbler = coût L pour gain marginal (named routes) ; améliorations incrémentales possibles sans migration |
| Logger custom 359L | **keep** | API domain-specific encode conventions (auth/security/api), migration v2.5 récente, wrapper PSR-3 XS suffisant si futur besoin tiers |
| IdempotencyGuard 87L | **keep** | Race condition courte non réaliste sur cible (~10-50 users concurrents) ; symfony/lock ajouterait code sans valeur fonctionnelle |
| Http/* primitives 644L | **keep** | 6 classes encodent règles sécurité spécifiques (ClientIp F02, UrlValidator anti-SSRF) = assets ; PSR-7/PSR-15 = coût XL refonte transverse |
| SSE custom 477L | **keep** | Boundary PROJECT.md "out-of-scope SSE >10 op" + contrainte single-container Docker bloquent migration Mercure/Centrifugo |
| Redis | **keep** + recommandation sessions Redis (S, 1j) | 8 usages nécessaires (rate-limit F02, lockout F13, security signals F21, SSE queue, idempotency, health) ; sessions PHP fichier /tmp = dette UX redéploy |
| PostgreSQL | **keep** | 149 index sur 30 tables, covering indexes v2.7 vérifiés présents, partial index utilisés à bon escient ; followup pg_stat_user_indexes en dogfood |
| Docker multi-stage | **keep** | Stack mature (apk upgrade musl, fail-fast guard, non-root, classmap-authoritative) ; FrankenPHP attractif mais demande prérequis et n'élimine pas supervisord |

### Verdict global

**✓ Voie A (refacto sur place) confirmée** — la stack tient, les customs AgVote sont des assets, les 3 actions identifiées sont des hygiène (effort total ~2,5 j).

### Top 3 priorités Stage 3

1. **Migrer sessions PHP fichier → Redis** (S, 1j) — ferme dette UX dogfood
2. **Remplacer PhpSpreadsheet → OpenSpout import** (S, 1j) — symétrie + sortie EOL
3. **Quick-wins infra** : retirer ext-gd + remplacer Parsedown (XS+XS, <3h cumul) — hygiène

## Deviations from Plan

**None - plan executed exactly as written.**

Le plan minimal (14 tasks atomiques + format d'audit standardisé) a été suivi à la lettre. Les seules adaptations sont des **enrichissements d'analyse** au sein du format prévu (par exemple, audit Task 02 a élargi le scope à OpenSpout déjà présent dans `composer.json` — découverte naturelle pendant l'inspection code, pas déviation au plan).

## Issues Encountered

**Aucun bloqueur.** Le tooling `gsd-sdk` n'a pas été utilisé (workflow contracté instructed dans le prompt orchestrator), `git status` direct + `Read`/`Edit` ont suffi. Boundary respectée : `git status` sur paths production (`app/ public/ database/ tests/ composer.json composer.lock Dockerfile`) est resté vide tout du long.

## User Setup Required

None — audit purement documentaire, aucune configuration externe requise.

## Next Phase Readiness

- **Stage 3 (M-DECISION)** débloqué : les deux audits (Stage 1 chemin + Stage 2 stack) sont livrés. Décision attendue : confirmer Voie A + scoper M-INFRA-CLEANUP (4 actions, ~2,5j) + démarrer M-Signature en parallèle.
- **STATE.md / ROADMAP.md** : non touchés par cet exécuteur (instructed dans le prompt). À updater par l'orchestrator/utilisateur lors du passage à Stage 3.
- **Aucun blocker** identifié. Aucun fix de production n'est nécessaire avant de démarrer M-Signature ou M-VoteDistant : la stack tient.

## Self-Check: PASSED

**Created files:**
- `FOUND: .planning/STACK-AUDIT.md` (722 lignes)
- `FOUND: .planning/phases/01-audit-stack/01-01-PLAN.md`
- `FOUND: .planning/phases/01-audit-stack/01-01-SUMMARY.md` (ce fichier)

**Commits verified (15 total) :**
- `FOUND: 859654c` plan
- `FOUND: 7390694` AUDIT-STACK-01
- `FOUND: cdc1247` AUDIT-STACK-02
- `FOUND: c2454f1` AUDIT-STACK-03
- `FOUND: 89858b9` AUDIT-STACK-04
- `FOUND: 7459dc3` AUDIT-STACK-05
- `FOUND: 397808a` AUDIT-STACK-06
- `FOUND: 8399663` AUDIT-STACK-07
- `FOUND: e86536e` AUDIT-STACK-08
- `FOUND: f6effe9` AUDIT-STACK-09
- `FOUND: eb2225e` AUDIT-STACK-10
- `FOUND: 7732c73` AUDIT-STACK-11
- `FOUND: bff4c54` AUDIT-STACK-12
- `FOUND: 6fd6c3b` AUDIT-STACK-13
- `FOUND: f6c3d24` AUDIT-STACK-14

**Boundary verified:** `git status app/ public/ database/ tests/ composer.json composer.lock Dockerfile` retourne vide.

---
*Phase: 01-audit-stack*
*Completed: 2026-05-06*
