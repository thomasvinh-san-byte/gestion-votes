# Phase 6: Application Design Tokens - Context

**Gathered:** 2026-04-07
**Status:** Ready for planning

<domain>
## Phase Boundary

L'application a un design language uniforme et professionnel visible sur toutes les pages cles. Login redesigne en 2-panels, design tokens appliques uniformement, loading states CSS, et status badges semantiques.

</domain>

<decisions>
## Implementation Decisions

### Login 2-Panel Layout
- Ratio 50/50 entre panel branding et panel formulaire
- Panel branding: logo + nom AgVote + tagline, sobre et pro
- Background panel branding: gradient avec couleurs primaires (reutiliser l'orb existant de login.css)
- Breakpoint 768px: bascule en colonne unique, formulaire seul visible

### Design Token Enforcement
- Approche: grep + remplacement par page CSS, une page a la fois
- Priorite: couleurs d'abord, puis espacements (impact visuel maximal)
- Pages prioritaires: dashboard, hub, meetings (les plus visibles)
- Overrides: @layer pages pour les exceptions justifiees, cascade propre

### Loading States & Status Badges
- Loading: skeleton pulse sur l'element (.htmx-request), moderne et non-intrusif
- Couleurs badges: semantique OKLCH alignee sur design-system.css (success=vert, warning=orange, danger=rouge, info=bleu, neutral=gris)
- Shape: pill (border-radius arrondi) avec padding compact
- Animation: opacity fade-in 200ms, subtil et pro

### Claude's Discretion
- Ordre exact de traitement des 25 fichiers CSS par-page
- Details d'implementation du skeleton pulse animation
- Nommage des classes CSS pour les badges de statut

</decisions>

<code_context>
## Existing Code Insights

### Reusable Assets
- design-system.css (5,278 lignes, v2.0) — tokens OKLCH complets, @layer base/components/v4
- login.css — deja refait en v4.3 avec tokens, gradient orb, 420px card
- app.css — point d'entree universel importe par toutes les pages
- 25 fichiers CSS par-page dans public/assets/css/

### Established Patterns
- @property declarations pour les couleurs OKLCH
- @layer base, components, v4 pour la cascade
- Variables CSS: --color-*, --space-*, --text-*, --font-*
- Dark mode via prefers-color-scheme

### Integration Points
- Chaque page HTML charge app.css + son CSS specifique
- Les badges de statut apparaissent dans meetings, dashboard, hub, operator
- Loading states via classe .htmx-request ajoutee par HTMX/fetch

</code_context>

<specifics>
## Specific Ideas

- L'utilisateur veut un design moderne, pro, pas du fonctionnel plat
- Les ecrans sont horizontaux — utiliser la largeur agressivement, pas empiler verticalement
- Le login doit etre une vraie separation 2-panels (branding + form split)
- CSS infrastructure existe deja (design-system.css) — le travail est l'application uniforme

</specifics>

<deferred>
## Deferred Ideas

- Dark mode parity audit (v1.2 polish)
- Toast notification system (v1.2 polish)
- Role-specific sidebar nav (v1.2 polish)

</deferred>
