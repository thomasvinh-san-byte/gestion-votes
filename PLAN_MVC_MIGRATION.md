# Plan de migration full MVC — gestion-votes

## Etat des lieux (post-couacs)

| Couche        | Fichiers | Etat                                              |
|---------------|----------|---------------------------------------------------|
| Controllers   | 20       | OK — delegate aux Services, AbstractController gere les exceptions |
| Services      | 18       | OK (tenant mandatory apres fix). 2 reliquats `$GLOBALS` dans MeetingReportService et QuorumEngine |
| Repositories  | 28       | OK — data access pur, phantom aliases supprimes (470 methodes) |
| Vues          | 0 formel | MANQUANT — pas de couche View, HTML inline (vote.php, doc.php), templates email isoles |
| Routeur       | 0        | MANQUANT — 142 fichiers dans `public/api/v1/` (103 stubs + 39 handlers lourds) et 2 handlers directs |
| Middleware    | ad-hoc   | PARTIEL — auth/csrf/rate-limit appeles manuellement dans chaque endpoint |
| Bootstrap     | 1 monolithe (341L) | PARTIEL — config + DB + securite + helpers globaux dans un seul fichier |
| API helpers   | 1 monolithe (235L) | ~25 fonctions globales : `api_ok`, `api_fail`, `api_require_role`, `db()`, `audit_log`, etc. |
| `$GLOBALS`    | 6 restants | `APP_TENANT_ID` x4 (bootstrap, MeetingReportService, QuorumEngine, EmailController) + `__ag_vote_raw_body` x2 (api.php, CsrfMiddleware) |
| DB helpers depreces | 5 appels | `db_select_one` dans api.php (x2), aliases depreces dans bootstrap (x3) |
| Frontend      | 16 HTML/HTMX | SPA decouple (appels fetch vers API), pas de templating serveur |

### Inventaire detaille public/api/v1/ (142 fichiers)

**39 fichiers lourds (>40 lignes) — logique inline, pas de controller** :

| Fichier | Lignes | Sujet |
|---------|--------|-------|
| member_group_assignments.php | 290 | CRUD groupes/membres |
| motions_import_csv.php | 258 | Import CSV motions |
| export_templates.php | 250 | CRUD templates export |
| email_templates.php | 249 | CRUD templates email |
| motions_import_xlsx.php | 218 | Import XLSX motions |
| member_groups.php | 216 | CRUD groupes |
| motions_open.php | 156 | Ouverture motion |
| reminders.php | 155 | Gestion rappels |
| auth_login.php | 150 | Authentification |
| meeting_attachments.php | 144 | Pieces jointes |
| dashboard.php | 137 | Dashboard operateur |
| motions_close.php | 118 | Fermeture motion |
| members.php | 116 | CRUD membres |
| projector_state.php | 105 | Etat projecteur |
| degraded_tally.php | 92 | Decompte degrade |
| vote_tokens_generate.php | 86 | Generation tokens vote |
| meetings.php | 85 | CRUD seances |
| email_redirect.php | 81 | Redirect tracking |
| wizard_status.php | 79 | Etat wizard |
| quorum_card.php | 76 | Carte quorum |
| meeting_quorum_settings.php | 65 | Config quorum |
| dev_seed_attendances.php | 64 | Seed presences |
| agendas.php | 62 | Ordre du jour |
| meeting_vote_settings.php | 59 | Config vote |
| whoami.php | 55 | User courant |
| dev_seed_members.php | 53 | Seed membres |
| doc_index.php | 50 | Index doc |
| auth_logout.php | 49 | Deconnexion |
| meeting_late_rules.php | 47 | Regles retard |
| quorum_status.php | 45 | Status quorum |
| + 9 autres (40-45 lignes) | | |

**103 fichiers stubs (3-5 lignes)** — delegation pure vers un Controller existant.

### 20 controllers existants (6 853 lignes, 98 methodes)

| Controller | Meth. | Lignes | Responsabilite |
|-----------|-------|--------|----------------|
| ImportController | 6 | 857 | Import CSV/XLSX |
| MeetingReportsController | 5 | 713 | Rapports, exports |
| MeetingWorkflowController | 6 | 537 | Transitions etats seance |
| OperatorController | 3 | 521 | Actions operateur |
| AdminController | 5 | 521 | Admin users/audit |
| AnalyticsController | 2 | 433 | Analytics |
| MotionsController | 7 | 409 | CRUD motions |
| MeetingsController | 8 | 378 | CRUD seances |
| BallotsController | 7 | 338 | Bulletins |
| AuditController | 5 | 320 | Audit trail |
| TrustController | 2 | 284 | Anomalies |
| ExportController | 9 | 246 | Exports CSV/XLSX/PDF |
| EmailController | 3 | 219 | Emails |
| DevicesController | 5 | 203 | Devices tablette |
| SpeechController | 9 | 198 | File de parole |
| InvitationsController | 4 | 186 | Invitations |
| AttendancesController | 4 | 180 | Presences |
| PoliciesController | 4 | 150 | Politiques vote/quorum |
| ProxiesController | 3 | 131 | Procurations |
| AbstractController | 1 | 29 | Base (handle + try/catch) |

---

## Architecture cible

```
index.php (front controller unique)
    |
    v
Router (route table declarative)
    |
    v
MiddlewarePipeline (auth -> csrf -> rate-limit -> tenant)
    |
    v
Controller::method()     ← requete + reponse
    |
    v
Service                  ← logique metier
    |
    v
Repository               ← data access PDO
    |
    v
View (JsonView | TemplateView)  ← serialisation reponse
```

---

## Phases

### Phase 0 — Extraire les 39 handlers lourds vers des Controllers

**But** : Avant de pouvoir supprimer les fichiers `public/api/v1/`, les 39 fichiers
qui contiennent de la logique inline (>40 lignes) doivent etre refactores en controllers.

**Principe** : Chaque handler lourd est deplace dans un Controller existant ou nouveau.
Le fichier API est remplace par un stub de 4 lignes delegant au controller.

**Groupes de migration** (par controller cible) :

| Nouveau/existant controller | Fichiers sources | Total lignes |
|-----------------------------|-----------------|--------------|
| AuthController (nouveau) | auth_login.php (150), auth_logout.php (49), whoami.php (55) | 254 |
| MemberGroupsController (nouveau) | member_group_assignments.php (290), member_groups.php (216) | 506 |
| MembersController (nouveau) | members.php (116) | 116 |
| DashboardController (nouveau) | dashboard.php (137), wizard_status.php (79) | 216 |
| QuorumController (nouveau) | quorum_card.php (76), quorum_status.php (45), meeting_quorum_settings.php (65) | 186 |
| ProjectorController (nouveau) | projector_state.php (105) | 105 |
| AgendaController (nouveau) | agendas.php (62), meeting_late_rules.php (47) | 109 |
| ReminderController (nouveau) | reminders.php (155) | 155 |
| MeetingAttachmentController (nouveau) | meeting_attachments.php (144) | 144 |
| DocController (nouveau) | doc_index.php (50) | 50 |
| DevSeedController (nouveau, dev only) | dev_seed_members.php (53), dev_seed_attendances.php (64) | 117 |
| MotionsController (existant) | motions_open.php (156), motions_close.php (118), degraded_tally.php (92) | 366 |
| ImportController (existant) | motions_import_csv.php (258), motions_import_xlsx.php (218) | 476 |
| ExportController (existant) | export_templates.php (250) | 250 |
| EmailController (existant) | email_templates.php (249), email_redirect.php (81) | 330 |
| MeetingsController (existant) | meetings.php (85), meeting_vote_settings.php (59) | 144 |
| VoteTokenController (nouveau) | vote_tokens_generate.php (86) | 86 |

**Methode pour chaque fichier** :
1. Creer la methode dans le controller cible (copier la logique)
2. Remplacer le fichier API par un stub de 4 lignes : `(new Controller())->handle('method')`
3. Tester que l'endpoint repond identiquement

**Regle** : Aucune URL ne change. Les fichiers restent en place mais deviennent des stubs.

**Estimation** : ~3 500 lignes deplacees (pas reecrites), 10-12 nouveaux controllers.

---

### Phase 1 — Routeur central (eliminer 142 stubs)

**But** : Remplacer les 142 fichiers `public/api/v1/*.php` (tous devenus des stubs
de 4 lignes apres Phase 0) par une table de routes unique.

**Prerequis** : Phase 0 terminee (plus aucune logique inline dans les stubs).

**Fichiers a creer** :
- `app/Core/Router.php` — routeur simple exact-match (pas besoin de regex pour l'instant)
- `app/routes.php` — table declarative des routes
- `public/index.php` — front controller unique (require bootstrap → api → router → dispatch)

**Fichiers a supprimer** :
- Les 142 fichiers `public/api/v1/*.php` (devenus stubs identiques)

**Migration** :
1. Creer `Router` avec methode `dispatch(string $method, string $uri)`
2. Generer `routes.php` a partir des stubs existants (script automatise)
3. Creer `public/index.php` qui charge bootstrap → api → router → dispatch
4. Configurer `.htaccess` / nginx pour rediriger vers `index.php`
5. Conserver temporairement les stubs avec un fallback interne
6. Tester chaque endpoint, puis supprimer les stubs

**Regle** : Les URLs ne changent pas (`/api/v1/ballots` fonctionne toujours).

**Risque** : Configuration serveur (.htaccess). Fournir config Apache ET nginx.

**Estimation** : ~300 lignes de code nouveau (Router + routes), ~600 lignes supprimees (stubs).

---

### Phase 2 — Pipeline de middlewares

**But** : Extraire auth, CSRF, rate-limiting du code inline vers des middlewares chainables.

**Fichiers a creer** :
- `app/Core/MiddlewarePipeline.php` — execute une stack de middlewares en sequence
- `app/Core/Middleware/AuthMiddleware.php` — wrapper autour de l'existant `Security/AuthMiddleware`
- `app/Core/Middleware/CsrfMiddleware.php` — wrapper autour de `Security/CsrfMiddleware`
- `app/Core/Middleware/RateLimitMiddleware.php` — wrapper
- `app/Core/Middleware/TenantMiddleware.php` — injecte `$tenantId` dans le contexte requete

**Fichiers a modifier** :
- `app/routes.php` — chaque route declare ses middlewares
- Tous les controllers — supprimer les appels manuels a `api_require_role()`, `api_rate_limit()`

**Migration** :
1. Creer `MiddlewarePipeline` avec pattern `$next($request)`
2. Wrapper les classes Security existantes
3. Ajouter `TenantMiddleware` qui resolve le tenant et le passe au contexte
4. Declarer les middlewares par route dans `routes.php`
5. Supprimer les appels inline dans les 20 controllers
6. Eliminer les 3 derniers `$GLOBALS['APP_TENANT_ID']`

**Risque** : Certains controllers ont des roles differents par methode. Le middleware doit supporter la granularite par action.

---

### Phase 3 — Objet Request/Response

**But** : Remplacer les fonctions globales (`api_ok`, `api_fail`, `api_request`) par des objets.

**Fichiers a creer** :
- `app/Core/Http/Request.php` — encapsule `$_GET`, `$_POST`, `$_SERVER`, body JSON
- `app/Core/Http/Response.php` — encapsule status code, headers, body
- `app/Core/Http/JsonResponse.php` — extends Response pour JSON

**Fichiers a modifier** :
- `AbstractController.php` — injecte Request, retourne Response
- Tous les 20 controllers — remplacer `api_ok()` → `return JsonResponse::ok(...)`, `api_fail()` → `throw ApiException`
- `app/api.php` — supprimer les fonctions globales (garder en deprecated temporairement)

**Migration incrementale** :
1. Creer Request/Response
2. Modifier `AbstractController::handle()` pour passer Request et intercepter Response
3. Migrer les controllers un par un (plus petits d'abord : Proxies, Policies, Speech)
4. Deprecer puis supprimer les fonctions globales

**Dependance** : Phase 2 terminee (middlewares recoivent Request).

---

### Phase 4 — Couche View formelle

**But** : Centraliser la serialisation des reponses et extraire le HTML inline.

**Fichiers a creer** :
- `app/View/JsonView.php` — envelope `{ok: true, data: ...}` standard
- `app/View/HtmlView.php` — rendu HTML simple (pour vote.php, doc.php)
- `app/Templates/vote_form.php` — template extrait de vote.php
- `app/Templates/doc_page.php` — template extrait de doc.php

**Fichiers a modifier** :
- `public/vote.php` → `app/Controller/VotePublicController.php` (nouveau controller)
- `public/doc.php` → `app/Controller/DocController.php` (nouveau controller)

**Migration** :
1. Creer VotePublicController avec 3 actions : show form, submit, confirmation
2. Extraire le HTML de vote.php (lignes 84-182) vers des templates
3. Creer DocController avec 2 actions : index, show
4. Extraire le HTML de doc.php (lignes 131-268) vers un template
5. Les routes sont ajoutees au routeur (Phase 1)
6. Supprimer `public/vote.php` et `public/doc.php`

**Risque** : vote.php est accessible publiquement par token (pas d'auth). S'assurer que la route publique reste fonctionnelle.

---

### Phase 5 — Decomposition du bootstrap

**But** : Eclater `bootstrap.php` (342L) en providers focuses.

**Fichiers a creer** :
- `app/Core/Providers/EnvProvider.php` — charge .env
- `app/Core/Providers/DatabaseProvider.php` — PDO singleton
- `app/Core/Providers/SecurityProvider.php` — init auth, CSRF, rate limiter
- `app/Core/Providers/CorsProvider.php` — headers CORS
- `app/Core/Application.php` — orchestre les providers + boot

**Fichiers a supprimer** :
- `app/bootstrap.php` (remplace par Application::boot())
- Helpers globaux `db()`, `db_select_one()`, etc.
- `app/api.php` (absorbe dans middlewares + Request/Response)

**Migration** :
1. Creer Application avec methode `boot()` qui appelle les providers dans l'ordre
2. Migrer les 5 derniers appels aux db helpers depreces vers des Repositories
3. Remplacer `db()` global par injection du PDO dans les Repositories
4. Supprimer les fonctions `db_select_one`, `db_one`, `db_all`, `db_exec`, `db_scalar`
5. Supprimer les fonctions `audit_log`, `api_uuid4`, `ws_auth_token` → classes dedicees

**Dependance** : Phases 1-3 terminees.

---

### Phase 6 — Nettoyage final et qualite

**But** : Eliminer toutes les dettes techniques restantes.

**Actions** :
1. Supprimer les 2 derniers `$GLOBALS` (MeetingReportService, QuorumEngine) — tenant passe en parametre
2. Supprimer `$GLOBALS['APP_TENANT_ID']` de bootstrap.php
3. Supprimer `EmailController` usage de `$GLOBALS`
4. Supprimer le code mort dans sw.js : `queueOfflineAction()` qui ne persiste rien
5. Ajouter un `phpstan` (niveau 5+) dans CI pour prevenir les regressions de typage
6. Ajouter un test d'integration pour chaque route (smoke test)

---

## Ordre de priorite et dependances

```
Phase 0 (Handlers → Controllers) <- independant, peut commencer immediatement
    |
Phase 1 (Routeur)                <- necessite Phase 0
    |
Phase 2 (Middlewares)            <- necessite Phase 1
    |
Phase 3 (Request/Response)      <- necessite Phase 2
    |
    +-- Phase 4 (Views)          <- necessite Phase 3
    |
    +-- Phase 5 (Bootstrap)      <- necessite Phase 3
    |
Phase 6 (Nettoyage)             <- apres tout
```

## Metriques de succes

| Critere                       | Avant    | Apres    |
|-------------------------------|----------|----------|
| Fichiers API avec logique inline | 39+2  | 0        |
| Fichiers stubs API            | 142      | 0        |
| Controllers                   | 20       | ~32      |
| Fonctions globales            | ~25      | 0        |
| `$GLOBALS` usages             | 6        | 0        |
| DB helpers depreces           | 5 appels | 0        |
| Handlers directs (vote/doc)   | 2        | 0        |
| Middlewares ad-hoc inline     | ~60 appels| 0       |
| Fichiers routes.php           | 0        | 1        |
| Front controllers             | 144      | 1        |
