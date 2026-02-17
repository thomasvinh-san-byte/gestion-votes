# AG-Vote

Application web de gestion de séances de vote avec vote électronique sécurisé.

Assemblées générales, conseils syndicaux, réunions formelles — préparation, conduite en direct, vote, résultats, PV.

[![Open in GitHub Codespaces](https://github.com/codespaces/badge.svg)](https://codespaces.new/thomasvinh-san-byte/gestion-votes?quickstart=1)

## Démarrage rapide

```bash
git clone https://github.com/thomasvinh-san-byte/gestion-votes.git
cd gestion-votes
cp .env.example .env
docker compose up -d
```

Ouvrir **http://localhost:8080** — compte test : `admin@ag-vote.local` / `Admin2026!`

> Ou cliquer sur **Open in GitHub Codespaces** ci-dessus pour lancer directement dans le navigateur.

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

Documentation technique : [Architecture](docs/dev/ARCHITECTURE.md) · [API](docs/dev/API.md) · [Sécurité](docs/dev/SECURITY.md) · [Tests](docs/dev/TESTS.md) · [Conformité](docs/dev/CONFORMITE_CDC.md)

## Licence

Projet privé — tous droits réservés.
