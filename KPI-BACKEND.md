# KPI Backend — gestion-votes

> Mesurage au 2026-02-20, branche `claude/linux-kernel-role-6Sv0J`

---

## Tableau de bord

| KPI | Actuel | Cible | Priorite |
|-----|--------|-------|----------|
| Endpoints publics avec rate limiting | **9/12** (75%) | **12/12** (100%) | HAUTE |
| Info leakage neutralisee (api_fail centralisee) | **OUI** | maintenir | - |
| Tenant isolation — controllers avec check | **100%** critiques fixes | maintenir 100% | CRITIQUE |
| Repository UPDATE/DELETE avec tenant_id | ~60% | **100%** | HAUTE |
| Methodes controller avec audit_log | **51/98** (52%) | **>80%** | MOYENNE |
| ensureSchema() / DDL runtime restants | **16 appels** | **0** | MOYENNE |
| Exceptions silencieuses (catch vide) | **2** (dev-only) | **0** | BASSE |
| Validation UUID systematique | partiel | **100% api_require_uuid** | MOYENNE |

---

## Detail par axe

### 1. SECURITE — Isolation multi-tenant

**Etat** : Les 3 failles critiques IDOR sont corrigees (SpeechController, DevicesController, InvitationsController). Le delete proxy est isole.

**Reste a faire** :
- 16+ methodes repository font des UPDATE/DELETE sans `tenant_id` dans le WHERE
- Principalement dans : `InvitationRepository` (8), `EmailQueueRepository` (5), `AttendanceRepository` (3)
- Ces methodes sont generalement appelees depuis des contextes deja valides par le controller, mais la defense en profondeur exige le tenant dans chaque requete

**KPI** : `nb_repo_methods_sans_tenant / total_repo_write_methods`
**Objectif** : 0%

---

### 2. SECURITE — Rate limiting

**Etat** : 9 endpoints publics sur 12 ont du rate limiting (75%).

| Endpoint | Rate limit | Status |
|----------|-----------|--------|
| ballot_cast | 60/min | OK |
| ballot_result | 120/min | OK |
| vote_incident | 30/min | OK |
| speech_request | 30/min | OK |
| speech_queue | 120/min | OK |
| speech_current | 120/min | OK |
| speech_myStatus | 120/min | OK |
| device_heartbeat | 60/min | OK |
| invitation_redeem | 30/min | OK |
| current_motion | - | MANQUANT |
| motions_for_meeting | - | MANQUANT |
| meeting_stats | - | MANQUANT |

**KPI** : `endpoints_publics_avec_ratelimit / total_endpoints_publics`
**Objectif** : 100%

---

### 3. FIABILITE — Couverture audit trail

**Etat** : 51 appels `audit_log()` dans 98 methodes controller (52%).

| Controller | Methodes | audit_log | Couvert |
|-----------|----------|-----------|---------|
| AdminController | 5 | 9 | 100% |
| ImportController | 6 | 6 | 100% |
| PoliciesController | 4 | 4 | 100% |
| ProxiesController | 3 | 3 | 100% |
| MotionsController | 7 | 6 | ~85% |
| SpeechController | 9 | 6 | ~67% |
| BallotsController | 7 | 4 | ~57% |
| MeetingWorkflowController | 6 | 4 | ~67% |
| DevicesController | 5 | 3 | 60% |
| AttendancesController | 4 | 2 | 50% |
| MeetingsController | 8 | 1 | 12% |
| ExportController | 9 | 0 | 0% |
| EmailController | 3 | 0 | 0% |
| MeetingReportsController | 5 | 0 | 0% |
| AuditController | 5 | 0 | 0% (read-only, acceptable) |
| TrustController | 2 | 0 | 0% (read-only, acceptable) |

**Methodes critiques sans audit** :
- `ExportController` : 9 methodes d'export sans trace (qui exporte quoi, quand)
- `EmailController` : envois d'email sans trace
- `MeetingsController` : creation/modification de seances presque sans trace
- `MeetingReportsController` : generation de rapports sans trace

**KPI** : `methodes_avec_audit / methodes_etat_changeant`
**Objectif** : >80% (les GET purs n'ont pas besoin d'audit)

---

### 4. QUALITE CODE — DDL runtime

**Etat** : 16 appels `ensureSchema()` restants.

| Service/Repository | Appels |
|-------------------|--------|
| SpeechService | 7 |
| NotificationsService | 7 |
| ManualActionRepository (via degraded_tally) | 1 |
| OfficialResultsService (methode morte) | 1 |

**Impact** : Chaque requete HTTP execute potentiellement un CREATE TABLE IF NOT EXISTS, degradant les performances et compliquant les tests.

**KPI** : `nb_appels_ensureSchema`
**Objectif** : 0 (tout dans les migrations SQL)

---

### 5. QUALITE CODE — Validation input coherente

**Etat** : Melange de patterns dans les controllers.

| Pattern | Occurrences | Recommande |
|---------|------------|------------|
| `api_require_uuid($data, 'field')` | ~30 | OUI |
| `trim() + manual check` | ~25 | NON |
| `(string)($_GET['x'] ?? '')` sans validation | ~10 | NON |

**KPI** : `usages_api_require_uuid / total_uuid_validations`
**Objectif** : 100%

---

### 6. SECURITE — Fuite d'information

**Etat** : Corrige au niveau central (`api_fail()` strip les details en non-dev).

28 endpoints passent encore `$e->getMessage()` a `api_fail()`, mais le filtre central les neutralise sauf en `APP_ENV=development`.

**KPI** : `api_fail_avec_exception_message`
**Objectif** : Remplacer progressivement par des messages generiques au niveau endpoint (defense en profondeur)

---

## Plan d'action prioritise

### Sprint 1 — Securite (immediat)
- [ ] Ajouter rate limiting aux 3 endpoints publics manquants
- [ ] Ajouter `tenant_id` aux 16 methodes repository manquantes
- [ ] Audit trail sur `MeetingsController` (creation/modification seances)

### Sprint 2 — Fiabilite (court terme)
- [ ] Migrer `SpeechService` et `NotificationsService` hors DDL runtime (14 appels)
- [ ] Audit trail sur `ExportController` et `EmailController`
- [ ] Standardiser validation UUID (`api_require_uuid` partout)

### Sprint 3 — Qualite (moyen terme)
- [ ] Remplacer `$e->getMessage()` par messages generiques dans les 28 endpoints
- [ ] Supprimer methode morte `OfficialResultsService::ensureSchema()`
- [ ] Supprimer deprecated `db_*()` wrappers dans bootstrap.php si plus utilises

---

## Metriques de reference (baseline)

```
date:                2026-02-20
commit:              85eb4b2
php_files_total:     ~200
api_endpoints_v1:    142
controllers:         20
controller_methods:  98
audit_log_calls:     51
rate_limited:        9/12 public
tenant_isolated:     critiques 100%, repo ~60%
ddl_runtime:         16
silent_catches:      2
info_leakage:        neutralise centralement
```
