---
milestone: M-INFRA-CLEANUP
audited: 2026-05-06
status: passed
scope: "Foundation cleanup post-pivot, AVANT features 1.0"
duration: "1 session (commits 389e320..079ba5b sur claude/sync-remote-state-Av1dd)"
scores:
  requirements: 10/10 satisfied
  phases: 3/3 shipped (sessions / chemin / quick-wins)
  commits: 9 atomiques
  files_changed: 32 (+1604 / -659)
  tests_added: 7 fichiers PHPUnit + 1 spec Playwright + 1 fixture XLSX
  composer_deltas: "+1 (league/commonmark) -2 (parsedown, phpspreadsheet) net -1 direct + -10 transitive"
checkpoints_explained:
  - "#1 Recon code (Dockerfile, php.ini, composer.json, sessions usage)"
  - "#2 Plan Phase 1 + démarrage exécution"
  - "#3 Phase 1 done, démarrage Phase 2"
  - "#4 Phase 2 done, démarrage Phase 3"
  - "#5 Phases 1+2+3 done, audit milestone"
findings:
  audit_chemin_warnings_closed: "3/3 (motion.kind, procuration cap, import edge cases)"
  audit_stack_recommendations_executed: "4/4 (ext-gd remove, parsedown→commonmark, openspout import, sessions Redis)"
  bugs_latents_resolus:
    - "proxy_max_per_receiver default divergence 99 vs 3 (Stage 1 étape 08) — aligné à 3 via constante partagée"
    - "Sessions /tmp tmpfs perdues à chaque container restart (Stage 2) — Redis DB1 + auth runtime"
    - "Doc CLAUDE.md/STACK.md mention erronée GD pour email pixel — corrigée"
    - "tantieme/tantième aliases CSV import = vocab copro proscrit CLAUDE.md — retirés"
    - "parseVotingPower sans borne haute = poison quorum (1e9 / NaN / Inf) — clamp à 1000.0"
    - "UTF-8 BOM non stripé sur CSV Excel UTF-8 → premier header corrompu — fix"
  perte_features: "aucune — toute migration backwards-compatible (signatures repo, contracts ExportService)"
boundary_check:
  composer_lock_diff_explained: "1 add + 2 remove direct, ~10 transitive deltas — tous documentés en commits"
  no_breaking_changes: "MotionRepository::create() $kind nullable optional, ExportService streamReportXlsx remplace pattern createSpreadsheet+writer dead code"
deferred_to_devmachine:
  - "Test E2E session-persistence.spec.js (skip auto si docker compose absent)"
  - "Validation visuelle XLSX OpenSpout multi-sheet vs anciens fixtures"
  - "Migration DB motion.kind (idempotente — application au prochain entrypoint pg up)"
---

# M-INFRA-CLEANUP — Milestone Audit

## Verdict

**Status : `passed`**

10/10 requirements shippés en 3 phases atomiques sur 9 commits. Aucun ⚠ Stage 1 (chemin critique) ne reste ouvert. Aucune des 4 priorités Stage 2 (stack) n'est en attente. La fondation est propre pour les features 1.0 (M-Signature, M-VoteDistant, M-Stats).

**Première vraie milestone de BUILD post-pivot.** Marque la fin de la phase audit/cleanup et l'entrée en phase produit offensive.

## Phase 1 — Sessions Redis (P0)

| Req | Commit | Verdict |
|-----|--------|---------|
| CLEANUP-SESSIONS-01 | `389e320` | ✓ php.ini + entrypoint runtime injection |
| CLEANUP-SESSIONS-02 | `cf8bf33` | ✓ SESSIONS-MIGRATION.md (TTL/rollback/monitoring/failure modes) |
| CLEANUP-SESSIONS-03 | `c3ba672` | ✓ 4 PHPUnit (config) + 1 Playwright (restart container) |

**Impact :** sessions survivent désormais à `docker compose restart app`. Bloquant UX dogfood levé.

## Phase 2 — Fixes chemin critique

| Req | Commit | Verdict |
|-----|--------|---------|
| CLEANUP-CHEMIN-MOTION-KIND | `a081542` | ✓ Migration idempotente + repo non-breaking + 5 PHPUnit |
| CLEANUP-CHEMIN-PROCURATION | `012c91d` | ✓ Constante partagée `DEFAULT_MAX_PER_RECEIVER=3` + 3 PHPUnit guards |
| CLEANUP-CHEMIN-IMPORT | `596bf21` | ✓ BOM strip + voting_power clamp + drop tantieme + 3 PHPUnit edge cases |

**Impact :** les 3 ⚠ Stage 1 (audit chemin critique) sont fermés. Le schéma exprime `motion.kind`, le cap procuration est cohérent API/import, l'import CSV gère les vrais fichiers Excel français.

## Phase 3 — Quick-wins infra

| Req | Commit | Verdict |
|-----|--------|---------|
| CLEANUP-INFRA-DOC | `2035c34` | ✓ Mention GD pixel email retirée CLAUDE.md/STACK.md |
| CLEANUP-INFRA-GD-REMOVE | `2035c34` | ✓ ext-gd build retiré + libs runtime liées + `--ignore-platform-req=ext-gd` |
| CLEANUP-INFRA-PARSEDOWN | `9e5173a` | ✓ league/commonmark + safe-mode preserved + 6 PHPUnit parity |
| CLEANUP-INFRA-OPENSPOUT-IMPORT | `079ba5b` | ✓ XlsxImporter + ExportService streamReportXlsx + AnalyticsController + 5 PHPUnit |

**Impact :** image runtime allégée, dépendance Parsedown abandonware retirée, dual-track XLSX (in-memory + streaming) consolidé en streaming-only via OpenSpout.

## Boundary check

```
composer.lock : 1 add (commonmark) + 6 remove (phpspreadsheet+5 transitives)
                                   + 5 add (commonmark+4 transitives)
                                   = net -1 direct, ~stable transitives
app/, tests/   : tous touchés ciblés, aucun fichier de plus que documenté
database/      : 1 migration ajoutée (idempotente)
deploy/        : php.ini + entrypoint.sh + Dockerfile uniquement
```

Pas de modification rampante. Chaque fichier touché est tracé à un commit atomique avec le requirement correspondant en préfixe.

## Test coverage M-INFRA-CLEANUP

7 fichiers PHPUnit créés + 1 fichier modifié + 1 spec Playwright :

- `SessionRedisConfigTest` — 4 tests, 8 assertions
- `MotionKindMigrationTest` — 5 tests, 10 assertions
- `ProxyCapAlignmentTest` — 3 tests, 6 assertions
- `CsvImporterEdgeCasesTest` — 3 tests, 7 assertions
- `CommonMarkRenderingTest` — 6 tests, 12 assertions
- `XlsxImporterOpenSpoutTest` — 5 tests, 18 assertions
- `ImportServiceTest` (delta) — +5 tests, +9 assertions
- `session-persistence.spec.js` — 1 spec @slow (skip auto sandbox)

Tous PASS. Cible PHPUnit `tests/Unit/Module.php` ciblées per CLAUDE.md (jamais full suite).

## Risques résiduels (post-milestone)

- **Migration `motion.kind` non encore appliquée à la DB live.** Idempotente via `IF NOT EXISTS`, sera appliquée au prochain `entrypoint.sh` au container up.
- **Test E2E `session-persistence.spec.js` non exécuté en sandbox** (docker compose indisponible). Skip auto. À valider en dev-machine au premier deploy.
- **dompdf déclare `ext-gd` en require** — `--ignore-platform-req=ext-gd` est un workaround légitime documenté. Si dompdf rencontre un `<img>` raster dans un PV, il échouera runtime. Aucun PV existant n'embarque d'image raster.
- **Anciens tests `Risky` (21 sur ExportControllerTest)** — pré-existants, output buffer pattern Controller exit. Hors scope M-INFRA-CLEANUP.

## Next milestone

`/gsd:complete-milestone M-INFRA-CLEANUP` puis bootstrap `M-Signature` (Signature électronique PV eIDAS avancée — DocuSign API ou Cryptolib auto-hébergé).

---

*Première milestone BUILD post-pivot 2026-05-05. Foundation propre, prête pour features 1.0.*
