# AG-VOTE v3.19 — Statut projet

> **Date** : 2026-03-10
> **Statut** : CODE PRÊT — Infrastructure à provisionner

---

## 1. Résumé

Le code AG-VOTE est **100% prêt pour la production**. Les 16 pages du wireframe
sont implémentées, la sécurité est auditée et validée, 693 tests passent sans échec.

Le seul prérequis restant est le **provisionnement de l'infrastructure** (serveur,
base de données, HTTPS, variables d'environnement).

---

## 2. Pages — 16/16

| # | Page | HTML | JS |
|---|------|------|----|
| 1 | Landing | `index.html` | — |
| 2 | Dashboard | `dashboard.htmx.html` | `dashboard.js` |
| 3 | Séances | `meetings.htmx.html` | `meetings.js` |
| 4 | Wizard | `wizard.htmx.html` | `wizard.js` |
| 5 | Hub (Fiche séance) | `hub.htmx.html` | `hub.js` |
| 6 | Opérateur | `operator.htmx.html` | `operator-tabs.js` + modules |
| 7 | Post-session (PV) | `postsession.htmx.html` | `postsession.js` |
| 8 | Votant (tablette) | `vote.htmx.html` | `vote.js` |
| 9 | Écran salle | `public.htmx.html` | `public-screen.js` |
| 10 | Audit | `trust.htmx.html` | `trust.js` |
| 11 | Archives | `archives.htmx.html` | `archives.js` |
| 12 | Membres | `members.htmx.html` | `members.js` |
| 13 | Utilisateurs | `admin.htmx.html` | `admin.js` |
| 14 | Paramètres | `admin.htmx.html?tab=settings` | `admin.js` |
| 15 | Statistiques | `analytics.htmx.html` | `analytics.js` |
| 16 | Aide | `help.htmx.html` | `help.js` |

---

## 3. Sécurité

Audit complet : voir `SECURITY_AUDIT.md` (25 constatations, toutes CRITIQUE/ÉLEVÉE résolues).

| Contrôle | Statut |
|----------|--------|
| Auth deny-by-default | ✅ |
| Ballot strict INSERT (pas d'upsert) | ✅ |
| VoteToken dans cast() | ✅ |
| tenant_id defense-in-depth | ✅ |
| Audit logging complet | ✅ |
| Consolidation protégée (operator/admin) | ✅ |
| CSRF actif (votant protégé par token one-time) | ✅ |
| CSP headers | ✅ |
| escapeHtml() dans tous les Web Components | ✅ |
| innerHTML audit (309 assignments, 48 fichiers) | ✅ |

---

## 4. Backend

| Métrique | Valeur |
|----------|--------|
| Contrôleurs | 38 |
| Services | 19 |
| Repositories | 30 + 4 traits |
| Routes API | 291 |
| Tables DB | 37 |
| Migrations | 21 |
| Tests | 693 (0 failures, 1514 assertions) |

---

## 5. Mise en production

Voir `PRODUCTION.md` pour la checklist complète.

```bash
APP_ENV=production
APP_DEBUG=0
APP_AUTH_ENABLED=1
CSRF_ENABLED=1
RATE_LIMIT_ENABLED=1
APP_SECRET=<64+ chars>      # openssl rand -hex 64
DB_DSN=pgsql:host=...
DEFAULT_TENANT_ID=<uuid>
```

**Étapes :**
1. Configurer les variables d'environnement
2. Initialiser la base de données (`database/setup.sh`)
3. Appliquer les migrations
4. Pointer le serveur web sur `/public/`
5. Activer HTTPS
6. Vérifier avec `bin/check-prod-readiness.sh`

---

## 6. Améliorations futures (non bloquantes)

| Feature | Effort | Description |
|---------|--------|-------------|
| Guided Tours | 2-3j | Visites guidées interactives (7 tours, 23 étapes) |
| Global Search | 1-2j | Ctrl+K, recherche globale avec overlay |
| Onboarding Banner | 0.5j | Bannière d'accueil première connexion |
| Vue calendrier | 1-2j | Affichage calendrier des séances (complément vue liste) |
| Drag & drop résolutions | 1j | Réordonnement par glisser-déposer dans le wizard |
| Split operator-tabs.js | 2h | Extraire settings + dashboard en sous-modules |
| Tests E2E | 1-2 sem | Playwright/Cypress pour les parcours critiques |

---

## 7. Documentation

| Document | Contenu |
|----------|---------|
| `PRODUCTION.md` | Checklist de mise en production |
| `SECURITY_AUDIT.md` | Audit sécurité détaillé (25 constatations) |
| `SETUP.md` | Installation locale |
| `CONTRIBUTING.md` | Guide de contribution |
| `docs/GUIDE_FONCTIONNEL.md` | Guide fonctionnel complet |
| `docs/FAQ.md` | Questions fréquentes |
| `docs/RECETTE_DEMO.md` | Démo en 10 minutes |
| `docs/UTILISATION_LIVE.md` | Guide opérateur séance en direct |
| `docs/DEPLOIEMENT_DOCKER.md` | Déploiement Docker |
| `docs/DEPLOIEMENT_RENDER.md` | Déploiement Render |
| `docs/directive-projet.md` | Cahier des charges (référence) |
| `docs/dev/` | API, architecture, sécurité, tests, Web Components |
