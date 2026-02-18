# Plan de mise en oeuvre — Durcissement API & qualité

Suite au refactoring de la console opérateur (terminé), ce plan couvre le
durcissement sécurité, la validation centralisée, et la qualité générale du backend.

**Statut : EN COURS**

---

## Phase A — Sécurité des routes API (priorité haute)

### A.1 Ajouter `api_require_role()` aux 4 routes non protégées
| Route | Rôle(s) requis | Justification |
|-------|---------------|---------------|
| `quorum_policies.php` | `operator` | Lecture des politiques de quorum |
| `vote_policies.php` | `operator` | Lecture des politiques de vote |
| `meeting_status.php` | `operator` | Statut détaillé + compteurs de la séance |
| `quorum_status.php` | `operator` | Statut du quorum (données sensibles) |

Pattern à suivre : `api_require_role('operator');` en top-level, avant tout `try {}`.

### A.2 Corriger le RBAC hardcodé dans `meeting_status.php`
- Remplacer `'can_current_user_validate' => true` (ligne 63) par un vrai check.
- Utiliser `api_current_user_role()` pour vérifier si le rôle est `president` ou `admin`.

### A.3 Supprimer la fuite de données debug dans `attendances.php`
- Retirer le champ `'debug' => $debug` de la réponse `api_ok()` (ligne 50).
- Supprimer le bloc de construction `$debug` (lignes 33-45).

### A.4 Séparer `.env` de `.env.example`
- `.env.example` : valeurs par défaut sûres (`APP_AUTH_ENABLED=1`, `CSRF_ENABLED=1`, `RATE_LIMIT_ENABLED=1`).
- `.env` : déjà dans `.gitignore`, supprimer le fichier tracké du dépôt avec `git rm --cached`.
- Ajouter un commentaire dans `.env.example` : `# Copier en .env et adapter les valeurs`.

---

## Phase B — InputValidator dans les routes principales

### B.1 Routes cibles (6 routes à haute fréquence)
| Route | Schéma existant | Méthodes HTTP |
|-------|----------------|---------------|
| `meetings.php` | `ValidationSchemas::meeting()` | POST, PUT |
| `members.php` | `ValidationSchemas::member()` | POST, PUT/PATCH |
| `motions.php` | `ValidationSchemas::motion()` | POST |
| `agendas.php` | — (créer `ValidationSchemas::agenda()`) | POST |
| `attendances.php` | `ValidationSchemas::attendance()` | GET (query params) |
| `admin_quorum_policies.php` | `ValidationSchemas::quorumPolicy()` | POST (create/update) |
| `admin_vote_policies.php` | `ValidationSchemas::votePolicy()` | POST (create/update) |

### B.2 Harmoniser les limites entre schémas et routes
| Champ | Route actuelle | Schéma | Décision |
|-------|---------------|--------|----------|
| `meetings.title` | max 200 | max 255 | → 255 (schéma gagne) |
| `motions.title` | max 80 | max 500 | → 500 (schéma gagne) |
| `agendas.title` | max 40 | — | → créer schéma avec max 100 |

### B.3 Pattern de migration par route
```php
// AVANT (ad-hoc) :
$title = trim($input['title'] ?? '');
if ($title === '') api_fail('missing_title', 422, [...]);
if (mb_strlen($title) > 200) api_fail('title_too_long', 422, [...]);

// APRÈS (InputValidator) :
$result = ValidationSchemas::meeting()->validate($input);
$result->failIfInvalid();
$title = $result->get('title');
```

### B.4 Créer `ValidationSchemas::agenda()`
```php
public static function agenda(): InputValidator {
    return InputValidator::schema()
        ->uuid('meeting_id')->required()
        ->string('title')->required()->minLength(1)->maxLength(100)
        ->integer('position')->optional()->min(0);
}
```

---

## Phase C — Try/catch et gestion d'erreurs (priorité moyenne)

### C.1 Ajouter try/catch aux routes sans protection
| Route | Lignes à wraper |
|-------|----------------|
| `dashboard.php` | Tout le corps après `api_require_role()` |
| `admin_quorum_policies.php` | Tout le corps après `api_require_role()` |
| `admin_vote_policies.php` | Tout le corps après `api_require_role()` |
| `invitations_schedule.php` | Tout le corps après `api_require_role()` |

Pattern :
```php
try {
    // corps existant
} catch (PDOException $e) {
    error_log("Database error in <file>.php: " . $e->getMessage());
    api_fail('database_error', 500, ['detail' => $e->getMessage()]);
} catch (Throwable $e) {
    error_log("Unexpected error in <file>.php: " . $e->getMessage());
    api_fail('internal_error', 500, ['detail' => $e->getMessage()]);
}
```

### C.2 Compléter les try/catch partiels
| Route | Correction |
|-------|-----------|
| `meeting_validate.php` | Englober `$repo->findByIdForTenant()` dans le try existant |
| `degraded_tally.php` | Englober `audit_log()` + `NotificationsService::emit()` + `api_ok()` dans le try |

### C.3 Corriger `meeting_launch.php`
- Remplacer `throw $e` (ligne 131) par `api_fail('launch_failed', 500, ['detail' => $e->getMessage()])`.
- Inverser l'ordre : `api_require_role()` AVANT `api_request('POST')`.

---

## Phase D — Tests MailerService & EmailQueueService (priorité moyenne)

### D.1 `tests/Unit/MailerServiceTest.php`
- `testIsConfiguredReturnsFalseWithEmptyConfig()`
- `testIsConfiguredReturnsTrueWithValidConfig()`
- `testSanitizeHeaderRemovesNewlines()`
- `testBuildMessageContainsRequiredHeaders()`
- `testSendReturnsErrorWhenNotConfigured()`

### D.2 `tests/Unit/EmailQueueServiceTest.php`
- `testScheduleInvitationsRequiresValidMeetingId()`
- `testProcessQueueHandlesEmptyQueue()`
- `testGetQueueStatsReturnsExpectedShape()`

(Tests unitaires sans SMTP réel — mock ou vérification de la logique interne.)

---

## Phase E — Nettoyage console.log frontend (priorité moyenne)

### E.1 Stratégie
Remplacer les `console.log` par un guard conditionnel. Ne PAS supprimer les logs —
les garder utiles en dev, silencieux en prod.

### E.2 Fichiers concernés
| Fichier | Occurrences | Action |
|---------|------------|--------|
| `sw.js` | 8 | Garder — Service Worker, utile pour le debug |
| `websocket-client.js` | 9 | Guard `if (this.debug)` (propriété existante ?) |
| `offline-storage.js` | 9 | Guard `if (window.AG_DEBUG)` |
| `conflict-resolver.js` | 2 | Guard `if (window.AG_DEBUG)` |
| `utils.js` | 1 | Déjà dans une fonction utilitaire — OK |
| `components/index.js` | 1 | Déjà gardé par `hostname === 'localhost'` — OK |

### E.3 Implémentation
Ajouter en haut de `utils.js` :
```js
window.AG_DEBUG = window.AG_DEBUG ??
  (location.hostname === 'localhost' || location.hostname === '127.0.0.1');
```
Puis dans chaque fichier, remplacer `console.log('[PREFIX]', ...)` par
`if (window.AG_DEBUG) console.log('[PREFIX]', ...)`.

---

## Ordre d'exécution

1. **Phase A** (sécurité) — impact immédiat, changements simples
2. **Phase B** (InputValidator) — le plus gros chantier, route par route
3. **Phase C** (try/catch) — mécanique, peut se faire en parallèle de B
4. **Phase D** (tests) — nouveaux fichiers, pas de régression
5. **Phase E** (console.log) — cosmétique, en dernier

---

## Fichiers à modifier

| Fichier | Phases |
|---------|--------|
| `public/api/v1/quorum_policies.php` | A.1, C.1 |
| `public/api/v1/vote_policies.php` | A.1, C.1 |
| `public/api/v1/meeting_status.php` | A.1, A.2 |
| `public/api/v1/quorum_status.php` | A.1 |
| `public/api/v1/attendances.php` | A.3 |
| `.env.example` | A.4 |
| `public/api/v1/meetings.php` | B.1 |
| `public/api/v1/members.php` | B.1 |
| `public/api/v1/motions.php` | B.1 |
| `public/api/v1/agendas.php` | B.1 |
| `public/api/v1/admin_quorum_policies.php` | B.1, C.1 |
| `public/api/v1/admin_vote_policies.php` | B.1, C.1 |
| `app/Core/Validation/Schemas/ValidationSchemas.php` | B.2, B.4 |
| `public/api/v1/dashboard.php` | C.1 |
| `public/api/v1/invitations_schedule.php` | C.1 |
| `public/api/v1/meeting_validate.php` | C.2 |
| `public/api/v1/degraded_tally.php` | C.2 |
| `public/api/v1/meeting_launch.php` | C.3 |
| `tests/Unit/MailerServiceTest.php` | D.1 (nouveau) |
| `tests/Unit/EmailQueueServiceTest.php` | D.2 (nouveau) |
| `public/assets/js/core/utils.js` | E.3 |
| `public/assets/js/services/websocket-client.js` | E.2 |
| `public/assets/js/services/offline-storage.js` | E.2 |
| `public/assets/js/services/conflict-resolver.js` | E.2 |
