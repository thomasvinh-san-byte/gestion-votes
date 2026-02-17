# Architecture technique — AG-Vote

## Table des matières

1. [Vue d'ensemble](#vue-densemble)
2. [Arborescence du projet](#arborescence-du-projet)
3. [Couche API](#couche-api)
4. [Couche sécurité](#couche-sécurité)
5. [Couche frontend](#couche-frontend)
6. [Base de données](#base-de-données)
7. [Services métier](#services-métier)
8. [Variables d'environnement](#variables-denvironnement)
9. [Conventions de développement](#conventions-de-développement)

---

## Vue d'ensemble

AG-Vote est une application PHP 8.3+ / PostgreSQL 16+ / HTMX pour la gestion de séances de vote formelles. Architecture sans framework, API-first, avec PostgreSQL comme source de vérité.

---

## Arborescence du projet

```
gestion-votes/
├── public/                     Racine web (Nginx DocumentRoot)
│   ├── api/
│   │   ├── v1/                 148 endpoints REST PHP
│   │   └── bus/                Event bus (publish, stream)
│   ├── assets/
│   │   ├── css/                19 fichiers CSS (design-system, app, pages)
│   │   └── js/                 32 fichiers JS total
│   │       ├── components/     10 fichiers (9 Web Components + index.js)
│   │       ├── core/           4 fichiers (utils, shared, shell, page-components)
│   │       ├── pages/          12 fichiers (admin, vote, operator-tabs, login, etc.)
│   │       └── services/       6 fichiers (websocket-client, offline-storage, etc.)
│   ├── partials/               Composants HTML partagés (sidebar, topbar)
│   ├── fragments/              7 fragments PHP (drawers, OOB)
│   ├── exports/                Templates d'export (PV)
│   ├── errors/                 Pages 403, 404, 500
│   ├── *.htmx.html             14 pages applicatives
│   ├── index.html              Page d'accueil
│   └── login.html              Page de connexion
├── app/                        Code backend (hors webroot)
│   ├── api.php                 Point d'entrée API — fonctions canoniques
│   ├── bootstrap.php           Initialisation (DB, .env, constantes)
│   ├── config.php              Configuration applicative
│   ├── auth.php                Auth utilities (aliases rétrocompatibilité)
│   ├── Repository/             27 repositories (AbstractRepository + 26 métier)
│   ├── Services/               22 services métier (namespace AgVote\Service)
│   ├── Core/
│   │   ├── Security/           AuthMiddleware, CsrfMiddleware, RateLimiter, SecurityHeaders, Permissions
│   │   └── Validation/         InputValidator, ValidationSchemas
│   ├── WebSocket/              EventBroadcaster, Server
│   └── Templates/              Layout.php + templates email
├── database/
│   ├── schema-master.sql       Schéma DDL unifié (36 tables, triggers, index)
│   ├── setup.sh                Script d'initialisation automatique
│   ├── seeds/                  Seeds numérotés (01-08, idempotent)
│   ├── migrations/             10 migrations (001-008 + datées)
│   └── setup_demo_az.sh        Script démo A-Z
├── deploy/                     Configuration Docker (entrypoint, nginx, php-fpm, supervisord)
├── bin/                        Scripts exécutables (websocket-server.php)
├── config/                     Configuration avancée
├── docs/                       Documentation
├── tests/                      Tests (Unit/, Integration/, e2e/)
├── Dockerfile                  Image Docker (PHP 8.3 + Nginx + supervisord)
├── docker-compose.yml          Orchestration (app + PostgreSQL)
├── .env.example                Template variables d'environnement
└── composer.json               Dépendances PHP
```

---

## Couche API

### Point d'entrée unique : app/api.php

Tout endpoint PHP dans public/api/v1/ commence par :
```php
require __DIR__ . '/../../../app/api.php';
```

Ce fichier charge bootstrap.php (DB, .env) puis expose les fonctions canoniques :

**Réponses :**
- `api_ok(array $data, int $code = 200)` — Réponse JSON succès `{"ok":true,"data":{...}}`
- `api_fail(string $error, int $code = 400, array $extra = [])` — Réponse JSON erreur `{"ok":false,"error":"code"}`

**Authentification :**
- `api_require_role(string|array $roles)` — Vérifie le rôle via AuthMiddleware. Bloque avec 403 si non autorisé.
- `api_current_tenant_id()` — Retourne le tenant_id courant (multi-tenancy).

**Requêtes :**
- `api_request(string $method)` — Valide la méthode HTTP, décode le body JSON.
- `api_get_body()` — Retourne le corps de la requête décodé.

**Gardes métier :**
- `api_guard_meeting_not_validated(string $meetingId)` — Bloque toute modification sur une séance validée (409).
- `api_guard_meeting_exists(string $meetingId)` — Vérifie l'existence de la séance (404).

**Base de données (via bootstrap.php) :**
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

// 2. Validation méthode
$input = api_request('POST');

// 3. Extraction et validation des paramètres
$meetingId = trim((string)($input['meeting_id'] ?? ''));
if ($meetingId === '') api_fail('missing_meeting_id', 422);

// 4. Gardes métier
api_guard_meeting_exists($meetingId);
api_guard_meeting_not_validated($meetingId);

// 5. Logique métier
$result = db_exec("UPDATE ...", [...]);

// 6. Audit
audit_log('action_name', 'resource_type', $meetingId, ['detail' => '...'], $meetingId);

// 7. Réponse
api_ok(['updated' => true]);
```

### Format de réponse standard

Succès :
```json
{"ok": true, "data": {"meetings": [...]}}
```

Erreur :
```json
{"ok": false, "error": "meeting_not_found", "detail": "Séance introuvable."}
```

---

## Couche sécurité

### AuthMiddleware
- Vérifie le header `X-Api-Key` ou la session PHP
- Résout le rôle système de l'utilisateur
- Expose `AuthMiddleware::getCurrentUser()`, `::getCurrentRole()`, `::getCurrentTenantId()`

### Flux de connexion
1. L'utilisateur saisit ses identifiants sur `/login.html`
2. POST vers `/api/v1/auth_login.php` — valide le mot de passe (bcrypt) ou le hash SHA256 de la clé API
3. Session PHP créée avec `$_SESSION['auth_user']`
4. Les appels suivants utilisent le cookie de session (pas besoin de renvoyer les identifiants)
5. Déconnexion via `/api/v1/auth_logout.php`

### Rôles

**Rôles système** (table `users.role`) — permanents, hiérarchiques :
| Rôle | Niveau | Droits |
|------|--------|--------|
| admin | 100 | Tout (users, policies, config) |
| operator | 80 | Conduite de séance, CRUD membres |
| auditor | 60 | Lecture audit, anomalies |
| viewer | 40 | Lecture archives |

**Rôles de séance** (table `meeting_roles`) — par réunion :
- president, assessor, voter

Un utilisateur peut avoir un rôle système (operator) ET un rôle de séance (president pour la réunion X).

### CSRF
- Activé via `CSRF_ENABLED=1` dans .env
- Token vérifié pour POST/PUT/PATCH/DELETE
- Désactivé pour les endpoints publics (vote par token, heartbeat)

### Rate Limiting
- Activé via `RATE_LIMIT_ENABLED=1` dans .env
- Suit les échecs d'auth dans `auth_failures`
- Bloque après N échecs par IP/fenêtre temporelle

---

## Couche frontend

### Design system CSS
19 fichiers CSS, organisés par page/domaine :
- `design-system.css` — Tokens (couleurs, tailles, espacements) + composants de base
- `app.css` — Layout applicatif global, importe design-system.css via `@import`
- `admin.css`, `operator.css`, `vote.css`, `login.css`, etc. — Styles spécifiques par page

### Web Components
10 composants réutilisables dans `public/assets/js/components/` :

| Composant | Fichier | Usage |
|-----------|---------|-------|
| `<ag-kpi>` | ag-kpi.js | Cartes KPI (valeur, label, variante, icône) |
| `<ag-badge>` | ag-badge.js | Badges de statut (success, warning, live, draft) |
| `<ag-spinner>` | ag-spinner.js | Indicateurs de chargement |
| `<ag-toast>` | ag-toast.js | Notifications toast avec `AgToast.show()` |
| `<ag-quorum-bar>` | ag-quorum-bar.js | Barres de progression quorum |
| `<ag-vote-button>` | ag-vote-button.js | Boutons de vote (pour/contre/abstention) |
| `<ag-popover>` | ag-popover.js | Popovers contextuels |
| `<ag-searchable-select>` | ag-searchable-select.js | Select avec recherche |
| `<ag-offline-indicator>` | ag-offline-indicator.js | Indicateur mode hors-ligne |

Les composants utilisent le Shadow DOM et émettent des événements personnalisés.

### JavaScript
32 fichiers organisés en 4 dossiers :

**core/** — Utilitaires partagés :
- `utils.js` — Fonctions utilitaires (apiGet, apiPost, formatDate, getMeetingId, setNotif)
- `shell.js` — Framework drawer (navigation, readiness, infos, anomalies)
- `shared.js` — Fonctions partagées entre pages
- `page-components.js` — Composants de page réutilisables

**pages/** — Logique spécifique par page :
- `login.js`, `admin.js`, `vote.js`, `operator-tabs.js`, `meetings.js`, `members.js`, `archives.js`, `report.js`, `trust.js`, `validate.js`, `auth-ui.js`, `pv-print.js`

**services/** — Services JS métier :
- `websocket-client.js` — Client WebSocket avec reconnexion
- `offline-storage.js` — Stockage hors-ligne (IndexedDB)
- `conflict-resolver.js` — Résolution de conflits de sync
- `meeting-context.js` — Contexte de séance réactif
- `session-wizard.js` — Assistant de création de séance
- `speaker.js` — Gestion de la file d'intervenants

### Pattern SPA léger
Les pages `.htmx.html` sont des single-page applications légères utilisant du vanilla JS :
- Les appels API se font via `fetch()` (encapsulés dans `apiGet()`, `apiPost()` de `core/utils.js`)
- Le rendu DOM est géré par JavaScript natif (innerHTML, createElement, etc.)
- HTMX est chargé par 2 pages (trust, vote) pour un usage futur, mais les attributs `hx-*` ne sont pas utilisés actuellement
- Les fragments PHP dans `public/fragments/` génèrent du HTML partiel (drawers, OOB updates)

---

## Base de données

### Tables principales (36)

**Domaine métier :**
- `meetings` — Séances avec machine à états (draft > scheduled > frozen > live > closed > validated > archived)
- `motions` — Résolutions rattachées à une séance
- `ballots` — Bulletins de vote individuels (value: for/against/abstain/nsp)
- `attendances` — Présences (mode: present/remote/proxy/absent/excused)
- `proxies` — Procurations (giver_member_id > receiver_member_id)
- `members` — Participants avec poids de vote (vote_weight)

**Sécurité et audit :**
- `users` — Utilisateurs système (password_hash, api_key_hash, role)
- `audit_events` — Journal append-only avec chaîne SHA256
- `auth_failures` — Échecs d'authentification pour rate limiting

**Politiques :**
- `quorum_policies` — Règles de quorum (seuil, dénominateur, mode)
- `vote_policies` — Règles de majorité (seuil, base, traitement abstention)

### Invariants de la DB

1. **Immutabilité post-validation** : Des triggers PostgreSQL empêchent toute modification sur les tables `motions`, `ballots`, `attendances` lorsque `meetings.validated_at` est défini.
2. **Chaîne de hachage** : Chaque `audit_events` contient le hash de l'événement précédent. Toute modification casse la chaîne.
3. **Multi-tenancy** : Toutes les tables portent un `tenant_id`. La contrainte UNIQUE inclut le tenant.
4. **Timestamps automatiques** : Triggers `updated_at` sur toutes les tables.

### Machine à états des séances

```
draft → scheduled → frozen → live → closed → validated → archived
                                  ↘ (reset demo)
```

Chaque transition est contrôlée par rôle et enregistrée dans `meeting_state_transitions`.

---

## Services métier (app/Services/ — namespace AgVote\Service)

| Service | Responsabilité |
|---------|---------------|
| VoteEngine | Calcul des résultats de vote (bulletins + politiques + quorum) |
| QuorumEngine | Calcul du quorum (meeting-wide et par motion) |
| BallotsService | Enregistrement des bulletins, vérification éligibilité |
| AttendancesService | Pointage, vérification présence, résumé |
| ProxiesService | Gestion des procurations |
| VoteTokenService | Génération/validation/consommation des tokens de vote |
| NotificationsService | Notifications temps réel (blocking/warn/info) |
| MeetingReportService | Génération du PV (HTML) |
| MeetingResultsService | Résultats consolidés par séance |
| OfficialResultsService | Consolidation officielle des résultats |
| MeetingValidator | Vérification pré-validation (tous votes clos, pas d'anomalies) |
| MeetingWorkflowService | Machine à états des séances |
| SpeechService | File d'attente des intervenants |
| InvitationsService | Tokens d'invitation (email, QR) |
| MembersService | Gestion du registre des membres |
| MailerService | Envoi d'emails |
| EmailQueueService | File d'attente d'emails |
| EmailTemplateService | Templates d'email personnalisables |
| ExportService | Exports CSV/XLSX/HTML |
| ImportService | Imports CSV/XLSX (membres, motions, présences) |
| UrlSlugService | Génération de slugs pour URLs opaques |
| ErrorDictionary | Messages d'erreur localisés |

---

## Variables d'environnement (.env)

| Variable | Description | Défaut dev |
|----------|-------------|------------|
| APP_ENV | Environnement (development/production) | development |
| APP_DEBUG | Mode debug (0/1) | 1 |
| APP_SECRET | Secret pour hashing (64 chars min) | dev-secret... |
| DB_DSN | DSN PostgreSQL | pgsql:host=localhost;port=5432;dbname=vote_app |
| DB_USER | Utilisateur DB | vote_app |
| DB_PASS | Mot de passe DB | vote_app_dev_2026 |
| DEFAULT_TENANT_ID | UUID du tenant par défaut | aaaaaaaa-1111-2222-3333-444444444444 |
| APP_AUTH_ENABLED | Activer l'authentification (0/1) | 1 |
| CSRF_ENABLED | Activer la protection CSRF (0/1) | 0 |
| RATE_LIMIT_ENABLED | Activer le rate limiting (0/1) | 1 |
| CORS_ALLOWED_ORIGINS | Origines CORS autorisées | http://localhost:8080 |

---

## Conventions de développement

- **PHP** : PSR-12, strict types implicite, prepared statements PDO
- **Nommage API** : `api_ok()`, `api_fail()`, `api_require_role()`, `api_current_tenant_id()`
- **Nommage SQL** : snake_case, tables au pluriel, colonnes explicites
- **Nommage JS** : camelCase, IIFE pour isolation
- **Pas de framework** : Code applicatif direct, pas d'ORM
- **Pas de dépendances front** : Vanilla JS uniquement (HTMX chargé mais non utilisé)
- **PWA** : manifest.json + sw.js pour installation et mode hors-ligne
