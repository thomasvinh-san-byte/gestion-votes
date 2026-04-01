# Requirements: AG-VOTE

**Defined:** 2026-03-31
**Core Value:** Self-hosted voting platform with legal compliance for French general assemblies

## v6.0 Requirements

Requirements for v6.0 Production & Email milestone. Each maps to roadmap phases.

### Email

- [x] **EMAIL-01**: L'operateur peut envoyer une invitation par email aux membres d'une seance — l'email contient un lien qui amene le destinataire vers la page de vote
- [x] **EMAIL-02**: L'operateur peut envoyer un rappel par email avant une seance — l'email contient la date, le lieu et un lien vers le hub
- [x] **EMAIL-03**: Apres cloture d'une seance, un email de resultats est envoye aux participants avec un lien vers les resultats
- [x] **EMAIL-04**: Les templates d'emails sont personnalisables depuis l'interface admin (sujet, corps HTML avec variables)
- [x] **EMAIL-05**: L'envoi utilise SMTP generique (Symfony Mailer) — compatible Mailgun, SendGrid, OVH, Gmail

### Notifications

- [x] **NOTIF-01**: Une icone cloche dans le header affiche un badge avec le nombre de notifications non lues
- [x] **NOTIF-02**: Cliquer sur la cloche affiche la liste des notifications recentes (nouveau vote ouvert, seance bientot, resultats disponibles)
- [ ] **NOTIF-03**: Un toast apparait en temps reel via SSE quand un evenement important survient (vote ouvert, quorum atteint, seance demarree)

## Future Requirements

### Deploiement (deferred to v6.1+)

- **DEPLOY-01**: Deploiement Render (web service Docker + PostgreSQL manage + Redis)
- **DEPLOY-02**: Page /status sante (DB, Redis, filesystem)
- **DEPLOY-03**: HTTPS + domaine personnalise
- **DEPLOY-04**: Variables d'environnement production documentees

### PV & Compliance (deferred)

- **PV-01**: Generation PV finalisee et testee
- **PV-02**: Export PDF conforme aux exigences legales francaises

## Out of Scope

| Feature | Reason |
|---------|--------|
| Reception d'emails entrants (webhook inbound) | Pas necessaire — les emails contiennent des liens, pas de reponse a traiter |
| Monitoring avance (dashboard, graphiques) | Trop ambitieux pour ce milestone |
| Framework migration | Vanilla stack is the identity |
| Nouveaux modes de vote | Functional parity first |
| App mobile native | PWA approach maintained |

## Traceability

| Requirement | Phase | Status |
|-------------|-------|--------|
| EMAIL-01 | Phase 63 | Complete |
| EMAIL-02 | Phase 63 | Complete |
| EMAIL-03 | Phase 63 | Complete |
| EMAIL-04 | Phase 62 | Complete |
| EMAIL-05 | Phase 62 | Complete |
| NOTIF-01 | Phase 64 | Complete |
| NOTIF-02 | Phase 64 | Complete |
| NOTIF-03 | Phase 64 | Pending |

**Coverage:**
- v6.0 requirements: 8 total
- Mapped to phases: 8
- Unmapped: 0

---
*Requirements defined: 2026-03-31*
*Last updated: 2026-03-31 after roadmap creation*
