# Requirements: AG-VOTE

**Defined:** 2026-04-01
**Core Value:** Self-hosted voting platform with legal compliance for French general assemblies

## v7.0 Requirements

Requirements for v7.0 Production Essentials milestone.

### PV Officiel

- [x] **PV-01**: Apres validation d'une seance, l'operateur peut generer un PV PDF contenant : en-tete (nom orga, date, lieu), liste des membres presents/representes, quorum atteint, resolutions avec resultats detailles (pour/contre/abstention), et pied de page (signatures president + secretaire)
- [x] **PV-02**: Le PV PDF utilise le template asso loi 1901 standard et est genere via Dompdf (deja installe)
- [x] **PV-03**: Le PV genere est consultable et telechargeable depuis la page post-session

### Email Queue

- [ ] **QUEUE-01**: Un worker cron dans le conteneur Docker execute processQueue() automatiquement a intervalle regulier (toutes les minutes)
- [ ] **QUEUE-02**: Les emails en echec sont re-essayes avec un backoff, et les echecs definitifs sont marques comme failed dans la queue

### Setup Initial

- [ ] **SETUP-01**: Une page /setup est accessible uniquement quand aucun utilisateur admin n'existe dans la base
- [ ] **SETUP-02**: Le formulaire de setup cree le premier tenant (nom de l'organisation) et le premier admin (email + mot de passe)
- [ ] **SETUP-03**: Apres le setup, la page redirige vers /login et n'est plus accessible

### Reset Password

- [ ] **RESET-01**: La page de login affiche un lien "Mot de passe oublie" qui ouvre un formulaire de demande de reset (saisie email)
- [ ] **RESET-02**: Un email contenant un lien securise avec token temporaire (expiration 1h) est envoye a l'utilisateur
- [ ] **RESET-03**: Le lien amene sur une page de saisie du nouveau mot de passe, qui met a jour le hash en base

## Future Requirements

### Deploiement (deferred)

- **DEPLOY-01**: Deploiement Render (web service Docker + PostgreSQL manage + Redis)
- **DEPLOY-02**: Page /status sante (DB, Redis, filesystem)

## Out of Scope

| Feature | Reason |
|---------|--------|
| AI-assisted PV minutes | Feature future — pas dans ce milestone |
| Electronic signature | Deferred — signatures textuelles suffisent pour asso loi 1901 |
| ClamAV virus scanning | Deferred |
| Internationalisation (i18n) | Francais uniquement pour l'instant |
| Deploiement cloud | Milestone separe |

## Traceability

| Requirement | Phase | Status |
|-------------|-------|--------|
| PV-01 | Phase 67 | Complete |
| PV-02 | Phase 67 | Complete |
| PV-03 | Phase 67 | Complete |
| QUEUE-01 | Phase 68 | Pending |
| QUEUE-02 | Phase 68 | Pending |
| SETUP-01 | Phase 69 | Pending |
| SETUP-02 | Phase 69 | Pending |
| SETUP-03 | Phase 69 | Pending |
| RESET-01 | Phase 70 | Pending |
| RESET-02 | Phase 70 | Pending |
| RESET-03 | Phase 70 | Pending |

**Coverage:**
- v7.0 requirements: 11 total
- Mapped to phases: 11
- Unmapped: 0

---
*Requirements defined: 2026-04-01*
*Last updated: 2026-04-01 after roadmap creation*
