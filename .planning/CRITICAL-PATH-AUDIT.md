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
- **Nuance 2 (CLAUDE.md)** : la mention `'tantiemes', 'tantièmes'` dans le column map (ligne 125 ImportService.php) est un terme à connotation copropriété. CLAUDE.md proscrit "copropriété/syndic" comme vocabulaire user-visible. Ici c'est un alias en-tête CSV donc l'utilisateur a juste l'option d'utiliser ce mot dans son fichier source — ce n'est pas affiché dans l'UI. Acceptable mais à noter.
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

**Recommandation** : valider en live qu'une politique de quorum tenant fallback existe par défaut (sinon `met: null` → cockpit peut afficher "—" au lieu de "atteint"). Stage 2 peut interroger : 3 modes (single/double/evolving) sont-ils tous nécessaires en pratique ? Le mode `double` est rare en associations (plus typique en copropriété — donc à proscrire selon CLAUDE.md ?), à confirmer terrain.

---
