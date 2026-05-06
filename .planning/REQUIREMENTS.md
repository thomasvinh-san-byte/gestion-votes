# Requirements: AgVote — M-INFRA-CLEANUP (foundation post-pivot)

**Defined:** 2026-05-05
**Core Value:** Le secrétaire de séance fait en 5 clics ce qui prenait 1h en papier — avec une traçabilité légale au moins équivalente au procès-verbal manuscrit.

**Goal :** Foundation propre AVANT les features 1.0. Fixer les 3 ⚠ identifiés Stage 1 (M-AUDIT-CHEMIN) + livrer les 4 priorités Stage 2 (M-AUDIT-STACK). Effort ~2.5-3 jours dev, divisé en 3 phases parallélisables.

**Référence :** `.planning/DECISION.md` (Stage 3) pour le scope formel.

## v1 Requirements

### Phase 1 — Sessions Redis (P0, bloquant UX dogfood)

- [x] **CLEANUP-SESSIONS-01** : Configurer PHP `session.save_handler = redis` + `session.save_path = "tcp://redis:6379?database=1"` via `Dockerfile` ou `php.ini` custom. Vérifier que phpredis extension est chargée (déjà OK per AUDIT-STACK-05).
- [x] **CLEANUP-SESSIONS-02** : Migration hard-cutover (acceptable car pas de prod live). Documenter dans CHANGELOG.md ou `.planning/intel/SESSIONS-MIGRATION.md` les implications opérationnelles (TTL Redis = `session.gc_maxlifetime`, basique flush sur upgrade Redis).
- [x] **CLEANUP-SESSIONS-03** : Test PHPUnit + Playwright qui prouvent persistance sessions au redeploy. Test scenario : login → `docker compose down && docker compose up` (sans `-v`) → session toujours active. Cible : test E2E `tests/e2e/specs/session-persistence.spec.js`.

### Phase 2 — Fixes chemin critique (3 ⚠ Stage 1)

- [x] **CLEANUP-CHEMIN-IMPORT** : Fixer edge cases import CSV/XLSX identifiés AUDIT-CHEMIN-02. Investigation détaillée dans `.planning/archive-pre-pivot-2026-05-05/CRITICAL-PATH-AUDIT.md` étape 02. Probables : dédoublonnage email case-insensitive, encodings (Latin-1 / UTF-8 BOM), validation poids vote bornes. Test PHPUnit ciblé.
- [x] **CLEANUP-CHEMIN-MOTION-KIND** : Ajouter colonne `motion.kind` (default `'resolution'`) via migration DB + adapter `MotionRepository::create()` + UI passe la valeur. Préserve compat retrofit (toutes les motions existantes deviennent `'resolution'` par défaut). Pas de scrutin majoritaire, pas de table candidates — juste la colonne pour permettre l'expression du gap dans le schéma. Test PHPUnit + migration idempotente.
- [x] **CLEANUP-CHEMIN-PROCURATION** : Vérifier + fixer la cap incohérence latente identifiée AUDIT-CHEMIN-08. Étape probable : revoir `ProxyService::canDelegate()` ou équivalent pour s'assurer que cap `max_proxies_per_member` est respecté à la fois côté validation client et côté service métier. Test PHPUnit qui prouve qu'un membre ne peut pas porter plus de N procurations.

### Phase 3 — Quick-wins infra (Stage 2 priorités)

- [x] **CLEANUP-INFRA-DOC** : Fix doc `CLAUDE.md` / `STACK.md` mention "GD pour pixel email tracking" — incorrecte (pixel = GIF base64 hardcodé). Réviser sections concernées.
- [x] **CLEANUP-INFRA-GD-REMOVE** : Retirer `ext-gd` du `Dockerfile` + `composer.json` `config.platform` + adapter doc. Vérifier qu'aucun `imagecreate*()` ou similaire n'est utilisé dans le code (AUDIT-STACK-05 a confirmé : non utilisé). PHP `php -l` + tests existants doivent rester verts.
- [x] **CLEANUP-INFRA-PARSEDOWN** : Remplacer `erusev/parsedown` ^1.8 par `league/commonmark` ^2.x. Identifier les 1-2 sites d'usage (probablement `EmailTemplateService` ou similar), adapter signature API (`Parsedown->text($md)` → `$converter->convert($md)->getContent()`). Composer `composer remove erusev/parsedown` + `composer require league/commonmark`. Test PHPUnit qui prouve rendu équivalent sur fixture markdown.
- [x] **CLEANUP-INFRA-OPENSPOUT-IMPORT** : Migrer le path import XLSX (probablement `XlsxImporter` ou `ImportService`) de `phpspreadsheet` vers `openspout/openspout` (déjà dépendance pour export). API streaming au lieu de in-memory. `composer remove phpoffice/phpspreadsheet` + adapter code import + test PHPUnit fixture XLSX.

## v2 Requirements (post M-INFRA-CLEANUP)

À définir post-M-INFRA-CLEANUP sur base feedback dogfood ou priorités features 1.0.

## Out of Scope (cette milestone)

| Feature | Reason |
|---------|--------|
| Implémenter Signature électronique PV | Milestone séparée M-Signature, post-CLEANUP |
| Implémenter Vote distant token | Milestone séparée M-VoteDistant, post-Signature |
| Implémenter Stats cross-séance | Milestone séparée M-Stats, post-VoteDistant |
| Élection multi-candidats / scrutin majoritaire | Hors-scope pivot — décision user 2026-05-05 |
| Polish UI/UX/visual | Pas dans le scope foundation cleanup ; le polish v2.7 a déjà nettoyé l'essentiel |
| Refactor Custom Router/Logger/IdempotencyGuard/Http/SSE | Stage 2 a confirmé : keep. Pas de refactor préventif |
| Optimisation perf pure (au-delà des refactos imposés par OpenSpout streaming) | Hors-scope — perf déjà adressée v2.7 (N+1 + ETag) |

## Traceability

| Requirement | Phase | Status |
|-------------|-------|--------|
| CLEANUP-SESSIONS-01 | Phase 1 | Shipped |
| CLEANUP-SESSIONS-02 | Phase 1 | Shipped |
| CLEANUP-SESSIONS-03 | Phase 1 | Shipped |
| CLEANUP-CHEMIN-IMPORT | Phase 2 | Shipped |
| CLEANUP-CHEMIN-MOTION-KIND | Phase 2 | Shipped |
| CLEANUP-CHEMIN-PROCURATION | Phase 2 | Shipped |
| CLEANUP-INFRA-DOC | Phase 3 | Shipped |
| CLEANUP-INFRA-GD-REMOVE | Phase 3 | Shipped |
| CLEANUP-INFRA-PARSEDOWN | Phase 3 | Shipped |
| CLEANUP-INFRA-OPENSPOUT-IMPORT | Phase 3 | Shipped |

**Coverage :**
- v1 requirements : 10 total
- Mapped to phases : 10 (déjà phasé en 3 phases parallélisables)

---
*Requirements defined : 2026-05-05*
*Première vraie milestone de BUILD post-pivot — foundation propre avant features 1.0.*
