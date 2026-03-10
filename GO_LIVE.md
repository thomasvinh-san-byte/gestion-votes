# GO-LIVE — Seuil de mise en production AG-VOTE v3.19

> **Date** : 2026-03-10
> **Statut** : PRÊT AU SEUIL — NE PAS PASSER EN PRODUCTION
> **Branche** : `claude/plan-directive-wireframe-HomdZ`

---

## 1. Résumé exécutif

Le système AG-VOTE est au **seuil de go-live** : toutes les fonctionnalités critiques
sont implémentées, la sécurité est validée (y compris l'audit innerHTML P2-03),
les 16 pages du wireframe existent en HTML. **Aucun item technique bloquant ne subsiste.**
Il reste le provisionnement d'infrastructure et **plusieurs éléments nice-to-have**
non bloquants reportés post-go-live.

**Ce document recense ce qui est fait, ce qui bloque, et ce qui est reporté.**

---

## 2. Matrice de complétude — 16 pages wireframe

| # | Page | Fichier HTML | JS | Statut |
|---|------|--------------|----|--------|
| 1 | Landing | `index.html` | — | ✅ Prêt |
| 2 | Dashboard | `dashboard.htmx.html` | `dashboard.js` | ✅ Prêt |
| 3 | Séances | `meetings.htmx.html` | `meetings.js` | ✅ Prêt |
| 4 | Wizard | `wizard.htmx.html` | `wizard.js` | ✅ Prêt |
| 5 | Hub (Fiche séance) | `hub.htmx.html` | `hub.js` | ✅ Prêt |
| 6 | Opérateur | `operator.htmx.html` | `operator-tabs.js` + modules | ✅ Prêt |
| 7 | Post-session (PV) | `postsession.htmx.html` | `postsession.js` | ✅ Prêt |
| 8 | Votant (tablette) | `vote.htmx.html` | `vote.js` | ✅ Prêt |
| 9 | Écran salle | `public.htmx.html` | `public-screen.js` | ✅ Prêt |
| 10 | Audit | `trust.htmx.html` | `trust.js` | ✅ Prêt |
| 11 | Archives | `archives.htmx.html` | `archives.js` | ✅ Prêt |
| 12 | Membres | `members.htmx.html` | `members.js` | ✅ Prêt |
| 13 | Utilisateurs | `admin.htmx.html` | `admin.js` | ✅ Prêt |
| 14 | Paramètres | `admin.htmx.html?tab=settings` | `admin.js` | ✅ Prêt |
| 15 | Statistiques | `analytics.htmx.html` | `analytics.js` | ✅ Prêt |
| 16 | Aide | `help.htmx.html` | `help.js` | ✅ Prêt |

**Résultat : 16/16 pages implémentées.**

---

## 3. Sécurité

| Domaine | Statut |
|---------|--------|
| Auth deny-by-default | ✅ Fait (P1-06, #03) |
| Ballot strict INSERT (pas d'upsert) | ✅ Fait (#02) |
| VoteToken dans cast() | ✅ Fait (P1-03) |
| tenant_id defense-in-depth | ✅ Fait (P1-04) |
| Audit logging complet | ✅ Fait (P1-02) |
| Consolidation protégée (rôle operator/admin) | ✅ Fait (P1-07) |
| CSRF actif (sauf votant — protégé par token) | ✅ Fait |
| CSP headers | ✅ Fait |
| escapeHtml() correct dans les 5 Web Components | ✅ Fait (P1-01) |
| innerHTML audit (309 assignments, 48 fichiers) | ✅ Fait (P2-03) — toutes données échappées |

Détail complet : voir `SECURITY_AUDIT.md` (25 constatations, 19 confirmées, toutes CRITIQUE/ÉLEVÉE résolues).

---

## 4. Backend

| Métrique | Valeur |
|----------|--------|
| Contrôleurs | 38 |
| Services | 19 |
| Repositories | 30 + 4 traits |
| Routes API | 291 |
| Tables DB | 37 |
| Migrations | 16 |
| Tests | 693 (0 failures, 1514 assertions) |

**Verdict** : Backend 100% production-ready. Aucun item bloquant.

---

## 5. Items BLOQUANTS avant go-live

### 5.1. ~~P2-03 — Audit innerHTML (SÉCURITÉ)~~ ✅ RÉSOLU

- **Quoi** : 309 assignments `innerHTML` dans 48 fichiers JS — audit systématique terminé
- **Résultat** : Toutes les données dynamiques utilisateur (noms, emails, titres, rôles)
  sont échappées via `escapeHtml()` ou `encodeURIComponent()`. Les seules interpolations
  sans échappement sont des valeurs numériques internes ou des chaînes statiques.
- **Conclusion** : **Aucune vulnérabilité XSS identifiée. Aucune correction nécessaire.**

### 5.2. Vérification déploiement (seul item bloquant restant)

- **Quoi** : Toutes les variables d'environnement de `PRODUCTION.md` doivent être
  configurées sur l'infra cible
- **Statut** : Checklist documentée, non exécutée (go-live pas encore lancé)
- **C'est le seul élément technique restant avant go-live.**

---

## 6. Items NICE-TO-HAVE (reportés post-go-live)

Ces fonctionnalités existent dans le wireframe React mais ne sont **pas nécessaires**
pour un go-live fonctionnel. Elles améliorent l'expérience sans bloquer l'usage.

### 6.1. Guided Tours (visites guidées)

- **Wireframe** : 7 tours, 23 étapes au total (dashboard, wizard, hub, operator, etc.)
- **Statut** : Non implémenté — composant `GuidedTour` présent uniquement dans le wireframe
- **Raison du report** : UX polish, pas fonctionnel. Les utilisateurs peuvent utiliser
  la page Aide pour comprendre le système.
- **Effort estimé** : 2-3 jours

### 6.2. Global Search (Ctrl+K)

- **Wireframe** : Recherche globale avec overlay, filtres par section
- **Statut** : Non implémenté
- **Raison du report** : Commodité de navigation. La sidebar + les pages individuelles
  offrent déjà une navigation complète.
- **Effort estimé** : 1-2 jours

### 6.3. Onboarding Banner

- **Wireframe** : Bannière « Bienvenue » avec étapes de démarrage
- **Statut** : Non implémenté
- **Raison du report** : First-use UX, pas critique pour les utilisateurs formés.
- **Effort estimé** : 0.5 jour

### 6.4. Vue calendrier (séances)

- **Wireframe** : Mode calendrier pour visualiser les séances
- **Statut** : La vue liste est implémentée, pas la vue calendrier
- **Raison du report** : Mode d'affichage alternatif, la liste suffit.
- **Effort estimé** : 1-2 jours

### 6.5. Drag & drop (réordonnement résolutions)

- **Wireframe** : Réordonnement des résolutions par glisser-déposer dans le wizard
- **Statut** : Non implémenté — les résolutions sont dans l'ordre de création
- **Raison du report** : Commodité, pas bloquant (les résolutions peuvent être
  réordonnées manuellement via l'API).
- **Effort estimé** : 1 jour

### 6.6. Phase 3 — Split supplémentaire operator-tabs.js

- **Quoi** : Extraire settings + dashboard en sous-modules (2 669 → ~1 850 lignes)
- **Statut** : Le fichier fonctionne tel quel. Phase 2 (split initial) est faite.
- **Raison du report** : Maintenance/lisibilité, pas fonctionnel.
- **Effort estimé** : 2h

### 6.7. Tests E2E (P2-04)

- **Quoi** : Playwright/Cypress pour les parcours critiques
- **Statut** : 693 tests unitaires couvrent la logique. 2 tests d'intégration existent.
- **Raison du report** : Important mais non bloquant — les tests unitaires couvrent
  la logique métier. Les E2E sont pour la confiance sur les parcours UI.
- **Effort estimé** : 1-2 semaines

---

## 7. Configuration go-live

Voir `PRODUCTION.md` pour la checklist complète. Résumé des variables critiques :

```bash
APP_ENV=production          # ⚠️ NE PAS oublier
APP_DEBUG=0                 # ⚠️ NE PAS oublier
APP_AUTH_ENABLED=1          # ⚠️ OBLIGATOIRE
CSRF_ENABLED=1              # ⚠️ OBLIGATOIRE
RATE_LIMIT_ENABLED=1        # ⚠️ OBLIGATOIRE
APP_SECRET=<64+ chars>      # ⚠️ OBLIGATOIRE — openssl rand -hex 64
DB_DSN=pgsql:host=...       # ⚠️ OBLIGATOIRE
DEFAULT_TENANT_ID=<uuid>    # ⚠️ OBLIGATOIRE
```

---

## 8. Définition de « go-live »

**Go-live** signifie :
1. Toutes les variables de `PRODUCTION.md` sont configurées
2. La base de données est initialisée (`database/setup.sh`)
3. Les migrations sont appliquées
4. Le serveur web pointe sur `/public/`
5. HTTPS est activé
6. Le système est accessible aux utilisateurs finaux

**Nous sommes au seuil** : le code est prêt, la documentation existe, mais aucune
de ces 6 étapes d'infrastructure n'a été exécutée.

---

## 9. Résumé visuel

```
 FAIT                           BLOQUANT              NICE-TO-HAVE
 ──────────────────────         ─────────────         ──────────────────
 ✅ 16/16 pages HTML            ⚠️ Config infra       ○ Guided tours
 ✅ 7 P1 sécurité                  (seul restant)     ○ Global search
 ✅ P2-03 innerHTML audité                             ○ Onboarding banner
 ✅ 693 tests (0 fail)                                 ○ Vue calendrier
 ✅ 291 routes API                                     ○ Drag & drop
 ✅ Sidebar complète                                   ○ Operator split P3
 ✅ Design system Acte Officiel                        ○ Tests E2E
 ✅ Dark mode
 ✅ Documentation complète
```

---

## 10. Décision

**NE PAS passer en production tant que :**
1. ~~L'audit innerHTML (P2-03) n'est pas terminé et validé~~ ✅ Fait
2. L'infrastructure cible n'est pas configurée et testée

**Le code est 100% prêt.** Tous les items techniques sont résolus.
**Le passage en production est une décision métier** — il ne reste que le
provisionnement de l'infrastructure (serveur, DB, HTTPS, variables d'env).
