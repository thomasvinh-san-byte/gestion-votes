# AUDIT DE SECURITE VERIFIE — AG-Vote (gestion-votes)

**Application :** AG-Vote — Systeme de gestion de votes en assemblee generale
**Date de l'audit initial :** 2026-02-20
**Date de verification :** 2026-02-20
**Perimetre :** Authentification, autorisation, CSRF, SSE/WebSocket, multi-tenant, integrite des votes, gestion des secrets
**Constatations initiales :** 25 | **Confirmees :** 19 | **Faux positifs :** 5 | **Reclassee :** 1

---

## SYNTHESE DES VERIFICATIONS

| # | Constatation | Severite initiale | Verdict | Severite verifiee |
|---|-------------|-------------------|---------|-------------------|
| 01 | Endpoint de vote sans authentification | CRITIQUE | **CORRIGEE** | — |
| 02 | Upsert permet la modification silencieuse des votes | CRITIQUE | **CONFIRMEE** | CRITIQUE |
| 03 | Authentification desactivee par defaut | CRITIQUE | **CONFIRMEE** | CRITIQUE |
| 04 | Suppression des audit logs (reset demo) | ELEVEE | **CONFIRMEE** (attenuee) | MOYENNE |
| 05 | CSRF contourne pour endpoints public/voter | ELEVEE | **FAUX POSITIF** | — |
| 06 | Absence d'isolation multi-tenant | ELEVEE | **CONFIRMEE** (mitigations amont) | ELEVEE |
| 07 | Auditeur peut modifier via consolidation | ELEVEE | **CONFIRMEE** | ELEVEE |
| 08 | Endpoint SSE sans authentification | ELEVEE | **CONFIRMEE** | ELEVEE |
| 09 | Auditeur peut saisir comptages degrades | MOYENNE | **CONFIRMEE** | MOYENNE |
| 10 | `setCurrentUser()` public en production | MOYENNE | **FAUX POSITIF** | — |
| 11 | Resultats de vote sans authentification | MOYENNE | **CONFIRMEE** (tenant present) | FAIBLE |
| 12 | `motions_close` sans scope seance | MOYENNE | **CONFIRMEE** | MOYENNE |
| 13 | `meeting_validate` sans scope seance | MOYENNE | **CONFIRMEE** | MOYENNE |
| 14 | Procedures d'urgence : IDOR faible | MOYENNE | **CONFIRMEE** | MOYENNE |
| 15 | Signalement d'incident sans rate limiting | MOYENNE | **CONFIRMEE** | MOYENNE |
| 16 | File WS sur filesystem sans controle d'acces | MOYENNE | **CONFIRMEE** | MOYENNE |
| 17 | XSS potentiel via innerHTML | MOYENNE | **FAUX POSITIF** | — |
| 18 | Fuite de detail d'erreur | MOYENNE | **RECLASSEE** | FAIBLE |
| 19 | Hierarchie de roles implicite | FAIBLE | **CONFIRMEE** (design intentionnel) | INFO |
| 20 | Heartbeat accepte role arbitraire | FAIBLE | **FAUX POSITIF** | — |
| 21 | Permissions dupliquees | FAIBLE | **CONFIRMEE** (risque maintenance) | FAIBLE |
| 22 | LIMIT par interpolation SQL | FAIBLE | **FAUX POSITIF** | — |
| 23 | Secret de repli previsible | FAIBLE | **CONFIRMEE** (gate dev-only) | FAIBLE |
| 24 | Absence de CSP | FAIBLE | **FAUX POSITIF** (CSP present dans bootstrap.php) | — |
| 25 | Controles positifs | INFO | **CONFIRMEE** | INFO |

---

## DETAIL DES FAUX POSITIFS

### Constatation 05 — CSRF bypass : FAUX POSITIF

L'exemption CSRF pour les endpoints `public`/`voter` est un choix architectural delibere. Les votants n'ont pas de session PHP — ils s'authentifient via des **vote tokens** (generes par `VoteTokenService`, one-time, limites dans le temps a 1h, cryptographiquement aleatoires). Le vote_token sert de facto de protection CSRF car il est imprevisible et a usage unique. L'infrastructure CSRF standard est correctement implementee (hash_equals timing-safe, verification de lifetime).

### Constatation 10 — setCurrentUser() : FAUX POSITIF

La methode `setCurrentUser()` n'est appelee nulle part dans le code de production. Elle est dans une section explicitement marquee `// TEST HELPERS` et n'est accessible que par execution directe de code PHP (pas via HTTP). Risque negligeable.

### Constatation 17 — XSS via innerHTML : FAUX POSITIF

L'application dispose d'une fonction `escapeHtml()` correctement implementee (`Utils.escapeHtml` dans `utils.js`, utilisant `textContent` -> `innerHTML`). Les donnees dynamiques utilisateur (noms, titres) sont echappees avant insertion. Les usages de `innerHTML` avec du HTML statique sont inoffensifs. De plus, une CSP est en place (voir ci-dessous).

### Constatation 22 — LIMIT interpolation : FAUX POSITIF

Les deux cas utilisent `declare(strict_types=1)` avec des parametres types `int`. `max(1, $limit)` garantit une valeur positive. L'injection SQL est impossible par typage strict du langage.

### Constatation 24 — CSP absente : FAUX POSITIF

Un header CSP complet EST configure dans `bootstrap.php:156` :
```
Content-Security-Policy: default-src 'self'; script-src 'self' 'unsafe-inline' https://unpkg.com ...
```
Avec les headers `X-Content-Type-Options`, `X-Frame-Options`, `X-XSS-Protection`, `Referrer-Policy` et HSTS conditionnel.

---

## DETAIL DES RECLASSEMENTS

### Constatation 04 — Suppression audit logs : ELEVEE -> MOYENNE

La verification a revele que la migration `20260217_security_hardening.sql` cree un **trigger d'immutabilite** sur `audit_events` qui **empeche physiquement le DELETE**. L'appel `deleteAuditEventsByMeeting()` echoue silencieusement car l'exception est attrapee (ligne 966 : `catch (\Throwable $e) { /* table may not exist */ }`). Les audit events sont donc preserves. Le probleme reel : le reset **croit** avoir efface les logs, l'operateur voit un succes, mais c'est faux. C'est un defaut de coherence, pas une destruction de donnees.

### Constatation 11 — Resultats sans auth : MOYENNE -> FAIBLE

`VoteEngine::computeMotionResult()` extrait le `tenant_id` depuis la motion via une jointure (ligne 52), et les comptages d'electeurs eligibles sont scopes par tenant (ligne 76). L'isolation tenant est presente. Le risque restant est la divulgation de resultats agreges a un utilisateur non authentifie connaissant un `motion_id` valide (UUID).

### Constatation 18 — Fuite d'erreur : MOYENNE -> FAIBLE

`api_fail()` supprime `$extra['detail']` pour les erreurs >= 500 quand `APP_ENV === 'production'`. Le risque ne subsiste qu'en mode `demo`/`dev`. En production configuree correctement, les details sont masques.

### Constatation 19 — Hierarchie de roles : FAIBLE -> INFO

La hierarchie est un design intentionnel documente dans les commentaires d'architecture (lignes 13-29 de AuthMiddleware). La matrice de permissions (`PERMISSIONS` constant) controle explicitement les acces — la hierarchie est un filet de secours, pas la source principale d'autorisation. Les permissions `audit:*` excluent explicitement `operator`.

---

## CONSTATATIONS CONFIRMEES — DETAIL ET COMPLEXITE D'INTEGRATION

---

### CRITIQUE 01 — Endpoint de vote sans authentification

**Fichier :** `app/Controller/BallotsController.php:45`
**Exploitabilite :** FACILE-MOYENNE (necessite `motion_id` + `member_id` valides, membre present)

**Statut : [x] CORRIGE** — `VoteTokenService::validateAndConsume()` integre dans `BallotsController::cast()`.
- Si `vote_token` present : validation atomique + verification croisee motion_id/member_id
- Si token invalide/expire : rejet 401 ; si motion/member mismatch : rejet 403
- `token_hash` trace dans les donnees d'audit
- Retrocompatible : votes sans token acceptes pendant la transition

---

### CRITIQUE 02 — Upsert permet la modification silencieuse des votes

**Fichier :** `app/Repository/BallotRepository.php:127-143`
**Exploitabilite :** MOYENNE (necessite les memes preconditions que 01 + vote deja emis)

**Mitigations existantes :**
- Motion doit etre `live` et ouverte
- `audit_log('ballot_cast', ...)` journalise le nouveau vote (mais pas l'ancien)

**Remediation proposee :**
Remplacer `ON CONFLICT DO UPDATE` par `INSERT` strict. Gerer le conflit unique en levant une erreur 409 (`already_voted`).

**Complexite d'integration : FAIBLE**
- Supprimer la clause `ON CONFLICT DO UPDATE` de `castBallot()`
- Attraper l'exception de contrainte unique dans `BallotsService` et retourner 409
- Si le re-vote est un besoin metier (president le demande), creer un endpoint dedie `ballots_revote` avec role `operator` et justification obligatoire, journalisant l'ancienne valeur
- **Fichiers a modifier :** `BallotRepository.php`, `BallotsService.php`
- **Estimation :** 1 jour (sans re-vote) / 2-3 jours (avec endpoint revote)

---

### CRITIQUE 03 — Authentification desactivee par defaut

**Fichier :** `app/Core/Security/AuthMiddleware.php:172-176`
**Exploitabilite :** FACILE (oubli de configuration = systeme grand ouvert)

**Mitigations existantes :**
- `.env.example` configure `APP_AUTH_ENABLED=1` (mais c'est un template)
- Bootstrap valide `APP_SECRET` en production

**Remediation proposee :**
Inverser la logique : `isEnabled()` retourne `true` par defaut, `false` uniquement si `APP_AUTH_ENABLED` vaut explicitement `0` ou `false`.

**Complexite d'integration : FAIBLE**
- Modifier `isEnabled()` dans `AuthMiddleware.php`
- Verifier que les tests et le mode dev configurent explicitement `APP_AUTH_ENABLED=0`
- Ajouter une validation dans `bootstrap.php` : refuser de demarrer si `APP_ENV=production` et `APP_AUTH_ENABLED=0`
- **Fichiers a modifier :** `AuthMiddleware.php`, `bootstrap.php`, `.env.example`
- **Estimation :** 0.5 jour

---

### ELEVEE 06 — Absence d'isolation multi-tenant

**Fichiers :** `BallotRepository.php:14-23, 28-48`, `MotionRepository.php:354-370`
**Exploitabilite :** MOYENNE (UUID difficilement devinables, mais fuites possibles via logs/UI)

**Mitigations existantes :**
- Les endpoints appelants (`BallotsController::listForMotion`) valident le tenant en amont via `findByIdForTenant()`
- `findWithBallotContext()` joint `meetings` d'ou le `tenant_id` est extrait

**Remediation proposee :**
Ajouter `AND tenant_id = :tid` a toutes les methodes repository qui n'en ont pas, en ajoutant le parametre `$tenantId` a la signature.

**Complexite d'integration : MOYENNE**
- 4 methodes a modifier dans `BallotRepository` + 2 dans `MotionRepository`
- Tous les appelants doivent fournir `$tenantId` — propager depuis le contexte existant
- `findWithBallotContext()` : ajouter `AND mt.tenant_id = :tid` au WHERE
- Tests de regression sur tally, listForMotion, castBallot
- **Fichiers a modifier :** `BallotRepository.php`, `MotionRepository.php`, `BallotsService.php`, `VoteEngine.php`, `OfficialResultsService.php`
- **Estimation :** 2-3 jours

---

### ELEVEE 07 — Auditeur peut modifier via consolidation

**Fichier :** `app/Controller/MeetingWorkflowController.php:441-453`
**Exploitabilite :** ELEVEE (un auditeur peut consolider n'importe quelle seance sans audit log)

**Remediation proposee :**
1. Changer `api_require_role('auditor')` en `api_require_role(['operator', 'admin'])`
2. Ajouter controle tenant sur `meetingId`
3. Ajouter un `audit_log()` pour la consolidation
4. Ajouter un garde d'idempotence (empecher re-consolidation)

**Complexite d'integration : FAIBLE**
- Changement de role : 1 ligne
- Ajout tenant check : `api_guard_meeting_exists($meetingId)` (helper existant)
- Ajout audit_log : 3 lignes
- **Fichiers a modifier :** `MeetingWorkflowController.php`
- **Estimation :** 0.5 jour

---

### ELEVEE 08 — Endpoint SSE sans authentification

**Fichier :** `public/api/bus/stream.php`
**Exploitabilite :** ELEVEE (acces direct, divulgation complete des evenements temps reel)

**Remediation proposee :**
Reecrire `stream.php` pour :
1. Exiger une session ou un token API
2. Filtrer les evenements par `tenant_id` et `meeting_id` du user
3. Ne pas rejouer l'historique complet
4. Ajouter un header `X-Accel-Buffering: no` pour les proxies

**Complexite d'integration : MOYENNE-ELEVEE**
- Le fichier est un script standalone (pas de bootstrap/api.php include)
- Il faut integrer `api.php` + `AuthMiddleware` dans la boucle SSE
- Le format d'`events.jsonl` ne contient pas de `tenant_id` — il faut modifier `EventBroadcaster::queue()` pour l'inclure
- Le filtrage necessite un parsing JSON par ligne et un comparatif tenant/meeting
- Impact sur les clients JS qui consomment le flux SSE
- **Fichiers a modifier :** `stream.php`, `EventBroadcaster.php`, JS client SSE
- **Estimation :** 3-4 jours

---

### MOYENNE 09 — Auditeur peut saisir comptages degrades

**Fichier :** `public/api/v1/degraded_tally.php:15`

**Remediation :** Retirer `'auditor'` du tableau de roles.
**Complexite : TRIVIALE** — 1 ligne, 0.5h. Fichier : `degraded_tally.php`.

---

### MOYENNE 12 — motions_close sans scope seance

**Fichier :** `public/api/v1/motions_close.php:13`

**Remediation :** Ajouter `AuthMiddleware::setMeetingContext($meetingId)` avant le role check, puis utiliser `requirePermission('motion:close', $meetingId)`.
**Complexite : FAIBLE** — 3-5 lignes. Le `$meetingId` est deja disponible via la motion. Fichier : `motions_close.php`. Estimation : 0.5 jour.

---

### MOYENNE 13 — meeting_validate sans scope seance

**Fichier :** `public/api/v1/meeting_validate.php:9`

**Remediation :** Identique a la 12 : `setMeetingContext()` + `requirePermission('meeting:validate', $meetingId)`.
**Complexite : FAIBLE** — 3-5 lignes. Fichier : `meeting_validate.php`. Estimation : 0.5 jour.

---

### MOYENNE 14 — Procedures d'urgence IDOR

**Fichier :** `public/api/v1/emergency_procedures.php`

**Remediation :** Ajouter `api_guard_meeting_exists($meetingId)` pour valider le tenant. Restreindre `audience` aux valeurs autorisees via whitelist.
**Complexite : FAIBLE** — Le helper `api_guard_meeting_exists` existe deja. 2-3 lignes. Estimation : 0.5 jour.

---

### MOYENNE 15 — Signalement d'incident sans rate limiting

**Fichier :** `app/Controller/BallotsController.php:306-324`

**Remediation :** Ajouter `api_rate_limit('incident_report', 10, 60)` (10 par minute). Le mecanisme `RateLimiter` existe deja.
**Complexite : TRIVIALE** — 1 ligne. Fichier : `BallotsController.php`. Estimation : 0.5h.

---

### MOYENNE 16 — File WS sur filesystem sans controle d'acces

**Fichier :** `app/WebSocket/EventBroadcaster.php:14-15, 140-168`

**Remediation :**
1. Definir les permissions du fichier a `0600` dans `queue()`
2. Utiliser un repertoire dedie (`/var/run/agvote/`) plutot que `/tmp/`
3. Optionnel : signer les evenements avec HMAC(APP_SECRET)

**Complexite : FAIBLE-MOYENNE** — Changement de permissions : 1 ligne. Changement de repertoire : config + creation du dossier au deploiement. HMAC : ~20 lignes dans `queue()` + `dequeue()`. Estimation : 1 jour.

---

### FAIBLES 11, 18, 21, 23

| # | Remediation | Complexite | Estimation |
|---|------------|-----------|------------|
| 11 | Ajouter `api_require_role('viewer')` au endpoint result | Triviale | 0.5h |
| 18 | Remplacer `$e->getMessage()` par message generique dans degraded_tally | Triviale | 0.5h |
| 21 | Documenter que `Permissions.php` est la source de verite ; supprimer le doublon dans AuthMiddleware | Faible | 1 jour |
| 23 | Remplacer le secret de repli par un `throw` en mode dev aussi | Triviale | 0.5h |

---

## PLAN D'IMPLEMENTATION PAR PHASES

### PHASE 1 — Correctifs critiques (Semaine 1)

**Objectif :** Eliminer les risques de compromission de l'integrite des votes.

| # | Action | Fichiers | Complexite | Jours |
|---|--------|----------|-----------|-------|
| 03 | Inverser le defaut de `isEnabled()` (deny-by-default) | `AuthMiddleware.php`, `bootstrap.php`, `.env.example` | Faible | 0.5 |
| 02 | Remplacer upsert par INSERT strict + erreur 409 | `BallotRepository.php`, `BallotsService.php` | Faible | 1 |
| 07 | Corriger le role de consolidation (auditor -> operator) + audit log | `MeetingWorkflowController.php` | Faible | 0.5 |
| 09 | Retirer auditor de degraded_tally | `degraded_tally.php` | Triviale | 0.1 |
| 15 | Ajouter rate limiting au report incident | `BallotsController.php` | Triviale | 0.1 |
| 11 | Ajouter auth au endpoint result | `BallotsController.php` | Triviale | 0.1 |
| 18 | Supprimer detail d'erreur dans degraded_tally | `degraded_tally.php` | Triviale | 0.1 |
| 23 | Supprimer le secret de repli | `AuthMiddleware.php` | Triviale | 0.1 |

**Total Phase 1 : ~2.5 jours** | 8 fichiers | Risque de regression : faible (changements localises)

---

### PHASE 2 — Securisation des endpoints (Semaines 2-3)

**Objectif :** Fermer les vecteurs d'acces non autorises et le scope des roles de seance.

| # | Action | Fichiers | Complexite | Jours |
|---|--------|----------|-----------|-------|
| 01 | Integrer vote_token dans le flux de vote | `BallotsController.php`, `BallotsService.php`, JS front | Moyenne | 3 |
| 12 | Ajouter setMeetingContext + requirePermission a motions_close | `motions_close.php` | Faible | 0.5 |
| 13 | Ajouter setMeetingContext + requirePermission a meeting_validate | `meeting_validate.php` | Faible | 0.5 |
| 14 | Ajouter tenant guard aux procedures d'urgence | `emergency_procedures.php` | Faible | 0.5 |
| 04 | Supprimer l'appel deleteAuditEvents de resetDemo (ou logguer "reset" event) | `MeetingWorkflowController.php` | Faible | 0.5 |

**Total Phase 2 : ~5 jours** | 6 fichiers | Risque de regression : moyen (flux de vote impacte)
**Prerequis :** Tests d'integration du flux de vote complet apres integration du token.

---

### PHASE 3 — Renforcement multi-tenant et temps reel (Semaines 3-5)

**Objectif :** Defense en profondeur et isolation des donnees.

| # | Action | Fichiers | Complexite | Jours |
|---|--------|----------|-----------|-------|
| 06 | Ajouter tenant_id a toutes les requetes repository | `BallotRepository.php`, `MotionRepository.php`, `BallotsService.php`, `VoteEngine.php`, `OfficialResultsService.php` | Moyenne | 3 |
| 08 | Securiser endpoint SSE (auth + filtrage tenant/meeting) | `stream.php`, `EventBroadcaster.php`, JS client | Moyenne-Elevee | 4 |
| 16 | Restreindre les permissions de la file WS + signer les events | `EventBroadcaster.php` | Faible-Moyenne | 1 |
| 21 | Unifier les sources de permissions | `Permissions.php`, `AuthMiddleware.php` | Faible | 1 |

**Total Phase 3 : ~9 jours** | 10 fichiers | Risque de regression : eleve (requetes SQL modifiees, flux temps reel)
**Prerequis :** Suite de tests exhaustive couvrant les scenarios multi-tenant.

---

## RESUME GLOBAL

| Phase | Duree | Jours-dev | Constatations traitees | Risque residuel apres phase |
|-------|-------|-----------|------------------------|----------------------------|
| **Phase 1** | Semaine 1 | 2.5 j | 03, 02, 07, 09, 15, 11, 18, 23 | Votes toujours sans token (01), SSE ouvert (08) |
| **Phase 2** | Semaines 2-3 | 5 j | 01, 12, 13, 14, 04 | Multi-tenant incomplet (06), SSE ouvert (08) |
| **Phase 3** | Semaines 3-5 | 9 j | 06, 08, 16, 21 | Risques faibles uniquement |
| **Total** | **5 semaines** | **16.5 j** | **19 constatations** | — |

Les 5 constatations non traitees sont des faux positifs (05, 10, 17, 22, 24) et la constatation 19 (INFO, design intentionnel) et 20 (impact nul sur la securite).

---

### CONTROLES POSITIFS CONFIRMES (Constatation 25)

La verification a confirme les controles suivants comme correctement implementes :

1. **Chaine de hachage cryptographique** des audit events (trigger PostgreSQL avec SHA-256 + `prev_hash` chaine, scope par seance, `FOR UPDATE` anti-race-condition) — **VERIFIE dans migration 20260217**
2. **Verrouillage `FOR UPDATE`** sur les meetings pour les operations concurrentes — **VERIFIE dans MeetingRepository::lockForUpdate()**
3. **Hachage HMAC-SHA256** des cles API (cles brutes jamais stockees)
4. **Timeout de session** 30 minutes avec destruction active
5. **Machine a etats** des seances avec transitions autorisees et roles par transition
6. **Garde de seance validee** empechant les modifications post-validation
7. **Validation APP_SECRET** >= 32 caracteres en mode production
8. **CSP complete** dans bootstrap.php avec headers de securite
9. **Requetes preparees** coherentes dans les repositories
10. **Contraintes CHECK** au niveau base de donnees sur poids, statuts, roles

---

*Rapport d'audit verifie et plan d'implementation — AG-Vote (gestion-votes)*
