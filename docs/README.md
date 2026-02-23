# Documentation — AG-Vote

Bienvenue dans la documentation d'AG-Vote, votre application de gestion de séances de vote formelles.

---

## Documentation utilisateur

| Document | Description |
|----------|-------------|
| [GUIDE_FONCTIONNEL.md](GUIDE_FONCTIONNEL.md) | Guide complet de toutes les pages et fonctionnalités |
| [FAQ.md](FAQ.md) | Questions fréquentes (général, rôles, votes, sécurité) |
| [UTILISATION_LIVE.md](UTILISATION_LIVE.md) | Guide complet pour conduire une séance en direct |
| [RECETTE_DEMO.md](RECETTE_DEMO.md) | Scénario de démonstration guidée (~10 min) |
| [DEPLOIEMENT_DOCKER.md](DEPLOIEMENT_DOCKER.md) | Deploiement Docker local (dev et production) |
| [DEPLOIEMENT_RENDER.md](DEPLOIEMENT_RENDER.md) | Deployer sur Render (demo et production) |
| [ANALYTICS_ETHICS.md](ANALYTICS_ETHICS.md) | Analytics et conformité RGPD |

---

## Documentation technique

Pour les développeurs et administrateurs système, consultez le dossier [dev/](dev/).

### Sommaire technique

| Catégorie | Documents |
|-----------|-----------|
| **Architecture** | [ARCHITECTURE](dev/ARCHITECTURE.md), [WEB_COMPONENTS](dev/WEB_COMPONENTS.md), [SECURITY](dev/SECURITY.md) |
| **API** | [API](dev/API.md), [OpenAPI](api/openapi.yaml) |
| **Installation** | [INSTALLATION](dev/INSTALLATION.md), [TESTS](dev/TESTS.md), [MIGRATION](dev/MIGRATION.md), [DOCKER](DEPLOIEMENT_DOCKER.md), [RENDER](DEPLOIEMENT_RENDER.md) |
| **Spécifications** | [Cahier des charges](dev/cahier_des_charges.md), [AUDIT_FRONTEND](dev/AUDIT_FRONTEND.md), [ANALYTICS_ETHICS](dev/ANALYTICS_ETHICS.md) |

---

## Démarrage rapide

### Comment démarrer une séance ?

1. Connectez-vous avec vos identifiants sur `/login.html`
2. Allez dans **Séances** > **Nouvelle séance**
3. Ajoutez les résolutions et configurez les politiques
4. Passez la séance en mode **Live** pour démarrer

### Comment voter ?

1. Recevez votre lien de vote (QR code ou URL)
2. Accédez à la page de vote
3. Choisissez votre vote : Pour, Contre, Abstention
4. Confirmez — le vote est enregistré

### Besoin d'aide ?

- Consultez la [FAQ](FAQ.md) pour les questions courantes
- Consultez le [guide fonctionnel](GUIDE_FONCTIONNEL.md) pour le détail de chaque page
- Consultez la [documentation technique](dev/) pour les questions avancées
