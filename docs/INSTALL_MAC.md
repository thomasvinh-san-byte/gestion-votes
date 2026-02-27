# Installation sur macOS — AG-VOTE

Guide pas-à-pas pour installer et lancer **AG-VOTE** sur un Mac (Intel ou Apple Silicon).

> **Temps estimé** : 10–15 minutes (première fois).

---

## Table des matières

1. [Prérequis](#1-prérequis)
2. [Installer Docker Desktop](#2-installer-docker-desktop)
3. [Récupérer le projet](#3-récupérer-le-projet)
4. [Configurer et lancer](#4-configurer-et-lancer)
5. [Ouvrir dans le navigateur](#5-ouvrir-dans-le-navigateur)
6. [Comptes de test](#6-comptes-de-test)
7. [Commandes du quotidien](#7-commandes-du-quotidien)
8. [Accès depuis un autre appareil (LAN)](#8-accès-depuis-un-autre-appareil-lan)
9. [Mise à jour](#9-mise-à-jour)
10. [Arrêt et désinstallation](#10-arrêt-et-désinstallation)
11. [Dépannage](#11-dépannage)

---

## 1. Prérequis

- macOS 13 (Ventura) ou plus récent
- 4 Go de RAM disponibles
- 2 Go d'espace disque
- Un compte GitHub (pour cloner le dépôt)

---

## 2. Installer Docker Desktop

### Option A : Téléchargement direct (recommandé)

1. Aller sur **https://www.docker.com/products/docker-desktop/**
2. Cliquer sur **Download for Mac**
   - **Apple Silicon** (M1/M2/M3/M4) → choisir *Apple Chip*
   - **Intel** → choisir *Intel Chip*
3. Ouvrir le fichier `.dmg` téléchargé
4. Glisser **Docker** dans le dossier **Applications**
5. Lancer **Docker** depuis le Launchpad ou le dossier Applications
6. Accepter les conditions d'utilisation
7. Attendre que l'icône Docker (baleine) dans la barre de menus indique **"Docker Desktop is running"**

### Option B : Via Homebrew

Si vous avez [Homebrew](https://brew.sh) installé :

```bash
brew install --cask docker
```

Puis lancer Docker Desktop depuis Applications.

### Vérifier l'installation

Ouvrir **Terminal** (Applications → Utilitaires → Terminal) et taper :

```bash
docker --version
docker compose version
```

Résultat attendu :
```
Docker version 27.x.x, build xxxxx
Docker Compose version v2.x.x
```

> Si `docker` n'est pas reconnu, fermez et rouvrez le Terminal après avoir lancé Docker Desktop.

---

## 3. Récupérer le projet

### Option A : Cloner avec Git (recommandé)

Git est pré-installé sur macOS. Ouvrez le Terminal :

```bash
# Se placer dans le dossier souhaité (ex: Bureau)
cd ~/Desktop

# Cloner le dépôt
git clone https://github.com/thomasvinh-san-byte/gestion-votes.git

# Entrer dans le dossier
cd gestion-votes
```

> **Première utilisation de Git sur Mac ?** macOS vous proposera d'installer les *Command Line Tools*. Acceptez et relancez la commande.

### Option B : Télécharger le ZIP

1. Aller sur https://github.com/thomasvinh-san-byte/gestion-votes
2. Cliquer sur le bouton vert **Code** → **Download ZIP**
3. Dézipper le fichier téléchargé
4. Ouvrir le Terminal et naviguer vers le dossier :

```bash
cd ~/Downloads/gestion-votes-main
```

---

## 4. Configurer et lancer

### 4.1 Démarrage rapide (recommandé)

```bash
./bin/dev.sh
```

Ce script fait tout automatiquement :
1. Crée `.env` à partir de `.env.example` (si absent)
2. Télécharge les images Docker et construit l'image AG-VOTE
3. Crée la base de données et charge les données de démo
4. Attend que le healthcheck passe
5. Affiche l'URL et les identifiants de test

> **Premier lancement : 3–5 minutes** (téléchargement des images + compilation).
> Les lancements suivants sont quasi-instantanés (~5 secondes).

### 4.2 Démarrage manuel (alternatif)

```bash
cp .env.example .env
docker compose up -d
docker compose logs -f app
```

Attendez de voir :
```
[INFO] AG-VOTE ready — http://0.0.0.0:8080
```

Appuyez sur `Ctrl+C` pour quitter les logs (l'application continue de tourner).

---

## 5. Ouvrir dans le navigateur

Ouvrez **Safari**, **Chrome** ou **Firefox** et allez à :

### http://localhost:8080

Vous verrez la page d'accueil d'AG-VOTE.

| URL | Page |
|-----|------|
| http://localhost:8080 | Accueil |
| http://localhost:8080/login.html | Connexion |
| http://localhost:8080/help.htmx.html | Aide & FAQ |
| http://localhost:8080/docs.htmx.html | Documentation |

---

## 6. Comptes de test

Les données de démo sont chargées automatiquement. Voici les comptes disponibles :

| Rôle | Email | Mot de passe |
|------|-------|-------------|
| Administrateur | `admin@ag-vote.local` | `Admin2026!` |
| Opérateur | `operator@ag-vote.local` | `Operator2026!` |
| Président | `president@ag-vote.local` | `President2026!` |
| Votant | `votant@ag-vote.local` | `Votant2026!` |
| Auditeur | `auditor@ag-vote.local` | `Auditor2026!` |
| Observateur | `viewer@ag-vote.local` | `Viewer2026!` |

> **Mode test** : l'authentification est désactivée par défaut. Vous pouvez naviguer sans vous connecter. Pour activer l'authentification, modifiez `APP_AUTH_ENABLED=1` dans `.env` et relancez avec `docker compose restart app`.

---

## 7. Commandes du quotidien

Toutes les commandes sont à exécuter dans le Terminal, depuis le dossier du projet.

### Via Make (recommandé)

```bash
make dev             # Démarrage complet
make test            # Lancer les tests
make logs            # Suivre les logs (Ctrl+C pour quitter)
make logs-err        # Afficher uniquement les erreurs
make status          # État complet du stack
make rebuild         # Rebuild + restart
make shell           # Shell dans le conteneur app
make db              # Console PostgreSQL
make clean           # Arrêter les services
make                 # Afficher toutes les commandes
```

### Commandes Docker directes

```bash
docker compose up -d           # Démarrer
docker compose down            # Arrêter (données conservées)
docker compose restart app     # Redémarrer l'app seule
docker compose logs -f app     # Logs en direct
docker compose ps              # État des conteneurs
```

---

## 8. Accès depuis un autre appareil (LAN)

Pour accéder à AG-VOTE depuis un téléphone, une tablette ou un autre ordinateur sur le même réseau Wi-Fi :

### 8.1 Trouver l'IP de votre Mac

```bash
ipconfig getifaddr en0
```

Résultat : par exemple `192.168.1.42`

### 8.2 Ouvrir depuis l'autre appareil

Sur le navigateur de l'autre appareil :

```
http://192.168.1.42:8080
```

### 8.3 Mettre à jour les CORS (si nécessaire)

Si vous obtenez des erreurs CORS, ajoutez l'IP dans `.env` :

```bash
CORS_ALLOWED_ORIGINS=http://localhost:8080,http://192.168.1.42:8080
```

Puis redémarrez :

```bash
docker compose restart app
```

### 8.4 Firewall macOS

Si la connexion est bloquée, macOS affichera une popup **"Voulez-vous autoriser les connexions entrantes ?"** → cliquez **Autoriser**.

Si vous avez manqué la popup :
- Préférences Système → Réseau → Pare-feu → Options
- Autoriser `com.docker.backend`

---

## 9. Mise à jour

```bash
cd ~/Desktop/gestion-votes   # ou votre dossier

# Récupérer les dernières modifications
git pull origin main

# Reconstruire et relancer
make rebuild
# ou : docker compose up -d --build
```

Les migrations SQL sont appliquées automatiquement au redémarrage.

---

## 10. Arrêt et désinstallation

### Arrêter l'application

```bash
docker compose down
```

### Supprimer les données (reset complet)

```bash
docker compose down -v
```

### Désinstaller Docker Desktop

1. Quitter Docker Desktop (clic droit sur l'icône baleine → Quit)
2. Aller dans Applications → glisser Docker vers la Corbeille
3. Vider la Corbeille

---

## 11. Dépannage

### "Cannot connect to the Docker daemon"

Docker Desktop n'est pas lancé. Ouvrez-le depuis Applications → Docker.

### "Port 8080 already in use"

Un autre service utilise le port. Changez-le dans `.env` :

```bash
APP_PORT=9090
```

Puis relancez :

```bash
docker compose down && docker compose up -d
```

L'application sera sur http://localhost:9090.

### "Error response from daemon: pull access denied"

Le build local est nécessaire. Vérifiez que vous êtes dans le bon dossier :

```bash
ls docker-compose.yml    # doit afficher le fichier
docker compose up -d --build
```

### Le build est lent sur Apple Silicon

C'est normal au premier build (émulation x86 pour certaines dépendances). Les builds suivants utilisent le cache et sont rapides.

### Tout reconstruire de zéro

```bash
make reset
./bin/rebuild.sh --no-cache
# ou manuellement :
docker compose down -v
docker compose build --no-cache
docker compose up -d
```

### Voir les logs PHP détaillés

```bash
docker compose logs app 2>&1 | grep -i error
```

---

## Résumé express

```bash
# Installation complète en 3 commandes :
git clone https://github.com/thomasvinh-san-byte/gestion-votes.git
cd gestion-votes
./bin/dev.sh

# Puis ouvrir : http://localhost:8080
```
