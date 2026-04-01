# Requirements: AG-VOTE

**Defined:** 2026-04-01
**Core Value:** Self-hosted voting platform with legal compliance for French general assemblies

## v6.1 Requirements

Requirements for v6.1 PDF & Preparation de Seance milestone.

### Pieces jointes de seance

- [ ] **ATTACH-01**: L'operateur peut uploader des pieces jointes PDF a une seance depuis le wizard de creation (etape 1 — infos de la seance)
- [ ] **ATTACH-02**: L'operateur peut gerer (voir, ajouter, supprimer) les pieces jointes depuis la console operateur
- [ ] **ATTACH-03**: Les votants peuvent consulter les pieces jointes de la seance depuis le hub (section "Documents de la seance" avec ag-pdf-viewer)
- [ ] **ATTACH-04**: Les votants peuvent consulter les pieces jointes de la seance depuis la page de vote (bouton "Documents" avec ag-pdf-viewer)
- [ ] **ATTACH-05**: Un endpoint serve securise permet aux votants d'acceder aux fichiers PDF (authentification session OU token de vote)

## Future Requirements

### Deploiement (deferred to v7.0+)

- **DEPLOY-01**: Deploiement Render (web service Docker + PostgreSQL manage + Redis)
- **DEPLOY-02**: Page /status sante (DB, Redis, filesystem)

### PV & Compliance (deferred)

- **PV-01**: Generation PV finalisee et testee
- **PV-02**: Export PDF conforme aux exigences legales francaises

## Out of Scope

| Feature | Reason |
|---------|--------|
| PV (proces-verbal) | Se recoit par email apres seance — pas melange avec les documents de seance |
| Upload de fichiers non-PDF | PDF uniquement pour ce milestone |
| Categorisation/ordonnancement des documents | Fonctionnalite future |
| ClamAV virus scanning | Deferred |

## Traceability

| Requirement | Phase | Status |
|-------------|-------|--------|
| ATTACH-01 | TBD | Pending |
| ATTACH-02 | TBD | Pending |
| ATTACH-03 | TBD | Pending |
| ATTACH-04 | TBD | Pending |
| ATTACH-05 | TBD | Pending |

**Coverage:**
- v6.1 requirements: 5 total
- Mapped to phases: 0
- Unmapped: 5

---
*Requirements defined: 2026-04-01*
*Last updated: 2026-04-01 after milestone v6.1 start*
