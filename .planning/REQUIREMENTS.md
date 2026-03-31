# Requirements: AG-VOTE

**Defined:** 2026-03-31
**Core Value:** Self-hosted voting platform with legal compliance for French general assemblies

## v6.0 Requirements

Requirements for v6.0 Production & Email milestone. Each maps to roadmap phases.

### Email

- [ ] **EMAIL-01**: L'opérateur peut envoyer une invitation par email aux membres d'une séance — l'email contient un lien qui amène le destinataire vers la page de vote
- [ ] **EMAIL-02**: L'opérateur peut envoyer un rappel par email avant une séance — l'email contient la date, le lieu et un lien vers le hub
- [ ] **EMAIL-03**: Après clôture d'une séance, un email de résultats est envoyé aux participants avec un lien vers les résultats
- [ ] **EMAIL-04**: Les templates d'emails sont personnalisables depuis l'interface admin (sujet, corps HTML avec variables)
- [ ] **EMAIL-05**: L'envoi utilise SMTP générique (Symfony Mailer) — compatible Mailgun, SendGrid, OVH, Gmail

### Notifications

- [ ] **NOTIF-01**: Une icône cloche dans le header affiche un badge avec le nombre de notifications non lues
- [ ] **NOTIF-02**: Cliquer sur la cloche affiche la liste des notifications récentes (nouveau vote ouvert, séance bientôt, résultats disponibles)
- [ ] **NOTIF-03**: Un toast apparaît en temps réel via SSE quand un événement important survient (vote ouvert, quorum atteint, séance démarrée)

## Future Requirements

### Déploiement (deferred to v6.1+)

- **DEPLOY-01**: Déploiement Render (web service Docker + PostgreSQL managé + Redis)
- **DEPLOY-02**: Page /status santé (DB, Redis, filesystem)
- **DEPLOY-03**: HTTPS + domaine personnalisé
- **DEPLOY-04**: Variables d'environnement production documentées

### PV & Compliance (deferred)

- **PV-01**: Génération PV finalisée et testée
- **PV-02**: Export PDF conforme aux exigences légales françaises

## Out of Scope

| Feature | Reason |
|---------|--------|
| Réception d'emails entrants (webhook inbound) | Pas nécessaire — les emails contiennent des liens, pas de réponse à traiter |
| Monitoring avancé (dashboard, graphiques) | Trop ambitieux pour ce milestone |
| Framework migration | Vanilla stack is the identity |
| Nouveaux modes de vote | Functional parity first |
| App mobile native | PWA approach maintained |

## Traceability

| Requirement | Phase | Status |
|-------------|-------|--------|
| EMAIL-01 | TBD | Pending |
| EMAIL-02 | TBD | Pending |
| EMAIL-03 | TBD | Pending |
| EMAIL-04 | TBD | Pending |
| EMAIL-05 | TBD | Pending |
| NOTIF-01 | TBD | Pending |
| NOTIF-02 | TBD | Pending |
| NOTIF-03 | TBD | Pending |

**Coverage:**
- v6.0 requirements: 8 total
- Mapped to phases: 0
- Unmapped: 8 ⚠️

---
*Requirements defined: 2026-03-31*
*Last updated: 2026-03-31 after milestone v6.0 start*
