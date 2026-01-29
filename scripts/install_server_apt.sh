#!/bin/bash
set -euo pipefail

# Configuration
APT_PACKAGES=(vim neovim nala eza zoxide python3 python3-pip python3-venv python3-mutagen tmux)
PYTHON_PACKAGES=(tqdm pillow requests numpy pandas matplotlib click rich loguru python-dateutil pydantic mutagen yt-dlp)
VENV_DIR="${HOME}/.local/python_env"

# Vérifications initiales
command -v python3 >/dev/null || { echo "Python 3 requis"; exit 1; }
APT_CMD=$(command -v apt || command -v apt-get || { echo "APT requis"; exit 1; })
[ "$EUID" -eq 0 ] && { echo "Ne pas exécuter en root"; exit 1; }

# Installation APT
echo "Mise à jour des dépôts..."
sudo "$APT_CMD" update
echo "Installation des paquets système..."
sudo "$APT_CMD" install -y --no-install-recommends "${APT_PACKAGES[@]}"

# Environnement Python
echo "Création de l'environnement Python..."
[ -d "$VENV_DIR" ] && rm -rf "$VENV_DIR"
python3 -m venv "$VENV_DIR"
source "${VENV_DIR}/bin/activate"
pip install --upgrade pip setuptools wheel
pip install "${PYTHON_PACKAGES[@]}"
deactivate

# Nettoyage
sudo "$APT_CMD" autoremove -y 2>/dev/null || true
sudo "$APT_CMD" clean 2>/dev/null || true

# Résumé
echo "Installation terminée:"
echo "- Environnement: ${VENV_DIR}"
echo "- Paquets APT: ${#APT_PACKAGES[@]}"
echo "- Paquets Python: ${#PYTHON_PACKAGES[@]}"
echo "Pour activer: source ${VENV_DIR}/bin/activate"
