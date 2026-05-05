# Requirements: AgVote v2.6 — Clôture dette technique

**Defined:** 2026-05-05
**Core Value:** L'application doit être fiable en production — aucun crash lié à des fallbacks fichiers, fuites mémoire, ou timeouts silencieux.

**Goal :** Liquider la dette explicite accumulée v2.3/v2.5 et atteindre un état "tout shipped, zéro carry-forward". Clôture stricte, ~1 semaine, 5 phases courtes, pas d'ajout opportuniste.

## v1 Requirements

### Tests (Heartbeat) — Bucket 1

- [ ] **TEST-V26-01** : Le test PHPUnit `tests/Unit/Sse/HeartbeatPayloadTest.php` valide la forme du payload `meeting.heartbeat` (champs `meeting_id`, `server_time`, `status`, `quorum`, `operator_count` présents et typés correctement) — lève HEARTBEAT-V25-03
- [ ] **TEST-V26-02** : Le test Playwright `tests/e2e/specs/sse-heartbeat.spec.js` ouvre le stream `/api/v1/events.php`, attend ≥12 s, vérifie au moins 1 event `meeting.heartbeat` reçu avec payload conforme — lève HEARTBEAT-V25-04

### Codes erreur ciblés — Bucket 2

- [ ] **ERR-V26-01** : 3 sites `business_error` génériques identifiés Phase 4 v2.3 (follow-up 04.6-FOLLOWUP-2) sont remplacés par des codes ciblés observables (ex. `meeting_not_found`, `vote_already_cast`, `quorum_not_reached`) avec entrées correspondantes dans `ErrorDictionary.php`
- [ ] **ERR-V26-02** : Les routes empty-state (rafale d'events SSE) sont rendues idempotentes — un test dédié vérifie que 2 requêtes back-to-back sur la même ressource vide retournent le même code/payload sans état corrompu (follow-up 04.6-FOLLOWUP-3)
- [ ] **ERR-V26-03** : Le dashboard `/admin/error-stats` reflète ces nouveaux codes (vérifié par test smoke après 1 cycle de capture)

### Tokens cleanup — Bucket 3

- [ ] **TOKENS-V26-01** : Phase 7.2 du remediation plan exécutée — width tokens nettoyés (`--border-width-thin/normal/thick` consolidés sur 1 site visible, doublons supprimés)
- [ ] **TOKENS-V26-02** : Phase 7.3 — soft/none variants éliminés (pas de `--shadow-soft`, `--shadow-none` séparé de `--shadow-0`), emphasis flatten appliqué
- [ ] **TOKENS-V26-03** : Phase 7.4 — ring variants unifiés (`--ring-*` réduits à 1-2 tokens canoniques)
- [ ] **TOKENS-V26-04** : Audit final post-7.4 confirme **<30 tokens 1-site** (cible du plan), nouveau snapshot `.planning/v2.6-TOKENS-AUDIT-FINAL.md`

### Test infra + GSD ergo — Bucket 4

- [ ] **INFRA-V26-01** : `tests/e2e/helpers/seed-meeting.js` permet d'activer les tests `@integration` (test F-4 de Phase 1 v2.4 — actuellement skippé faute de fixture)
- [ ] **INFRA-V26-02** : Playwright dual-install résolu — un seul `npm install` à la racine ou doc claire qui explique la double install (`tests/e2e/`)
- [ ] **INFRA-V26-03** : README `tests/e2e/README.md` documente Playwright deps, browsers (chromium/firefox/webkit), auth-setup rate-limit, comment écrire un nouveau spec
- [ ] **INFRA-V26-04** : Doc pattern Explore scan dans `.planning/intel/` ou équivalent — anti faux-positifs BEM substring (Phase 3 Schoger S-8)
- [ ] **INFRA-V26-05** : `gsd-code-reviewer` agent reçoit budget timeout configurable + scope splits (`--files=js`, `--files=php`, `--files=tests`)

### Print/PDF polish — Bucket 5

- [ ] **PDF-V26-01** : Header dompdf répétabilité — sur PV ≥10 pages, le header `[Titre séance] — JJ/MM/YYYY` apparaît sur **chaque** page (pas seulement la première)
- [ ] **PDF-V26-02** : Em-dash UTF-8 (`—`) rendu correctement dans le PDF (pas de `?` ou `??`) — test smoke automatisé sur fixture PV avec accents et symboles français
- [ ] **PDF-V26-03** : Pagination robuste — footer `Page X sur Y` correct sur toutes les pages, pas de coupure de contenu en bas de page

## v2 Requirements (deferred / out-of-milestone)

Aucun — v2.6 est un milestone de clôture. Ce qui n'est pas dans v1 ci-dessus est explicitement out-of-scope.

## Out of Scope

| Feature | Reason |
|---------|--------|
| OPS dev-machine §3 (Playwright runs) | Dev-machine, pas de code à écrire — restera dans OPS-CHECKLIST jusqu'à exécution manuelle |
| OPS dev-machine §4 (visual inspection) | Idem — inspection browser, hors-code |
| OPS dev-machine §5 (cron schedule) | Idem — config sysadmin, hors-code |
| Nouvelles capacités métier (signature, archivage hash chain, procuration en lot…) | Milestone de clôture, pas d'ajout |
| Refactos opportunistes en cours de route | Clôture stricte — toute découverte ouvre une seed/todo, pas un ajout milestone |
| Migration vers framework | Hors charte projet (refactoring incrémental uniquement) |
| Logger context enrichment dashboards | ERR-monitoring du backlog v2.4 — différé, non bloquant pour la dette listée |

## Traceability

| Requirement | Phase | Status |
|-------------|-------|--------|
| TEST-V26-01 | Phase 1 | Pending |
| TEST-V26-02 | Phase 1 | Pending |
| ERR-V26-01 | Phase 2 | Pending |
| ERR-V26-02 | Phase 2 | Pending |
| ERR-V26-03 | Phase 2 | Pending |
| TOKENS-V26-01 | Phase 3 | Pending |
| TOKENS-V26-02 | Phase 3 | Pending |
| TOKENS-V26-03 | Phase 3 | Pending |
| TOKENS-V26-04 | Phase 3 | Pending |
| INFRA-V26-01 | Phase 4 | Pending |
| INFRA-V26-02 | Phase 4 | Pending |
| INFRA-V26-03 | Phase 4 | Pending |
| INFRA-V26-04 | Phase 4 | Pending |
| INFRA-V26-05 | Phase 4 | Pending |
| PDF-V26-01 | Phase 5 | Pending |
| PDF-V26-02 | Phase 5 | Pending |
| PDF-V26-03 | Phase 5 | Pending |

**Coverage :**
- v1 requirements : 17 total
- Mapped to phases : 17
- Unmapped : 0 ✓

---
*Requirements defined : 2026-05-05*
*Last updated : 2026-05-05 after v2.6 milestone bootstrap*
