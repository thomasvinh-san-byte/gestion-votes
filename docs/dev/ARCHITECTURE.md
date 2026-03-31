# Architecture technique : AG-Vote

## Table des matieres

1. [Vue d'ensemble](#vue-densemble)
2. [Arborescence du projet](#arborescence-du-projet)
3. [Couche API](#couche-api)
4. [Couche securite](#couche-securite)
5. [Couche frontend](#couche-frontend)
6. [Base de donnees](#base-de-donnees)
7. [Services metier](#services-metier)
8. [Variables d'environnement](#variables-denvironnement)
9. [Conventions de developpement](#conventions-de-developpement)

---

## Vue d'ensemble

AG-Vote est une application PHP 8.4+ / PostgreSQL 16+ pour la gestion de seances de vote formelles. L'architecture est sans framework, API-first, avec PostgreSQL comme source de verite. Le frontend repose sur du JavaScript vanilla et des Web Components natifs.

---

## Arborescence du projet

```
gestion-votes/
├── public/                     Racine web (Nginx DocumentRoot)
│   ├── api/
│   │   ├── v1/                 142 endpoints REST PHP
│   │   └── bus/                Event bus (publish, stream SSE)
│   ├── assets/
│   │   ├── css/                20 fichiers CSS (design-system, app, pages)
│   │   └── js/                 57 fichiers JS total
│   │       ├── components/     21 fichiers (20 Web Components + index.js)
│   │       ├── core/           5 fichiers (utils, shared, shell, page-components, event-stream)
│   │       ├── pages/          29 fichiers (admin, vote, operator-*, dashboard, wizard, hub, etc.)
│   │       └── services/       1 fichier (meeting-context)
│   ├── partials/               Composants HTML partages (sidebar, topbar)
│   ├── exports/                Templates d'export (PV)
│   ├── errors/                 Pages 403, 404, 500
│   ├── *.htmx.html            18 pages applicatives
│   ├── index.html              Page d'accueil
│   └── login.html              Page de connexion
├── app/                        Code backend (hors webroot)
│   ├── api.php                 Point d'entree API, fonctions canoniques
│   ├── bootstrap.php           Initialisation (DB, .env, constantes)
│   ├── config.php              Configuration applicative
│   ├── auth.php                Auth utilities (aliases retrocompatibilite)
│   ├── Repository/             31 repositories (AbstractRepository + 30 metier) + 4 traits
│   ├── Services/               19 services metier (namespace AgVote\Service)
│   ├── Core/
│   │   ├── Security/           AuthMiddleware, CsrfMiddleware, RateLimiter, Permissions
│   │   └── Validation/         InputValidator, ValidationSchemas
│   └── Templates/              Layout.php + templates email
├── database/
│   ├── schema-master.sql       Schema DDL unifie (40 tables, triggers, index)
│   ├── setup.sh                Script d'initialisation automatique
│   ├── seeds/                  Seeds numerotes (01-08, idempotent)
│   ├── migrations/             21 migrations (001-008 + datees)
│   └── setup_demo_az.sh        Script demo A-Z
├── deploy/                     Configuration Docker (entrypoint, nginx, php-fpm, supervisord)
├── bin/                        Scripts executables
├── config/                     Configuration avancee
├── docs/                       Documentation
├── tests/                      Tests (Unit/, Integration/, e2e/)
├── Dockerfile                  Image Docker (PHP 8.4 + Nginx + supervisord)
├── docker-compose.yml          Orchestration (app + PostgreSQL + Redis)
├── .env.example                Template variables d'environnement
└── composer.json               Dependances PHP
```

---

## Couche API

### Point d'entree unique : app/api.php

Tout endpoint PHP dans public/api/v1/ commence par :
```php
require __DIR__ . '/../../../app/api.php';
```

Ce fichier charge bootstrap.php (DB, .env) puis expose les fonctions canoniques :

**Reponses :**
- `api_ok(array $data, int $code = 200)` : reponse JSON succes `{"ok":true,"data":{...}}`
- `api_fail(string $error, int $code = 400, array $extra = [])` : reponse JSON erreur `{"ok":false,"error":"code"}`

**Authentification :**
- `api_require_role(string|array $roles)` : verifie le role via AuthMiddleware. Bloque avec 403 si non autorise.
- `api_current_tenant_id()` : retourne le tenant_id courant (multi-tenancy).

**Requetes :**
- `api_request(string $method)` : valide la methode HTTP, decode le body JSON.
- `api_get_body()` : retourne le corps de la requete decode.

**Gardes metier :**
- `api_guard_meeting_not_validated(string $meetingId)` : bloque toute modification sur une seance validee (409).
- `api_guard_meeting_exists(string $meetingId)` : verifie l'existence de la seance (404).

**Base de donnees (via bootstrap.php) :**
- `db_select(string $sql, array $params)` : SELECT multiple rows
- `db_select_one(string $sql, array $params)` : SELECT single row
- `db_scalar(string $sql, array $params)` : SELECT single value
- `db_exec(string $sql, array $params)` : INSERT/UPDATE/DELETE
- `audit_log(string $action, string $resource_type, string $resource_id, array $payload, ?string $meetingId)` : enregistrement audit

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

---

## Couche securite

### AuthMiddleware
- Verifie le header `X-Api-Key` ou la session PHP
- Resout le role systeme de l'utilisateur
- Expose `AuthMiddleware::getCurrentUser()`, `::getCurrentRole()`, `::getCurrentTenantId()`

### Flux de connexion
1. L'utilisateur saisit ses identifiants sur `/login.html`
2. POST vers `/api/v1/auth_login.php` : valide le mot de passe (bcrypt) ou le hash SHA256 de la cle API
3. Session PHP creee avec `$_SESSION['auth_user']`
4. Les appels suivants utilisent le cookie de session (pas besoin de renvoyer les identifiants)
5. Deconnexion via `/api/v1/auth_logout.php`

### Roles

**Roles systeme** (table `users.role`), permanents et hierarchiques :
| Role | Niveau | Droits |
|------|--------|--------|
| admin | 100 | Tout (users, policies, config) |
| operator | 80 | Conduite de seance, CRUD membres |
| auditor | 60 | Lecture audit, anomalies |
| viewer | 40 | Lecture archives |

**Roles de seance** (table `meeting_roles`), par reunion :
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

---

## Couche frontend

### Design system CSS
20 fichiers CSS, organises par page/domaine :
- `design-system.css` : tokens (couleurs, tailles, espacements) + composants de base
- `app.css` : layout applicatif global, importe design-system.css via `@import`
- `admin.css`, `operator.css`, `vote.css`, `login.css`, etc. : styles specifiques par page

### Web Components
20 composants reutilisables dans `public/assets/js/components/` :

| Composant | Fichier | Usage |
|-----------|---------|-------|
| `<ag-kpi>` | ag-kpi.js | Cartes KPI (valeur, label, variante, icone) |
| `<ag-badge>` | ag-badge.js | Badges de statut (success, warning, live, draft) |
| `<ag-spinner>` | ag-spinner.js | Indicateurs de chargement |
| `<ag-toast>` | ag-toast.js | Notifications toast avec `AgToast.show()` |
| `<ag-quorum-bar>` | ag-quorum-bar.js | Barres de progression quorum |
| `<ag-vote-button>` | ag-vote-button.js | Boutons de vote (pour/contre/abstention) |
| `<ag-popover>` | ag-popover.js | Popovers contextuels |
| `<ag-searchable-select>` | ag-searchable-select.js | Select avec recherche |
| `<ag-modal>` | ag-modal.js | Modales (confirmation, formulaires) |
| `<ag-confirm>` | ag-confirm.js | Dialogues de confirmation |
| `<ag-breadcrumb>` | ag-breadcrumb.js | Fil d'Ariane |
| `<ag-page-header>` | ag-page-header.js | En-tete de page standardise |
| `<ag-stepper>` | ag-stepper.js | Indicateur d'etapes (wizard) |
| `<ag-pagination>` | ag-pagination.js | Pagination de listes |
| `<ag-donut>` | ag-donut.js | Graphiques en anneau |
| `<ag-mini-bar>` | ag-mini-bar.js | Mini barres de donnees |
| `<ag-tooltip>` | ag-tooltip.js | Infobulles |
| `<ag-scroll-top>` | ag-scroll-top.js | Bouton retour en haut |
| `<ag-time-input>` | ag-time-input.js | Champ de saisie horaire |
| `<ag-tz-picker>` | ag-tz-picker.js | Selecteur de fuseau horaire |

Les composants utilisent le Shadow DOM et emettent des evenements personnalises.

### JavaScript
57 fichiers organises en 4 dossiers :

**core/** (5 fichiers), utilitaires partages :
- `utils.js` : fonctions utilitaires (apiGet, apiPost, formatDate, getMeetingId, setNotif)
- `shell.js` : framework drawer (navigation, readiness, infos, anomalies)
- `shared.js` : fonctions partagees entre pages
- `page-components.js` : composants de page reutilisables
- `event-stream.js` : client SSE pour les mises a jour temps reel

**pages/** (29 fichiers), logique specifique par page :
- `login.js`, `admin.js`, `vote.js`, `operator-tabs.js`, `meetings.js`, `archives.js`, `report.js`, `trust.js`, `validate.js`, `auth-ui.js`, `pv-print.js`, `dashboard.js`, `wizard.js`, `hub.js`, `postsession.js`, `members.js`, `analytics-dashboard.js`, `help-faq.js`, `docs-viewer.js`, `landing.js`, `public.js`, etc.

**services/** (1 fichier), services JS metier :
- `meeting-context.js` : contexte de seance reactif

**components/** (21 fichiers) : voir section Web Components ci-dessus.

### Pattern SPA leger
Les pages `.htmx.html` sont des single-page applications legeres utilisant du JavaScript vanilla :
- Les appels API se font via `fetch()` (encapsules dans `apiGet()`, `apiPost()` de `core/utils.js`)
- Le rendu DOM est gere par JavaScript natif (innerHTML, createElement, etc.)
- Les fragments PHP dans `public/fragments/` generent du HTML partiel (drawers, OOB updates)

---

## Base de donnees

### Tables principales (40)

**Domaine metier :**
- `meetings` : seances avec machine a etats (draft > scheduled > frozen > live > closed > validated > archived)
- `motions` : resolutions rattachees a une seance
- `ballots` : bulletins de vote individuels (value: for/against/abstain/nsp)
- `attendances` : presences (mode: present/remote/proxy/absent/excused)
- `proxies` : procurations (giver_member_id > receiver_member_id)
- `members` : participants avec poids de vote (vote_weight)

**Securite et audit :**
- `users` : utilisateurs systeme (password_hash, api_key_hash, role)
- `audit_events` : journal append-only avec chaine SHA256
- `auth_failures` : echecs d'authentification pour rate limiting

**Politiques :**
- `quorum_policies` : regles de quorum (seuil, denominateur, mode)
- `vote_policies` : regles de majorite (seuil, base, traitement abstention)

### Invariants de la DB

1. **Immutabilite post-validation** : des triggers PostgreSQL empechent toute modification sur les tables `motions`, `ballots`, `attendances` lorsque `meetings.validated_at` est defini.
2. **Chaine de hachage** : chaque `audit_events` contient le hash de l'evenement precedent. Toute modification casse la chaine.
3. **Multi-tenancy** : toutes les tables portent un `tenant_id`. La contrainte UNIQUE inclut le tenant.
4. **Timestamps automatiques** : triggers `updated_at` sur toutes les tables.

### Machine a etats des seances

```
draft > scheduled > frozen > live > closed > validated > archived
                                  \ (reset demo)
```

Chaque transition est controlee par role et enregistree dans `meeting_state_transitions`.

---

## Services metier (app/Services/, 19 fichiers)

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
| MeetingWorkflowService | Machine a etats des seances |
| SpeechService | File d'attente des intervenants |
| MonitoringService | Surveillance systeme et health checks |
| MailerService | Envoi d'emails |
| EmailQueueService | File d'attente d'emails |
| EmailTemplateService | Templates d'email personnalisables |
| ExportService | Exports CSV/XLSX/HTML |
| ImportService | Imports CSV/XLSX (membres, motions, presences) |
| ErrorDictionary | Messages d'erreur localises |

---

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
| CORS_ALLOWED_ORIGINS | Origines CORS autorisees | http://localhost:8080 |

---

## Conventions de developpement

- **PHP** : PSR-12, strict types implicite, prepared statements PDO
- **Nommage API** : `api_ok()`, `api_fail()`, `api_require_role()`, `api_current_tenant_id()`
- **Nommage SQL** : snake_case, tables au pluriel, colonnes explicites
- **Nommage JS** : camelCase, IIFE pour isolation
- **Pas de framework** : code applicatif direct, pas d'ORM
- **Pas de dependances front** : JavaScript vanilla uniquement, Web Components natifs
- **PWA** : manifest.json + sw.js pour installation et mode hors-ligne
