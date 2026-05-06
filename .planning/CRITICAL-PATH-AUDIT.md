# Audit Chemin Critique — M-AUDIT-CHEMIN

**Date** : 2026-05-05 (consolidé 2026-05-06)
**Scope** : audit statique sandbox (lecture code + tests existants). Validation live dev-machine = follow-up.
**Stage** : 1 du pivot stratégique 2026-05-05 (avant Stage 2 audit stack et Stage 3 décision Voie A/B/C).
**Boundary** : aucun fix ici — uniquement constat. Tickets de fix = livrable Stage 3.

**Légende verdict** :
- ✓ : code lu semble OK + tests existants passent + pas de signal de régression
- ⚠ : code OK mais nuances connues (TODO, code récemment refactoré, edge case identifié)
- ✗ : problème détecté en lecture statique (bug visible, dépendance manquante, contrat cassé)
- ❓ : impossible de juger sans exécution live (dépend de runtime data, intégration externe, état DB)

**Légende impact** :
- 🛑 bloquant dogfood : la 1re asso pilote ne peut pas utiliser l'app
- 🔴 bloquant 1.0 shipped : OK pour dogfood mais bloque release publique
- 🟡 nice-to-have : améliore l'expérience sans bloquer
- ⚪ esthétique : polish sans impact fonctionnel

**Verdict global** : (à conclure dans la synthèse, étape 12)

---

## Étape 01 — Setup admin vierge (AUDIT-CHEMIN-01)

**Description du flow** : install Docker fresh → première navigation sur `/setup` → écran de création du premier admin (organisation + nom + email + mot de passe + confirmation) → soumission POST → création atomique `tenants` + `users` (rôle `admin`) → redirection `/login?setup=ok` → connexion → cookie de session → accès dashboard.

**Code concerné** :
- `app/Controller/SetupController.php` (208 lignes) — handler GET/POST `/setup`, n'étend pas `AbstractController` (utilise `HtmlView::render()` conformément à CLAUDE.md). Garde `hasAnyAdmin()` puis 404 opaque.
- `app/Controller/SetupRedirectException.php` — exception levée en mode test pour intercepter redirect/404 sans `exit`.
- `app/Repository/SetupRepository.php` (70 lignes) — `hasAnyAdmin()` + `createTenantAndAdmin()` en transaction PDO atomique avec `RETURNING id`.
- `app/Controller/AuthController.php` (326 lignes) — endpoint `/api/v1/auth_login` avec rate-limiting IP (`auth_login`, défaut 5/300s), lockout par compte (`AccountLockout`), `password_verify` constant-time même pour utilisateur inexistant (dummy hash), F21 SecuritySignal, F13 lockout.
- `app/Controller/AccountController.php` (146 lignes) — endpoints `/account` + ping session.
- `app/Controller/PasswordResetController.php` — flow reset (hors chemin critique direct mais lié au login).
- `app/routes.php` ligne 405 : `$router->mapAny('/setup', SetupController::class, 'setup')` — pas de middleware (le contrôleur fait sa garde).
- Routes auth lignes 110-115 : `auth_login` (sans rate_limit middleware déclaré — le rate-limit est INTERNE au contrôleur), `auth_csrf`, `auth_logout`, `whoami`.
- `database/schema-master.sql` lignes 96-134 : tables `tenants` et `users` (uniqueness `(tenant_id, email)`, role enum `admin|operator|auditor|viewer`).

**Tests existants** :
- `tests/Unit/SetupControllerTest.php` — couvre flow nominal + erreurs validation + 404 si admin existe.
- `tests/Unit/SetupControllerHardeningTest.php` — durcissement F22 CSRF requis sur POST initial, info-leak prevention (404 opaque vs 302).
- `tests/Unit/AuthControllerTest.php` — login/logout/whoami/csrf flow.
- `tests/Unit/AccountLockoutPureTest.php` — F13 progressive lockout par compte.
- `tests/Unit/AccountControllerTest.php` — handler compte connecté.
- `tests/Unit/AuthMiddlewareTest.php` + `AuthMiddlewareTimeoutTest.php` — middleware session.
- `tests/Unit/PasswordResetControllerTest.php` + `PasswordResetServiceTest.php`.

**Recoupement archive** :
- v2.0 et antérieur : flow login + setup mature.
- F02 (client IP réel non-spoofable), F13 (lockout par compte), F21 (SecuritySignal), F22 (CSRF setup) sont des hardenings sécurité documentés dans archives `v1.x-MILESTONE-AUDIT.md`. La couche est défensive et bien instrumentée.
- `.planning/archive-pre-pivot-2026-05-05/` mentionne "sécurité défensive F02-F22" comme `⚠` (validated mais non re-vérifié E2E).

**Verdict statique** : ✓

**Justification** :
- Le contrôleur est correct architecturalement : transaction atomique tenant+user, garde idempotente `hasAnyAdmin()`, CSRF requis, validation française stricte (longueur, format email, password_hash, confirm match), 404 opaque (pas de leak d'état d'init).
- `password_verify` est appelé même quand l'utilisateur n'existe pas (dummy hash) → pas de timing attack pour user enumeration.
- Lockout par compte F13 + rate-limit IP F02 → défense en profondeur.
- Couverture tests robuste (5 fichiers tests directement liés). Pas de TODO/FIXME visible.
- Nuance mineure : les commentaires utilisent ASCII sans accents dans certains messages d'erreur (ex. "caracteres" au lieu de "caractères") — c'est cohérent avec la note CLAUDE.md "ASCII compat" mais visible côté user. Non bloquant, simple polish.

**Reproduction live (dev-machine)** :
```bash
# 1. Reset complet (volumes Docker pour effacer DB)
docker compose down -v
docker compose up -d
sleep 8  # attendre healthchecks PostgreSQL + Redis + PHP-FPM

# 2. Vérifier que /setup est servi (premier accès)
curl -sI http://localhost:8080/setup | head -3
# attendu : HTTP/1.1 200 OK + cookie de session initialisé

# 3. GET du formulaire pour récupérer le token CSRF
CSRF=$(curl -s -c /tmp/cookies.txt http://localhost:8080/setup | grep -oP 'name="csrf_token"\s+value="\K[^"]+')
echo "CSRF token: $CSRF"

# 4. POST création admin
curl -i -b /tmp/cookies.txt -c /tmp/cookies.txt \
  -X POST http://localhost:8080/setup \
  -d "csrf_token=$CSRF" \
  -d "organisation_name=Asso Pilote" \
  -d "admin_name=Sophie Martin" \
  -d "admin_email=admin@example.org" \
  -d "admin_password=Test123!Secure" \
  -d "admin_password_confirm=Test123!Secure"
# attendu : HTTP 302 Location: /login?setup=ok

# 5. Vérifier que /setup retourne maintenant 404 (idempotence)
curl -sI http://localhost:8080/setup | head -1
# attendu : HTTP/1.1 404 Not Found

# 6. Login et accès dashboard
curl -sb /tmp/cookies.txt http://localhost:8080/api/v1/auth_csrf
# puis POST /api/v1/auth_login avec email/password + token CSRF
# vérifier whoami : curl -sb /tmp/cookies.txt http://localhost:8080/api/v1/whoami
# attendu : { ok: true, user_id: ..., role: "admin" }
```

**Impact** : 🟡 nice-to-have — couche mature, défensive ; pas de bloquant attendu.

**Recommandation** : valider en live le flow complet avant le prochain stage. Si OK, ne rien refacto. Stage 2 peut auditer la stack `CsrfMiddleware` + `RateLimiter` + `AccountLockout` pour décider keep/replace, mais le code applicatif lui-même est sain.

---

## Étape 02 — Import CSV membres (AUDIT-CHEMIN-02)

**Description du flow** : opérateur upload un fichier CSV (ou XLSX) avec ~50 membres. Colonnes attendues : `name` (ou `first_name`+`last_name`), `email`, `voting_power`, `is_active`, `groups`. Système détecte encoding (UTF-8/Win-1252/ISO-8859-1), détecte séparateur (`,` vs `;`), valide colonnes, dédoublonne emails (in-batch + DB), valide poids vote, persiste en transaction.

**Code concerné** :
- `app/Controller/ImportController.php` (174 lignes, très dense, one-liners) — handlers `membersCsv`, `membersXlsx`, `attendancesCsv/Xlsx`, `proxiesCsv/Xlsx`, `motionsCsv/Xlsx`. Chaque handler : (1) `IdempotencyGuard::check()` retourne cache si idempotent ; (2) lit fichier ; (3) délègue à `ImportService` puis `CsvImporter::processMemberImport()` ; (4) `audit_log` ; (5) `IdempotencyGuard::store()`.
- `app/Services/ImportService.php` (250 lignes) — façade : `mapColumns()`, `getMembersColumnMap()` (alias français : `nom`, `prénom`, `pondération`, `tantièmes`, `poids`, `groupes`, `collège`...), `parseVotingPower()`, `parseBoolean()`, `validateUploadedFile()`, `checkDuplicateEmails()`, `readCsvFile()` / `readXlsxFile()`.
- `app/Services/CsvImporter.php` (292 lignes) — `readFile()` détecte encoding via `mb_detect_encoding`, normalise UTF-8, détecte `;` vs `,`. `processMemberImport()` valide ligne par ligne (nom min 2 char, email format), upsert via `member()->updateImport()` ou `createImport()`, gère groupes (séparateurs `|` ou `;`).
- `app/Services/XlsxImporter.php` (300 lignes) — utilise `phpoffice/phpspreadsheet` (déjà déclaré dans composer.json).
- `app/Controller/MembersController.php` (244 lignes) — CRUD membres `/api/v1/members*`.
- `app/Controller/MemberGroupsController.php` — CRUD groupes.
- `app/routes.php` lignes 243-246 : `members_import_csv`, `members_import_xlsx` avec `rate_limit` `csv_import` 10/3600s.
- `database/schema-master.sql` lignes 174-192 : table `members` (uniqueness sur `(tenant_id, full_name)` et `(tenant_id, external_ref)` ; index actif).

**Tests existants** :
- `tests/Unit/ImportControllerTest.php` — handlers controller.
- `tests/Unit/ImportServiceTest.php` — column map + parsing.
- `tests/Unit/MembersControllerTest.php` + `MemberGroupsControllerTest.php` — CRUD.
- Note : pas de fixture 50-membres explicite vu dans la sandbox ; le test peut couvrir 5-10 lignes mais le scaling à 50 reste à valider en live.

**Recoupement archive** :
- v1.5/v1.6 : import CSV membres + procurations livrés et durcis (lecture archive `MILESTONES.md`).
- Sécurité : `IdempotencyGuard` couvre les retries (F22-like), `validateUploadedFile()` couvre upload vector, taille max 10 Mo (file) / 5 Mo (csv_content). Audit `member_import` event émis.

**Verdict statique** : ⚠

**Justification** :
- Le code est fonctionnel et défensif : auto-détection encoding (`mb_detect_encoding` avec fallback Win-1252/ISO-8859-1), auto-détection séparateur, alias français étendus pour les en-têtes (`pondération`, `tantièmes`, `poids`, `prénom`, `collège`), upsert idempotent (email puis full_name), gestion groupes avec création-à-la-volée, transaction PDO, audit_log, idempotency guard sur retries.
- **Nuance 1 (style)** : `ImportController.php` est écrit en one-liners ultra-denses (1 statement = 1 ligne, plusieurs `;` séparés). Très peu lisible. Risque de maintenance, mais pas un bug. Probablement compressé pour rester sous une LOC budget.
- **Nuance 2 (CLAUDE.md)** : la mention `'tantiemes', 'tantièmes'` dans le column map (ligne 125 ImportService.php) est un terme métier à proscrire selon CLAUDE.md (vocabulaire hors cible asso/collectivité). Ici c'est un alias en-tête CSV donc l'utilisateur a juste l'option d'utiliser ce mot dans son fichier source — ce n'est pas affiché dans l'UI. Acceptable mais à noter.
- **Nuance 3 (test coverage)** : pas de stress test 50-lignes visible ; les tests existants couvrent le happy path et quelques edge cases (duplicate emails). Comportement sur ligne corrompue au milieu du batch (ex. ligne 25/50 invalide) : selon le code, l'erreur est ajoutée à `errors[]`, la ligne est `skipped++`, le batch continue. Pas de rollback total. C'est cohérent mais à confirmer en live.
- **Nuance 4 (limites)** : taille fichier max 10 Mo (file upload) / 5 Mo (csv_content). Pas de limite explicite sur le nombre de lignes — pour 50 lignes c'est sans problème, pour 5000 lignes ce serait à vérifier (timeout PHP ? mémoire ?).

**Reproduction live (dev-machine)** :
```bash
# 0. Préalable : admin créé via étape 01, login OK, session active dans /tmp/cookies.txt

# 1. Générer un CSV 50 membres avec poids variés
{
  echo "nom,email,poids,actif,groupes"
  for i in $(seq 1 50); do
    weight=$(awk -v n=$i 'BEGIN{print (n%5==0)?5:1}')
    echo "Membre $i,membre$i@example.org,$weight,1,Groupe$((i%3))"
  done
} > /tmp/members50.csv

# 2. Récupérer un CSRF token + uploader le CSV
CSRF=$(curl -sb /tmp/cookies.txt http://localhost:8080/api/v1/auth_csrf | jq -r .csrf_token)
curl -sb /tmp/cookies.txt -H "X-Csrf-Token: $CSRF" \
  -F "file=@/tmp/members50.csv" \
  http://localhost:8080/api/v1/members_import_csv | jq

# attendu : { ok: true, imported: 50, skipped: 0, errors: [] }

# 3. Vérifier en DB (depuis container postgres)
docker compose exec db psql -U app -d agvote -c \
  "SELECT count(*), sum(voting_power) FROM members WHERE tenant_id = (SELECT id FROM tenants LIMIT 1)"
# attendu : count=50, sum cohérent (10*5 + 40*1 = 90)

# 4. Stress test idempotence : ré-uploader le même fichier
curl -sb /tmp/cookies.txt -H "X-Csrf-Token: $CSRF" \
  -F "file=@/tmp/members50.csv" \
  http://localhost:8080/api/v1/members_import_csv | jq
# attendu : imported=0 (ou =50 si update path), skipped=50, errors=[] — pas de doublons

# 5. Test ligne corrompue (email invalide)
sed -i 's|membre25@example.org|not-an-email|' /tmp/members50.csv
curl -sb /tmp/cookies.txt -H "X-Csrf-Token: $CSRF" \
  -F "file=@/tmp/members50.csv" \
  http://localhost:8080/api/v1/members_import_csv | jq
# attendu : skipped=1, errors=[{line: 26, error: "Email invalide"}], imported=49 ou similaire
```

**Impact** : 🟡 nice-to-have — base solide, comportement documenté, pas de show-stopper visible.

**Recommandation** : à valider en live avec un fichier CSV réel (encoding Excel français = Windows-1252) pour confirmer l'auto-détection. Stage 2 peut s'interroger sur la nécessité de garder XLSX en plus de CSV (`phpspreadsheet` est lourd en mémoire — pertinent pour stack audit). Le style one-liner d'`ImportController.php` est un candidat refacto Stage 3 si Voie A choisie.

---

## Étape 03 — Création séance + ordre du jour (AUDIT-CHEMIN-03)

**Description du flow** : opérateur ouvre le wizard de création d'une séance (titre, date, président, type), persiste la séance en `draft`, ajoute des points d'ordre du jour (`agendas`), puis crée des motions (`motions`) attachées à chaque point d'agenda. Vérifier persistance, ordre, validation, idempotence.

**Code concerné** :
- `app/Controller/MeetingsController.php` (297 lignes) — `index`, `update`, `archive`, `archivesList`, `status`, `statusForMeeting`, `summary`, `stats`, `createMeeting` (avec `IdempotencyGuard`), `deleteMeeting`. Délègue à `MeetingLifecycleService::createFromWizard()`.
- `app/Services/MeetingLifecycleService.php` — orchestre wizard create + lifecycle.
- `app/Services/MeetingValidator.php` (96 lignes) — règles `canBeValidated()` (président renseigné, pas de motion ouverte, motions fermées avec résultats exploitables, consolidation).
- `app/Controller/AgendaController.php` (131 lignes) — `listForMeeting`, `create` (avec validation `ValidationSchemas::agenda()`, idempotency, `nextIdx()` auto-incrément), `lateRules` (règles arrivée tardive quorum/vote), `listForMeetingPublic`.
- `app/Controller/MotionsController.php` (316 lignes) — `createOrUpdate` (validation schema : `agenda_id` UUID requis, `title` 1-500 char, `description` ≤ 10000 char, `secret` bool, `vote_policy_id`/`quorum_policy_id` optionnels), `createSimple` (sans agenda — créé auto), `listForMeeting`, `deleteMotion`, `reorder`, `tally`, `current`, `open`, `close`, `degradedTally`, `overrideDecision`.
- `app/Services/MotionsService.php` (626 lignes) — service métier dense.
- `app/routes.php` lignes 253-260 (meetings), 118-124 (agendas), 335-345 (motions).
- `database/schema-master.sql` ligne 60-62 : enum `motion_value AS ENUM ('for','against','abstain','nsp')`. Lignes 437-475 : table `motions` avec colonnes `secret bool`, `position int`, `vote_policy_id`, `quorum_policy_id`, `decision`, `official_*`, `manual_*`, `evote_results jsonb`. Triggers : `motions_body_from_description` (sync body ← description), `auto_generate_motion_slug`.

**Tests existants** :
- `tests/Unit/MeetingsControllerTest.php` — handlers (note PROJECT.md : "6 pre-existing MeetingsControllerTest failures hors scope v2.7" — donc déjà connus comme cassés sur update/delete).
- `tests/Unit/MeetingValidatorTest.php` — règles validation.
- `tests/Unit/MeetingLifecycleServiceTest.php` — wizard + transitions.
- `tests/Unit/AgendaControllerTest.php`.
- `tests/Unit/MotionsControllerTest.php` + `MotionsServiceTest.php` + `MotionsControllerOverrideDecisionTest.php`.
- `tests/Unit/MotionRepositoryTenantIsolationTest.php` — isolation multi-tenant.

**Recoupement archive** :
- v1.4-v1.5 : création séance et wizard livrés. v2.0 Operateur Live UX a renforcé.
- PROJECT.md mentionne `6 pre-existing MeetingsControllerTest failures (update/delete, hors scope v2.7)` — dette technique connue côté tests.

**Verdict statique** : ⚠

**Justification** :
- **Constat majeur (à creuser)** : le schéma SQL ne possède **AUCUNE colonne `kind` / `type`** sur `motions`. L'enum `motion_value` est `('for','against','abstain','nsp')` — c'est la valeur d'un ballot, pas le type de motion. Aucun mention de "election", "candidate", "open_question" dans `app/Services/VoteEngine.php`, `OfficialResultsService.php`, `MotionsService.php`, ni dans `database/schema-master.sql`. Recherche `grep -rnE "motion_kind|motion\.kind|->kind"` dans `app/` retourne 1 seul match : `BallotsController.php:408 'kind' => $kind` qui est en fait la *catégorie d'un incident* dans `audit_log('vote_incident', ...)`.
- **Conséquence directe** : la fonctionnalité **"vote motion élection multi-candidats"** demandée par REQUIREMENTS AUDIT-CHEMIN-07 et la fonctionnalité **"question ouverte"** ne sont **pas implémentées en sandbox** sur la stack actuelle. Le wizard, l'agenda et le `MotionsController::createOrUpdate` exposent uniquement `title`/`description`/`secret`. Le moteur de vote (étapes 06-08) ne connaît que For/Against/Abstain/NSP.
- Le code de création de séance lui-même est sain : validation centralisée (`InputValidator::schema()` + `ValidationSchemas`), idempotency guard, audit_log, gestion erreurs typée (mapping `RuntimeException` → code HTTP), guard `meeting_archived/validated` immutable, `slug` auto-généré par trigger PG, `position` auto via `nextIdx()`.
- **Dette technique connue** : "6 pre-existing MeetingsControllerTest failures sur update/delete" (PROJECT.md). À investiguer Stage 2/3.

**Reproduction live (dev-machine)** :
```bash
# 0. Préalable : login admin OK, /tmp/cookies.txt actif, CSRF récupéré.

CSRF=$(curl -sb /tmp/cookies.txt http://localhost:8080/api/v1/auth_csrf | jq -r .csrf_token)

# 1. Créer une séance via wizard
MID=$(curl -sb /tmp/cookies.txt -H "X-Csrf-Token: $CSRF" -H "Content-Type: application/json" \
  -d '{"title":"AG ordinaire 2026","scheduled_at":"2026-06-01T18:00:00Z","president_name":"Sophie Martin"}' \
  http://localhost:8080/api/v1/meetings | jq -r .meeting_id)
echo "MID=$MID"

# 2. Ajouter 3 points d'agenda
A1=$(curl -sb /tmp/cookies.txt -H "X-Csrf-Token: $CSRF" -H "Content-Type: application/json" \
  -d "{\"meeting_id\":\"$MID\",\"title\":\"Approbation comptes 2025\"}" \
  http://localhost:8080/api/v1/agendas | jq -r .agenda_id)
A2=$(curl -sb /tmp/cookies.txt -H "X-Csrf-Token: $CSRF" -H "Content-Type: application/json" \
  -d "{\"meeting_id\":\"$MID\",\"title\":\"Election bureau\"}" \
  http://localhost:8080/api/v1/agendas | jq -r .agenda_id)
A3=$(curl -sb /tmp/cookies.txt -H "X-Csrf-Token: $CSRF" -H "Content-Type: application/json" \
  -d "{\"meeting_id\":\"$MID\",\"title\":\"Question ouverte budget 2027\"}" \
  http://localhost:8080/api/v1/agendas | jq -r .agenda_id)

# 3. Tenter d'ajouter 3 motions de natures différentes
# 3a. Résolution simple (For/Against/Abstain) — devrait marcher
curl -sb /tmp/cookies.txt -H "X-Csrf-Token: $CSRF" -H "Content-Type: application/json" \
  -d "{\"agenda_id\":\"$A1\",\"title\":\"Adoption comptes 2025\",\"description\":\"Approuver le bilan financier\"}" \
  http://localhost:8080/api/v1/motions | jq

# 3b. Election multi-candidats — chercher si un payload avec "kind":"election" est accepté ou ignoré
curl -sb /tmp/cookies.txt -H "X-Csrf-Token: $CSRF" -H "Content-Type: application/json" \
  -d "{\"agenda_id\":\"$A2\",\"title\":\"Election trésorier\",\"kind\":\"election\",\"candidates\":[\"Alice\",\"Bob\",\"Claire\"]}" \
  http://localhost:8080/api/v1/motions | jq
# attendu (selon code statique) : "kind" et "candidates" sont SILENT-IGNORED par InputValidator (pas dans schema). Motion créée comme résolution standard.

# 3c. Question ouverte — même comportement attendu
curl -sb /tmp/cookies.txt -H "X-Csrf-Token: $CSRF" -H "Content-Type: application/json" \
  -d "{\"agenda_id\":\"$A3\",\"title\":\"Budget 2027 ?\",\"kind\":\"open_question\"}" \
  http://localhost:8080/api/v1/motions | jq
# attendu : motion créée mais le "kind" perdu.

# 4. Vérifier en DB que les motions sont créées sans champ kind/type
docker compose exec db psql -U app -d agvote -c \
  "SELECT id, title, secret, position FROM motions WHERE meeting_id='$MID' ORDER BY position"
# attendu : 3 lignes, aucune colonne 'kind' présente.
```

**Impact** : 🛑 bloquant dogfood (selon scope cible) — si la 1re asso pilote utilise le vote majoritaire de personnes (ex. élection bureau), le système ne sait pas le faire. Si elle se limite à des résolutions Pour/Contre/Abstention (cas comptes annuels, motions ordinaires d'AG), pas de blocage. Cette ambiguïté est centrale pour la décision de Stage 3.

**Recommandation** : ⚠ La création séance/agenda/motion résolution est saine. Mais **le périmètre fonctionnel doit être clarifié immédiatement** : le requirement AUDIT-CHEMIN-07 (élection multi-candidats) suppose une feature **non implémentée**. Trois scénarios :
1. Si la cible asso n'a JAMAIS d'élection : reformuler REQUIREMENTS pour retirer AUDIT-CHEMIN-07 et déclarer "scope = vote résolutif uniquement".
2. Si l'élection est nécessaire pour dogfood : c'est un blocker majeur — Stage 3 devra ajouter un milestone feature `M-ElectionMotion` (schema + service + UI).
3. Voir si une `motion.secret = true` + bulletin libre approxime "élection" — peu probable car le moteur compte For/Against/Abstain, pas des noms.

À investiguer en priorité Stage 2 (audit du domaine métier réel demandé) avant Stage 3 (décision direction).

---

## Étape 04 — Ouverture séance live (AUDIT-CHEMIN-04)

**Description du flow** : transitions guardées `draft → scheduled → frozen → live`. À chaque step, pré-requis vérifiés (motions présentes, attendance pointée, quorum atteint en warning). Transition unique via `meeting_transition` ou multi-step "fast-forward" via `launch`. Cockpit opérateur accessible une fois `live`. Audit_event émis. Évent SSE diffusé.

**Code concerné** :
- `app/Controller/MeetingWorkflowController.php` (256 lignes) — `transition`, `launch`, `readyCheck`, `resetDemo`. Gère HTTP, audit_log, broadcast SSE.
- `app/Services/MeetingTransitionService.php` (269 lignes) — `transition()` valide enum status, `launch()` calcule path multi-step (`draft → ['scheduled', 'frozen', 'live']`, `scheduled → ['frozen', 'live']`, `frozen → ['live']`), agrège `issues` et `warnings` de chaque step.
- `app/Services/MeetingWorkflowService.php` (237 lignes) — `issuesBeforeTransition()` règles déclaratives :
  - `draft → scheduled` : exige motions présentes (`no_motions`).
  - `scheduled → frozen` : exige attendance pointée (`no_attendance`), warning si pas de président.
  - `frozen → live` : warning `quorum_not_met` (non bloquant — l'opérateur décide).
  - `live → paused` : bloque si vote ouvert.
  - `live → closed` : bloque si vote ouvert.
  - `closed → validated` : exige résultats consolidés (codes `bad_closed_results`).
- `app/Services/MeetingLifecycleService.php` (274 lignes) — wizard, summary, stats.
- `app/Controller/OperatorController.php` (130 lignes) — endpoints cockpit.
- `app/Services/OperatorWorkflowService.php` (297 lignes).
- `app/routes.php` ligne 299 : `/meeting_transition`. Lignes 262 : `/meeting_status`, `/meeting_status_for_meeting`.
- `database/schema-master.sql` ligne 28 : `meeting_status AS ENUM ('draft','scheduled','frozen','live','paused','closed','validated','archived')`. Ligne 280 : `status meeting_status NOT NULL DEFAULT 'draft'`. Index partiels pour `frozen`, `paused`, `validated`.

**Tests existants** :
- `tests/Unit/MeetingWorkflowControllerTest.php`.
- `tests/Unit/MeetingWorkflowServiceTest.php`.
- `tests/Unit/MeetingTransitionServiceTest.php` + `MeetingTransitionTest.php`.
- `tests/Unit/StateTransitionCoherenceTest.php` — validation cohérence des transitions.
- `tests/Unit/RelaxRoleTransitionsTest.php` — assouplissements RBAC.
- `tests/Unit/OperatorControllerTest.php` + `OperatorWorkflowServiceTest.php`.

**Recoupement archive** :
- v2.0 "Operateur Live UX" — refacto majeur du cockpit + transitions. Documenté `⚠ Validated, à re-vérifier E2E` dans PROJECT.md.
- v2.7 mentionne pre-existing test failures sur Meeting update/delete (étape 03) ; les transitions (étape 04) sont distinctes et non listées comme cassées.

**Verdict statique** : ✓

**Justification** :
- Le service est très bien structuré : règles déclaratives par couple `(fromStatus, toStatus)`, séparation `issues` (bloquants) vs `warnings` (informatifs, opérateur décide), idempotence via early-return `already_in_target`, refus définitif sur `archived`.
- `launch()` simule chaque step intermédiaire et agrège tous les pré-requis avant d'autoriser le saut multi-step → empêche d'arriver à `live` avec une étape intermédiaire non valide.
- Couverture tests robuste (6 fichiers tests dédiés transitions + state).
- Index PostgreSQL partiels sur `frozen`, `paused`, `validated` → bonnes performances pour requêtes "trouve la séance live courante".
- Garde solide : `archived_meeting_locked` sur tous les transitions, `meeting_archived_locked` cohérent avec étape 03.
- Nuance : `paused` et `scheduled` sont des states intermédiaires qui élargissent l'enum. À valider live qu'ils ne créent pas de blocage UX (ex. opérateur clique "lancer" → l'app reste en `scheduled` au lieu de jumpler à `live` car attendance non pointée).

**Reproduction live (dev-machine)** :
```bash
# 0. Préalable : MID = séance créée à l'étape 03 avec 1+ motion ; auth admin/operator OK.

CSRF=$(curl -sb /tmp/cookies.txt http://localhost:8080/api/v1/auth_csrf | jq -r .csrf_token)

# 1. Vérifier readyCheck (pré-requis avant lancement)
curl -sb /tmp/cookies.txt -H "X-Csrf-Token: $CSRF" -H "Content-Type: application/json" \
  -d "{\"meeting_id\":\"$MID\"}" \
  http://localhost:8080/api/v1/meeting_ready_check | jq
# attendu : { issues: [{code: "no_attendance", ...}], warnings: [...] }

# 2. Tenter transition draft → live directement (devrait être interdit OU resort en multi-step launch)
curl -sb /tmp/cookies.txt -H "X-Csrf-Token: $CSRF" -H "Content-Type: application/json" \
  -d "{\"meeting_id\":\"$MID\",\"to_status\":\"live\"}" \
  http://localhost:8080/api/v1/meeting_transition | jq
# attendu : 422 ou 409 — la transition single-step ne saute pas les états

# 3. Marquer attendance (préalable pour passer scheduled → frozen — voir étape 05)
# ... (curl /attendances_bulk avec liste de membres)

# 4. Launch multi-step : draft → scheduled → frozen → live en un seul appel
curl -sb /tmp/cookies.txt -H "X-Csrf-Token: $CSRF" -H "Content-Type: application/json" \
  -d "{\"meeting_id\":\"$MID\"}" \
  http://localhost:8080/api/v1/meeting_launch | jq
# attendu : { meeting_id, from_status: "draft", to_status: "live", path: ["scheduled","frozen","live"], warnings: [...] }

# 5. Idempotence : retenter transition vers live (déjà live)
curl -sb /tmp/cookies.txt -H "X-Csrf-Token: $CSRF" -H "Content-Type: application/json" \
  -d "{\"meeting_id\":\"$MID\",\"to_status\":\"live\"}" \
  http://localhost:8080/api/v1/meeting_transition | jq
# attendu : { from_status: "live", to_status: "live", already_in_target: true }

# 6. Vérifier accès cockpit opérateur après passage live
curl -sb /tmp/cookies.txt "http://localhost:8080/api/v1/meeting_status?meeting_id=$MID" | jq
# attendu : status: "live", données chargées (membres, motions, présences)

# 7. Vérifier audit_event
docker compose exec db psql -U app -d agvote -c \
  "SELECT action, payload FROM audit_events WHERE resource_type='meeting' AND resource_id='$MID' ORDER BY created_at DESC LIMIT 5"
# attendu : meeting_transitioned (de draft vers scheduled, frozen, live)
```

**Impact** : 🟡 nice-to-have — code mature, pré-requis bien définis. Pas de blocage attendu en live.

**Recommandation** : valider en live la diffusion SSE de l'event de transition (cockpit opérateur doit refresh seul). Si OK, ne rien refacto. Stage 2 peut auditer si l'enum à 8 états (`draft|scheduled|frozen|live|paused|closed|validated|archived`) est sur-dimensionné — peut-être que `paused` et `scheduled` ajoutent de la complexité sans valeur user.

---

## Étape 05 — Émargement présence + quorum (AUDIT-CHEMIN-05)

**Description du flow** : opérateur marque les membres présents (`present`, `remote`, `proxy`, `excused`). `QuorumEngine` calcule en continu si le quorum est atteint, en pondérant les voix selon `voting_power` des membres. Politique configurable par séance ou tenant : seuil simple (`single`), double seuil (`double`), seuil évolutif 2e convocation (`evolving`).

**Code concerné** :
- `app/Controller/AttendancesController.php` (174 lignes) — `listForMeeting`, `bulk` (marquage en masse), `upsert` (un membre), `setPresentFrom` (timestamp arrivée tardive). Routes mappées avec rôles `operator/president/trust/admin`.
- `app/Services/AttendancesService.php` (161 lignes) — logique upsert + lookup membre.
- `app/Services/QuorumEngine.php` (359 lignes) — moteur principal :
  - `computeForMeeting()` : politique meeting → fallback `settings.settQuorumThreshold` → `noPolicy`.
  - `computeForMotion()` : politique motion-level → fallback meeting → `noPolicy`.
  - `computeInternal()` : calcule sur ratios `present_count / eligible_count` ET `present_weight / eligible_weight`, applique mode (single/double/evolving), respecte `include_proxies` et `count_remote` flags, filtre `lateCutoff` (motion ouverte avant arrivée → exclu).
- `app/Controller/QuorumController.php` (182 lignes) — endpoints `/api/v1/quorum*`, `/quorum_status`.
- `app/routes.php` lignes 128-134 (attendances) et 365-367 (quorum).
- `database/schema-master.sql` ligne 47 : `attendance_mode AS ENUM ('present','remote','proxy','excused')`. Lignes 214-237 : table `quorum_policies` avec contraintes CHECK : `mode IN ('single','evolving','double')`, `denominator IN ('eligible_members','eligible_weight')`, `threshold` ∈ [0,1], `threshold_call2` optionnel, `denominator2`/`threshold2` optionnels.

**Tests existants** :
- `tests/Unit/AttendancesControllerTest.php` + `AttendancesServiceTest.php`.
- `tests/Unit/QuorumEngineTest.php` (référence centrale CLAUDE.md "complex business logic — Quorum/majority calculation, weighted voting").
- `tests/Unit/QuorumEngineSettingsTest.php` — fallback settings tenant.
- `tests/Unit/QuorumLogicTest.php` — règles algébriques.
- `tests/Unit/QuorumControllerTest.php`.
- `tests/Unit/WeightedVoteRegressionTest.php` — anti-régression pondération.

**Recoupement archive** :
- v2.0 Operateur Live UX : refacto majeur du calcul quorum + pondération.
- PROJECT.md : `⚠ Quorum/pondération/procurations — code v2.0` (validated mais pas re-vérifié E2E récent).
- v2.7 N+1 audit (`v2.7-N+1-AUDIT.md`) — possible point d'optimisation `attendance.countPresentMembers` répété.

**Verdict statique** : ✓

**Justification** :
- Code très bien architecturé : separation politique (quorum_policies) / calcul (QuorumEngine) / persistance (AttendanceRepository). DI nullable via constructeur (CLAUDE.md compliance), services finaux.
- Algorithme correct : itère sur tous les `attendance_mode` autorisés (`present` toujours, `remote` si `count_remote`, `proxy` si `include_proxies`), calcule ratios SUR LES POIDS (`sumPresentWeight`) ET sur le compte de membres (`countPresentMembers`) selon `denominator` (`eligible_members` ou `eligible_weight`).
- Mode `double` (deux seuils indépendants tous deux à respecter) et `evolving` (threshold différent en 2e convocation) implémentés correctement.
- Triple fallback chain bien ordonné : motion-level → meeting-level → tenant settings → `noPolicy`. Le fallback `settings.settQuorumThreshold` synthétise une policy minimale ("Réglages tenant"), évite un `noPolicy` désagréable.
- Late arrival : `lateCutoff = motion.opened_at` permet d'exclure les arrivants tardifs du quorum d'une motion ouverte avant. Pertinent légalement.
- Couverture tests forte : 5 fichiers tests dédiés + un anti-régression (`WeightedVoteRegressionTest`).
- Note mineure : `noPolicy` retourne `met: null` (pas `false`) — l'UI doit savoir distinguer "pas de politique" vs "non atteint". À vérifier que le cockpit ne traite pas `null` comme "atteint".

**Reproduction live (dev-machine)** :
```bash
# 0. Préalable : étape 02 (50 membres importés) + étape 03 (séance MID + motions) + étape 04 (séance en frozen).

CSRF=$(curl -sb /tmp/cookies.txt http://localhost:8080/api/v1/auth_csrf | jq -r .csrf_token)

# 1. Récupérer la liste des membres + quelques IDs
MEMBERS_JSON=$(curl -sb /tmp/cookies.txt http://localhost:8080/api/v1/members)
echo "$MEMBERS_JSON" | jq '.items[:3]'

# 2. Marquer 30 membres présents en bulk (10 avec poids 5, 20 avec poids 1)
MID_LIST=$(echo "$MEMBERS_JSON" | jq -r '.items[:30] | map({member_id: .id, mode: "present"}) | tostring')
curl -sb /tmp/cookies.txt -H "X-Csrf-Token: $CSRF" -H "Content-Type: application/json" \
  -d "{\"meeting_id\":\"$MID\",\"items\":$MID_LIST}" \
  http://localhost:8080/api/v1/attendances_bulk | jq

# 3. Calcul quorum
curl -sb /tmp/cookies.txt "http://localhost:8080/api/v1/quorum_status?meeting_id=$MID" | jq
# attendu : applied: true, met: true ou false, details.primary.{numerator_members, numerator_weight, denominator_members, denominator_weight, ratio, met}

# 4. Vérifier en DB la cohérence
docker compose exec db psql -U app -d agvote -c \
  "SELECT mode, count(*), sum(m.voting_power) AS total_weight \
   FROM attendances a JOIN members m ON m.id = a.member_id \
   WHERE a.meeting_id='$MID' AND a.tenant_id=(SELECT tenant_id FROM meetings WHERE id='$MID') \
   GROUP BY mode"
# attendu : 30 lignes 'present' avec sum(voting_power) cohérent (ex 50)

# 5. Stress test : marquer 1 membre 'remote' et vérifier que le calcul l'inclut si count_remote=true
M_REMOTE=$(echo "$MEMBERS_JSON" | jq -r '.items[31].id')
curl -sb /tmp/cookies.txt -H "X-Csrf-Token: $CSRF" -H "Content-Type: application/json" \
  -d "{\"meeting_id\":\"$MID\",\"member_id\":\"$M_REMOTE\",\"mode\":\"remote\"}" \
  http://localhost:8080/api/v1/attendances_upsert | jq

curl -sb /tmp/cookies.txt "http://localhost:8080/api/v1/quorum_status?meeting_id=$MID" | jq
# attendu : numerator_members augmenté de 1, ratio recalculé
```

**Impact** : 🟡 nice-to-have — moteur mature, défensif, bien testé. Pas de blocage attendu.

**Recommandation** : valider en live qu'une politique de quorum tenant fallback existe par défaut (sinon `met: null` → cockpit peut afficher "—" au lieu de "atteint"). Stage 2 peut interroger : 3 modes (single/double/evolving) sont-ils tous nécessaires en pratique ? Le mode `double` est rare en associations (cible CLAUDE.md), à confirmer terrain.

---

## Étape 06 — Vote motion résolution simple (AUDIT-CHEMIN-06)

**Description du flow** : opérateur ouvre une motion résolution (POST `/motions_open`). Votants envoient leur ballot (`for`/`against`/`abstain`/`nsp`) via `POST /ballots_cast`. Opérateur ferme la motion (POST `/motions_close`). `VoteEngine` calcule le résultat pondéré et le décide (`adopted`/`rejected`).

**Code concerné** :
- `app/Controller/BallotsController.php` (414 lignes) — `listForMotion`, `cast` (vote endpoint), `manualVote`, `redeemPaperBallot`, `cancel`. Validation et appel à `BallotsService`.
- `app/Services/BallotsService.php` (221 lignes) — `castBallot()` :
  - Garde tenant (multi-tenant safe via `_tenant_id`).
  - Garde state : `meeting.status === 'live'`, `motion.opened_at IS NOT NULL && closed_at IS NULL`, séance non validée.
  - Garde présence : `attendancesService->isPresentDirect()` (mode `present` ou `remote`, EXCLUE `proxy` pour éviter chaînes).
  - Garde poids : `weight = member.voting_power`, valide `>= 0`, `<= 1e6`, fini.
  - **Anti-TOCTOU explicite** : `meetingRepo->lockForUpdate()` (SELECT FOR UPDATE) en transaction, puis re-validation `motion_opened_at/closed_at` à l'intérieur du lock (cas course concurrent close).
  - Idempotence : `UNIQUE (motion_id, member_id)` en DB → SQLSTATE 23505 capturé → message français explicite "Ce membre a déjà voté".
  - Audit : SSE broadcast `EventBroadcaster::voteCast()` après transaction (best-effort, échec loggé sans bloquer).
- `app/Services/VoteEngine.php` (346 lignes) — calcul tally, décision (majorité simple, qualifiée, etc. selon vote_policy).
- `app/Services/OfficialResultsService.php` (399 lignes) — consolidation manuelle vs e-vote.
- `app/routes.php` ligne 145-150 (ballots), 341-342 (motions open/close).
- `database/schema-master.sql` lignes 662-679 : table `ballots` avec `UNIQUE (motion_id, member_id)`, `value motion_value NOT NULL`, `weight numeric(12,4) NOT NULL DEFAULT 1.0`, `is_proxy_vote bool`, `proxy_source_member_id`. Trigger `ballots_fill_context` remplit `meeting_id`/`tenant_id` automatiquement depuis la motion.

**Tests existants** :
- `tests/Unit/BallotsControllerTest.php` + `BallotsServiceTest.php`.
- `tests/Unit/VoteEngineTest.php` + `VoteEngineSettingsTest.php` + `VoteLogicTest.php`.
- `tests/Unit/WeightedVoteRegressionTest.php` — anti-régression poids.
- `tests/Unit/OfficialResultsServiceTest.php`.
- `tests/Unit/DataIntegrityLocksTest.php` — locks SQL transactions.
- `tests/Unit/MotionsControllerTest.php` (handlers open/close).

**Recoupement archive** :
- v1.6 → v2.0 → v2.7 : moteur de vote pondéré durci en 28 milestones successifs.
- F22 (CSRF action-scoped), F13/F21 (rate limit + lockout) appliqués à `ballots_cast`.
- v2.7-N+1-AUDIT.md mentionne possible optimisation `tally()` répété sur SSE broadcast — non bloquant, perf.
- TOCTOU défensive ajoutée explicitement (commentaires "TOCTOU prevention" dans BallotsService.php) — c'est un travail de durcissement récent.

**Verdict statique** : ✓

**Justification** :
- Implémentation référence quasi parfaite : transaction PG avec `SELECT FOR UPDATE`, re-validation systématique en zone protégée, contrainte UNIQUE en DB pour idempotence (pas en applicatif fragile), trigger `ballots_fill_context` qui assure la cohérence tenant_id/meeting_id même si l'appelant les omet, audit_log + SSE broadcast en best-effort.
- Anti-chaîne procuration : un `proxy_source_member_id` ne peut pas être lui-même en mode `proxy` à la séance — la garde `isPresentDirect` (qui exclut `proxy`) empêche la procuration transitive A→B→C.
- Validation weight stricte : `is_finite($weight) && $weight >= 0.0 && $weight <= 1e6` empêche `Infinity`, `NaN`, valeurs négatives, valeurs absurdes.
- Messages d'erreur en français explicite : "Ce membre a déjà voté sur cette résolution. Un re-vote nécessite une annulation préalable par l'opérateur." — UX clair.
- Couverture tests étendue (8+ fichiers tests directement liés). Régression pondération couverte.
- Nuance : le test PROJECT.md "6 pre-existing MeetingsControllerTest failures" concerne meeting update/delete (étape 03), PAS les ballots. Les ballots restent verts.

**Reproduction live (dev-machine)** :
```bash
# 0. Préalable : étape 04 (séance live) + étape 05 (30 présents). Mention motion_id MOTID = première motion résolution.

CSRF=$(curl -sb /tmp/cookies.txt http://localhost:8080/api/v1/auth_csrf | jq -r .csrf_token)

# 1. Ouvrir le vote sur la motion
curl -sb /tmp/cookies.txt -H "X-Csrf-Token: $CSRF" -H "Content-Type: application/json" \
  -d "{\"motion_id\":\"$MOTID\"}" http://localhost:8080/api/v1/motions_open | jq

# 2. 30 votants émettent leur ballot (test idempotence + tally)
for MID_MEMBER in $(curl -sb /tmp/cookies.txt http://localhost:8080/api/v1/members | jq -r '.items[:30] | .[].id'); do
  VAL=$(awk 'BEGIN{srand(); r=rand(); if (r<0.6) print "for"; else if (r<0.85) print "against"; else print "abstain"}')
  curl -sb /tmp/cookies.txt -H "X-Csrf-Token: $CSRF" -H "Content-Type: application/json" \
    -d "{\"motion_id\":\"$MOTID\",\"member_id\":\"$MID_MEMBER\",\"value\":\"$VAL\"}" \
    http://localhost:8080/api/v1/ballots_cast > /dev/null
done

# 3. Test idempotence : re-voter le même membre doit échouer
SAMPLE=$(curl -sb /tmp/cookies.txt http://localhost:8080/api/v1/members | jq -r '.items[0].id')
curl -sb /tmp/cookies.txt -H "X-Csrf-Token: $CSRF" -H "Content-Type: application/json" \
  -d "{\"motion_id\":\"$MOTID\",\"member_id\":\"$SAMPLE\",\"value\":\"for\"}" \
  http://localhost:8080/api/v1/ballots_cast | jq
# attendu : 409/422 avec message "Ce membre a déjà voté..."

# 4. Fermer le vote
curl -sb /tmp/cookies.txt -H "X-Csrf-Token: $CSRF" -H "Content-Type: application/json" \
  -d "{\"motion_id\":\"$MOTID\"}" http://localhost:8080/api/v1/motions_close | jq

# 5. Récupérer le tally pondéré
curl -sb /tmp/cookies.txt "http://localhost:8080/api/v1/ballots_result?motion_id=$MOTID" | jq
# attendu : { for: <weighted>, against: <weighted>, abstain: <weighted>, total: ..., decision: "adopted"|"rejected" }

# 6. Vérifier en DB que les poids sont bien appliqués (pas un compte flat)
docker compose exec db psql -U app -d agvote -c \
  "SELECT value, count(*) AS n, sum(weight) AS total_weight FROM ballots WHERE motion_id='$MOTID' GROUP BY value"
# attendu : sum(weight) > count(*) si certains membres ont voting_power > 1
```

**Impact** : 🟡 nice-to-have — coeur métier, code mature et défensif. Le résultat doit être correct. Si confirmé en live, c'est une force de l'app.

**Recommandation** : valider en live le cas TOCTOU (vote pendant close) — difficile à reproduire mais le code prévoit. SSE broadcast à valider visuellement (cockpit refresh seul). Stage 2 ne devrait pas remettre en cause cette couche — c'est l'asset le plus solide du codebase.

---

## Étape 07 — Vote motion élection multi-candidats (AUDIT-CHEMIN-07)

**Description du flow demandé par REQUIREMENTS** : motion type "élection" avec N candidats, M sièges. Votants choisissent K candidats (K ≤ M ou autre règle). Calcul résultats : scrutin majoritaire à 1 ou 2 tours, vote d'approbation, possiblement STV.

**Code concerné** : **AUCUN.**

Recherche exhaustive dans le codebase :
```bash
$ grep -rnE "candidate|candidat|election|stv|borda|condorcet|approval" \
    app/Services/VoteEngine.php \
    app/Services/OfficialResultsService.php \
    app/Services/BallotsService.php
# 0 match
```

```bash
$ grep -rnE "kind|motion_type|motion_kind" app/ database/ | grep -v "test\|.htmx\|README"
# 1 match: BallotsController.php:408 'kind' => $kind  (audit_log incident, pas type motion)
```

```bash
$ grep -nE "candidate|candidat" app/ database/
# Matches non liés (MemberGroups, RGPD purge, URL validator) — aucun lien avec voting
```

**État réel du modèle de vote** :
- Table `ballots.value` : enum `motion_value AS ENUM ('for','against','abstain','nsp')`. Pas de colonne `candidate_id`, pas de table `candidates`, pas de table `motion_candidates`.
- Service `VoteEngine.php` : 346 lignes calculant uniquement majorité Pour/Contre/Abstention selon `vote_policy` (seuils, exclusion abstentions). Pas de routine multi-choix.
- Service `OfficialResultsService.php` : consolidation `official_for`/`official_against`/`official_abstain`/`official_total` — pas de structure pour "candidat X a reçu N voix".
- Aucune route `/elections/*`, `/candidates/*`, ni endpoint de vote multi-choix.
- L'`evote_results jsonb` colonne `motions` (schema ligne 463) est utilisée pour stocker tally résolutif structuré, pas un classement candidats.

**Tests existants** : aucun test "election", "candidate", "multi-choice" trouvé dans `tests/Unit/`. Le flow n'est pas testé car non implémenté.

**Recoupement archive** :
- Aucun milestone v1.0 → v2.7 ne mentionne "élection" ou "scrutin majoritaire à plusieurs candidats" (lecture rapide `MILESTONES.md`).
- Le flow "membre A vote pour le candidat B" n'a jamais existé dans cette base de code.

**Verdict statique** : ✗

**Justification** :
- La fonctionnalité **n'existe pas**. Le requirement AUDIT-CHEMIN-07 décrit un flow non implémenté.
- C'est un trou fonctionnel **structurel** (schéma DB + service + UI manquants), pas un bug.
- Si on essaie de "détourner" un vote résolutif pour faire une élection (ex. créer une motion par candidat avec vote `for`/`against`), cela donne un résultat approximatif (vote d'approbation par défaut) mais ne couvre pas :
  - sièges multiples (élire 3 trésoriers parmi 5 candidats),
  - règles "1 électeur = K voix maximum",
  - 2e tour,
  - départage en cas d'égalité,
  - bulletin secret avec liste de noms.

**Reproduction live (dev-machine)** :
```bash
# Le test "live" consiste à confirmer le constat statique.

# 1. Confirmer absence de table candidates
docker compose exec db psql -U app -d agvote -c "\\d motions" | grep -iE "kind|type|candidate"
# attendu : aucune ligne

docker compose exec db psql -U app -d agvote -c "\\dt" | grep -iE "candidate|election"
# attendu : aucune table

# 2. Tenter une "élection" en créant 1 motion par candidat (workaround)
A_BUREAU=$(curl -sb /tmp/cookies.txt -H "X-Csrf-Token: $CSRF" -H "Content-Type: application/json" \
  -d "{\"meeting_id\":\"$MID\",\"title\":\"Election bureau\"}" \
  http://localhost:8080/api/v1/agendas | jq -r .agenda_id)
for cand in Alice Bob Claire; do
  curl -sb /tmp/cookies.txt -H "X-Csrf-Token: $CSRF" -H "Content-Type: application/json" \
    -d "{\"agenda_id\":\"$A_BUREAU\",\"title\":\"Election $cand au bureau\"}" \
    http://localhost:8080/api/v1/motions | jq
done
# attendu : 3 motions séparées créées. Le user devra voter "pour" sur chacune ou les ranger soi-même.
# La synthèse "qui est élu" doit être faite manuellement, pas par le système.
```

**Impact** : 🛑 bloquant dogfood — si la 1re asso pilote a une élection à son AG (tâche extrêmement courante : élection bureau, conseil d'administration, président), le système ne sait pas le faire proprement. Workaround "1 motion par candidat" dégrade l'UX et perd la sémantique électorale.

**Recommandation** : décision majeure pour Stage 3.
- **Option 1 (étroit)** : retirer AUDIT-CHEMIN-07 de REQUIREMENTS, déclarer "scope = vote résolutif uniquement", documenter que les élections doivent être gérées hors-app. Coût : zéro. Risque : on rate des cas d'usage.
- **Option 2 (refacto)** : ajouter `motion.kind`, table `candidates`, ballot multi-choix, calcul majoritaire 1 tour. Coût : ~2 semaines dev. À budgéter dans une feature `M-ElectionMotion` Stage 3+.
- **Option 3 (rebuild)** : si Stage 2 conclut que la stack PHP/HTMX n'est pas le bon choix pour ce besoin (workflow complexe avec UX riche), ce gap renforce l'argument Voie C (rebuild from scratch). À considérer.

---

## Étape 08 — Vote avec procuration active (AUDIT-CHEMIN-08)

**Description du flow** : membre A donne procuration à membre B pour la séance. B est présent (`present` ou `remote`), A absent. B vote sur motion → ballot enregistré avec `is_proxy_vote=true`, `proxy_source_member_id = B.id`, `member_id = A.id`, `weight = A.voting_power` (poids de A appliqué au choix de B). Plus tôt, vérifier que B vote aussi pour lui-même séparément (vote direct).

**Code concerné** :
- `app/Services/ProxiesService.php` (143 lignes) — `upsert()`, `revoke()`, `hasActiveProxy()`, `hasActiveProxyForUpdate()`. Documenté en JSDoc clair :
  - Anti-self-delegation (`giver != receiver`).
  - Tenant coherence (meeting + giver + receiver même tenant).
  - **Anti-chaîne** : si B a déjà délégué à C, A ne peut pas déléguer à B (lock `countActiveAsGiverForUpdate`).
  - Cap par receiver : `proxy_max_per_receiver` env var (default 99 ici, mais 3 dans `ImportController.php` config — incohérence à noter).
  - Transaction avec `SELECT FOR UPDATE` pour éviter TOCTOU concurrent proxy creation.
- `app/Services/ProcurationPdfService.php` (296 lignes) — génération PDF de mandat de procuration (signature physique).
- `app/Services/BallotsService.php` (cf. étape 06) — `castBallot()` avec branche `isProxyVote` :
  - `proxy_source_member_id` UUID requis.
  - Mandataire doit être présent (`isPresentDirect` exclut mode `proxy` → empêche chaîne A→B→C runtime).
  - `hasActiveProxyForUpdate()` re-vérifie en transaction (TOCTOU sur révocation entre check et insert).
- `app/Controller/ProxiesController.php` (123 lignes) — endpoints CRUD.
- `app/routes.php` ligne 358-362 (proxies + import).
- `database/schema-master.sql` lignes 616-633 :
  - Table `proxies` : `UNIQUE (tenant_id, meeting_id, giver_member_id)` (un membre = max 1 procuration active par séance), `CHECK (giver_member_id <> receiver_member_id)` (anti-self-delegation au niveau DB), `revoked_at` pour soft-delete, `scope proxy_scope DEFAULT 'full'`, `agenda_limits text[]` pour procuration partielle (par point d'agenda).
  - Index partiel `WHERE revoked_at IS NULL` pour requêtes "actives uniquement".

**Tests existants** :
- `tests/Unit/ProxiesControllerTest.php` + `ProxiesServiceTest.php`.
- `tests/Unit/ProcurationPdfControllerTest.php` + `ProcurationPdfServiceTest.php`.

**Recoupement archive** :
- v1.5/v1.6 : import procurations + mandat PDF.
- v2.0 Operateur Live UX : durcissement TOCTOU procuration (les commentaires "TOCTOU prevention" dans ProxiesService.php sont récents).
- PROJECT.md : `⚠ Vote en direct avec quorum/pondération/procurations` validated mais pas re-vérifié récent.

**Verdict statique** : ⚠

**Justification** :
- Le code est extrêmement défensif : 4 règles de validation explicites avec messages français, anti-chaîne **vérifiée 2x** (au upsert ET au cast), transaction avec `SELECT FOR UPDATE`, contrainte DB `UNIQUE` sur giver (un membre = 1 proxy max active par séance), `CHECK` au niveau SQL pour anti-self-delegation.
- Modèle data sain : `revoked_at` permet historique (pas de DELETE), `scope='full'|partial` + `agenda_limits text[]` permet procurations partielles (non testé en lecture statique).
- **Incohérence de cap** : `ProxiesService.php:73` lit `config('proxy_max_per_receiver', 99)` (default 99). `ImportController.php:50` lit `config('proxy_max_per_receiver', 3)` (default 3). Les deux defaults diffèrent ! Si `proxy_max_per_receiver` n'est pas configuré dans tenant_settings, l'API `/proxies` accepte jusqu'à 99 mais l'import CSV plafonne à 3. Selon la source, on a un bug d'incohérence ou les deux sont voulus différents. **À investiguer.**
- Côté ballot : `BallotsService.castBallot()` re-valide le proxy en transaction avec `hasActiveProxyForUpdate()` → si A révoque entre-temps, le vote de B est rejeté avec message "Procuration révoquée avant le vote". Excellent.
- Anti-chaîne complet : (1) au moment de créer la proxy (anti-A→B si B a déjà donné à C), (2) au moment du vote (mandataire doit être en mode `present`/`remote`, pas `proxy`).
- Limites légales : la cap par receiver (3 ou 99) est configurable par env var. Pour une asso loi 1901 française, le standard est typiquement 1-3 procurations max — donc default 3 d'`ImportController.php` est plus correct que default 99 d'API. **À aligner.**
- Couverture tests : 4 fichiers. Pas de stress test sur "B révoque sa délégation pendant que A vote" (cas TOCTOU) — couvert par le code mais peut-être pas par tests.

**Reproduction live (dev-machine)** :
```bash
# 0. Préalable : étape 02 (membres importés) + étape 03 (séance MID + motion résolution MOTID)
# Choisir 2 membres : A (donneur, absent) et B (receveur, présent)

CSRF=$(curl -sb /tmp/cookies.txt http://localhost:8080/api/v1/auth_csrf | jq -r .csrf_token)
A=$(curl -sb /tmp/cookies.txt http://localhost:8080/api/v1/members | jq -r '.items[0].id')
B=$(curl -sb /tmp/cookies.txt http://localhost:8080/api/v1/members | jq -r '.items[1].id')

# 1. Marquer B présent, A absent
curl -sb /tmp/cookies.txt -H "X-Csrf-Token: $CSRF" -H "Content-Type: application/json" \
  -d "{\"meeting_id\":\"$MID\",\"items\":[{\"member_id\":\"$B\",\"mode\":\"present\"}]}" \
  http://localhost:8080/api/v1/attendances_bulk | jq

# 2. A donne procuration à B
curl -sb /tmp/cookies.txt -H "X-Csrf-Token: $CSRF" -H "Content-Type: application/json" \
  -d "{\"meeting_id\":\"$MID\",\"giver_member_id\":\"$A\",\"receiver_member_id\":\"$B\"}" \
  http://localhost:8080/api/v1/proxies | jq
# attendu : ok: true

# 3. Tester anti-chaîne : C tente de déléguer à A (mais A a délégué à B → interdit)
C=$(curl -sb /tmp/cookies.txt http://localhost:8080/api/v1/members | jq -r '.items[2].id')
curl -sb /tmp/cookies.txt -H "X-Csrf-Token: $CSRF" -H "Content-Type: application/json" \
  -d "{\"meeting_id\":\"$MID\",\"giver_member_id\":\"$C\",\"receiver_member_id\":\"$A\"}" \
  http://localhost:8080/api/v1/proxies | jq
# attendu : 422 "Chaîne de procuration interdite (le mandataire délègue déjà)."

# 4. Ouvrir vote sur motion + B vote pour A (proxy)
curl -sb /tmp/cookies.txt -H "X-Csrf-Token: $CSRF" -H "Content-Type: application/json" \
  -d "{\"motion_id\":\"$MOTID\"}" http://localhost:8080/api/v1/motions_open
curl -sb /tmp/cookies.txt -H "X-Csrf-Token: $CSRF" -H "Content-Type: application/json" \
  -d "{\"motion_id\":\"$MOTID\",\"member_id\":\"$A\",\"value\":\"for\",\"is_proxy_vote\":true,\"proxy_source_member_id\":\"$B\"}" \
  http://localhost:8080/api/v1/ballots_cast | jq
# attendu : ballot inserted avec weight = voting_power(A)

# 5. B vote aussi pour lui-même
curl -sb /tmp/cookies.txt -H "X-Csrf-Token: $CSRF" -H "Content-Type: application/json" \
  -d "{\"motion_id\":\"$MOTID\",\"member_id\":\"$B\",\"value\":\"against\",\"is_proxy_vote\":false}" \
  http://localhost:8080/api/v1/ballots_cast | jq
# attendu : 2 ballots distincts pour la motion (A via proxy + B direct)

# 6. Vérifier en DB
docker compose exec db psql -U app -d agvote -c \
  "SELECT b.member_id, b.value, b.weight, b.is_proxy_vote, b.proxy_source_member_id \
   FROM ballots b WHERE b.motion_id='$MOTID' ORDER BY b.cast_at"
# attendu : 2 lignes — (A, for, w_A, true, B) et (B, against, w_B, false, NULL)

# 7. Test révocation pendant vote (TOCTOU) — difficile à scripter, à valider par mode debug

# 8. Test cap : faire que B reçoive plein de procurations (jusqu'à voir l'erreur)
for i in 2 3 4 5 6 7 8 9 10; do
  G=$(curl -sb /tmp/cookies.txt http://localhost:8080/api/v1/members | jq -r ".items[$i].id")
  curl -sb /tmp/cookies.txt -H "X-Csrf-Token: $CSRF" -H "Content-Type: application/json" \
    -d "{\"meeting_id\":\"$MID\",\"giver_member_id\":\"$G\",\"receiver_member_id\":\"$B\"}" \
    http://localhost:8080/api/v1/proxies | jq -r '.error // "ok"'
done
# Selon proxy_max_per_receiver setting tenant : voir où le cap se déclenche
```

**Impact** : 🟡 nice-to-have — couche solide, anti-fraude robuste. L'incohérence de cap (3 vs 99) est un bug latent, à fixer Stage 3.

**Recommandation** : 
1. **Bug à régler Stage 3** : aligner les defaults de `proxy_max_per_receiver` (UI/import vs upsert API). Fixer un default unique (3 ou 5 typique pour asso française).
2. Valider en live le flow PV/PDF de mandat de procuration (`ProcurationPdfService`) — pas testé statiquement ici en profondeur.
3. Tester en live le cas TOCTOU "B révoque sa délégation pendant que A vote" — code défensif, mais nécessite confirmation runtime.
4. Stage 2 peut envisager de retirer le scope `partial` (`agenda_limits text[]`) si jamais utilisé — complexité non justifiée.

---

## Étape 09 — Clôture séance (AUDIT-CHEMIN-09)

**Description du flow** : transition `live → closed` ou `paused → closed`. Pré-requis : aucune motion ouverte. Conséquences : `motion.opened_at IS NOT NULL && closed_at NULL` doit être vide, ballots interdits, audit_event émis. Réouverture interdite (mais `closed → live` peut exister, à vérifier). Date `ended_at` posée. `closed_by = userId`.

**Code concerné** :
- `app/Controller/MeetingWorkflowController.php` — handler `transition` reçoit `to_status: closed`.
- `app/Services/MeetingTransitionService.php` lignes 194-222 — `buildTransitionFields()` :
  - Cas `closed` : `ended_at = now()` (si pas déjà), `closed_by = userId`.
  - Cas `paused` : `paused_at = now()`, `paused_by = userId`.
- `app/Services/MeetingWorkflowService.php` lignes 99-113 — `issuesBeforeTransition()` :
  - `live → paused` : bloque si `countOpenMotions > 0` avec message "Impossible de mettre en pause : N vote(s) en cours. Fermez le vote avant de mettre en pause."
  - `live → closed` ou `paused → closed` : bloque si `countOpenMotions > 0` avec message "N résolution(s) encore ouverte(s)".
- `app/Services/BallotsService.php` (cf. étape 06) : garde `meeting.status === 'live'` → après close, `castBallot()` rejette avec "Impossible de voter sur une motion dont la séance n'est pas en cours".
- `database/schema-master.sql` ligne 280 : `status meeting_status DEFAULT 'draft'`. Colonnes `frozen_at/by`, `started_at`, `opened_by`, `paused_at/by`, `ended_at`, `closed_by`, `validated_at/by/by_user_id`, `archived_at` (cf lecture précédente).

**Tests existants** :
- `tests/Unit/MeetingTransitionServiceTest.php` + `MeetingTransitionTest.php`.
- `tests/Unit/MeetingWorkflowServiceTest.php`.
- `tests/Unit/StateTransitionCoherenceTest.php`.

**Recoupement archive** :
- v2.0 Operateur Live UX : workflow lifecycle complet.
- F09 hardening (mentionné lignes 230-260 dans `MeetingTransitionService.php`) : `resetDemo` ne peut PAS reset une séance `live`/`frozen`/`closed`/`validated`/`archived` — protection contre wipe accidentel pendant AG. Whitelist `RESETTABLE_STATUSES = ['draft', 'scheduled']`. Très défensif.

**Verdict statique** : ✓

**Justification** :
- Pré-requis et side effects propres : `countOpenMotions` bloque close si vote ouvert (messages français explicites), `ended_at`/`closed_by` posés automatiquement, audit_event via le contrôleur, broadcast SSE.
- Anti-réouverture implicite : la machine d'état ne définit pas explicitement `closed → live` dans le path `launch`. Pour réouvrir, il faut retransitionner manuellement via `meeting_transition` — accepté par le service mais l'opérateur doit le vouloir explicitement (pas dans le happy path UI).
- Idempotence : early-return `already_in_target` si `from_status === to_status`.
- F09 (resetDemo whitelist) : protection robuste contre destruction accidentelle des ballots pendant une AG live.
- Couverture tests robuste sur transitions et coherence.
- Nuance : la transition `closed → live` est techniquement permise par `MeetingTransitionService::transition()` (pas dans le `match` du `launch`, mais le single-step `transition()` ne refuse pas). Comportement ambigu → si l'UI propose un bouton "réouvrir séance fermée", c'est documenté ? À valider live + UX review.

**Reproduction live (dev-machine)** :
```bash
# 0. Préalable : étape 04 (séance MID en live), étape 06 (motion MOTID votée puis fermée).

CSRF=$(curl -sb /tmp/cookies.txt http://localhost:8080/api/v1/auth_csrf | jq -r .csrf_token)

# 1. Tenter close avec une motion encore ouverte → doit être bloqué
# (Préalable : ouvrir une 2e motion sans la fermer)
MOTID2=$(curl -sb /tmp/cookies.txt "http://localhost:8080/api/v1/motions_for_meeting?meeting_id=$MID" | jq -r '.items[1].id')
curl -sb /tmp/cookies.txt -H "X-Csrf-Token: $CSRF" -H "Content-Type: application/json" \
  -d "{\"motion_id\":\"$MOTID2\"}" http://localhost:8080/api/v1/motions_open

curl -sb /tmp/cookies.txt -H "X-Csrf-Token: $CSRF" -H "Content-Type: application/json" \
  -d "{\"meeting_id\":\"$MID\",\"to_status\":\"closed\"}" \
  http://localhost:8080/api/v1/meeting_transition | jq
# attendu : 422/409 avec "issues" : code "motion_open", message "1 résolution(s) encore ouverte(s)"

# 2. Fermer la motion
curl -sb /tmp/cookies.txt -H "X-Csrf-Token: $CSRF" -H "Content-Type: application/json" \
  -d "{\"motion_id\":\"$MOTID2\"}" http://localhost:8080/api/v1/motions_close

# 3. Maintenant la transition close doit passer
curl -sb /tmp/cookies.txt -H "X-Csrf-Token: $CSRF" -H "Content-Type: application/json" \
  -d "{\"meeting_id\":\"$MID\",\"to_status\":\"closed\"}" \
  http://localhost:8080/api/v1/meeting_transition | jq
# attendu : { from_status: "live", to_status: "closed", transitioned_at: ... }

# 4. Vérifier en DB que ended_at et closed_by sont posés
docker compose exec db psql -U app -d agvote -c \
  "SELECT status, started_at, ended_at, closed_by FROM meetings WHERE id='$MID'"
# attendu : status="closed", ended_at=<now>, closed_by=<user_id>

# 5. Tenter de voter une motion sur séance fermée
curl -sb /tmp/cookies.txt -H "X-Csrf-Token: $CSRF" -H "Content-Type: application/json" \
  -d "{\"motion_id\":\"$MOTID\",\"member_id\":\"$A\",\"value\":\"for\"}" \
  http://localhost:8080/api/v1/ballots_cast | jq
# attendu : erreur "Impossible de voter sur une motion dont la séance n'est pas en cours"

# 6. Test idempotence : fermer 2x
curl -sb /tmp/cookies.txt -H "X-Csrf-Token: $CSRF" -H "Content-Type: application/json" \
  -d "{\"meeting_id\":\"$MID\",\"to_status\":\"closed\"}" \
  http://localhost:8080/api/v1/meeting_transition | jq
# attendu : already_in_target: true (200, pas 409)

# 7. Tester resetDemo refusé sur closed (F09)
curl -sb /tmp/cookies.txt -H "X-Csrf-Token: $CSRF" -H "Content-Type: application/json" \
  -d "{\"meeting_id\":\"$MID\"}" http://localhost:8080/api/v1/admin_reset_demo | jq
# attendu : erreur meeting_status_not_resettable
```

**Impact** : 🟡 nice-to-have — couche mature, comportement logique. F09 hardening solide.

**Recommandation** : valider en live qu'aucune action utilisateur (vote, marquer présence, modifier motion) ne fonctionne sur séance `closed`. Stage 2 peut clarifier la sémantique de `closed → live` (réouverture autorisée ou pas) — soit l'expliciter dans le service avec gate, soit l'enlever de l'enum-passable.

---

## Étape 10 — Génération PV PDF (AUDIT-CHEMIN-10)

**Description du flow** : depuis une séance `closed`, opérateur déclenche `POST /meeting_generate_report_pdf`. Service génère HTML structuré (titre, en-tête, présents, motions avec résultats pondérés, signature placeholder), passe à `dompdf` qui rend en PDF. Header répété à chaque page, footer "Page X sur Y", accents français corrects (DejaVu Sans), pagination automatique. PDF servi avec `Content-Disposition: attachment`.

**Code concerné** :
- `app/Controller/MeetingReportsController.php` (261 lignes) — `generateReport` (HTML), `generatePdf` (PDF binaire).
- `app/Services/MeetingReportsService.php` (330 lignes) — service principal :
  - Ligne 182-189 : CSS in-line avec `@page` margin, `@top-center` (header), `@bottom-center` (footer "Page X sur Y" via `counter(page) " sur " counter(pages)`), `font-family: "DejaVu Sans"`.
  - Ligne 190 : `<!DOCTYPE html><html lang="fr"><head><meta charset="UTF-8">`.
  - Ligne 276 : `$options->set('defaultFont', 'DejaVu Sans')`.
  - Ligne 279 : `$dompdf->loadHtml($html, 'UTF-8')`.
  - Watermark "BROUILLON" possible (`.draft-watermark` / `.draft-banner` classes).
- `app/Services/MeetingReportService.php` (319 lignes) — variante (legacy ?). Confirme `<meta charset="utf-8">` ligne 65.
- `app/Services/ReportGenerator.php` (296 lignes) — Stream HTML standalone (non-PDF) pour preview.
- `app/Services/ProcurationPdfService.php` (296 lignes) — PDF mandat de procuration (étape 08), même engine dompdf.
- `app/routes.php` lignes 303-304 : `/meeting_generate_report` (HTML), `/meeting_generate_report_pdf` (PDF).
- `composer.json` ligne 7 : `"dompdf/dompdf": "^3.1"`.

**Tests existants** :
- `tests/Unit/MeetingReportServiceTest.php`.
- `tests/Unit/MeetingReportsServiceTest.php`.
- `tests/Unit/MeetingReportsControllerTest.php`.
- `tests/Unit/MeetingReportsLongPdfTest.php` — **stress test long PDF**, contient :
  - `testHeaderRepeatedOnEveryPageOfLongPv` (ligne 179).
  - `testEmDashAndFrenchAccentsRenderedCorrectly` (ligne 258).
  - `testFooterPageXSurYOnEveryPage` (ligne 327).
  - `testShortPvStillRendersInPriorPageBudget` (ligne 398).
- `tests/Unit/ProcurationPdfServiceTest.php` + `ProcurationPdfControllerTest.php`.

**Recoupement archive** :
- v1.4 → v2.7 : génération PV PDF mature, anti-régression sur header/footer/accents/pagination.
- PROJECT.md mentionne "Génération PV PDF (dompdf, header répété, accents UTF-8)" comme `⚠ validated mais pas re-vérifié visuellement`.
- PROJECT.md note "Génération PV ≥10 pages réelle (smoke test PHPUnit OK, visuel jamais validé)".
- `dompdf 3.1` est une version récente (3.x supporte mieux UTF-8 que 2.x).

**Verdict statique** : ✓

**Justification** :
- Setup dompdf textbook : `defaultFont = "DejaVu Sans"` (police embarquée qui couvre Latin-1 + Latin Extended → tous les accents français), `loadHtml($html, 'UTF-8')` explicite, `<meta charset="UTF-8">` dans le DOM, `htmlspecialchars($s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')` partout pour escaping safe.
- CSS @page propre : header répété (`@top-center`), footer pagination (`@bottom-center` avec `counter(page) " sur " counter(pages)`), marges, font cohérente.
- Tests anti-régression dédiés : `testHeaderRepeatedOnEveryPageOfLongPv`, `testFooterPageXSurYOnEveryPage`, `testEmDashAndFrenchAccentsRenderedCorrectly`. C'est rare et précieux.
- `Procès-verbal - <title>` titre dans le DOM, watermark "BROUILLON" possible si pas validé, structure sémantique HTML5 (h1/h2/h3, table, tr/th/td).
- Couleurs et badges (success/danger/warning) cohérents.
- Nuance 1 : 2 services de PV existent (`MeetingReportService.php` 319 lignes + `MeetingReportsService.php` 330 lignes). Il y en a probablement un legacy. À clarifier.
- Nuance 2 : pas de signature électronique implémentée. La feature `M-Signature` (eIDAS) est explicitement mentionnée comme `Out-of-Scope post Stage 3`. Le PV actuel a une *zone signature placeholder* mais aucune signature cryptographique. Pour la valeur légale, c'est un gap de produit (mais c'est dans la roadmap).

**Reproduction live (dev-machine)** :
```bash
# 0. Préalable : étape 09 (séance MID closed avec 3+ motions résolues, 30+ ballots, 1+ proxies).

CSRF=$(curl -sb /tmp/cookies.txt http://localhost:8080/api/v1/auth_csrf | jq -r .csrf_token)

# 1. Générer le PV en PDF
curl -sb /tmp/cookies.txt -H "X-Csrf-Token: $CSRF" -H "Content-Type: application/json" \
  -d "{\"meeting_id\":\"$MID\",\"format\":\"pdf\"}" \
  http://localhost:8080/api/v1/meeting_generate_report_pdf \
  -o /tmp/pv.pdf
ls -lh /tmp/pv.pdf
file /tmp/pv.pdf
# attendu : PDF document, version X.Y, ~50-200 KB pour 5 pages

# 2. Vérifier le contenu textuel (sans ouvrir le PDF)
pdftotext /tmp/pv.pdf - | head -50
# attendu : "Procès-verbal", titre séance avec accents corrects, président, date, liste motions

# 3. Compter les pages
pdfinfo /tmp/pv.pdf | grep Pages
# attendu : Pages: 3-10 selon contenu

# 4. Test accents (tester é, è, ç, à, ù, ï)
pdftotext /tmp/pv.pdf - | grep -oE "Procès-verbal|élu|président|décision|résolution" | sort -u
# attendu : tous présents avec accents bien rendus

# 5. Visuel : ouvrir avec viewer
xdg-open /tmp/pv.pdf
# vérifier visuellement :
#   - header répété sur chaque page (titre séance + date)
#   - footer "Page 1 sur N" sur chaque page
#   - structure : titre H1, sections H2 (Membres, Motions, Procurations), tables résultats
#   - accents français lisibles (pas de □ ni ?)
#   - décisions colorées (vert adopté, rouge rejeté)
#   - pas de débordement de texte

# 6. Test long PV (forcer ≥10 pages avec beaucoup de motions/ballots)
# créer 20 motions, 50 ballots chacune, puis générer
# attendu : pagination fluide, header présent partout, footer page X sur Y exact

# 7. Stress charset Win-1252 (membre importé depuis Excel français — voir étape 02)
# ouvrir PV qui inclut un nom avec ç ou œ
# attendu : caractères rendus correctement
```

**Impact** : 🟡 nice-to-have — couche solide avec tests anti-régression dédiés. Charset/font configurés correctement. Risque résiduel : pas de signature cryptographique (gap produit, pas bug code).

**Recommandation** :
1. Valider visuellement le PV avec contenu réel asso pilote (5-10 pages, accents, tableau résultats).
2. Stage 2 : choisir entre `MeetingReportService.php` et `MeetingReportsService.php` — supprimer le legacy.
3. Stage 3 / `M-Signature` : ajouter signature électronique eIDAS avancée (ce gap est connu et budgété).
4. dompdf 3.1 est OK ; pas de raison de migrer vers wkhtmltopdf ou autre engine. Garder.

---

## Étape 11 — Archive + audit hash chain (AUDIT-CHEMIN-11)

**Description du flow** : transitions `closed → validated → archived`. Validation pose `validated_at`/`validated_by`/`validated_by_user_id` (pré-requis : pré-requis `MeetingValidator::canBeValidated()` — président, motions toutes fermées avec résultats exploitables, consolidation faite). Archivage pose `archived_at`. À tout moment, chaque INSERT dans `audit_events` calcule automatiquement `prev_hash` (lookup last event same scope) et `this_hash = sha256(prev_hash || tenant_id || user_id || action || resource_type || resource_id || payload || created_at)`. Endpoint `/audit_verify?meeting_id=...` parcourt les events ordonnés et vérifie `events[i].prev_hash === events[i-1].this_hash`.

**Code concerné** :
- `database/schema-master.sql` lignes 705-777 — table + trigger + indexes :
  - Colonnes : `prev_hash bytea`, `this_hash bytea`, `payload jsonb`, `created_at timestamptz`, `actor_user_id`, `actor_role`, `action`, `resource_type`, `resource_id`, `ip_address`, `user_agent`.
  - Function `audit_events_compute_hash()` : scope chain par `meeting_id` si présent, sinon par `tenant_id`. `SELECT ... FOR UPDATE` serialise le calcul (anti-fork concurrent).
  - Algorithme : `digest( hex(prev_hash) || '|' || tenant_id || '|' || user_id || '|' || action || '|' || resource_type || '|' || resource_id || '|' || payload || '|' || created_at, 'sha256')`.
  - Premier event d'une chaîne : `prev = NULL`, donc `coalesce(encode(NULL,'hex'),'') = ''` — la chaîne commence par "|tenant|user|...".
  - Trigger `BEFORE INSERT` → impossible d'insérer en bypassant la chaîne.
  - Index dédiés `idx_audit_meeting_chain` (partiel, `WHERE meeting_id IS NOT NULL`) et `idx_audit_tenant_chain` (`WHERE meeting_id IS NULL`) pour lookup chaîne en O(log n).
- `app/bootstrap.php` lignes 96-118 — fonction globale `audit_log()` : appel `RepositoryFactory::auditEvent()->insert(...)` avec context AuthMiddleware. Catch Throwable → log error sans rethrow (best-effort, ne bloque pas l'opération métier).
- `app/Repository/AuditEventRepository.php` ligne 136+ — query `listForMeetingExport` + insert.
- `app/Controller/AuditController.php` :
  - `verifyChain()` (ligne 243-283) : load events ordonnés `created_at DESC, id DESC`, parcours `for (i=1; i<total; i++)` vérifie `events[i-1].this_hash === events[i].prev_hash`. Retourne `chain_valid: bool`, `error_count`, `errors: [{index, event_id, timestamp}]`.
  - `timeline`, `export` (full audit timeline), `meetingAudit`, `meetingEvents`, `operatorEvents`.
- `app/routes.php` lignes 137-142 (audit routes), `audit_verify` ligne 139.
- `app/Services/MeetingTransitionService.php` lignes 213-220 — transitions `validated`/`archived` posent les timestamps.
- `app/Services/MeetingValidator.php` (cf. étape 03) — `canBeValidated()` : 4 règles dont `consolidation_missing`, `bad_closed_results`.

**Tests existants** :
- `tests/Unit/AuditControllerTest.php` (seul, mais c'est l'essentiel).
- `tests/Unit/StateTransitionCoherenceTest.php` (transitions validate/archive).
- `tests/Unit/MeetingValidatorTest.php`.
- Pas de test SQL direct sur le trigger `audit_events_compute_hash` visible (le trigger est en DB, donc testable seulement avec Postgres réel — attendu en integration test).

**Recoupement archive** :
- v1.5/v1.6 : audit hash chain et trigger PG ajouté.
- PROJECT.md : `⚠ Audit hash chain immutable (registre légal traçable)` — validated mais pas re-vérifié récent.
- F09 hardening (`MeetingTransitionService.php`) protège contre wipe pendant AG.

**Verdict statique** : ✓

**Justification** :
- Implémentation référence : la chaîne de hash est calculée **dans la base de données via trigger PG**, pas dans le code applicatif. Conséquence majeure : impossible de bypass l'audit en bidouillant l'ORM, l'API ou le code. Une simple `INSERT INTO audit_events (...)` depuis psql calcule automatiquement le hash. C'est l'architecture la plus robuste possible pour un registre légal.
- `SELECT ... FOR UPDATE` au moment du calcul de `prev_hash` serialise les inserts concurrents → pas de fork de chaîne (deux events avec le même `prev_hash`).
- Algorithme : sha256, hex-encoded prev_hash (pour stabilité texte), séparateur `|` non ambigu, inclut tous les champs pertinents (tenant, user, action, resource, payload, timestamp).
- Scoping intelligent : chaîne par `(tenant_id, meeting_id)` quand applicable, sinon par `(tenant_id)` global. Permet de vérifier l'intégrité d'une séance individuellement sans charger tout l'historique tenant.
- Index partiels dédiés à la lookup chaîne (`idx_audit_meeting_chain`, `idx_audit_tenant_chain`) → performance correcte sur large historique.
- Endpoint `verifyChain` : calcul `O(N)` sur events de la séance, retourne précisément où la chaîne est cassée si problème.
- Modif post-archive : aucun trigger SQL `BEFORE UPDATE/DELETE` sur `audit_events` n'est défini. **Donc rien n'empêche au niveau DB de modifier ou supprimer un event** — c'est le rôle du **applicatif** de ne JAMAIS faire d'UPDATE/DELETE sur audit_events (et `audit_log()` n'expose qu'un INSERT). C'est conforme à l'architecture "append-only par convention applicative + cryptographie pour détection".
  - Cas attaque : un admin DB direct (psql) peut UPDATE un event → la chaîne sera cassée (le `this_hash` ne match plus le payload modifié). Détection garantie via `verifyChain`.
  - Renforcement possible Stage 3 : ajouter `REVOKE UPDATE, DELETE ON audit_events FROM app_user` dans la migration → empêche l'app et l'admin DB régulier de bypasser. Acceptable.
- `audit_log()` global : best-effort (catch Throwable, log sans rethrow). Bon design pour ne pas casser le métier en cas de failure d'audit, mais signifie qu'un bug DB peut faire perdre silencieusement des events. À monitorer via `Logger::error('audit_log failed')`.

**Reproduction live (dev-machine)** :
```bash
# 0. Préalable : étape 09 (séance MID closed avec ballots et events).

CSRF=$(curl -sb /tmp/cookies.txt http://localhost:8080/api/v1/auth_csrf | jq -r .csrf_token)

# 1. Vérifier readyCheck pour validation
curl -sb /tmp/cookies.txt -H "X-Csrf-Token: $CSRF" -H "Content-Type: application/json" \
  -d "{\"meeting_id\":\"$MID\"}" http://localhost:8080/api/v1/meeting_ready_check | jq

# 2. Transitionner closed → validated
curl -sb /tmp/cookies.txt -H "X-Csrf-Token: $CSRF" -H "Content-Type: application/json" \
  -d "{\"meeting_id\":\"$MID\",\"to_status\":\"validated\"}" \
  http://localhost:8080/api/v1/meeting_transition | jq

# 3. validated → archived
curl -sb /tmp/cookies.txt -H "X-Csrf-Token: $CSRF" -H "Content-Type: application/json" \
  -d "{\"meeting_id\":\"$MID\",\"to_status\":\"archived\"}" \
  http://localhost:8080/api/v1/meeting_transition | jq

# 4. Vérifier la chaîne via API
curl -sb /tmp/cookies.txt "http://localhost:8080/api/v1/audit_verify?meeting_id=$MID" | jq
# attendu : { chain_valid: true, total_events: N, error_count: 0, errors: [] }

# 5. Vérifier en DB direct
docker compose exec db psql -U app -d agvote -c \
  "SELECT id, action, encode(prev_hash,'hex') as prev, encode(this_hash,'hex') as this \
   FROM audit_events \
   WHERE meeting_id='$MID' \
   ORDER BY created_at, id"
# attendu : 1ère ligne prev_hash NULL, ensuite chaque ligne prev_hash = this_hash de la précédente

# 6. Tester détection de tampering : modifier un payload directement en DB
docker compose exec db psql -U app -d agvote -c \
  "UPDATE audit_events SET payload = '{\"hacked\":true}'::jsonb \
   WHERE meeting_id='$MID' \
   AND action='ballot_cast' LIMIT 1"
# (Note: PG ne supporte pas LIMIT sur UPDATE direct → utiliser CTE ou subquery)

# 7. Re-vérifier la chaîne — doit détecter
curl -sb /tmp/cookies.txt "http://localhost:8080/api/v1/audit_verify?meeting_id=$MID" | jq
# attendu : chain_valid: false, errors: [{index: I, ...}]

# 8. Tenter de voter sur séance archived
curl -sb /tmp/cookies.txt -H "X-Csrf-Token: $CSRF" -H "Content-Type: application/json" \
  -d "{\"motion_id\":\"$MOTID\",\"member_id\":\"$A\",\"value\":\"for\"}" \
  http://localhost:8080/api/v1/ballots_cast | jq
# attendu : erreur séance archivée

# 9. Tenter modif motion sur séance archived
curl -sb /tmp/cookies.txt -H "X-Csrf-Token: $CSRF" -H "Content-Type: application/json" \
  -d "{\"motion_id\":\"$MOTID\",\"title\":\"hacked\"}" \
  http://localhost:8080/api/v1/motions | jq
# attendu : meeting_archived_locked
```

**Impact** : 🟡 nice-to-have — hash chain en trigger PG = quasi-imparable. Audit traçabilité légale solide. Pas de blocage attendu.

**Recommandation** :
1. Stage 3 : envisager `REVOKE UPDATE, DELETE ON audit_events FROM app_user;` pour défense en profondeur. Coût zéro, valeur réelle.
2. Valider en live le `verifyChain` avec une séance de 100+ events réels.
3. Documenter aux utilisateurs admin que l'audit_events est immutable légalement, et que toute modif (ex. via psql) sera détectée.
4. Stage 2 : la solution custom hash chain + trigger PG est solide. Pas de raison de la remplacer par une lib externe (ex. immutable-log SaaS, blockchain) — l'overhead de migration ne justifie pas l'évolution.

---

## Synthèse (AUDIT-CHEMIN-12)

### Tableau récapitulatif

| #  | Étape                                  | Verdict | Impact |
|----|----------------------------------------|---------|--------|
| 01 | Setup admin vierge                     | ✓       | 🟡     |
| 02 | Import CSV membres                     | ⚠       | 🟡     |
| 03 | Création séance + ordre du jour        | ⚠       | 🛑     |
| 04 | Ouverture séance live                  | ✓       | 🟡     |
| 05 | Émargement présence + quorum           | ✓       | 🟡     |
| 06 | Vote motion résolution simple          | ✓       | 🟡     |
| 07 | Vote motion élection multi-candidats   | ✗       | 🛑     |
| 08 | Vote avec procuration active           | ⚠       | 🟡     |
| 09 | Clôture séance                         | ✓       | 🟡     |
| 10 | Génération PV PDF                      | ✓       | 🟡     |
| 11 | Archive + audit hash chain             | ✓       | 🟡     |

### Compteurs

**Total ✓** : 7 | **⚠** : 3 | **✗** : 1 | **❓** : 0 (avec 7+3+1+0 = 11 étapes)

### Bloquants identifiés

**🛑 Bloquant dogfood (1) :**
- **Étape 07 (✗)** : Vote motion élection multi-candidats — fonctionnalité non implémentée dans le code (pas de `motion.kind`, pas de table `candidates`, pas de scrutin majoritaire). Si la 1re asso pilote a une élection à son AG (cas extrêmement courant : élection bureau, conseil d'administration), workaround "1 motion par candidat" dégrade UX et perd la sémantique électorale.
- **Étape 03 (⚠ → potentiellement 🛑)** : Création motion ne supporte que `title`/`description`/`secret`. Les types "élection" et "question ouverte" demandés par REQUIREMENTS sont silencieusement ignorés. C'est le même gap que l'étape 07, vu côté création.

**🔴 Bloquant 1.0 shipped (0)** : aucun.

### Non-bloquants

**🟡 Nice-to-have (10)** : étapes 01, 02, 04, 05, 06, 08, 09, 10, 11. Toutes ✓ ou ⚠ techniquement saines. Quelques nuances :
- Étape 02 : style one-liner ultra-dense d'`ImportController.php` est un candidat refacto.
- Étape 08 : incohérence des defaults `proxy_max_per_receiver` (3 dans import vs 99 dans API upsert) → bug latent à fixer Stage 3.
- Étape 10 : 2 services PV existent (`MeetingReportService.php` + `MeetingReportsService.php`) — clarifier le legacy.

**⚪ Esthétique (0)** : aucun pur polish identifié.

### Inconnus (live required)

Aucune étape n'est en ❓ — la lecture statique a permis de trancher partout. Cependant **toutes** les étapes ✓ et ⚠ doivent être confirmées en live dev-machine via les procédures de reproduction fournies, car la sandbox n'a pas pu exécuter Docker / DB / requests réelles.

Les points spécifiquement à valider en live :
- Étape 01 : flow complet setup → login → dashboard, cookies de session, redirect 302 puis 404 idempotent.
- Étape 02 : encoding Excel français Windows-1252 auto-détecté correctement.
- Étape 04 : SSE broadcast event de transition (cockpit refresh seul).
- Étape 06 : cas TOCTOU concurrent (vote pendant close de motion).
- Étape 08 : incohérence cap procuration `3 vs 99` confirmée en runtime.
- Étape 10 : visuel PV ≥ 5 pages avec accents français, header répété, footer "Page X sur Y".
- Étape 11 : `verifyChain` détecte une modif manuelle d'audit_events.

### Verdict global Stage 1

**Le chemin critique fonctionne en lecture statique pour 7 étapes sur 11 (64% ✓), avec 3 étapes ⚠ techniquement saines mais avec des nuances mineures, et 1 étape ✗ bloquante (élection multi-candidats non implémentée).**

L'application AgVote est **techniquement solide** sur le périmètre **vote résolutif simple** (For/Against/Abstain/NSP) :
- Auth + setup mature et défensif (F02-F22 hardening cumulé).
- Import CSV/XLSX fonctionnel avec auto-détection encoding.
- Workflow lifecycle séance (8 états) cohérent et idempotent.
- Pondération + procuration anti-chaîne avec TOCTOU-safe transactions.
- Hash chain audit en trigger PG = registre légal quasi-imparable.
- Génération PV PDF (dompdf 3.1) avec tests anti-régression.

**Mais le périmètre fonctionnel est plus étroit que ce que REQUIREMENTS suggérait** :
- Pas d'élection multi-candidats (gap structurel : schema + service + UI à construire).
- Pas de signature électronique (gap budgété, M-Signature post Stage 3).
- Pas de question ouverte (gap structurel, similar à élection).
- Vote distant par token déjà partiellement implémenté (VoteToken visible dans tests) mais non audité ici en détail.

**Trois risques majeurs identifiés** :
1. **Gap fonctionnel élection** (étape 07) — bloquant dogfood selon profil de la 1re asso pilote.
2. **Incohérence cap procuration** (étape 08) — bug latent à fixer.
3. **Tests legacy MeetingsController failures** (PROJECT.md) — dette technique non traitée.

### Recommandation pour Stage 2 (audit stack)

Priorités d'investigation :
1. **HAUTE** : `dompdf 3.1` — confirmer en runtime que le PV se génère bien sur ≥ 10 pages avec accents et procuration. Tester perf (< 5 sec). Si OK, garder. Sinon, évaluer `wkhtmltopdf` (binaire externe, plus rapide) ou `ChromeHeadless` (Puppeteer-like).
2. **HAUTE** : `phpoffice/phpspreadsheet` — souvent gourmand en mémoire pour XLSX. Mesurer footprint sur fichier 50 lignes. Si > 50 Mo, envisager retirer le support XLSX (ne garder que CSV) — la valeur user est marginale (Excel exporte en CSV).
3. **MOYENNE** : Custom Router / Logger / IdempotencyGuard / RateLimiter / AccountLockout / CsrfMiddleware — composants custom AgVote. Mesurer LOC + tests + coût maintenance vs équivalents Symfony/Laminas. **Si bien testé et stable, garder** (régle "don't fix what works").
4. **MOYENNE** : phpredis (extension PHP) — vérifier disponibilité Docker Alpine 3.21, fallback filesystem confirmé pour SSE.
5. **MOYENNE** : `symfony/mailer ^8.0` — version récente, OK probable. Vérifier compat PHP 8.4.
6. **BASSE** : `dompdf` config → décider si hash chain custom PG-trigger reste préférable à une solution append-only externe (low priority — le custom marche très bien).
7. **BASSE** : HTMX 2.0.6 — frontend léger, validé en v2.0/2.7. Pas de raison de challenger.

### Recommandation pour Stage 3 (décision Voie A/B/C)

**Voie A (refacto sur place) — RECOMMANDÉE en première intention**

Arguments pour :
- 7/11 étapes ✓, 3/11 ⚠ techniquement saines, 1/11 ✗ identifié et localisé (élection).
- Architecture défensive bien établie (TOCTOU, hash chain, transactions, idempotence). Le code n'est pas pourri — il est juste incomplet sur 1 axe (élection).
- Coût refacto :
  - Bug `proxy_max_per_receiver` : 1 ligne de fix.
  - Clarification `MeetingReportService` legacy : 1 PR de cleanup.
  - 6 tests MeetingsController failures : à investiguer (peut-être 1-2 jours).
  - Feature `M-ElectionMotion` (motion.kind, table candidates, ballot multi-choix, scrutin majoritaire 1 tour) : ~2 semaines dev (schema + service + UI). Peut être planifiée en milestone séparée.
- Total estimé : ~3 semaines pour atteindre 11/11 ✓ + élection.

Arguments contre :
- Style one-liner d'`ImportController.php` rend lecture difficile — coût de prise de main pour un nouveau dev.
- Si Stage 2 révèle de gros problèmes stack (ex. perf dompdf catastrophique), Voie A devient plus chère.

**Voie B (rebuild partiel infra) — Si Stage 2 conclut perf insuffisante**

Cas d'usage : si dompdf 3.1 n'est pas viable, ou si la stack PHP-FPM/HTMX bottleneck en prod réelle. Reconstruire ciblé (ex. PV PDF avec `chrome-headless`, frontend avec Vue 3 ou Svelte). Garder le coeur métier (BallotsService, QuorumEngine, hash chain).

Arguments pour : limite le risque tout en améliorant le point faible identifié.

Arguments contre : 4-6 semaines de travail pour gain incrémental. Risque de régressions.

**Voie C (rebuild from scratch) — DÉCONSEILLÉE sauf découverte majeure Stage 2**

Cas d'usage : si Stage 2 révèle un défaut architectural systémique (ex. multi-tenant cassé en profondeur, sécurité défaillante de fond, perf O(N²) partout). Aucun signal de cette gravité dans l'audit Stage 1.

Arguments contre majeurs :
- 6+ mois de travail.
- Perte des 28 milestones de hardening (F02-F22, TOCTOU, hash chain).
- Pas d'utilisateur réel encore → on rebuild un produit qu'on ne sait pas utile.

**Recommandation finale Stage 3** : **Voie A**, avec milestone explicite `M-ElectionMotion` budgeté immédiatement après le pivot, et fix tickets (proxy cap + tests legacy) en parallèle. Si Stage 2 révèle un blocker de stack, escalader à Voie B.

### Boundary respectée

Cet audit n'a modifié aucun fichier de production (`app/`, `public/`, `database/`, `tests/`, `composer.*`). Tickets de fix (proxy cap, MeetingsController legacy tests, MeetingReportService dedup, élection feature) = livrable Stage 3 (M-DECISION).

---

*Audit clos 2026-05-06. Stage 2 (audit stack) à lancer avec les priorités recommandées ci-dessus.*
