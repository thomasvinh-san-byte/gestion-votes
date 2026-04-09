# Phase 14: Visual Polish - Context

**Gathered:** 2026-04-09
**Status:** Ready for planning
**Mode:** Auto-generated from milestone scope

<domain>
## Phase Boundary

L'app feel pro. 4 axes de polish UX visible :
1. Toast notification system unifie (deferred de v1.1)
2. Dark mode parity audit + fixes (4 items deferred de v1.1)
3. Role-specific sidebar nav (chaque role voit son menu)
4. Micro-interactions : focus rings, hover states, loading transitions

Aucune nouvelle feature, juste polish.

</domain>

<decisions>
## Implementation Decisions

### Toast notification system
- Reuser le composant AgToast deja existant dans public/assets/js/components/ (utilise par settings.js, login.js)
- Verifier qu'il est expose globalement et appele de facon coherente sur toutes les pages
- Si missing : creer un wrapper window.AgToast.show(type, message) qui delegue au composant
- Convertir au moins 5 pages a utiliser ce systeme (au lieu de alert/inline messages)

### Dark mode parity
- Audit page-par-page en mode dark
- Fix les contrastes broken, les couleurs hard-codees qui ne basculent pas, les SVG qui ne re-tintent pas
- Tester via toggle dans /login footer (deja existant)
- Reference : design-system.css a deja les variables OKLCH dark mode

### Role-specific sidebar nav
- shared.js charge sidebar.html partial pour toutes les pages
- auth-ui.js applique deja role filtering via data-requires-role attribute (Phase 5)
- Verifier que les data-requires-role sont presents et corrects sur tous les items
- Si manquant : ajouter
- Tester avec les 4 roles : admin (tout), operator, president, votant (minimum)

### Micro-interactions
- Focus rings coherents : utilise --color-primary outline + offset
- Hover states : transform translateY(-1px) + shadow uplift sur les boutons primaires
- Loading transitions : skeleton shimmer / opacity fade-in sur les listes
- Button press feedback : transform scale(0.98)

</decisions>

<code_context>
## Existing Code Insights

### Components
- public/assets/js/components/index.js - charge AgToast et autres
- public/assets/js/core/auth-ui.js - role filtering sidebar
- public/assets/js/core/shared.js - sidebar partial loader
- public/partials/sidebar.html - sidebar template

### Design system
- design-system.css v2.0 - tokens dark mode, transitions, shadows
- @layer base, components, v4, pages

### Pages a polir
- Toutes les 21 pages applicatives
- Focus prioritaire sur celles ou l'utilisateur passe le plus de temps : dashboard, operator, hub, vote, settings

</code_context>

<specifics>
## Specific Ideas

- L'utilisateur a clairement dit "polish post-MVP" — pas de nouvelle feature
- Les 4 items de Phase 14 viennent de tech debt v1.1 deferred
- Tester chaque polish via Playwright extensions des critical-path specs existants

</specifics>

<deferred>
## Deferred Ideas

- Animations Lottie / illustrations vectorielles - hors scope
- Theme switcher avance (multiple themes) - hors scope, juste light/dark
- Sidebar collapsible avec animation - hors scope, sidebar reste statique

</deferred>
