# AG-Vote — Gestion des votes

Application web de gestion de **seances de vote avec vote electronique securise**, concue pour un usage operationnel reel (assemblees generales, conseils syndicaux, reunions formelles).

Le produit couvre l'integralite du cycle de vie d'une seance :
**preparation > conduite en live > consolidation > validation > production des livrables (PV, exports).**

---

## Objectifs du produit

- Permettre la **conduite fluide d'une seance de vote** avec ou sans vote electronique
- Garantir des **resultats juridiquement defendables** (dans le perimetre CDC defini)
- Offrir une **tracabilite complete** des actions et incidents
- Rendre la seance **testable et rejouable** (seed, reset, audit)
- Fournir des **livrables exploitables** (PV, CSV) apres validation

Le projet est volontairement :

- **PostgreSQL-first** (DB = source de verite)
- **Simple cote front** (HTML + HTMX, pas de SPA)
- **Strict sur les regles metier** (garde-fous, verrouillage)

---

## Stack technique

| Composant | Technologie |
|-----------|-------------|
| Backend | PHP 8.3+, PDO PostgreSQL |
| Frontend | HTML, HTMX, JavaScript vanilla |
| Base de donnees | PostgreSQL 16+ |
| Authentification | Cle API + session PHP |
| Serveur | Apache 2.4+ avec mod_rewrite |

---

## Demarrage rapide

### 1. Prerequis

- PHP >= 8.3 avec extensions : `pdo_pgsql`, `mbstring`, `json`, `session`
- PostgreSQL >= 16
- Apache 2.4+ (ou serveur PHP integre pour le dev)

### 2. Installation

```bash
# Cloner le projet
git clone <url> gestion-votes
cd gestion-votes

# Installer les dependances systeme (Ubuntu/Debian)
sudo bash scripts/install-deps.sh

# Initialiser la base de donnees (role + schema + migrations + seeds + .env)
sudo bash database/setup.sh
```

Ou sans donnees de demo : `sudo bash database/setup.sh --no-demo`

### 3. Lancer le serveur de dev

```bash
php -S 0.0.0.0:8080 -t public
```

### 4. Se connecter

Ouvrir `http://localhost:8080/login.html` avec les identifiants de test :

| Role | Email | Mot de passe |
|------|-------|-------------|
| admin | `admin@ag-vote.local` | `Admin2026!` |
| operator | `operator@ag-vote.local` | `Operator2026!` |
| president | `president@ag-vote.local` | `President2026!` |
| votant | `votant@ag-vote.local` | `Votant2026!` |

Comptes crees par `database/seeds/02_test_users.sql`.

Voir [docs/INSTALLATION.md](docs/INSTALLATION.md) pour l'installation complete.

---

## Roles utilisateurs

Le systeme distingue deux types de roles :

### Roles systeme (permanents)

| Role | Acces | Description |
|------|-------|-------------|
| **admin** | Total | Gestion des utilisateurs, politiques, configuration systeme |
| **operator** | Conduite | Pilotage des seances, gestion presences, ouverture/fermeture votes |
| **auditor** | Lecture audit | Controle des anomalies, journal d'audit, conformite |
| **viewer** | Lecture seule | Consultation des archives et resultats |

### Roles de seance (par reunion)

| Role | Acces | Description |
|------|-------|-------------|
| **president** | Decision | Cloture les votes, valide la seance, engage la responsabilite juridique |
| **assessor** | Support | Assiste l'operateur, verifie la conformite |
| **voter** | Vote | Exprime son vote via tablette ou mobile |

---

## Interfaces principales

| Interface | URL | Role cible |
|-----------|-----|------------|
| Accueil | `/index.html` | Tous |
| Connexion | `/login.html` | Tous |
| Tableau de bord | `/meetings.htmx.html` | operator |
| Console operateur | `/operator.htmx.html` | operator |
| Flux operateur | `/operator_flow.htmx.html` | operator |
| Cockpit president | `/president.htmx.html` | president |
| Controle & audit | `/trust.htmx.html` | auditor |
| Administration | `/admin.htmx.html` | admin |
| Resolutions | `/motions.htmx.html` | operator |
| Presences | `/attendance.htmx.html` | operator |
| Membres | `/members.htmx.html` | operator |
| Procurations | `/proxies.htmx.html` | operator |
| Invitations | `/invitations.htmx.html` | operator |
| Vote (tablette) | `/vote.htmx.html` | voter |
| Validation | `/validate.htmx.html` | president |
| PV / Exports | `/report.htmx.html` | operator |
| Archives | `/archives.htmx.html` | viewer |
| Ecran public | `/public.htmx.html` | public |
| Vote papier | `/paper_redeem.htmx.html` | public |

---

## Fonctionnalites principales

### Gestion de seance
- Creation et pilotage de seances avec machine a etats (`draft > scheduled > frozen > live > closed > validated > archived`)
- Ordre du jour et resolutions
- Transitions controlees par role

### Presences & procurations
- Pointage present / distant / absent / excuse
- Procurations avec controle de plafond
- Impact automatique sur quorum et ponderation

### Vote electronique
- Vote par **token unique** (QR / lien tablette)
- Anti-rejeu (token consomme une seule fois)
- Confirmation obligatoire cote votant
- Support du **mode degrade** (vote manuel justifie)
- Vote papier avec code a usage unique

### Calculs automatiques
- Ponderation (tantiemes / poids)
- Quorum global et par resolution (politiques configurables)
- Majorite ponderee avec politiques de seuil
- Resultat juridique explicite par resolution

### Controle & tracabilite
- Journal d'audit append-only avec chaine de hachage SHA256
- Declaration d'incidents (reseau, materiel, decision exceptionnelle)
- Detection automatique d'anomalies
- Actions manuelles tracees avec justification

### Post-seance
- Validation finale par le president
- **Verrouillage complet** de la base apres validation (HTTP 409)
- Generation de PV (HTML + PDF)
- Exports CSV (presences, votes, resultats, audit)

---

## Architecture du projet

```
gestion-votes/
+-- public/                  Racine web (DocumentRoot Apache)
|   +-- api/v1/              118 endpoints REST (PHP)
|   +-- assets/css/          Feuilles de style
|   +-- assets/js/           JavaScript client
|   +-- partials/            Composants HTML reutilisables
|   +-- errors/              Pages d'erreur 404/500
|   +-- *.htmx.html          Pages applicatives
|   +-- .htaccess            Routage et securite Apache
+-- app/                     Code backend
|   +-- api.php              Point d'entree API (fonctions helpers)
|   +-- bootstrap.php        Initialisation applicative
|   +-- services/            17 services metier
|   +-- Core/Security/       AuthMiddleware, CSRF, RateLimiter
|   +-- Core/Validation/     Validation des entrees
+-- database/
|   +-- schema.sql           Schema PostgreSQL (35+ tables)
|   +-- setup.sh             Script d'initialisation automatique
|   +-- migrations/          Migrations incrementales
|   +-- seeds/               Seeds numerotes (01-07)
+-- docs/                    Documentation complete
+-- .env                     Configuration environnement
+-- .env.production          Template production
+-- PRODUCTION.md            Checklist deploiement
```

Voir [docs/ARCHITECTURE.md](docs/ARCHITECTURE.md) pour le detail technique.

---

## Documentation

| Document | Description |
|----------|-------------|
| [ARCHITECTURE.md](docs/ARCHITECTURE.md) | Architecture technique, patterns, conventions |
| [API.md](docs/API.md) | Reference complete des 118 endpoints |
| [INSTALLATION.md](docs/INSTALLATION.md) | Installation complete sur Linux |
| [UTILISATION_LIVE.md](docs/UTILISATION_LIVE.md) | Guide operationnel jour J |
| [RECETTE_DEMO.md](docs/RECETTE_DEMO.md) | Scenario de demonstration (~10 min) |
| [SECURITY.md](docs/SECURITY.md) | Securite, authentification, audit |
| [CONFORMITE_CDC.md](docs/CONFORMITE_CDC.md) | Cadre juridique, garanties, limites |
| [FAQ.md](docs/FAQ.md) | Questions frequentes |
| [PRODUCTION.md](PRODUCTION.md) | Checklist deploiement production |
| [cahier_des_charges.md](docs/cahier_des_charges.md) | Specifications fonctionnelles v1.1 |

---

## Conformite & cadre CDC

Le systeme couvre notamment :

- Identification du votant par token unique
- Unicite du vote (anti-rejeu)
- Ponderation et quorum
- Tracabilite complete (audit, incidents, actions manuelles)
- Calculs rejouables depuis les bulletins
- Verrouillage total apres validation
- PV et exports uniquement post-validation

Les limites connues (assumees et documentees) sont detaillees dans [CONFORMITE_CDC.md](docs/CONFORMITE_CDC.md).

---

## Philosophie du projet

- **Clarte > complexite**
- **DB comme source de verite**
- **Moins de magie, plus d'audit**
- **Ce qui n'est pas couvert est explicitement documente**
