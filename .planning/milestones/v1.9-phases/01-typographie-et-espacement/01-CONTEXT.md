# Phase 1: Typographie et Espacement - Context

**Gathered:** 2026-04-21
**Status:** Ready for planning

<domain>
## Phase Boundary

Les textes sont lisibles et l'espacement est confortable sur toutes les pages — la fondation visuelle sur laquelle les phases suivantes s'appuient. Cible les tokens CSS fondamentaux: base font-size, labels, header, et spacing entre elements.

Requirements: TYPO-01, TYPO-02, TYPO-03, TYPO-04

</domain>

<decisions>
## Implementation Decisions

### Token Migration Strategy
- Changer --text-base de 0.875rem (14px) a 1rem (16px) — tous les composants utilisant le token se mettent a jour automatiquement
- --text-md reste a 1rem (16px), effectivement egal a la nouvelle base — fusion ulterieure si necessaire
- Mettre a jour --type-label-size de --text-sm a --text-base (nouveau 16px), supprimer text-transform:uppercase et couleur muted sur .form-label
- Creer des alias semantiques --form-gap: var(--space-5) (20px) et --section-gap: var(--space-6) (24px) — reutiliser l'echelle existante

### Header Redesign
- Breadcrumb + titre de page uniquement (supprimer sous-titre et barre decorative)
- Les boutons d'action descendent sous le header dans une barre toolbar — le header reste propre a 64px
- Breadcrumb texte simple avec separateur "/" a taille --text-sm, couleur muted

### Scope & Cascade
- Appliquer a toutes les pages en une fois via changement de tokens — la cascade CSS gere la propagation globale
- Inclure la page login — elle utilise les memes tokens du design-system
- Verification manuelle des pages cles (login, dashboard, meetings, vote, wizard) + tests E2E existants
- Le dark theme herite automatiquement des memes changements de tokens (tokens agnostiques au theme pour le sizing)

### Claude's Discretion
- Aucun — toutes les decisions ont ete prises explicitement

</decisions>

<code_context>
## Existing Code Insights

### Reusable Assets
- design-system.css (157KB) — systeme de tokens complet avec echelle typographique --text-2xs a --text-5xl
- Echelle d'espacement --space-1 (4px) a --space-24 (96px) en grille de 4px
- Alias de tokens labels: --type-label-size, --type-label-weight, --type-label-lead

### Established Patterns
- Tokens CSS avec var() sur tout le codebase — changement de token = propagation globale
- Couleurs oklch() avec support theme clair/sombre
- Alias semantiques existants pour typographie (--type-page-title, --type-section-title, etc.)

### Integration Points
- body font-size dans design-system.css ligne ~89: font-size: var(--text-base)
- .form-label dans design-system.css ligne ~1813: text-transform: uppercase + color: var(--color-text-muted)
- --header-height dans design-system.css ligne ~486: 56px
- .nav-group-label dans design-system.css: font-size: 12px, text-transform: uppercase

</code_context>

<specifics>
## Specific Ideas

No specific requirements — open to standard approaches using existing token system.

</specifics>

<deferred>
## Deferred Ideas

None — discussion stayed within phase scope.

</deferred>
