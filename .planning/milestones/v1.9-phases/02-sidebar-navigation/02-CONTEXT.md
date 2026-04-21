# Phase 2: Sidebar Navigation - Context

**Gathered:** 2026-04-21
**Status:** Ready for planning

<domain>
## Phase Boundary

La navigation laterale est toujours visible et utilisable sans effort — chaque utilisateur voit uniquement les liens pertinents pour son role. Transformation de la sidebar rail 58px hover-to-expand en sidebar toujours ouverte 200px avec labels visibles.

Requirements: NAV-01, NAV-02, NAV-03

</domain>

<decisions>
## Implementation Decisions

### Sidebar Layout Strategy
- Changer --sidebar-width de 58px a 200px — sidebar toujours ouverte avec labels visibles, supprimer le comportement hover-expand
- Supprimer entierement le mecanisme pin/unpin — la sidebar est toujours ouverte, pas d'option de reduction
- Mobile: garder le comportement hamburger existant — sidebar en overlay sur mobile, toujours ouverte sur desktop uniquement
- Padding-left statique sur .app-main: calc(200px + 20px) — pas de toggle JS necessaire puisque la sidebar ne se reduit jamais

### Role-Based Filtering
- Garder le filtrage client-side via JS (auth-ui.js) — fonctionne deja, risque minimal de casser le comportement existant
- Un votant voit uniquement: "Voter" (page de vote) et "Mon compte" (parametres du compte)
- Les items caches restent dans le DOM avec display:none (approche actuelle avec data-requires-role)
- L'etat "pas de seance" pour les votants: le lien "Voter" reste visible, la page vote elle-meme affiche un etat vide (Phase 3 gere les etats vides)

### Touch Targets & Visual Polish
- Augmenter la hauteur de .nav-item de 42px a 44px via ajustement du padding
- Les en-tetes de nav-group aussi a 44px pour la coherence — tous les elements cliquables de la sidebar ont la meme taille de cible
- Labels longs: troncature avec ellipsis a la largeur du conteneur
- A 1366px minimum, 200px sidebar + 1166px contenu fonctionne — pas de breakpoint special necessaire

### Claude's Discretion
- Aucun — toutes les decisions ont ete prises explicitement

</decisions>

<code_context>
## Existing Code Insights

### Reusable Assets
- public/partials/sidebar.html — template HTML de la sidebar avec nav-items et data-requires-role
- public/assets/js/core/shell.js — controleur sidebar (pin, expand, scroll indicators)
- public/assets/js/pages/auth-ui.js — filtrage des items par role via whoami API
- design-system.css — tokens sidebar (--sidebar-width, --sidebar-expanded, --sidebar-bg, etc.)

### Established Patterns
- CSS variables pour les dimensions sidebar
- Position fixed pour la sidebar, padding-left sur .app-main
- data-requires-role attribut pour le filtrage client-side
- localStorage pour la persistance de l'etat pin (a supprimer)
- Transition CSS cubic-bezier pour l'animation expand (a supprimer)
- Hamburger nav sur mobile avec .open state

### Integration Points
- shell.js: gere le pin/unpin, le calcul de --sidebar-top, les scroll indicators
- auth-ui.js: filtrage par role via /api/v1/whoami.php
- .app-main padding-left: toggle JS quand sidebar est pinnee
- Mobile media query: hamburger toggle + overlay
- Z-index stacking: --z-sidebar: 55, --z-header: 90

</code_context>

<specifics>
## Specific Ideas

- Le votant ne voit que "Voter" et "Mon compte" — pas les 16 liens admin
- La sidebar fait exactement ~200px — pas 252px (trop large) ni 58px (rail)
- Supprimer les animations de hover-expand et le toggle pin

</specifics>

<deferred>
## Deferred Ideas

None — discussion stayed within phase scope.

</deferred>
