# Installation & démarrage

Ce document décrit **pas à pas** comment installer et lancer l’application **Gestion des votes** sur une machine Linux (poste de développement ou machine virtuelle).

À l’issue de ces étapes, tu dois disposer :

* d’une base PostgreSQL initialisée (`vote_app`),
* d’une application PHP capable de s’y connecter,
* d’une interface opérateur accessible via un navigateur web.

> Ce guide est volontairement **linéaire, explicite et reproductible**.
> Il ne suppose aucune connaissance préalable du projet.

---

## Périmètre de ce document

Ce document couvre uniquement :

* l’installation technique,
* la configuration minimale,
* le démarrage de l’application.

L’utilisation en séance, la démonstration et la conformité CDC sont décrites dans :

* `UTILISATION_LIVE.md`
* `RECETTE_DEMO.md`
* `CONFORMITE_CDC.md`

---

## Prérequis système

### 1.1 Logiciels requis

* **PHP ≥ 8.1**

  * paquets : `php`, `php-cli`, `php-pgsql`
* **PostgreSQL ≥ 13**
* (optionnel) **Git** pour cloner le dépôt

Installation type sur Debian / Ubuntu :

```bash
sudo apt update
sudo apt install -y php php-cli php-pgsql postgresql git
```

Vérification rapide :

```bash
php -v
psql --version
```

---

### 1.2 Arborescence du projet

On suppose que le dépôt est cloné dans :

```text
~/gestion_votes_php/
```

Structure attendue :

```text
gestion_votes_php/
  app/        # logique métier, services
  public/     # point d’entrée web (HTML, HTMX, API)
  database/   # scripts SQL
  config/     # configuration PHP
```

---

## Configuration de PostgreSQL

L’application utilise PostgreSQL comme **source de vérité unique**.
Nous allons :

1. créer un rôle applicatif (`ca_app`),
2. créer la base (`vote_app`),
3. appliquer le schéma SQL.

Toutes les commandes suivantes s’exécutent **sur la machine où tourne PostgreSQL**.

---

### 2.1 Création du rôle applicatif

Connexion en superutilisateur PostgreSQL (généralement `postgres`) :

```bash
sudo -u postgres psql
```

Création du rôle applicatif (idempotent) :

```sql
DO $$
BEGIN
  IF NOT EXISTS (SELECT 1 FROM pg_roles WHERE rolname = 'ca_app') THEN
    CREATE ROLE ca_app LOGIN PASSWORD 'CHANGE_ME_STRONG';
  END IF;
END $$;
```

Puis quitter :

```sql
\q
```

> Le mot de passe `CHANGE_ME_STRONG` est acceptable en **développement**.
> En environnement sensible, choisis un mot de passe robuste et reporte-le
> dans la configuration PHP (voir plus bas).

---

### 2.2 Création de la base de données

Toujours en tant que `postgres` :

```bash
sudo -u postgres psql
```

```sql
CREATE DATABASE vote_app OWNER ca_app;
\q
```

La base est maintenant :

* vide,
* détenue par `ca_app`,
* prête à recevoir le schéma.

---

## Initialisation du schéma SQL

Le schéma est fourni sous forme de script SQL versionné dans le dépôt.

### 3.1 Application du script

Depuis le répertoire du projet :

```bash
cd ~/gestion_votes_php
```

Méthode recommandée (évite les problèmes de droits) :

```bash
cp database/setup_bdd_postgre.sql /tmp/setup_bdd_postgre.sql
sudo chown postgres:postgres /tmp/setup_bdd_postgre.sql
sudo -u postgres psql -d vote_app -f /tmp/setup_bdd_postgre.sql
```

### 3.2 Contenu du script

Le script :

* crée les tables métier (meetings, motions, ballots, etc.),
* installe les extensions nécessaires (`pgcrypto`, `citext`),
* crée les fonctions et triggers d’audit,
* met en place les garde-fous post-validation,
* est **idempotent** (peut être rejoué sans casser l’existant).

---

## Configuration de l’application PHP

### 4.1 Fichier de configuration

Le fichier principal est :

```text
config/config.php
```

Configuration par défaut (extrait) :

```php
DB_DATABASE = vote_app
DB_USERNAME = ca_app
DB_PASSWORD = CHANGE_ME_STRONG
```

Ces valeurs conviennent si :

* PostgreSQL est sur la même machine,
* la base s’appelle `vote_app`,
* le rôle est `ca_app`.

Sinon, adapte soit :

* les variables d’environnement,
* soit les valeurs par défaut du fichier.

---

### 4.2 Test de connexion manuelle

Avant de lancer PHP, teste la connexion :

```bash
psql -h 127.0.0.1 -U ca_app -d vote_app
```

Si tu obtiens le prompt :

```text
vote_app=>
```

la configuration est correcte.

Commande utile :

```sql
\dt
```

pour vérifier la présence des tables.

---

## Lancer l’application (mode développement)

Depuis la racine du projet :

```bash
cd ~/gestion_votes_php
php -S 0.0.0.0:8000 -t public
```

* `0.0.0.0` : accessible depuis le réseau local
* `8000` : port libre (modifiable)
* `public/` : racine web

---

## Accès aux interfaces

Dans un navigateur :

* **Opérateur**
  `http://<IP>:8000/operator.htmx.html`

* **Président**
  `http://<IP>:8000/president.htmx.html`

* **Auditor / contrôle**
  `http://<IP>:8000/trust.htmx.html`

Exemple local :

```
http://127.0.0.1:8000/operator.htmx.html
```

---

## Dépannage rapide

### Connexion DB impossible

Vérifier :

```bash
sudo -u postgres psql -l | grep vote_app
sudo -u postgres psql -c "\du ca_app"
```

Puis :

```bash
psql -h 127.0.0.1 -U ca_app -d vote_app
```

---

### Erreur “relation does not exist”

👉 Le schéma n’a pas été appliqué sur la bonne base.

Rejouer :

```bash
sudo -u postgres psql -d vote_app -f /tmp/setup_bdd_postgre.sql
```

---

## 8️⃣ Étape suivante

Une fois l’application lancée :

* pour **utiliser le produit en séance** → `UTILISATION_LIVE.md`
* pour **tester rapidement** → `RECETTE_DEMO.md`
* pour **le cadre juridique** → `CONFORMITE_CDC.md`

---

### ✔️ Résumé

En résumé :

1. Installer PHP + PostgreSQL
2. Créer le rôle `ca_app` et la base `vote_app`
3. Appliquer le schéma SQL
4. Lancer le serveur PHP
5. Ouvrir l’interface opérateur

👉 L’environnement est prêt.
