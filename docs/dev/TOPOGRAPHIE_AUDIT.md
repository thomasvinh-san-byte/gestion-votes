# AG-VOTE — Reste a faire

> Derniere mise a jour : 2026-03-09

---

## Ameliorations futures (non bloquantes pour la prod)

| # | Sujet | Etat | Detail |
|---|-------|------|--------|
| 1 | **WebSocket frontend ou SSE** | A faire | Le backend a `app/WebSocket/` mais le frontend utilise du polling 5s (`core/shell.js`). Activer le WebSocket existant ou migrer vers SSE pour reduire la charge serveur et la latence UX. |
| 2 | **Decouper `operator.htmx.html`** (89 KB) | A faire | Page la plus lourde, 128 refs SVG, tout en un seul fichier. La decouper en fragments HTMX charges a la demande (`hx-get`) pour ameliorer le temps de chargement mobile. |
| 3 | **Monitoring / alerting** | A faire | Les tables `system_metrics` et `system_alerts` existent mais ne sont pas exploitees. Ajouter un webhook ou un email d'alerte sur les seuils critiques (espace disque, echecs email, erreurs DB). |
