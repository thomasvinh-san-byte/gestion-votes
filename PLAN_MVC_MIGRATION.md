# Plan de migration full MVC — gestion-votes

## Etat des lieux (post-couacs)

| Couche        | Fichiers | Etat                                              |
|---------------|----------|---------------------------------------------------|
| Controllers   | 20       | OK — delegate aux Services, AbstractController gere les exceptions |
| Services      | 18+      | OK (tenant mandatory apres fix). 2 reliquats `$GLOBALS` dans MeetingReportService et QuorumEngine |
| Repositories  | 25+      | OK — data access pur, phantom aliases supprimes    |
| Vues          | 0 formel | MANQUANT — pas de couche View, HTML inline (vote.php, doc.php), templates email isoles |
| Routeur       | 0        | MANQUANT — 142 stubs PHP dans `public/api/v1/` et 2 handlers directs |
| Middleware    | ad-hoc   | PARTIEL — auth/csrf/rate-limit appeles manuellement dans chaque endpoint |
| Bootstrap     | 1 monolithe (342L) | PARTIEL — config + DB + securite + helpers globaux dans un seul fichier |
| Fonctions globales | ~25  | EXCESSIF — `api_ok`, `api_fail`, `api_require_role`, `db()`, `audit_log`, etc. |
| `$GLOBALS`    | 4 restants | A eliminer (MeetingReportService, QuorumEngine, EmailController, bootstrap) |
| DB helpers depreces | 5 appels | `db_select_one`, `db_all`, etc. dans api.php et bootstrap |

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

### Phase 1 — Routeur central (eliminer 142 stubs)

**But** : Remplacer les 142 fichiers `public/api/v1/*.php` (3-4 lignes chacun) par une table de routes unique.

**Fichiers a creer** :
- `app/Core/Router.php` — routeur simple pattern-matching (regex ou exact-match)
- `app/routes.php` — table declarative des routes
- `public/index.php` — front controller unique (require bootstrap + api, dispatch)

**Fichiers a supprimer** :
- Les 142 fichiers `public/api/v1/*.php`

**Migration** :
1. Creer `Router` avec methode `dispatch(string $method, string $uri)`
2. Generer `routes.php` a partir des stubs existants (script de migration)
3. Creer `public/index.php` qui charge bootstrap → api → router → dispatch
4. Configurer `.htaccess` / nginx pour rediriger vers `index.php`
5. Conserver temporairement les stubs avec un redirect interne
6. Tester, puis supprimer les stubs

**Regle** : Les URLs ne changent pas (`/api/v1/ballots` fonctionne toujours).

**Risque** : Configuration serveur (.htaccess). Fournir config Apache ET nginx.

**Estimation** : ~250 lignes de code nouveau, ~500 lignes supprimees.

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
Phase 1 (Routeur)          <- independant, peut commencer immediatement
    |
Phase 2 (Middlewares)      <- necessite Phase 1
    |
Phase 3 (Request/Response) <- necessite Phase 2
    |
    +-- Phase 4 (Views)    <- necessite Phase 3
    |
    +-- Phase 5 (Bootstrap)  <- necessite Phase 3
    |
Phase 6 (Nettoyage)       <- apres tout
```

## Metriques de succes

| Critere                       | Avant    | Apres    |
|-------------------------------|----------|----------|
| Fichiers stubs API            | 142      | 0        |
| Fonctions globales            | ~25      | 0        |
| `$GLOBALS` usages             | 4        | 0        |
| DB helpers depreces           | 5 appels | 0        |
| Handlers directs (vote/doc)   | 2        | 0        |
| Middlewares ad-hoc inline     | ~60 appels| 0       |
| Fichiers routes.php           | 0        | 1        |
| Front controllers             | 144      | 1        |
