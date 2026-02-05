# Roadmap AG-Vote — Travaux Validés

**Date de validation** : 5 février 2026
**Statut** : Liste définitive des travaux à réaliser

---

## Vue d'ensemble

| Catégorie | Effort total | Priorité |
|-----------|--------------|----------|
| Migration MVC | 28 jours | Fondation |
| Refactoring Workflow Opérateur | ~10 jours | P1-P3 |
| Exports | 14 jours | P2 |
| Invitations | 12 jours | P2 |
| UI/UX | ~17 jours | Critique → Nice-to-have |
| Fonctionnalités avancées | 46 jours | P2-P4 |
| **TOTAL** | **~127 jours** | - |

---

## 1. Migration MVC ✅ TERMINÉE

Objectif : Éliminer le SQL inline des endpoints, architecture 3 couches propre.

| Phase | Description | Effort | Statut |
|-------|-------------|--------|--------|
| 1 | Domaines simples (Members, Proxies, Invitations) | 3 jours | ✅ Fait |
| 2 | Meetings + Motions (29 endpoints) | 8 jours | ✅ Fait |
| 3 | Moteurs calcul (QuorumEngine, VoteEngine) | 4 jours | ✅ Fait |
| 4 | Domaines secondaires (55 endpoints) | 11 jours | ✅ Fait |
| 5 | Nettoyage + validation | 2 jours | ✅ Fait |

**Total : 28 jours** → **Complété le 5 février 2026**
- 24 repositories (AbstractRepository + 23 concrets)
- 0 SQL inline dans les endpoints API
- Nouveaux repos : AnalyticsRepository, WizardRepository

### Détail Phase 1 — Domaines simples
- `MemberRepository` : CRUD membres, import CSV
- `ProxyRepository` : Gestion procurations
- `InvitationRepository` : Tokens, envois

### Détail Phase 2 — Meetings + Motions
- `MeetingRepository` : 20 endpoints (CRUD, transitions, stats)
- `MotionRepository` : 9 endpoints (CRUD, résultats, reorder)

### Détail Phase 3 — Moteurs calcul
- `QuorumCalculationRepository` : Données brutes quorum
- `VoteCalculationRepository` : Données brutes votes
- Services gardent la logique de calcul

### Détail Phase 4 — Domaines secondaires
- `AttendanceRepository` : Présences
- `BallotRepository` : Bulletins
- `UserRepository` + `SystemRepository` : Admin
- `MeetingReportRepository` : Exports/rapports
- `DeviceRepository` : Devices/emergency
- `AuditEventRepository` : Journal audit
- `PolicyRepository` : Configuration quorum
- `NotificationRepository` : Notifications

---

## 2. Refactoring Workflow Opérateur

Objectif : Centraliser la gestion de séance dans `operator.htmx.html`.

| Phase | Description | Priorité | Statut |
|-------|-------------|----------|--------|
| 1.1 | Membres inline dans operator | P1 | ⬜ À faire |
| 1.2 | Présences inline (pointage rapide) | **P1 Haute** | ⬜ À faire |
| 1.3 | Résolutions inline (CRUD) | P2 | ⬜ À faire |
| 2 | Validations pré-freeze (issues_before_transition) | P1 | ⬜ À faire |
| 3 | Exports post-validation intégrés | P2 | ⬜ À faire |
| 4 | Simplification navigation sidebar | P3 | ⬜ À faire |

### Phase 1.1 — Membres inline
- Section membres dans operator avec recherche
- Import CSV inline
- Ajout rapide de membre

### Phase 1.2 — Présences inline (Priorité haute)
- Pointage rapide avec toggle présent/distant/excusé
- Compteurs temps réel
- Bulk actions (tous présents)

### Phase 1.3 — Résolutions inline
- CRUD résolutions sans changer de page
- Réordonner drag & drop
- Édition inline du titre

### Phase 2 — Validations pré-freeze
- `MeetingWorkflowService::issuesBeforeTransition()`
- Affichage des issues avant transition
- Blocage si issues critiques

### Phase 3 — Exports post-validation
- Section exports visible après validation
- Boutons PV PDF/CSV inline
- Envoi PV par email

### Phase 4 — Navigation simplifiée
- Sidebar réduite : Séances → Fiche Séance → Admin → Archives

---

## 3. Exports

Objectif : Support XLSX natif, imports étendus, templates personnalisables.

| Phase | Description | Effort | Statut |
|-------|-------------|--------|--------|
| 1 | Support XLSX natif (PhpSpreadsheet) | 3 jours | ⬜ À faire |
| 2 | Imports étendus (motions, présences, procurations CSV) | 4 jours | ⬜ À faire |
| 3 | Templates d'export personnalisables | 3 jours | ⬜ À faire |
| 4 | Rapports agrégés multi-séances | 4 jours | ⬜ À faire |

**Total : 14 jours**

### Phase 1 — XLSX natif
- Installation PhpSpreadsheet
- 6 endpoints export XLSX
- Export workbook multi-feuilles

### Phase 2 — Imports étendus
- `motions_import_csv.php`
- `attendances_import_csv.php`
- `proxies_import_csv.php`
- Templates CSV téléchargeables

### Phase 3 — Templates personnalisables
- Table `export_templates`
- Sélection colonnes, ordre, renommage
- Interface UI configuration

### Phase 4 — Rapports agrégés
- Participation annuelle
- Historique décisions
- Évolution pouvoir de vote
- Statistiques procurations/quorum

---

## 4. Invitations

Objectif : Templates email personnalisables, envoi programmé, métriques.

| Phase | Description | Effort | Statut |
|-------|-------------|--------|--------|
| 1 | Templates email personnalisables (15 variables) | 4 jours | ⬜ À faire |
| 2 | Envoi programmé + rappels automatiques | 5 jours | ⬜ À faire |
| 3 | Métriques et suivi (ouvertures, clics) | 3 jours | ⬜ À faire |

**Total : 12 jours**

### Phase 1 — Templates personnalisables
- Table `email_templates`
- 15+ variables (membre, séance, dates, etc.)
- Prévisualisation live
- Interface éditeur

### Phase 2 — Envoi programmé
- Table `email_queue`
- Table `reminder_schedules`
- Worker cron (process_email_queue.php)
- Rappels J-7, J-3, J-1

### Phase 3 — Métriques
- Pixel tracking ouverture
- Redirect tracking clics
- Dashboard métriques
- Taux envoi/délivré/ouvert/cliqué

---

## 5. UI/UX

| Action | Priorité | Effort | Statut |
|--------|----------|--------|--------|
| Merger design-system.css + ui.css | Critique | 2 jours | ⬜ À faire |
| Accessibilité (alt text, ARIA, skip links) | Critique | 2 jours | ⬜ À faire |
| Optimisation mobile (drawers, tables) | Important | 3 jours | ⬜ À faire |
| Web Components consolidation | Nice-to-have | 2 semaines | ⬜ À faire |

**Total : ~17 jours**

### Merger CSS
- Unifier design-system.css et ui.css
- Standardiser naming conventions
- Supprimer duplications

### Accessibilité
- Alt text sur toutes les images
- ARIA roles complets
- Skip links navigation
- Live regions notifications

### Optimisation mobile
- Drawers responsive (<380px)
- Tables scroll horizontal
- Navigation tactile

### Web Components
- Extraire composants réutilisables
- Documentation Storybook-like
- Tests composants

---

## 6. Fonctionnalités Avancées

| Priorité | Fonctionnalité | Effort | Statut |
|----------|----------------|--------|--------|
| P2 | WebSocket temps réel | 8 jours | ⬜ À faire |
| P3 | Séances récurrentes | 5 jours | ⬜ À faire |
| P3 | Champs personnalisés membres | 6 jours | ⬜ À faire |
| P4 | Application mobile PWA | 15 jours | ⬜ À faire |
| P4 | Mode hors-ligne | 12 jours | ⬜ À faire |

**Total : 46 jours**

### P2 — WebSocket temps réel
- Serveur Ratchet ou Swoole
- Événements : motion.opened, vote.cast, attendance.updated
- Fallback polling si WS indisponible
- Reconnexion automatique

### P3 — Séances récurrentes
- Colonne `recurrence_rule` (RRULE)
- Colonne `parent_meeting_id`
- Patterns : mensuel, trimestriel, annuel
- Duplication automatique résolutions

### P3 — Champs personnalisés
- Table `custom_fields`
- Table `custom_field_values`
- Types : text, number, date, select, boolean
- Formulaires dynamiques

### P4 — Application mobile PWA
- Service Worker cache
- Manifest installation
- Push notifications
- Expérience app-like

### P4 — Mode hors-ligne
- Cache données séance
- Queue votes offline
- Synchronisation retour réseau
- Gestion conflits

---

## Éléments Exclus

Les éléments suivants ne font **PAS** partie de la roadmap :

- ~~Multi-langue (i18n)~~
- ~~Canal SMS~~
- ~~Tests E2E~~
- ~~CI/CD pipeline~~
- ~~2FA~~
- ~~SSO (SAML/OIDC)~~
- ~~Export données personnelles RGPD~~
- ~~Couverture tests >80%~~

---

## Priorisation Suggérée

### Sprint 1 (2-3 semaines) — Fondations
1. Migration MVC Phase 1 (domaines simples)
2. Merger CSS (critique)
3. Présences inline (P1 haute)

### Sprint 2 (2-3 semaines) — Cœur métier
4. Migration MVC Phase 2 (Meetings + Motions)
5. Validations pré-freeze
6. Membres inline

### Sprint 3 (2-3 semaines) — Exports & Invitations
7. Support XLSX
8. Templates email
9. Imports étendus

### Sprint 4+ — Avancé
10. Migration MVC Phases 3-5
11. WebSocket
12. Accessibilité complète
13. Fonctionnalités P3-P4

---

## Suivi d'avancement

| Catégorie | Complété | En cours | À faire |
|-----------|----------|----------|---------|
| Migration MVC | 0/5 | - | 5/5 |
| Workflow Opérateur | 0/6 | - | 6/6 |
| Exports | 0/4 | - | 4/4 |
| Invitations | 0/3 | - | 3/3 |
| UI/UX | 0/4 | - | 4/4 |
| Fonctionnalités | 0/5 | - | 5/5 |

**Dernière mise à jour** : 5 février 2026
