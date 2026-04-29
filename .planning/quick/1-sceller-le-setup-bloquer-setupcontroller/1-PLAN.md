---
plan_id: quick-1
title: Sceller le setup — 404 si admin existe + CSRF strict
mode: quick
wave: 1
depends_on: []
autonomous: true
files_modified:
  - app/Controller/SetupController.php
  - app/Templates/setup_form.php
  - tests/Unit/SetupControllerHardeningTest.php
must_haves:
  truths:
    - "GET ou POST /setup retourne 404 dès qu'un admin actif existe en BD (plus de redirect leakant l'état)"
    - "POST /setup sans token CSRF valide est refusé avec une erreur visible (formulaire ré-affiché avec message FR)"
    - "Une instance fraîche (zéro admin) peut toujours compléter le setup avec un token CSRF valide"
    - "SetupController reste dans son contrat actuel : pas d'extension d'AbstractController, sortie via HtmlView::render()"
  artifacts:
    - "app/Controller/SetupController.php contient un guard 404 et une validation CSRF avant handlePost()"
    - "app/Templates/setup_form.php injecte CsrfMiddleware::field() dans le <form>"
    - "tests/Unit/SetupControllerHardeningTest.php couvre 404-on-admin, missing-csrf, bad-csrf, success-path"
  key_links:
    - app/Controller/SetupController.php
    - app/Core/Security/CsrfMiddleware.php
    - app/Templates/setup_form.php
    - tests/Unit/SetupControllerHardeningTest.php
---

# Plan: Sceller le setup

## Contexte

Vecteur d'attaque ciblé (audit sécurité 2026-04-29) : `SetupController` est exposé sans CSRF. Bien que `hasAnyAdmin()` empêche déjà une seconde init, deux faiblesses subsistent :

1. **Info leak** : sur une instance déjà initialisée, `/setup` redirige vers `/login` (302). Un attaquant peut distinguer "instance vierge" vs "instance configurée" — utile pour cibler une fenêtre d'init.
2. **CSRF absent sur POST** : un attaquant qui devine qu'une instance fraîche est en cours de déploiement peut héberger un formulaire qui, si l'IT admin le déclenche avant le setup légitime, crée un compte admin avec des identifiants choisis par l'attaquant.

Le commentaire de sécurité actuel (lignes 22-24) défend l'absence de CSRF : ce raisonnement omet la fenêtre d'attaque pré-init. On l'aligne sur la défense en profondeur.

## Tâches

<task id="1" name="Sceller SetupController">
  <files>app/Controller/SetupController.php</files>
  <action>
    1. Remplacer le bloc `hasAnyAdmin() → redirect('/login')` par une réponse `404 Not Found` opaque. En mode test (PHPUNIT_RUNNING), lever `SetupRedirectException('/404', 404)` pour permettre l'assertion ; en prod, `http_response_code(404); exit;` sans corps (pas de leak).
    2. Avant `handlePost()` ET avant `renderForm()` (GET), démarrer la session via `\AgVote\Core\Security\CsrfMiddleware::init()` afin que le token CSRF soit disponible dans le template.
    3. Dans `handlePost()`, AVANT toute lecture du `Request`, appeler `CsrfMiddleware::validate(false)` (mode non-strict pour pouvoir rendre la page setup avec une bannière FR plutôt qu'une exception API JSON). Si `false`, ré-afficher le formulaire avec un `$errors[]` dédié : `"Jeton de sécurité invalide. Rechargez la page et réessayez."` et préserver les anciennes valeurs (sauf mots de passe).
    4. Mettre à jour le doc-block de sécurité du contrôleur pour refléter la nouvelle posture (CSRF requis + 404 opaque).
  </action>
  <verify>
    - `php -l app/Controller/SetupController.php` → No syntax errors detected
    - `grep -n "404\|CsrfMiddleware" app/Controller/SetupController.php` → présence du guard 404 et de l'appel CSRF
    - Aucune occurrence de `redirect('/login')` dans la branche `hasAnyAdmin()`
  </verify>
  <done>
    Le contrôleur sert 404 si admin existe, valide CSRF sur POST, conserve son contrat HtmlView, garde declare(strict_types=1).
  </done>
</task>

<task id="2" name="Injecter le champ CSRF dans le template">
  <files>app/Templates/setup_form.php</files>
  <action>
    Ajouter `<?= \AgVote\Core\Security\CsrfMiddleware::field() ?>` comme premier enfant du `<form id="setupForm">` (juste après l'ouverture `<form ...>`, avant le bloc d'erreurs). Le helper renvoie un input hidden déjà échappé.
  </action>
  <verify>
    - `php -l app/Templates/setup_form.php` → No syntax errors detected
    - `grep -n "CsrfMiddleware::field" app/Templates/setup_form.php` → 1 occurrence dans le formulaire
  </verify>
  <done>
    Le formulaire embarque le token CSRF en hidden input ; rendu HTML inchangé visuellement.
  </done>
</task>

<task id="3" name="Tests unitaires de durcissement">
  <files>tests/Unit/SetupControllerHardeningTest.php</files>
  <action>
    Nouveau fichier de test PHPUnit dans le namespace `Tests\Unit` qui couvre :
    1. `testReturns404WhenAdminExists()` : `SetupRepository::hasAnyAdmin()` mocké à `true`, GET → `SetupRedirectException` avec code 404 (pas 302).
    2. `testRejectsPostWithoutCsrfToken()` : zéro admin, POST sans `csrf_token` → form ré-affiché, erreur FR "Jeton de sécurité invalide" présente, `createTenantAndAdmin` non appelé.
    3. `testRejectsPostWithInvalidCsrfToken()` : zéro admin, session contient un token, POST envoie un token différent → idem rejet.
    4. `testAcceptsPostWithValidCsrfToken()` : zéro admin, token session = token POST, payload valide → `createTenantAndAdmin()` appelé, `SetupRedirectException('/login?setup=ok')`.
    Utiliser un double de `SetupRepository` injecté via le constructeur (le contrôleur supporte déjà `?SetupRepository`). Activer `define('PHPUNIT_RUNNING', true)` si non défini par bootstrap. Ne pas lancer toute la suite — uniquement ce fichier.
  </action>
  <verify>
    - `php -l tests/Unit/SetupControllerHardeningTest.php` → No syntax errors detected
    - `timeout 60 php vendor/bin/phpunit tests/Unit/SetupControllerHardeningTest.php --no-coverage` → 4 tests, OK
  </verify>
  <done>
    Les 4 tests passent, couvrant les régressions futures sur le 404 et la validation CSRF.
  </done>
</task>

## Critères de vérification (must_haves)

- [ ] `php -l` propre sur les 3 fichiers modifiés
- [ ] `timeout 60 php vendor/bin/phpunit tests/Unit/SetupControllerHardeningTest.php --no-coverage` : 4 tests OK
- [ ] Manuellement : `curl -X POST http://localhost/setup -d "organisation_name=X..."` sans csrf_token sur instance vierge → page setup ré-affichée avec erreur FR
- [ ] Manuellement : `curl -i http://localhost/setup` sur instance configurée → `HTTP/1.1 404 Not Found`, pas de redirect

## Hors scope (à traiter dans d'autres tâches du Sprint 0)

- F2 — TRUSTED_PROXIES + helper ClientIp
- F3 — Idempotence sur degraded_tally
- F4 — Audit per-member sur members_bulk
- F5 — Auth-first dans SSE stream

## Risque de régression

**Faible.** Le contrôleur garde son contrat (HtmlView, pas d'AbstractController). Le seul changement de comportement observable côté utilisateur :
- Avant : `/setup` sur instance configurée → 302 vers `/login` (l'utilisateur arrive sur login).
- Après : `/setup` sur instance configurée → 404 (l'utilisateur voit la page d'erreur). C'est le comportement souhaité — `/setup` n'est pas une URL utilisateur après le premier déploiement.

Aucun appelant interne ne fait de fetch sur `/setup` après init.
