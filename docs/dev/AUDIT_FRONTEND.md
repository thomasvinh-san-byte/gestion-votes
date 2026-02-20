# Audit sécurité & qualité — Frontend JS

Date : 2026-02-20
Périmètre : `public/assets/js/**/*.js` + fichiers `.htmx.html`

---

## 1. Tableau de bord KPIs

| KPI | Description | Avant | Après | Cible | Statut |
|-----|-------------|-------|-------|-------|--------|
| K1 | innerHTML sans escapeHtml (XSS) | 6 UNSAFE | **0** | 0 | ATTEINT |
| K2 | Secrets dans localStorage | 4 violations | **0** | 0 | ATTEINT |
| K3 | Error handling API silencieux | 14 PARTIAL (89%) | **0 silencieux (100%)** | 100% | ATTEINT |
| K4 | POST sans CSRF token | 1 manquant | **0** | 0 | ATTEINT |
| K5 | Formulaires sans validation email | 3 faiblesses | **0** | 0 | ATTEINT |
| K6 | Fonctions depth > 3 niveaux | 22 fonctions | **22** | 0 | NON TRAITE |

### K3 — Stratégie appliquée aux 10 ex-PARTIAL

Aucun catch n'est plus silencieux. Chaque instance a reçu un traitement
adapté à son contexte UI :

| Fichier:Ligne | Endpoint | Traitement appliqué |
|---------------|----------|---------------------|
| vote.js:152 | device_heartbeat.php | Compteur d'échecs consécutifs → `notify('error')` après 3 ratés |
| vote.js:198-201 | policies + settings (×4) | `console.warn` groupé avec liste des endpoints en erreur |
| vote.js:422 | attendances.php | `console.warn` avec message + fallback members.php documenté |
| shell.js:134,160,182 | context drawer (×3) | Accumulation → indicateur "sections indisponibles" en pied de drawer |
| operator-tabs.js:2221 | invitations_stats.php | Affichage "—" dans tous les compteurs au lieu de les laisser vides |

---

## 2. Corrections appliquées

### K1 — XSS (6 fixes, 4 fichiers)

| # | Fichier | Ligne | Correction |
|---|---------|-------|------------|
| 1 | `core/shared.js` | 141-149 | `openModal()` : title, cancelText, confirmText passés par `Utils.escapeHtml()` |
| 2 | `public.htmx.html` | 189-190 | Meeting picker : titre et heure passés par `escapeHtml()` local |
| 3 | `public.htmx.html` | 167 | Ajout d'une fonction `escapeHtml()` locale (utils.js non chargé sur cette page) |
| 4 | `pages/auth-ui.js` | 288-289 | Access-denied : roleLabel et requiredLabels passés par `esc()` local |
| 5 | `members.htmx.html` | 646, 746 | Initiales dans les avatars passées par `escapeHtml()` |
| 6 | `docs.htmx.html` | 317 | Strip `<script>`, `<iframe>`, `<object>`, `<embed>` du HTML rendu par marked |

**Architecture** : La vraie source du problème K1 est que `openModal()` accepte
`opts.title` comme HTML brut. Après fix, `title` est échappé systématiquement.
`opts.body` reste du HTML intentionnel (formulaires). Ce contrat est maintenant
explicite et sécurisé.

### K2 — Secrets client (2 fixes, 2 fichiers)

| # | Fichier | Correction |
|---|---------|------------|
| 1 | `public.htmx.html` | `api_key` : `localStorage` → `sessionStorage` (survit au reload, pas à la fermeture de session) |
| 2 | `vote.htmx.html` | Vote receipts : `localStorage` → `sessionStorage` (les choix de vote ne persistent plus entre sessions) |

### K3 — Error handling (6 fixes, 4 fichiers)

| # | Fichier | Correction |
|---|---------|------------|
| 1 | `pages/pv-print.js` | 3 `catch(_){}` silencieux → accumulation d'erreurs et message visible "Chargement partiel" |
| 2 | `pages/meetings.js` | Upload attachments : `console.warn` → `setNotif('warning', ...)` |
| 3 | `pages/vote.js:152` | Heartbeat : compteur d'échecs consécutifs → `notify('error')` après 3 ratés, reset à 0 en cas de succès |
| 4 | `pages/vote.js:198-201` | Policies ×4 : `console.warn` groupé listant les endpoints en erreur |
| 5 | `pages/vote.js:422` | Attendances : `console.warn` informatif avant fallback sur members.php |
| 6 | `core/shell.js:134,160,182` | Context drawer ×3 : accumulation des échecs → indicateur discret "sections indisponibles" en pied de drawer |
| 7 | `pages/operator-tabs.js:2221` | Stats invitations : affichage "—" explicite dans les compteurs au lieu de les laisser vides |

### K4 — CSRF (1 fix)

| # | Fichier | Correction |
|---|---------|------------|
| 1 | `pages/meetings.js` | `uploadAttachments()` : ajout header `X-CSRF-Token` sur le `fetch()` FormData |

**Note** : `login.js:auth_login.php` POST sans CSRF est accepté — endpoint
pré-authentification, pas de session à protéger.

### K5 — Validation (3 fixes, 2 fichiers)

| # | Fichier | Correction |
|---|---------|------------|
| 1 | `pages/admin.js:168` | Création utilisateur : ajout `Utils.isValidEmail()` |
| 2 | `pages/admin.js:307` | Edition utilisateur : ajout `Utils.isValidEmail()` |
| 3 | `pages/report.js:89` | Envoi PV par email : ajout `Utils.isValidEmail()` |

---

## 3. K6 — Plan de remédiation complexité

### Diagnostic

22 fonctions dépassent 3 niveaux d'imbrication. Distribution par cause :

| Pattern | Occurrences | Depth max | Fichiers principaux |
|---------|-------------|-----------|---------------------|
| MODAL_PATTERN | 8 | 5 | admin.js, operator-motions.js |
| DELEGATED_CLICK | 7 | 5 | admin.js, operator-tabs.js, operator-attendance.js |
| LOOP_LOGIC | 3 | 4 | admin.js, meetings.js |
| PROMISE_CHAIN | 3 | 4 | admin.js, auth-ui.js |
| INIT_FUNCTION | 1 | 4 | shell.js |

### Inventaire complet

#### admin.js — 8 fonctions (pire fichier, 1363 lignes)

| # | Ligne | Fonction | Depth | Chaîne d'imbrication |
|---|-------|----------|-------|----------------------|
| 1 | 17 | Tab click handler | 4 | forEach > addEventListener > forEach > forEach |
| 2 | 189 | Delegated users table | 5 | addEventListener > if > openModal > onConfirm > try/if |
| 3 | 231 | Set password onConfirm | 4 | addEventListener > if > openModal > onConfirm > api.then |
| 4 | 258 | Delete user onConfirm | 4 | addEventListener > if > openModal > onConfirm > try/if |
| 5 | 302 | Edit user onConfirm | 4 | addEventListener > if > openModal > onConfirm > api.then |
| 6 | 537 | Bulk assign onConfirm | 5 | openModal > onConfirm > for > try > if/else |
| 7 | 562 | Bulk assign setTimeout | 5 | onConfirm > setTimeout > addEventListener > forEach |
| 8 | 978 | Permission matrix search | 5 | addEventListener > querySelectorAll.forEach > if |

#### operator-motions.js — 5 fonctions (1246 lignes)

| # | Ligne | Fonction | Depth | Chaîne d'imbrication |
|---|-------|----------|-------|----------------------|
| 1 | 126 | Collapsible bind | 4 | forEach > addEventListener > if > target.closest |
| 2 | 134 | Vote actions | 4 | forEach > addEventListener > stopPropagation > openVote |
| 3 | 157 | Delete motion confirm | 5 | forEach > addEventListener > new Promise > querySelector > resolve |
| 4 | 466 | castManualVote correct | 5 | forEach > addEventListener > new Promise > querySelector > resolve |
| 5 | 616 | applyUnanimity confirm | 5 | new Promise > querySelector > addEventListener > resolve |

#### operator-tabs.js — 3 fonctions (2669 lignes)

| # | Ligne | Fonction | Depth | Chaîne d'imbrication |
|---|-------|----------|-------|----------------------|
| 1 | ~97 | createModal | 4 | function > if/else > className > addEventListener |
| 2 | ~74 | openDrawer | 4 | if > switch > custom.render callback |
| 3 | ~100 | Tab switching | 4 | addEventListener > forEach > classList > show/hide |

#### Autres fichiers — 6 fonctions (depth 4)

| Fichier | Fonctions depth > 3 | Pattern dominant |
|---------|---------------------|------------------|
| operator-attendance.js | 2 | DELEGATED_CLICK |
| vote.js | 2 | DELEGATED_CLICK + INIT |
| shell.js | 1 | INIT_FUNCTION |
| auth-ui.js | 2 | PROMISE_CHAIN + DELEGATED_CLICK |
| meetings.js | 3 | LOOP_LOGIC + DELEGATED_CLICK |

### Plan de remédiation — 3 étapes

#### Etape 1 : `Shared.confirmModal()` — Promise wrapper (impact : -8 fonctions)

La cause racine de 8/22 fonctions est le pattern callback de `openModal()` :

```
// AVANT (depth 5) — admin.js:189
btn.addEventListener('click', async function() {           // depth 1
  if (btn) {                                               // depth 2
    Shared.openModal({                                     // depth 3
      onConfirm: async function() {                        // depth 4
        try { await api(...); } catch(e) { ... }           // depth 5
      }
    });
  }
});
```

Ajouter à `shared.js` :

```javascript
/**
 * Promise-based modal confirmation.
 * @param {Object} opts - same as openModal, sans onConfirm
 * @returns {Promise<HTMLElement|false>} modal element on confirm, false on cancel
 */
Shared.confirmModal = function(opts) {
  return new Promise(function(resolve) {
    openModal(Object.assign({}, opts, {
      onConfirm: function(modal) { resolve(modal); },
      onCancel:  function()      { resolve(false); }
    }));
  });
};
```

Puis le code appelant devient :

```
// APRES (depth 3) — admin.js:189
btn.addEventListener('click', async function() {           // depth 1
  if (!btn) return;                                        // early return
  const modal = await Shared.confirmModal({                // depth 2
    title: '...', body: '...'
  });
  if (!modal) return;
  try { await api(...); } catch(e) { ... }                 // depth 3
});
```

**Fonctions impactées** : admin.js (5), operator-motions.js (3)
**Gain** : -1 à -2 niveaux par fonction. 8 fonctions passent de depth 4-5 à depth 3.
**Risque** : FAIBLE — le contrat de `openModal()` ne change pas, `confirmModal()` est un wrapper.

Il faudra aussi ajouter un callback `onCancel` à `openModal()` (actuellement absent),
appelé par les boutons close/cancel et le backdrop click.

#### Etape 2 : Extraire les handlers en fonctions nommées (impact : -7 fonctions)

Le pattern delegated-click dans admin.js:189 empile 5 sous-handlers dans un seul
`addEventListener`. Extraire chaque action :

```javascript
// AVANT — un seul handler monolithique
usersTableBody.addEventListener('click', async function(e) {
  let btn;
  btn = e.target.closest('.btn-toggle-user');
  if (btn) { /* 20 lignes depth 3-5 */ return; }
  btn = e.target.closest('.btn-password-user');
  if (btn) { /* 30 lignes depth 3-5 */ return; }
  btn = e.target.closest('.btn-delete-user');
  if (btn) { /* 20 lignes depth 3-5 */ return; }
  btn = e.target.closest('.btn-edit-user');
  if (btn) { /* 40 lignes depth 3-5 */ return; }
});

// APRES — fonctions nommées, handler de dispatch
async function handleToggleUser(btn) { /* depth 2 max */ }
async function handleSetPassword(btn) { /* depth 2 max */ }
async function handleDeleteUser(btn) { /* depth 2 max */ }
async function handleEditUser(btn) { /* depth 2 max */ }

usersTableBody.addEventListener('click', function(e) {
  const dispatch = {
    '.btn-toggle-user': handleToggleUser,
    '.btn-password-user': handleSetPassword,
    '.btn-delete-user': handleDeleteUser,
    '.btn-edit-user': handleEditUser
  };
  for (const [sel, fn] of Object.entries(dispatch)) {
    const btn = e.target.closest(sel);
    if (btn) { fn(btn); return; }
  }
});
```

**Fonctions impactées** : admin.js (3), operator-tabs.js (1), operator-motions.js (2), operator-attendance.js (1)
**Gain** : Le handler principal passe de depth 5 à depth 2. Chaque sous-handler est depth 2-3.
**Risque** : FAIBLE — refactoring mécanique, aucun changement de comportement.

#### Etape 3 : Remplacer .then/.catch par async/await (impact : -3 fonctions)

```javascript
// AVANT (depth 4)
api('/api/v1/endpoint', data)
  .then(function(r) {
    if (r.body && r.body.ok) { setNotif('success', '...'); }
    else { setNotif('error', getApiError(r.body)); }
  })
  .catch(function(err) { setNotif('error', err.message); });

// APRES (depth 2)
try {
  const r = await api('/api/v1/endpoint', data);
  if (r.body?.ok) setNotif('success', '...');
  else setNotif('error', getApiError(r.body));
} catch (err) { setNotif('error', err.message); }
```

**Fonctions impactées** : admin.js (2 : set_password, edit_user), auth-ui.js (1)
**Gain** : -1 niveau d'imbrication.
**Risque** : FAIBLE — passage mécanique.
**Prérequis** : Les fonctions parentes doivent être `async` (déjà le cas pour admin.js).

### Résumé du plan

| Etape | Effort | Fonctions corrigées | Risque | Prérequis |
|-------|--------|---------------------|--------|-----------|
| 1. `confirmModal()` | ~2h | 8 | Faible | Ajouter `onCancel` à `openModal()` |
| 2. Handlers nommés | ~3h | 7 | Faible | Aucun |
| 3. async/await | ~1h | 3 | Faible | Vérifier `async` sur les parents |
| **Total** | **~6h** | **18/22** | | |

Les 4 fonctions restantes (depth 4 dans meetings.js et vote.js) sont des
boucles de rendu avec ternaires — la complexité est dans la logique métier,
pas dans la structure. Les simplifier nécessiterait un DSL de templates, ce
qui serait du sur-engineering.

### Ordre d'exécution recommandé

1. **Etape 1 en premier** — elle crée l'infrastructure (`confirmModal`) qui rend
   l'étape 2 plus simple.
2. **Etape 2 ensuite** — chaque handler extrait est immédiatement testable isolément.
3. **Etape 3 en dernier** — transformation mécanique, peut être faite fichier par fichier.

### Tests nécessaires

Chaque étape doit être validée par :
- Test manuel des modales de confirmation (créer, supprimer, modifier utilisateur)
- Test des raccourcis clavier (Escape, Tab trap)
- Test du focus restore après fermeture modale
- Test des cas d'erreur API (serveur down, 403, 422)

---

## 4. Inventaire global des appels API — 127 endpoints

### Par fichier

| Fichier | Appels | COVERED | PARTIAL | CSRF ok |
|---------|--------|---------|---------|---------|
| admin.js | 25 | 25 | 0 | 25/25 |
| operator-tabs.js | 28 | 28 | 0 | 28/28 |
| operator-motions.js | 13 | 13 | 0 | 13/13 |
| operator-attendance.js | 9 | 9 | 0 | 9/9 |
| operator-speech.js | 7 | 7 | 0 | 7/7 |
| vote.js | 11 | 11 | 0 | 11/11 |
| shell.js | 6 | 6 | 0 | 6/6 |
| auth-ui.js | 4 | 4 | 0 | 4/4 |
| login.js | 3 | 3 | 0 | 2/3 (login pre-auth) |
| meetings.js | 3 | 3 | 0 | 3/3 |
| pv-print.js | 3 | 3 | 0 | N/A (GET) |
| validate.js | 4 | 4 | 0 | 4/4 |
| report.js | 2 | 2 | 0 | 2/2 |
| trust.js | 7 | 7 | 0 | 7/7 |
| archives.js | 1 | 1 | 0 | 1/1 |
| shared.js | 1 | 1 | 0 | N/A (GET) |
| **TOTAL** | **127** | **127** | **0** | **125/127** |

### Infrastructure CSRF

- `api()` dans `utils.js` inclut automatiquement `X-CSRF-Token` sur tout POST
- `apiGet()` / `apiPost()` délèguent à `api()`
- 4 raw `fetch()` POST ajoutent le CSRF manuellement (OK)
- 1 raw `fetch()` POST sans CSRF : `login.js:auth_login.php` (pre-auth, accepté)

---

## 5. Corrections supplémentaires (passe 2)

### A. localStorage → sessionStorage (privacy sur appareils partagés)

| # | Fichier | Correction |
|---|---------|------------|
| 1 | `pages/vote.js` | 14 occurrences : `device.id`, `public.meeting_id`, `public.member_id` migrés vers sessionStorage |
| 2 | `services/meeting-context.js` | 4 occurrences : `meeting_id` context migré vers sessionStorage |

**Seul localStorage restant** : `shell.js` — thème clair/sombre (non-sensible, conservé).

### E. Fuites mémoire (timers + event listeners)

| # | Fichier | Correction |
|---|---------|------------|
| 1 | `pages/vote.js:947-951` | Timers poll/heartbeat stockés sur `window` avec `clearInterval` avant recréation |
| 2 | `pages/operator-tabs.js:1606` | Timer clock non gardé → ajout `_clockInterval` avec clear + cleanup dans `beforeunload` |
| 3 | `components/ag-popover.js:112` | `removeEventListeners()` ne nettoyait que document → nettoyage complet de tous les listeners (trigger + popover + document) |

### G. Accessibilité

| # | Fichier | Correction |
|---|---------|------------|
| 1 | `pages/login.js` | `showError()` donne le focus à `errorBox` pour les lecteurs d'écran |
| 2 | `login.html` | Ajout `tabindex="-1"` sur `errorBox` pour le rendre focusable programmatiquement |

---

## 6. Conventions à respecter (post-audit)

### Injection HTML

- `openModal({ title })` : le titre est désormais auto-échappé par `Utils.escapeHtml()`
- `openModal({ body })` : reste du HTML intentionnel (formulaires) — les données
  dynamiques DOIVENT être échappées par l'appelant
- **Règle** : tout texte provenant de l'API (`title`, `name`, `email`, `error`)
  doit passer par `escapeHtml()` avant insertion dans un template HTML

### Stockage client

- **sessionStorage** uniquement pour les données temporaires (api_key, receipts, meeting/member IDs)
- **localStorage** réservé aux préférences non-sensibles (thème)
- **Règle** : aucune donnée identifiant un votant ou un choix de vote ne doit survivre à la fermeture du navigateur

### Timers

- Tout `setInterval` doit être assigné à une variable nettoyable
- Avant de créer un timer, toujours `clearInterval` le précédent
- Tout composant avec timers doit implémenter un cleanup dans `beforeunload` ou `disconnectedCallback`

### Nouveaux appels API

Tout nouveau `fetch()` / `api()` doit :
1. Être dans un `try/catch` avec `setNotif('error', ...)` sauf justification documentée
2. Inclure `X-CSRF-Token` si POST (utiliser `api()` de préférence)
3. Valider le format email avec `Utils.isValidEmail()` si le champ est un email

---

## 7. Audit du chemin critique — Cycle de vie des séances

Date : 2026-02-20
Périmètre : workflow complet `draft → scheduled → frozen → live → paused → closed → validated → archived`

### Machine d'états backend (`MeetingWorkflowService`)

```
draft ──→ scheduled ──→ frozen ──→ live ──→ closed ──→ validated ──→ archived
  ↑            ↑           ↑         ↓↑         ↑
  └────────────┘           └─────────┘│         │
   (retour brouillon)       (dégeler) │         │
                                      ↓         │
                                   paused ──────┘
```

Chaque transition est validée par `issuesBeforeTransition()` :

| Transition | Bloquant | Avertissement |
|------------|----------|---------------|
| draft → scheduled | Résolutions requises | — |
| scheduled → frozen | Présences requises | Président non assigné |
| frozen → live | — | Quorum non atteint |
| live → paused | Vote en cours interdit | — |
| live/paused → closed | Vote en cours interdit | — |
| closed → validated | Résultats invalides | Non consolidé |

### Bugs corrigés

#### BUG 1 — CRITIQUE : `launch()` contourne les validations intermédiaires

**Avant** : `MeetingWorkflowController::launch()` appelait
`issuesBeforeTransition($meetingId, $tenant, 'live')` qui ne vérifiait que
la transition `frozen → live`. Lancer depuis `draft` contournait les checks
"résolutions requises" (draft→scheduled) et "présences requises"
(scheduled→frozen).

**Root cause** : `issuesBeforeTransition()` utilise `$fromStatus` lu en base
pour cibler les vérifications. Lors d'un lancement atomique (draft→live),
`$fromStatus = 'draft'` et `$toStatus = 'live'` ne matchent aucun bloc
conditionnel.

**Fix** :
- `MeetingWorkflowService::issuesBeforeTransition()` accepte un paramètre
  optionnel `$fromStatusOverride` pour simuler l'état source.
- `launch()` itère sur chaque étape du chemin (`['scheduled', 'frozen', 'live']`)
  en passant l'état simulé : chaque transition intermédiaire est vérifiée.

**Fichiers** : `app/Services/MeetingWorkflowService.php`,
`app/Controller/MeetingWorkflowController.php`

#### BUG 2 — HAUTE : Bouton "Ouvrir la séance" actif sans présences

**Avant** : `getConformityScore()` ajoute inconditionnellement +1 pour
"Convocations" (optionnel, toujours vrai). Seuil = 3/4. Résultat : on peut
atteindre 3 avec `membres(1) + convocations(1) + règlement(1) = 3` sans
aucune présence.

**Fix** : `updatePrimaryButton()` ajoute un check explicite
`hasAttendance` en plus du score : `btnPrimary.disabled = score < 3 || !hasAttendance`.

**Fichier** : `public/assets/js/pages/operator-tabs.js`

#### BUG 3 — MOYENNE : Pas d'indicateur de chargement sur les transitions

**Avant** : `launchSessionConfirmed()`, `closeSession()`, `doTransition()`
fermaient le modal immédiatement et lançaient l'appel API sans feedback
visuel. L'utilisateur pouvait cliquer à nouveau ou ne pas savoir si
l'action était en cours.

**Fix** : Ajout de `Shared.btnLoading()` sur les boutons déclencheurs
(btnPrimary, btnLaunchSession, btnCloseSession, transition buttons) avec
restauration dans `finally`.

**Fichiers** : `public/assets/js/pages/operator-tabs.js`,
`public/assets/js/pages/operator-motions.js`

#### BUG 4 — BASSE : `getTransitionReadiness()` transitions incomplètes

**Avant** : La map `possibleTransitions` ne contenait pas `paused → live/closed`
ni `live → paused`.

**Fix** : Ajout des transitions manquantes.

**Fichier** : `app/Services/MeetingWorkflowService.php`

### Faux positifs identifiés (audit frontend initial)

Les 3 findings "CRITICAL" de l'audit frontend automatisé étaient des
faux positifs :

| Finding | Réalité |
|---------|---------|
| `submitVote()` non défini | `window.submitVote = cast` à vote.js:963 |
| Archives export sans handlers | Handlers à archives.js:401-406 |
| Year filter non câblé | Handler à archives.js:305 |
