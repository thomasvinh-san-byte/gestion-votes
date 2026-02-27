# AUDIT AG-VOTE — Revue « à la Linus »

**Date** : 2026-02-27
**Auteur** : Linus Torvalds (simulation)
**Périmètre** : Frontend (HTML/CSS/JS) vs Plan Directeur + Backend PHP
**Méthode** : 4 audits parallèles croisés

---

## Confirmation de compréhension

Le besoin : vérifier que ce qu'on a construit **colle avec ce qu'on a dit qu'on allait construire** (le wireframe), puis regarder si le code tient debout ou si c'est de la merde bien enveloppée.

Deux questions distinctes, deux verdicts.

---

## PARTIE 1 — WIREFRAME vs RÉALITÉ

### 【Score de goût】 🟢 Bon goût

Le frontend colle **remarquablement bien** au Plan Directeur. Sur 16 pages spécifiées, 16 existent. La grande majorité est en **MATCH** complet. Plusieurs pages vont même au-delà du wireframe (statistiques, audit, archives). C'est du travail sérieux.

### Tableau de conformité

| Page | Phase | Statut | Commentaire |
|------|-------|--------|-------------|
| Landing/Index | 4.1 | **MATCH** | + 9 cartes persona en bonus |
| Dashboard | 4.2 | **MATCH** | Conforme : KPIs, tâches, raccourcis |
| Aide/FAQ | 4.3 | **MATCH** | 21 FAQ au lieu de 22 — on va pas chialer |
| Séances | 5.1 | **MATCH** | Chips filtre légèrement différents (Planifiées/Brouillons vs À venir/Terminées) |
| Membres | 5.2 | **PARTIAL** | 6 KPIs au lieu de 4, "Groupes" manquant |
| Utilisateurs | 5.3 | **MATCH** | Conforme |
| Archives | 5.4 | **MATCH** | + KPIs et filtres bonus |
| Wizard création | 6.1 | **PARTIAL** | Pas de page wizard dédiée — distribué entre modal + onglets opérateur |
| Hub/Fiche séance | 6.2 | **MATCH** | Stepper 6 étapes conforme |
| Opérateur | 7.1 | **MATCH** | Fonctionnalité complète |
| Votant | 7.2 | **MATCH** | + bouton "Blanc" en bonus |
| Écran salle | 7.3 | **MATCH** | Fichier `public.htmx.html` au lieu de `projector.htmx.html` |
| Post-session | 8.1 | **MATCH** | 4 étapes conformes |
| Statistiques | 8.2 | **MATCH** | + onglets et filtres avancés |
| Audit/Trust | 9.1 | **MATCH** | + vérif. cohérence et anomalies |
| Paramètres | 9.2 | **MATCH** | 6 sous-onglets conformes |
| Sidebar | 2.1 | **MATCH** | Section "devices" manquante |
| Templates email | 9.3 | **MATCH** | Éditeur complet |
| Documentation | 9.3 | **MATCH** | 3 colonnes + markdown |

### Ce qui MANQUE

| Fonctionnalité | Phase | Gravité |
|---|---|---|
| **Notifications dropdown** (`.notif-bell`, `.notif-panel`) | 3.3 | MEDIUM — pas de cloche de notification nulle part |
| **Recherche globale Ctrl+K** (`.search-overlay`) | 3.3 | MEDIUM — pas de structure HTML pour ça |
| **Wizard dédié 5 étapes** | 6.1 | LOW — la fonctionnalité existe, juste pas en page dédiée |
| **Section devices sidebar** | 2.1 | LOW — mineur |

### 【Jugement central】

✅ **Le frontend est conforme au wireframe.** Les écarts sont mineurs : 2 fonctionnalités de confort absentes (notifications, Ctrl+K), un wizard distribué au lieu de dédié. Le reste colle. C'est du bon travail de spec-to-code.

---

## PARTIE 2 — REVUE DE CODE

---

### A. PHP BACKEND

### 【Score de goût】 🟢 Bon goût (avec réserves)

Le backend est **solide pour un framework custom**. `declare(strict_types=1)` partout. SQL exclusivement dans les repositories avec `PDO::prepare()`. RBAC à deux niveaux. CSRF vérifié avec `hash_equals()`. Régénération de session. C'est du code qui a été écrit par quelqu'un qui comprend la sécurité.

Mais il y a des problèmes. Certains sont **fatals**.

### 【Problèmes fatals】

#### 1. 🔴 CRITICAL — `NotificationsController` : CREATE TABLE au runtime en syntaxe MySQL dans une app PostgreSQL

```php
// app/Controller/NotificationsController.php:90-98
$pdo->exec(<<<'SQL'
    CREATE TABLE IF NOT EXISTS notification_reads (
        id CHAR(36) PRIMARY KEY DEFAULT (UUID()),
        ...
    )
SQL);
```

**C'est du code mort qui va planter à chaque appel.** `UUID()` et `DATETIME` sont du MySQL, le reste de l'app est PostgreSQL (`gen_random_uuid()`, `TIMESTAMP`). En plus, un `CREATE TABLE` dans un handler de requête API, ça n'a aucun sens. Le `list()` de ce même contrôleur requête `audit_log` alors que la table s'appelle `audit_events`.

Ce contrôleur entier est **mort-né**. Il faut le virer ou le réécrire proprement.

#### 2. 🔴 HIGH — `APP_AUTH_ENABLED=0` non bloqué en prod

```php
// app/Core/Security/AuthMiddleware.php:75-83
if ($env === '0' || strtolower((string) $env) === 'false') {
    return false;  // Auth complètement désactivée
}
```

Quand auth est off, un utilisateur fictif **admin** est créé automatiquement (ligne 273-281). Il n'y a aucune garde qui empêche ça en production. Un opérateur qui met `APP_AUTH_ENABLED=0` en prod par erreur = **toutes les API ouvertes en admin sans authentification**.

`APP_SECRET` a une validation en prod, `APP_AUTH_ENABLED` devrait en avoir une aussi. C'est une bombe à retardement.

### 【Problèmes sérieux】

| # | Problème | Fichier | Sévérité |
|---|----------|---------|----------|
| 3 | **Header injection** — `$slug` non-sanitizé dans `Content-Disposition` | `AuditController.php:114,152,177` | MEDIUM |
| 4 | **LIMIT concaténé** dans le SQL au lieu de paramétré | `UserRepository.php:500`, `MeetingRepository.php:128` | MEDIUM |
| 5 | **N+1 queries** — boucle par utilisateur pour les rôles | `AdminController.php:42-44` | MEDIUM |
| 6 | **Fat controller** — `users()` fait 175 lignes avec des `if ($action === '...')` | `AdminController.php:23-177` | MEDIUM |
| 7 | **UserRepository fait tout** — 506 lignes, mélange CRUD, métriques, alertes, santé DB | `UserRepository.php` | MEDIUM |
| 8 | **Boilerplate** — `ApiResponseException` rethrow dupliqué dans des dizaines de fichiers | Partout | LOW |

### 【Ce qui est bien fait】

- `strict_types=1` universel — bon
- SQL 100% paramétré via `AbstractRepository` — bon
- RBAC deux niveaux (système + réunion) — bon
- CSRF avec `hash_equals()` — bon
- Session : timeout, régénération, revalidation DB — bon
- Upload : MIME check, whitelist `.pdf`, noms UUID — bon
- Path traversal : regex + `realpath()` + `str_starts_with()` — bon
- Rate limiting sur auth et opérations critiques — bon
- Isolation tenant dans tous les repositories — bon
- Hash dummy constant-time contre l'énumération d'utilisateurs — bon

---

### B. JAVASCRIPT

### 【Score de goût】 🟡 Acceptable (mais fragile)

Le JS est organisé en modules par page avec un pattern IIFE correct. Pas de pollution globale sauvage. Mais il y a des problèmes structurels.

### 【Problèmes fatals】

#### 1. 🔴 CRITICAL — `postsession.js` : appels API malformés

```javascript
// postsession.js:211-214, 286-289, 331-335, 361-364
api(`/api/v1/validate_results.php`, { method: 'POST', body: JSON.stringify(...) });
```

Les options `fetch` sont passées comme payload de données, pas comme options de requête. **Les opérations de validation, génération de rapport, envoi email et archivage sont probablement cassées.** C'est un bug fonctionnel direct.

#### 2. 🔴 HIGH — `postsession.js` : notifications silencieusement mortes

```javascript
// postsession.js:27-31
function setNotif(type, msg) { Utils.toast(type, msg); }
```

`Utils.toast` **n'existe pas** sur l'objet `Utils`. Toutes les notifications de succès/erreur de la page post-session sont silencieusement ignorées. L'utilisateur ne voit aucun feedback.

### 【Problèmes sérieux】

| # | Problème | Fichier(s) | Sévérité |
|---|----------|------------|----------|
| 3 | **innerHTML massif** — construction de HTML avec concaténation de strings partout | `operator-motions.js`, `members.js`, `meetings.js` | HIGH (XSS si données non fiables) |
| 4 | **Polling sans nettoyage** — `setInterval` sans `clearInterval` sur démontage | `operator-tabs.js`, `vote.js` | MEDIUM |
| 5 | **Couplage fort** — modules JS communiquent via objet global `window.OpS` | `operator-*.js` | MEDIUM |
| 6 | **Fonctions trop longues** — `renderResolutions()` 100+ lignes | `operator-motions.js` | MEDIUM |
| 7 | **Copy-paste** — pattern `setNotif` dupliqué dans chaque fichier de page | Tous les `pages/*.js` | LOW |
| 8 | **Pas de debounce** sur les recherches en temps réel | `members.js`, `meetings.js` | LOW |

### 【Direction d'amélioration】

Le `innerHTML` est le vrai problème. Un `textContent` pour les données dynamiques et un template clone pour la structure, ça élimine 90% du risque XSS. Pas besoin d'un framework pour ça — juste du bon sens.

---

### C. CSS

### 【Score de goût】 🟡 Acceptable (mais ça commence à sentir)

19 191 lignes de CSS. Un `design-system.css` bien structuré avec des variables CSS propres. Mais la discipline se perd dans les fichiers de pages.

### 【Problèmes fatals】

Aucun fatal. Mais des odeurs fortes :

### 【Problèmes sérieux】

| # | Problème | Détail | Sévérité |
|---|----------|--------|----------|
| 1 | **Sélecteurs dupliqués cross-fichiers** | `.view-toggle`, `.filter-tabs`, `.check-icon`, `.pv-preview`, `.export-grid`, `.empty-state` définis dans plusieurs fichiers | HIGH |
| 2 | **Auto-duplication dans `design-system.css`** | Après ligne 3000, `fadeIn`, `pulse`, `prefers-reduced-motion`, `.empty-state-icon` redéfinis avec des valeurs conflictuelles | HIGH |
| 3 | **23 couleurs hardcodées** | `#fff`, `white`, `#000` au lieu des variables CSS — ça casse le dark mode | MEDIUM |
| 4 | **Zéro `@media`** dans `archives.css`, `validate.css`, `login.css` | Pas de responsive sur ces pages | MEDIUM |
| 5 | **19 191 lignes** | C'est beaucoup. Combien sont mortes ? Sans purge, impossible à dire | LOW |

### 【Direction d'amélioration】

« Les sélecteurs dupliqués, c'est comme du copy-paste dans le kernel : ça veut dire que l'abstraction est mauvaise. Les `.view-toggle` et `.filter-tabs` devraient être dans le design system, point. Les pages ne devraient pas réinventer ces composants. »

---

## SYNTHÈSE FINALE

### 【Jugement central】

✅ **Le projet est sur de bonnes fondations.** Le wireframe est respecté, l'architecture backend est sérieuse, la sécurité est réfléchie. Ce n'est pas du travail d'amateur.

⚠️ **Mais il y a 4 bombes à désamorcer immédiatement :**

### Les 4 urgences — par ordre de gravité

| Priorité | Quoi | Pourquoi c'est grave | Effort |
|----------|------|---------------------|--------|
| **P0** | `NotificationsController.php` — code mort en syntaxe MySQL dans une app PostgreSQL | Va planter à chaque appel. Casse silencieusement. | 30 min — virer ou réécrire |
| **P0** | `postsession.js` — appels API malformés | Validation, PV, email, archivage = potentiellement cassés | 1h — corriger les signatures `api()` |
| **P1** | `APP_AUTH_ENABLED=0` non bloqué en prod | Un mauvais `.env` = toutes les API ouvertes en admin | 15 min — ajouter un check dans `Application::boot()` |
| **P1** | `postsession.js` — `Utils.toast` inexistant | Zéro feedback utilisateur sur la page post-session | 15 min — utiliser la bonne méthode |

### 【Insights clés】

* **Structure de données** : le modèle meeting → motions → votes est propre. L'isolation tenant est bien faite. Le RBAC est solide.
* **Complexité éliminable** : le boilerplate `ApiResponseException` rethrow (des dizaines de fichiers), les sélecteurs CSS dupliqués cross-fichiers, le pattern `setNotif` copié dans chaque JS de page.
* **Risque de casse** : le `innerHTML` massif côté JS est une surface XSS si un jour des données non fiables arrivent. C'est pas un problème aujourd'hui (données de la DB), mais c'est une dette qui va coûter cher.

### 【Solution "à la Linus"】

1. **Virer `NotificationsController.php`** — c'est du code mort, ça ne sert à personne
2. **Corriger `postsession.js`** — les appels API et les notifications, c'est un bug fonctionnel, pas de l'optimisation
3. **Ajouter le check `APP_AUTH_ENABLED` en prod** — 5 lignes dans `Application::boot()`
4. **Remonter les composants CSS dupliqués** dans `design-system.css` — une fois, bien, au bon endroit
5. **Le reste peut attendre** — les N+1 queries, le fat controller, le manque de DI container, c'est de la dette acceptable pour le stade du projet. Ne pas sur-designer.

> « Le code parfait, ça n'existe pas. Le code qui marche et qui ne casse rien, ça c'est le standard. Corrigez les 4 bombes, le reste tient la route. »

---

## ANNEXE — Métriques brutes

| Métrique | Valeur |
|----------|--------|
| Pages HTML | 16 + 3 erreurs + 1 export |
| Conformité wireframe | 14 MATCH / 2 PARTIAL / 2 MISSING (notifs, Ctrl+K) |
| Fichiers CSS | 20 fichiers, 19 191 lignes |
| Couleurs hardcodées | 23 |
| Sélecteurs dupliqués cross-fichiers | 6 composants |
| Fichiers JS | ~15 fichiers |
| `innerHTML` usages | massif (non compté) |
| Fichiers PHP (controllers) | 35+ |
| `strict_types` | 100% |
| SQL paramétré | 100% (sauf LIMIT) |
| Trouvailles CRITICAL | 2 |
| Trouvailles HIGH | 3 |
| Trouvailles MEDIUM | ~12 |
| Trouvailles LOW | ~8 |
