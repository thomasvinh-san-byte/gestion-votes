# AG-Vote

AG-Vote est une plateforme web de gestion de votes en assemblée générale. Elle accompagne l'organisateur à chaque étape : création de la séance, envoi des convocations, conduite des votes en direct, suivi du quorum, et génération du procès-verbal officiel.

L'application s'adresse aux associations, syndicats de copropriété et toute organisation tenue de respecter les règles de vote du droit français.

**Essayer la démo :** [ag-vote.onrender.com](https://ag-vote.onrender.com/)
Identifiants de test : `admin@ag-vote.local` / `Admin2026!`

## Démarrage rapide

```bash
git clone https://github.com/thomasvinh-san-byte/gestion-votes.git
cd gestion-votes
./bin/dev.sh
```

Le script prépare l'environnement, lance les conteneurs Docker et attend que le healthcheck confirme la disponibilité. Au premier lancement, le téléchargement des images prend environ trois minutes ; les lancements suivants démarrent en quelques secondes.

Une fois le stack prêt, ouvrir **http://localhost:8080** et se connecter avec les identifiants de test ci-dessus.

> L'unique prérequis est [Docker Desktop](https://www.docker.com/products/docker-desktop/) sur macOS ou Windows, ou Docker Engine sur Linux. Les guides d'installation détaillés sont disponibles pour [macOS](docs/INSTALL_MAC.md) et [Linux](docs/DOCKER_INSTALL.md).

## Fonctionnalités

**Gestion complète des séances.** Chaque séance suit un cycle de vie structuré (brouillon, planifiée, gelée, en cours, clôturée, validée, archivée) qui garantit qu'aucune étape réglementaire n'est oubliée.

**Vote électronique sécurisé.** Chaque votant reçoit un jeton unique à usage unique. Le système assure la protection contre le rejeu, propose le scrutin secret ou public, et permet le vote par QR code.

**Quorum en temps réel.** Les politiques de quorum sont configurables par séance (majorité des présents, un tiers, seuil personnalisé). Le calcul se met à jour automatiquement à chaque pointage de présence.

**Présences et procurations.** Le pointage s'effectue en direct depuis la console opérateur. Les procurations sont soumises à un plafond paramétrable et leur impact sur le quorum est recalculé instantanément.

**Synchronisation temps réel.** L'interface opérateur et les écrans de vote communiquent par Server-Sent Events (SSE) avec un mécanisme de fan-out Redis, ce qui permet à tous les participants de suivre l'avancement sans recharger la page.

**Traçabilité et audit.** Un journal d'audit append-only enregistre chaque action avec une chaîne de hachage SHA-256. L'intégralité du journal est exportable en CSV pour vérification indépendante.

**Procès-verbal officiel.** Le PV est généré en HTML et en PDF (via Dompdf). Il consolide les résultats de chaque résolution, les présences, les procurations et le détail des votes. Une fois validé par le président, le PV est verrouillé contre toute modification.

**Import de données.** Les membres, présences, résolutions et procurations peuvent être importés par fichier CSV ou XLSX, avec détection automatique du séparateur et des en-têtes en français ou en anglais.

## Stack technique

| Couche | Technologie |
|--------|-------------|
| Backend | PHP 8.4 sans framework, PostgreSQL 16, Redis 7 |
| Frontend | JavaScript vanilla, Web Components natifs, CSS custom properties |
| Infrastructure | Docker (nginx + php-fpm + supervisord), GitHub Actions (7 jobs CI) |
| Tests | Plus de 2 300 tests unitaires PHPUnit (90 % de couverture sur les services), 177 tests end-to-end Playwright |

## Commandes courantes

| Commande | Rôle |
|----------|------|
| `make dev` | Démarrer l'environnement Docker de développement |
| `make test` | Lancer la suite de tests PHPUnit |
| `make rebuild` | Reconstruire l'image et redémarrer les conteneurs |
| `make logs` | Afficher les logs en continu (Ctrl+C pour quitter) |
| `make status` | Afficher l'état complet du stack |
| `make` | Liste de toutes les commandes disponibles |

Les scripts shell du répertoire `bin/` sont également utilisables directement : `./bin/dev.sh`, `./bin/test.sh`, `./bin/rebuild.sh`.

## Documentation

### Guides utilisateur

| Document | Contenu |
|----------|---------|
| [Guide fonctionnel](docs/GUIDE_FONCTIONNEL.md) | Parcours utilisateur complet, de la création de séance à l'archivage |
| [Guide opérateur](docs/UTILISATION_LIVE.md) | Conduite d'une séance de vote en direct |
| [Démo guidée](docs/RECETTE_DEMO.md) | Scénario de test reproductible en une dizaine de minutes |
| [FAQ](docs/FAQ.md) | Réponses aux questions les plus fréquentes |

### Déploiement

| Document | Contenu |
|----------|---------|
| [Déploiement Docker](docs/DEPLOIEMENT_DOCKER.md) | Mise en production avec Docker Compose |
| [Déploiement Render](docs/DEPLOIEMENT_RENDER.md) | Hébergement sur Render (démo ou production) |

### Documentation technique

[Architecture](docs/dev/ARCHITECTURE.md) · [API REST](docs/dev/API.md) · [Sécurité](docs/dev/SECURITY.md) · [Tests](docs/dev/TESTS.md) · [Web Components](docs/dev/WEB_COMPONENTS.md)

## Licence

Projet privé. Tous droits réservés.
