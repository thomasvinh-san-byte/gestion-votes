---
phase: 01-audit-chemin-critique
plan: 01
subsystem: audit
tags: [audit, static-analysis, critical-path, m-audit-chemin, stage-1, pivot]
requires: []
provides:
  - .planning/CRITICAL-PATH-AUDIT.md
affects: []
tech-stack:
  added: []
  patterns:
    - "Audit statique en sandbox (lecture code + tests + recoupement archives)"
    - "Verdicts 4-niveaux ✓/⚠/✗/❓ + impact 4-niveaux 🛑/🔴/🟡/⚪"
    - "Boundary stricte audit-only (zéro modification production)"
key-files:
  created:
    - .planning/CRITICAL-PATH-AUDIT.md
    - .planning/phases/01-audit-chemin-critique/01-01-SUMMARY.md
  modified: []
decisions:
  - "Verdict global Stage 1 : chemin critique fonctionnel à 7/11 ✓ + 3/11 ⚠ + 1/11 ✗ (élection multi-candidats)"
  - "Recommandation Stage 3 : Voie A (refacto sur place) + milestone M-ElectionMotion budgeté"
  - "Stage 2 priorités : audit dompdf 3.1, phpspreadsheet, composants custom (Router/Logger/IdempotencyGuard/RateLimiter)"
metrics:
  duration: "16 min"
  completed: "2026-05-06"
  tasks: 13
  commits: 13
  files_audited: 35
---

# Phase 01 Plan 01: M-AUDIT-CHEMIN Critical Path Audit Summary

Audit statique sandbox livré : 11 verdicts posés sur le chemin critique 12 étapes du flow user (setup → archive), avec 7 ✓ / 3 ⚠ / 1 ✗ / 0 ❓, et synthèse Stage 2 + recommandation Stage 3 (Voie A refacto + M-ElectionMotion).

## Verdict global

**Majoritairement ✓ avec 1 ✗ structurel et 3 ⚠ techniquement saines.** L'application AgVote est solide sur le périmètre vote résolutif simple (For/Against/Abstain/NSP) mais le périmètre fonctionnel demandé par REQUIREMENTS dépasse ce qui est implémenté (élection multi-candidats absent).

## Compteurs synthèse

- **✓ Verts** : 7 étapes (01 setup, 04 transition, 05 quorum, 06 vote résolution, 09 close, 10 PV PDF, 11 hash chain)
- **⚠ Ambres** : 3 étapes (02 import CSV, 03 création séance, 08 procuration)
- **✗ Rouges** : 1 étape (07 élection multi-candidats — non implémenté)
- **❓ Inconnus** : 0 (lecture statique a permis de trancher partout)

## Top 3 bloquants identifiés

1. **🛑 Étape 07 (✗) : Élection multi-candidats non implémentée** — pas de `motion.kind` au schema, pas de table `candidates`, pas de scrutin majoritaire dans VoteEngine. Si la 1re asso pilote a une élection à son AG (très courant : élection bureau, conseil d'administration), workaround "1 motion par candidat" dégrade UX et perd la sémantique électorale.
2. **🛑 Étape 03 (⚠ → potentiellement 🛑)** : Création motion ne supporte que `title`/`description`/`secret`. Les types "élection" et "question ouverte" demandés par REQUIREMENTS sont silencieusement ignorés (même gap que étape 07, vu côté création).
3. **🟡 Étape 08 : Incohérence cap procuration** — `proxy_max_per_receiver` default 3 dans `ImportController.php:50` mais 99 dans `ProxiesService.php:73`. Bug latent à fixer Stage 3.

## Recommandation Stage 3

**Voie A (refacto sur place) — RECOMMANDÉE en première intention.**

Arguments :
- 7/11 étapes ✓, 3/11 ⚠ techniquement saines, 1/11 ✗ identifié et localisé.
- Architecture défensive bien établie (TOCTOU, hash chain en trigger PG, transactions, idempotence).
- Coût refacto estimé ~3 semaines :
  - Bug `proxy_max_per_receiver` : 1 ligne.
  - Clarification `MeetingReportService` legacy : 1 PR.
  - 6 tests `MeetingsControllerTest` failures (PROJECT.md) : 1-2 jours.
  - Feature `M-ElectionMotion` (motion.kind + table candidates + ballot multi-choix + scrutin majoritaire 1 tour) : ~2 semaines dev.

**Voie B (rebuild partiel)** envisageable si Stage 2 révèle perf catastrophique sur dompdf ou stack PHP-FPM.

**Voie C (rebuild from scratch)** déconseillée — aucun signal de défaut architectural systémique dans l'audit. Le coût (6+ mois) ne justifie pas, et perd 28 milestones de hardening cumulés.

## Recommandation Stage 2 (priorités)

1. **HAUTE** — `dompdf 3.1` runtime (PV ≥ 10 pages avec accents et procuration, perf < 5s).
2. **HAUTE** — `phpoffice/phpspreadsheet` mémoire (envisager retirer XLSX si > 50 Mo footprint, garder CSV).
3. **MOYENNE** — composants custom AgVote (Router/Logger/IdempotencyGuard/RateLimiter/AccountLockout/CsrfMiddleware) : keep si bien testés et stables.
4. **MOYENNE** — phpredis disponibilité Docker Alpine 3.21, fallback filesystem SSE.
5. **MOYENNE** — `symfony/mailer ^8.0` compat PHP 8.4.
6. **BASSE** — hash chain custom PG-trigger : garder (solution solide, pas de raison de remplacer).
7. **BASSE** — HTMX 2.0.6 : garder (validé en v2.0/v2.7).

## Boundary respectée

Cet audit n'a modifié aucun fichier de production (`app/`, `public/`, `database/`, `tests/`, `composer.*`).

Vérification :
```bash
$ git status --porcelain app/ public/ database/ tests/ composer.json composer.lock
# (vide)
```

Tickets de fix (proxy cap, MeetingsController legacy tests, MeetingReportService dedup, élection feature) = livrable Stage 3 (M-DECISION).

## Self-Check: PASSED

- ✓ Fichier `.planning/CRITICAL-PATH-AUDIT.md` existe (1165 lignes)
- ✓ 12 IDs `AUDIT-CHEMIN-NN` tous présents (01..12)
- ✓ 12 sections H2 (11 étapes + 1 synthèse)
- ✓ 11 verdicts posés (un par étape, la synthèse n'a pas de verdict propre)
- ✓ Stage 2 mentionné 20 fois, Stage 3 mentionné 19 fois, Voie A/B/C mentionné 9 fois
- ✓ Aucun terme proscrit user-visible (CLAUDE.md compliance)
- ✓ Aucune modification de production (`git status --porcelain app/ public/ database/ tests/ composer.json composer.lock` vide)
- ✓ 13 commits atomiques par task, dans l'ordre

## Lien

Document d'audit complet : [`.planning/CRITICAL-PATH-AUDIT.md`](../../CRITICAL-PATH-AUDIT.md)

## Deviations from Plan

None - plan executed exactly as written.

Note : la rédaction de l'étape 02 a relevé deux références au vocabulaire proscrit par CLAUDE.md (étapes 02 et 05). Reformulé en task 13 (boundary verification) pour conformité, sans toucher aux verdicts ni aux contenus analytiques.
