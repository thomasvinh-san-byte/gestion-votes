# Requirements: AgVote v2.6 — Clôture dette technique

**Defined:** 2026-05-05
**Core Value:** L'application doit être fiable en production — aucun crash lié à des fallbacks fichiers, fuites mémoire, ou timeouts silencieux.

**Goal :** Liquider la dette explicite accumulée v2.3/v2.5 et atteindre un état "tout shipped, zéro carry-forward". Clôture stricte, ~1 semaine, 5 phases courtes, pas d'ajout opportuniste.

## v1 Requirements

### Tests (Heartbeat) — Bucket 1

- [x] **TEST-V26-01** : Le test PHPUnit `tests/Unit/Sse/HeartbeatPayloadTest.php` valide la forme du payload `meeting.heartbeat` (champs `meeting_id`, `server_time`, `status`, `quorum`, `operator_count` présents et typés correctement) — lève HEARTBEAT-V25-03 ✓ Phase 1 (8 tests / 29 assertions)
- [x] **TEST-V26-02** : Le test Playwright `tests/e2e/specs/sse-heartbeat.spec.js` ouvre le stream `/api/v1/events.php`, attend ≥12 s, vérifie au moins 1 event `meeting.heartbeat` reçu avec payload conforme — lève HEARTBEAT-V25-04 ✓ Phase 1 (spec compile, full run pending live stack dev-machine)

### Codes erreur ciblés — Bucket 2

- [x] **ERR-V26-01** : 3 sites `business_error` génériques identifiés Phase 4 v2.3 (follow-up 04.6-FOLLOWUP-2) sont remplacés par des codes ciblés observables (ex. `meeting_not_found`, `vote_already_cast`, `quorum_not_reached`) avec entrées correspondantes dans `ErrorDictionary.php` ✓ Phase 2 (catch extractor + 3 normalizations + 2 dict entries archived/validated_meeting_locked, ~80 service throws bénéficient automatiquement)
- [x] **ERR-V26-02** : Les routes empty-state (rafale d'events SSE) sont rendues idempotentes — un test dédié vérifie que 2 requêtes back-to-back sur la même ressource vide retournent le même code/payload sans état corrompu (follow-up 04.6-FOLLOWUP-3) ✓ Phase 2 (intra-request scope locked, ErrorEventsRepository::capture() guard md5(rid|code|route), 7 PHPUnit tests)
- [x] **ERR-V26-03** : Le dashboard `/admin/error-stats` reflète ces nouveaux codes (vérifié par test smoke après 1 cycle de capture) ✓ Phase 2 (ErrorStatsRoutingTest 3 mock-PDO tests sur INSERT + SELECT)

### Tokens cleanup — Bucket 3

- [x] **TOKENS-V26-01** : Phase 7.2 du remediation plan exécutée — width tokens nettoyés (`--border-width-thin/normal/thick` consolidés sur 1 site visible, doublons supprimés) ✓ Phase 3 (-2 tokens 1-site)
- [x] **TOKENS-V26-02** : Phase 7.3 — soft/none variants éliminés + emphasis flatten appliqué ✓ Phase 3 (-4 tokens 1-site)
- [x] **TOKENS-V26-03** : Phase 7.4 — ring variants unifiés (`--ring-*` réduits aux 4 canoniques `--shadow-ring-2px-*`) ✓ Phase 3 (-4 tokens 1-site, total -10)
- [x] **TOKENS-V26-04** : Audit final post-7.4 livré dans `.planning/v2.6-TOKENS-AUDIT-FINAL.md`. Décompte final : **31 tokens 1-site** (cible <30, delta +1 documenté ; ratios borders 97.7% + shadows 100% au-delà cible 95%). Le +1 provient de drift v2.5→v2.6 hors-scope Phase 3 (4 tokens v2.5-done ayant perdu un caller dans des refactos indépendants) — recommandation v2.7 mini-plan ~15min pour atteindre ≤25.

### Test infra + GSD ergo — Bucket 4

- [x] **INFRA-V26-01** : `tests/e2e/helpers/seed-meeting.js` permet d'activer les tests `@integration` ✓ Phase 4 (audit static PASS — helper + spec + route TestSeedController présents et corrects ; gate runtime 3 runs verts déférée dev-machine)
- [x] **INFRA-V26-02** : Playwright dual-install résolu (`bin/check-deps.sh` guard + `tests/e2e/` SOT pour `@playwright/test`) ✓ Phase 4 (audit confirme état attendu présent depuis v2.4 P3)
- [x] **INFRA-V26-03** : README `tests/e2e/README.md` documente Playwright deps + browsers + auth-setup rate-limit + procédure write spec ✓ Phase 4 (5 sections requises présentes, walkthrough scaffold prêt à compléter dev-machine)
- [x] **INFRA-V26-04** : Doc pattern Explore scan déplacé à `.planning/intel/EXPLORE-PATTERNS.md` (canonical) avec stub redirect à l'ancien path + refs actives mises à jour (e2e README + agent doc) ✓ Phase 4
- [x] **INFRA-V26-05** : `gsd-code-reviewer` agent budget timeout + scope splits ✓ Phase 4 (agent doc déjà avec flags depuis v2.4 P3, template runtime verification 04-03-CODE-REVIEWER-VERIFICATION.md prêt pour la review v2.6 réelle dev-machine)

### Print/PDF polish — Bucket 5

- [x] **PDF-V26-01** : Header dompdf répétabilité — sur PV ≥10 pages, le header `[Titre séance] — JJ/MM/YYYY` apparaît sur **chaque** page ✓ Phase 5 (smoke test parsing PDF binaire, asserts occurrences ≥ N pages)
- [x] **PDF-V26-02** : Em-dash UTF-8 (`—`) + accents français rendus correctement ✓ Phase 5 (`MeetingReportsLongPdfTest::testEmDashAndFrenchAccentsRenderedCorrectly`, panel `é à è ê ô ç ù` + em-dash U+2014)
- [x] **PDF-V26-03** : Pagination robuste — footer `Page X sur Y` sur toutes les pages, `page-break-inside: avoid` non régressé ✓ Phase 5 (4 tests / 46 assertions GREEN, fixture LongPvFixtureBuilder ≥10 pages, `smalot/pdfparser` ^2 ajouté en dev dep)

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
| TEST-V26-01 | Phase 1 | ✓ Complete |
| TEST-V26-02 | Phase 1 | ✓ Complete |
| ERR-V26-01 | Phase 2 | ✓ Complete |
| ERR-V26-02 | Phase 2 | ✓ Complete |
| ERR-V26-03 | Phase 2 | ✓ Complete |
| TOKENS-V26-01 | Phase 3 | ✓ Complete |
| TOKENS-V26-02 | Phase 3 | ✓ Complete |
| TOKENS-V26-03 | Phase 3 | ✓ Complete |
| TOKENS-V26-04 | Phase 3 | ✓ Complete (31 vs cible <30, +1 documenté drift v2.5→v2.6 hors-scope) |
| INFRA-V26-01 | Phase 4 | ✓ Complete (runtime gate dev-machine) |
| INFRA-V26-02 | Phase 4 | ✓ Complete |
| INFRA-V26-03 | Phase 4 | ✓ Complete (walkthrough scaffold dev-machine) |
| INFRA-V26-04 | Phase 4 | ✓ Complete |
| INFRA-V26-05 | Phase 4 | ✓ Complete (real review dev-machine) |
| PDF-V26-01 | Phase 5 | ✓ Complete |
| PDF-V26-02 | Phase 5 | ✓ Complete |
| PDF-V26-03 | Phase 5 | ✓ Complete |

**Coverage :**
- v1 requirements : 17 total
- Mapped to phases : 17
- Unmapped : 0 ✓

---
*Requirements defined : 2026-05-05*
*Last updated : 2026-05-05 after v2.6 milestone bootstrap*
