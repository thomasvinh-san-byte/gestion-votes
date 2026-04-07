# Phase 1: Infrastructure Redis - Context

**Gathered:** 2026-04-07
**Status:** Ready for planning
**Mode:** Auto-generated (infrastructure phase — discuss skipped)

<domain>
## Phase Boundary

L'application ne depend plus d'aucun fichier /tmp en production — Redis est le seul broker pour SSE, rate-limiting, et detection serveur. Health check Redis au boot avec message d'erreur clair si indisponible.

</domain>

<decisions>
## Implementation Decisions

### Claude's Discretion
All implementation choices are at Claude's discretion — pure infrastructure phase. Use ROADMAP phase goal, success criteria, and codebase conventions to guide decisions.

Key constraints from research:
- Redis Pub/Sub pour SSE (XADD/XREAD si replay necessaire)
- Lua eval() pour rate-limiting atomique (pas MULTI/EXEC)
- Heartbeat Redis avec TTL pour remplacer PID-file
- Health check dans Application::boot()
- Verifier Redis 6.2+ dans docker-compose (requis pour LPOP count)

</decisions>

<code_context>
## Existing Code Insights

### Reusable Assets
- `app/SSE/EventBroadcaster.php` — SSE broker actuel avec fallback fichier (lignes 206-273)
- `app/Core/Security/RateLimiter.php` — Rate-limiting fichier avec flock (lignes 198-234)
- `app/Core/Application.php` — Boot orchestrator, point d'insertion pour health check
- Redis phpredis extension deja installee et configuree

### Established Patterns
- Singleton DatabaseProvider pour connexion PDO
- RepositoryFactory pour DI
- Configuration via .env / EnvProvider

### Integration Points
- EventBroadcaster utilise par les controllers pour SSE
- RateLimiter utilise par RateLimitGuard middleware
- PID-file dans /tmp/agvote-sse.pid

</code_context>

<specifics>
## Specific Ideas

No specific requirements — infrastructure phase. Refer to ROADMAP phase description and success criteria.

</specifics>

<deferred>
## Deferred Ideas

None — infrastructure phase.

</deferred>
