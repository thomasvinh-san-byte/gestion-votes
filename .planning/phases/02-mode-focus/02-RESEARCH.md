# Phase 2: Mode Focus - Research

**Researched:** 2026-04-29
**Domain:** Frontend HTML/CSS/JS — vue epuree a 5 zones dans operator.htmx.html (mode execution)
**Confidence:** HIGH

---

<phase_requirements>
## Phase Requirements

| ID | Description | Research Support |
|----|-------------|-----------------|
| FOCUS-01 | En mode execution, l'interface affiche exactement 5 zones: titre motion, resultat vote, quorum status, chronometre, actions — autres zones masquees | DOM mappe ligne par ligne ci-dessous; les 5 zones cibles sont identifiees; les zones a masquer (status bar, KPI strip, tags, resolution-progress, guidance, sub-tabs Avance/Presences, exec-meta-row, speaker panel, manual vote list) sont enumerees |
| FOCUS-02 | Boutons lancer vote, fermer scrutin, passer motion restent cliquables et visibles dans la vue focus sans scrolling | `.op-action-bar` (#opActionBar) contient deja `opBtnProclaim` + `opBtnToggleVote` (lancer/fermer dynamiquement); `.op-exec-header-right` contient `opBtnCloseSession`. Pour "passer motion": ajouter un bouton dedie ou conserver `opBtnNextVote` dans `.op-post-vote-guidance`. Le pattern CSS `position: sticky` + `.op-action-bar` deja en place |
| FOCUS-03 | Toggle visible permet basculer vue complete <-> vue focus, etat persiste pendant la seance | `sessionStorage` deja utilise (`opChecklistCollapsed` Phase 1) — meme pattern via cle `opFocusMode`; bouton place dans `.op-exec-header-right` a cote du chrono |
</phase_requirements>

---

## Summary

Phase 2 est entierement frontend, isofunctionnelle (aucune nouvelle donnee) : on ajoute une classe CSS sur le conteneur `#viewExec` pour basculer en mode focus, on cache les zones non-essentielles via selecteurs CSS descendants, et on persiste l'etat dans `sessionStorage`. Aucune modification PHP, aucun nouvel endpoint, aucun nouveau JS metier.

L'infrastructure DOM existe deja: les 5 zones cibles sont des elements distincts avec des IDs stables (`opExecTitle`, `execActiveVote`, `opChecklistRowQuorum`, `opExecTimer`, `opActionBar`/`opBtnCloseSession`). Le mecanisme de persistance Phase 1 (`sessionStorage.opChecklistCollapsed` + restore dans `setMode('exec')`) est directement reproductible. Le toggle s'integre dans la barre exec `.op-exec-header-right` deja occupee par le chrono et le bouton Cloturer.

**Decision architecturale clef:** appliquer la classe `.op-focus-mode` sur le conteneur `#viewExec` (pas sur `body`, pas via `[data-focus]`). Raisons: (1) scope visuel limite a la vue exec, (2) coherent avec `.op-checklist-panel--collapsed` (modificateur sur le conteneur), (3) un selecteur CSS unique `.op-focus-mode .X { display: none }` couvre toutes les zones masquees, (4) survit a `setMode('setup')` puis `setMode('exec')` car `#viewExec` persiste dans le DOM.

**Primary recommendation:** Implementer en 2 plans: (a) HTML + CSS — ajouter le bouton toggle dans `.op-exec-header-right`, ajouter la regle CSS `.op-focus-mode { ... }` avec ~10 selecteurs `display: none`, ajouter une regle pour rendre `.op-action-bar` sticky en focus mode; (b) JS wiring — handler click du toggle dans operator-tabs.js (idempotent + sessionStorage), restoration dans `setMode('exec')` apres bloc `opChecklistPanel`, aria-pressed sync.

---

## User Constraints (no CONTEXT.md found, scope inferred from prompt)

### Locked Decisions
- Modifie uniquement `public/operator.htmx.html` et fichiers compagnons CSS/JS — aucun changement PHP
- Sit on top of Phase 1 (panneau checklist deja present) — la checklist DOIT rester visible en mode focus
- 5 zones exactement: titre motion, resultat vote, quorum status, chronometre, actions
- Toggle visible (pas un raccourci clavier qui serait hors-scope projet selon ROADMAP "Out of Scope")
- Persistence pendant la seance (sessionStorage, pas localStorage)

### Claude's Discretion
- Choix CSS scope (body vs container vs data-attribute) — **decision: container `#viewExec` via classe `.op-focus-mode`**
- Position exacte du bouton toggle dans le DOM — **decision: dans `.op-exec-header-right` a gauche du chrono**
- Icone et label du toggle — **decision: `icon-zoom-in`/`icon-zoom-out` ou `icon-eye`/`icon-eye` avec label "Focus" / "Vue complete"**
- Comportement de `.op-action-bar` en focus (sticky bottom recommandee pour FOCUS-02 sans scroll)
- Liste exacte des zones a masquer (a affiner via plan)

### Deferred Ideas (OUT OF SCOPE)
- Raccourcis clavier (declare hors perimetre dans PROJECT.md)
- Animation de transition entre vue complete et focus (overflow CSS pas cassant, mais hors scope; reserver pour Phase 3 si besoin)
- Mode focus en preparation (focus est intrinsequement lie au mode exec)

---

## Standard Stack

### Core

| Composant | Version/Source | Purpose | Why Standard |
|-----------|---------------|---------|--------------|
| HTML natif | — | Bouton toggle dans `.op-exec-header-right` | Stack HTMX, pas de framework JS UI |
| CSS class modifier | `.op-focus-mode` sur `#viewExec` | Toggle de visibilite des zones | Pattern etabli `.op-checklist-panel--collapsed` Phase 1 |
| `sessionStorage` | API navigateur | Persistance etat focus pendant la seance | Pattern etabli `opChecklistCollapsed` Phase 1, `vote.js`, `hub.js` |
| Design tokens | `--color-*`, `--space-*`, `--text-*` | Bouton toggle styling | Tokens deja utilises dans la barre exec |
| `aria-pressed` | ARIA | Etat du bouton toggle | Pattern existant: `btnModeSetup`/`btnModeExec` lignes 91-92 utilisent `aria-pressed` |

### Fichiers modifies

| Fichier | Type de changement |
|---------|--------------------|
| `public/operator.htmx.html` | Ajout bouton toggle dans `.op-exec-header-right` (1 element, ~5 lignes) |
| `public/assets/css/operator.css` | Ajout regle `.op-focus-mode .X { display: none }` + sticky `.op-action-bar` + transition opt (~30 lignes) |
| `public/assets/js/pages/operator-tabs.js` | Click handler du toggle + restore dans `setMode('exec')` (~25 lignes), pres du bloc Phase 1 ligne 2104-2116 / 3151-3161 |

Aucun fichier PHP. Aucune route. Aucun endpoint API. **Aucune modification de** `operator-exec.js` **ou** `operator-realtime.js` **n'est necessaire** — la logique focus est une preoccupation de presentation pure.

---

## Map of Existing Exec Mode DOM Zones

Inventaire complet de `#viewExec` (operator.htmx.html lignes 1167-1584). Les **5 zones gardees** sont marquees [KEEP], les autres [HIDE].

### Header `.op-exec-header` (lignes 1170-1186)
| Element | ID | Statut | Notes |
|---------|----|---------|----|
| `.op-live-dot` | — | KEEP | Indicateur visuel discret |
| `h1.op-exec-title` | `opExecTitle` | **KEEP — Zone 1: titre seance** | Affiche le titre de la seance (peut etre re-purpose pour titre motion ou ajouter un sous-element pour le titre motion actif) |
| `.op-live-chrono` | `opExecTimer` | **KEEP — Zone 4: chronometre** | Mis a jour par `updateExecHeaderTimer()` (operator-exec.js:371-388) toutes les secondes |
| `#opBtnRoomDisplay` | — | HIDE | Bouton "Projection" (lien externe) |
| `#opBtnCloseSession` | `opBtnCloseSession` | **KEEP — Zone 5: actions** | "Cloturer" la seance |

### Status & KPI strip (lignes 1192-1234)
| Element | ID | Statut | Notes |
|---------|----|---------|----|
| `.op-exec-status-bar` | `opExecStatusBar` | HIDE | Bandeau "Seance en cours" + En direct + connectes + elapsed |
| `.op-kpi-strip` | `opKpiStrip` | HIDE | 7 KPIs: PRESENTS, QUORUM, ONT VOTE, RESOLUTION, DUREE, EN LIGNE, INACTIFS — redondant avec checklist |

### Tags & progress (lignes 1237-1255)
| Element | ID | Statut | Notes |
|---------|----|---------|----|
| `.op-tags` | `opTags` | HIDE | Tags contextuels (quorum, correspondance, procurations) |
| `.op-resolution-progress` | `opResolutionProgress` | HIDE | Barre segmentee de progression resolutions |

### Guidance & close banner (lignes 1258-1282)
| Element | ID | Statut | Notes |
|---------|----|---------|----|
| `.op-guidance` | `opGuidance` | HIDE | "Ouvrez un vote pour commencer" |
| `.exec-close-banner` | `execCloseBanner` | KEEP (conditionnel) | Affiche quand la seance peut etre cloturee — fait partie zone actions, hidden de toute facon par defaut |

### Body & main (lignes 1285-1515)
| Element | ID | Statut | Notes |
|---------|----|---------|----|
| `.op-exec-body` | — | KEEP (conteneur Phase 1) | flex row pour main + checklist |
| `.op-exec-main` | — | KEEP (conteneur) | flex 1, scrollable |
| `.op-resolution-card` | `opResolutionCard` | KEEP (parent) | Conteneur de la zone resultat vote |
| `.op-resolution-header` | — | HIDE | Header avec live dot + h2 + tags (deja affiche dans le titre header) |
| `.op-resolution-title` | `opResTitle` | KEEP — peut servir de titre motion si on prefere | Alternative au titre seance pour Zone 1 |
| `.op-tabs#opSubTabs` | `opSubTabs` | HIDE | 3 onglets Resultat/Avance/Presences — focus = uniquement Resultat |
| `.op-tab-panel#opPanelResultat` | `opPanelResultat` | KEEP (parent) | Panneau actif par defaut |
| `#execNoVote` | `execNoVote` | KEEP (conditionnel) | Affiche quand pas de vote ouvert |
| `#opPostVoteGuidance` | `opPostVoteGuidance` | KEEP (conditionnel) | Contient `opBtnNextVote` ("Vote suivant") = **passer motion** pour FOCUS-02 |
| `#opEndOfAgenda` | `opEndOfAgenda` | KEEP (conditionnel) | Contient `opBtnEndSession` |
| **`.op-vote-card#execActiveVote`** | `execActiveVote` | **KEEP — Zone 2: resultat vote** | Carte centrale: live badge + titre + counters + bars + participation |
| `.exec-live-badge` | `execLiveBadge` | KEEP | Badge "VOTE EN COURS" |
| `.exec-vote-title` | `execVoteTitle` | KEEP | Titre motion actuelle (alternative pour Zone 1) |
| `.op-vote-counters` | — | KEEP | Pour/Contre/Abstention (avec aria-live) |
| `.op-vote-bars` | `opVoteBars` | KEEP | Barres de progression Pour/Contre/Abst |
| `.exec-participation-row` | — | KEEP | Bandeau de participation |
| `.exec-meta-row` | — | HIDE (deja `hidden` ligne 1374) | RAS |
| `.op-equality-warning` | `opEqualityWarning` | KEEP (conditionnel) | Avertissement egalite |
| `.exec-speaker-panel` | `execSpeakerInfo` | HIDE | Speaker queue (peripherique) |
| `#execManualVoteList` | `execManualVoteList` | HIDE | Liste de vote manuelle (utilisable seulement par operateur, hors flow rapide) |
| `#execManualSearch` | `execManualSearch` | HIDE | Champ de recherche manuel |
| `.op-tab-panel#opPanelAvance` | `opPanelAvance` | HIDE | Mode avance (papier, passerelle, suspendre) |
| `.op-tab-panel#opPanelPresences` | `opPanelPresences` | HIDE | Liste detaillee presences |
| **`.op-action-bar`** | `opActionBar` | **KEEP — Zone 5: actions** | Sticky bottom: `opBtnProclaim` (Proclamer) + `opBtnToggleVote` (Lancer/Fermer vote dynamique selon `currentOpenMotion.closed_at`) + `execBtnCloseVote` |

### Checklist panel (lignes 1517-1573, ajoute Phase 1)
| Element | ID | Statut | Notes |
|---------|----|---------|----|
| `aside.op-checklist-panel` | `opChecklistPanel` | KEEP (parent) | Affiche les 4 indicateurs (sse, quorum, votes, online) |
| `#opChecklistRowQuorum` | — | **KEEP — Zone 3: quorum status** | Indicateur quorum binaire ok/alert |
| `#opChecklistRowSse` | — | KEEP | SSE indicator (utile en focus aussi, alerte vie ou mort) |
| `#opChecklistRowVotes` | — | KEEP | Votes recus en temps reel |
| `#opChecklistRowOnline` | — | KEEP | Votants en ligne |

### Mapping final 5 zones cibles (FOCUS-01)

| Zone | Element | ID | Note |
|------|---------|----|----|
| 1. Titre motion | `.exec-vote-title` | `execVoteTitle` | Dans `#execActiveVote` — alternative: `opResTitle` ou `opExecTitle` (titre seance) |
| 2. Resultat vote | `#execActiveVote` (entierement) | `execActiveVote` | Carte centrale avec counters + bars + participation |
| 3. Quorum status | `#opChecklistRowQuorum` (panneau checklist) | `opChecklistRowQuorum` | Le panneau Phase 1 fournit deja l'affichage |
| 4. Chronometre | `.op-live-chrono` (header) | `opExecTimer` | Dans `.op-exec-header-right` |
| 5. Actions | `.op-action-bar` + `#opBtnCloseSession` (header) | `opActionBar` + `opBtnCloseSession` | Lancer/Fermer dans `opBtnToggleVote`, "Vote suivant" dans `opBtnNextVote` (post-vote guidance), Cloturer seance dans `opBtnCloseSession` (header) |

**Note importante pour FOCUS-02:** Le bouton "Passer motion" n'a pas un ID unique stable — c'est `opBtnNextVote` qui apparait conditionnellement dans `.op-post-vote-guidance` quand un vote vient d'etre cloture. C'est l'unique chemin pour "passer a la motion suivante". En focus mode, la `.op-post-vote-guidance` doit donc rester visible (KEEP conditionnel), OU on doit dupliquer un bouton dans la `.op-action-bar`.

---

## Existing sessionStorage / Mode Toggle Patterns to Reuse

### Pattern 1: Phase 1 checklist collapse persistence (operator-tabs.js:3151-3161)

```javascript
// Source: /public/assets/js/pages/operator-tabs.js:3151-3161
var checklistToggleBtn = document.getElementById('opChecklistToggle');
if (checklistToggleBtn) {
  checklistToggleBtn.addEventListener('click', function() {
    var panel = document.getElementById('opChecklistPanel');
    if (!panel) return;
    var isCollapsed = panel.classList.toggle('op-checklist-panel--collapsed');
    this.setAttribute('aria-expanded', String(!isCollapsed));
    this.title = isCollapsed ? 'Afficher le panneau de controle' : 'Reduire le panneau';
    sessionStorage.setItem('opChecklistCollapsed', String(isCollapsed));
  });
}
```

**Adaptable pattern for focus mode:**
```javascript
var focusToggleBtn = document.getElementById('opBtnFocusMode');
if (focusToggleBtn) {
  focusToggleBtn.addEventListener('click', function() {
    var view = document.getElementById('viewExec');
    if (!view) return;
    var isFocus = view.classList.toggle('op-focus-mode');
    this.setAttribute('aria-pressed', String(isFocus));
    this.title = isFocus ? 'Vue complete' : 'Mode focus';
    sessionStorage.setItem('opFocusMode', String(isFocus));
  });
}
```

### Pattern 2: Phase 1 restoration in setMode (operator-tabs.js:2103-2116)

```javascript
// Source: /public/assets/js/pages/operator-tabs.js:2103-2116
// Show/hide checklist panel based on mode (CHECK panel visibility)
var checklistPanel = document.getElementById('opChecklistPanel');
if (checklistPanel) {
  if (mode === 'exec') {
    checklistPanel.hidden = false;
    var collapsed = sessionStorage.getItem('opChecklistCollapsed') === 'true';
    checklistPanel.classList.toggle('op-checklist-panel--collapsed', collapsed);
    var toggleBtn = document.getElementById('opChecklistToggle');
    if (toggleBtn) toggleBtn.setAttribute('aria-expanded', String(!collapsed));
  } else {
    checklistPanel.hidden = true;
  }
}
```

**Adaptable pattern (added immediately after, in the same `if (mode === 'exec')` block):**
```javascript
// Restore focus mode state from sessionStorage
var isFocus = sessionStorage.getItem('opFocusMode') === 'true';
viewExec.classList.toggle('op-focus-mode', isFocus);
var focusBtn = document.getElementById('opBtnFocusMode');
if (focusBtn) focusBtn.setAttribute('aria-pressed', String(isFocus));
```

### Pattern 3: aria-pressed two-state buttons (already used)

```html
<!-- Source: operator.htmx.html:91-92 — modeSwitch buttons -->
<button class="mode-switch-btn active" id="btnModeSetup" aria-pressed="true">Préparation</button>
<button class="mode-switch-btn" id="btnModeExec" aria-pressed="false">Exécution</button>
```

Le pattern `aria-pressed="true|false"` est l'equivalent ARIA correct pour un toggle button (vs `aria-expanded` qui est pour un disclosure).

---

## CSS Approach: Container Class on `#viewExec`

### Decision: `.op-focus-mode` class on `#viewExec`

| Approach | Pros | Cons | Verdict |
|----------|------|------|---------|
| `body.op-focus-mode` | Simple selectors, global scope | Pollue body avec etat de la vue exec; reset complique au `setMode('setup')`; peut affecter modales/popovers/toasts inattendus | NO |
| `[data-focus="true"]` on `#viewExec` | Modern, attribute-driven | Selecteurs `[data-focus="true"] .X` plus verbeux qu'une classe; pas de difference fonctionnelle | NO |
| **`.op-focus-mode` on `#viewExec`** | **Scope limite a la vue exec; pattern coherent avec `.op-checklist-panel--collapsed` Phase 1; selecteurs CSS simples `.op-focus-mode .X`; survit aux switches de mode car `#viewExec` persiste; coherent avec `aria-pressed` sur le bouton** | Necessite que les zones a cacher soient bien dans `#viewExec` (toutes le sont) | **YES** |

### CSS Pattern (a creer)

```css
/* Source: nouveau bloc dans operator.css apres la section checklist (ligne ~2117) */

/* =============================================================================
   FOCUS MODE — vue epuree avec 5 zones essentielles uniquement
   ============================================================================= */

/* Zones masquees en mode focus (FOCUS-01) */
.op-focus-mode .op-exec-status-bar,
.op-focus-mode .op-kpi-strip,
.op-focus-mode .op-tags,
.op-focus-mode .op-resolution-progress,
.op-focus-mode .op-guidance,
.op-focus-mode .op-resolution-header,
.op-focus-mode .op-tabs,
.op-focus-mode .op-tab-panel:not(.active),
.op-focus-mode .exec-speaker-panel,
.op-focus-mode #execManualVoteList,
.op-focus-mode #execManualSearch,
.op-focus-mode #opBtnRoomDisplay {
  display: none !important;
}

/* Zone 5 actions: sticky bottom pour rester visible sans scroll (FOCUS-02) */
.op-focus-mode .op-action-bar {
  position: sticky;
  bottom: 0;
  z-index: 10;
  box-shadow: 0 -2px 8px var(--color-backdrop-soft, rgba(0,0,0,0.05));
}

/* Augmenter le titre motion en mode focus pour la lisibilite */
.op-focus-mode .exec-vote-title {
  font-size: var(--text-2xl);
  margin-bottom: var(--space-4);
}

/* Donner plus d'espace au resultat vote */
.op-focus-mode .op-vote-card {
  padding: var(--space-6) var(--space-4);
}

/* Bouton toggle focus dans le header */
.op-focus-toggle {
  display: inline-flex;
  align-items: center;
  gap: var(--space-1);
  padding: 0.375rem 0.625rem;
  border: 1px solid var(--color-border);
  background: transparent;
  color: var(--color-text-muted);
  border-radius: var(--radius-md);
  cursor: pointer;
  font-size: var(--text-sm);
}
.op-focus-toggle[aria-pressed="true"] {
  background: var(--color-primary-subtle);
  color: var(--color-primary-text);
  border-color: var(--color-primary);
}
.op-focus-toggle:hover {
  background: var(--color-bg-subtle);
}
```

---

## Toggle Button Placement

### Decision: dans `.op-exec-header-right`, a gauche du chrono `opExecTimer`

**Localisation HTML:** operator.htmx.html lignes 1175-1185 (`.op-exec-header-right`).

**Contexte actuel:**
```html
<div class="op-exec-header-right">
  <span class="op-live-chrono" id="opExecTimer">00:00:00</span>
  <a class="btn btn-sm btn-secondary" id="opBtnRoomDisplay" ...>... Projection</a>
  <ag-tooltip text="Cloturer la seance en cours" position="bottom">
    <button class="btn btn-sm btn-danger" id="opBtnCloseSession">... Cloturer</button>
  </ag-tooltip>
</div>
```

**Apres ajout (FOCUS-03):**
```html
<div class="op-exec-header-right">
  <ag-tooltip text="Basculer entre vue complete et vue focus" position="bottom">
    <button class="op-focus-toggle btn btn-sm btn-secondary" id="opBtnFocusMode"
            type="button" aria-pressed="false" title="Mode focus">
      <svg class="icon icon-text" aria-hidden="true"><use href="/assets/icons.svg#icon-zoom-in"></use></svg>
      <span class="op-focus-toggle-label">Focus</span>
    </button>
  </ag-tooltip>
  <span class="op-live-chrono" id="opExecTimer">00:00:00</span>
  <!-- ...reste inchange... -->
</div>
```

**Pourquoi cet emplacement:**
1. Toujours visible en mode exec (header reste affiche)
2. Pres du chrono qui est lui aussi conserve en focus — coherence visuelle
3. Pas dans `.op-meeting-bar` (deja chargee en mode/cloturer/aide), pas dans `.op-action-bar` (focalise sur les actions de vote)
4. Pas masque par la regle CSS `.op-focus-mode .X { display: none }` car `.op-exec-header` n'est pas dans la liste

**Icone:**
- `icon-zoom-in` (presente dans le sprite, ligne 76 de l'enumeration) pour activer le focus
- En mode focus active (aria-pressed=true), l'icone reste la meme — c'est la classe CSS qui change l'apparence (background primary-subtle)
- Alternative: `icon-eye` — mais less explicite. `icon-zoom-in` evoque mieux le "zoom" sur l'essentiel.

**Label:** "Focus" — court, en francais, coherent avec le projet.

**Tooltip:** "Basculer entre vue complete et vue focus" — explicite l'action toggle.

---

## Don't Hand-Roll

| Problem | Don't Build | Use Instead | Why |
|---------|-------------|-------------|-----|
| Persistance etat focus | Cookie ou localStorage | `sessionStorage` cle `opFocusMode` | Pattern UX etabli Phase 1, scope session, automatique cleanup |
| Mecanisme show/hide multi-zone | Multi `[hidden]` ou JS toggleAttribute par element | Classe CSS unique `.op-focus-mode` + selecteurs descendants | Pattern Phase 1 `.op-checklist-panel--collapsed`; un seul JS toggle, le CSS fait le travail |
| Bouton toggle | Custom component | `<button>` standard avec `aria-pressed` + classe CSS active | Pattern existant `btnModeSetup`/`btnModeExec` — coherence ARIA |
| Sticky action bar en focus | JavaScript scroll listener | CSS `position: sticky; bottom: 0` | Native CSS, zero JS, performant |
| Restoration au switch de mode | Listener `setMode` + extra logic | Bloc dans la fonction `setMode` existante (apres bloc Phase 1 ligne 2103-2116) | Centralise, deja le bon endroit |
| Calcul des zones a masquer | API/JS de classification | Liste statique de selecteurs CSS | Les zones sont structurellement identifiees, pas de logique dynamique |

---

## Common Pitfalls

### Pitfall 1: Chrono `opExecTimer` qui s'arrete

**What goes wrong:** Si on touche a la fonction `setMode()` ou si on supprime/recree le DOM `#opExecTimer`, l'interval `_execTimerInterval` (operator-exec.js:26) garde une reference a un element detruit ou ne s'execute plus.

**Why it happens:** `startExecTimer()` est appele dans `setMode('exec')` (ligne 2100). Si le focus mode declenchait un re-render du `#viewExec`, l'interval continuerait sur un element detruit.

**How to avoid:** Le focus mode N'AGIT PAS sur le DOM (pas d'innerHTML, pas de removeChild). Il ajoute UNE classe sur `#viewExec`. Le chrono `#opExecTimer` n'est jamais detruit ni recree. Le timer JS continue inchange.

**Warning signs:** Chrono fige a une heure, ou disparait en mode focus. Signal pour verifier que la regle CSS ne masque pas accidentellement `.op-live-chrono` ou son parent.

### Pitfall 2: Panneau checklist masque par erreur en focus mode

**What goes wrong:** Phase 1 panneau checklist `.op-checklist-panel` doit rester visible en focus mode (Zone 3 quorum status vit dedans). Une regle CSS trop large (`.op-focus-mode aside { display: none }`) pourrait le casser.

**Why it happens:** Selecteur CSS trop generique masque des zones essentielles.

**How to avoid:** Liste BLANCHE explicite des zones a masquer (selecteurs precis avec classes ou IDs). Ne JAMAIS utiliser `.op-focus-mode aside`, `.op-focus-mode > *`, ou un selecteur generique. Verifier visuellement que le panneau checklist reste affiche.

**Warning signs:** En focus mode, le panneau checklist disparait. Test e2e: assertion sur `#opChecklistPanel:not([hidden])` apres toggle focus.

### Pitfall 3: Boutons d'action pas accessibles sans scroll (FOCUS-02 viole)

**What goes wrong:** Si `.op-action-bar` n'est pas en `position: sticky` et que le contenu de `#execActiveVote` (counters + bars + participation) est plus haut que la viewport, l'utilisateur doit scroller pour cliquer Proclamer / Lancer-Fermer le vote.

**Why it happens:** Le layout `.op-exec-main` a `overflow-y: auto` (operator.css:746-753) — l'action bar peut sortir de la zone visible.

**How to avoid:** Ajouter `position: sticky; bottom: 0` sur `.op-action-bar` UNIQUEMENT en mode focus (`.op-focus-mode .op-action-bar`). Cela colle la barre au bas du conteneur `.op-exec-main` scrollable. Tester sur viewport 1080p (cible projet).

**Warning signs:** Test manuel: en focus mode, scroller vers le bas dans la carte de vote — la barre d'actions doit suivre. Test e2e: `await expect(actionBar).toBeInViewport()` apres scroll.

### Pitfall 4: Etat focus mode survit a `setMode('setup')`

**What goes wrong:** L'utilisateur active focus, switche en preparation, revient en exec — la classe `.op-focus-mode` est-elle toujours sur `#viewExec`?

**Why it happens:** La classe est sur `#viewExec` qui persiste dans le DOM (juste `hidden`). En sortie de `setMode('setup')`, la classe reste mais le mode est invisible. En revenant `setMode('exec')`, le bloc de restoration depuis `sessionStorage` re-applique l'etat.

**How to avoid:** Le pattern correct est exactement Pattern 2 ci-dessus: lire `sessionStorage.opFocusMode` et faire `viewExec.classList.toggle('op-focus-mode', isFocus)` dans `setMode('exec')`. Cela synchronise le DOM avec l'etat persiste meme si l'utilisateur a manipule la classe entre-temps.

**Warning signs:** Etat focus reste actif apres revisite de la page (sessionStorage devrait persister), ou s'efface apres un cycle setup/exec (sessionStorage devrait survivre).

### Pitfall 5: `prefers-reduced-motion` pour le bouton toggle / transition

**What goes wrong:** Si on ajoute une `transition` sur `.op-focus-mode` ou sur le toggle, les utilisateurs avec `prefers-reduced-motion: reduce` doivent etre respectes (cf. CHECK-05 Phase 1, ANIM-03 Phase 3).

**Why it happens:** Animation/transition par defaut peut etre genante pour utilisateurs sensibles.

**How to avoid:** Aucune transition sur le bouton ou le toggle de focus mode (snap immediat). Si on veut une transition douce (e.g. fade des zones masquees), wrapper dans `@media (prefers-reduced-motion: no-preference) { ... }` comme dans `.op-checklist-panel:not(.op-checklist-panel--collapsed) { transition: width 200ms }` (operator.css:2114-2116).

**Warning signs:** Si Phase 3 (Animations) introduit des transitions, verifier que le focus toggle reste instantane.

### Pitfall 6: aria-pressed pas synchronise apres restoration

**What goes wrong:** Au `setMode('exec')`, on restore la classe CSS depuis sessionStorage mais on oublie de mettre a jour `aria-pressed` du bouton toggle. Le screen reader et l'apparence visuelle divergent.

**Why it happens:** Deux sources de verite (classe CSS + attribut ARIA) doivent etre synchronises a chaque transition.

**How to avoid:** Toujours faire les deux en meme temps (cf. Pattern 2 ci-dessus). Test e2e: `await expect(focusBtn).toHaveAttribute('aria-pressed', 'true')` apres reload de la page en mode exec.

**Warning signs:** Bouton qui ne reflete pas visuellement l'etat focus apres un revisite.

### Pitfall 7: Tour onboarding casse en focus mode

**What goes wrong:** La page operateur a un onboarding `data-tour=...` (operator-header, operator-actions, operator-resolution, operator-track, operator-tabs, etc., cf. lignes 44, 87, 90, 112, 129, 140). En focus mode, certains targets sont masques (`tabsNav`, KPI strip).

**Why it happens:** Les classes `display: none` cassent les pointers du tour.

**How to avoid:** Le tour est lance via le bouton "Aide" dans `.op-meeting-bar-right` qui est HORS de `#viewExec`. Quand l'utilisateur lance un tour, le focus mode peut etre desactive automatiquement OU le tour ignore les targets `display:none`. **Recommendation:** ne pas modifier le tour, accepter que certaines etapes sont masquees en focus mode (l'utilisateur peut desactiver focus pour faire le tour). Documenter dans le SUMMARY.

**Warning signs:** Tour bloque sur une etape avec target invisible. Test manuel: lancer le tour en focus mode.

---

## Code Examples

Verified patterns from existing codebase, ready to adapt for Phase 2.

### Phase 1 setMode block (target for insertion of focus restoration)

```javascript
// Source: /public/assets/js/pages/operator-tabs.js:2103-2116
// Show/hide checklist panel based on mode (CHECK panel visibility)
var checklistPanel = document.getElementById('opChecklistPanel');
if (checklistPanel) {
  if (mode === 'exec') {
    checklistPanel.hidden = false;
    var collapsed = sessionStorage.getItem('opChecklistCollapsed') === 'true';
    checklistPanel.classList.toggle('op-checklist-panel--collapsed', collapsed);
    var toggleBtn = document.getElementById('opChecklistToggle');
    if (toggleBtn) toggleBtn.setAttribute('aria-expanded', String(!collapsed));
  } else {
    checklistPanel.hidden = true;
  }
}

// === ADD AFTER (Phase 2) ===
if (mode === 'exec' && viewExec) {
  var isFocus = sessionStorage.getItem('opFocusMode') === 'true';
  viewExec.classList.toggle('op-focus-mode', isFocus);
  var focusBtn = document.getElementById('opBtnFocusMode');
  if (focusBtn) focusBtn.setAttribute('aria-pressed', String(isFocus));
}
```

### Phase 1 toggle handler (template for focus toggle)

```javascript
// Source: /public/assets/js/pages/operator-tabs.js:3151-3161
var checklistToggleBtn = document.getElementById('opChecklistToggle');
if (checklistToggleBtn) {
  checklistToggleBtn.addEventListener('click', function() {
    var panel = document.getElementById('opChecklistPanel');
    if (!panel) return;
    var isCollapsed = panel.classList.toggle('op-checklist-panel--collapsed');
    this.setAttribute('aria-expanded', String(!isCollapsed));
    this.title = isCollapsed ? 'Afficher le panneau de controle' : 'Reduire le panneau';
    sessionStorage.setItem('opChecklistCollapsed', String(isCollapsed));
  });
}

// === ADD NEAR (Phase 2) ===
var focusToggleBtn = document.getElementById('opBtnFocusMode');
if (focusToggleBtn) {
  focusToggleBtn.addEventListener('click', function() {
    var view = document.getElementById('viewExec');
    if (!view) return;
    var isFocus = view.classList.toggle('op-focus-mode');
    this.setAttribute('aria-pressed', String(isFocus));
    this.title = isFocus ? 'Vue complete' : 'Mode focus';
    sessionStorage.setItem('opFocusMode', String(isFocus));
  });
}
```

### Existing aria-pressed two-state button pattern

```html
<!-- Source: /public/operator.htmx.html:91-92 -->
<button class="mode-switch-btn active" id="btnModeSetup" aria-pressed="true">Préparation</button>
<button class="mode-switch-btn" id="btnModeExec" aria-pressed="false">Exécution</button>
```

### Existing reduced-motion wrap pattern (Phase 1)

```css
/* Source: /public/assets/css/operator.css:2104-2117 */
@media (prefers-reduced-motion: no-preference) {
  .op-checklist-row--alert .op-checklist-icon {
    animation: checklistPulse 1s ease-in-out 3;
  }
  @keyframes checklistPulse { 0%, 100% { opacity: 1; } 50% { opacity: 0.3; } }
  .op-checklist-panel:not(.op-checklist-panel--collapsed) {
    transition: width 200ms var(--ease-standard, ease);
  }
}
```

### Existing chrono update (DO NOT MODIFY)

```javascript
// Source: /public/assets/js/pages/operator-exec.js:371-388
function updateExecHeaderTimer() {
  var el = document.getElementById('opExecTimer');
  // ...inchange — focus mode ne touche pas le timer
}
function startExecTimer() {
  stopExecTimer();
  updateExecHeaderTimer();
  _execTimerInterval = setInterval(updateExecHeaderTimer, 1000);
}
```

---

## Implementation Patterns Summary

### Pattern A: HTML — Toggle Button Placement

Insert in `.op-exec-header-right` BEFORE `#opExecTimer` (at line ~1176):

```html
<ag-tooltip text="Basculer entre vue complete et vue focus" position="bottom">
  <button class="op-focus-toggle btn btn-sm btn-secondary" id="opBtnFocusMode"
          type="button" aria-pressed="false" title="Mode focus">
    <svg class="icon icon-text" aria-hidden="true"><use href="/assets/icons.svg#icon-zoom-in"></use></svg>
    Focus
  </button>
</ag-tooltip>
```

### Pattern B: CSS — Hide List + Sticky Action Bar

Append to `operator.css` after the checklist section (after line ~2117):

```css
.op-focus-mode .op-exec-status-bar,
.op-focus-mode .op-kpi-strip,
.op-focus-mode .op-tags,
.op-focus-mode .op-resolution-progress,
.op-focus-mode .op-guidance,
.op-focus-mode .op-resolution-header,
.op-focus-mode .op-tabs,
.op-focus-mode .exec-speaker-panel,
.op-focus-mode #execManualVoteList,
.op-focus-mode #execManualSearch,
.op-focus-mode #opBtnRoomDisplay {
  display: none !important;
}

.op-focus-mode .op-action-bar {
  position: sticky;
  bottom: 0;
  z-index: 10;
  box-shadow: 0 -2px 8px rgba(0, 0, 0, 0.05);
}
```

### Pattern C: JS — Toggle Handler

Add near `checklistToggleBtn` block in operator-tabs.js (~line 3162):

```javascript
var focusToggleBtn = document.getElementById('opBtnFocusMode');
if (focusToggleBtn) {
  focusToggleBtn.addEventListener('click', function() {
    var view = document.getElementById('viewExec');
    if (!view) return;
    var isFocus = view.classList.toggle('op-focus-mode');
    this.setAttribute('aria-pressed', String(isFocus));
    this.title = isFocus ? 'Vue complete' : 'Mode focus';
    sessionStorage.setItem('opFocusMode', String(isFocus));
  });
}
```

### Pattern D: JS — setMode Restoration

Add after the existing checklistPanel block in `setMode()` (~line 2117):

```javascript
if (mode === 'exec' && viewExec) {
  var isFocusMode = sessionStorage.getItem('opFocusMode') === 'true';
  viewExec.classList.toggle('op-focus-mode', isFocusMode);
  var focusBtn = document.getElementById('opBtnFocusMode');
  if (focusBtn) focusBtn.setAttribute('aria-pressed', String(isFocusMode));
}
```

### Pattern E: Hidden conditional zones (KEEP-conditional)

Some zones are KEEP but conditionally `[hidden]` by JS (like `execCloseBanner`, `opPostVoteGuidance`, `opEndOfAgenda`, `execActiveVote`). The CSS `.op-focus-mode .X { display: none }` rule MUST NOT include them because their visibility is already managed correctly. The focus mode adds NO new conditional logic, just an extra layer of visibility filtering on the structural zones.

---

## Validation Architecture

### Test Framework
| Property | Value |
|----------|-------|
| Framework | Playwright (tests/e2e/) |
| Config file | `tests/e2e/playwright.config.js` |
| Quick run command | `npx playwright test tests/e2e/specs/operator-focus-mode.spec.js` |
| Full suite command | `npx playwright test tests/e2e/specs/` |

### Phase Requirements -> Test Map

| Req ID | Behavior | Test Type | Automated Command | File Exists? |
|--------|----------|-----------|-------------------|-------------|
| FOCUS-01 | Toggle ajoute classe `.op-focus-mode` sur `#viewExec`, zones non-essentielles `display: none` (KPI strip, tags, status bar, etc.); les 5 zones cibles restent visibles | e2e DOM assertion | `npx playwright test tests/e2e/specs/operator-focus-mode.spec.js` | ❌ Wave 0 |
| FOCUS-02 | En focus mode, `#opActionBar`, `#opBtnCloseSession` et `#opBtnNextVote` (post-vote) sont visibles dans la viewport sans scroll (sticky bottom) | e2e visibility + viewport | `npx playwright test tests/e2e/specs/operator-focus-mode.spec.js` | ❌ Wave 0 |
| FOCUS-03 | Click sur `#opBtnFocusMode` toggle l'etat; `aria-pressed` mis a jour; `sessionStorage.opFocusMode` persiste; au reload de la page exec, l'etat focus est restaure | e2e click + storage assertion | `npx playwright test tests/e2e/specs/operator-focus-mode.spec.js` | ❌ Wave 0 |

Note: FOCUS-01 peut etre verifie de maniere robuste en assertant la classe CSS sur `#viewExec` + l'invisibilite (`toBeHidden()` ou `display: none` via `.evaluate(el => getComputedStyle(el).display)`) sur quelques elements representatifs (op-kpi-strip, op-exec-status-bar). Pas besoin de tester chaque selecteur individuellement.

### Sampling Rate
- **Par tache:** Inspection visuelle dans le browser (pas de CI rapide pour l'UI)
- **Par wave merge:** `npx playwright test tests/e2e/specs/operator-focus-mode.spec.js`
- **Phase gate:** Suite verte + review visuelle avant `/gsd:verify-work`. Verifier specifiquement: panneau checklist Phase 1 reste visible en focus mode; chrono continue de tourner; bouton Cloturer toujours cliquable.

### Wave 0 Gaps
- [ ] `tests/e2e/specs/operator-focus-mode.spec.js` — couvre FOCUS-01 a FOCUS-03 (click toggle, classe CSS, sessionStorage, sticky action bar). Pattern derive de `tests/e2e/specs/operator-e2e.spec.js`.

Note: existing critical-path-operator.spec.js et operator-e2e.spec.js doivent rester verts (no regression).

---

## Open Questions

1. **Choix entre `opExecTitle` (titre seance) ou `execVoteTitle` (titre motion) pour la Zone 1**
   - What we know: FOCUS-01 dit "titre motion" — `execVoteTitle` ID est plus precis. Il vit dans `#execActiveVote` qui est conditionnel (visible quand un vote est ouvert).
   - What's unclear: Que faut-il afficher comme Zone 1 quand aucun vote n'est ouvert? Le titre seance (`opExecTitle` dans le header) reste affiche meme sans vote. C'est probablement la bonne solution: le header conserve `opExecTitle` (titre seance) ET le centre affiche `execVoteTitle` (titre motion) quand un vote est actif.
   - Recommendation: ne pas masquer ni l'un ni l'autre. Le header garde le titre seance (toujours present), `execActiveVote` (qui contient `execVoteTitle`) est conditionnel mais reste visible quand un vote est ouvert. Pas d'ambiguite si on traite "Zone 1: titre" comme la combinaison des deux.

2. **Bouton "Passer motion" (FOCUS-02): existe-t-il deja ou faut-il en creer un?**
   - What we know: Le seul bouton "passer a la motion suivante" est `opBtnNextVote` dans `.op-post-vote-guidance` (apparait apres cloture d'un vote). Pas de bouton dedie persistant.
   - What's unclear: FOCUS-02 demande "passer motion" cliquable et visible sans scroll. Faut-il ajouter un bouton permanent dans `.op-action-bar`?
   - Recommendation: NE PAS ajouter de nouveau bouton. Le flux UX existant est: cloturer le vote -> guidance affiche "Vote suivant" -> click. Conserver `.op-post-vote-guidance` visible en focus mode (KEEP). Si le planner veut un bouton permanent, c'est une evolution UX a discuter via /gsd:discuss-phase, hors scope de Phase 2 minimum.

3. **Comportement de `prefers-reduced-motion` sur la transition focus**
   - What we know: Phase 1 utilise `@media (prefers-reduced-motion: no-preference)` pour wrapper les transitions/animations. Phase 3 (Animations Vote) couvrira le sujet en profondeur (ANIM-03).
   - What's unclear: Phase 2 doit-elle ajouter des transitions a la bascule focus (e.g. fade des zones masquees)?
   - Recommendation: NON. Snap instantane (display: none / display: block). Pas de transition CSS sur le focus. Plus simple, plus performant, evite les conflits avec Phase 3. Si une transition douce est jugee desirable, ajouter en Phase 3 sous le wrapper `prefers-reduced-motion: no-preference`.

4. **Test sur viewport non-1080p**
   - What we know: ROADMAP cite "1080p viewport fit" comme cible (v1.6 Phase 3). La regle `@media (max-width: 1024px) .op-checklist-panel { display: none }` (Phase 1) supprime le panneau en mobile.
   - What's unclear: En focus mode + viewport <1024px, le quorum status (Zone 3) est invisible (panneau masque). Acceptable?
   - Recommendation: Mobile/tablette n'est pas la cible operateur (operateur = console PC en seance). Conserver la regle existante. Si une fonctionnalite cross-device etait souhaitee, replacer Zone 3 ailleurs en focus mode + mobile — hors scope Phase 2.

---

## Sources

### Primary (HIGH confidence)
- `/public/operator.htmx.html` (1704 lignes) — Structure exec mode lignes 1167-1584 inspectee ligne par ligne
- `/public/assets/css/operator.css` (2169 lignes) — Regles `.view-exec`, `.op-exec-*`, `.op-action-bar`, `.op-checklist-*` extraites
- `/public/assets/js/pages/operator-tabs.js` (3562 lignes) — `setMode()` ligne 2040-2121, checklist toggle ligne 3151-3161
- `/public/assets/js/pages/operator-exec.js` (1023 lignes) — `updateExecHeaderTimer()`, `refreshExecChecklist()`, action bar visibility
- `/public/assets/js/pages/operator-realtime.js` (343 lignes) — SSE state management, intact en Phase 2
- `.planning/phases/01-checklist-operateur/01-RESEARCH.md` — Patterns Phase 1 (sessionStorage, classe modificateur, prefers-reduced-motion)
- `.planning/phases/01-checklist-operateur/01-UI-SPEC.md` — Design system contract (tokens, typo, color)
- `.planning/phases/01-checklist-operateur/01-01-SUMMARY.md` — Decisions HTML/CSS Phase 1
- `.planning/phases/01-checklist-operateur/01-02-SUMMARY.md` — Decisions JS Phase 1, sessionStorage pattern, idempotence

### Secondary (MEDIUM confidence)
- `.planning/REQUIREMENTS.md` — FOCUS-01..03 confirmes
- `.planning/ROADMAP.md` — Phase 2 success criteria
- `tests/e2e/specs/operator-e2e.spec.js` — Pattern des tests e2e existants pour operator (a etendre pour focus mode)

---

## Metadata

**Confidence breakdown:**
- DOM mapping: HIGH — chaque element inspecte ligne par ligne dans operator.htmx.html
- CSS approach: HIGH — pattern Phase 1 directement reproductible, choix justifie par tableau comparatif
- JS patterns: HIGH — code Phase 1 a copier-adapter (sessionStorage, setMode bloc, click handler)
- Toggle placement: HIGH — emplacement `.op-exec-header-right` confirme par inspection, icone presente dans sprite
- Pitfalls: HIGH — chrono (lignes 371-388 inspectees), checklist (Phase 1 RESEARCH), sticky bottom (CSS standard)

**Research date:** 2026-04-29
**Valid until:** 2026-05-29 (code stable post-Phase 1, aucune migration framework prevue)
