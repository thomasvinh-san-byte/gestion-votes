# Documentation — AG-Vote

Bienvenue dans la documentation d'AG-Vote, votre application de gestion de séances de vote formelles.

---

## Documentation utilisateur

| Document | Description |
|----------|-------------|
| [FAQ.md](FAQ.md) | Questions fréquentes (général, rôles, votes, sécurité) |
| [UTILISATION_LIVE.md](UTILISATION_LIVE.md) | Guide complet pour conduire une séance en direct |
| [RECETTE_DEMO.md](RECETTE_DEMO.md) | Scénario de démonstration guidée (~10 min) |
| [DEPLOIEMENT_RENDER.md](DEPLOIEMENT_RENDER.md) | Deployer sur Render (demo et production) |

---

## Documentation technique

Pour les développeurs et administrateurs système, consultez le dossier [dev/](dev/).

### Sommaire technique

| Catégorie | Documents |
|-----------|-----------|
| **Architecture** | [ARCHITECTURE](dev/ARCHITECTURE.md), [WEB_COMPONENTS](dev/WEB_COMPONENTS.md), [SECURITY](dev/SECURITY.md) |
| **API** | [API](dev/API.md), [OpenAPI](api/openapi.yaml) |
| **Installation** | [INSTALLATION](dev/INSTALLATION.md), [TESTS](dev/TESTS.md), [MIGRATION](dev/MIGRATION.md), [RENDER](DEPLOIEMENT_RENDER.md) |
| **Conformité** | [CONFORMITE_CDC](dev/CONFORMITE_CDC.md), [AUDIT_RAPPORT](dev/AUDIT_RAPPORT.md), [ANALYTICS_ETHICS](dev/ANALYTICS_ETHICS.md) |
| **Plans** | [PLAN_UNDERDEVELOPED](dev/PLAN_UNDERDEVELOPED.md), [PLAN_MVC](dev/PLAN_MVC.md), [PLAN_EXPORTS](dev/PLAN_EXPORTS.md) |

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
- Consultez la [documentation technique](dev/) pour les questions avancées
