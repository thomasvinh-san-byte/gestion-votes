# Plan de mise en oeuvre — Console Opérateur bimodale

Refactoring de `operator.htmx.html` + `operator-tabs.js` + `operator.css`
pour aligner la console opérateur sur le wireframe (mode Préparation / Exécution).

**Statut : TERMINÉ** — toutes les phases sont implémentées.

---

## Phase 0 — Nettoyage préalable ✅

### 0.1 Supprimer `operator.js` legacy ✅
- `operator.js` supprimé ; seul `operator-tabs.js` est chargé.

### 0.2 Retirer le wizard de la page opérateur ✅
- Pas de `<div id="wizard-progress">` ni de `<script>` session-wizard dans operator.
- `session-wizard.js` : code `isOperatorPage` supprimé, skip explicite dans `init()`,
  entrée operator retirée de `PAGE_STEP_MAP`, styles `.wizard-readonly` supprimés.
- Le wizard continue de fonctionner sur les autres pages (meetings, members).

---

## Phase 1 — Nouvelle barre de séance (meeting bar) ✅

### 1.1 HTML ✅
Meeting bar avec 2 lignes :
- **Ligne 1** : sélecteur séance + badge statut + chip santé + horloge + bouton Actualiser
- **Ligne 2** : context hint + mode switch (Préparation/Exécution) + bouton principal + lien Projection

### 1.2 CSS ✅
- `.meeting-bar`, `.meeting-bar-top`, `.meeting-bar-actions`, `.meeting-bar-chip`
- `.health-dot` (3 états : ok, warn, danger)
- `.mode-switch` (segmented control avec `.active`)
- `.context-hint` (italique, muted)
- Grid layout : `grid-template-rows: auto auto auto 1fr`

### 1.3 JS ✅
- Horloge `tick()` (setInterval 30s, format HH:MM)
- `btnBarRefresh` → `loadAllData()`
- `btnProjector` → lien vers `/public.htmx.html?meeting_id=…`
- `updateHealthChip()` basé sur le score de conformité
- `updateContextHint()` selon le mode et l'état

---

## Phase 2 — Mode switch (Préparation / Exécution) ✅

### 2.1 Variable d'état ✅
```js
let currentMode = 'setup'; // 'setup' | 'exec'
```

### 2.2 Fonction `setMode(mode)` ✅
- Guard : empêche l'entrée en mode exec si la séance n'est pas `live`
- Met à jour `aria-pressed` sur les boutons
- Désactive `btnModeExec` quand la séance n'est pas live
- Bascule la visibilité : `viewSetup` ↔ `viewExec`, masque/affiche `tabsNav`
- Appelle `updatePrimaryButton()`, `updateContextHint()`, `announce()`
- En mode exec : `refreshExecView()` + `startSessionTimer()`

### 2.3 HTML — Enveloppes de vue ✅
- `<section id="viewSetup">` contient le dashboard + les 7 onglets
- `<section id="viewExec">` contient la grille 3 colonnes + KPI strip

### 2.4 Bascule automatique ✅
- `launchSession()` → `setMode('exec')`
- Au chargement, si `meetingStatus === 'live'` → `setMode('exec')`
- Polling détecte un nouveau vote → notification + bascule vers onglet vote ou refresh exec

---

## Phase 3 — Vue Préparation restructurée ✅

### 3.1 Dashboard de synthèse ✅
Grille 2 colonnes au-dessus des onglets : checklist conformité + panneau alertes.

### 3.2 Checklist de conformité ✅
4 étapes avec score 0-4 :

| # | Étape | Condition | Implémentation |
|---|-------|-----------|----------------|
| 1 | Registre des membres | `membersCache.length > 0` | `getConformityScore()` |
| 2 | Présences & procurations | Au moins 1 présent/distant | `attendanceCache` |
| 3 | Convocations | Toujours validé (optionnel) | score++ |
| 4 | Règlement & présidence | Policy ou président assigné | `currentMeeting.quorum_policy_id` |

- `renderConformityChecklist()` rend les items avec état (done/pending/optional)
- `updateHealthChip()` met à jour le chip santé dans la meeting bar
- `btnPrimary` activé si score ≥ 3

### 3.3 Panneau d'alertes ✅
- `renderAlertsPanel(targetId, countId)` réutilisable (setup + exec)
- Sources : checklist incomplète, quorum non atteint, appareils inactifs, vote sans bulletins

### 3.4 Onglets ✅
7 onglets conservés : Paramètres, Résolutions, Présences, Procurations, Parole, Vote, Résultats.

---

## Phase 4 — Vue Exécution (grille 3 colonnes) ✅

### 4.1 HTML ✅
- KPI strip : quorum bar, participation %, durée séance, résolutions X/Y
- Grille 3 colonnes : Vote en cours | Files & opérations | Alertes
- Vote : titre + KPIs (pour/contre/abstention) + barre participation + bouton clôturer
- Opérations : parole (orateur + file), appareils (en ligne/inactifs), votes manuels (recherche + P/C/A)
- Toggle `#execNoVote` / `#execActiveVote` selon qu'un vote est ouvert
- `renderExecQuickOpenList()` : boutons d'ouverture rapide dans le panel no-vote

### 4.2 CSS ✅
- `.exec-grid` : `grid-template-columns: 1.25fr 1fr 1fr`
- `.exec-card`, `.exec-subcard`, `.exec-kpi`, `.live-badge`
- `.exec-kpi-strip` avec séparateurs
- `.exec-participation-row` avec barre de progression
- `.exec-manual-vote-row` avec boutons P/C/A

### 4.3 JS ✅
- `refreshExecView()` → `refreshExecKPIs()`, `refreshExecVote()`, `refreshExecSpeech()`,
  `refreshExecDevices()`, `refreshExecManualVotes()`, `refreshAlerts()`
- `refreshExecVote()` toggle `execNoVote`/`execActiveVote` et les compteurs
- `renderExecQuickOpenList()` affiche les résolutions ouvrables
- Votes manuels avec recherche et binding des boutons
- Timer séance basé sur `opened_at`

---

## Phase 5 — Masquer les onglets en mode Exécution ✅

### 5.1 Visibilité ✅
Géré dans `setMode()` :
- `mode === 'exec'` : `viewSetup.hidden = true`, `viewExec.hidden = false`, `tabsNav` masqué
- `mode === 'setup'` : `viewSetup.hidden = false`, `viewExec.hidden = true`, `tabsNav` affiché

Le setup-dashboard est contenu dans `#viewSetup`, donc automatiquement masqué en exec.

### 5.2 Grid layout CSS ✅
```css
[data-page-role="operator"] .app-shell {
  grid-template-rows: auto auto auto 1fr;
  grid-template-areas:
    "sidebar meetingbar"
    "sidebar meetingbar-actions"
    "sidebar tabs"
    "sidebar main";
}
```

---

## Phase 6 — Polish et intégration ✅

### 6.1 Transitions automatiques ✅
- `launchSession()` → `setMode('exec')` + annonce
- Au chargement, si `live` → `setMode('exec')` via `showMeetingContent()`
- `closeSession()` → `setMode('setup')` + `switchTab('resultats')`
- `doTransition()` : auto-switch selon le nouveau statut
  (live → exec, closed/validated/archived → setup + resultats)

### 6.2 Bouton principal contextuel ✅
- Pas de séance → disabled, "Ouvrir la séance"
- Setup + prêt (score ≥ 3) → "Ouvrir la séance" → `launchSession()`
- Setup + live → "Passer en exécution" → `setMode('exec')`
- Setup + terminé → disabled, "Séance terminée"
- Exec + vote ouvert → "Voir le vote" → scroll vers `execVoteCard`
- Exec + pas de vote → "Préparation" → `setMode('setup')`
- `btnModeExec` désactivé si la séance n'est pas `live`

### 6.3 `aria-live` et annonces ✅
- `<div class="sr-only" id="srAnnounce" aria-live="polite">`
- `announce()` pour : changement de mode, séance lancée, vote ouvert (manuel + polling),
  vote clôturé, transitions d'état

### 6.4 Responsive ✅
- `< 1100px` : exec-grid → 1 colonne, KPI strip → wrap
- `< 980px` : sidebar masquée sur la page opérateur, meeting bar full-width
- `< 768px` : tabs-nav scroll horizontal, meeting bar empilée, attendance grid 1 col
- `< 480px` : tailles réduites pour quick-counts et results

---

## Fichiers modifiés

| Fichier | Rôle |
|---------|------|
| `public/operator.htmx.html` | Structure HTML bimodale (meeting bar, viewSetup, viewExec) |
| `public/assets/js/pages/operator-tabs.js` | Logique JS (3 430 lignes) : tabs, mode switch, exec view, polling |
| `public/assets/css/operator.css` | Styles opérateur (1 984 lignes) : meeting bar, exec grid, responsive |
| `public/assets/js/services/session-wizard.js` | Wizard nettoyé (skip operator, code mort supprimé) |

## Ce qui n'a PAS changé

- Les 7 onglets (Paramètres, Résolutions, Présences, Procurations, Parole, Vote, Résultats)
- La logique métier existante (API calls, caches, state machine)
- Le wizard sur les AUTRES pages (meetings, members)
- La sidebar globale
- Les composants web (`ag-searchable-select`, `ag-popover`, `ag-toast`)
- Le drawer system (conservé pour les modales secondaires)
- Les imports CSV, les exports, le système de rôles
