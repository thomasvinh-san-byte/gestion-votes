# Analyse Migration Laravel - AG-Vote

> Document de reference pour la decision de migration vers Laravel.
> Date: 2026-02-03

## Table des matieres

1. [Resume executif](#resume-executif)
2. [Etat actuel du codebase](#etat-actuel-du-codebase)
3. [Problemes identifies](#problemes-identifies)
4. [Options de migration](#options-de-migration)
5. [Analyse RBAC](#analyse-rbac)
6. [Plan de migration API-only](#plan-de-migration-api-only)
7. [Estimation des efforts](#estimation-des-efforts)
8. [Recommandations](#recommandations)
9. [Ameliorations immediates (sans Laravel)](#ameliorations-immediates-sans-laravel)

---

## Resume executif

| Critere | Valeur |
|---------|--------|
| **Taille codebase** | ~8,000 lignes PHP applicatif |
| **Effort migration complete** | 160-210 heures (4-6 semaines) |
| **Effort migration API-only** | 80-100 heures (2-3 semaines) |
| **Risque principal** | RBAC custom (840 lignes AuthMiddleware) |
| **Recommandation** | Migration API-only progressive |

---

## Etat actuel du codebase

### Metriques

| Composant | Quantite | Lignes | Notes |
|-----------|----------|--------|-------|
| Repositories | 17 classes | 5,253 | PDO/SQL pur |
| Services | 15 classes | ~4,000 | Logique metier |
| Endpoints API | 124 fichiers | ~6,000 | Standalone .php |
| JavaScript | 35 fichiers | ~4,000 | Vanilla + HTMX |
| HTML/HTMX | 49 pages | ~8,000 | Frontend |
| Tests | 8 fichiers | ~800 | 0.3% couverture |
| **Total applicatif** | — | **~28,000** | Hors vendor/ |

### Architecture actuelle

```
gestion-votes/
├── app/
│   ├── Core/Security/
│   │   ├── AuthMiddleware.php    # 840 lignes - RBAC principal
│   │   ├── CsrfMiddleware.php
│   │   └── RateLimiter.php
│   ├── Repository/               # 17 classes PDO
│   │   ├── AbstractRepository.php
│   │   ├── MeetingRepository.php # 1,004 lignes
│   │   ├── MotionRepository.php  # 832 lignes
│   │   └── ...
│   └── services/                 # 15 classes
│       ├── VoteEngine.php
│       ├── QuorumEngine.php
│       └── ...
├── public/
│   ├── api/v1/                   # 124 endpoints
│   └── *.htmx.html               # 49 pages
└── database/
    └── schema.sql                # PostgreSQL 16+
```

### Stack technique

| Couche | Technologie | Version |
|--------|-------------|---------|
| Backend | PHP | 8.2+ |
| Base de donnees | PostgreSQL | 16+ |
| Frontend | HTMX + Vanilla JS | — |
| Auth | Custom (session + API keys) | — |
| PDF | DomPDF | 2.x |

---

## Problemes identifies

### 1. RBAC fragile (Critique)

**Localisation:** `app/Core/Security/AuthMiddleware.php`

**Symptomes:**
- 840 lignes de code static
- Matrice de permissions dispersee
- Difficile a tester unitairement
- Un bug peut casser toute l'app

**Code actuel:**
```php
private const STATE_TRANSITIONS = [
    'draft'     => ['scheduled' => 'operator', 'frozen' => 'president'],
    'scheduled' => ['frozen' => 'president', 'draft' => 'admin'],
    'frozen'    => ['live' => 'president', 'scheduled' => 'admin'],
    // ...
];

public static function canDo(string $action, ?string $meetingId = null): bool
{
    // 200+ lignes de logique conditionnelle
}
```

**Impact:** Risque eleve de regression lors de modifications.

### 2. Couverture de tests insuffisante (Critique)

| Metrique | Valeur | Cible |
|----------|--------|-------|
| Fichiers de test | 8 | 50+ |
| Couverture | 0.3% | 50%+ |
| Tests auth | 0 | 20+ |
| Tests API | 0 | 40+ |

**Impact:** Regressions non detectees, bugs en production.

### 3. Routing disperse (Moyen)

**Actuel:** 124 fichiers .php standalone
```
public/api/v1/
├── auth_login.php
├── auth_logout.php
├── meetings.php
├── meetings_index.php
├── meetings_update.php
├── motions_open.php
├── motions_close.php
└── ... (117 autres fichiers)
```

**Problemes:**
- Pas de middleware centralise
- Duplication de code (bootstrap, auth check)
- Difficile a documenter

### 4. Validation manuelle (Moyen)

**Actuel:**
```php
$motionId = trim((string)($_GET['motion_id'] ?? ''));
if ($motionId === '' || !api_is_uuid($motionId)) {
    api_fail('missing_motion_id', 422, ['detail' => 'motion_id requis']);
}
```

**Probleme:** Repetitif, inconsistant, pas de messages traduits.

### 5. Logging basique (Faible)

**Actuel:**
```php
error_log('Error in ballots.php: ' . $e->getMessage());
```

**Manque:**
- Logs structures
- Niveaux (info, warning, error)
- Contexte utilisateur
- Rotation des logs

---

## Options de migration

### Option A: Garder le stack actuel + ameliorations

**Effort:** 80-120 heures

| Action | Heures | Priorite |
|--------|--------|----------|
| Refactorer AuthMiddleware en service testable | 16 | P0 |
| Ajouter 40 tests unitaires | 24 | P0 |
| Ajouter Monolog pour logging | 8 | P1 |
| Creer validation wrapper | 12 | P1 |
| Documenter API (OpenAPI) | 16 | P2 |
| **Total** | **76** | |

**Avantages:**
- Zero risque de regression
- Equipe reste productive
- Ameliorations immediates

**Inconvenients:**
- RBAC reste complexe
- Pas de benefice framework
- Tests manuels a maintenir

### Option B: Migration Laravel complete

**Effort:** 160-210 heures

| Tache | Heures | Risque |
|-------|--------|--------|
| Setup + DB migrations | 8 | Faible |
| Eloquent models (17) | 24 | Moyen |
| Controllers + routes | 40 | Moyen |
| RBAC (Policies) | 32 | **Eleve** |
| Services (copie) | 8 | Faible |
| Tests | 24 | Moyen |
| Frontend Blade (optionnel) | 24 | Faible |
| Cutover + docs | 16 | Moyen |
| **Total** | **176** | |

**Avantages:**
- Framework moderne, ecosystem riche
- RBAC propre (Policies)
- Tests facilites
- Meilleure documentation

**Inconvenients:**
- 4-6 semaines de dev
- Risque de bugs au cutover
- Courbe d'apprentissage

### Option C: Migration API-only (Recommandee)

**Effort:** 80-100 heures

| Tache | Heures | Risque |
|-------|--------|--------|
| Setup Laravel + PG | 4 | Faible |
| Auth + RBAC | 20 | Moyen |
| Meetings/Motions/Attendance | 24 | Moyen |
| Vote + Wizard | 16 | Moyen |
| Tests | 16 | Faible |
| Migration progressive | 8 | Faible |
| **Total** | **88** | |

**Avantages:**
- Frontend HTMX inchange
- Rollback facile (v1 reste)
- Migration progressive
- RBAC propre

**Inconvenients:**
- Deux backends temporairement
- Configuration CORS

---

## Analyse RBAC

### Systeme actuel

```
Niveau 1: Roles systeme
├── admin      → Acces total
├── operator   → Gestion seances
├── auditor    → Lecture seule
└── viewer     → Consultation

Niveau 2: Roles par seance (meeting_roles)
├── president  → Transitions, votes
├── assessor   → Validation
└── voter      → Vote uniquement
```

### Matrice de permissions actuelle

| Action | admin | operator | president | voter |
|--------|-------|----------|-----------|-------|
| Creer seance | ✓ | ✓ | — | — |
| Planifier | ✓ | ✓ | — | — |
| Geler | ✓ | — | ✓ | — |
| Ouvrir (live) | ✓ | — | ✓ | — |
| Ouvrir vote | ✓ | ✓ | ✓ | — |
| Voter | — | — | — | ✓ |
| Cloturer | ✓ | — | ✓ | — |
| Valider | ✓ | — | ✓ | — |

### Solution Laravel proposee

```php
// app/Policies/MeetingPolicy.php
class MeetingPolicy
{
    public function transition(User $user, Meeting $meeting, string $to): bool
    {
        $rules = [
            'draft' => [
                'scheduled' => fn($u, $m) => $u->hasRole('operator'),
                'frozen' => fn($u, $m) => $this->isPresident($u, $m),
            ],
            'scheduled' => [
                'frozen' => fn($u, $m) => $this->isPresident($u, $m),
                'draft' => fn($u, $m) => $u->hasRole('admin'),
            ],
            'frozen' => [
                'live' => fn($u, $m) => $this->isPresident($u, $m),
                'scheduled' => fn($u, $m) => $u->hasRole('admin'),
            ],
            'live' => [
                'closed' => fn($u, $m) => $this->isPresident($u, $m),
            ],
            'closed' => [
                'validated' => fn($u, $m) => $this->isPresident($u, $m),
            ],
        ];

        $check = $rules[$meeting->status][$to] ?? null;
        return $check && $check($user, $meeting);
    }

    private function isPresident(User $user, Meeting $meeting): bool
    {
        return MeetingRole::where('meeting_id', $meeting->id)
            ->where('user_id', $user->id)
            ->where('role', 'president')
            ->exists();
    }
}

// Usage dans controller
public function transition(Request $request, Meeting $meeting)
{
    $this->authorize('transition', [$meeting, $request->to_status]);

    $meeting->transitionTo($request->to_status);

    return response()->json(['ok' => true]);
}
```

### Middleware pour roles par seance

```php
// app/Http/Middleware/EnsureMeetingRole.php
class EnsureMeetingRole
{
    public function handle($request, Closure $next, ...$roles)
    {
        $meetingId = $request->route('meeting')
            ?? $request->input('meeting_id');

        if (!$meetingId) {
            return $next($request);
        }

        $userRole = MeetingRole::where('meeting_id', $meetingId)
            ->where('user_id', auth()->id())
            ->value('role');

        if (!in_array($userRole, $roles)) {
            abort(403, 'Role insuffisant pour cette seance');
        }

        return $next($request);
    }
}

// routes/api.php
Route::middleware(['auth', 'meeting.role:president,assessor'])
    ->group(function () {
        Route::post('/meetings/{meeting}/transition', [MeetingController::class, 'transition']);
    });
```

---

## Plan de migration API-only

### Phase 1: Socle (Semaine 1)

```bash
# Creer projet Laravel dans sous-dossier
composer create-project laravel/laravel laravel-api
cd laravel-api

# Configurer PostgreSQL
# .env
DB_CONNECTION=pgsql
DB_HOST=127.0.0.1
DB_PORT=5432
DB_DATABASE=agvote
DB_USERNAME=agvote
DB_PASSWORD=xxx
```

**Fichiers a creer:**

```
laravel-api/
├── app/
│   ├── Models/
│   │   ├── User.php
│   │   ├── Meeting.php
│   │   ├── Motion.php
│   │   ├── Member.php
│   │   ├── Attendance.php
│   │   ├── Ballot.php
│   │   └── MeetingRole.php
│   ├── Policies/
│   │   ├── MeetingPolicy.php
│   │   └── MotionPolicy.php
│   └── Http/
│       ├── Controllers/Api/
│       │   └── AuthController.php
│       └── Middleware/
│           └── EnsureMeetingRole.php
├── routes/
│   └── api.php
└── config/
    └── auth.php  # Session guard
```

### Phase 2: Auth + RBAC (Semaine 1-2)

**Endpoints a migrer:**
- `POST /api/v2/auth/login`
- `POST /api/v2/auth/logout`
- `GET /api/v2/auth/csrf`
- `GET /api/v2/whoami`

**Tests a ecrire:**
```php
public function test_login_with_valid_credentials()
{
    $user = User::factory()->create(['password' => bcrypt('test')]);

    $response = $this->postJson('/api/v2/auth/login', [
        'email' => $user->email,
        'password' => 'test',
    ]);

    $response->assertOk()
        ->assertJsonStructure(['ok', 'data' => ['user']]);
}

public function test_operator_cannot_freeze_meeting()
{
    $operator = User::factory()->create(['role' => 'operator']);
    $meeting = Meeting::factory()->scheduled()->create();

    $response = $this->actingAs($operator)
        ->postJson("/api/v2/meetings/{$meeting->id}/transition", [
            'to_status' => 'frozen'
        ]);

    $response->assertForbidden();
}
```

### Phase 3: Core (Semaine 2-3)

**Endpoints prioritaires (80% usage):**

| Endpoint actuel | Endpoint Laravel | Priorite |
|-----------------|------------------|----------|
| meetings.php | GET /api/v2/meetings/{id} | P0 |
| meetings_index.php | GET /api/v2/meetings | P0 |
| meeting_transition.php | POST /api/v2/meetings/{id}/transition | P0 |
| attendances.php | GET /api/v2/meetings/{id}/attendances | P0 |
| attendances_upsert.php | POST /api/v2/attendances | P0 |
| motions_for_meeting.php | GET /api/v2/meetings/{id}/motions | P0 |
| motions_open.php | POST /api/v2/motions/{id}/open | P1 |
| motions_close.php | POST /api/v2/motions/{id}/close | P1 |
| manual_vote.php | POST /api/v2/ballots | P1 |
| wizard_status.php | GET /api/v2/meetings/{id}/wizard | P1 |

### Phase 4: Cutover (Semaine 3-4)

**Migration frontend progressive:**

```javascript
// public/assets/js/utils.js

// Ajouter switch de version API
const API_MIGRATED = {
  '/api/v1/meetings.php': '/api/v2/meetings',
  '/api/v1/meetings_index.php': '/api/v2/meetings',
  '/api/v1/attendances.php': '/api/v2/attendances',
  // ...
};

async function api(endpoint, data = null) {
  // Verifier si endpoint migre
  let url = endpoint;
  for (const [old, nouveau] of Object.entries(API_MIGRATED)) {
    if (endpoint.startsWith(old)) {
      url = endpoint.replace(old, nouveau);
      break;
    }
  }

  // Reste inchange...
}
```

---

## Estimation des efforts

### Comparaison des options

| Option | Heures | Semaines | Risque | ROI 12 mois |
|--------|--------|----------|--------|-------------|
| A: Ameliorations | 80 | 2 | Faible | Moyen |
| B: Migration complete | 180 | 5 | Eleve | Eleve |
| C: API-only | 90 | 2.5 | Moyen | Eleve |

### Detail Option C (Recommandee)

| Tache | Junior | Senior | Total |
|-------|--------|--------|-------|
| Setup projet | 4h | 2h | 6h |
| Models Eloquent | 16h | 8h | 24h |
| Auth + RBAC | 8h | 16h | 24h |
| Controllers | 16h | 8h | 24h |
| Tests | 8h | 8h | 16h |
| Documentation | 4h | 2h | 6h |
| **Total** | **56h** | **44h** | **100h** |

---

## Recommandations

### Court terme (0-4 semaines)

1. **Lancer Option C** - Migration API-only Laravel
2. **Priorite:** Auth + RBAC + Meetings
3. **Garder v1 fonctionnel** pendant transition

### Moyen terme (1-3 mois)

1. Migrer tous les endpoints vers /api/v2/
2. Atteindre 50% couverture tests
3. Deprecier /api/v1/ (warnings logs)

### Long terme (3-6 mois)

1. Supprimer /api/v1/
2. Migrer frontend vers Blade (optionnel)
3. Ajouter features: notifications, exports async

---

## Ameliorations immediates (sans Laravel)

Si la migration est reportee, voici les ameliorations prioritaires:

### 1. Refactorer AuthMiddleware

**Avant:**
```php
class AuthMiddleware {
    public static function canDo(...) { /* 200 lignes */ }
}
```

**Apres:**
```php
// app/Core/Security/PermissionChecker.php
class PermissionChecker {
    private array $rules;

    public function __construct() {
        $this->rules = require __DIR__ . '/permissions.php';
    }

    public function check(User $user, string $action, ?Meeting $meeting = null): bool
    {
        $rule = $this->rules[$action] ?? null;
        if (!$rule) return false;
        return $rule($user, $meeting);
    }
}

// app/Core/Security/permissions.php
return [
    'meeting.create' => fn($u, $m) => in_array($u->role, ['admin', 'operator']),
    'meeting.freeze' => fn($u, $m) => $u->role === 'admin' || $this->isPresident($u, $m),
    // ...
];
```

**Effort:** 16 heures

### 2. Ajouter logging structure

```php
// app/Core/Logger.php
class Logger {
    private static $instance;

    public static function info(string $message, array $context = []): void
    {
        self::log('INFO', $message, $context);
    }

    public static function error(string $message, array $context = []): void
    {
        self::log('ERROR', $message, $context);
    }

    private static function log(string $level, string $message, array $context): void
    {
        $entry = [
            'timestamp' => date('c'),
            'level' => $level,
            'message' => $message,
            'context' => $context,
            'user_id' => $_SESSION['user_id'] ?? null,
            'request_id' => $_SERVER['HTTP_X_REQUEST_ID'] ?? uniqid(),
        ];

        error_log(json_encode($entry));
    }
}
```

**Effort:** 8 heures

### 3. Ajouter validation centralisee

```php
// app/Core/Validator.php
class Validator {
    public static function uuid(string $value, string $field): void
    {
        if (!preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i', $value)) {
            api_fail('invalid_' . $field, 422, ['detail' => "$field doit etre un UUID valide"]);
        }
    }

    public static function required(mixed $value, string $field): void
    {
        if ($value === null || $value === '') {
            api_fail('missing_' . $field, 422, ['detail' => "$field est requis"]);
        }
    }

    public static function inArray(mixed $value, array $allowed, string $field): void
    {
        if (!in_array($value, $allowed)) {
            api_fail('invalid_' . $field, 422, [
                'detail' => "$field doit etre parmi: " . implode(', ', $allowed)
            ]);
        }
    }
}

// Usage
Validator::required($motionId, 'motion_id');
Validator::uuid($motionId, 'motion_id');
```

**Effort:** 12 heures

### 4. Ajouter tests critiques

**Fichiers prioritaires:**

```
tests/
├── Unit/
│   ├── PermissionCheckerTest.php
│   ├── VoteEngineTest.php
│   └── QuorumEngineTest.php
└── Integration/
    ├── AuthTest.php
    ├── MeetingTransitionTest.php
    └── VoteFlowTest.php
```

**Effort:** 24 heures

---

## Conclusion

| Question | Reponse |
|----------|---------|
| Faut-il migrer? | **Oui**, mais progressivement |
| Quelle option? | **C: API-only Laravel** |
| Quand commencer? | Des que possible |
| Risque principal? | RBAC - prevoir 20h+ |
| ROI attendu | Positif apres 3 mois |

**Prochaine etape:** Creer le projet Laravel et migrer Auth + RBAC en premier.
