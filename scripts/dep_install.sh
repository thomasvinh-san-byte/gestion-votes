#!/usr/bin/env bash
set -euo pipefail

# ============================================================
#  Installation des paquets Ubuntu nécessaires au projet
#  (sans création de base, ni import SQL, ni config)
#
#  Usage :
#    chmod +x scripts/install_ubuntu_packages.sh
#    sudo ./scripts/install_ubuntu_packages.sh
# ============================================================

if [[ $EUID -ne 0 ]]; then
  echo "Ce script doit être lancé avec sudo ou en root."
  exit 1
fi

echo "== Mise à jour des paquets APT =="
apt-get update -y

echo "== Installation des outils de base =="
apt-get install -y \
  git \
  unzip \
  curl

echo "== Installation de PHP et des extensions nécessaires =="
apt-get install -y \
  php \
  php-cli \
  php-pgsql \
  php-mbstring \
  php-xml \
  php-zip \
  php-gd \
  php-curl

echo "== Installation de PostgreSQL =="
apt-get install -y \
  postgresql \
  postgresql-contrib

echo "== Installation de Composer (via apt) =="
if ! command -v composer >/dev/null 2>&1; then
  apt-get install -y composer
else
  echo "Composer est déjà installé."
fi

echo "===================================================="
echo "  Paquets Ubuntu installés pour le projet."
echo
echo "  Tu peux maintenant :"
echo "    - configurer PostgreSQL (création de la DB, etc.)"
echo "    - lancer composer dans ton projet"
echo "    - démarrer le serveur PHP :"
echo "        php -S 0.0.0.0:8081 -t public"
echo "===================================================="

