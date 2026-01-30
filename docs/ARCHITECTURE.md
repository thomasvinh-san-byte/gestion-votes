# Architecture technique — AG-Vote

## Vue d'ensemble

AG-Vote est une application PHP 8.3+ / PostgreSQL 16+ / HTMX pour la gestion de seances de vote formelles. Architecture sans framework, API-first, avec PostgreSQL comme source de verite.

## Arborescence du projet

```
gestion-votes/
├── public/                     Racine web Apache (DocumentRoot)
│   ├── api/v1/                 118 endpoints REST PHP
│   ├── assets/
│   │   ├── css/                3 fichiers CSS (design-system, app, ui)
│   │   └── js/                 7 fichiers JS (shell, utils, auth-ui, vote, meetings, meeting-context, pv-print)
│   ├── partials/               Composants HTML partages
│   ├── fragments/              Fragments PHP (drawer, etc.)
│   ├── exports/                Templates d'export (PV)
│   ├── errors/                 Pages 404, 500
│   ├── *.htmx.html             22 pages applicatives
│   ├── favicon.svg             Icone du site
│   └── .htaccess               Routage, securite, cache, compression
├── app/                        Code backend (hors webroot)
│   ├── api.php                 Point d'entree API — fonctions canoniques
│   ├── bootstrap.php           Initialisation (DB, .env, constantes)
│   ├── config.php              Configuration applicative
│   ├── auth.php                Legacy auth (desactive, conserve)
│   ├── services/               17 services metier
│   ├── Core/
│   │   ├── Security/           AuthMiddleware, CsrfMiddleware, RateLimiter, SecurityHeaders
│   │   └── Validation/         InputValidator, ValidationSchemas
│   ├── templates/              Templates email
│   └── .htaccess               Deny all (defense en profondeur)
├── database/
│   ├── schema.sql              Schema DDL complet (35+ tables, triggers, index)
│   ├── seeds/                  Donnees de test (users, members)
│   ├── migrations/             Migrations incrementales
│   └── .htaccess               Deny all
├── datasets/                   Jeux de donnees de recette
├── config/                     Configuration avancee
├── docs/                       Documentation
├── tests/                      Tests unitaires (PHPUnit)
├── .env                        Variables d'environnement
├── .env.production             Template production
└── .htaccess                   Protection racine (.env, vendor, app, database)
```

## Couche API

### Point d'entree unique : app/api.php

Tout endpoint PHP dans public/api/v1/ commence par :
```php
require __DIR__ . '/../../../app/api.php';
```

Ce fichier charge bootstrap.php (DB, .env) puis expose les fonctions canoniques :

**Reponses :**
- `api_ok(array $data, int $code = 200)` — Reponse JSON succes `{"ok":true,"data":{...}}`
- `api_fail(string $error, int $code = 400, array $extra = [])` — Reponse JSON erreur `{"ok":false,"error":"code"}`

**Authentification :**
- `api_require_role(string|array $roles)` — Verifie le role via AuthMiddleware. Bloque avec 403 si non autorise.
- `api_current_tenant_id()` — Retourne le tenant_id courant (multi-tenancy).

**Requetes :**
- `api_request(string $method)` — Valide la methode HTTP, decode le body JSON.
- `api_get_body()` — Retourne le corps de la requete decode.

**Gardes metier :**
- `api_guard_meeting_not_validated(string $meetingId)` — Bloque toute modification sur une seance validee (409).
- `api_guard_meeting_exists(string $meetingId)` — Verifie l'existence de la seance (404).

**Base de donnees (via bootstrap.php) :**
- `db_select(string $sql, array $params)` — SELECT multiple rows
- `db_select_one(string $sql, array $params)` — SELECT single row
- `db_scalar(string $sql, array $params)` — SELECT single value
- `db_exec(string $sql, array $params)` — INSERT/UPDATE/DELETE
- `audit_log(string $action, string $resource_type, string $resource_id, array $payload, ?string $meetingId)` — Enregistrement audit

### Convention d'un endpoint

```php
<?php
require __DIR__ . '/../../../app/api.php';

// 1. Authentification
api_require_role(['operator', 'admin']);

// 2. Validation methode
$input = api_request('POST');

// 3. Extraction et validation des parametres
$meetingId = trim((string)($input['meeting_id'] ?? ''));
if ($meetingId === '') api_fail('missing_meeting_id', 422);

// 4. Gardes metier
api_guard_meeting_exists($meetingId);
api_guard_meeting_not_validated($meetingId);

// 5. Logique metier
$result = db_exec("UPDATE ...", [...]);

// 6. Audit
audit_log('action_name', 'resource_type', $meetingId, ['detail' => '...'], $meetingId);

// 7. Reponse
api_ok(['updated' => true]);
```

### Format de reponse standard

Succes :
```json
{"ok": true, "data": {"meetings": [...]}}
```

Erreur :
```json
{"ok": false, "error": "meeting_not_found", "detail": "Seance introuvable."}
```

## Couche securite

### AuthMiddleware
- Verifie le header `X-Api-Key` ou la session PHP
- Resout le role systeme de l'utilisateur
- Expose `AuthMiddleware::getCurrentUser()`, `::getCurrentRole()`, `::getCurrentTenantId()`

### Flux de connexion
1. L'utilisateur saisit sa cle API sur `/login.html`
2. POST vers `/api/v1/auth_login.php` — valide le hash SHA256 en base
3. Session PHP creee avec `$_SESSION['auth_user']`
4. Les appels suivants utilisent le cookie de session (pas besoin de renvoyer la cle)
5. Deconnexion via `/api/v1/auth_logout.php`

### Roles

**Roles systeme** (table `users.role`) — permanents, hierarchiques :
| Role | Niveau | Droits |
|------|--------|--------|
| admin | 100 | Tout (users, policies, config) |
| operator | 80 | Conduite de seance, CRUD membres |
| auditor | 60 | Lecture audit, anomalies |
| viewer | 40 | Lecture archives |

**Roles de seance** (table `meeting_roles`) — par reunion :
- president, assessor, voter

Un utilisateur peut avoir un role systeme (operator) ET un role de seance (president pour la reunion X).

### CSRF
- Active via `CSRF_ENABLED=1` dans .env
- Token verifie pour POST/PUT/PATCH/DELETE
- Desactive pour les endpoints publics (vote par token, heartbeat)

### Rate Limiting
- Active via `RATE_LIMIT_ENABLED=1` dans .env
- Suit les echecs d'auth dans `auth_failures`
- Bloque apres N echecs par IP/fenetre temporelle

## Couche frontend

### Design system CSS
3 fichiers en cascade :
1. `design-system.css` — Tokens (couleurs, tailles, espacements)
2. `ui.css` — Composants (boutons, cartes, badges, formulaires, tableaux, drawers, modales)
3. `app.css` — Layout applicatif, pages specifiques. Importe ui.css via `@import`.

Les pages chargent `app.css` qui importe automatiquement `ui.css`.

### JavaScript
7 fichiers avec roles distincts :
- `utils.js` — Fonctions utilitaires (apiGet, apiPost, formatDate, getMeetingId, setNotif)
- `shell.js` — Framework drawer (navigation, readiness, infos, anomalies). Charge automatiquement `auth-ui.js`.
- `auth-ui.js` — Banniere d'authentification (login/logout, affichage role)
- `meeting-context.js` — Contexte de seance reactif
- `meetings.js` — Page tableau de bord seances
- `vote.js` — Interface de vote tablette
- `pv-print.js` — Mise en page PV pour impression

### Pattern HTMX
Les pages .htmx.html utilisent HTMX pour le rendu dynamique :
- `hx-get` / `hx-post` pour les appels API
- `hx-target` pour l'injection de fragments
- `hx-trigger` pour les evenements (load, revealed, every Ns)
- Les fragments PHP dans `public/fragments/` generent du HTML partiel

## Base de donnees

### Tables principales (35+)

**Domaine metier :**
- `meetings` — Seances avec machine a etats (draft > scheduled > frozen > live > closed > validated > archived)
- `motions` — Resolutions rattachees a une seance
- `ballots` — Bulletins de vote individuels (value: for/against/abstain/nsp)
- `attendances` — Presences (mode: present/remote/proxy/absent/excused)
- `proxies` — Procurations (giver_member_id > receiver_member_id)
- `members` — Participants avec poids de vote (vote_weight)

**Securite et audit :**
- `users` — Utilisateurs systeme (api_key_hash, role)
- `audit_events` — Journal append-only avec chaine SHA256
- `auth_failures` — Echecs d'authentification pour rate limiting

**Politiques :**
- `quorum_policies` — Regles de quorum (seuil, denominateur, mode)
- `vote_policies` — Regles de majorite (seuil, base, traitement abstention)

### Invariants de la DB

1. **Immutabilite post-validation** : Des triggers PostgreSQL empechent toute modification sur les tables `motions`, `ballots`, `attendances` lorsque `meetings.validated_at` est defini.
2. **Chaine de hachage** : Chaque `audit_events` contient le hash de l'evenement precedent. Toute modification casse la chaine.
3. **Multi-tenancy** : Toutes les tables portent un `tenant_id`. La contrainte UNIQUE inclut le tenant.
4. **Timestamps automatiques** : Triggers `updated_at` sur toutes les tables.

### Machine a etats des seances

```
draft → scheduled → frozen → live → closed → validated → archived
                                  ↘ (reset demo)
```

Chaque transition est controlée par role et enregistree dans `meeting_state_transitions`.

## Services metier (app/services/)

| Service | Responsabilite |
|---------|---------------|
| VoteEngine | Calcul des resultats de vote (bulletins + politiques + quorum) |
| QuorumEngine | Calcul du quorum (meeting-wide et par motion) |
| BallotsService | Enregistrement des bulletins, verification eligibilite |
| AttendancesService | Pointage, verification presence, resume |
| ProxiesService | Gestion des procurations |
| VoteTokenService | Generation/validation/consommation des tokens de vote |
| NotificationsService | Notifications temps reel (blocking/warn/info) |
| MeetingReportService | Generation du PV (HTML) |
| OfficialResultsService | Consolidation officielle des resultats |
| MeetingValidator | Verification pre-validation (tous votes clos, pas d'anomalies) |
| SpeechService | File d'attente des intervenants |
| InvitationsService | Tokens d'invitation (email, QR) |
| MembersService | Gestion du registre des membres |
| MailerService | Envoi d'emails |
| AuthService | (deprecated) → AuthMiddleware |
| QuorumService | (deprecated) → QuorumEngine |

## Variables d'environnement (.env)

| Variable | Description | Defaut dev |
|----------|-------------|------------|
| APP_ENV | Environnement (development/production) | development |
| APP_DEBUG | Mode debug (0/1) | 1 |
| APP_SECRET | Secret pour hashing (64 chars min) | dev-secret... |
| DB_DSN | DSN PostgreSQL | pgsql:host=localhost;port=5432;dbname=vote_app |
| DB_USER | Utilisateur DB | vote_app |
| DB_PASS | Mot de passe DB | vote_app_dev_2026 |
| DEFAULT_TENANT_ID | UUID du tenant par defaut | aaaaaaaa-1111-2222-3333-444444444444 |
| APP_AUTH_ENABLED | Activer l'authentification (0/1) | 1 |
| CSRF_ENABLED | Activer la protection CSRF (0/1) | 0 |
| RATE_LIMIT_ENABLED | Activer le rate limiting (0/1) | 1 |
| CORS_ALLOWED_ORIGINS | Origines CORS autorisees | http://localhost:8000 |

## Conventions de developpement

- **PHP** : PSR-12, strict types implicite, prepared statements PDO
- **Nommage API** : `api_ok()`, `api_fail()`, `api_require_role()`, `api_current_tenant_id()`
- **Nommage SQL** : snake_case, tables au pluriel, colonnes explicites
- **Nommage JS** : camelCase, IIFE pour isolation
- **Pas de framework** : Code applicatif direct, pas d'ORM
- **Pas de dependances front** : Vanilla JS + HTMX uniquement
