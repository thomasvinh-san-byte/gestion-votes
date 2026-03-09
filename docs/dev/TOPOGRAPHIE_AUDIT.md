# AG-VOTE — Reste a faire

> Derniere mise a jour : 2026-03-09

---

## Ameliorations futures (non bloquantes pour la prod)

| # | Sujet | Etat | Detail |
|---|-------|------|--------|
| 1 | **SSE (Server-Sent Events)** | Fait | Endpoint SSE `/api/v1/events.php` + client `event-stream.js`. Le polling reste en fallback (cadence reduite x3 quand SSE actif). Pages operator, vote, projector connectees. EventBroadcaster publie sur Redis `sse:events:{meetingId}`. |
| 2 | **Decouper `operator.htmx.html`** | Fait | Page reduite de 90 KB a 61 KB. Sections exec view (293 lignes) et live tabs (221 lignes) extraites en `/partials/operator-exec.html` et `/partials/operator-live-tabs.html`, chargees a la demande via `fetch()` au premier usage. |
| 3 | **Monitoring / alerting** | Fait | `MonitoringService` + commande CLI `monitor:check`. Collecte metrics, evalue seuils (DB latence, disque, auth failures, email backlog), envoie alertes par email aux admins et/ou webhook (Slack, PagerDuty...). Cron toutes les 5 min via supervisord. Cleanup automatique des anciennes metriques/alertes. Config via env: `MONITOR_ALERT_EMAILS`, `MONITOR_WEBHOOK_URL`, seuils personnalisables. |
