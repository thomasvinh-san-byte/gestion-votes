# Requirements: AG-VOTE v5.1

**Defined:** 2026-03-31
**Core Value:** Self-hosted voting platform with legal compliance, 100% operational

## v5.1 Requirements

### Nettoyage WebSocket vers SSE (SSE)

- [x] **SSE-01**: Le namespace `AgVote\WebSocket` est renommé en `AgVote\SSE` dans tous les fichiers
- [x] **SSE-02**: `WebSocketListener` est renommé en `SseListener`, tous les commentaires "WebSocket" corrigés
- [x] **SSE-03**: Zéro occurrence de "WebSocket" dans le code PHP (hors vendor/)

### Edge cases Vote (VOTE)

- [ ] **VOTE-01**: Un vote avec un token expiré ou déjà utilisé retourne une erreur claire (pas 500)
- [ ] **VOTE-02**: Un double vote avec le même token est rejeté et l'anomalie est loguée en audit
- [ ] **VOTE-03**: Un vote sur une motion fermée retourne une erreur explicite

### Edge cases Quorum (QUOR)

- [ ] **QUOR-01**: Le calcul de quorum fonctionne correctement avec zéro membre présent (pas de division par zéro)
- [ ] **QUOR-02**: L'ajout ou le retrait de présence en cours de vote met à jour le quorum en temps réel via SSE

### Edge cases Sessions (SESS)

- [ ] **SESS-01**: Les transitions d'état invalides (ex: draft vers validated) sont rejetées avec message explicite
- [ ] **SESS-02**: La suppression d'une séance en cours (live) est interdite

### Edge cases Import (IMP)

- [ ] **IMP-01**: Un CSV avec encodage Windows-1252 ou ISO-8859-1 est détecté et converti correctement
- [ ] **IMP-02**: Les doublons par email sont détectés et signalés (pas de création silencieuse)

### Edge cases Auth (AUTH)

- [ ] **AUTH-01**: Une session expirée redirige vers /login avec un message clair (pas de page blanche)
- [ ] **AUTH-02**: Les tentatives de brute force sont bloquées et l'IP est loguée dans l'audit trail

### Nettoyage code mort (CLEAN)

- [ ] **CLEAN-01**: Les stubs restants dans les controllers sont supprimés ou implémentés
- [ ] **CLEAN-02**: Les seeds de démo ne contiennent aucune référence copropriété ou syndic
- [ ] **CLEAN-03**: Les fichiers dead code identifiés sont nettoyés ou documentés

## v6.0 Requirements

### Performance et Monitoring

- **PERF-01**: Load testing suite for SSE connections (100+ concurrent voters)
- **PERF-02**: Database query performance benchmarks for large tenants (1000+ members)
- **MON-01**: Prometheus metrics endpoint for production monitoring

### Déploiement

- **DEPLOY-01**: Railway deployment config (Dockerfile, env vars, Redis)
- **DEPLOY-02**: Supabase PostgreSQL integration option

## Out of Scope

| Feature | Reason |
|---------|--------|
| Nouveaux modes de vote | Milestone de hardening, pas de nouvelles fonctionnalités |
| Déploiement Railway/cloud | Reporté à v6.0 |
| Refactoring framework | Vanilla stack est l'identité du projet |
| Renommage fichiers .htmx.html | Les fichiers physiques gardent leur nom, nginx rewrites suffisent |

## Traceability

| Requirement | Phase | Status |
|-------------|-------|--------|
| SSE-01 | Phase 58 | Complete |
| SSE-02 | Phase 58 | Complete |
| SSE-03 | Phase 58 | Complete |
| VOTE-01 | Phase 59 | Pending |
| VOTE-02 | Phase 59 | Pending |
| VOTE-03 | Phase 59 | Pending |
| QUOR-01 | Phase 59 | Pending |
| QUOR-02 | Phase 59 | Pending |
| SESS-01 | Phase 60 | Pending |
| SESS-02 | Phase 60 | Pending |
| IMP-01 | Phase 60 | Pending |
| IMP-02 | Phase 60 | Pending |
| AUTH-01 | Phase 60 | Pending |
| AUTH-02 | Phase 60 | Pending |
| CLEAN-01 | Phase 61 | Pending |
| CLEAN-02 | Phase 61 | Pending |
| CLEAN-03 | Phase 61 | Pending |

**Coverage:**
- v5.1 requirements: 17 total
- Mapped to phases: 17
- Unmapped: 0

---
*Requirements defined: 2026-03-31*
