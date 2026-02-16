# Ethique et confidentialité des analytics

## Principes fondamentaux

AG-Vote collecte des statistiques **agrégées** pour permettre aux administrateurs de suivre l'activité des séances de vote. Ces données ne permettent **jamais** d'identifier les choix de vote individuels.

### Ce que nous collectons

| Donnée | Usage | Identification possible |
|--------|-------|------------------------|
| Taux de participation par séance | Suivi de l'engagement | Non |
| Nombre de résolutions adoptées/rejetées | Statistiques globales | Non |
| Durée moyenne des votes | Optimisation des séances | Non |
| Distribution des temps de réponse | Analyse de performance | Non |
| Indicateurs de qualité (quorum, anomalies) | Audit et conformité | Non |

### Ce que nous ne collectons **jamais**

- Le détail des votes individuels dans les analytics
- L'historique de vote par membre
- Les corrélations entre un membre et son choix de vote
- Les métadonnées permettant d'inférer un vote (timing exact + membre)

---

## Protection du secret du vote

### Séparation des données

Les bulletins de vote sont stockés séparément des données d'analytics. L'API `/api/v1/analytics.php` ne retourne **que des données agrégées** :

```php
// Exemple de données retournées
{
  "meetings": 15,
  "motions": 42,
  "avg_participation_rate": 78.5,
  "motion_decisions": {
    "adopted": 35,
    "rejected": 7
  }
}
```

Il n'existe **aucun endpoint** permettant de récupérer les votes individuels via l'API analytics.

### Seuils de confidentialité

Pour éviter les inférences statistiques :

- Les graphiques ne sont affichés que si au moins **3 séances** sont disponibles
- Les taux par séance ne sont pas détaillés au niveau du membre
- Les temps de réponse sont regroupés en plages (< 30s, 30s-1min, 1-2min, etc.)

---

## Conformité réglementaire

### RGPD

AG-Vote respecte les principes du RGPD :

1. **Minimisation** : seules les données nécessaires sont collectées
2. **Finalité** : les analytics servent uniquement au suivi opérationnel
3. **Sécurité** : accès limité aux rôles `admin` et `operator`
4. **Transparence** : ce document explique ce qui est collecté

### Droit électoral

Le secret du vote est garanti par :

- L'impossibilité technique de lier un bulletin à un membre via l'API
- L'absence de logs nominatifs des choix de vote
- La destruction des tokens après usage (anti-rejeu)

---

## Accès aux données

| Rôle | Accès analytics | Accès votes individuels |
|------|-----------------|------------------------|
| Admin | Complet | Aucun |
| Operator | Complet | Aucun |
| Auditor | Lecture seule | Aucun |
| Viewer | Aucun | Aucun |
| Voter | Aucun | Son propre vote uniquement |

---

## Questions fréquentes

### Les analytics peuvent-ils révéler mon vote ?

**Non.** Les analytics ne contiennent que des agrégats (totaux, moyennes, pourcentages). Aucune donnée individuelle n'est exposée.

### Un administrateur peut-il voir comment j'ai voté ?

**Non.** Même un administrateur n'a pas accès aux votes individuels. Les bulletins sont stockés sans lien direct avec l'interface d'administration.

### Les données sont-elles partagées avec des tiers ?

**Non.** AG-Vote fonctionne en réseau local fermé. Aucune donnée n'est transmise à l'extérieur.

### Puis-je désactiver les analytics ?

Les analytics sont calculés à la demande et ne collectent pas de données supplémentaires. Ils agrègent uniquement les informations déjà présentes en base pour le fonctionnement normal de l'application.

---

## Contact

Pour toute question sur la confidentialité des données, contactez l'administrateur de votre instance AG-Vote.
