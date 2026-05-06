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
