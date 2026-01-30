# Plan de migration MVC

Ce document decrit la migration de l'architecture actuelle (endpoint-centric)
vers un MVC pragmatique a 3 couches. La migration est incrementale : on peut
convertir un endpoint a la fois sans casser l'existant.

---

## Etat actuel

```
public/api/v1/*.php  →  [SQL direct OU Service]  →  PostgreSQL
```

| Metrique | Valeur |
|----------|--------|
| Endpoints | 118 fichiers |
| SQL inline dans endpoints | 84 (71%) |
| Endpoints deleguant a un service | 34 (29%) |
| Services | 17 classes statiques (3 300 lignes) |
| Repositories | 0 |
| DTOs / Entites | 0 |
| Injection de dependances | Non (global `$pdo`) |

**Probleme** : 71% des endpoints melangent validation HTTP, logique metier
et requetes SQL dans le meme fichier.

---

## Architecture cible

```
Endpoint (Controller)  →  Service (Logique metier)  →  Repository (SQL)
       ↓                         ↓                          ↓
   Validation HTTP          Regles metier           Requetes PostgreSQL
   Auth / CSRF              Exceptions typees       Retourne des arrays
   Response JSON            Aucun SQL               Aucune logique
```

**Regles strictes :**
- Un endpoint ne contient JAMAIS de SQL
- Un service ne contient JAMAIS de SQL — il appelle un Repository
- Un repository ne contient JAMAIS de logique metier
- `audit_log()` reste dans l'endpoint (couche HTTP)

---

## Structure de fichiers cible

```
app/
  Repository/                    # NOUVEAU — acces donnees
    AbstractRepository.php       # Classe de base (PDO, helpers)
    MeetingRepository.php
    MotionRepository.php
    BallotRepository.php
    MemberRepository.php
    AttendanceRepository.php
    ProxyRepository.php
    AuditEventRepository.php
    NotificationRepository.php
    InvitationRepository.php
    DeviceRepository.php
    MeetingReportRepository.php
    QuorumCalculationRepository.php
    VoteCalculationRepository.php
    SpeechRepository.php
  services/                      # REFACTORISE — logique metier pure
    BallotsService.php           # Utilise BallotRepository + MemberRepository
    AttendancesService.php       # Utilise AttendanceRepository
    QuorumEngine.php             # Utilise QuorumCalculationRepository
    VoteEngine.php               # Utilise VoteCalculationRepository
    NotificationsService.php     # Utilise NotificationRepository
    ...
  Core/                          # INCHANGE — securite
    Security/AuthMiddleware.php
    Security/CsrfMiddleware.php
    Security/RateLimiter.php
    Validation/InputValidator.php
  bootstrap.php                  # Ajoute : autoload repositories
  api.php                        # INCHANGE
public/api/v1/                   # REFACTORISE — thin controllers
  meetings.php                   # 10-20 lignes max
  motions.php
  ...
```

---

## Phases de migration

### Phase 0 — Fondation (prerequis)

**Objectif** : poser les bases sans toucher aux endpoints existants.

1. **AbstractRepository** : classe de base avec acces PDO

```php
// app/Repository/AbstractRepository.php
class AbstractRepository {
    protected PDO $pdo;

    public function __construct(?PDO $pdo = null) {
        $this->pdo = $pdo ?? db();
    }

    protected function selectOne(string $sql, array $params = []): ?array {
        $st = $this->pdo->prepare($sql);
        $st->execute($params);
        $row = $st->fetch(PDO::FETCH_ASSOC);
        return $row === false ? null : $row;
    }

    protected function selectAll(string $sql, array $params = []): array {
        $st = $this->pdo->prepare($sql);
        $st->execute($params);
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }

    protected function execute(string $sql, array $params = []): int {
        $st = $this->pdo->prepare($sql);
        $st->execute($params);
        return $st->rowCount();
    }

    protected function scalar(string $sql, array $params = []) {
        $st = $this->pdo->prepare($sql);
        $st->execute($params);
        return $st->fetchColumn();
    }
}
```

2. **Autoloading** : ajouter PSR-4 dans composer.json

```json
{
  "autoload": {
    "psr-4": {
      "AgVote\\Repository\\": "app/Repository/",
      "AgVote\\Service\\": "app/services/"
    }
  }
}
```

3. **bootstrap.php** : ajouter `require vendor/autoload.php`

**Livrable** : AbstractRepository + autoload. Zero changement fonctionnel.

---

### Phase 1 — Domaines simples (CRUD)

**Objectif** : migrer les endpoints simples pour etablir le pattern.

| Domaine | Endpoints | Repository | Effort |
|---------|-----------|------------|--------|
| Members | 4 fichiers | MemberRepository | 1 jour |
| Proxies | 3 fichiers | ProxyRepository | 0.5 jour |
| Invitations | 4 fichiers | InvitationRepository | 1 jour |
| Agendas | 2 fichiers | AgendaRepository | 0.5 jour |

**Exemple — members.php avant :**

```php
api_require_role('operator');
if ($method === 'GET') {
    $members = db_select_all(
        "SELECT id, full_name, vote_weight, is_active
         FROM members WHERE tenant_id = ? ORDER BY full_name",
        [api_current_tenant_id()]
    );
    api_ok(['members' => $members]);
}
```

**Apres :**

```php
api_require_role('operator');
$repo = new MemberRepository();
if ($method === 'GET') {
    $members = $repo->listActive(api_current_tenant_id());
    api_ok(['members' => $members]);
}
```

**Livrable** : 4 repositories, 13 endpoints migres, tests unitaires.

---

### Phase 2 — Domaines coeur (Meetings + Motions)

**Objectif** : migrer le domaine le plus volumineux (29 endpoints).

| Domaine | Endpoints | Repository | Effort |
|---------|-----------|------------|--------|
| Meetings | 20 fichiers | MeetingRepository | 5 jours |
| Motions | 9 fichiers | MotionRepository | 3 jours |

Les meetings sont le domaine le plus complexe : machine d'etats
(draft → scheduled → frozen → live → closed → validated → archived),
transitions, validation, statistiques, rapports.

**MeetingRepository — methodes cles :**

```
findById(id, tenantId): ?array
listByTenant(tenantId, status?): array
create(data): array
update(id, data): int
transition(id, fromStatus, toStatus): bool
archive(id): bool
validate(id, userId): bool
getStats(id): array
getSummary(id): array
getReadyCheck(id): array
```

**Livrable** : 2 repositories, 29 endpoints migres, tests integration workflow.

---

### Phase 3 — Moteurs de calcul (Quorum + Vote)

**Objectif** : extraire le SQL des moteurs de calcul.

| Service actuel | Repository cible | Lignes | Effort |
|----------------|-----------------|--------|--------|
| QuorumEngine | QuorumCalculationRepository | 186 | 1.5 jours |
| VoteEngine | VoteCalculationRepository | 254 | 1.5 jours |
| OfficialResultsService | VoteCalculationRepository | 288 | 1 jour |

**Principe** : le service garde la logique de calcul (seuils, regles de
majorite), le repository fournit les donnees brutes (comptages, aggregations).

```
QuorumEngine.compute(motionId)
  → QuorumCalculationRepository.getAttendanceCounts(meetingId)
  → QuorumCalculationRepository.getPolicyForMotion(motionId)
  → calcul du seuil (logique pure, testable sans BD)
```

**Livrable** : 2 repositories, 3 services refactorises, tests unitaires calcul.

---

### Phase 4 — Domaines secondaires

**Objectif** : migrer les domaines restants.

| Domaine | Endpoints | Repository | Effort |
|---------|-----------|------------|--------|
| Attendance | 5 fichiers | AttendanceRepository | 1 jour |
| Ballots/Voting | 10 fichiers | BallotRepository | 2 jours |
| Admin | 7 fichiers | UserRepository + SystemRepository | 2 jours |
| Export/Reports | 8 fichiers | MeetingReportRepository | 2 jours |
| Device/Emergency | 14 fichiers | DeviceRepository | 2 jours |
| Audit | 2 fichiers | AuditEventRepository | 0.5 jour |
| Quorum config | 4 fichiers | PolicyRepository | 0.5 jour |
| Notifications | 5 fichiers | NotificationRepository | 1 jour |

**Livrable** : 8 repositories, ~55 endpoints migres.

---

### Phase 5 — Nettoyage

**Objectif** : supprimer le code mort et valider.

1. Supprimer les appels `db_select_*()` / `db_execute()` des endpoints
2. Supprimer `global $pdo` des services
3. Verifier que TOUT le SQL est dans `app/Repository/`
4. Marquer les helpers `db_*()` comme deprecated (garder pour compatibilite)
5. Audit securite (aucune regression AuthMiddleware/CSRF)

---

## Inventaire par domaine

### Endpoints deja propres (34 — aucun travail)

| Fichier | Service utilise |
|---------|-----------------|
| ballots_cast.php | BallotsService |
| ballots_result.php | VoteEngine |
| speech_*.php (8) | SpeechService |
| proxies.php | ProxiesService |
| proxies_upsert.php | ProxiesService |
| notifications_*.php (5) | NotificationsService |
| auth_login.php | AuthMiddleware |
| auth_logout.php | - |
| ping.php | - |
| whoami.php | - |
| + ~14 autres | divers |

### Endpoints a migrer (84 — par priorite)

**Critique** (coeur metier, 29 fichiers) :
- meetings.php, meetings_update.php, meetings_archive.php
- meeting_transition.php, meeting_status.php, meeting_validate.php
- meeting_consolidate.php, meeting_ready_check.php, meeting_stats.php
- meeting_summary.php, meeting_report.php, meeting_generate_report_pdf.php
- meeting_audit.php, meeting_audit_events.php, meeting_quorum_settings.php
- meeting_vote_settings.php, meeting_late_rules.php, meeting_report_send.php
- meeting_reset_demo.php, meeting_status_for_meeting.php
- motions.php, motions_open.php, motions_close.php
- motion_tally.php, motion_delete.php, degraded_tally.php
- motions_reorder.php, motions_for_meeting.php, motions_batch.php

**Important** (domaines de support, 30 fichiers) :
- members.php, members_import.php, members_export.php, members_dedup.php
- attendances.php, attendances_bulk.php, attendances_history.php
- attendances_check_in.php, attendances_check_out.php
- manual_vote.php, operator_open_vote.php
- admin_users.php, admin_roles.php, admin_config.php
- admin_system_status.php, admin_tenants.php, admin_audit_log.php
- export_*.php (4 fichiers)
- trust_checks.php, trust_anomalies.php
- quorum_compute.php, quorum_status.php
- + 5 autres

**Secondaire** (utilitaires, 25 fichiers) :
- invitations_create.php, invitations_send_bulk.php, invitations_list.php
- device_*.php (4 fichiers)
- emergency_*.php, projector_*.php
- agendas.php, agendas_for_meeting.php
- votes/ sous-dossier (3 fichiers)
- + divers

---

## Regles de migration par endpoint

Pour chaque endpoint a migrer :

1. **Identifier** les requetes SQL dans le fichier
2. **Creer** la methode correspondante dans le Repository
3. **Remplacer** l'appel SQL par l'appel Repository
4. **Verifier** que l'endpoint ne contient plus de SQL
5. **Tester** via cURL que le comportement est identique
6. **Committer** endpoint par endpoint (petit diff, facile a relire)

---

## Ce qui ne change PAS

| Composant | Raison |
|-----------|--------|
| app/Core/Security/* | Securite deja bien isolee |
| app/Core/Validation/* | Validation deja bien isolee |
| api.php | Helpers de reponse inchanges |
| Format API `{"ok": true, "data": {...}}` | Aucun breaking change |
| database/schema.sql | Schema SQL inchange |
| public/*.html | Frontend inchange |

---

## Estimation globale

| Phase | Contenu | Effort |
|-------|---------|--------|
| 0 | Fondation (AbstractRepository, autoload) | 1 jour |
| 1 | Domaines simples (Members, Proxies, Invitations, Agendas) | 3 jours |
| 2 | Meetings + Motions | 8 jours |
| 3 | Moteurs de calcul (Quorum, Vote) | 4 jours |
| 4 | Domaines secondaires | 11 jours |
| 5 | Nettoyage + validation | 2 jours |
| **Total** | | **~29 jours de dev** |

---

## Criteres de succes

- [ ] 0 requete SQL dans les endpoints
- [ ] 0 `global $pdo` dans les services
- [ ] 15+ repositories avec tests unitaires
- [ ] Format API inchange (zero breaking change)
- [ ] Securite inchangee (AuthMiddleware, CSRF, RateLimiter)
- [ ] Temps de reponse API : +-10% (pas de regression)
