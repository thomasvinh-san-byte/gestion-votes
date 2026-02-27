# =============================================================================
# AG-VOTE — Makefile
# =============================================================================
# Raccourcis pour les commandes courantes de développement et déploiement.
#
# Usage :
#   make              Affiche l'aide
#   make dev          Démarre l'environnement Docker dev
#   make test         Lance les tests PHPUnit
#   make logs         Suit les logs (Ctrl+C pour quitter)
#   make status       État complet du stack
#   make rebuild      Rebuild + restart
# =============================================================================

.DEFAULT_GOAL := help

.PHONY: help dev rebuild logs status test test-ci lint lint-fix check-prod shell db redis clean

# --- Aide -------------------------------------------------------------------

help: ## Afficher cette aide
	@echo ""
	@echo "  AG-VOTE — Commandes disponibles"
	@echo "  ════════════════════════════════"
	@echo ""
	@grep -E '^[a-zA-Z_-]+:.*?## .*$$' $(MAKEFILE_LIST) \
		| awk 'BEGIN {FS = ":.*?## "}; {printf "  \033[36m%-15s\033[0m %s\n", $$1, $$2}'
	@echo ""

# --- Développement ----------------------------------------------------------

dev: ## Démarrer l'environnement Docker dev (crée .env, healthcheck, identifiants)
	@./bin/dev.sh

rebuild: ## Rebuild complet (down + build + up + healthcheck)
	@./bin/rebuild.sh

rebuild-clean: ## Rebuild sans cache Docker (from scratch)
	@./bin/rebuild.sh --no-cache

# --- Monitoring -------------------------------------------------------------

logs: ## Suivre les logs de tous les services
	@./bin/logs.sh

logs-app: ## Suivre les logs de l'app uniquement
	@./bin/logs.sh app

logs-db: ## Suivre les logs PostgreSQL
	@./bin/logs.sh db

logs-err: ## Afficher uniquement les erreurs/warnings
	@./bin/logs.sh err

status: ## État complet du stack (conteneurs, health, DB, Redis)
	@./bin/status.sh

# --- Tests & qualité -------------------------------------------------------

test: ## Lancer les tests PHPUnit (rapide, sans coverage)
	@./bin/test.sh

test-ci: ## Lancer les tests en mode CI (coverage + strict)
	@./bin/test.sh ci

lint: ## Vérifier le code PHP (php-cs-fixer dry-run)
	@vendor/bin/php-cs-fixer fix --dry-run --diff

lint-fix: ## Corriger automatiquement le formatage PHP
	@vendor/bin/php-cs-fixer fix

check-prod: ## Vérifier la conformité production (.env)
	@./bin/check-prod-readiness.sh

# --- Accès direct -----------------------------------------------------------

shell: ## Ouvrir un shell dans le conteneur app
	@docker compose exec app sh

db: ## Ouvrir la console PostgreSQL
	@docker compose exec db psql -U vote_app -d vote_app

redis: ## Ouvrir la console Redis
	@docker compose exec redis redis-cli

# --- Nettoyage --------------------------------------------------------------

clean: ## Arrêter les services (conserve les données)
	@docker compose down --remove-orphans

reset: ## Tout supprimer (conteneurs + volumes + données)
	@echo "⚠  Ceci va supprimer toutes les données PostgreSQL."
	@echo "   Ctrl+C pour annuler, Enter pour continuer."
	@read _confirm
	@docker compose down -v --remove-orphans
	@echo "Reset complet. Relancer avec : make dev"
