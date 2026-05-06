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
