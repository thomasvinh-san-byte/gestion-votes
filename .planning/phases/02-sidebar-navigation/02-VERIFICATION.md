---
phase: 02-sidebar-navigation
verified: 2026-04-21T10:00:00Z
status: passed
score: 7/7 must-haves verified
gaps: []
# Gap resolved: bindPinButton() call removed from shell.js MutationObserver (commit f69c30d9)
    artifacts:
      - path: "public/assets/js/core/shell.js"
        issue: "Line 115: bindPinButton() appele dans le MutationObserver callback mais la fonction n'existe plus — ReferenceError a l'execution"
    missing:
      - "Supprimer l'appel bindPinButton() ligne 115 dans le MutationObserver callback de shell.js"
human_verification:
  - test: "Verification visuelle sidebar 200px"
    expected: "Sidebar affichee a 200px avec tous les labels visibles, sans bouton pin, contenu principal decale de 220px"
    why_human: "La verification visuelle Task 2 (checkpoint:human-verify) a ete differee par l'utilisateur avec 'Continue without validation'"
  - test: "Navigation votant en situation reelle"
    expected: "Voter voit uniquement: logo, Tableau de bord, Voter, Guide & FAQ, Mon compte. Cliquer 'Mon compte' mene a /settings sans 404."
    why_human: "E2E Playwright ne peut pas s'executer dans cet environnement (bibliotheques systeme manquantes)"
  - test: "Verification ReferenceError en console"
    expected: "Aucune erreur console 'bindPinButton is not defined' quand la sidebar est chargee dynamiquement"
    why_human: "Necessite un vrai navigateur pour observer les erreurs console JavaScript"
---

# Phase 2: Sidebar Navigation — Verification Report

**Phase Goal:** La navigation laterale est toujours visible et utilisable sans effort — chaque utilisateur voit uniquement les liens pertinents pour son role
**Verified:** 2026-04-21T10:00:00Z
**Status:** gaps_found
**Re-verification:** No — initial verification

## Goal Achievement

### Observable Truths (Plan 01)

| #  | Truth                                                                              | Status     | Evidence                                                                                   |
|----|------------------------------------------------------------------------------------|------------|--------------------------------------------------------------------------------------------|
| 1  | La sidebar fait 200px de large en permanence avec labels visibles                  | VERIFIED   | `--sidebar-width: 200px` (line 489 CSS), `.app-sidebar { width: 200px }` (line 989 CSS), `.nav-label { opacity: 1; max-width: 180px; text-overflow: ellipsis }` (line 1085 CSS) |
| 2  | Le mecanisme pin/unpin est completement supprime (CSS, JS, HTML)                   | FAILED     | CSS: aucune regle `.app-sidebar:hover` ni `.app-sidebar.pinned` (sauf media query mobile OK), `--z-sidebar-pin` token seul reste (inoffensif). HTML: `sidebarPin` absent. JS: `PIN_KEY`, `togglePin`, `bindPinButton` function, `SidebarPin` export supprimes — MAIS `bindPinButton()` appele ligne 115 dans MutationObserver. ReferenceError a l'execution. |
| 3  | Le contenu principal est decale de 220px a gauche sans toggle JS                   | VERIFIED   | `.app-main { padding-left: calc(200px + 20px) }` (line 1198 CSS), aucun override JS |
| 4  | Un votant voit 'Voter' et 'Mon compte' dans la sidebar                             | VERIFIED   | `sidebar.html` line 109: `href="/vote" data-requires-role="admin,operator,president,voter"` label "Voter"; line 117: `href="/settings"` sans `data-requires-role`, label "Mon compte" |
| 5  | Tous les nav-items et nav-groups font minimum 44px de hauteur                      | VERIFIED   | `.nav-item { height: 44px }` (line 1046 CSS), `.nav-group { min-height: 44px }` (line 1097 CSS) |
| 6  | Les labels longs sont tronques avec ellipsis                                       | VERIFIED   | `.nav-label { text-overflow: ellipsis; white-space: nowrap; overflow: hidden; max-width: 180px }` (line 1085 CSS), `.nav-group-label` idem (line 1118 CSS) |
| 7  | 'Mon compte' pointe vers /settings (page existante), pas /account (inexistant)    | VERIFIED   | `sidebar.html` line 117: `href="/settings"` confirme, aucun `href="/account"` present |

**Score Plan 01:** 6/7 truths verified

### Observable Truths (Plan 02)

| #  | Truth                                                                                              | Status      | Evidence                                                                                   |
|----|----------------------------------------------------------------------------------------------------|-------------|--------------------------------------------------------------------------------------------|
| 1  | Le test E2E votant verifie /vote et Mon compte (/settings) visibles pour un votant                | VERIFIED    | `critical-path-votant.spec.js`: `mustBeVisible` contient `/vote`, `/dashboard`; assertion separee `monCompte` via `hasText: 'Mon compte'` |
| 2  | La sidebar a 200px de large visuellement sur une page reelle                                       | HUMAN_NEEDED | Test E2E non executable (env Playwright cassé), verification visuelle differee |
| 3  | Le votant ne voit que les liens attendus dans la sidebar                                            | VERIFIED    | Test: `mustBeHidden` couvre `/users`, `/admin`, `/settings[data-requires-role="admin"]`, `/members`, `/operator`, `/hub`, `/wizard`; NAV-01 assertion `boundingBox().width === 200` presente |

**Score Plan 02:** 2/3 truths (1 human-needed)

---

## Required Artifacts

| Artifact                                              | Expected                                                    | Status     | Details                                                                          |
|-------------------------------------------------------|-------------------------------------------------------------|------------|----------------------------------------------------------------------------------|
| `public/assets/css/design-system.css`                 | Sidebar 200px static, labels opacity 1, touch targets 44px, no hover/pin rules | VERIFIED | Confirme: sidebar 200px, nav-label opacity:1, nav-item height:44px, nav-group min-height:44px, aucun `.app-sidebar:hover`/`.app-sidebar.pinned` hors media query mobile |
| `public/partials/sidebar.html`                        | No pin button, Mon compte pointant /settings, Voter dans nav principale | VERIFIED | Pin button absent, Voter line 109, Mon compte line 117 pointant /settings sans data-requires-role |
| `public/assets/js/core/shell.js`                      | No pin/unpin logic, no localStorage sidebar key, no SidebarPin export | STUB (partial) | Fonctions supprimees mais `bindPinButton()` appele ligne 115 (orphan call) — ReferenceError en production |
| `tests/e2e/specs/critical-path-votant.spec.js`        | Updated voter sidebar assertions including /vote and Mon compte visibility | VERIFIED | Mon compte assertion, /vote dans mustBeVisible, boundingBox width check — tous presentes |

---

## Key Link Verification

| From                                          | To                                  | Via                                                    | Status   | Details                                                               |
|-----------------------------------------------|-------------------------------------|--------------------------------------------------------|----------|-----------------------------------------------------------------------|
| `public/assets/css/design-system.css`         | `public/partials/sidebar.html`      | `.nav-item { height: 44px }` s'applique a tous les items dont Mon compte | WIRED  | height:44px confirme line 1046 CSS, s'applique a tous `.nav-item` incluant Mon compte |
| `public/partials/sidebar.html`                | `public/settings.htmx.html`        | Mon compte href pointe vers /settings                  | WIRED    | Line 117 sidebar.html: `href="/settings"`, label "Mon compte", aucun `href="/account"` |
| `public/assets/js/core/shell.js`              | `public/assets/css/design-system.css` | Pas de toggle JS du padding-left, CSS gere statiquement | WIRED | `padding-left: calc(200px + 20px)` CSS static confirme, aucun override JS detecte |
| `tests/e2e/specs/critical-path-votant.spec.js` | `public/partials/sidebar.html`     | Test assert nav items ajoutes dans Plan 01 sont visibles pour votant | WIRED | `monCompte` assertion via `hasText: 'Mon compte'`, `/vote` dans mustBeVisible |

---

## Requirements Coverage

| Requirement | Source Plan | Description                                                                | Status       | Evidence                                                                              |
|-------------|-------------|----------------------------------------------------------------------------|--------------|---------------------------------------------------------------------------------------|
| NAV-01      | 02-01, 02-02 | Sidebar toujours ouverte ~200px avec labels visibles, plus de hover-to-expand | SATISFIED  | `--sidebar-width: 200px`, `.nav-label { opacity: 1 }`, aucun hover/expand CSS |
| NAV-02      | 02-01, 02-02 | Items de navigation filtres par role — votant voit uniquement ce qu'il faut | SATISFIED   | Mon compte sans `data-requires-role`, Voter avec role voter inclus, Parametres admin-only; test E2E verifie le filtrage |
| NAV-03      | 02-01, 02-02 | Boutons et liens navigation minimum 44x44px (WCAG 2.5.8)                  | SATISFIED    | `.nav-item { height: 44px }`, `.nav-group { min-height: 44px }` |

**Orphaned requirements:** Aucun — NAV-01, NAV-02, NAV-03 tous couverts par les plans de cette phase. NAV-04 est en phase 5 (hors perimetre phase 2).

---

## Anti-Patterns Found

| File                                   | Line | Pattern                        | Severity | Impact                                                                                    |
|----------------------------------------|------|--------------------------------|----------|-------------------------------------------------------------------------------------------|
| `public/assets/js/core/shell.js`       | 115  | `bindPinButton()` appele mais fonction inexistante | BLOCKER | ReferenceError JavaScript a l'execution quand le sidebar partial est charge dynamiquement via MutationObserver. La sidebar peut ne pas se comporter correctement apres chargement HTMX. |

---

## Human Verification Required

### 1. Verification visuelle sidebar 200px

**Test:** Ouvrir l'application dans un navigateur sur la page dashboard (login admin)
**Expected:** Sidebar affichee a exactement 200px de large avec tous les labels visibles, sans bouton pin visible, contenu principal decale correctement sans chevauchement
**Why human:** Task 2 (checkpoint:human-verify) differee par l'utilisateur avec "Continue without validation"

### 2. Navigation votant en situation reelle

**Test:** Se connecter en tant que votant, verifier les liens visibles dans la sidebar, cliquer "Mon compte"
**Expected:** Voter voit uniquement: logo/brand, Tableau de bord, Voter, Guide & FAQ, Mon compte. Cliquer "Mon compte" mene a /settings (pas de 404).
**Why human:** Playwright ne peut pas s'executer dans cet environnement (bibliotheques systeme manquantes: libatk-1.0, libasound.so.2, etc.)

### 3. Absence d'erreur console JavaScript

**Test:** Ouvrir la console navigateur et naviguer vers n'importe quelle page utilisant la sidebar
**Expected:** Aucune erreur `ReferenceError: bindPinButton is not defined` dans la console
**Why human:** Seul un vrai navigateur peut confirmer que le MutationObserver ne declenche pas d'erreur (et si elle est silencieuse ou non)

---

## Gaps Summary

**Un seul gap bloquant:** `bindPinButton()` est appele a la ligne 115 de `public/assets/js/core/shell.js` dans le callback d'un MutationObserver qui s'execute quand le partial sidebar est charge dynamiquement. La fonction a ete supprimee (correctement) mais l'appel a ete oublie. Ce gap est mineur en termes de code a modifier (supprimer une ligne) mais potentiellement impactant en production: toute navigation HTMX qui recharge le partial sidebar declenchera un `ReferenceError`, pouvant interrompre l'initialisation du scroll fade, nav group toggle, active page marking et sidebar top update dans ce meme callback.

**Correction requise:** Supprimer l'unique appel `bindPinButton();` a la ligne 115 de `public/assets/js/core/shell.js`.

---

_Verified: 2026-04-21T10:00:00Z_
_Verifier: Claude (gsd-verifier)_
