# AG-Vote

Plateforme de vote en assemblée générale — de la convocation au procès-verbal.

Créez une séance, invitez les membres, conduisez les votes en direct, générez le PV officiel. Conçu pour les associations, syndicats de copropriété et organisations soumises au droit français.

**Démo :** [ag-vote.onrender.com](https://ag-vote.onrender.com/) — `admin@ag-vote.local` / `Admin2026!`

## Démarrage rapide

```bash
git clone https://github.com/thomasvinh-san-byte/gestion-votes.git
cd gestion-votes
./bin/dev.sh
```

Ouvrir **http://localhost:8080** — premier lancement ~3 min, les suivants ~5s.

> Prérequis : [Docker Desktop](https://www.docker.com/products/docker-desktop/) (macOS/Windows) ou Docker Engine (Linux). Voir [installation macOS](docs/INSTALL_MAC.md) · [installation Linux](docs/DOCKER_INSTALL.md).

## Fonctionnalités

| Domaine | Description |
|---------|-------------|
| **Séances** | Machine à états complète : brouillon → planifiée → gelée → en cours → clôturée → validée → archivée |
| **Vote électronique** | Token unique par votant, anti-rejeu, QR code, scrutin secret ou public |
| **Quorum** | Politiques configurables (50%+1, 1/3, personnalisé), calcul automatique en temps réel |
| **Présences & procurations** | Pointage en direct, plafond de procurations, impact automatique sur le quorum |
| **Temps réel** | SSE (Server-Sent Events) avec fan-out Redis — synchronisation opérateur/votants instantanée |
| **Audit** | Journal append-only, chaîne de hachage SHA-256, export CSV, traçabilité complète |
| **PV officiel** | Procès-verbal HTML et PDF (Dompdf), consolidation des résultats, verrouillage post-validation |
| **Import** | CSV et XLSX — membres, présences, résolutions, procurations |

## Stack technique

| Couche | Technologie |
|--------|-------------|
| Backend | PHP 8.4 (no-framework), PostgreSQL 16, Redis 7 |
| Frontend | JavaScript vanilla, Web Components, CSS custom properties |
| Infra | Docker (nginx + php-fpm + supervisord), GitHub Actions CI |
| Tests | 2 300+ tests PHPUnit (90% couverture services), 177 tests E2E Playwright |

## Commandes

| Commande | Description |
|----------|-------------|
| `make dev` | Démarrer le stack Docker |
| `make test` | Lancer les tests PHPUnit |
| `make rebuild` | Rebuild + restart |
| `make logs` | Suivre les logs |
| `make status` | État du stack |
| `make` | Toutes les commandes |

> `bin/*.sh` utilisables directement : `./bin/dev.sh`, `./bin/test.sh`, `./bin/rebuild.sh`

## Documentation

| Guide | |
|-------|---|
| [Guide fonctionnel](docs/GUIDE_FONCTIONNEL.md) | Parcours utilisateur complet |
| [Guide opérateur](docs/UTILISATION_LIVE.md) | Conduite d'une séance en direct |
| [Démo guidée](docs/RECETTE_DEMO.md) | Scénario de test (~10 min) |
| [FAQ](docs/FAQ.md) | Questions fréquentes |
| [Déploiement Docker](docs/DEPLOIEMENT_DOCKER.md) | Production avec Docker Compose |
| [Déploiement Render](docs/DEPLOIEMENT_RENDER.md) | PaaS cloud (démo ou production) |

**Documentation technique :** [Architecture](docs/dev/ARCHITECTURE.md) · [API](docs/dev/API.md) · [Sécurité](docs/dev/SECURITY.md) · [Tests](docs/dev/TESTS.md) · [Web Components](docs/dev/WEB_COMPONENTS.md)

## Licence

Projet privé — tous droits réservés.
