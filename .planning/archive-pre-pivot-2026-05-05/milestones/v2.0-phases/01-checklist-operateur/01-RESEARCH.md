# Phase 1: Checklist Operateur - Research

**Researched:** 2026-04-21
**Domain:** Frontend HTML/CSS/JS — panneau de monitoring temps reel dans operator.htmx.html
**Confidence:** HIGH

---

<user_constraints>
## User Constraints (from CONTEXT.md)

### Locked Decisions

**Checklist Layout & Placement**
- La checklist apparait comme un panneau lateral droit (collapsible) dans la vue execution, toujours visible sans scrolling
- Chaque indicateur est affiche sur une ligne compacte: icone + label + valeur (ex: `checkmark Quorum 42/60 (70%)`) avec icone coloree selon l'etat
- La checklist coexiste avec le KPI strip existant — elle est une vue de statut consolidee, le KPI strip reste pour les metriques detaillees
- La checklist est visible uniquement en mode Execution (pas en mode Preparation)

**Alert System & Thresholds**
- Les alertes visuelles se manifestent par un flash inline rouge + pulsation de l'icone pendant 3 secondes, pas de modal ni de toast
- La deconnexion SSE a un traitement distinct: banniere rouge persistante en haut de la checklist ("Connexion perdue") car elle affecte tous les autres indicateurs
- L'indicateur quorum est binaire: vert si presents >= requis, rouge sinon
- Pas de son — alertes visuelles uniquement

**SSE & Connected Voters Display**
- Le statut SSE est affiche avec un point colore + label: point vert "Connecte" / point rouge "Deconnecte" avec temps depuis le dernier evenement
- Les "votants connectes" utilisent le mecanisme existant de presence Redis TTL (heartbeat 60s dans operator-realtime.js)
- Les votes recus sont affiches en format fraction "12/45 votes recus" montrant la progression vers le total eligible
- La checklist reste visible en permanence pendant le mode execution, pas d'auto-collapse

### Claude's Discretion
- Details d'implementation CSS (animations, transitions, breakpoints)
- Structure HTML exacte du panneau checklist
- Ordre des indicateurs dans la checklist
- Gestion des etats transitoires (chargement initial, pas de motion active)

### Deferred Ideas (OUT OF SCOPE)
None — discussion stayed within phase scope.
</user_constraints>

---

<phase_requirements>
## Phase Requirements

| ID | Description | Research Support |
|----|-------------|-----------------|
| CHECK-01 | En mode live, une checklist affiche le statut quorum (atteint/non atteint) avec le ratio votants/total | `computeQuorumStats()` fournit `currentVoters`, `required`, `totalMembers` — donnees disponibles sans nouvel endpoint |
| CHECK-02 | La checklist indique le nombre de votes recus en temps reel via SSE | `O.ballotsCache` et `O.currentOpenMotion` disponibles dans `refreshExecKPIs()` — SSE `vote.cast` declenche deja `loadBallots()` |
| CHECK-03 | La checklist montre le statut connexion reseau et SSE (connecte/deconnecte) | `sseConnected` booleen + `setSseIndicator(state)` dans operator-realtime.js — callbacks `onConnect`/`onDisconnect` existants a brancher |
| CHECK-04 | La checklist affiche le nombre de votants connectes en temps reel | `execDevOnline` ID deja mis a jour par `refreshExecDevices()` — copie de la meme source |
| CHECK-05 | Si un indicateur passe au rouge (quorum non atteint, SSE deconnecte), une alerte visuelle automatique apparait | CSS `animation: checklistPulse` 3 cycles + classe `--alert` sur la row — aucune logique JS complexe |
</phase_requirements>

---

## Summary

Phase 1 est entierement frontend: un seul fichier HTML, une extension CSS, et des branchements JS dans des modules existants. Aucune modification backend, aucun nouvel endpoint API, aucun nouveau service PHP.

L'infrastructure est deja en place: SSE tourne via `EventStream` + `operator-realtime.js`, les donnees de quorum et de votes sont calculees par `computeQuorumStats()` dans `operator-exec.js`, et le compteur de votants connectes est deja mis a jour dans `refreshExecDevices()`. La phase consiste a creer le panneau HTML, lui ajouter les styles CSS, puis brancher les fonctions existantes pour qu'elles alimentent aussi le panneau checklist.

Le seul point d'attention architectural est le wrapper layout: `.op-exec-main` est actuellement une `flex-direction: column` seule dans `.view-exec`. Pour placer la checklist a droite, un `.op-exec-body` wrapper `display: flex; flex-row` doit etre introduit autour de `.op-exec-main` et du nouveau panneau. Ce wrapper est minimal et ne perturbe pas le reste du layout.

**Primary recommendation:** Ajouter le panneau `.op-checklist-panel` comme second enfant d'un nouveau wrapper `.op-exec-body` (flex row) a l'interieur de `#viewExecContent`, alimenter ses valeurs depuis les fonctions existantes `computeQuorumStats()` et `refreshExecDevices()`, et brancher les callbacks SSE `onConnect`/`onDisconnect` pour la banniere de deconnexion.

---

## Standard Stack

### Core

| Composant | Version/Source | Purpose | Why Standard |
|-----------|---------------|---------|--------------|
| HTML natif | — | Structure du panneau checklist | Projet HTMX — pas de framework JS UI |
| operator.css | `/public/assets/css/operator.css` (2021 lignes) | Styles du panneau | Toutes les regles operator dans ce fichier unique |
| operator-realtime.js | `/public/assets/js/pages/operator-realtime.js` | Mise a jour SSE indicator checklist | Contient deja `setSseIndicator()` et les callbacks SSE |
| operator-exec.js | `/public/assets/js/pages/operator-exec.js` | Alimentation des donnees quorum/votes | Contient `computeQuorumStats()` et `refreshExecKPIs()` |
| event-stream.js | `/public/assets/js/core/event-stream.js` | SSE client | `onConnect`/`onDisconnect` a brancher sur indicateur SSE checklist |
| Design tokens | `--color-*`, `--space-*`, `--text-*` | Couleurs, espacements, typographie | Tokens deja utilises dans toute la page operator |
| CSS `@keyframes` | CSS natif | Animation `checklistPulse` (3 cycles) | Aucun Anime.js pour les anims CHECK-05 (Anime.js reserve au count-up KPI strip) |

### Fichiers modifies

| Fichier | Type de changement |
|---------|--------------------|
| `public/operator.htmx.html` | Ajout HTML du panneau + wrapper `.op-exec-body` autour de `.op-exec-main` |
| `public/assets/css/operator.css` | Ajout regles `.op-checklist-*` + `.op-exec-body` + `@keyframes checklistPulse` |
| `public/assets/js/pages/operator-realtime.js` | Branchement checklist dans les handlers SSE existants (`handleSSEEvent`) |
| `public/assets/js/pages/operator-exec.js` | Appel a une nouvelle `refreshExecChecklist()` depuis `refreshExecView()` |

Aucun fichier PHP. Aucune route. Aucun endpoint API.

---

## Architecture Patterns

### Structure HTML du panneau

```
#viewExecContent
  .op-exec-status-bar     (inchange)
  .op-kpi-strip           (inchange)
  .op-tags                (inchange)
  .op-resolution-progress (inchange)
  .op-guidance            (inchange)
  .exec-close-banner      (inchange)
  .op-exec-body           [NOUVEAU wrapper flex row]
    .op-exec-main         (existant, flex: 1)
    aside.op-checklist-panel [NOUVEAU, 240px, role="complementary"]
      .op-checklist-sse-banner [hidden par defaut]
      .op-checklist-header
        h3.op-checklist-title "CONTROLE SEANCE"
        button.op-checklist-toggle
      .op-checklist-rows
        .op-checklist-row [data-row="sse"]
        .op-checklist-row [data-row="quorum"]
        .op-checklist-row [data-row="votes"]
        .op-checklist-row [data-row="online"]
```

La structure du panneau s'insere DANS `.op-main > .view-exec > #viewExecContent`, pas dans `.op-body` (grid 280px + fluid). Cela preserve la structure grid existante.

### Pattern 1: Wrapper layout `.op-exec-body`

**What:** Nouveau div qui englobe `.op-exec-main` (existant) et `.op-checklist-panel` (nouveau) en flex row.

**When to use:** Uniquement dans `#viewExecContent`, apres `.op-guidance` / `.exec-close-banner`.

**Pourquoi pas modifier `.op-exec-main` directement:** `.op-exec-main` a deja `flex-direction: column` et `overflow-y: auto`. Le wrapper evite de casser ce comportement.

**CSS:**
```css
/* Source: patron etabli dans operator.css pour les splits flex */
.op-exec-body {
  flex: 1;
  display: flex;
  flex-direction: row;
  min-height: 0;
  overflow: hidden;
}

.op-exec-main {
  /* existant — supprimer flex: 1 du top-level, le mettre ici */
  flex: 1;
  overflow-y: auto;
  /* ... reste inchange */
}
```

### Pattern 2: Checklist row avec etat d'alerte

**What:** Chaque indicateur = une ligne `.op-checklist-row` avec icone + label + valeur.

**When to use:** 4 indicateurs: SSE, quorum, votes, votants.

**Classe d'alerte:** `.op-checklist-row--alert` (fond `--color-danger-subtle`, bordure gauche 3px `--color-danger`, icone anime par `checklistPulse`).

```css
/* Source: patron .op-tag-no-quorum + .op-agenda-item.current existants */
.op-checklist-row {
  display: flex;
  align-items: center;
  gap: var(--space-2);
  min-height: 44px;
  padding: 0 var(--space-4);
  border-left: 3px solid transparent;
}

.op-checklist-row--alert {
  background: var(--color-danger-subtle);
  border-left-color: var(--color-danger);
}

.op-checklist-row--alert .op-checklist-icon {
  animation: checklistPulse 1s ease-in-out 3;
  color: var(--color-danger);
}

@media (prefers-reduced-motion: no-preference) {
  @keyframes checklistPulse {
    0%, 100% { opacity: 1; }
    50%       { opacity: 0.3; }
  }
}
```

### Pattern 3: Alimentation des donnees depuis les fonctions existantes

**What:** Nouvelle fonction `refreshExecChecklist()` dans `operator-exec.js`, appelee depuis `refreshExecView()`.

**Donnees sources:**
- Quorum: `computeQuorumStats()` — disponible dans le meme fichier (`operator-exec.js`)
- Votes recus: `O.ballotsCache` (objet clef=membre_id) + `O.attendanceCache` — disponibles dans `O` global
- Votants connectes: lire le textContent de `#execDevOnline` (deja mis a jour par `refreshExecDevices()`) OU acceder directement a `O.devicesCache`
- SSE status: via `sseConnected` dans `operator-realtime.js` — expose sur `O` ou passe en parametre

**Decouplage propre:** `refreshExecChecklist()` lit les donnees de `O` (namespace global OpS) — pas de couplage direct avec `operator-realtime.js`. Le statut SSE est expose via `O.sseConnected` (a ajouter dans operator-realtime.js).

```javascript
// Dans operator-exec.js
function refreshExecChecklist() {
  var stats = computeQuorumStats();
  // Quorum row
  var quorumMet = stats.currentVoters >= stats.required;
  setChecklistRow('quorum', quorumMet ? 'ok' : 'alert',
    stats.currentVoters + '/' + stats.required +
    ' (' + (stats.totalMembers > 0 ? Math.round(stats.currentVoters / stats.totalMembers * 100) : 0) + '%)');
  // Votes row
  var totalBallots = Object.keys(O.ballotsCache).length;
  var eligible = stats.present + stats.proxyActive;
  setChecklistRow('votes', 'neutral', totalBallots + '/' + eligible);
  // Online voters row
  var onlineEl = document.getElementById('execDevOnline');
  var onlineCount = onlineEl ? onlineEl.textContent : '0';
  setChecklistRow('online', 'neutral', onlineCount);
  // SSE row driven by operator-realtime.js via O.sseState
  updateChecklistSseRow(O.sseState || 'offline');
}
```

### Pattern 4: SSE disconnect banner

**What:** Banniere rouge persistante en haut du panneau, visible uniquement quand SSE est `offline`.

**Trigger:** Callback `onDisconnect` dans `operator-realtime.js` deja existant — ajouter `showChecklistSseBanner()`.

**Clear:** Callback `onConnect` — ajouter `hideChecklistSseBanner()`.

**HTML:** Element avec attribut `[hidden]` par defaut, JavaScript retire/remet `hidden`.

```javascript
// Dans operator-realtime.js, dans onDisconnect existant:
var banner = document.getElementById('opChecklistSseBanner');
if (banner) banner.hidden = false;

// Dans onConnect existant:
var banner = document.getElementById('opChecklistSseBanner');
if (banner) banner.hidden = true;
```

### Anti-Patterns to Avoid

- **Ne pas creer de nouveau endpoint API** pour les donnees de la checklist — tout est deja dans `O.*Cache` cotes JS.
- **Ne pas modifier la structure `.op-body` grid** (280px sidebar + fluid main) — le panneau s'insere dans `.op-main`, pas au niveau du grid body.
- **Ne pas dupliquer les sources de donnees** — lire `computeQuorumStats()` existant, pas recalculer.
- **Ne pas utiliser Anime.js pour les animations checklist** — `checklistPulse` est CSS pur (Anime.js reserve au count-up KPI, Phase 3).
- **Ne pas auto-collapse la checklist** — la decision CONTEXT.md est "reste visible en permanence".
- **Ne pas modifier `event-stream.js`** — les callbacks `onConnect`/`onDisconnect` suffisent via `operator-realtime.js`.

---

## Don't Hand-Roll

| Problem | Don't Build | Use Instead | Why |
|---------|-------------|-------------|-----|
| Calcul quorum | Recalculer present/required | `computeQuorumStats()` dans operator-exec.js | Deja calcule, inclut proxies |
| Comptage votes | Parcourir ballotsCache manuellement | `Object.keys(O.ballotsCache).length` | Pattern existant dans refreshExecKPIs |
| Compteur votants connectes | Nouveau endpoint presence | Lire `#execDevOnline` ou `O.devicesCache` | Deja mis a jour par refreshExecDevices |
| SSE status tracking | Nouveau listener EventSource | `O.sseState` expose par operator-realtime.js | Module deja proprietaire du SSE |
| Animation pulse alerte | Anime.js timeline | CSS `@keyframes checklistPulse` | Simple opacity toggle, 3 cycles — CSS suffit |
| Collapse state persistence | Cookie ou localStorage | `sessionStorage` cle `opChecklistCollapsed` | Pattern UX etabli, scope session |

---

## Common Pitfalls

### Pitfall 1: Casser le scroll de `.op-exec-main`

**What goes wrong:** Si `.op-exec-body` n'a pas `min-height: 0` + `overflow: hidden`, le panneau checklist peut forcer la hauteur et deborder hors viewport.

**Why it happens:** Les flex containers ont besoin de `min-height: 0` pour respecter les contraintes de hauteur parentes dans une chaine de flex/grid.

**How to avoid:** `.op-exec-body { flex: 1; display: flex; min-height: 0; overflow: hidden; }` — le `overflow: hidden` concontient les enfants.

**Warning signs:** Scrollbar apparait sur `body` ou `.op-main` au lieu de `.op-exec-main`.

### Pitfall 2: `checklistPulse` se rejoue en boucle infinie

**What goes wrong:** Si la classe `--alert` est retiree puis reajoutee pendant que l'animation tourne, le compteur d'iterations repart de zero — apparence de boucle infinie.

**Why it happens:** Reecriture du className ou toggle de la classe pendant l'animation.

**How to avoid:** Ajouter la classe une seule fois par transition d'etat. Ne pas toggeler en boucle. Utiliser un drapeau `data-alert-active` pour eviter de rajouter la classe si elle est deja presente.

### Pitfall 3: Desynchronisation SSE indicator entre meeting bar et checklist

**What goes wrong:** La barre de reunion a deja `#opSseIndicator` avec `data-sse-state`. Si la checklist a son propre indicateur SSE, les deux peuvent diverger.

**Why it happens:** `setSseIndicator()` dans operator-realtime.js ne met a jour que `#opSseIndicator`. La checklist a son propre element.

**How to avoid:** Appeler `updateChecklistSseRow()` au meme endroit que `setSseIndicator()` — soit dans la meme fonction, soit exposer un hook. Ne pas creer un second listener EventSource.

### Pitfall 4: Donnees stale lors du chargement initial

**What goes wrong:** Le panneau checklist s'affiche vide (0/0, —) pendant 15 secondes jusqu'au premier poll.

**Why it happens:** `refreshExecChecklist()` est appelee depuis `refreshExecView()` qui est appelee par le poll.

**How to avoid:** Appeler `refreshExecChecklist()` aussi lors du `setMode('exec')` — dans operator-tabs.js la ou le mode execution est active.

### Pitfall 5: Checklist visible en mode Preparation

**What goes wrong:** Le panneau est rendu visible quand l'utilisateur est en mode Preparation.

**Why it happens:** `[hidden]` sur le panneau non gere lors du switch de mode.

**How to avoid:** Le panneau a l'attribut `[hidden]` dans le HTML initial. Il est montre/cache uniquement via `Shared.show()`/`Shared.hide()` dans le meme endroit que `#viewExec` est montre/cache (operator-tabs.js `setMode()`).

---

## Code Examples

Verified patterns from existing codebase:

### Lecture des donnees quorum (operator-exec.js ligne 409)
```javascript
// Source: /public/assets/js/pages/operator-exec.js:409
function computeQuorumStats() {
  var present = O.attendanceCache.filter(function(a) {
    return a.mode === 'present' || a.mode === 'remote';
  }).length;
  var proxyActive = O.proxiesCache.filter(function(p) { return !p.revoked_at; }).length;
  var currentVoters = present + proxyActive;
  var totalMembers = O.membersCache.length;
  var policy = O.policiesCache && O.policiesCache.quorum
    ? O.policiesCache.quorum.find(function(p) {
        return p.id === (O.currentMeeting ? O.currentMeeting.quorum_policy_id : null);
      }) : null;
  var threshold = policy && policy.threshold ? parseFloat(policy.threshold) : 0.5;
  var required = Math.ceil(totalMembers * threshold);
  return { present, proxyActive, currentVoters, totalMembers, required, threshold };
}
```

### Branchement dans handleSSEEvent (operator-realtime.js ligne 99)
```javascript
// Source: /public/assets/js/pages/operator-realtime.js:99
// Exemple de branchement supplementaire dans un case existant:
case 'vote.cast':
case 'vote.updated':
  // ... code existant ...
  // AJOUTER: mise a jour checklist votes
  if (O.fn.refreshExecChecklist) O.fn.refreshExecChecklist();
  break;

case 'attendance.updated':
  O.fn.loadQuorumStatus();
  // AJOUTER: mise a jour checklist quorum
  if (O.fn.refreshExecChecklist) O.fn.refreshExecChecklist();
  break;
```

### SSE indicator pattern existant (operator-realtime.js ligne 32)
```javascript
// Source: /public/assets/js/pages/operator-realtime.js:32
var SSE_LABELS = {
  live: '\u25cf En direct',
  reconnecting: '\u26a0 Reconnexion...',
  offline: '\u2715 Hors ligne'
};

function setSseIndicator(state) {
  var el = document.getElementById('opSseIndicator');
  var lb = document.getElementById('opSseLabel');
  if (el) el.setAttribute('data-sse-state', state);
  if (lb) lb.textContent = SSE_LABELS[state] || state;
}
// Etat expose: sseConnected (booleen) — a completer avec O.sseState = state
```

### Pattern couleur tag quorum existant (operator.css ligne 665)
```css
/* Source: /public/assets/css/operator.css:665 */
.op-tag-quorum    { background: var(--color-success-subtle); border-color: var(--color-success); color: var(--color-success-text); }
.op-tag-no-quorum { background: var(--color-danger-subtle);  border-color: var(--color-danger);  color: var(--color-danger-text); }
/* Ces memes tokens seront utilises pour .op-checklist-row--alert */
```

### Point SSE existant dans la meeting bar (operator.htmx.html ligne 61)
```html
<!-- Source: /public/operator.htmx.html:61 -->
<span class="op-sse-indicator" id="opSseIndicator" data-sse-state="offline"
      aria-live="polite" aria-label="Connexion en direct">
  <span class="op-sse-dot" aria-hidden="true"></span>
  <span class="op-sse-label" id="opSseLabel">Hors ligne</span>
</span>
<!-- Le checklist SSE row sera un second indicateur SSE avec son propre ID -->
```

### Responsive breakpoint existant (operator.css ligne 1976)
```css
/* Source: /public/assets/css/operator.css:1976 */
@media (max-width: 1024px) {
  .op-body { grid-template-columns: 1fr; }
  /* AJOUTER: masquer/reduire le panneau checklist */
  .op-checklist-panel { display: none; }
}
```

---

## Validation Architecture

La phase ne touche aucun code PHP — pas de tests PHPUnit pertinents. Les tests e2e Playwright sont la methode de validation appropriee.

### Test Framework
| Property | Value |
|----------|-------|
| Framework | Playwright (tests/e2e/) |
| Config file | `tests/e2e/playwright.config.js` |
| Quick run command | `npx playwright test tests/e2e/specs/critical-path-operator.spec.js` |
| Full suite command | `npx playwright test tests/e2e/specs/` |

### Phase Requirements -> Test Map

| Req ID | Behavior | Test Type | Automated Command | File Exists? |
|--------|----------|-----------|-------------------|-------------|
| CHECK-01 | Panneau checklist visible en mode live avec ratio quorum | e2e smoke | `npx playwright test tests/e2e/specs/operator-checklist.spec.js` | ❌ Wave 0 |
| CHECK-02 | Votes recus mis a jour en temps reel via SSE | e2e | `npx playwright test tests/e2e/specs/operator-checklist.spec.js` | ❌ Wave 0 |
| CHECK-03 | Indicateur SSE vert/rouge selon etat connexion | e2e | `npx playwright test tests/e2e/specs/operator-checklist.spec.js` | ❌ Wave 0 |
| CHECK-04 | Compte votants connectes visible | e2e | `npx playwright test tests/e2e/specs/operator-checklist.spec.js` | ❌ Wave 0 |
| CHECK-05 | Classe `--alert` + animation pulse sur indicateur rouge | e2e DOM assertion | `npx playwright test tests/e2e/specs/operator-checklist.spec.js` | ❌ Wave 0 |

Note: CHECK-05 (animation) est verifiable en assertant la presence de la classe CSS `op-checklist-row--alert` sur la row concernee — pas besoin de verifier l'animation elle-meme visuellement.

### Sampling Rate
- **Par tache:** Inspection visuelle dans le browser (pas de CI rapide pour l'UI)
- **Par wave merge:** `npx playwright test tests/e2e/specs/operator-checklist.spec.js`
- **Phase gate:** Suite verte + review visuelle avant `/gsd:verify-work`

### Wave 0 Gaps
- [ ] `tests/e2e/specs/operator-checklist.spec.js` — couvre CHECK-01 a CHECK-05 (assertions DOM sur presence des elements, classes d'etat, valeurs affichees)

---

## Integration Points Confirmed

Suite a l'inspection du code source:

| Point d'integration | Localisation | Methode |
|---------------------|-------------|---------|
| Ajout HTML checklist | `operator.htmx.html` apres ligne 1234 (fin de `.op-kpi-strip`) | Nouveau wrapper `.op-exec-body` contenant `.op-exec-main` existant + `.op-checklist-panel` nouveau |
| Alimentation quorum CHECK-01 | `operator-exec.js` dans `refreshExecView()` | Appel `refreshExecChecklist()` qui appelle `computeQuorumStats()` |
| Alimentation votes CHECK-02 | `operator-realtime.js` dans `handleSSEEvent` case `vote.cast` | Appel `O.fn.refreshExecChecklist()` |
| SSE status CHECK-03 | `operator-realtime.js` dans `setSseIndicator(state)` | Ajout `updateChecklistSseRow(state)` a la meme fonction |
| Votants connectes CHECK-04 | `operator-exec.js` dans `refreshExecDevices()` | Appel `refreshExecChecklist()` apres mise a jour `execDevOnline` |
| Alerte visuelle CHECK-05 | CSS + `refreshExecChecklist()` | Toggle classe `op-checklist-row--alert` selon etat — CSS gere l'animation |
| Banniere deconnexion SSE | `operator-realtime.js` callbacks `onConnect`/`onDisconnect` | `banner.hidden = false/true` sur `#opChecklistSseBanner` |
| Visibilite execution only | `operator-tabs.js` `setMode()` | `Shared.show/hide` sur `.op-checklist-panel` |

---

## Open Questions

1. **Exposition de `sseConnected` sur `O`**
   - What we know: `sseConnected` est une variable locale dans l'IIFE de `operator-realtime.js`, non exposee sur `O`
   - What's unclear: Faut-il exposer `O.sseState` (string: 'live'|'reconnecting'|'offline') ou appeler directement `updateChecklistSseRow(state)` depuis `setSseIndicator(state)`?
   - Recommendation: Appeler `updateChecklistSseRow(state)` directement dans `setSseIndicator()` — plus simple, pas de couplage via `O`. La fonction `updateChecklistSseRow` peut etre definie dans `operator-exec.js` et enregistree sur `O.fn` pour que `operator-realtime.js` puisse la trouver.

2. **Modification de `.op-exec-main` pour le wrapper**
   - What we know: `.op-exec-main` a `flex: 1` en CSS mais il est enfant de `.view-exec` (flex column) — le wrapper `.op-exec-body` devrait heriter ce `flex: 1`
   - What's unclear: Y a-t-il un `flex: 1` hardcode dans `.op-exec-main` qui doit etre deplace vers `.op-exec-body`?
   - Recommendation: Verifier que `flex: 1` est bien sur `.op-exec-main` en CSS (oui, ligne 746-753 confirme) — lors de l'ajout du wrapper, mettre `flex: 1` sur `.op-exec-body` et retirer de `.op-exec-main` (ou laisser les deux, les flex imbriques le gerent naturellement).

---

## Sources

### Primary (HIGH confidence)
- `/public/operator.htmx.html` — Structure HTML complete de la page (1644 lignes)
- `/public/assets/css/operator.css` — Regles CSS operator (2021 lignes)
- `/public/assets/js/pages/operator-exec.js` — Logique exec + `computeQuorumStats()` (949 lignes)
- `/public/assets/js/pages/operator-realtime.js` — SSE + `setSseIndicator()` + `handleSSEEvent()` (341 lignes)
- `/public/assets/js/core/event-stream.js` — Client SSE `EventStream.connect()` (182 lignes)
- `.planning/phases/01-checklist-operateur/01-CONTEXT.md` — Decisions utilisateur verrouillees
- `.planning/phases/01-checklist-operateur/01-UI-SPEC.md` — Contrat UI detaille (composants, couleurs, typographie, accessibilite)

### Secondary (MEDIUM confidence)
- `.planning/REQUIREMENTS.md` — Requirements CHECK-01 a CHECK-05 confirmes
- `tests/e2e/specs/critical-path-operator.spec.js` — Pattern des tests e2e existants pour operator

---

## Metadata

**Confidence breakdown:**
- Standard stack: HIGH — code source inspecte directement, zero incertitude sur les fichiers a modifier
- Architecture: HIGH — structure HTML et CSS verifiee ligne par ligne, points d'integration confirmes
- Pitfalls: HIGH — identifies via inspection du code existant (flex min-height, animation toggle, SSE couplage)
- Validation: MEDIUM — tests e2e Playwright existent mais aucun spec checklist encore

**Research date:** 2026-04-21
**Valid until:** 2026-05-21 (code stable, aucune migration framework prevue)
