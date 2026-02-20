# AUDIT DE SECURITE — AG-Vote (gestion-votes)

**Application :** AG-Vote — Systeme de gestion de votes en assemblee generale
**Date :** 2026-02-20
**Perimetre :** Authentification, autorisation, injection SQL, CSRF, XSS, SSE/WebSocket, gestion des secrets, integrite des votes
**Nombre de constatations :** 28

---

## TABLE DE SYNTHESE

| # | Severite | Titre | Fichier(s) principal(aux) |
|---|----------|-------|---------------------------|
| 01 | CRITIQUE | Le endpoint de vote ne requiert aucune authentification | `BallotsController.php:45` |
| 02 | CRITIQUE | L'upsert de bulletin permet la modification silencieuse des votes | `BallotRepository.php:127-143` |
| 03 | CRITIQUE | Authentification desactivee par defaut | `AuthMiddleware.php:172-176` |
| 04 | ELEVEE | Suppression des logs d'audit (reset demo) detruit la chaine de hachage | `MeetingWorkflowController.php:484` |
| 05 | ELEVEE | Protection CSRF contournee pour les endpoints public/voter | `api.php:84-88` |
| 06 | ELEVEE | Absence d'isolation multi-tenant dans plusieurs requetes | Multiples repositories |
| 07 | ELEVEE | L'auditeur peut modifier les donnees via la consolidation | `MeetingWorkflowController.php:441-453` |
| 08 | ELEVEE | Endpoint SSE sans authentification ni isolation tenant | `stream.php:1-23` |
| 09 | MOYENNE | L'auditeur peut saisir des comptages manuels (mode degrade) | `degraded_tally.php:15` |
| 10 | MOYENNE | `setCurrentUser()` public en code de production | `AuthMiddleware.php:876-879` |
| 11 | MOYENNE | Resultats de vote accessibles sans authentification | `BallotsController.php:155-166` |
| 12 | MOYENNE | `motions_close.php` : role president sans scope seance | `motions_close.php:13` |
| 13 | MOYENNE | `meeting_validate.php` : role president sans scope seance | `meeting_validate.php:9` |
| 14 | MOYENNE | Procedures d'urgence : protection IDOR faible | `emergency_procedures.php:6-10` |
| 15 | MOYENNE | Signalement d'incident sans authentification (pollution du log) | `BallotsController.php:306-324` |
| 16 | MOYENNE | File d'evenements WS sur filesystem sans controle d'acces | `EventBroadcaster.php:14-15, 140-168` |
| 17 | MOYENNE | XSS potentiel via innerHTML dans le front-end | Multiples fichiers JS |
| 18 | MOYENNE | Fuite de detail d'erreur en mode degrade | `degraded_tally.php:94` |
| 19 | FAIBLE | Hierarchie de roles accorde implicitement l'acces auditeur aux operateurs | `AuthMiddleware.php:332-343` |
| 20 | FAIBLE | Heartbeat device accepte un role arbitraire du client | `DevicesController.php:157-171` |
| 21 | FAIBLE | Sources de definition des permissions dupliquees | Multiples fichiers |
| 22 | FAIBLE | LIMIT par interpolation dans les requetes SQL | `UserRepository.php:524`, `MeetingRepository.php:790` |
| 23 | FAIBLE | Secret applicatif de repli previsible en mode dev | `AuthMiddleware.php:859` |
| 24 | FAIBLE | Absence de Content-Security-Policy | Configuration serveur |
| 25 | INFO | Controles de securite positifs | — |

---

## CONSTATATIONS DETAILLEES

---

### CONSTATATION 01 — Le endpoint de vote ne requiert aucune authentification (CRITIQUE)

**Fichier :** `app/Controller/BallotsController.php:45`

```php
public function cast(): void
{
    api_require_role('public');  // Aucune authentification
```

**Fichier :** `app/Core/Security/AuthMiddleware.php:308`

```php
if (in_array('public', $roles, true)) {
    return true;  // Acces sans aucun controle
}
```

**Description :** Le endpoint `ballots_cast` utilise `api_require_role('public')`, ce qui signifie que n'importe qui sur le reseau peut l'appeler sans cle API, sans session et sans credential. La seule protection est la logique metier dans `BallotsService` (verification de presence, motion ouverte, membre actif). Un attaquant non authentifie connaissant un `motion_id` et un `member_id` valides peut voter ou **ecraser** des votes existants.

**Impact :** Un attaquant non authentifie peut voter pour n'importe quel membre ou ecraser des votes existants. Compromission complete de l'integrite du scrutin.

**Remediation :**
1. Exiger au minimum un jeton de vote (`vote_token`) valide ou le role `voter` avec authentification.
2. Valider le jeton de vote au niveau du endpoint, pas uniquement via le `member_id` fourni par le client.

---

### CONSTATATION 02 — L'upsert de bulletin permet la modification silencieuse des votes (CRITIQUE)

**Fichier :** `app/Repository/BallotRepository.php:127-143`

```php
ON CONFLICT (motion_id, member_id) DO UPDATE
SET value = EXCLUDED.value,
    weight = EXCLUDED.weight,
    cast_at = now(),
    is_proxy_vote = EXCLUDED.is_proxy_vote,
    proxy_source_member_id = EXCLUDED.proxy_source_member_id
```

**Description :** La methode `castBallot()` utilise `ON CONFLICT ... DO UPDATE`, ce qui signifie qu'une seconde requete de vote pour le meme couple `(motion_id, member_id)` remplace silencieusement le vote precedent. Combine avec la constatation 01, un attaquant non authentifie peut ecraser le bulletin de n'importe quel votant tant que la motion est ouverte. Il n'existe aucun mecanisme de detection ni de prevention de la falsification.

**Impact :** L'integrite du vote est fondamentalement compromise. Un seul acteur malveillant peut changer le resultat de tout scrutin ouvert.

**Remediation :**
1. Si le re-vote est intentionnel, exiger une autorisation explicite et journaliser la valeur precedente.
2. Si le re-vote n'est PAS voulu, utiliser `INSERT` sans `ON CONFLICT UPDATE` et rejeter les doublons avec une erreur 409.
3. Toujours exiger l'authentification (voir constatation 01).

---

### CONSTATATION 03 — Authentification desactivee par defaut (CRITIQUE)

**Fichier :** `app/Core/Security/AuthMiddleware.php:172-176, 373-382`

```php
public static function isEnabled(): bool
{
    $env = getenv('APP_AUTH_ENABLED');
    return $env === '1' || strtolower((string)$env) === 'true';
}

// Quand desactive :
if (!self::isEnabled()) {
    self::$currentUser = [
        'id' => 'dev-user',
        'role' => 'admin',
        'name' => 'Dev User (Auth Disabled)',
    ];
    return self::$currentUser;
}
```

**Description :** Si `APP_AUTH_ENABLED` n'est pas explicitement mis a `1` ou `true`, l'authentification est completement desactivee et chaque requete s'execute en tant qu'`admin`. L'etat par defaut est « desactive ». Si un deploiement de production omet cette variable d'environnement, l'ensemble du systeme est grand ouvert.

**Impact :** Contournement complet de l'authentification dans les deploiements mal configures. Acces administrateur total pour tout le monde.

**Remediation :** Adopter le modele deny-by-default : l'authentification doit etre active par defaut. Exiger un `APP_AUTH_ENABLED=0` explicite pour la desactiver, et refuser de demarrer en mode `production` avec l'auth desactivee.

---

### CONSTATATION 04 — Suppression des logs d'audit detruit la chaine de hachage (ELEVEE)

**Fichier :** `app/Controller/MeetingWorkflowController.php:484`

```php
(new MeetingRepository())->deleteAuditEventsByMeeting($meetingId, api_current_tenant_id());
```

**Fichier :** `app/Repository/MeetingRepository.php:964-971`

```php
public function deleteAuditEventsByMeeting(string $meetingId, string $tenantId): void
{
    $this->execute(
        "DELETE FROM audit_events WHERE meeting_id = :mid AND tenant_id = :tid",
```

**Description :** La fonction `resetDemo` (accessible par `operator` et `admin`) supprime les audit_events d'une seance. La table `audit_events` utilise une chaine de hachage cryptographique (migration `20260218_security_hardening.sql`), dont la suppression de tout enregistrement brise l'integrite pour toute la portee de la seance. Un operateur peut reinitialiser une seance pour effacer les preuves de manipulation ou de violations procedurales.

**Impact :** La piste d'audit est destructible par les operateurs, ce qui contredit l'objectif du durcissement cryptographique.

**Remediation :**
1. Restreindre `resetDemo` au role `admin` uniquement.
2. Ne jamais supprimer les audit events ; les marquer avec un evenement « reset » tout en preservant la chaine.
3. Ajouter un flag `mode_demo` au niveau de l'environnement et refuser les resets en production.

---

### CONSTATATION 05 — Protection CSRF contournee pour les endpoints public/voter (ELEVEE)

**Fichier :** `app/api.php:84-88`

```php
if ($csrfEnabled && in_array($method, ['POST', 'PUT', 'PATCH', 'DELETE'], true)) {
    if (!in_array('public', $roles, true) && !in_array('voter', $roles, true)) {
        CsrfMiddleware::validate();
    }
}
```

**Description :** La validation CSRF est intentionnellement ignoree pour les endpoints avec les roles `public` ou `voter`. Puisque `ballots_cast` utilise le role `public`, il n'y a aucune protection CSRF sur l'emission des votes. Un site web malveillant peut forger des requetes de vote si un utilisateur a une session active.

**Impact :** Manipulation de votes cross-site sans connaissance de l'utilisateur.

**Remediation :** Implementer un equivalent CSRF base sur le jeton de vote pour les endpoints voter. Le vote_token lui-meme pourrait servir a cet effet.

---

### CONSTATATION 06 — Absence d'isolation multi-tenant dans plusieurs requetes (ELEVEE)

**Fichiers concernes :**

| Fichier | Ligne | Requete sans `tenant_id` |
|---------|-------|--------------------------|
| `BallotRepository.php` | 14-23 | `listForMotion()` — `WHERE b.motion_id = :mid` |
| `BallotRepository.php` | 28-48 | `tally()` — `WHERE motion_id = :mid` |
| `MotionRepository.php` | 354-370 | `findWithBallotContext()` — `WHERE m.id = :mid` |
| `MotionRepository.php` | 822-831 | `findWithMeetingTenant()` — `WHERE mo.id = :id` |

**Description :** Dans un deploiement multi-tenant, ces requetes operent uniquement sur l'identifiant de la ressource (UUID) sans verifier l'appartenance au tenant. Bien que les UUID soient difficiles a deviner, si un `tenant_id` ne correspond pas, les donnees d'un tenant peuvent etre lues ou modifiees par l'utilisateur d'un autre tenant. Le endpoint `ballots_cast` (non authentifie, constatation 01) appelle `findWithBallotContext` qui n'a pas de controle tenant.

**Impact :** Acces inter-tenant aux donnees si les identifiants de ressources sont connus ou fuites.

**Remediation :** Ajouter `AND tenant_id = :tid` a toutes les requetes, ou valider l'appartenance au tenant au niveau de la couche service avant de poursuivre.

---

### CONSTATATION 07 — L'auditeur peut modifier les donnees via la consolidation (ELEVEE)

**Fichier :** `app/Controller/MeetingWorkflowController.php:441-453`

```php
public function consolidate(): void
{
    api_require_role('auditor');
    $body = api_request('POST');
    $meetingId = trim((string)($body['meeting_id'] ?? ''));
    $r = OfficialResultsService::consolidateMeeting($meetingId);
```

**Description :** Le endpoint `consolidate` requiert seulement le role `auditor`, mais la consolidation ecrit les resultats officiels dans les motions. Le role auditeur est defini comme « compliance, lecture seule » dans les commentaires d'architecture. Cela viole le principe du moindre privilege. De plus, `consolidateMeeting` n'a pas de controle tenant (voir constatation 06).

**Impact :** Un auditeur (suppose etre en lecture seule) peut declencher la consolidation et modifier les resultats officiels pour n'importe quelle seance.

**Remediation :**
1. Changer le role requis en `operator` ou `admin`.
2. Ajouter le controle tenant a la logique de consolidation.

---

### CONSTATATION 08 — Endpoint SSE sans authentification ni isolation tenant (ELEVEE)

**Fichier :** `public/api/bus/stream.php:1-23`

```php
<?php
header('Content-Type: text/event-stream');
header('Cache-Control: no-cache');
$file = __DIR__ . '/events.jsonl';
$start = time();
while (true) {
  if (is_readable($file)) {
    $fh = fopen($file, 'r');
    if ($fh) {
      while (($line = fgets($fh)) !== false) {
        $evt = json_decode($line, true);
        if (!$evt) continue;
        echo 'event: ' . $evt['type'] . "\n";
        echo 'data: ' . json_encode($evt['payload']) . "\n\n";
```

**Description :** Le endpoint SSE (Server-Sent Events) ne comporte aucune authentification, aucun controle de role et aucun filtrage par tenant ou par seance. Tout client qui se connecte recoit **tous** les evenements de **toutes** les seances de **tous** les tenants. Cela inclut les resultats de votes en temps reel, les changements de statut des motions et les mises a jour de presence.

De plus, le fichier source (`events.jsonl`) est lu depuis le debut a chaque nouvelle connexion, ce qui signifie que les nouveaux clients recoivent aussi l'historique complet des evenements.

**Impact :**
- Divulgation d'information complete sur tous les scrutins en cours a tout utilisateur non authentifie.
- Pour les votes secrets, cela revele les resultats avant la divulgation officielle.
- Pas de protection contre le flooding de connexions (DoS).

**Remediation :**
1. Ajouter une authentification (session ou token) au endpoint SSE.
2. Filtrer les evenements par `tenant_id` et `meeting_id` en fonction des droits de l'utilisateur.
3. Utiliser un curseur (offset/ID) pour ne pas renvoyer l'historique complet.
4. Implementer une limite de connexions concurrentes.

---

### CONSTATATION 09 — L'auditeur peut saisir des comptages manuels (MOYENNE)

**Fichier :** `public/api/v1/degraded_tally.php:15`

```php
api_require_role(['operator','auditor']);
```

**Description :** Le endpoint de comptage degrade (saisie manuelle) autorise le role `auditor` a ecrire des comptages manuels. Selon le modele RBAC, les auditeurs doivent etre strictement en lecture seule. Ce endpoint modifie les colonnes `motions.manual_*` et ecrit dans la table `manual_actions`.

**Remediation :** Retirer `auditor` des roles autorises. Seul `operator` et `admin` devraient pouvoir saisir des comptages degrades.

---

### CONSTATATION 10 — `setCurrentUser()` public en code de production (MOYENNE)

**Fichier :** `app/Core/Security/AuthMiddleware.php:876-879`

```php
public static function setCurrentUser(?array $user): void
{
    self::$currentUser = $user;
}
```

**Description :** Cette methode statique publique permet a n'importe quel code de definir arbitrairement le contexte utilisateur, y compris le role. Bien que qualifiee de « test helper », elle existe dans le code de production. Si un input utilisateur atteint un chemin de code appelant cette methode, cela permettrait une usurpation d'identite complete.

**Remediation :** Supprimer cette methode du code de production, ou la conditionner derriere un flag test/dev qui ne peut pas etre active en production.

---

### CONSTATATION 11 — Resultats de vote accessibles sans authentification (MOYENNE)

**Fichier :** `app/Controller/BallotsController.php:155-166`

```php
public function result(): void
{
    api_require_role('public');
    $params = api_request('GET');
    $motionId = trim((string)($params['motion_id'] ?? ''));
    $result = VoteEngine::computeMotionResult($motionId);
```

**Description :** Les resultats de vote sont accessibles a des utilisateurs non authentifies en fournissant un `motion_id`. Comme `VoteEngine::computeMotionResult` ne verifie pas le `tenant_id`, n'importe qui peut interroger les resultats de n'importe quelle motion.

**Remediation :** Exiger au minimum le role `viewer` et ajouter le controle tenant.

---

### CONSTATATION 12 — `motions_close.php` : role president sans scope seance (MOYENNE)

**Fichier :** `public/api/v1/motions_close.php:13`

```php
api_require_role(['operator', 'president', 'admin']);
```

**Description :** Le controle de role verifie que l'utilisateur a l'un de ces roles au niveau systeme, mais `president` est un role **scope a la seance**. Le code n'appelle pas `AuthMiddleware::setMeetingContext()` avant de verifier les permissions. Le president de la seance A ne devrait pas pouvoir fermer les motions de la seance B.

**Remediation :** Utiliser `AuthMiddleware::setMeetingContext($meetingId)` puis `AuthMiddleware::requirePermission('motion:close', $meetingId)`.

---

### CONSTATATION 13 — `meeting_validate.php` : role president sans scope seance (MOYENNE)

**Fichier :** `public/api/v1/meeting_validate.php:9`

```php
api_require_role(['president', 'admin']);
```

**Description :** Meme probleme que la constatation 12. Le role `president` devrait etre scope a la seance en cours de validation, mais aucun contexte de seance n'est defini. Le president de la seance A pourrait valider la seance B.

**Remediation :** Definir le contexte de seance et utiliser les controles bases sur les permissions : `AuthMiddleware::setMeetingContext($meetingId)` puis `AuthMiddleware::requirePermission('meeting:validate', $meetingId)`.

---

### CONSTATATION 14 — Procedures d'urgence : protection IDOR faible (MOYENNE)

**Fichier :** `public/api/v1/emergency_procedures.php:6-10`

```php
api_require_role('operator');
$q = api_request('GET');
$aud = trim((string)($q['audience'] ?? 'operator'));
$meetingId = trim((string)($q['meeting_id'] ?? ''));
```

**Description :** Le parametre `audience` est pris directement de l'input utilisateur, et le `meeting_id` n'est pas valide par rapport au tenant de l'utilisateur. Un operateur pourrait lister les procedures d'urgence pour tout type d'audience et consulter les verifications pour tout `meeting_id`.

**Remediation :** Valider que la seance appartient au tenant de l'utilisateur. Restreindre le parametre `audience` au role de l'utilisateur.

---

### CONSTATATION 15 — Signalement d'incident sans authentification (MOYENNE)

**Fichier :** `app/Controller/BallotsController.php:306-324`

```php
public function reportIncident(): void
{
    api_require_role('public');
    $in = api_request('POST');
    $kind = trim((string)($in['kind'] ?? 'network'));
    $detail = trim((string)($in['detail'] ?? ''));
    audit_log('vote_incident', 'vote', $tokenHash ?: null, [
        'kind' => $kind,
        'detail' => $detail,
    ]);
```

**Description :** Des utilisateurs non authentifies peuvent ecrire des rapports d'incident arbitraires dans le log d'audit. Il n'y a pas de limitation de debit, pas de validation de longueur et pas de sanitisation. Cela peut etre utilise pour noyer le log d'audit.

**Remediation :** Ajouter une limitation de debit et des contraintes de longueur. Envisager d'exiger un jeton de session ou de vote valide.

---

### CONSTATATION 16 — File d'evenements WS sur filesystem sans controle d'acces (MOYENNE)

**Fichier :** `app/WebSocket/EventBroadcaster.php:14-15, 140-168`

```php
private const QUEUE_FILE = '/tmp/agvote-ws-queue.json';
private const LOCK_FILE = '/tmp/agvote-ws-queue.lock';

private static function queue(array $event): void
{
    // ... ecrit directement dans le fichier, pas de controle d'acces
    file_put_contents(self::QUEUE_FILE, json_encode($queue));
}
```

**Description :** La file de communication WebSocket utilise un fichier JSON dans `/tmp/` lisible et inscriptible par tout processus du systeme. N'importe quel processus local peut :
- Lire la file et obtenir les evenements de vote en temps reel.
- Injecter des evenements forges dans la file.
- Supprimer ou corrompre la file (DoS).

Il n'y a pas de validation de l'origine des evenements dans `dequeue()`.

**Remediation :**
1. Utiliser des permissions de fichier restrictives (0600).
2. Signer les evenements avec un HMAC pour detecter les injections.
3. Envisager un mecanisme de transport plus robuste (Redis, unix socket prive).

---

### CONSTATATION 17 — XSS potentiel via innerHTML dans le front-end (MOYENNE)

**Fichiers :** Multiples fichiers JS (`shell.js`, `page-components.js`, `operator-attendance.js`, etc.)

```javascript
// Exemples dans shell.js:
dbody.innerHTML = '<div style="padding:4px 0;">' + sections.join('') + '</div>';

// page-components.js:
this.container.innerHTML = html;

// operator-attendance.js:
grid.innerHTML = filtered.map(m => { /* ... */ }).join('');
```

**Description :** De nombreux fichiers JavaScript front-end utilisent `innerHTML` pour inserer du contenu dynamique. Si des donnees provenant du serveur (noms de membres, titres de motions, etc.) ne sont pas correctement echappees en amont, un attaquant pourrait injecter du HTML/JavaScript malveillant. Le composant `ag-searchable-select.js` dispose d'une methode `escapeHtml()` mais elle n'est pas utilisee de maniere systematique.

**Remediation :**
1. Remplacer `innerHTML` par `textContent` lorsque seul du texte est attendu.
2. Utiliser une fonction d'echappement HTML systematique pour toutes les donnees dynamiques.
3. Activer une Content-Security-Policy stricte (voir constatation 24).

---

### CONSTATATION 18 — Fuite de detail d'erreur en mode degrade (MOYENNE)

**Fichier :** `public/api/v1/degraded_tally.php:94`

```php
api_fail('degraded_tally_failed', 500, ['detail' => $e->getMessage()]);
```

**Description :** En cas d'erreur, le message d'exception complet (potentiellement avec des details SQL ou internes) est retourne dans la reponse API. Bien que `api_fail()` supprime les details en mode production pour les erreurs 5xx, ce endpoint les fuit quand meme car le filtrage s'applique uniquement quand `APP_ENV === 'production'` (et le defaut est `demo`).

**Remediation :** Ne jamais exposer `$e->getMessage()` dans les reponses API. Logger l'erreur cote serveur et retourner un message generique.

---

### CONSTATATION 19 — Hierarchie de roles accorde implicitement l'acces auditeur (FAIBLE)

**Fichier :** `app/Core/Security/AuthMiddleware.php:332-343`

```php
$userLevel = self::ROLE_HIERARCHY[$systemRole] ?? 0;
foreach ($roles as $requiredRole) {
    if (self::isMeetingRole($requiredRole)) { continue; }
    $requiredLevel = self::ROLE_HIERARCHY[$requiredRole] ?? 0;
    if ($userLevel >= $requiredLevel) {
        return true;
    }
}
```

**Description :** La verification par hierarchie signifie que tout role de niveau superieur accede implicitement aux permissions des roles inferieurs. Ainsi, `operator` (80) passe automatiquement tout controle exigeant `auditor` (50). Les operateurs peuvent acceder aux ressources de l'auditeur sans attribution explicite.

**Remediation :** Si la hierarchie est intentionnelle, la documenter clairement. Si l'acces auditeur doit etre explicite, retirer la comparaison hierarchique pour le role auditeur.

---

### CONSTATATION 20 — Heartbeat device accepte un role arbitraire du client (FAIBLE)

**Fichier :** `app/Controller/DevicesController.php:157-171`

```php
public function heartbeat(): void
{
    api_require_role('public');
    $in = api_request('POST');
    $role = (string)($in['role'] ?? '');
    $repo->upsertHeartbeat($deviceId, $tenantId, $meetingId, $role, ...);
```

**Description :** Le endpoint heartbeat accepte un champ `role` du client et le stocke directement. Etant un endpoint `public`, n'importe qui peut pretendre etre n'importe quel device avec n'importe quel role, polluant le tableau de bord de monitoring.

**Remediation :** Ne pas faire confiance aux valeurs `role` fournies par le client. Deriver le role de l'utilisateur authentifie ou du role assigne a la seance.

---

### CONSTATATION 21 — Sources de definition des permissions dupliquees (FAIBLE)

**Fichiers :**
- `app/Core/Security/Permissions.php` (lignes 35-98)
- `app/Core/Security/AuthMiddleware.php` (lignes 67-130)
- Table `role_permissions` (migration 003)

**Description :** Les permissions sont definies dans trois endroits separes. Si l'un d'eux diverge, le comportement d'autorisation devient incoherent. Par exemple, `AuthMiddleware::STATE_TRANSITIONS` inclut des transitions `paused` absentes de `Permissions::TRANSITIONS`.

**Remediation :** Utiliser une source unique de verite. La classe `Permissions` devrait etre la source canonique.

---

### CONSTATATION 22 — LIMIT par interpolation dans les requetes SQL (FAIBLE)

**Fichiers :**
- `app/Repository/UserRepository.php:524`
- `app/Repository/MeetingRepository.php:790`

```php
// UserRepository.php:524
"... ORDER BY created_at DESC LIMIT " . max(1, $limit)

// MeetingRepository.php:790
"ORDER BY created_at {$order}"
```

**Description :** Bien que `$limit` soit contraint par `max(1, $limit)` (donc toujours un entier positif) et `$order` est assaini via un controle whitelist (`ASC`/`DESC`), ces patterns d'interpolation dans les requetes SQL sont fragiles. Une modification future negligente pourrait introduire une injection.

**Remediation :** Utiliser des parametres lies meme pour `LIMIT` et `ORDER BY` lorsque le driver le supporte, ou au minimum documenter le contrat de securite de ces fonctions.

---

### CONSTATATION 23 — Secret applicatif de repli previsible en mode dev (FAIBLE)

**Fichier :** `app/Core/Security/AuthMiddleware.php:859`

```php
error_log('[WARNING] Using insecure APP_SECRET in dev mode.');
return 'dev-secret-not-for-production-' . str_repeat('x', 32);
```

**Description :** Quand l'auth est desactivee et qu'aucun `APP_SECRET` n'est configure, un secret de repli deterministe est utilise. Si ce code est execute en production par erreur, tous les HMAC (cles API, jetons de vote) sont calculables par un attaquant.

**Remediation :** En l'absence de secret valide, refuser de demarrer plutot que d'utiliser un repli. Logger une erreur fatale.

---

### CONSTATATION 24 — Absence de Content-Security-Policy (FAIBLE)

**Description :** Aucun header `Content-Security-Policy` n'est envoye par le serveur. L'utilisation intensive de `innerHTML` dans le front-end (constatation 17) rend l'application d'autant plus vulnerable aux attaques XSS sans cette couche de defense en profondeur.

**Remediation :** Ajouter un header CSP restrictif, par exemple :
```
Content-Security-Policy: default-src 'self'; script-src 'self'; style-src 'self' 'unsafe-inline'
```

---

### CONSTATATION 25 — Controles de securite positifs (INFO)

Les controles de securite suivants sont correctement implementes :

1. **Chaine de hachage cryptographique des audit events** (migration `20260218_security_hardening.sql`) avec scope par seance et verrouillage `FOR UPDATE` pour prevenir les conditions de concurrence.
2. **Hachage des cles API avec HMAC-SHA256** — les cles brutes ne sont jamais stockees.
3. **Timeout de session** (30 minutes d'inactivite dans `AuthMiddleware::authenticate()`).
4. **Migration des tokens d'invitation** vers un stockage hash-only.
5. **Machine a etats des seances** avec des regles de transition bien definies et une autorisation par transition.
6. **Garde de seance validee** (`api_guard_meeting_not_validated`) empechant les modifications apres validation dans la plupart des endpoints.
7. **Prevention d'auto-modification** — l'admin ne peut pas se desactiver/supprimer/retrograder lui-meme.
8. **Attribution du role president** restreinte a l'admin uniquement avec revocation automatique du president precedent.
9. **Transition forcee** restreinte a l'admin avec journalisation des actions forcees.
10. **Validation du `APP_SECRET`** en mode production exigeant un secret de minimum 32 caracteres.
11. **Contraintes au niveau base de donnees** : contraintes `CHECK` sur les poids des bulletins, les statuts des motions, les roles de seance et la ponderation des votes.
12. **Securite transactionnelle** avec verrouillage de lignes `FOR UPDATE` sur les operations concurrentes.
13. **Validation des UUID** systematique sur les inputs critiques via `api_is_uuid()` et `api_require_uuid()`.
14. **Sanitisation du ORDER BY** via whitelist dans `listAuditEvents()`.
15. **Requetes preparees** utilisees de maniere coherente dans la quasi-totalite des repositories.

---

## MATRICE DE PRIORISATION DES REMEDIATIONS

### Phase 1 — Correctifs immediats (0-2 semaines)

| # | Action | Effort |
|---|--------|--------|
| 01 | Ajouter authentification au endpoint de vote (vote_token ou role voter) | Moyen |
| 02 | Remplacer l'upsert par un INSERT strict + gestion du conflit en 409 | Faible |
| 03 | Inverser le defaut : auth activee par defaut, `APP_AUTH_ENABLED=0` pour desactiver | Faible |
| 08 | Ajouter authentification + filtrage tenant/seance au endpoint SSE | Moyen |
| 05 | Implementer un jeton CSRF ou vote_token pour les endpoints voter/public | Moyen |

### Phase 2 — Renforcement (2-4 semaines)

| # | Action | Effort |
|---|--------|--------|
| 06 | Ajouter `AND tenant_id = :tid` a toutes les requetes sans isolation tenant | Moyen |
| 07 | Changer le role de consolidation de `auditor` a `operator` | Faible |
| 09 | Retirer `auditor` du endpoint `degraded_tally` | Faible |
| 04 | Remplacer la suppression des audit events par un marquage « reset » | Moyen |
| 12/13 | Ajouter `setMeetingContext()` + `requirePermission()` aux endpoints president | Moyen |
| 10 | Supprimer `setCurrentUser()` du code de production | Faible |
| 16 | Restreindre les permissions de la queue fichier WS + signer les evenements | Moyen |

### Phase 3 — Durcissement (4-8 semaines)

| # | Action | Effort |
|---|--------|--------|
| 11 | Ajouter auth au endpoint de resultats + tenant scoping | Faible |
| 14 | Valider le tenant pour les procedures d'urgence | Faible |
| 15 | Ajouter rate limiting au signalement d'incidents | Faible |
| 17 | Audit systematique des usages de `innerHTML` + fonction d'echappement | Moyen |
| 18 | Supprimer les details d'erreur des reponses 5xx en mode non-production | Faible |
| 24 | Deployer un header Content-Security-Policy | Moyen |
| 19-23 | Corrections mineures (hierarchie, heartbeat, permissions, SQL, secret) | Faible |

---

*Fin du rapport d'audit de securite.*
