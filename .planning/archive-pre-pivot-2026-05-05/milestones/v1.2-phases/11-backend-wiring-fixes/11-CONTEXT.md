# Phase 11: Backend Wiring Fixes - Context

**Gathered:** 2026-04-08
**Status:** Ready for planning
**Mode:** Auto-generated from v1.2-PAGES-AUDIT.md findings + user MVP escalation

<domain>
## Phase Boundary

Eliminer toutes les "wiring gaps" identifiees dans v1.2-PAGES-AUDIT.md :
- 5 endpoints fantomes que le frontend appelle mais qui n'existent pas
- 6 settings DEAD qui sont sauvegardes en DB mais jamais lus par le code metier (dont 3 critiques pour le vote)
- 4 boutons orphelins (3 dans /trust + 1 #btnStartTour /meetings)
- Dette tech v1.0 carry-over (getDashboardStats wiring, MeetingReports/Motions split)

Phase prerequis avant Phase 12 (page sweep) : impossible de prouver "le bouton fonctionne" si l'endpoint n'existe pas.

</domain>

<decisions>
## Implementation Decisions (locked from audit)

### Endpoints fantomes a creer

1. **`public/api/v1/procuration_pdf.php`** — Genere PDF de procuration
   - Input: `proxy_id`, `meeting_id` (query params)
   - Auth: operator/president/admin OR proxy giver_member via voter token
   - Output: `application/pdf` stream
   - Implementation: deja existe `ProcurationPdfService` (verifie via grep) — il suffit de creer le endpoint qui l'appelle

2. **`public/api/v1/motions_override_decision.php`** — Override decision motion avec justification
   - Input: POST { motion_id, decision (adopted/rejected), justification }
   - Auth: admin/president only
   - Behavior: update motions table, audit_log entry, return updated motion
   - Implementation: nouveau MotionsService::overrideDecision() ou methode sur MotionsController

3. **`public/api/v1/invitations_send_reminder.php`** — Envoi de rappels invitations
   - Input: POST { meeting_id }
   - Auth: operator/admin
   - Behavior: trouve toutes les invitations non-confirmees pour le meeting, queue email reminder via EmailQueueService, retourne count
   - Implementation: utiliser InvitationsService existant + EmailQueueService

4. **`public/api/v1/meeting_attachments_public.php`** — Liste attachments accessibles publiquement
   - Input: GET { meeting_id }
   - Auth: aucune (mais meeting doit etre live OU le requester doit avoir un token de vote valide)
   - Output: liste { id, filename, size, mime } pour les attachments avec `is_public=true` ou similaire
   - Implementation: nouveau endpoint, query meeting_attachments WHERE public=true

5. **`public/api/v1/meeting_attachment_serve.php`** — Sert le fichier attachment
   - Input: GET { id }
   - Auth: meme regle que `_public`
   - Output: stream le fichier avec Content-Disposition
   - Implementation: lire le fichier depuis upload dir, stream

### Settings DEAD a cabler dans le code metier

**Critiques (vote engine)** :
1. **`settVoteMode`** — `VoteEngine` doit lire ce setting pour decider si les votes sont publics ou secrets. Si "secret", le ballot ne stocke pas l'identite du votant en clair.
2. **`settQuorumThreshold`** — `QuorumEngine::computeDecision()` doit utiliser ce seuil au lieu d'une constante hardcodee.
3. **`settMajority`** — Idem, le calcul de decision (adopted/rejected) doit lire le type de majorite depuis ce setting.

**Decision a prendre par tasks** : `settMaxLoginAttempts`, `settPasswordMinLength`, `settHighContrast` — soit on les wire dans le code, soit on les supprime du HTML. Recommandation : supprimer si on ne va pas les implementer (pas de field menteur).

### Boutons orphelins

- **`#btnExportSelection`**, **`#btnExportSelectedCsv`**, **`#btnExportSelectedJson`** dans /trust : decider — wire vers TrustController exports OR supprimer les 3 du HTML
- **`#btnStartTour`** dans /meetings : c'est un onboarding tour stub. Soit on l'implemente (non — hors scope MVP), soit on le supprime.

### Dette tech v1.0

- **DEBT-01** : `DashboardController::index()` doit appeler `$this->repo()->meeting()->getDashboardStats($tenantId)` (existe deja, ecrit en Phase 2 v1.0) au lieu des 11 COUNT separes
- **DEBT-02** : `MeetingReportsController` (727 lignes) - extraire la logique metier dans `MeetingReportsService`, laisser le controller mince (orchestration HTTP only)
- **DEBT-03** : Idem `MotionsController` (720 lignes) - extraire `MotionsService`

### Test gates

Chaque task doit produire :
- Le code (endpoint OU wiring OU refactor)
- Un test PHPUnit qui verifie le comportement
- Pour les settings : un test qui CHANGE la valeur et verifie que le calcul change
- Pour les endpoints : un test qui POST/GET et verifie 200 OK + payload structure
- `php -l` clean
- Aucune regression : les tests existants doivent toujours passer

</decisions>

<code_context>
## Existing Code Insights

### Files concerned
- app/Services/VoteEngine.php (cabler settVoteMode/settMajority)
- app/Services/QuorumEngine.php (cabler settQuorumThreshold)
- app/Repository/SettingsRepository.php (existe deja, methode get())
- app/Services/ProcurationPdfService.php (existe, juste cree endpoint)
- app/Services/EmailQueueService.php (existe, scheduleReminders methode probable)
- app/Services/InvitationsService.php (existe)
- app/Controller/DashboardController.php (DEBT-01)
- app/Controller/MeetingReportsController.php (DEBT-02)
- app/Controller/MotionsController.php (DEBT-03)
- public/trust.htmx.html (4 orphan buttons)
- public/meetings.htmx.html (1 orphan #btnStartTour)
- public/settings.htmx.html (potentially clean up dead settings)
- public/api/v1/ (5 new endpoints to create)

### Routes registration
Each new endpoint must be registered in app/routes.php via `$router->mapXXX()`.

### Test infrastructure
- PHPUnit suite already exists
- Playwright suite already exists (Phase 8/9)
- Phase 9 critical-path-{role}.spec.js can be extended to cover the new endpoints

</code_context>

<specifics>
## Specific Ideas

- User says "MVP minimum" — every fix must be PROVED working, not just "exists"
- For each new endpoint: write a PHPUnit integration test that hits the route and asserts response
- For each settings wire: write a PHPUnit test that demonstrates "before wire = constant value, after wire = setting respected"
- For dead buttons: removing is acceptable (user said MVP, not feature-complete) — document the removal in commit msg

</specifics>

<deferred>
## Deferred Ideas

None - this phase is the "make everything actually work" phase.

</deferred>
