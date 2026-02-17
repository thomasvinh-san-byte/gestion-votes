# AG-Vote — Gestion des votes

Application web de gestion de **séances de vote avec vote électronique sécurisé**, conçue pour un usage opérationnel réel (assemblées générales, conseils syndicaux, réunions formelles).

Le produit couvre l'intégralité du cycle de vie d'une séance :
**préparation → conduite en live → consolidation → validation → production des livrables (PV, exports).**

---

## Démarrage rapide (Docker)

### Prérequis

- [Docker Desktop](https://www.docker.com/products/docker-desktop/) (macOS / Windows) ou Docker Engine (Linux)

### Lancer en 4 commandes

```bash
git clone https://github.com/thomasvinh-san-byte/gestion-votes.git
cd gestion-votes
cp .env.example .env
docker compose up -d
```

Ouvrir **http://localhost:8080** dans le navigateur.

> Premier lancement : 3–5 min (build de l'image). Les suivants : ~5 sec.

### Image pré-construite (ghcr.io)

```bash
docker pull ghcr.io/thomasvinh-san-byte/gestion-votes:main
```

### Comptes de test

| Rôle | Email | Mot de passe |
|------|-------|-------------|
| Admin | `admin@ag-vote.local` | `Admin2026!` |
| Opérateur | `operator@ag-vote.local` | `Operator2026!` |
| Président | `president@ag-vote.local` | `President2026!` |
| Votant | `votant@ag-vote.local` | `Votant2026!` |
| Auditeur | `auditor@ag-vote.local` | `Auditor2026!` |
| Observateur | `viewer@ag-vote.local` | `Viewer2026!` |

---

## Stack technique

| Composant | Technologie |
|-----------|-------------|
| Backend | PHP 8.3, 22 services, 27 repositories |
| API REST | 153 endpoints (public/api/v1/) |
| Frontend | HTML, JavaScript vanilla, Web Components |
| Base de données | PostgreSQL 16 (36 tables) |
| Temps réel | WebSocket (PHP natif) |
| Conteneurisation | Docker, Nginx, PHP-FPM, Supervisord |
| CI/CD | GitHub Actions → ghcr.io |

---

## Objectifs du produit

- Permettre la **conduite fluide d'une séance de vote** avec ou sans vote électronique
- Garantir des **résultats juridiquement défendables** (dans le périmètre CDC défini)
- Offrir une **traçabilité complète** des actions et incidents
- Rendre la séance **testable et rejouable** (seed, reset, audit)
- Fournir des **livrables exploitables** (PV, CSV) après validation

---

## Rôles utilisateurs

### Rôles système (permanents)

| Rôle | Accès | Description |
|------|-------|-------------|
| **admin** | Total | Gestion des utilisateurs, politiques, configuration système |
| **operator** | Conduite | Pilotage des séances, gestion présences, ouverture/fermeture votes |
| **auditor** | Lecture audit | Contrôle des anomalies, journal d'audit, conformité |
| **viewer** | Lecture seule | Consultation des archives et résultats |

### Rôles de séance (par réunion)

| Rôle | Accès | Description |
|------|-------|-------------|
| **president** | Décision | Clôture les votes, valide la séance, engage la responsabilité juridique |
| **assessor** | Support | Assiste l'opérateur, vérifie la conformité |
| **voter** | Vote | Exprime son vote via tablette ou mobile |

---

## Interfaces (14 pages)

| Interface | URL | Rôle cible |
|-----------|-----|------------|
| Accueil | `/index.html` | Tous |
| Connexion | `/login.html` | Tous |
| Séances | `/meetings.htmx.html` | operator |
| Console opérateur | `/operator.htmx.html` | operator |
| Membres | `/members.htmx.html` | operator |
| Modèles email | `/email-templates.htmx.html` | operator |
| Vote (tablette) | `/vote.htmx.html` | voter |
| Validation | `/validate.htmx.html` | president |
| PV / Exports | `/report.htmx.html` | operator |
| Contrôle & audit | `/trust.htmx.html` | auditor |
| Administration | `/admin.htmx.html` | admin |
| Analytics | `/analytics.htmx.html` | admin |
| Archives | `/archives.htmx.html` | viewer |
| Écran public | `/public.htmx.html` | public |
| Aide & FAQ | `/help.htmx.html` | Tous |
| Documentation | `/docs.htmx.html` | Tous |

---

## Fonctionnalités principales

### Gestion de séance
- Création et pilotage avec machine à états (`draft → scheduled → frozen → live → closed → validated → archived`)
- Ordre du jour et résolutions
- Transitions contrôlées par rôle

### Présences & procurations
- Pointage présent / distant / absent / excusé
- Procurations avec contrôle de plafond
- Impact automatique sur quorum et pondération

### Vote électronique
- Vote par **token unique** (QR code / lien tablette)
- Anti-rejeu (token consommé une seule fois)
- Confirmation obligatoire côté votant
- Support du **mode dégradé** (vote manuel justifié)

### Calculs automatiques
- Pondération (tantièmes / poids)
- Quorum global et par résolution (politiques configurables)
- Majorité pondérée avec politiques de seuil
- Résultat juridique explicite par résolution

### Contrôle & traçabilité
- Journal d'audit append-only avec chaîne de hachage SHA256
- Déclaration d'incidents (réseau, matériel, décision exceptionnelle)
- Détection automatique d'anomalies
- Actions manuelles tracées avec justification

### Post-séance
- Validation finale par le président
- **Verrouillage complet** de la base après validation (HTTP 409)
- Génération de PV (HTML + PDF)
- Exports CSV (présences, votes, résultats, audit)

---

## Architecture du projet

```
gestion-votes/
├── public/                  Racine web (DocumentRoot Nginx)
│   ├── api/v1/              153 endpoints REST (PHP)
│   ├── assets/css/          Feuilles de style
│   ├── assets/js/           JavaScript client + Web Components
│   ├── assets/icons.svg     Sprite SVG (92 icônes)
│   ├── partials/            Composants HTML réutilisables
│   └── *.htmx.html          14 pages applicatives
├── app/                     Code backend
│   ├── api.php              Point d'entrée API (helpers)
│   ├── bootstrap.php        Initialisation applicative
│   ├── Services/            22 services métier
│   ├── Repository/          27 repositories (accès données)
│   ├── Core/Security/       AuthMiddleware, CSRF, RateLimiter
│   ├── Core/Validation/     Validation des entrées
│   └── Templates/           Templates email
├── database/
│   ├── schema-master.sql    Schéma PostgreSQL (36 tables)
│   ├── setup.sh             Script d'initialisation
│   ├── migrations/          Migrations incrémentales
│   └── seeds/               Seeds numérotées (01-07)
├── deploy/                  Configuration Docker
│   ├── nginx.conf           Configuration Nginx
│   ├── php-fpm.conf         Configuration PHP-FPM
│   ├── supervisord.conf     Orchestration des processus
│   └── entrypoint.sh        Script de démarrage conteneur
├── docs/                    Documentation complète
├── docker-compose.yml       Orchestration des services
├── Dockerfile               Build de l'image applicative
└── .env.example             Template de configuration
```

---

## Installation

| Plateforme | Guide |
|------------|-------|
| **macOS** (Intel / Apple Silicon) | [docs/INSTALL_MAC.md](docs/INSTALL_MAC.md) |
| **Linux** (Debian / Ubuntu) | [docs/DOCKER_INSTALL.md](docs/DOCKER_INSTALL.md) |
| **Développeur** (sans Docker) | [docs/dev/INSTALLATION.md](docs/dev/INSTALLATION.md) |

### Installation sans Docker (développement)

```bash
# Prérequis : PHP 8.3+, PostgreSQL 16+
git clone https://github.com/thomasvinh-san-byte/gestion-votes.git
cd gestion-votes
sudo bash database/setup.sh
php -S 0.0.0.0:8080 -t public
```

---

## Documentation

| Document | Description |
|----------|-------------|
| [FAQ](docs/FAQ.md) | Questions fréquentes |
| [Guide opérateur](docs/UTILISATION_LIVE.md) | Conduite d'une séance en direct |
| [Démo guidée](docs/RECETTE_DEMO.md) | Scénario de démonstration (~10 min) |
| [Installation macOS](docs/INSTALL_MAC.md) | Guide pas-à-pas pour Mac |
| [Installation Docker](docs/DOCKER_INSTALL.md) | Déploiement Docker sur Linux |
| [Architecture](docs/dev/ARCHITECTURE.md) | Architecture technique, patterns, conventions |
| [API](docs/dev/API.md) | Référence des 153 endpoints |
| [Sécurité](docs/dev/SECURITY.md) | Authentification, audit, RGPD |
| [Conformité CDC](docs/dev/CONFORMITE_CDC.md) | Cadre juridique, garanties, limites |
| [Tests](docs/dev/TESTS.md) | Stratégie de tests |

La documentation est aussi consultable en ligne dans l'application : `/docs.htmx.html`

---

## Conformité & cadre CDC

Le système couvre notamment :

- Identification du votant par token unique
- Unicité du vote (anti-rejeu)
- Pondération et quorum
- Traçabilité complète (audit, incidents, actions manuelles)
- Calculs rejouables depuis les bulletins
- Verrouillage total après validation
- PV et exports uniquement post-validation

Les limites connues sont détaillées dans [CONFORMITE_CDC.md](docs/dev/CONFORMITE_CDC.md).

---

## Philosophie du projet

- **Clarté > complexité**
- **DB comme source de vérité**
- **Moins de magie, plus d'audit**
- **Ce qui n'est pas couvert est explicitement documenté**
