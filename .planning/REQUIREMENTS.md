# Requirements: AgVote — Dette Technique

**Defined:** 2026-04-07
**Core Value:** L'application doit etre fiable en production — aucun crash lie a des fallbacks fichiers, des fuites memoire, ou des timeouts silencieux.

## v1 Requirements

Requirements for this milestone. Each maps to roadmap phases.

### Infrastructure Redis

- [ ] **REDIS-01**: SSE EventBroadcaster utilise Redis Pub/Sub exclusivement, fallback fichier supprime
- [x] **REDIS-02**: Rate-limiting utilise Redis avec script Lua atomique (INCR+EXPIRE), flock supprime
- [ ] **REDIS-03**: Detection serveur SSE via heartbeat Redis avec TTL, PID-file supprime
- [x] **REDIS-04**: Health check Redis au boot de Application, erreur claire si Redis indisponible

### Performance

- [x] **PERF-01**: PDO::ATTR_TIMEOUT configure pour connection timeout, statement_timeout PostgreSQL pour query timeout, configurable par environnement
- [x] **PERF-02**: MeetingStatsRepository utilise une seule requete d'aggregation avec FILTER au lieu de 10+ COUNT(*) separes
- [x] **PERF-03**: ExportService utilise openspout/openspout pour streaming XLSX, memoire sub-3MB quelle que soit la taille des donnees
- [x] **PERF-04**: EmailQueueService traite les emails par lots (batch de 25), avec backpressure et pas de chargement complet en memoire

### Refactoring

- [x] **REFAC-01**: Logique metier de ImportController (921 lignes) extraite dans des services dedies, controller = orchestration HTTP uniquement

### Tests

- [x] **TEST-01**: RgpdExportController a des tests unitaires couvrant scope validation, acces non autorise, et compliance donnees personnelles (Note: auth 401 teste via AuthMiddleware::requireRole() directement — le stub api_require_role() no-op dans bootstrap.php empeche le test via callController; limitation d'infrastructure acceptee)
- [x] **TEST-02**: AuthMiddleware a des tests couvrant le lifecycle session complet et les transitions d'etat des 10+ variables statiques
- [ ] **TEST-03**: SSE EventBroadcaster (post-migration Redis) a des tests pour les race conditions et la fiabilite de delivery
- [x] **TEST-04**: ImportController a des tests pour le fuzzy matching de colonnes CSV (partial matches, case sensitivity, headers multi-langue)

## v2 Requirements

Deferred to future milestone. Tracked but not in current roadmap.

### Refactoring Avance

- **REFAC-02**: Eliminer les statics AuthMiddleware → SessionContext injectable avec DI
- **REFAC-03**: Splitter MeetingReportsController si > 400 lignes apres extraction services
- **REFAC-04**: Splitter MotionsController si > 400 lignes apres extraction services

### Resilience

- **RESIL-01**: Retry avec exponential backoff dans api_transaction()
- **RESIL-02**: Audit trail pour operations session (creation/expiry)
- **RESIL-03**: Bounce handling pour EmailQueueService (delivery confirmation SMTP)

## Out of Scope

| Feature | Reason |
|---------|--------|
| Migration vers Symfony/Laravel | Refactoring incremental uniquement, pas de migration framework |
| Redis sessions (remplacement $_SESSION) | Complexite disproportionnee pour ce milestone |
| 100% couverture tests | Cible 70-80% overall, 95%+ chemins critiques |
| Regressions visuelles v4.2 | Milestone separe |
| Interactions JS/HTMX cassees | Milestone separe |
| Nouvelles fonctionnalites metier | Stabiliser d'abord |
| PDFs convocation/emargement | Hors perimetre de l'app |
| Raccourcis clavier | Hors perimetre |

## Traceability

Which phases cover which requirements. Updated during roadmap creation.

| Requirement | Phase | Status |
|-------------|-------|--------|
| REDIS-01 | Phase 1 | Complete |
| REDIS-02 | Phase 1 | Complete |
| REDIS-03 | Phase 1 | Complete |
| REDIS-04 | Phase 1 | Complete |
| PERF-01 | Phase 2 | Complete |
| PERF-02 | Phase 2 | Complete |
| PERF-03 | Phase 2 | Complete |
| PERF-04 | Phase 2 | Complete |
| REFAC-01 | Phase 3 | Complete |
| TEST-01 | Phase 3 | Complete |
| TEST-02 | Phase 3 | Complete |
| TEST-03 | Phase 4 | Pending |
| TEST-04 | Phase 4 | Complete |

**Coverage:**
- v1 requirements: 13 total
- Mapped to phases: 13
- Unmapped: 0 ✓

---
*Requirements defined: 2026-04-07*
*Last updated: 2026-04-07 after roadmap creation*
