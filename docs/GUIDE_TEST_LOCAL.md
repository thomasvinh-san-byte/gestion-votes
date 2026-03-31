# Guide de test local — AG-Vote

Ce document décrit la **marche à suivre complète** pour lancer, tester et valider le projet AG-Vote en local via Docker. Il s'adresse aux développeurs et testeurs souhaitant vérifier le bon fonctionnement de l'application avant une mise en production ou une démonstration.

---

## Table des matières

1. [Pré-requis](#1-pré-requis)
2. [Lancement de l'environnement](#2-lancement-de-lenvironnement)
3. [Vérifier que tout fonctionne](#3-vérifier-que-tout-fonctionne)
4. [Comptes de test](#4-comptes-de-test)
5. [Commandes utiles pendant les tests](#5-commandes-utiles-pendant-les-tests)
6. [Scénario de test fonctionnel](#6-scénario-de-test-fonctionnel)
7. [Tests automatisés](#7-tests-automatisés)
8. [Points de contrôle à remonter](#8-points-de-contrôle-à-remonter)
9. [Dépannage et reset](#9-dépannage-et-reset)
10. [Arrêt propre](#10-arrêt-propre)

---

## 1. Pré-requis

- **Docker** et **Docker Compose** installés (voir `docs/DOCKER_INSTALL.md` pour Linux, `docs/INSTALL_MAC.md` pour macOS)
- Port **8080** disponible sur la machine locale
- Un terminal Bash

---

## 2. Lancement de l'environnement

### Méthode rapide (recommandée)

```bash
cd gestion-votes
./bin/dev.sh
```

Ce script :
- copie `.env.example` vers `.env` si le fichier n'existe pas,
- lance les 3 services Docker (app, db, redis),
- attend que le healthcheck passe (jusqu'à 90 s),
- affiche l'URL et les identifiants de connexion.

### Alternative via Make

```bash
make dev
```

### Services démarrés

| Service | Description | Port local |
|---------|-------------|------------|
| **app** | Nginx + PHP-FPM 8.4 | `8080` |
| **db** | PostgreSQL 16.8 | `5433` |
| **redis** | Redis 7.4 | `6380` |

L'application est accessible à l'adresse : **http://localhost:8080**

---

## 3. Vérifier que tout fonctionne

```bash
# État des conteneurs, health, ports
make status

# Healthcheck API
curl http://localhost:8080/api/v1/health.php
```

Résultat attendu du healthcheck : un JSON contenant `"ok": true`.

---

## 4. Comptes de test

Les données de démo sont chargées automatiquement (`LOAD_SEED_DATA=1`).

### Utilisateurs système

| Rôle | Email | Mot de passe |
|------|-------|-------------|
| Admin | `admin@ag-vote.local` | `Admin2026!` |
| Opérateur | `operator@ag-vote.local` | `Operator2026!` |
| Président | `president@ag-vote.local` | `President2026!` |
| Auditeur | `auditor@ag-vote.local` | `Auditor2026!` |

### Données de démo disponibles

- 1 assemblée générale en cours (statut **LIVE**)
- 1 assemblée en brouillon (dans 14 jours)
- 12 membres avec poids de vote varies (35 a 120)
- 5 résolutions à différents statuts
- 5 politiques de quorum + 4 politiques de vote

> **Note** : en mode démo (`APP_ENV=demo`), l'authentification, le CSRF et le rate limiting sont **désactivés** pour faciliter la navigation.

---

## 5. Commandes utiles pendant les tests

### Monitoring

```bash
make logs          # Suivre les logs de tous les services
make logs-app      # Logs de l'application uniquement
make logs-db       # Logs PostgreSQL
make logs-err      # Uniquement les erreurs et warnings
make status        # État complet du stack
```

### Accès direct

```bash
make shell         # Shell interactif dans le conteneur app
make db            # Console PostgreSQL (psql)
make redis         # Console Redis
```

### Tests et qualité

```bash
make test          # Tests PHPUnit (rapide)
make test-ci       # Tests avec couverture de code (mode CI)
make lint          # Vérification du formatage PHP
make lint-fix      # Correction automatique du formatage
make check-prod    # Vérification de conformité production
```

---

## 6. Scénario de test fonctionnel

Suivre le parcours complet décrit dans `docs/RECETTE_DEMO.md` (environ 10 minutes). Voici un résumé des étapes clés :

### 6.1 Préparation

1. Ouvrir les interfaces dans le navigateur :
   - `/operator/{uuid}` : interface operateur
   - `/hub` : interface hub/president
   - `/audit` : interface auditeur
2. Vérifier les présences des membres
3. Saisir au moins une procuration

### 6.2 Vote électronique

1. Ouvrir une résolution depuis l'interface Opérateur
2. Voter depuis 2 navigateurs / appareils différents via `/vote.php?token=…`
3. Vérifier la mise à jour en temps réel côté Opérateur et Président
4. Clôturer la résolution et vérifier :
   - le calcul de quorum,
   - le calcul de majorite,
   - le résultat juridique (ADOPTÉE / REJETÉE / NON VALABLE)

### 6.3 Incidents et mode dégradé

1. Déclarer un incident (type réseau ou matériel)
2. Saisir un vote manuel avec justification
3. Vérifier que les incidents apparaissent dans l'audit

### 6.4 Validation et verrouillage

1. Clôturer toutes les résolutions ouvertes
2. Valider la séance depuis l'interface Président
3. **Tester le verrouillage** : tenter de modifier une présence ou d'ouvrir une résolution → refus attendu (HTTP 409)

### 6.5 Livrables

1. Vérifier le procès-verbal (participants, résultats pondérés, incidents, règles)
2. Télécharger les exports CSV (présences, votes, résultats, audit)

---

## 7. Tests automatisés

### Tests unitaires (PHPUnit)

```bash
make test
```

### Smoke test A-Z (parcours complet API)

```bash
bash scripts/smoke_test.sh
```

Ce script teste automatiquement :
- ping et accessibilité de la base,
- login des 4 comptes (admin, operator, president, auditor),
- listing des réunions, motions, membres,
- transitions de séance : scheduled → frozen → live,
- enregistrement des présences (6 membres),
- ouverture d'une motion, génération de tokens, votes, clôture,
- transitions finales : live → closed → validated.

Résultat : `exit 0` si tout passe, `exit 1` au premier échec.

### Tests d'intégration API

```bash
php scripts/test_api_integration.php
```

---

## 8. Points de contrôle à remonter

Lors des tests, voici les éléments à vérifier et documenter en cas d'anomalie.

### Fonctionnel

| Point de contrôle | Attendu |
|-------------------|---------|
| Connexion / déconnexion par rôle | Accès conforme aux permissions |
| Cycle de vie d'une AG | scheduled → frozen → live → closed → validated |
| Lancement et déroulement d'un vote | Tokens générés, votes enregistrés, résultat calculé |
| Quorum et majorité pondérée | Calculs corrects selon les poids de vote |
| Procurations | Impact sur le quorum et la majorite, visible en audit |
| Verrouillage post-validation | Toute modification refusée (HTTP 409) |
| Exports PDF / CSV | Fichiers générés, contenu cohérent |

### Technique

| Point de contrôle | Comment vérifier |
|-------------------|-----------------|
| Healthcheck OK | `curl http://localhost:8080/api/v1/health.php` |
| Aucune erreur dans les logs | `make logs-err` (aucune ligne ne doit apparaître) |
| Temps de réponse | Les pages chargent en < 2 s |
| Temps réel (SSE) | Les votes et présences se mettent à jour sans recharger |
| Tests PHPUnit | `make test` — tous les tests passent |
| Smoke test | `bash scripts/smoke_test.sh` — 0 échec |

### Sécurité (test optionnel avec auth activée)

Pour tester avec la sécurité activée, modifier `.env` :

```ini
APP_AUTH_ENABLED=1
CSRF_ENABLED=1
RATE_LIMIT_ENABLED=1
```

Puis relancer : `make rebuild`

| Point de contrôle | Attendu |
|-------------------|---------|
| Accès sans session | Redirection vers login |
| CSRF | Requêtes POST sans token → rejetées |
| Rate limiting | Requêtes excessives → HTTP 429 |

### Format de remontée d'anomalie

Pour chaque problème trouvé, documenter :

```
Titre      : [description courte]
Sévérité   : Bloquant / Majeur / Mineur
Étape      : [étape du scénario où le problème survient]
Attendu    : [comportement attendu]
Obtenu     : [comportement observé]
Logs       : [extrait pertinent de `make logs-err`]
Navigateur : [navigateur et version utilisés]
```

---

## 9. Dépannage et reset

### Rebuild des conteneurs

```bash
make rebuild             # Rebuild complet
make rebuild-clean       # Rebuild sans cache Docker (from scratch)
```

### Réinitialisation de la base de données

```bash
make shell
bash database/setup.sh --reset    # Supprime et recrée la base avec les données de démo
```

Options disponibles pour `database/setup.sh` :

| Option | Effet |
|--------|-------|
| *(aucune)* | Crée la base avec les données de démo |
| `--no-demo` | Crée la base sans données de démo |
| `--seed` | Recharge uniquement les seeds (sans recréer la structure) |
| `--reset` | Supprime tout et recrée de zéro |

### Reset complet (tout supprimer)

```bash
make reset     # Supprime conteneurs + volumes + données
make dev       # Relance tout
```

---

## 10. Arrêt propre

```bash
make clean     # Stoppe les services, conserve les données
```

Pour relancer plus tard : `make dev`

---

## Références

- [Scénario de recette complet](RECETTE_DEMO.md) — Parcours démo chronométré (10 min)
- [Guide d'utilisation live](UTILISATION_LIVE.md) — Guide opérateur en séance
- [Guide fonctionnel](GUIDE_FONCTIONNEL.md) — Présentation fonctionnelle de l'application
- [FAQ](FAQ.md) — Questions fréquentes
- [Architecture technique](dev/ARCHITECTURE.md) — Architecture du système
