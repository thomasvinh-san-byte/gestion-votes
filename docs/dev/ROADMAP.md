# Roadmap AG-Vote — Travaux Validés

**Date de validation** : 5 février 2026
**Dernière mise à jour** : 5 février 2026
**Statut** : Liste définitive des travaux à réaliser

---

## Vue d'ensemble

| Catégorie | Effort total | Priorité | Statut |
|-----------|--------------|----------|--------|
| Migration MVC | 28 jours | Fondation | ✅ Terminé |
| Refactoring Workflow Opérateur | ~10 jours | P1-P3 | ✅ Terminé |
| Exports | 14 jours | P2 | ✅ Terminé |
| Invitations | 12 jours | P2 | ✅ Backend terminé |
| UI/UX | ~17 jours | Critique → Nice-to-have | ✅ Terminé |
| Fonctionnalités avancées | 46 jours | P2-P4 | ⬜ À faire |
| **TOTAL** | **~127 jours** | - | ~65% complété |

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
- 27 repositories (AbstractRepository + 26 concrets)
- 0 SQL inline dans les endpoints API
- Audit MVC complet : aucune requête directe db() ou PDO hors repositories

### Repositories implémentés
- AbstractRepository (base)
- AgendaRepository, AggregateReportRepository, AnalyticsRepository
- AttendanceRepository, BallotRepository, DeviceRepository
- EmailEventRepository, EmailQueueRepository, EmailTemplateRepository
- EmergencyProcedureRepository, ExportTemplateRepository, FragmentRepository
- InvitationRepository, ManualActionRepository, MeetingRepository
- MemberGroupRepository, MemberRepository, MotionRepository
- NotificationRepository, PolicyRepository, ProxyRepository
- ReminderScheduleRepository, SpeechRepository, UserRepository
- VoteTokenRepository, WizardRepository

---

## 2. Refactoring Workflow Opérateur ✅ TERMINÉ

Objectif : Centraliser la gestion de séance dans `operator.htmx.html`.

| Phase | Description | Priorité | Statut |
|-------|-------------|----------|--------|
| 1.1 | Membres inline dans operator | P1 | ✅ Fait |
| 1.2 | Présences inline (pointage rapide) | **P1 Haute** | ✅ Fait |
| 1.3 | Résolutions inline (CRUD) | P2 | ✅ Fait |
| 2 | Validations pré-freeze (issues_before_transition) | P1 | ✅ Fait |
| 3 | Exports post-validation intégrés | P2 | ✅ Fait |
| 4 | Simplification navigation sidebar | P3 | ✅ Fait |

**Complété le 5 février 2026**
- Interface opérateur avec 6 onglets (Paramètres, Résolutions, Présences, Parole, Vote, Résultats)
- MeetingWorkflowService avec validations pré-transition Helios-style
- Exports intégrés dans l'onglet Résultats

---

## 3. Exports ✅ TERMINÉ

Objectif : Support XLSX natif, imports étendus, templates personnalisables.

| Phase | Description | Effort | Statut |
|-------|-------------|--------|--------|
| 1 | Support XLSX natif (PhpSpreadsheet) | 3 jours | ✅ Fait |
| 2 | Imports étendus (motions, présences, procurations CSV) | 4 jours | ✅ Fait |
| 3 | Templates d'export personnalisables | 3 jours | ✅ Fait |
| 4 | Rapports agrégés multi-séances | 4 jours | ✅ Fait |

**Total : 14 jours** — **Complété le 5 février 2026**

### Phase 3 — Templates personnalisables
- Table `export_templates` (migration 007)
- ExportTemplateRepository avec colonnes disponibles par type
- API CRUD complète : `/api/v1/export_templates.php`
- 6 types supportés : attendance, votes, members, motions, audit, proxies

### Phase 4 — Rapports agrégés
- AggregateReportRepository avec 5 types de rapports
- Types : participation, decisions, voting_power, proxies, quorum
- API : `/api/v1/reports_aggregate.php`
- Formats : JSON, CSV, XLSX
- Filtres : date range, meeting IDs spécifiques

---

## 4. Invitations ✅ TERMINÉ (Backend)

Objectif : Templates email personnalisables, envoi programmé, métriques.

| Phase | Description | Effort | Statut |
|-------|-------------|--------|--------|
| 1 | Templates email personnalisables (16 variables) | 4 jours | ✅ Backend fait |
| 2 | Envoi programmé + rappels automatiques | 5 jours | ✅ Backend fait |
| 3 | Métriques et suivi (ouvertures, clics) | 3 jours | ✅ Backend fait |

**Total : 12 jours** — Backend 100% complet

**Implémenté:**
- EmailTemplateRepository, EmailQueueRepository, ReminderScheduleRepository, EmailEventRepository
- EmailTemplateService (16 variables, preview, validation)
- EmailQueueService (processQueue, scheduleInvitations, processReminders)
- 7 endpoints API (templates, scheduling, reminders, stats, tracking)
- Worker script process_email_queue.php (cron-ready)
- Pixel tracking + redirect tracking

---

## 5. UI/UX ✅ TERMINÉ

| Action | Priorité | Effort | Statut |
|--------|----------|--------|--------|
| Merger design-system.css + ui.css | Critique | 2 jours | ✅ Fait |
| Accessibilité (alt text, ARIA, skip links) | Critique | 2 jours | ✅ Fait |
| Optimisation mobile (drawers, tables) | Important | 3 jours | ✅ Fait |
| Web Components consolidation | Nice-to-have | 2 semaines | ✅ Fait |

**Total : ~17 jours** — **Complété le 5 février 2026**

### Merger CSS
- design-system.css unifié (54KB)
- Variables CSS standardisées
- Duplications supprimées

### Accessibilité
- Skip links implémentés (.skip-link)
- ARIA roles complets (role="main", landmarks)
- Live regions pour notifications
- Focus management pour modals
- prefers-reduced-motion supporté

### Optimisation mobile
- Breakpoint small phone (< 480px)
- Touch targets 44px minimum
- Navigation mobile toggle
- Safe area insets pour notched devices
- Tables responsive scroll horizontal

### Web Components (7 composants)
- `ag-badge.js` - Badges avec états
- `ag-kpi.js` - Indicateurs KPI
- `ag-quorum-bar.js` - Barre de quorum animée
- `ag-spinner.js` - Spinner de chargement
- `ag-toast.js` - Notifications toast
- `ag-vote-button.js` - Boutons de vote
- `index.js` - Point d'entrée

---

## 6. Fonctionnalités Avancées 🔄 En cours

| Priorité | Fonctionnalité | Effort | Statut |
|----------|----------------|--------|--------|
| P2 | WebSocket temps réel | 8 jours | ✅ Fait |
| P3 | Champs personnalisés membres | 6 jours | ⬜ À faire |
| P4 | Application mobile PWA | 15 jours | ✅ Fait (base) |
| P4 | Mode hors-ligne | 12 jours | ✅ Fait |

**Complété : 35/46 jours**

### P2 — WebSocket temps réel ✅ TERMINÉ
**Implémenté le 5 février 2026**

Fichiers créés:
- `app/WebSocket/Server.php` - Serveur Ratchet avec authentification et rooms
- `app/WebSocket/EventBroadcaster.php` - Service de broadcast via file queue
- `bin/websocket-server.php` - Script de démarrage avec signal handling
- `public/assets/js/websocket-client.js` - Client JS avec reconnexion et polling fallback
- `config/supervisord-websocket.conf` - Configuration production

Événements temps réel:
- `motion.opened` - Ouverture d'une résolution
- `motion.closed` - Clôture avec résultats
- `vote.cast` - Vote enregistré (tally mis à jour)
- `attendance.updated` - Présence modifiée
- `meeting.status_changed` - Transition de statut

Intégrations API:
- `motions_open.php`, `motions_close.php`
- `BallotsService::castBallot()`
- `AttendancesService::upsert()`
- `meeting_transition.php`

### P3 — Champs personnalisés
- Table `custom_fields`
- Table `custom_field_values`
- Types : text, number, date, select, boolean
- Formulaires dynamiques

### P4 — Application mobile PWA ✅ BASE TERMINÉE
**Implémenté le 5 février 2026**

Fichiers créés:
- `public/manifest.json` - Manifest PWA avec shortcuts et icons
- `public/sw.js` - Service Worker avec stratégies de cache

Stratégies de cache:
- Cache-first pour assets statiques (CSS, JS, images)
- Network-first avec cache fallback pour API
- Stale-while-revalidate pour pages HTML

### P4 — Mode hors-ligne ✅ TERMINÉ
**Implémenté le 5 février 2026**

Fichiers créés:
- `public/assets/js/offline-storage.js` - IndexedDB wrapper complet
- `public/assets/js/conflict-resolver.js` - Résolution de conflits
- `public/assets/js/components/ag-offline-indicator.js` - Indicateur visuel

Fonctionnalités:
- Stockage IndexedDB (meetings, motions, members, attendances)
- Queue d'actions offline avec retry automatique
- Synchronisation à la reconnexion
- Résolution de conflits (server-wins, client-wins, merge, manual)
- UI de résolution de conflits
- Background sync via Service Worker

---

## Éléments Exclus

Les éléments suivants ne font **PAS** partie de la roadmap :

- ~~Multi-langue (i18n)~~
- ~~Canal SMS~~
- ~~CI/CD pipeline~~
- ~~2FA~~
- ~~SSO (SAML/OIDC)~~
- ~~Export données personnelles RGPD~~
- ~~Couverture tests >80%~~

---

## Tests

| Catégorie | Nombre | Statut |
|-----------|--------|--------|
| Tests unitaires | 189 | ✅ 100% passent |
| Tests E2E | ~40 | ✅ Implémentés |

---

## Suivi d'avancement Final

| Catégorie | Complété | En cours | À faire |
|-----------|----------|----------|---------|
| Migration MVC | 5/5 | - | 0/5 |
| Workflow Opérateur | 6/6 | - | 0/6 |
| Exports | 4/4 | - | 0/4 |
| Invitations | 3/3 | - | 0/3 |
| UI/UX | 4/4 | - | 0/4 |
| Fonctionnalités avancées | 3/4 | - | 1/4 |

**Avancement global : ~90%**
- WebSocket temps réel : ✅ Terminé
- Mode hors-ligne : ✅ Terminé
- PWA base : ✅ Terminé
- Champs personnalisés : ⬜ Non implémenté (P3)

**Dernière mise à jour** : 5 février 2026
