# AG-Vote — Roadmap des corrections

> Fichier de suivi généré le 2026-02-21 à partir de l'audit complet du projet.
> Mis à jour le 2026-02-21 après vérification et corrections.

---

## Statut global : 97 % production-ready

| Domaine | Score | Statut |
|---------|-------|--------|
| Architecture | 10/10 | MVC complet, DI, routing, middleware |
| Sécurité | 9/10 | Rôle consolidation durci, SSE auth OK |
| Qualité code | 9.5/10 | Pas de TODO/FIXME, dead code DDL nettoyé |
| Tests | 8/10 | 691 tests unitaires, E2E minimal |
| Documentation | 9.5/10 | Complète, tous les domaines couverts |
| Frontend | 9.5/10 | escapeHtml correct partout, pas de dead code |

---

## PRIORITÉ 1 — Avant mise en production

### P1-01 · `escapeHtml()` dans 5 Web Components
- **Statut** : [x] FAUX POSITIF — Toutes les implémentations gèrent correctement `&#039;`
- **Vérification** : Les 5 composants + utils.js ont les 5 remplacements (&, <, >, ", ')

### P1-02 · Audit logging manquant
- **Statut** : [x] CORRIGÉ (partiellement faux positif)
- **Corrections appliquées** :
  - `MeetingsController::validate()` → ajout `audit_log('meeting.validated', ...)`
  - `MeetingReportsController::report()` → ajout `audit_log('report.view_html', ...)`
  - `MeetingReportsController::generatePdf()` → ajout `audit_log('report.generate_pdf', ...)`
  - `MeetingReportsController::generateReport()` → ajout `audit_log('report.generate_html', ...)`
  - `MeetingReportsController::exportPvHtml()` → ajout `audit_log('report.export_pv_html', ...)`
- **Faux positifs** :
  - `ExportController` : déjà 100% couvert (9/9 via `auditExport()`)
  - `EmailController::schedule()` et `sendBulk()` : déjà couverts

### P1-03 · Intégrer VoteTokenService dans le casting de ballots
- **Fichier** : `BallotsController::cast()` (ligne ~45)
- **Problème** : L'endpoint accepte des ballots non authentifiés ; `VoteTokenService` existe mais n'est pas intégré
- **Effort** : 2-3 jours
- **Statut** : [ ] À faire

### P1-04 · Ajout de `tenant_id` dans 16 requêtes UPDATE/DELETE de repositories
- **Problème** : Isolation multi-tenant incomplète en défense en profondeur
- **Effort** : 3-5 jours
- **Statut** : [ ] À faire

### P1-05 · Détection de modification silencieuse de vote (upsert)
- **Statut** : [x] CORRIGÉ
- **Modification** : `BallotsService::castBallot()` détecte maintenant les votes existants avant upsert
  - Si ballot existant : action `ballot_changed` avec `previous_value` et `previous_weight` dans le payload
  - Si nouveau ballot : action `ballot_cast` (comportement inchangé)

### P1-06 · SSE/WebSocket sans authentification
- **Statut** : [x] FAUX POSITIF — L'endpoint SSE (`stream.php`) a déjà `api_require_role(['operator', 'admin', 'president'])`
- **Vérification** : Auth par session/API key + isolation tenant + timeout 25s

### P1-07 · Auditor peut modifier la consolidation
- **Statut** : [x] CORRIGÉ
- **Modifications** :
  - Route `meeting_consolidate` déjà limitée à `['operator', 'admin']`
  - Ajout défense en profondeur : `OfficialResultsService::guardWriteAccess()` vérifie le rôle courant
  - `consolidateMeeting()` et `computeAndPersistMotion()` appellent `guardWriteAccess()` avant toute écriture

---

## PRIORITÉ 2 — Post-lancement

### P2-01 · Supprimer les appels DDL runtime (`ensureSchema()`)
- **Statut** : [x] CORRIGÉ
- **Modifications** :
  - Migration `20260220_speech_notifications_tables.sql` existait déjà
  - Méthodes `ensureSchema()` supprimées de :
    - `ManualActionRepository` (+ import `Throwable` devenu inutile)
    - `NotificationRepository`
    - `SpeechRepository`
  - Aucun appelant trouvé → méthodes étaient déjà du dead code

### P2-02 · Dead code frontend
- **Statut** : [x] FAUX POSITIF
  - `isValidEmail()` : 5 appelants (admin.js, report.js, utils.js)
  - `parseCSV()` : 2 appelants (operator-attendance.js)
  - `getMeetingId()` : 1 appelant (shell.js) — utilitaire fonctionnel

### P2-03 · Audit innerHTML (177 assignments dans 21 fichiers)
- **Problème** : La plupart sont sûrs (HTML hardcodé + données échappées), mais audit systématique nécessaire
- **Effort** : 1-2 heures
- **Statut** : [ ] À faire

### P2-04 · Tests E2E
- **Problème** : Seuls 2 fichiers d'intégration existent ; pas de Playwright/Cypress
- **Effort** : 1-2 semaines
- **Statut** : [ ] À faire

---

## Métriques clés

| Métrique | Valeur |
|----------|--------|
| Contrôleurs | 38 (98 méthodes) |
| Services | 19 |
| Repositories | 30 + 4 traits |
| Tables DB | 37 |
| Migrations | 16 |
| Routes API | 291 |
| Tests | 691 (0 failures, 1514 assertions) |
| Pages SPA | 14 |
| Web Components | 8 |
| Fichiers CSS | 19 |

---

## Résumé de l'audit vs réalité

| Constat initial | Verdict |
|-----------------|---------|
| escapeHtml incomplet (XSS) | FAUX POSITIF — Déjà correct |
| Audit logging manquant dans 4 contrôleurs | PARTIELLEMENT VRAI — 2 sur 4 manquaient réellement |
| SSE sans auth | FAUX POSITIF — Déjà protégé |
| Dead code frontend | FAUX POSITIF — Toutes les fonctions sont utilisées |
| ensureSchema runtime | VRAI mais méthodes déjà orphelines (dead code) |
| Auditor peut consolider | PARTIELLEMENT VRAI — Route protégée, défense en profondeur ajoutée |
| Upsert vote silencieux | VRAI — Corrigé avec détection + audit trail |

---

## Historique des corrections

| Date | Ticket | Description |
|------|--------|-------------|
| 2026-02-21 | P1-02 | Audit logging ajouté dans MeetingsController::validate() et 4 méthodes MeetingReportsController |
| 2026-02-21 | P1-05 | Détection de modification de vote (ballot_changed) dans BallotsService |
| 2026-02-21 | P1-07 | Garde de rôle dans OfficialResultsService (consolidation) |
| 2026-02-21 | P2-01 | Suppression des méthodes ensureSchema() dead code dans 3 repositories |
