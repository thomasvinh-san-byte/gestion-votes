# Phase 4: Clarte et Jargon - Context

**Gathered:** 2026-04-21
**Status:** Ready for planning

<domain>
## Phase Boundary

L'interface parle la langue de l'utilisateur — zero terme technique cote votant, tooltips explicatifs cote admin, confirmations simples. Couvre l'elimination du jargon, l'ajout de tooltips, le remplacement du pattern "tapez VALIDER", et les descriptions d'export.

Requirements: CLAR-01, CLAR-02, CLAR-03, CLAR-04

</domain>

<decisions>
## Implementation Decisions

### Voter-Facing Jargon Elimination (CLAR-01)
- Remplacer "Quorum" par "Seuil de participation" sur public.htmx.html (page de projection visible par les votants)
- Reecrire les sections techniques du FAQ (help.htmx.html) en francais simple: "empreinte numerique" au lieu de "SHA-256", etc.
- Les pages admin-only (postsession, settings, audit, trust) gardent leurs termes techniques — CLAR-02 ajoute des tooltips a la place

### Confirmation Pattern & Tooltips (CLAR-02, CLAR-03)
- CLAR-03: Remplacer "tapez VALIDER" (validate.htmx.html) par un modal avec checkbox "Je confirme cette action" + bouton "Confirmer"
- CLAR-02: Tooltips sur tous les termes techniques admin: quorum, procuration, eIDAS, SHA-256, CNIL sur les pages operator, settings, postsession, audit, trust
- Utiliser le composant ag-tooltip existant (deja 100+ usages dans le codebase)
- Texte des tooltips en francais — ex: "Quorum: nombre minimum de votants requis pour que le scrutin soit valide"

### Export Descriptions (CLAR-04)
- Description visible sous chaque bouton d'export en <small> ou .export-desc — visible sans hover
- Tous les boutons d'export: modal archives (6 boutons), page audit (2 boutons), postsession PDF, trust export
- Style: une ligne en francais decrivant le contenu du fichier (ex: "Liste des votants et leurs choix par scrutin")

### Claude's Discretion
- Aucun — toutes les decisions ont ete prises explicitement

</decisions>

<code_context>
## Existing Code Insights

### Reusable Assets
- ag-tooltip component (public/assets/js/components/ag-tooltip.js) — CSS-only hover, deja 100+ usages
- Modal pattern existant dans le codebase (utilise dans archives, wizard, etc.)
- Pattern de confirmation existant dans validate.htmx.html (tapez VALIDER)

### Established Patterns
- ag-tooltip avec attributs text="" et position="top|bottom"
- Modals avec fond sombre + contenu centre
- Export buttons dans archives modal (6 boutons), audit toolbar, postsession, trust

### Integration Points
- public.htmx.html: quorum display lines 55-62, 150-160
- help.htmx.html: FAQ sections avec SHA-256, token, procuration
- validate.htmx.html: "tapez VALIDER" pattern line 224-225
- archives.htmx.html: modal export lines 189-240 (6 boutons)
- audit.htmx.html: export buttons lines 59-65
- postsession.htmx.html: eIDAS lines 293-300, export PDF line 326
- settings.htmx.html: CNIL line 268-271, quorum line 112
- trust.htmx.html: SHA-256 lines 129-133, export buttons lines 42-46
- operator.htmx.html: quorum, procurations

</code_context>

<specifics>
## Specific Ideas

- Le votant ne doit JAMAIS voir: eIDAS, SHA-256, quorum, CNIL, hash, token
- Les equivalents comprehensibles: "seuil de participation" (quorum), "empreinte numerique" (hash/SHA-256), "procuration" peut rester car c'est du francais courant
- Le modal de confirmation remplace un input texte par une checkbox — plus simple, plus accessible

</specifics>

<deferred>
## Deferred Ideas

None — discussion stayed within phase scope.

</deferred>
