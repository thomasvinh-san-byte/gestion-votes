# Phase 3: Feedback et Etats Vides - Context

**Gathered:** 2026-04-21
**Status:** Ready for planning

<domain>
## Phase Boundary

L'utilisateur n'est jamais face a un ecran vide ou silencieux — chaque etat (vide, chargement, zero-resultat, apres-vote) a un message explicite en francais. Standardiser les etats vides, la confirmation de vote, les indicateurs de chargement, et le traitement zero-resultat des filtres.

Requirements: FEED-01, FEED-02, FEED-03, FEED-04

</domain>

<decisions>
## Implementation Decisions

### Empty State Strategy (FEED-01)
- Etendre le composant web ag-empty-state existant (supporte deja icon + titre + description + bouton CTA)
- Toutes les pages avec listes/grilles: meetings, members, archives, users, email-templates, audit
- Messages actionnables avec boutons CTA (ex: "Creez votre premiere seance" avec lien vers la creation)
- Meme composant ag-empty-state pour les etats vides imbriques (onglets operateur) — le pattern tab-empty-guide existant fonctionne deja

### Vote Confirmation & Loading (FEED-02, FEED-04)
- Confirmation de vote: garder le div vote-confirmed-state visible en permanence jusqu'a l'ouverture d'un nouveau vote (supprimer le timeout/reset de 3s) et ajouter horodatage
- Format horodatage: "Vote enregistre le 21/04/2026 a 14:32" — format date francais
- Indicateur de chargement: ajouter un label "Chargement..." a cote des skeleton loaders existants — ne pas supprimer les patterns visuels skeleton
- Afficher le texte de chargement sur les zones de contenu HTMX via .htmx-indicator — visible pendant les requetes HTMX

### No-Results & Filter Reset (FEED-03)
- Utiliser ag-empty-state avec une icone recherche/filtre, message, et lien "Reinitialiser les filtres"
- Pages avec filtres: meetings (filter pills), members (filter chips), archives (search), audit (filtres date/type)
- Le lien "Reinitialiser" efface l'etat du filtre et declenche un rechargement HTMX de la liste (hx-get sans params de filtre)
- Les dropdowns ag-searchable-select gardent leur attribut empty-text existant (deja "Aucun votant trouve")

### Claude's Discretion
- Aucun — toutes les decisions ont ete prises explicitement

</decisions>

<code_context>
## Existing Code Insights

### Reusable Assets
- public/assets/js/components/ag-empty-state.js — web component existant (icon + titre + description + CTA)
- public/assets/js/components/ag-toast.js — systeme de notification (success, error, warning, info)
- design-system.css — classes .empty-state, .skeleton, .spinner deja definies
- Pattern tab-empty-guide dans operator.htmx.html — etats vides imbriques

### Established Patterns
- Skeleton loaders avec .skeleton, .skeleton-row, .skeleton-text
- .htmx-indicator pour les indicateurs de chargement pendant les requetes HTMX
- vote-confirmed-state div pour la confirmation de vote (actuellement 3s puis reset)
- ag-searchable-select avec attribut empty-text pour les dropdowns

### Integration Points
- vote.htmx.html: div#voteConfirmedState avec vote-confirmed-choice et vote-confirmed-text
- Pages avec listes: meetings.htmx.html, members.htmx.html, archives.htmx.html, users.htmx.html, email-templates.htmx.html, audit.htmx.html
- Filter pills/chips: JS toggle qui montre/cache les elements de liste
- HTMX swap targets: les listes sont rechargees via hx-get sur les endpoints API

</code_context>

<specifics>
## Specific Ideas

- Messages en francais uniquement — jamais de texte technique en anglais visible
- Les messages vides doivent etre actionnables ("Creez votre premiere seance" pas juste "Aucune seance")
- La confirmation de vote doit rester visible (pas un flash 3 secondes)
- "Chargement..." doit etre en francais (pas "Loading...")

</specifics>

<deferred>
## Deferred Ideas

None — discussion stayed within phase scope.

</deferred>
