# Requirements: AG-VOTE v3.0 — Session Lifecycle

**Defined:** 2026-03-16
**Core Value:** Full-stack session lifecycle wiring — zero demo data, real API, SSE real-time

## v3.0 Requirements

Requirements for v3.0 release. Each maps to roadmap phases.

### Création de session

- [x] **WIZ-01**: Le wizard crée une session en DB avec titre, type, lieu, date en une seule requête API
- [x] **WIZ-02**: Les membres sélectionnés à l'étape 2 du wizard sont persistés en transaction atomique avec la session
- [x] **WIZ-03**: Les résolutions saisies à l'étape 3 du wizard sont persistées en transaction atomique avec la session

### Hub & Dashboard

- [x] **HUB-01**: Le hub charge l'état réel de la session via l'API wizard_status (zéro donnée démo)
- [x] **HUB-02**: Le hub affiche un état d'erreur explicite quand le backend est indisponible
- [x] **HUB-03**: Le dashboard affiche les compteurs de sessions réels depuis la base de données
- [x] **HUB-04**: Le dashboard affiche un état d'erreur explicite au lieu du fallback démo

### SSE Temps Réel

- [x] **SSE-01**: Le endpoint events.php supporte plusieurs consommateurs simultanés sans perte d'événements
- [x] **SSE-02**: nginx dispose d'un location block dédié pour events.php avec fastcgi_buffering off
- [x] **SSE-03**: La configuration PHP-FPM documente le dimensionnement pour les connexions SSE longue durée
- [x] **SSE-04**: Le décompte des votes opérateur se met à jour en temps réel via SSE après chaque bulletin

### Console Opérateur

- [x] **OPR-01**: La console opérateur charge les données réelles de la session via meeting_id propagé par MeetingContext
- [x] **OPR-02**: L'onglet présence charge les données d'inscription depuis l'API
- [x] **OPR-03**: L'onglet motions charge les résolutions depuis l'API
- [x] **OPR-04**: La connexion SSE se déclenche sur MeetingContext:change (pas au chargement de page)

### Vote en Direct

- [x] **VOT-01**: L'opérateur peut ouvrir une motion et les votants voient la motion active
- [x] **VOT-02**: Le votant peut soumettre un bulletin et reçoit une confirmation
- [x] **VOT-03**: L'opérateur peut fermer une motion et les résultats sont calculés
- [x] **VOT-04**: Les transitions d'état machine (draft→scheduled→frozen→live→closed→validated) fonctionnent de bout en bout

### Post-Session & PV

- [ ] **PST-01**: L'étape 1 du stepper post-session affiche les résultats vérifiés (fix endpoint motions_for_meeting)
- [ ] **PST-02**: L'étape 2 déclenche la consolidation puis la transition closed→validated
- [ ] **PST-03**: L'étape 3 génère le PV en PDF via Dompdf
- [ ] **PST-04**: L'étape 4 permet l'archivage de la session (lien export_correspondance supprimé)

### Nettoyage

- [x] **CLN-01**: Zero constante SEED_ dans le codebase
- [x] **CLN-02**: Chaque appel API dispose d'états loading, error et empty
- [x] **CLN-03**: Le fallback démo audit.js (SEED_EVENTS) est supprimé et remplacé par un état d'erreur

## v3.x+ Requirements

Deferred to future release. Tracked but not in current roadmap.

### SSE Avancé

- **SSE-05**: Fallback SSE fichier testé en charge sans Redis
- **SSE-06**: Vote pondéré dans l'affichage des résultats du tally

### Console Opérateur Avancé

- **OPR-05**: Vote papier / saisie manuelle par l'opérateur
- **OPR-06**: Gestion proxy/procurations depuis la console opérateur
- **OPR-07**: invitations_stats.php complétude et suppression placeholder

### Post-Session Avancé

- **PST-05**: Trigger consolidation explicite dans l'UI post-session
- **PST-06**: eIDAS stockage signataires en base de données

## Out of Scope

Explicitly excluded. Documented to prevent scope creep.

| Feature | Reason |
|---------|--------|
| Signature électronique upload/validation | Complexité élevée, différé v4+ |
| export_correspondance endpoint | N'existe pas côté backend — lien supprimé dans v3.0 |
| Nouvelles librairies / changement de framework | Stack vanilla PHP+JS est l'identité du projet |
| Pages non-session (stats, audit, help, settings) | UI complète depuis v2.0, hors périmètre session lifecycle |
| Mobile native app | Approche PWA maintenue |
| Nouveaux modes de vote / types de rapport | Parité fonctionnelle d'abord |

## Traceability

Which phases cover which requirements. Updated during roadmap creation.

| Requirement | Phase | Status |
|-------------|-------|--------|
| WIZ-01 | Phase 16 | Complete |
| WIZ-02 | Phase 16 | Complete |
| WIZ-03 | Phase 16 | Complete |
| HUB-01 | Phase 23 | Complete |
| HUB-02 | Phase 16 | Complete |
| HUB-03 | Phase 17 | Complete |
| HUB-04 | Phase 17 | Complete |
| SSE-01 | Phase 18 | Complete |
| SSE-02 | Phase 18 | Complete |
| SSE-03 | Phase 18 | Complete |
| SSE-04 | Phase 18 | Complete |
| OPR-01 | Phase 19 | Complete |
| OPR-02 | Phase 19 | Complete |
| OPR-03 | Phase 19 | Complete |
| OPR-04 | Phase 19 | Complete |
| VOT-01 | Phase 23 | Complete |
| VOT-02 | Phase 20 | Complete |
| VOT-03 | Phase 20 | Complete |
| VOT-04 | Phase 23 | Complete |
| PST-01 | Phase 21 | Pending |
| PST-02 | Phase 21 | Pending |
| PST-03 | Phase 21 | Pending |
| PST-04 | Phase 21 | Pending |
| CLN-01 | Phase 22 | Complete |
| CLN-02 | Phase 22 | Complete |
| CLN-03 | Phase 17 | Complete |

**Coverage:**
- v3.0 requirements: 26 total
- Mapped to phases: 26
- Unmapped: 0 ✓

---
*Requirements defined: 2026-03-16*
*Last updated: 2026-03-16 after roadmap creation — all 26 requirements mapped*
