# Plan de mise en oeuvre — Console Opérateur bimodale

Refactoring de `operator.htmx.html` + `operator-tabs.js` + `operator.css`
pour aligner la console opérateur sur le wireframe (mode Préparation / Exécution).

---

## Phase 0 — Nettoyage préalable

### 0.1 Supprimer `operator.js` legacy
- **Fichier** : `public/assets/js/pages/operator.js` (1 800 lignes)
- Non chargé par l'HTML actuel (seul `operator-tabs.js` est référencé)
- Action : `git rm public/assets/js/pages/operator.js`

### 0.2 Retirer le wizard de la page opérateur
- **Fichier** : `operator.htmx.html` ligne 43 — supprimer `<div id="wizard-progress"></div>`
- **Fichier** : `operator.htmx.html` ligne 646 — retirer le `<script>` de `session-wizard.js`
- **Fichier** : `session-wizard.js` — le wizard continue de fonctionner sur les autres
  pages (meetings, members) ; on le masque uniquement sur la page opérateur.
  La section `isOperatorPage` (lignes 289-306) devient inutile — on peut la simplifier
  en ne rendant rien si `isOperatorPage`.

---

## Phase 1 — Nouvelle barre de séance (meeting bar)

### 1.1 HTML (`operator.htmx.html`)
Remplacer la `session-selector` (lignes 21-28) et intégrer le mode switch.

Nouvelle structure :

```
<section class="meeting-bar" aria-label="Sélection séance, état et mode">
  <div class="meeting-bar-inner">
    <!-- Ligne 1 : sélecteur + statut + santé + horloge + actions rapides -->
    <div class="meeting-bar-top">
      <div class="meeting-bar-left">
        <select id="meetingSelect">...</select>
        <span class="chip" id="meetingStatus">
          <span class="status-dot"></span>
          <strong id="statusText">Aucune séance</strong>
        </span>
        <span class="chip" id="meetingHealth">
          <strong id="healthText">—</strong>
          <span id="healthHint">pré-requis</span>
        </span>
      </div>
      <div class="meeting-bar-right">
        <span class="chip"><strong id="clock">--:--</strong> local</span>
        <button class="btn btn-secondary" id="btnRefresh">Actualiser</button>
        <button class="btn btn-danger" id="btnEmergency">Suspendre</button>
      </div>
    </div>
    <!-- Ligne 2 : mode switch + action principale + projection -->
    <div class="meeting-bar-actions">
      <div class="meeting-bar-left">
        <span class="context-hint" id="contextHint">Sélectionnez une séance…</span>
        <div class="mode-switch" id="modeSwitch" role="group" aria-label="Mode">
          <button id="btnModeSetup" aria-pressed="true">Préparation</button>
          <button id="btnModeExec" aria-pressed="false">Exécution</button>
        </div>
      </div>
      <div class="meeting-bar-right">
        <button class="btn btn-primary" id="btnPrimary" disabled>Action principale</button>
        <button class="btn btn-secondary" id="btnProjector" disabled>Écran de projection</button>
      </div>
    </div>
  </div>
</section>
```

### 1.2 CSS (`operator.css`)
- Remplacer `.session-selector` par `.meeting-bar` (sticky, backdrop-filter)
- Ajouter `.meeting-bar-inner`, `.meeting-bar-top`, `.meeting-bar-actions`
- Ajouter `.chip` (mono, bordure, fond blanc)
- Ajouter `.status-dot` (3 états : neutre, warn, danger)
- Ajouter `.mode-switch` (segmented control avec `aria-pressed`)
- Ajouter `.context-hint` (serif, muted)
- Grid layout : `grid-template-rows: auto auto auto 1fr` pour ajouter la meeting-bar

### 1.3 JS (`operator-tabs.js`)
- Ajouter horloge `tick()` (setInterval 1s, format HH:MM)
- Ajouter `btnRefresh` → appeler `loadAllData()`
- Ajouter `btnEmergency` → confirmation + toast (placeholder pour v2)
- Ajouter `btnProjector` → `window.open('/public.htmx.html?meeting_id=…')`
- Ajouter `updateHealthChip()` basé sur la checklist existante
- Ajouter `updateContextHint()` selon le mode et l'état

---

## Phase 2 — Mode switch (Préparation / Exécution)

### 2.1 Variable d'état
```js
let currentMode = 'setup'; // 'setup' | 'exec'
```

### 2.2 Fonction `setMode(mode)`
- Mettre à jour `aria-pressed` sur les boutons
- Basculer la visibilité :
  - `mode === 'setup'` → afficher `#viewSetup` (tabs + onglets), masquer `#viewExec`
  - `mode === 'exec'` → afficher `#viewExec` (grille 3 colonnes), masquer `#viewSetup`
- Mettre à jour le texte du `btnPrimary` :
  - Setup : "Ouvrir la séance" (si prêt) ou "Continuer la préparation"
  - Exec : "Accéder au vote"
- Mettre à jour `contextHint`
- Appeler `announce()` pour les lecteurs d'écran

### 2.3 HTML — Enveloppes de vue
Ajouter dans `<main>` :
```html
<!-- Vue Préparation (tabs existants enveloppés) -->
<section id="viewSetup" hidden>
  <!-- dashboard + checklist + alertes (nouveau) -->
  <!-- tabs existants -->
</section>

<!-- Vue Exécution (nouveau) -->
<section id="viewExec" hidden>
  <!-- grille 3 colonnes -->
</section>
```

### 2.4 Bascule automatique
- Quand `launchSession()` réussit → `setMode('exec')`
- Quand un vote s'ouvre (détection polling/ws) → si mode=setup, proposer bascule
- Au chargement, si `meetingStatus === 'live'` → `setMode('exec')` par défaut

---

## Phase 3 — Vue Préparation restructurée

### 3.1 Dashboard de synthèse (au-dessus des onglets)
Extraire les KPIs actuels de l'onglet Paramètres et les placer en haut de `#viewSetup`.

```html
<div class="setup-grid">
  <!-- Colonne gauche : dashboard + checklist -->
  <section class="card">
    <div class="card-header">
      <h2>Préparation</h2>
      <span class="chip"><strong id="lastUpdate">--:--:--</strong> maj</span>
    </div>
    <div class="card-body">
      <!-- KPIs : Membres | Présents/Éligibles | Appareils -->
      <div class="kpis">...</div>
      <!-- Checklist conformité (remplace le wizard + statusChecklist) -->
      <div class="conformity-checklist">
        <!-- 4 étapes : Registre, Présences, Convocations, Règlement -->
        <!-- Score : X/4 validés -->
        <!-- Bouton "Ouvrir la séance" (activé si ≥3) -->
      </div>
    </div>
  </section>

  <!-- Colonne droite : Alertes -->
  <section class="card" id="alertsPanel">
    <div class="card-header">
      <h2>Alertes</h2>
      <span class="chip"><strong id="alertCount">0</strong> actives</span>
    </div>
    <div class="card-body">
      <div id="alertsList">Aucune alerte.</div>
    </div>
  </section>
</div>
```

### 3.2 Checklist de conformité (remplace le wizard dans cette page)
4 étapes telles que définies dans le wireframe :

| # | Étape | Condition de validation | Source de données |
|---|-------|------------------------|-------------------|
| 1 | Registre des membres | `membersCache.length > 0` | `loadMembers()` |
| 2 | Présences & procurations | Au moins 1 présent + proxies OK | `attendanceCache` + `proxiesCache` |
| 3 | Convocations | Optionnel (toujours "Option") | `invitationsStats` |
| 4 | Règlement & présidence | Policies assignées + président | `currentMeeting.quorum_policy_id` + roles |

Nouvelle fonction `renderConformityChecklist()` :
- Calcule le score (0-4)
- Rend les 4 étapes avec état (validé / à faire / option)
- Active le bouton "Ouvrir la séance" si score ≥ 3
- Met à jour le chip santé dans la meeting bar

### 3.3 Panneau d'alertes
Nouvelle fonction `renderAlerts(target, items)` réutilisable (setup + exec) :
- Accepte un tableau `[{ title, message, severity }]`
- Severity : `info`, `warning`, `critical`
- Styling : bordure gauche colorée, badge sévérité
- Sources d'alertes :
  - Checklist incomplète → "Préparation incomplète"
  - Quorum non atteint (quand live) → "Quorum non atteint"
  - Appareils inactifs → "X appareils inactifs"
  - Vote ouvert sans votes → "Aucun vote enregistré"

### 3.4 Onglets (inchangés)
Les 7 onglets existants sont conservés tels quels sous le dashboard.
On nettoie l'onglet Paramètres :
- Retirer les quick-counts de présences (déjà dans le dashboard)
- Retirer le `dashboardCard` et `devicesCard` (déplacés dans la vue Exécution)
- Retirer le `launchBanner` (remplacé par la checklist)
- Conserver : Membres card, Invitations card, Infos générales, Politiques, Rôles, État

---

## Phase 4 — Vue Exécution (grille 3 colonnes)

### 4.1 HTML
```html
<section id="viewExec" hidden aria-label="Mode exécution">
  <div class="exec-grid">
    <!-- Col 1 : Vote en cours -->
    <section class="card" id="execVoteCard">
      <div class="card-header">
        <h2>Vote en cours</h2>
        <span class="live-badge"><span class="pulse"></span> LIVE</span>
      </div>
      <div class="card-body">
        <!-- Motion active : titre + metadata -->
        <!-- KPIs : Pour / Contre / Abstention -->
        <!-- Bouton Clôturer (avec confirmation) -->
        <!-- Texte de la motion (si disponible) -->
      </div>
    </section>

    <!-- Col 2 : Files & opérations -->
    <section class="card" id="execOpsCard">
      <div class="card-header">
        <h2>Files & opérations</h2>
      </div>
      <div class="card-body">
        <!-- Sous-card : Parole (orateur + file) -->
        <!-- Sous-card : Appareils (en ligne / inactifs) -->
        <!-- Sous-card : Votes manuels (recherche + 3 boutons) -->
      </div>
    </section>

    <!-- Col 3 : Alertes -->
    <section class="card" id="execAlertsCard">
      <div class="card-header">
        <h2>Alertes</h2>
        <span class="chip"><strong id="execAlertCount">0</strong> actives</span>
      </div>
      <div class="card-body">
        <div id="execAlertsList">Aucune alerte.</div>
      </div>
    </section>
  </div>
</section>
```

### 4.2 CSS
```css
.exec-grid {
  display: grid;
  grid-template-columns: 1.25fr 1fr 1fr;
  gap: 1rem;
  align-items: start;
}
@media (max-width: 1100px) {
  .exec-grid { grid-template-columns: 1fr; }
}
```

### 4.3 JS — Fonctions de la vue Exécution
Réutiliser au maximum le code existant de `operator-tabs.js` :

| Fonctionnalité Exec | Source existante à réutiliser |
|---|---|
| Vote en cours (motion + KPIs) | `loadVoteTab()` + `loadBallots()` (lignes ~2171+) |
| Bouton Clôturer | `closeVote()` existant |
| Parole | `loadSpeechQueue()` + `renderCurrentSpeaker()` (lignes ~1627+) |
| Appareils | `loadDevices()` (lignes ~600+) |
| Votes manuels | `renderManualVoteList()` + `castManualVote()` (lignes ~2171+) |
| Alertes | Nouveau `renderAlerts()` (partagé avec setup) |

Refactorer ces fonctions pour qu'elles acceptent un conteneur cible en paramètre,
permettant le rendu dans les onglets (setup) OU dans la grille exec.

---

## Phase 5 — Masquer les onglets en mode Exécution

### 5.1 Visibilité des onglets
Quand `mode === 'exec'` :
- `tabsNav.style.display = 'none'`
- Tous les `tab-content` masqués
- Le dashboard setup masqué
- Seul `#viewExec` visible

Quand `mode === 'setup'` :
- `tabsNav.style.display = 'flex'`
- `#viewSetup` visible (dashboard + onglets)
- `#viewExec` masqué

### 5.2 Mise à jour du grid layout CSS
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
La meeting bar prend désormais 2 lignes. Les tabs sont masqués en mode exec.

---

## Phase 6 — Polish et intégration

### 6.1 Transition automatique Setup → Exec
- `launchSession()` → `setMode('exec')` après transition réussie
- Au chargement, si `currentMeetingStatus === 'live'` → `setMode('exec')`
- Si statut passe à `closed` ou `validated` → revenir en `setup` (onglet Résultats)

### 6.2 Bouton principal contextuel
- Pas de séance → disabled, "Action principale"
- Setup + incomplet → "Continuer la préparation" → ouvre l'onglet Paramètres
- Setup + prêt → "Ouvrir la séance" → lance `launchSession()`
- Exec → "Accéder au vote" → scroll vers le panel vote

### 6.3 `aria-live` et annonces
- `<div class="sr-only" id="sr" aria-live="polite">` pour les changements de mode
- Annonces : "Mode changé", "Séance lancée", "Vote clôturé", etc.

### 6.4 Responsive
- `< 1100px` : exec-grid passe en 1 colonne
- `< 980px` : sidebar masquée, meeting bar full-width
- `< 768px` : tabs-nav scroll horizontal

---

## Ordre d'implémentation

| Étape | Fichiers modifiés | Dépendance |
|---|---|---|
| **0.1** Supprimer operator.js | `git rm` | Aucune |
| **0.2** Retirer wizard de operator | `operator.htmx.html`, `session-wizard.js` | Aucune |
| **1** Meeting bar enrichie | `operator.htmx.html`, `operator.css`, `operator-tabs.js` | 0 |
| **2** Mode switch + enveloppes | `operator.htmx.html`, `operator-tabs.js` | 1 |
| **3** Vue Préparation (dashboard + checklist + alertes) | `operator.htmx.html`, `operator.css`, `operator-tabs.js` | 2 |
| **4** Vue Exécution (grille 3 colonnes) | `operator.htmx.html`, `operator.css`, `operator-tabs.js` | 2 |
| **5** Masquer onglets en mode exec | `operator.css`, `operator-tabs.js` | 2+4 |
| **6** Polish (transitions auto, a11y, responsive) | `operator-tabs.js`, `operator.css` | 3+4+5 |

---

## Ce qui ne change PAS

- Les 7 onglets (Paramètres, Résolutions, Présences, Procurations, Parole, Vote, Résultats)
- La logique métier existante (API calls, caches, state machine)
- Le wizard sur les AUTRES pages (meetings, members)
- La sidebar globale (`partials/sidebar.html`)
- Les composants web (`ag-searchable-select`, `ag-popover`, `ag-toast`)
- Le drawer system (conservé pour les modales secondaires)
- Les imports CSV, les exports, le système de rôles
