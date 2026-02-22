# AG-Vote

Application web de gestion de séances de vote avec vote électronique sécurisé.

Assemblées générales, conseils syndicaux, réunions formelles — préparation, conduite en direct, vote, résultats, PV.

**Démo en ligne : https://ag-vote.onrender.com/**

> Compte test : `admin@ag-vote.local` / `Admin2026!`

[![Deploy to Render](https://render.com/images/deploy-to-render-button.svg)](https://render.com/deploy?repo=https://github.com/thomasvinh-san-byte/gestion-votes)

## Prérequis

L'application tourne dans **Docker**, ce qui évite d'installer PHP, PostgreSQL ou Nginx manuellement.

| Système | Ce qu'il faut installer |
|---------|------------------------|
| **macOS** | [Docker Desktop for Mac](https://www.docker.com/products/docker-desktop/) — télécharger, glisser dans Applications, lancer. Choisir *Apple Chip* (M1–M4) ou *Intel* selon votre Mac. Détails : [guide complet](docs/INSTALL_MAC.md) |
| **Windows** | [Docker Desktop for Windows](https://www.docker.com/products/docker-desktop/) — installer, activer WSL 2 si proposé, relancer |
| **Linux** | Docker Engine + Docker Compose — voir [guide Linux](docs/DOCKER_INSTALL.md) |

Vérifier que tout est prêt :

```bash
docker --version          # Docker version 27.x.x
docker compose version    # Docker Compose version v2.x.x
```

> Si `docker` n'est pas reconnu après installation, fermez et rouvrez votre terminal.

## Démarrage rapide

```bash
git clone https://github.com/thomasvinh-san-byte/gestion-votes.git
cd gestion-votes
cp .env.example .env
docker compose up -d
```

Premier lancement : 3–5 min (téléchargement des images). Les suivants : ~5 secondes.

Ouvrir **http://localhost:8080** — compte test : `admin@ag-vote.local` / `Admin2026!`

## Fonctionnalités

- **Séances** — machine à états complète (draft → live → validated → archived)
- **Vote électronique** — token unique, anti-rejeu, QR code, mode dégradé
- **Quorum & pondération** — tantièmes, politiques configurables par résolution
- **Présences & procurations** — pointage, plafond, impact automatique sur le quorum
- **Audit** — journal append-only avec chaîne de hachage SHA256, traçabilité complète
- **Livrables** — PV (HTML/PDF), exports CSV, verrouillage post-validation
- **Temps réel** — WebSocket pour synchronisation opérateur/votants

## Stack

PHP 8.3 · PostgreSQL 16 · JavaScript vanilla · Docker · Nginx · WebSocket

## Documentation

| Guide | |
|-------|---|
| [Installation macOS](docs/INSTALL_MAC.md) | Docker Desktop sur Mac |
| [Installation Linux](docs/DOCKER_INSTALL.md) | Docker sur Debian/Ubuntu |
| [Guide opérateur](docs/UTILISATION_LIVE.md) | Conduite d'une séance |
| [Démo guidée](docs/RECETTE_DEMO.md) | Scénario de test (~10 min) |
| [FAQ](docs/FAQ.md) | Questions fréquentes |
| [Deploiement Render](docs/DEPLOIEMENT_RENDER.md) | Deployer sur Render (demo et production) |

Documentation technique : [Architecture](docs/dev/ARCHITECTURE.md) · [API](docs/dev/API.md) · [Sécurité](docs/dev/SECURITY.md) · [Tests](docs/dev/TESTS.md) · [Conformité](docs/dev/CONFORMITE_CDC.md)

## Licence

Projet privé — tous droits réservés.
