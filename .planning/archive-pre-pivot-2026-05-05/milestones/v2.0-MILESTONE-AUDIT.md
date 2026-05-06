---
milestone: v2.0 — Operateur Live UX
audit_date: 2026-04-29
verdict: PASS
audit_type: cross-phase integration
verifier: claude (gsd, opus 4.7 1m)
phases_covered: [01-checklist-operateur, 02-mode-focus, 03-animations-vote]
files_inspected: 5 (operator.htmx.html, operator.css, operator-exec.js, operator-realtime.js, operator-tabs.js)
---

# v2.0 — Cross-Phase Integration Audit

**Milestone:** v2.0 — Operateur Live UX
**Phases auditees:** Phase 1 (Checklist), Phase 2 (Focus Mode), Phase 3 (Animations Vote)
**Methode:** Inspection statique cross-fichier — audit du wiring inter-phases (exports/imports, references DOM, chaines SSE, partage de fonctions via `window.OpS.fn`).

---

## 1. Verdict

**PASS — Pret pour cloture du milestone.**

Les 3 phases s'integrent proprement sans conflit:
- Toutes les fonctions inter-modules sont definies, registrees sur `O.fn`, et appelees correctement.
- Les 18 IDs DOM critiques referencees par le JS existent dans le HTML.
- L'orchestrateur `refreshExecView()` appelle dans le bon ordre les 3 refreshs des 3 phases (`refreshExecVote` Phase 3, `refreshExecChecklist` Phase 1, `refreshFocusQuorum` Phase 2).
- La chaine SSE `vote.cast -> loadBallots -> refreshExecView` declenche simultanement les compteurs animes (Phase 3), la mise a jour checklist (Phase 1), et la mise a jour quorum focus (Phase 2).
- Aucun conflit de specificite CSS detecte entre `.op-focus-mode` et `.op-checklist-row--alert`.
- Le respect de `prefers-reduced-motion` est wired aux 3 niveaux (JS guard, CSS local, CSS global).
- Pas de regression entre phases: chaque phase n'a ajoute que des chemins de code nouveaux, sans modifier le comportement des phases precedentes.

Les 8 verifications visuelles manuelles (animation timing, layout 1080p, OS reduced-motion) restent en attente d'execution Playwright en CI — elles sont documentees dans `04-AUDIT.md` section 6 et ne bloquent pas la fermeture du milestone du point de vue integration code.

---

## 2. Workflows Utilisateur — Trace End-to-End

### Workflow 1 — Operateur ouvre la page en mode setup

**Trace:**
- `setMode('setup')` (operator-tabs.js:2092) cache `viewExec`, affiche `viewSetup`
- `setMode('setup')` ligne 2113-2115: `checklistPanel.hidden = true` (Phase 1 panel cache)
- `setMode('setup')` ligne 2138-2141: `viewExec.classList.remove('op-focus-mode')` (etat visuel focus efface, sessionStorage preserve per Pitfall 4)

**Statut: WORKS.** Tous les panneaux des 3 phases sont caches en mode setup.

### Workflow 2 — Bascule setup -> exec : panneau checklist apparait

**Trace:**
- `setMode('exec')` ligne 2099 -> `refreshExecView()` qui appelle `refreshExecChecklist()` ligne 767 + `refreshFocusQuorum()` ligne 768
- ligne 2104-2116: `checklistPanel.hidden = false`, restauration de `opChecklistCollapsed` depuis sessionStorage, sync `aria-expanded`
- ligne 2121-2137: restauration de `opFocusMode` depuis sessionStorage. Si focus actif, `viewExec` recoit la classe `.op-focus-mode`, `aria-pressed=true`, `#opFocusQuorum.hidden=false`, et `O.fn.refreshFocusQuorum()` est appele

**Statut: WORKS.** L'ordre est correct (checklist d'abord, focus restoration ensuite, conformement a D-5 Phase 2).

### Workflow 3 — Toggle focus actif : checklist se cache, layout 5 zones

**Trace:**
- Click sur `#opBtnFocusMode` (operator-tabs.js:3189-3208)
- `view.classList.toggle('op-focus-mode')` -> CSS rule `.op-focus-mode .op-checklist-panel { display: none !important }` (operator.css:2167) cache le panneau Phase 1
- `aria-pressed`, `title`, `aria-label` syncronises ligne 3195-3200
- `sessionStorage.setItem('opFocusMode', String(isFocus))` ligne 3201
- `#opFocusQuorum.hidden = !isFocus` ligne 3204-3205 -> CSS revele le bloc quorum dedie via `display: flex !important`
- `O.fn.refreshFocusQuorum()` ligne 3207 -> populate les 3 elements `#opFocusQuorumValue`/`Status`/etat

**Statut: WORKS.** La transition est instantanee (pas de transition CSS sur `.op-focus-mode` — D-5 Pitfall 5 respecte). Les 13 zones secondaires sont cachees via la regle CSS aux lignes 2159-2173.

### Workflow 4 — Vote arrive via SSE : compteur anime + quorum se met a jour PARTOUT

**Trace (chaine inter-phase critique):**
- SSE event `vote.cast` recu (operator-realtime.js:105-115)
- `O.fn.loadBallots(motionId)` recharge `O.ballotsCache`
- `.then(() => O.fn.refreshExecView())` ligne 110 (en mode exec uniquement)
- `refreshExecView()` (operator-exec.js:757-769) appelle dans l'ordre:
  - `refreshExecVote()` ligne 759 -> Phase 3: `animateVoteCounter()` sur `#execVoteFor/Against/Abstain` (operator-exec.js:814-816), `_bumpVoteCounter` si delta > 0 (lignes 822-824) — ANIM-01 satisfait
  - `refreshExecChecklist()` ligne 767 -> Phase 1: `setChecklistRow('votes', ...)` met a jour `#opChecklistVotesValue`, et `setChecklistRow('quorum', ...)` recalcule le ratio — CHECK-02 satisfait
  - `refreshFocusQuorum()` ligne 768 -> Phase 2: met a jour `#opFocusQuorumValue` et `#opFocusQuorumStatus` (visible si focus actif, sinon caches mais data fresh) — FOCUS-03 D-3 single-source satisfait

**Statut: WORKS.** Une seule chaine SSE met a jour les 3 phases simultanement. La valeur du quorum est garantie identique entre la rangee checklist (Phase 1) et le bloc focus (Phase 2) car les deux appellent `computeQuorumStats()` (D-3 single source).

### Workflow 5 — Toggle focus off : checklist re-apparait, bloc focus se cache

**Trace:**
- Click sur `#opBtnFocusMode` deja en etat focus actif
- `view.classList.toggle('op-focus-mode')` -> la classe est retiree
- `.op-checklist-panel` reapparait (la regle `display: none !important` ne s'applique plus)
- `#opFocusQuorum.hidden = true` ligne 3205
- Le panneau checklist Phase 1 retrouve son etat collapsed/expanded depuis le DOM (la classe `--collapsed` n'a pas ete touchee)

**Statut: WORKS.** Aucune action sur le sessionStorage `opChecklistCollapsed` lors du toggle focus — invariant Phase 1 preserve.

### Workflow 6 — Bascule exec -> setup : tous les panneaux se cachent

**Trace:**
- `setMode('setup')`
- ligne 2113-2115: `checklistPanel.hidden = true` (Phase 1 cache)
- ligne 2140: `viewExec.classList.remove('op-focus-mode')` (Phase 2 visuel efface)
- `sessionStorage.opFocusMode` PRESERVE (Pitfall 4 D-5) — pas de `removeItem` ligne 2138-2141
- `sessionStorage.opChecklistCollapsed` PRESERVE — pas de `removeItem`

**Statut: WORKS.** Les deux etats persistes sont gardes pour la prochaine entree en exec.

### Workflow 7 — Setup -> exec : focus mode restaure depuis sessionStorage

**Trace:**
- `setMode('exec')` ligne 2121: `var isFocus = sessionStorage.getItem('opFocusMode') === 'true'`
- ligne 2123: `viewExec.classList.toggle('op-focus-mode', isFocus)` — restaure la classe
- ligne 2126-2131: restaure aria-pressed, title, aria-label
- ligne 2135: `focusQuorum.hidden = !isFocus` — restaure visibilite du bloc dedie
- ligne 2137: `if (isFocus) O.fn.refreshFocusQuorum()` — populate les valeurs immediatement (pas de "—" stale)

**Statut: WORKS.** La restauration mirror parfaitement le toggle, et `refreshExecView()` ligne 2099 deja appele en amont assure que tous les caches sont a jour avant la restauration.

---

## 3. Invariants Cross-Phase Verifies

| Invariant | Phase | Statut | Evidence |
|---|---|---|---|
| `O.fn.refreshExecChecklist` defini, registre, appele | 1 | OK | Defini operator-exec.js:1038, registre :1218, appele :767 + operator-realtime.js:150 |
| `O.fn.updateChecklistSseRow` defini, registre, appele | 1 | OK | Defini :1025, registre :1219, appele operator-realtime.js:38 |
| `O.fn.refreshFocusQuorum` defini, registre, appele | 2 | OK | Defini :1068, registre :1221, appele :768 + operator-tabs.js:2137 + :3207 |
| `animateVoteCounter` defini, integre dans refreshExecVote | 3 | OK | Defini :145, appele :814-816 dans refreshExecVote |
| `_bumpVoteCounter` defini, gate sur delta > 0 | 3 | OK | Defini :213, gate :822-824 dans refreshExecVote |
| Single source quorum (D-3 Phase 2) | 1+2 | OK | `computeQuorumStats()` appele a la fois :1040 (Phase 1 checklist) et :1074 (Phase 2 focus) |
| sessionStorage.opChecklistCollapsed independent de opFocusMode | 1+2 | OK | Toggle Phase 1 (op-tabs.js:3184) et Phase 2 (:3201) ecrivent des cles distinctes; aucun handler ne lit la cle de l'autre |
| Phase 3 anim non-applicable a Phase 1 panel | 1+3 | OK | Phase 3 cible `.op-vote-counter--bump` (cell parent de `#execVoteFor/Against/Abstain`); Phase 1 cible `.op-checklist-row--alert .op-checklist-icon` — selectors disjoints |
| `prefers-reduced-motion` respecte aux 3 niveaux | 1+2+3 | OK | Phase 1: CSS `@media (prefers-reduced-motion: no-preference)` guard sur `checklistPulse` (operator.css:2138-2151); Phase 3: JS `PREFERS_REDUCED_MOTION.matches` guard (operator-exec.js:152, 821); Global: `design-system.css:3059-3068` neutralise toutes les animations/transitions a 0.01ms |
| Focus mode cache panneau checklist (D-2 Phase 2) | 1+2 | OK | `.op-focus-mode .op-checklist-panel { display: none !important }` (operator.css:2167) — pas de conflit possible avec `.op-checklist-row--alert` car panneau cache |
| Aucune phase ne modifie operator-realtime.js sauf Phase 1 | 1 | OK | Phase 2 et Phase 3 n'ont pas modifie ce fichier (verification par `git log` dans 04-AUDIT.md) |

**Score: 11/11 invariants intacts.**

---

## 4. Conflits CSS Potentiels — Audit Specificite

| Selecteur Phase A | Selecteur Phase B | Conflit possible ? | Resolution |
|---|---|---|---|
| `.op-checklist-row--alert` (P1, sp 0,2,0) | `.op-focus-mode .op-checklist-panel { display: none }` (P2, sp 0,2,0 + !important) | NON | P2 cache le panneau entier en focus, donc `.op-checklist-row--alert` n'a aucun rendu visible. Pas de conflit. |
| `.op-vote-counter--bump` (P3) | `.op-focus-mode .op-vote-card` (P2, padding) | NON | P3 cible la cellule compteur enfant, P2 cible la card parent. Selectors disjoints. La bump animation fonctionne donc en mode normal ET en mode focus. |
| `.op-bar-fill { transition: width }` (P3) | `.op-focus-mode .op-bar-fill` | NON | Aucune regle `.op-focus-mode` ne touche `.op-bar-fill` (verifie par grep). La transition glide reste active dans les deux modes. |
| `@keyframes checklistPulse` (P1) | `@keyframes opVoteCounterBump` (P3) | NON | Noms de keyframe distincts; aucun risque d'override. |

**Conclusion:** Aucun conflit CSS detecte. Les 3 phases coexistent proprement.

---

## 5. Conflits Trouves

**Aucun.**

Les 11 commits feature de v2.0 ont ete crees en respectant strictement les locked decisions de chaque phase et n'ont touche que les chemins de code nouveaux. Phase 2 ajoute des regles CSS APRES Phase 1 (operator.css:2125+), Phase 3 ajoute des regles APRES Phase 2 (operator.css:944, 978-988 — toutefois Phase 3 a aussi mis a jour le commentaire au-dessus de la regle `.op-bar-fill` sans modifier la propriete CSS). Aucune phase n'a supprime ou re-ecrit du code des phases precedentes.

---

## 6. Verifications Visuelles Manuelles En Attente

Conformement au `04-AUDIT.md` section 6, 8 items restent a valider visuellement (Playwright bloque localement par `libatk-1.0.so.0` manquant). Ces verifications sont des controles de **perception** (timing animation, layout viewport, comportement OS reduced-motion) qui ne peuvent pas etre couvertes par inspection statique — elles sont deferrees a l'execution CI.

L'integration **code** est complete et coherente. La couverture **visuelle** sera obtenue lors du run Playwright en CI ou par QA humain avant le tag de release.

---

## 7. Sign-Off

### Pre-requis Cloture Milestone

- [x] Verification de chaque phase passee (Phase 1 PASS, Phase 2 PASS, Phase 3 PASS, Phase 4 AUDIT PASS)
- [x] Audit cross-phase integration PASS (ce document)
- [x] Aucun conflit code/CSS detecte
- [x] Tous les workflows utilisateur traces end-to-end
- [x] Tous les invariants Phase 1, 2, 3 intacts
- [ ] Suite Playwright E2E executee en CI — PENDING (deferre, infra)
- [ ] 8 verifications manuelles visuelles — PENDING (deferre, QA)

### Recommendation

**Ce milestone est pret pour `/gsd:complete-milestone`** du point de vue integration code. Les deux pendings restants (Playwright CI + QA manuel) peuvent etre executes en parallele de la cloture administrative et ne bloquent pas la promotion du code sur `main` (deja la). Le tag de release v2.0 reste subordonne au passage de la suite E2E en CI.

---

_Audit conduit par Claude (gsd, opus 4.7 1m)._
_Date: 2026-04-29._
