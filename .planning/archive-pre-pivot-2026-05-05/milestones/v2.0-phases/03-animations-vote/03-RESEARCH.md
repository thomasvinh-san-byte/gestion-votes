# Phase 3: Animations Vote - Research

**Researched:** 2026-04-29
**Domain:** Frontend HTML/CSS/JS — animations sur le vote card de operator.htmx.html (compteurs Pour/Contre/Abstention + barres de progression) avec respect de prefers-reduced-motion
**Confidence:** HIGH

---

<phase_requirements>
## Phase Requirements

| ID | Description | Research Support |
|----|-------------|-----------------|
| ANIM-01 | Quand un vote arrive via SSE, les compteurs pour/contre/abstention s'incrementent avec une animation visible (pas de changement instantane) | `refreshExecVote()` ligne 636 ecrit `forEl.textContent = fc` — substitution par le helper `animateKpiValue()` deja existant ligne 42 (anime.js count-up 600ms easeOutQuad) |
| ANIM-02 | Les barres de progression des resultats glissent vers leur nouvelle valeur en transition CSS fluide sans saut brusque | DEJA EN PLACE — `.op-bar-fill { transition: width 0.4s var(--ease-default); width: var(--bar-pct, 0%); }` ligne 935-940 de operator.css. Le passage de `--bar-pct` via `setProperty()` declenche la transition. Phase 3 doit confirmer la qualite de l'easing et combler le cas du chargement initial. |
| ANIM-03 | Sur prefers-reduced-motion: reduce, les compteurs et barres se mettent a jour instantanement sans animation | CSS GLOBAL DEJA EN PLACE — design-system.css ligne 3059-3068 force `animation-duration: 0.01ms` + `transition-duration: 0.01ms` pour `*::before, *, *::after`. Le tween anime.js (JS) doit etre garde manuellement par `window.matchMedia('(prefers-reduced-motion: reduce)').matches` |
</phase_requirements>

---

## Summary

Phase 3 est la plus **petite** des trois phases v2.0. La majorite du travail est deja en place dans le codebase :

1. Les **barres de progression** (`.op-bar-fill`, `.progress-bar`) ont deja `transition: width 0.4s var(--ease-default)` — le simple fait de faire `setProperty('--bar-pct', n + '%')` dans `refreshExecVote()` declenche une animation CSS fluide. ANIM-02 est satisfait des l'instant ou Phase 3 confirme la qualite des transitions et expose un fallback initial-load (cf. Pitfall 1).
2. Une fonction de count-up Anime.js (`animateKpiValue()` dans operator-exec.js:42) **existe deja** — elle est utilisee pour les KPIs `opKpiPresent/opKpiVoted/opKpiResolution`. La meme fonction peut etre reutilisee pour les compteurs Pour/Contre/Abstention.
3. Une regle globale `@media (prefers-reduced-motion: reduce)` dans design-system.css:3059 reduit toutes les transitions/animations CSS a 0.01ms — ANIM-03 est partiellement gratuit. Le seul ajout JS necessaire est un guard `matchMedia` dans `animateKpiValue()` qui actuellement ne le respecte pas.

L'intervention principale est donc : (a) modifier `refreshExecVote()` pour utiliser `animateKpiValue()` au lieu de `textContent =` direct sur les 3 compteurs ; (b) ajouter le guard reduced-motion dans `animateKpiValue()` (et son cousin `animateKpiPct()`) ; (c) eventuellement ajouter une animation CSS de pulse/highlight sur le compteur change pour amplifier la perception ANIM-01 ; (d) ne PAS animer au chargement initial (premier render).

**Primary recommendation:** Etendre `refreshExecVote()` pour appeler `animateKpiValue('execVoteFor', fc)` etc. au lieu de `textContent =` direct ; ajouter dans `animateKpiValue()` un guard `if (window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches) { el.textContent = newValue; return; }` ; conserver les transitions CSS existantes pour les barres ; gerer le first-render via flag `_voteAnimReady` qui ne devient `true` qu'apres le premier `refreshExecVote()` complet.

---

## Standard Stack

### Core

| Composant | Version/Source | Purpose | Why Standard |
|-----------|---------------|---------|--------------|
| Anime.js | 3.2.2 (CDN) | Count-up tween des compteurs Pour/Contre/Abstention | Deja charge globalement (operator.htmx.html ligne 1717), deja utilise par `animateKpiValue()` pour les KPIs strip — patron etabli dans le projet |
| CSS `transition: width` | natif | Animation des barres `.op-bar-fill` et `.progress-bar` | Deja en place — aucune modification requise sur les barres. Pure CSS, declenche automatiquement par `setProperty('--bar-pct', n + '%')` |
| `window.matchMedia` | natif | Detection runtime de `prefers-reduced-motion: reduce` | API standard, supportee partout. Necessaire car Anime.js (JS) ignore les media queries CSS |
| `font-variant-numeric: tabular-nums` | CSS natif | Stabilite visuelle des chiffres pendant le tween | Deja applique sur `.op-vote-count` ligne 887 — empeche le saut horizontal pendant le count-up |
| Design tokens transitions | `--duration-fast`, `--duration-normal`, `--ease-default`, `--ease-standard` | Coherence avec le reste du projet | Tokens definis dans design-system.css:524-540 — reutiliser pour toute nouvelle keyframe |

### Supporting (deja dans le projet)

| Element | Fichier:ligne | Usage Phase 3 |
|---------|---------------|---------------|
| `animateKpiValue(elementId, newValue)` | `operator-exec.js:42` | Reutiliser tel-quel pour `execVoteFor`, `execVoteAgainst`, `execVoteAbstain` (apres ajout du guard reduced-motion) |
| `animateKpiPct(elementId, newPct)` | `operator-exec.js:77` | Reutiliser pour `opPctFor`, `opPctAgainst`, `opPctAbstain` (apres ajout du guard reduced-motion) |
| `_prevVoteTotal` + `_deltaFadeTimer` | `operator-exec.js:27-28` | Pattern de rate-limit anti-rerun deja etabli — etudier pour copier sur le first-render guard |
| Globale reduced-motion CSS | `design-system.css:3059-3068` | Couvre automatiquement toutes les transitions CSS de Phase 3 — rien a refaire |
| `font-variant-numeric: tabular-nums` | `operator.css:887` | Deja en place sur `.op-vote-count` — pas de modif |

### Alternatives Considered

| Approche envisagee | Choix recommande | Tradeoff |
|--------------------|------------------|----------|
| CSS-only count-up via `@property --num` + `counter()` + `transition: --num` | NON recommande pour ce projet | Necessite Chrome 91+ / Safari 15.4+ / Firefox 128+ (Firefox ESR encore recent), syntaxe `counter()` complexe, et n'expose pas de hook simple `oncomplete`. Anime.js deja charge et eprouve sur les KPIs — pas de raison de bifurquer |
| `requestAnimationFrame` tween manuel | NON recommande | Reinventerait `animateKpiValue()`. Anime.js fait deja le travail, gere les easings, l'arrondi entier, et l'annulation |
| GSAP / Motion One | NON | Anime.js suffit, deja charge, deja utilise. Ajouter une lib serait du gaspillage |
| Pulse/highlight CSS sur le compteur change | OUI (optionnel) | Amplifie la perception du changement (ANIM-01 "animation visible"). Cout: 1 keyframe de ~10 lignes. Pattern eprouve avec `deltaPopIn` (operator.css:629-637) |

### Installation

Aucune nouvelle dependance — Anime.js deja chargee dans operator.htmx.html ligne 1717.

---

## DOM Map (vote card)

Localisation: `public/operator.htmx.html` lignes 1334-1395.

```
.op-vote-card.op-vote-card--active#execActiveVote      [hidden par defaut]
  ag-badge#execLiveBadge                               (badge "VOTE EN COURS")
  .exec-vote-title#execVoteTitle                       (titre motion)

  .op-vote-counters [aria-live=polite] [aria-atomic=true]
    .op-vote-counter.op-vote-for
      .op-vote-count#execVoteFor       <-- ANIM-01 cible #1
      .op-vote-label "Pour"
    .op-vote-counter.op-vote-against
      .op-vote-count#execVoteAgainst   <-- ANIM-01 cible #2
      .op-vote-label "Contre"
    .op-vote-counter.op-vote-abstain
      .op-vote-count#execVoteAbstain   <-- ANIM-01 cible #3
      .op-vote-label "Abstention"

  .op-vote-bars#opVoteBars
    .op-bar-row [Pour]
      .op-bar-track > .op-bar-fill.for#opBarFor    <-- ANIM-02 cible #1 (CSS deja OK)
      .op-bar-pct#opPctFor                          <-- pourcentage (peut animer)
    .op-bar-row [Contre]
      .op-bar-track > .op-bar-fill.against#opBarAgainst  <-- ANIM-02 cible #2
      .op-bar-pct#opPctAgainst
    .op-bar-row [Abstain]
      .op-bar-track > .op-bar-fill.abstain#opBarAbstain  <-- ANIM-02 cible #3
      .op-bar-pct#opPctAbstain

  .exec-participation-row
    .progress > .progress-bar.success#execVoteParticipationBar  <-- ANIM-02 cible #4 (animee dans refreshExecKPIs)
    .exec-participation-pct#execVoteParticipationPct

  .exec-meta-row [hidden] (legacy)
  .op-equality-warning#opEqualityWarning [hidden]
```

**Pertinent CSS state (operator.css):**
- ligne 868-900: compteurs (sans transition par defaut — animation gere par anime.js cote JS)
- ligne 935-940: `.op-bar-fill { transition: width 0.4s var(--ease-default); width: var(--bar-pct, 0%); }` — **deja en place**
- ligne 887: `.op-vote-count { font-variant-numeric: tabular-nums; }` — **deja en place** (anti-saut horizontal pendant le tween)

---

## JS Update Path (vote counters)

### Source des donnees

**Fichier:** `public/assets/js/pages/operator-motions.js:438-460` (`loadBallots(motionId)`)

```javascript
async function loadBallots(motionId) {
  const { body } = await api(`/api/v1/ballots.php?motion_id=${...}`);
  const ballots = body?.data?.items || [];
  O.ballotsCache = {};
  // ... remplit O.ballotsCache[member_id] = b.value
}
```

`loadBallots()` reecrit completement `O.ballotsCache` a chaque appel (pas d'increment differential). C'est l'API qui est la source de verite — le frontend ne reconstitue pas localement.

### Update DOM (cible Phase 3)

**Fichier:** `public/assets/js/pages/operator-exec.js:636-707` (`refreshExecVote()`)

Lignes 659-687 (zone d'intervention):

```javascript
var fc = 0, ac = 0, ab = 0;
Object.values(O.ballotsCache).forEach(function(v) {
  if (v === 'for') fc++;
  else if (v === 'against') ac++;
  else if (v === 'abstain') ab++;
});

// CIBLES ANIM-01 — actuellement instantane via textContent
if (forEl) forEl.textContent = fc;
if (againstEl) againstEl.textContent = ac;
if (abstainEl) abstainEl.textContent = ab;

var total = fc + ac + ab;
var pctFor = total > 0 ? Math.round((fc / total) * 100) : 0;
// ... (idem ac, ab)

// CIBLES ANIM-02 — CSS gere deja la transition via setProperty('--bar-pct')
if (barFor) barFor.style.setProperty('--bar-pct', pctFor + '%');
if (barAgainst) barAgainst.style.setProperty('--bar-pct', pctAgainst + '%');
if (barAbstain) barAbstain.style.setProperty('--bar-pct', pctAbstain + '%');

// CIBLES ANIM-01 (secondaire) — pourcentages affiches
if (pFor) pFor.textContent = pctFor + '%';
if (pAgainst) pAgainst.textContent = pctAgainst + '%';
if (pAbstain) pAbstain.textContent = pctAbstain + '%';
```

### SSE trigger chain

**Fichier:** `public/assets/js/pages/operator-realtime.js:104-115`

```javascript
case 'vote.cast':
case 'vote.updated':
  if (data.motion_id || (data.data && data.data.motion_id)) {
    var motionId = data.motion_id || data.data.motion_id;
    O.fn.loadBallots(motionId).then(function() {
      if (O.currentMode === 'exec') O.fn.refreshExecView();
    }).catch(...);
  }
  break;
```

`refreshExecView()` (`operator-exec.js:622`) appelle `refreshExecVote()` ligne 624. **La chaine SSE -> animation est deja en place.** Phase 3 hooke uniquement la fonction terminale.

### Diagramme de flux complet

```
SSE "vote.cast"
  -> handleSSEEvent (operator-realtime.js:101)
  -> O.fn.loadBallots(motionId)        [reecrit O.ballotsCache]
  -> O.fn.refreshExecView()             [orchestrator]
       -> refreshExecKPIs()             [animeKpi sur opKpiVoted DEJA OK]
       -> refreshExecVote()             [PHASE 3 — animeKpi sur execVoteFor/Against/Abstain]
            -> textContent = fc            [REMPLACER PAR animateKpiValue]
            -> setProperty('--bar-pct')    [CSS transition DEJA OK]
       -> refreshExecChecklist() (Phase 1)
       -> refreshFocusQuorum() (Phase 2)
```

---

## Architecture Patterns

### Pattern 1: Substitution de `textContent =` par `animateKpiValue()` (ANIM-01)

**What:** Remplacer les 3 affectations directes lignes 666-668 de operator-exec.js par des appels au helper existant.

**When to use:** Sur les compteurs entiers Pour/Contre/Abstention quand un vote arrive via SSE.

**Pourquoi pas un nouveau helper:** `animateKpiValue()` est deja teste, gere le node TEXT_NODE et le fallback si anime n'est pas charge.

**Code:**

```javascript
// AVANT (operator-exec.js:666-668)
if (forEl) forEl.textContent = fc;
if (againstEl) againstEl.textContent = ac;
if (abstainEl) abstainEl.textContent = ab;

// APRES
animateKpiValue('execVoteFor', fc);
animateKpiValue('execVoteAgainst', ac);
animateKpiValue('execVoteAbstain', ab);
```

Note: `animateKpiValue()` actuel cible `el.firstChild` (TEXT_NODE) — sur `.op-vote-count` les chiffres sont enfants directs (pas de span imbrique), donc le code fonctionne tel quel.

### Pattern 2: Ajout du guard reduced-motion dans `animateKpiValue()` (ANIM-03)

**What:** Court-circuiter le tween si l'utilisateur prefere les animations reduites.

**When to use:** En tete de `animateKpiValue()` ET `animateKpiPct()`.

**Code:**

```javascript
// operator-exec.js:42 — modification de animateKpiValue
function animateKpiValue(elementId, newValue) {
  var el = document.getElementById(elementId);
  if (!el) return;

  // ANIM-03 — respecter prefers-reduced-motion: reduce
  var prefersReduced = window.matchMedia &&
    window.matchMedia('(prefers-reduced-motion: reduce)').matches;

  if (prefersReduced || typeof anime === 'undefined') {
    if (el.firstChild && el.firstChild.nodeType === Node.TEXT_NODE) {
      el.firstChild.nodeValue = newValue;
    } else {
      el.textContent = newValue;
    }
    return;
  }

  var currentValue = parseInt(el.firstChild && el.firstChild.nodeType === Node.TEXT_NODE
    ? el.firstChild.nodeValue : el.textContent) || 0;
  var targetValue = parseInt(newValue) || 0;
  if (currentValue === targetValue) return;

  var obj = { val: currentValue };
  anime({
    targets: obj,
    val: targetValue,
    duration: 600,
    easing: 'easeOutQuad',
    round: 1,
    update: function() {
      if (el.firstChild && el.firstChild.nodeType === Node.TEXT_NODE) {
        el.firstChild.nodeValue = obj.val;
      } else {
        el.textContent = obj.val;
      }
    }
  });
}
```

### Pattern 3: Skip animation au premier render (anti-flash initial)

**What:** Au premier appel de `refreshExecVote()` apres ouverture du vote, ecrire les valeurs sans tween (sinon on verrait une animation 0 -> N pour chaque compteur a l'arrivee sur la vue).

**When to use:** Premier render du vote courant. Sur changement de motion, reinitialiser le drapeau.

**Code:**

```javascript
// operator-exec.js — etat module
var _activeVoteAnimReady = false;
var _activeVoteMotionId = null;

function refreshExecVote() {
  // ... code existant ...

  if (O.currentOpenMotion) {
    // Detect motion change -> reset animation guard
    var newMotionId = O.currentOpenMotion.id;
    if (newMotionId !== _activeVoteMotionId) {
      _activeVoteAnimReady = false;
      _activeVoteMotionId = newMotionId;
    }

    // ... compute fc, ac, ab ...

    if (_activeVoteAnimReady) {
      animateKpiValue('execVoteFor', fc);
      animateKpiValue('execVoteAgainst', ac);
      animateKpiValue('execVoteAbstain', ab);
    } else {
      // Premier render — set instant pour eviter le tween 0->N visible
      if (forEl) forEl.textContent = fc;
      if (againstEl) againstEl.textContent = ac;
      if (abstainEl) abstainEl.textContent = ab;
      _activeVoteAnimReady = true;
    }
    // ... reste inchange (barres CSS) ...
  } else {
    _activeVoteAnimReady = false;
    _activeVoteMotionId = null;
  }
}
```

### Pattern 4: Pulse/highlight CSS optionnel sur compteur change (renforcer ANIM-01)

**What:** Animation breve (200ms) sur le compteur dont la valeur vient de changer. Visuel: scale 1.0 -> 1.1 -> 1.0 OU background highlight transitoire.

**When to use:** Optionnel mais recommande — l'oeil capte mieux le changement avec un effet visuel en plus du tween numerique.

**Code (CSS):**

```css
/* operator.css — apres ligne 900 */
.op-vote-count {
  transition: transform var(--duration-fast) var(--ease-default);
}

.op-vote-count.op-vote-count--bumped {
  animation: voteCountBump 320ms cubic-bezier(0.34, 1.56, 0.64, 1);
}

@keyframes voteCountBump {
  0%   { transform: scale(1); }
  40%  { transform: scale(1.18); }
  100% { transform: scale(1); }
}
/* La regle globale prefers-reduced-motion: reduce dans design-system.css:3059
   neutralise automatiquement cette animation a 0.01ms — ANIM-03 OK. */
```

**Code (JS — wrapping minimal autour de animateKpiValue):**

```javascript
function animateVoteCounter(elementId, newValue) {
  var el = document.getElementById(elementId);
  if (!el) return;
  var prev = parseInt(el.textContent) || 0;
  if (prev === newValue) return;

  animateKpiValue(elementId, newValue);

  // Pulse only if value increased (vote arrived)
  if (newValue > prev) {
    el.classList.remove('op-vote-count--bumped');
    // Force reflow pour redeclencher l'animation
    void el.offsetWidth;
    el.classList.add('op-vote-count--bumped');
  }
}
```

### Pattern 5: Conserver les transitions CSS existantes pour les barres (ANIM-02)

**What:** **Ne rien modifier** sur la CSS des barres — la transition `width 0.4s var(--ease-default)` est deja optimale.

**When to use:** Toujours. Verifier seulement que `setProperty('--bar-pct', n + '%')` est appele (pas un setter direct sur `style.width`).

**Verification visuelle:** Au passage 0% -> 30% -> 45%, l'oeil voit une glissade fluide de 400ms. Le `--bar-pct` est anime via le custom property fallback (pas un `@property` declare — donc CSS interpole en mode discret, mais comme on transite la propriete `width` calculee a partir du custom prop, la transition fonctionne).

**Note importante:** Le custom property `--bar-pct` est utilise comme valeur litterale dans `width: var(--bar-pct, 0%)`. Sans declaration `@property --bar-pct { syntax: '<percentage>'; ... }`, la valeur change de maniere "discrete" du point de vue CSS — MAIS la propriete `width` elle-meme est animable et sera transitionnee. **Ce pattern fonctionne sans `@property`.**

### Anti-Patterns to Avoid

- **Ne PAS animer la propriete `transform: scale` sur les barres** — l'aspect ratio se deforme. Garder `width` (deja en place).
- **Ne PAS rajouter un transition globale `* { transition: all }`** — break de performance, conflits avec d'autres animations.
- **Ne PAS appeler `animateKpiValue` dans une boucle d'evenements SSE non-debouncee** — chaque vote cree un tween de 600ms. Si 10 votes arrivent en 1 seconde, 10 tweens se chevauchent. **Mitigation:** anime.js sur la meme target obj annule le tween precedent automatiquement (comportement par defaut). Verifier qu'on passe le meme `obj` reference (pas un nouvel objet a chaque appel) — actuellement `animateKpiValue` cree un nouveau `obj` chaque fois, ce qui est correct car anime.js cree des animations independantes mais la mise a jour DOM se fait via le `update` callback qui ecrit sur le meme element ; le dernier tween "gagne" visuellement.
- **Ne PAS animer au chargement initial** (Pattern 3 ci-dessus).
- **Ne PAS modifier le CSS global `prefers-reduced-motion: reduce`** dans design-system.css — il couvre deja Phase 3.
- **Ne PAS toucher operator-realtime.js** — la chaine SSE -> refreshExecVote est deja correcte.
- **Ne PAS introduire de nouveau endpoint API** — `loadBallots()` est deja en place.

---

## Don't Hand-Roll

| Probleme | Don't Build | Use Instead | Why |
|----------|-------------|-------------|-----|
| Count-up entier anime | `requestAnimationFrame` tween maison | `animateKpiValue()` (operator-exec.js:42) | Deja teste, gere TEXT_NODE, fallback anime undefined |
| Tween de pourcentage | `requestAnimationFrame` sur `el.textContent = pct + '%'` | `animateKpiPct()` (operator-exec.js:77) | Idem, copie symetrique |
| Detection reduced-motion | Heuristique JS sur user-agent | `window.matchMedia('(prefers-reduced-motion: reduce)').matches` | Standard W3C, supporte partout |
| Animation barres | Tween JS sur `style.width` | CSS `transition: width var(--duration) var(--easing)` deja en place ligne 938 | Le navigateur optimise les transitions de width sur GPU compositor |
| Highlight breve sur changement | `setTimeout(..., 320)` + cleanup manuel | CSS `@keyframes` + ajout/retrait de classe | Le navigateur gere la cleanup, et le `prefers-reduced-motion` global s'applique |
| Anti-flicker au premier render | Logique complexe de "ready state" | Drapeau `_activeVoteAnimReady` lie au `motion.id` (Pattern 3) | Pattern minimal, aligne avec `_prevVoteTotal` deja existant |

**Key insight:** Phase 3 doit etre **soustractive plutot qu'additive**. La majorite de l'infrastructure (anime.js, transitions CSS, reduced-motion global) existe deja. L'effort principal est de **brancher correctement** au lieu d'inventer.

---

## Common Pitfalls

### Pitfall 1: Animation 0 -> N visible au premier render du vote

**What goes wrong:** Quand l'operateur ouvre la vue execution alors qu'il y a deja 25 votes, les compteurs s'animent de 0 a 25 sur 600ms — donne une fausse impression que les votes arrivent en temps reel.

**Why it happens:** `refreshExecVote()` est appelee a chaque mode-switch et au premier poll. Le state initial DOM `0` -> state target `25` est anime comme tout autre changement.

**How to avoid:** Pattern 3 — drapeau `_activeVoteAnimReady` reset au changement de motion, premier render = set instant.

**Warning signs:** Counters animent visiblement quand on ouvre la vue execution sur une motion en cours avec votes deja recus.

### Pitfall 2: Tweens chevauches sur SSE rapide (rate-limit)

**What goes wrong:** Si 10 votes arrivent en 200ms, 10 calls a `refreshExecVote()` lancent 10 tweens. Les chiffres "sautent" car chaque tween ecrit `obj.val` arrondi sur le meme TEXT_NODE — le dernier tween a 600ms l'emporte mais les precedents ont brievement ecrit des valeurs intermediaires desordonnees.

**Why it happens:** anime.js ne sait pas que les targets multiples ecrivent sur le meme DOM node. Chaque animation a son propre `obj`.

**How to avoid:**
- Option A (simple): laisser tel quel — le dernier tween domine visuellement, le scintillement est minimal car les valeurs vont toujours dans le meme sens (croissant).
- Option B (robuste): stocker `_activeVoteTweens = {}` cle par elementId, et si un tween est en cours, l'arreter (`anime.remove(prevObj)` ou `obj.cancelled = true`) avant de creer le nouveau.
- Option C (rate-limit): `requestAnimationFrame` debouncer sur `refreshExecVote()` — n'appeler que 1x par frame max.

**Recommendation:** Option A pour Phase 3 — vote rate realiste = quelques votes/sec, pas 50/sec. Si scintillement observe en E2E, basculer Option B.

**Warning signs:** Compteurs qui "vibrent" en passant rapidement par des valeurs decroissantes pendant un burst SSE.

### Pitfall 3: Counter overshoot via `easeOutBack` ou similaire

**What goes wrong:** Si on remplace `easeOutQuad` par un easing avec rebond (`easeOutBack`, `easeOutElastic`), le compteur affiche `26` puis `25` — tres trompeur car suggere qu'un vote a ete annule.

**Why it happens:** Easings avec rebond depassent la valeur cible avant de revenir.

**How to avoid:** Garder `easing: 'easeOutQuad'` (deja en place dans `animateKpiValue`). Ne JAMAIS utiliser un easing avec overshoot pour les valeurs entieres monotones.

**Warning signs:** Code reviewer suggere "ajoute du fun" avec `easeOutBack` — refuser explicitement.

### Pitfall 4: Reduced-motion CSS reduit a 0.01ms mais le tween anime.js dure quand meme 600ms

**What goes wrong:** Sur un OS avec reduced-motion ON, les barres CSS se mettent a jour quasi-instantanement (0.01ms) mais les compteurs JS prennent 600ms a s'animer — desynchronisation visible et violation ANIM-03.

**Why it happens:** La media query CSS ne s'applique qu'aux animations/transitions CSS, pas aux durees JS d'anime.js.

**How to avoid:** Pattern 2 — guard `window.matchMedia` dans `animateKpiValue()`.

**Warning signs:** Tester avec `prefers-reduced-motion: reduce` (DevTools > Rendering > Emulate CSS media feature). Les compteurs doivent sauter sans tween.

### Pitfall 5: Le `aria-live="polite"` sur `.op-vote-counters` annonce chaque tick du tween

**What goes wrong:** Si le tween anime de 12 a 13, anime.js peut techniquement ecrire 12, 12, 12, 13 (a cause de l'arrondi entier). Les screen readers sur `aria-live="polite"` peuvent annoncer plusieurs fois "treize, treize" ou repondre tardivement.

**Why it happens:** Mises a jour DOM rapides successives sur un nœud aria-live.

**How to avoid:**
- `animateKpiValue` avec `round: 1` envoie 600/16 ~= 37 updates par seconde, mais la valeur entiere change peu de fois (12 -> 13 = 1 changement). Les screen readers debounce naturellement — generalement OK.
- Verifier en E2E avec Playwright + axe : le compteur final doit etre annonce.
- Si probleme: deplacer `aria-live="polite"` vers un span miroir cache qui n'est mis a jour qu'a la fin du tween.

**Warning signs:** Audit a11y signale annonces redondantes pendant les tweens.

### Pitfall 6: Le custom property `--bar-pct` ne s'anime pas sans `@property`

**What goes wrong:** Sur certains navigateurs anciens, `setProperty('--bar-pct', '50%')` change la valeur sans declenchement de transition (le navigateur considere les custom properties comme "discretes" par defaut).

**Why it happens:** Sans declaration `@property --bar-pct { syntax: '<percentage>'; inherits: false; initial-value: 0%; }`, le custom property n'est pas typographie et CSS ne sait pas l'interpoler.

**How to avoid (DEJA RESOLU dans le projet):** Le CSS actuel utilise `width: var(--bar-pct, 0%)` puis `transition: width 0.4s ...`. La propriete TRANSITIONNEE est `width` (qui EST animable nativement), pas `--bar-pct`. Le passage de la valeur via custom property est juste un mecanisme de transmission. Le navigateur recalcule `width` et l'anime. Donc **le code en place fonctionne sur tous les navigateurs cibles**.

**Warning signs:** Verifier dans les regressions Phase 3 que les barres glissent toujours fluidement. Si saut: probleme ailleurs (pas de transition CSS en cause).

### Pitfall 7: Le first-render sur reload de page applique l'animation au compteur initial 0

**What goes wrong:** Apres un F5 sur une motion ayant deja 25 votes, le DOM affiche `0` (HTML statique), puis le premier `refreshExecVote()` declenche l'animation 0 -> 25.

**Why it happens:** Pattern 3 reset `_activeVoteAnimReady = false` quand `motionId` change — sur F5, c'est un nouveau cycle de vie JS, le drapeau commence a `false`. Le premier render set en instant. **Pattern 3 couvre ce cas — verifie OK.**

**Warning signs:** Si le drapeau est mal initialise (e.g. `true` par defaut), animation visible au reload.

---

## Code Examples

Patrons verifies depuis le codebase existant.

### Anime.js count-up (operator-exec.js:42-70) — DEJA EN PLACE

```javascript
// Source: public/assets/js/pages/operator-exec.js:42
function animateKpiValue(elementId, newValue) {
  var el = document.getElementById(elementId);
  if (!el || typeof anime === 'undefined') {
    if (el && el.firstChild && el.firstChild.nodeType === Node.TEXT_NODE) {
      el.firstChild.nodeValue = newValue;
    }
    return;
  }
  var currentValue = parseInt(el.firstChild && el.firstChild.nodeType === Node.TEXT_NODE
    ? el.firstChild.nodeValue : el.textContent) || 0;
  var targetValue = parseInt(newValue) || 0;
  if (currentValue === targetValue) return;

  var obj = { val: currentValue };
  anime({
    targets: obj,
    val: targetValue,
    duration: 600,
    easing: 'easeOutQuad',
    round: 1,
    update: function() {
      if (el.firstChild && el.firstChild.nodeType === Node.TEXT_NODE) {
        el.firstChild.nodeValue = obj.val;
      } else {
        el.textContent = obj.val;
      }
    }
  });
}
```

**Phase 3 modification:** ajouter le guard `prefers-reduced-motion` au debut (cf. Pattern 2).

### Transition CSS sur barre (operator.css:935-940) — DEJA EN PLACE

```css
/* Source: public/assets/css/operator.css:935 */
.op-bar-fill {
  height: 100%;
  border-radius: 4px;
  transition: width 0.4s var(--ease-default);
  width: var(--bar-pct, 0%);
}
```

**Phase 3 modification:** Aucune. Verifier que `--ease-default` resout bien a `cubic-bezier(0.2, 0, 0, 1)` (oui, design-system.css:540).

### Pattern delta-badge avec animation (operator.css:619-637) — REUTILISABLE

```css
/* Source: public/assets/css/operator.css:619 */
.op-vote-delta-badge {
  position: absolute;
  top: -8px;
  right: -8px;
  background: var(--color-primary);
  color: var(--color-primary-text);
  /* ... */
  animation: deltaPopIn 0.3s cubic-bezier(0.34, 1.56, 0.64, 1);
}

@keyframes deltaPopIn {
  from { transform: scale(0.4); opacity: 0; }
  to   { transform: scale(1);   opacity: 1; }
}
```

**Phase 3 reuse:** Le pattern keyframe + animation est exactement celui necessaire pour Pattern 4 (`voteCountBump`).

### Reduced-motion global (design-system.css:3059-3068) — COUVRE AUTOMATIQUEMENT PHASE 3

```css
/* Source: public/assets/css/design-system.css:3059 */
@media (prefers-reduced-motion: reduce) {
  *,
  *::before,
  *::after {
    animation-duration: 0.01ms !important;
    animation-iteration-count: 1 !important;
    transition-duration: 0.01ms !important;
    scroll-behavior: auto !important;
  }
}
```

**Phase 3 implication:** Aucune nouvelle media query CSS necessaire. Le seul gap est cote JS (anime.js).

### Detection JS de reduced-motion — A AJOUTER

```javascript
// Pattern recommande pour Phase 3
function prefersReducedMotion() {
  return window.matchMedia &&
         window.matchMedia('(prefers-reduced-motion: reduce)').matches;
}
// Usage: if (prefersReducedMotion()) { el.textContent = newValue; return; }
```

### SSE handler vote.cast (operator-realtime.js:104-115) — INCHANGE EN PHASE 3

```javascript
// Source: public/assets/js/pages/operator-realtime.js:104
case 'vote.cast':
case 'vote.updated':
  if (data.motion_id || (data.data && data.data.motion_id)) {
    var motionId = data.motion_id || data.data.motion_id;
    O.fn.loadBallots(motionId).then(function() {
      if (O.currentMode === 'exec') O.fn.refreshExecView();
    }).catch(...);
  }
  break;
```

**Phase 3 modification:** AUCUNE. La chaine est deja correcte.

---

## State of the Art

| Old Approach | Current Approach | When Changed | Impact |
|--------------|------------------|--------------|--------|
| `requestAnimationFrame` tween manuel | Anime.js library | Projet adopte anime.js depuis le KPI strip (v1.x) | Phase 3 reutilise le helper existant |
| CSS `transition: all` | `transition: width 0.4s var(--ease-default)` (specifique) | design-system.css refactor (v1.4) | Aucun risque de side-effect transition |
| Pas de reduced-motion | `@media (prefers-reduced-motion: reduce) { *, *::before, *::after { ... 0.01ms } }` global | design-system.css ligne 3059 (v1.4) | Phase 3 herite gratuitement pour tout le CSS |
| `style.width = pct + '%'` direct | `setProperty('--bar-pct', pct + '%')` via custom property | operator-exec.js refactor | Pattern uniforme sur toutes les barres |

**Deprecated/outdated:** N/A — la stack est moderne et stable.

---

## Files to Modify (Phase 3)

| Fichier | Type changement | Lignes estimees |
|---------|----------------|-----------------|
| `public/assets/js/pages/operator-exec.js` | Modifier `animateKpiValue` (guard reduced-motion) + `animateKpiPct` (idem) + `refreshExecVote` (substituer textContent par animateKpiValue, ajouter guard first-render `_activeVoteAnimReady`, optionnel pulse class). | ~25-40 lignes ajoutees, ~6 lignes modifiees |
| `public/assets/css/operator.css` | Optionnel — ajouter `@keyframes voteCountBump` + selecteur `.op-vote-count--bumped` (Pattern 4). | ~12 lignes ajoutees |
| `public/operator.htmx.html` | AUCUN — DOM existant suffit. | 0 |
| `public/assets/js/pages/operator-realtime.js` | AUCUN — chaine SSE deja en place. | 0 |
| `public/assets/css/design-system.css` | AUCUN — reduced-motion global deja en place. | 0 |

**Total:** 1 fichier modifie obligatoire (operator-exec.js), 1 fichier CSS optionnel pour amplifier ANIM-01. **Aucun PHP. Aucun nouvel endpoint. Aucune modification HTML.**

---

## Validation Architecture

### Test Framework
| Property | Value |
|----------|-------|
| Framework | Playwright (tests/e2e/) |
| Config file | `tests/e2e/playwright.config.js` |
| Quick run command | `npx playwright test tests/e2e/specs/operator-e2e.spec.js --project=chromium` |
| Full suite command | `npx playwright test tests/e2e/specs/` |

### Phase Requirements -> Test Map

| Req ID | Behavior | Test Type | Automated Command | File Exists? |
|--------|----------|-----------|-------------------|-------------|
| ANIM-01 | Compteur Pour/Contre/Abstention s'anime visiblement quand un vote arrive (DOM mid-tween affiche valeur intermediaire) | e2e visual + DOM | `npx playwright test tests/e2e/specs/operator-animations.spec.js -g "vote counters animate"` | Wave 0 |
| ANIM-02 | Barre `.op-bar-fill` a `transition: width` actif et passe par valeurs intermediaires de width | e2e DOM assertion sur `getComputedStyle` | `npx playwright test tests/e2e/specs/operator-animations.spec.js -g "progress bars transition"` | Wave 0 |
| ANIM-03 | Avec emulate `prefers-reduced-motion: reduce`, les compteurs et barres se mettent a jour en < 50ms (pas de tween) | e2e via `page.emulateMedia({ reducedMotion: 'reduce' })` | `npx playwright test tests/e2e/specs/operator-animations.spec.js -g "respects reduced motion"` | Wave 0 |

**Verification techniques:**
- ANIM-01 test: lire `textContent` de `#execVoteFor` a t=100ms apres injection ballot, doit etre != valeur initiale ET != valeur finale (mid-tween).
- ANIM-02 test: lire `getComputedStyle(barFor).transitionDuration`, doit etre `"0.4s"` (sans reduced-motion) ou `"0.01ms"` (avec).
- ANIM-03 test: avec `emulateMedia({ reducedMotion: 'reduce' })`, le `textContent` doit egaler la valeur finale en < 50ms.

### Sampling Rate
- **Per task commit:** `node --check public/assets/js/pages/operator-exec.js` — verification syntaxe JS instantanee.
- **Per wave merge:** `npx playwright test tests/e2e/specs/operator-animations.spec.js` (~30s).
- **Phase gate:** Suite operator complete verte + revue visuelle dans navigateur reel.

### Wave 0 Gaps
- [ ] `tests/e2e/specs/operator-animations.spec.js` — couvre ANIM-01, ANIM-02, ANIM-03 (3 tests).
- [ ] Helper Playwright pour injecter un ballot via API ou simulateur de SSE event (a verifier — le projet a peut-etre deja un fixture ballot).

---

## Open Questions

1. **Ajouter ou non le pulse `voteCountBump` (Pattern 4) ?**
   - What we know: ANIM-01 demande "animation visible (pas de changement instantane)". Le count-up Anime.js seul satisfait ce critere.
   - What's unclear: l'utilisateur veut-il une animation **plus** marquee (pulse + count-up) ou seulement le count-up ?
   - Recommendation: laisser le planner decider, mais inclure le pulse comme task **separable** (peut etre execute ou skippe sans casser ANIM-01).

2. **Rate-limiting des tweens en cas de burst SSE ?**
   - What we know: Pitfall 2 — si 10 votes/sec, les tweens se chevauchent.
   - What's unclear: realisme du scenario (la prod voit-elle vraiment 10 votes/sec ?). Le projet n'a pas de stats publiques.
   - Recommendation: implementer Option A (laisser tel quel) et reserver Option B comme followup si l'E2E revele du scintillement.

3. **Animer aussi `#execVoteParticipationBar` et `#execVoteParticipationPct` ?**
   - What we know: la barre de participation utilise deja `transition: width` (design-system.css:2598). Le pourcentage est mis a jour par `textContent =` direct dans `refreshExecKPIs()` ligne 527.
   - What's unclear: faut-il etendre Pattern 1 a ce pourcentage aussi ? (ANIM-02 mentionne "barres" au pluriel mais semble cibler Pour/Contre/Abstain).
   - Recommendation: hors-scope strict de ANIM-02 (qui est sur "barres de progression des resultats"), mais cosmetiquement souhaitable. A traiter comme task optionnelle.

---

## Sources

### Primary (HIGH confidence)
- `public/operator.htmx.html:1334-1395` — DOM vote card (counters + bars)
- `public/assets/js/pages/operator-exec.js:42-98` — `animateKpiValue` + `animateKpiPct` (helpers existants)
- `public/assets/js/pages/operator-exec.js:622-707` — `refreshExecView` + `refreshExecVote` (cible principale)
- `public/assets/js/pages/operator-realtime.js:101-115` — SSE handler `vote.cast`/`vote.updated` (chaine deja correcte)
- `public/assets/js/pages/operator-motions.js:438-460` — `loadBallots()` (source de donnees)
- `public/assets/css/operator.css:868-944` — styles vote-counters et vote-bars (transition deja en place)
- `public/assets/css/design-system.css:3059-3068` — reduced-motion global
- `public/assets/css/design-system.css:524-540` — design tokens duration/easing
- `public/operator.htmx.html:1717` — chargement Anime.js 3.2.2 CDN
- `.planning/phases/01-checklist-operateur/01-RESEARCH.md` — patron Phase 1 (`@media prefers-reduced-motion: no-preference` block)
- `.planning/phases/02-mode-focus/02-CONTEXT.md` — D-7 hide list (verification que `.op-vote-card` reste visible en focus mode)

### Secondary (MEDIUM confidence)
- MDN `prefers-reduced-motion` — comportement standard de la media query
- `.planning/REQUIREMENTS.md` — ANIM-01..03 confirmes
- Anime.js 3.2.2 docs — `easeOutQuad`, `round`, `update` (deja documente in-code)

### Tertiary (LOW confidence)
- Aucun. Toutes les conclusions sont sourcees au code.

---

## Metadata

**Confidence breakdown:**
- Standard stack: HIGH — Anime.js + CSS transitions deja installes et utilises, aucune supposition.
- Architecture: HIGH — chaine SSE -> refresh -> DOM update inspectee ligne par ligne.
- Pitfalls: HIGH — pitfalls 1-7 identifies via lecture du code et tests d'imagination des cas limites (first-render, burst, screen readers).
- Validation: MEDIUM — `tests/e2e/specs/operator-animations.spec.js` n'existe pas encore, doit etre cree en Wave 0.

**Research date:** 2026-04-29
**Valid until:** 2026-05-29 (code stable, aucune migration prevue, Anime.js 3.2.2 LTS)
