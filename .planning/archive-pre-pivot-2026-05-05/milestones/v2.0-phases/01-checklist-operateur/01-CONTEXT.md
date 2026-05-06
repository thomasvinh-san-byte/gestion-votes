# Phase 1: Checklist Operateur - Context

**Gathered:** 2026-04-21
**Status:** Ready for planning

<domain>
## Phase Boundary

L'operateur dispose d'une checklist en temps reel affichant l'etat de la seance (quorum, votes recus, connectivite SSE, votants connectes) avec alertes visuelles automatiques. Scope: operator.htmx.html uniquement, mode execution. Consomme l'infrastructure SSE existante (EventBroadcaster + event-stream.js + operator-realtime.js) sans la modifier.

</domain>

<decisions>
## Implementation Decisions

### Checklist Layout & Placement
- La checklist apparait comme un panneau lateral droit (collapsible) dans la vue execution, toujours visible sans scrolling
- Chaque indicateur est affiche sur une ligne compacte: icone + label + valeur (ex: `checkmark Quorum 42/60 (70%)`) avec icone coloree selon l'etat
- La checklist coexiste avec le KPI strip existant — elle est une vue de statut consolidee, le KPI strip reste pour les metriques detaillees
- La checklist est visible uniquement en mode Execution (pas en mode Preparation)

### Alert System & Thresholds
- Les alertes visuelles se manifestent par un flash inline rouge + pulsation de l'icone pendant 3 secondes, pas de modal ni de toast
- La deconnexion SSE a un traitement distinct: banniere rouge persistante en haut de la checklist ("Connexion perdue") car elle affecte tous les autres indicateurs
- L'indicateur quorum est binaire: vert si presents >= requis, rouge sinon
- Pas de son — alertes visuelles uniquement

### SSE & Connected Voters Display
- Le statut SSE est affiche avec un point colore + label: point vert "Connecte" / point rouge "Deconnecte" avec temps depuis le dernier evenement
- Les "votants connectes" utilisent le mecanisme existant de presence Redis TTL (heartbeat 60s dans operator-realtime.js)
- Les votes recus sont affiches en format fraction "12/45 votes recus" montrant la progression vers le total eligible
- La checklist reste visible en permanence pendant le mode execution, pas d'auto-collapse

### Claude's Discretion
- Details d'implementation CSS (animations, transitions, breakpoints)
- Structure HTML exacte du panneau checklist
- Ordre des indicateurs dans la checklist
- Gestion des etats transitoires (chargement initial, pas de motion active)

</decisions>

<code_context>
## Existing Code Insights

### Reusable Assets
- `opSseIndicator` element (operator.htmx.html line ~50) — SSE status dot already exists in meeting bar
- KPI strip (lines 1200-1234) — PRESENTS, QUORUM%, ONT VOTE, EN LIGNE, INACTIFS with animateKpiPct
- Contextual tags `opTagQuorum` / `opTagNoQuorum` (lines 1238-1242) — green/red quorum badges
- Vote card (lines 1324-1342) — Pour/Contre/Abstention counters with progress bars
- `computeQuorumStats()` in operator-exec.js (line 409) — aggregates current voters, total, threshold

### Established Patterns
- SSE events flow through EventBroadcaster (Redis pub/sub) -> event-stream.js -> operator-realtime.js event router
- Real-time updates use HTMX swap targets: vote.cast reloads ballots, attendance.updated reloads quorum
- KPI animations use Anime.js count-up (600ms easeOutQuad) with static fallback
- Presence heartbeat: HEAD request every 60s to keep Redis TTL alive (operator-realtime.js lines 62-69)
- Quorum warning modal already triggers on status=live AND voters < required (lines 535-537)

### Integration Points
- New checklist panel integrates into operator.htmx.html execution view section (after line ~1033)
- SSE event handlers in operator-realtime.js need to update checklist indicators on: vote.cast, attendance.updated, quorum.updated
- event-stream.js onConnect/onDisconnect callbacks feed SSE status indicator
- Presence endpoint already exists for voter count — reuse for checklist "votants connectes"

</code_context>

<specifics>
## Specific Ideas

No specific requirements — open to standard approaches. The checklist consolidates existing scattered indicators into a unified monitoring panel.

</specifics>

<deferred>
## Deferred Ideas

None — discussion stayed within phase scope.

</deferred>
