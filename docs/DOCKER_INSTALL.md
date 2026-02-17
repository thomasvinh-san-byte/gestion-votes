# Installation Docker — AG-VOTE

Ce guide explique comment installer et lancer **AG-VOTE** via Docker sur une machine **Debian 12+ / Ubuntu 22.04+**.

> **Prérequis** : un accès `sudo` et une connexion internet.

---

## Table des matières

1. [Installer Docker](#1-installer-docker)
2. [Récupérer le projet](#2-récupérer-le-projet)
3. [Configurer l'environnement](#3-configurer-lenvironnement)
4. [Lancer l'application](#4-lancer-lapplication)
5. [Vérification](#5-vérification)
6. [Comptes de test](#6-comptes-de-test)
7. [Accès réseau (LAN)](#7-accès-réseau-lan)
8. [Commandes utiles](#8-commandes-utiles)
9. [Sauvegarde et restauration](#9-sauvegarde-et-restauration)
10. [Mise à jour](#10-mise-à-jour)
11. [Désinstallation](#11-désinstallation)
12. [Dépannage](#12-dépannage)

---

## 1. Installer Docker

### 1.1 Docker Engine + Docker Compose

```bash
# Mettre à jour les paquets
sudo apt update && sudo apt upgrade -y

# Installer les prérequis
sudo apt install -y ca-certificates curl gnupg

# Ajouter la clé GPG officielle Docker
sudo install -m 0755 -d /etc/apt/keyrings
curl -fsSL https://download.docker.com/linux/$(. /etc/os-release && echo "$ID")/gpg \
  | sudo gpg --dearmor -o /etc/apt/keyrings/docker.gpg
sudo chmod a+r /etc/apt/keyrings/docker.gpg

# Ajouter le dépôt Docker
echo \
  "deb [arch=$(dpkg --print-architecture) signed-by=/etc/apt/keyrings/docker.gpg] \
https://download.docker.com/linux/$(. /etc/os-release && echo "$ID") \
  $(. /etc/os-release && echo "$VERSION_CODENAME") stable" | \
sudo tee /etc/apt/sources.list.d/docker.list > /dev/null

# Installer Docker
sudo apt update
sudo apt install -y docker-ce docker-ce-cli containerd.io docker-compose-plugin
```

### 1.2 Autoriser votre utilisateur

```bash
sudo usermod -aG docker $USER
# Se déconnecter/reconnecter pour que le groupe prenne effet
newgrp docker
```

### 1.3 Vérifier l'installation

```bash
docker --version          # Docker Engine 24+
docker compose version    # Docker Compose v2+
```

---

## 2. Récupérer le projet

### Option A : Cloner le dépôt (développement)

```bash
sudo apt install -y git
git clone https://github.com/thomasvinh-san-byte/gestion-votes.git
cd gestion-votes
```

### Option B : Image pré-construite (production)

Si l'image est publiée sur GitHub Container Registry :

```bash
mkdir agvote && cd agvote

# Récupérer uniquement les fichiers de config nécessaires
curl -LO https://raw.githubusercontent.com/thomasvinh-san-byte/gestion-votes/main/docker-compose.yml
curl -LO https://raw.githubusercontent.com/thomasvinh-san-byte/gestion-votes/main/.env.example
```

Puis modifier `docker-compose.yml` pour remplacer `build: .` par :

```yaml
services:
  app:
    image: ghcr.io/thomasvinh-san-byte/gestion-votes:latest
```

---

## 3. Configurer l'environnement

```bash
cp .env.example .env
```

### Mode test (par défaut)

Le `.env.example` est configuré **sans authentification** (`APP_AUTH_ENABLED=0`, `CSRF_ENABLED=0`).
C'est le mode adapté pour tester l'application. Aucune modification n'est nécessaire pour un premier lancement :

```bash
cp .env.example .env
# Prêt — aucune autre modification requise pour le test
```

### Mode production

Pour un déploiement réel, ouvrir `.env` et adapter ces valeurs :

```bash
# Activer l'authentification et la protection CSRF
APP_AUTH_ENABLED=1
CSRF_ENABLED=1
RATE_LIMIT_ENABLED=1

# OBLIGATOIRE en production — générer un secret unique
APP_SECRET=$(openssl rand -hex 32)

# Mot de passe de la base de données — changer impérativement
DB_PASS=un_vrai_mot_de_passe_fort

# Environnement
APP_ENV=production
APP_DEBUG=0

# CORS — adapter à votre nom de domaine ou IP
CORS_ALLOWED_ORIGINS=http://votre-serveur:8080
```

Commande rapide pour générer le `.env` de production :

```bash
cp .env.example .env
sed -i "s/APP_AUTH_ENABLED=0/APP_AUTH_ENABLED=1/" .env
sed -i "s/CSRF_ENABLED=0/CSRF_ENABLED=1/" .env
sed -i "s/RATE_LIMIT_ENABLED=0/RATE_LIMIT_ENABLED=1/" .env
sed -i "s/APP_ENV=development/APP_ENV=production/" .env
sed -i "s/APP_DEBUG=1/APP_DEBUG=0/" .env
sed -i "s/APP_SECRET=.*/APP_SECRET=$(openssl rand -hex 32)/" .env
sed -i "s/DB_PASS=.*/DB_PASS=$(openssl rand -base64 24)/" .env
```

---

## 4. Lancer l'application

```bash
# Construire et démarrer (première fois)
docker compose up -d

# Suivre les logs de démarrage
docker compose logs -f app
```

Au premier lancement, l'entrypoint :
1. Attend que PostgreSQL soit prêt
2. Applique le schéma SQL + migrations
3. Charge les données de démo (si `LOAD_DEMO_DATA=1`)
4. Démarre Nginx + PHP-FPM + WebSocket

> Le premier build prend 2-5 minutes (compilation des extensions PHP).
> Les lancements suivants sont quasi-instantanés.

---

## 5. Vérification

```bash
# État des conteneurs
docker compose ps

# Résultat attendu :
# agvote-app   running (healthy)   0.0.0.0:8080->8080, 0.0.0.0:8081->8081
# agvote-db    running (healthy)   0.0.0.0:5433->5432
```

Tester dans le navigateur :

| URL | Description |
|-----|-------------|
| `http://localhost:8080` | Page d'accueil |
| `http://localhost:8080/login.html` | Connexion |

Tester en ligne de commande :

```bash
curl -s http://localhost:8080/ | head -5
```

---

## 6. Comptes de test

Créés automatiquement par les seeds (si `LOAD_DEMO_DATA=1`) :

| Rôle | Email | Mot de passe |
|------|-------|-------------|
| admin | `admin@ag-vote.local` | `Admin2026!` |
| operator | `operator@ag-vote.local` | `Operator2026!` |
| president | `president@ag-vote.local` | `President2026!` |
| votant | `votant@ag-vote.local` | `Votant2026!` |
| auditor | `auditor@ag-vote.local` | `Auditor2026!` |
| viewer | `viewer@ag-vote.local` | `Viewer2026!` |

> En production, désactivez les données de démo : `LOAD_DEMO_DATA=0` dans `.env`.

---

## 7. Accès réseau (LAN)

Par défaut, les ports écoutent sur `0.0.0.0` (toutes les interfaces).
Depuis un autre poste du réseau local :

```
http://192.168.1.50:8080/login.html
```

Penser à mettre à jour `CORS_ALLOWED_ORIGINS` dans `.env` :

```bash
CORS_ALLOWED_ORIGINS=http://localhost:8080,http://192.168.1.50:8080
```

Puis redémarrer :

```bash
docker compose restart app
```

### Firewall (optionnel)

```bash
sudo ufw allow 8080/tcp comment "AG-VOTE HTTP"
sudo ufw allow 8081/tcp comment "AG-VOTE WebSocket"
```

---

## 8. Commandes utiles

```bash
# Démarrer
docker compose up -d

# Arrêter (conserve les données)
docker compose down

# Redémarrer l'application seule
docker compose restart app

# Logs en temps réel
docker compose logs -f app
docker compose logs -f db

# Shell dans le conteneur app
docker compose exec app sh

# Console PostgreSQL
docker compose exec db psql -U vote_app -d vote_app

# Reconstruire après une mise à jour
docker compose up -d --build

# Statut des processus supervisord (dans le conteneur)
docker compose exec app supervisorctl status
```

---

## 9. Sauvegarde et restauration

### 9.1 Sauvegarder la base de données

```bash
# Dump SQL complet
docker compose exec db pg_dump -U vote_app -d vote_app \
  > backup_$(date +%Y%m%d_%H%M%S).sql

# Dump compressé
docker compose exec db pg_dump -U vote_app -d vote_app -Fc \
  > backup_$(date +%Y%m%d_%H%M%S).dump
```

### 9.2 Restaurer

```bash
# Depuis un dump SQL
docker compose exec -T db psql -U vote_app -d vote_app < backup_20260217_120000.sql

# Depuis un dump compressé
docker compose exec -T db pg_restore -U vote_app -d vote_app --clean < backup_20260217_120000.dump
```

### 9.3 Automatiser (cron)

```bash
# Ajouter au crontab : sauvegarde quotidienne à 2h du matin
echo "0 2 * * * cd /chemin/vers/gestion-votes && docker compose exec -T db pg_dump -U vote_app -d vote_app -Fc > /backups/agvote_\$(date +\%Y\%m\%d).dump" \
  | crontab -
```

---

## 10. Mise à jour

### Depuis le dépôt Git

```bash
cd gestion-votes
git pull origin main
docker compose up -d --build
```

### Depuis l'image ghcr.io

```bash
docker compose pull
docker compose up -d
```

> L'entrypoint applique automatiquement les nouvelles migrations SQL au redémarrage.

---

## 11. Désinstallation

```bash
# Arrêter et supprimer les conteneurs
docker compose down

# Supprimer aussi les volumes (SUPPRIME LES DONNÉES)
docker compose down -v

# Supprimer l'image locale
docker rmi agvote-app
```

---

## 12. Dépannage

### Le conteneur `app` ne démarre pas

```bash
docker compose logs app
```

Causes fréquentes :
- `.env` manquant → `cp .env.example .env`
- Port 8080 déjà utilisé → changer `APP_PORT` dans `.env`

### "Connection refused" sur PostgreSQL

```bash
# Vérifier que le conteneur db est healthy
docker compose ps db

# Tester la connexion
docker compose exec db pg_isready -U vote_app
```

### "Permission denied" sur Docker

```bash
sudo usermod -aG docker $USER
newgrp docker
```

### Reconstruire de zéro (tout supprimer)

```bash
docker compose down -v
docker compose up -d --build
```

### Logs PHP détaillés

```bash
# Activer le debug temporairement
docker compose exec app sh -c 'echo "display_errors=On" >> /usr/local/etc/php/conf.d/99-custom.ini'
docker compose restart app
```

---

## Architecture Docker

```
┌─────────────────────────────────────────────────────┐
│                   Docker Host                       │
│                                                     │
│  ┌─────────────────────────────────────────────┐    │
│  │           agvote-app (Alpine)                │    │
│  │                                              │    │
│  │  supervisord                                 │    │
│  │   ├── nginx        (:8080) ───► HTTP         │    │
│  │   ├── php-fpm      (:9000)   (interne)       │    │
│  │   └── websocket    (:8081) ───► WS           │    │
│  │                                              │    │
│  │  Volume: /tmp/ag-vote (logs, cache, fonts)   │    │
│  └──────────────┬───────────────────────────────┘    │
│                 │ PDO (pgsql:host=db:5432)           │
│  ┌──────────────▼───────────────────────────────┐    │
│  │           agvote-db (PostgreSQL 16)           │    │
│  │                                              │    │
│  │  Volume: pgdata (/var/lib/postgresql/data)   │    │
│  └──────────────────────────────────────────────┘    │
└─────────────────────────────────────────────────────┘
```
