---
phase: 02-codes-erreur-cibles
plan: 02
subsystem: error-capture
tags: [idempotency, error-events, repository, phpunit, tdd]
requires:
  - app/Repository/ErrorEventsRepository.php
  - app/Repository/AbstractRepository.php
provides:
  - "ErrorEventsRepository::capture() idempotent intra-requete"
  - "ErrorEventsRepository::resetIdempotencyCache() (test-only)"
affects:
  - app/api.php (api_fail() consommateur — comportement preserve)
  - tests/Unit/* (nouvelle suite ErrorEventsCaptureIdempotencyTest)
tech_stack_added: []
tech_stack_patterns:
  - "in-memory dedup keyed par md5(request_id|error_code|route)"
  - "static property scope-requete (RepositoryFactory singleton-per-request)"
key_files_created:
  - tests/Unit/ErrorEventsCaptureIdempotencyTest.php
key_files_modified:
  - app/Repository/ErrorEventsRepository.php
decisions:
  - "Guard pure in-memory (pas de Redis, pas de UNIQUE constraint DB) — error_events est best-effort, le scope intra-requete suffit a la success criteria #2 du ROADMAP"
  - "Skip guard si request_id OU route est null — preserve compat CLI/bootstrap"
  - "Cle = md5(rid|code|route) — payload exclu volontairement (rafales du meme handler avec payload legerement different doivent quand meme dedupe)"
metrics:
  tasks_completed: 1
  test_runs: 2
  duration_minutes: 5
  files_created: 1
  files_modified: 1
  completed_date: 2026-05-05
requirements:
  - ERR-V26-02
---

# Phase 02 Plan 02: Idempotency guard sur ErrorEventsRepository::capture() Summary

Guard in-memory pur sur `ErrorEventsRepository::capture()` keyed par `(request_id, error_code, route)` — empeche les rafales d'events SSE empty-state d'inscrire plusieurs lignes pour le meme tuple dans une seule requete HTTP, sans introduire de dependance Redis ni modifier le schema BDD.

## Modifications par fichier

### `app/Repository/ErrorEventsRepository.php` (modifie, +33 lignes)

- **Ligne 18-26 (nouvelle property)** : `private static array $captureSeenKeys = []` — cache d'idempotence in-memory, scope = duree de vie du `RepositoryFactory` singleton (= une requete HTTP).
- **Ligne 38-46 (corps de capture())** : guard ajoute en debut de methode. Si `requestId !== null/empty` ET `route !== null/empty`, on calcule `md5($requestId . '|' . $errorCode . '|' . $route)`. Si la cle existe deja dans `$captureSeenKeys`, on `return` (skip de l'INSERT). Sinon on l'enregistre puis on insere comme avant.
- **Ligne 76-83 (nouvelle methode)** : `public static function resetIdempotencyCache(): void` — utilitaire test-only documente avec `@internal` pour reset le cache entre tests / requetes simulees.

Aucun changement aux methodes `topCodesSince`, `timelineSince`, `storageStats`, `totalSince`. Imports `use AgVote\Core\Logger;` et `use Throwable;` inchanges.

### `tests/Unit/ErrorEventsCaptureIdempotencyTest.php` (cree, 93 lignes, 7 tests)

| Test | Cas couvert | Assertion |
| ---- | ----------- | --------- |
| `test_back_to_back_same_key_inserts_once` | 2 captures avec meme (rid, code, route) | `executeCount === 1` |
| `test_different_codes_same_request_inserts_twice` | meme rid+route, codes differents | `executeCount === 2` |
| `test_different_request_ids_inserts_twice` | meme code+route, rids differents | `executeCount === 2` |
| `test_null_request_id_skips_guard` | rid=null x2 | `executeCount === 2` (bypass) |
| `test_null_route_skips_guard` | route=null x2 | `executeCount === 2` (bypass) |
| `test_reset_cache_allows_re_insert` | reset entre 2 captures memes params | `executeCount === 2` |
| `test_payload_difference_does_not_break_dedupe` | meme (rid, code, route), payloads differents | `executeCount === 1` |

Le test exploite `createMock(PDO::class)` + `createMock(PDOStatement::class)` avec un callback sur `execute()` pour incrementer `$this->executeCount`. La signature `AbstractRepository::__construct(?PDO $pdo = null)` permet l'injection directe sans wrapper — aucune adaptation necessaire.

## Resultats des tests PHPUnit

```
$ timeout 60 php vendor/bin/phpunit tests/Unit/ErrorEventsCaptureIdempotencyTest.php --no-coverage
PHPUnit 10.5.63 by Sebastian Bergmann and contributors.
Runtime:       PHP 8.4.19
Configuration: phpunit.xml

.......                                                             7 / 7 (100%)

Time: 00:00.013, Memory: 12.00 MB

OK (7 tests, 8 assertions)
```

**Test runs consommes : 2/3 (CLAUDE.md max).** Run 1 = RED (7 erreurs attendues — methode `resetIdempotencyCache` pas encore en place), Run 2 = GREEN (7 OK).

## TDD Gate Compliance

| Gate | Commit | Status |
| ---- | ------ | ------ |
| RED | `92a1fe2` — `test(02-02): add failing tests...` | OK (7 erreurs comme attendu) |
| GREEN | `66417fb` — `feat(02-02): add in-memory idempotency guard...` | OK (7/7 tests passent) |
| REFACTOR | (non necessaire) | Code propre, aucun nettoyage requis |

## Verification syntaxique

```
$ php -l app/Repository/ErrorEventsRepository.php
No syntax errors detected
$ php -l tests/Unit/ErrorEventsCaptureIdempotencyTest.php
No syntax errors detected
```

## Acceptance Criteria

- [x] `php -l app/Repository/ErrorEventsRepository.php` exit 0
- [x] `php -l tests/Unit/ErrorEventsCaptureIdempotencyTest.php` exit 0
- [x] `grep -c captureSeenKeys` = 4 (>=2)
- [x] `grep -c resetIdempotencyCache` = 1 (>=1)
- [x] `grep -c md5(` = 1 (>=1)
- [x] `phpunit ...` retourne OK avec 7 tests passes (8 assertions)
- [x] `grep -c test_` = 7 (>=7)

## Deviations from Plan

None — plan execute exactement comme ecrit. La signature du constructeur `AbstractRepository::__construct(?PDO $pdo = null)` etait deja test-friendly, donc l'adaptation potentielle mentionnee dans le plan (ligne 250 et 345) n'a pas ete necessaire. Le mock PDO direct fonctionne tel quel.

## Followups (optionnels, hors scope phase 2)

- **Metrique de dedupe** : exposer un compteur `error_events_dedupe_count` (Redis ou in-memory) que le guard incremente quand il skip un INSERT. Permettrait d'observer en prod le volume de doublons evites — utile pour confirmer l'hypothese du plan ("rafales SSE multiples par requete").
- **Spec Playwright end-to-end** : simuler une rafale SSE empty-state sur une vraie ressource vide et confirmer via une requete SQL `SELECT count(*) FROM error_events WHERE request_id = ?` qu'une seule ligne est inscrite. Le PHPUnit cible suffit a la success criteria #2 du ROADMAP, mais une spec E2E donnerait une confiance supplementaire.
- **Cross-request dedupe** : si on souhaite a terme dedupe sur des fenetres temporelles plus larges (5 min par exemple), il faudra repasser sur Redis avec un TTL — mais c'est explicitement OUT OF SCOPE du milestone v2.6 (cf. commit `544a60a` qui a verrouille la portee intra-request).

## Self-Check: PASSED

- File `app/Repository/ErrorEventsRepository.php` modifie : FOUND
- File `tests/Unit/ErrorEventsCaptureIdempotencyTest.php` cree : FOUND
- Commit `92a1fe2` (test RED) : FOUND
- Commit `66417fb` (feat GREEN) : FOUND
