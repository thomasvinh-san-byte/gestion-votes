# Analytics - Usage éthique et limitations

## Objectif des tableaux de bord

Les analytics d'AG-Vote sont conçus pour **améliorer la gestion des séances**, pas pour surveiller les votants individuels.

---

## Ce que les analytics montrent

| Métrique | Données | Usage légitime |
|----------|---------|----------------|
| Taux de participation | % présents (agrégé) | Identifier les problèmes d'engagement |
| Résolutions adoptées | Comptage final public | Mesurer l'efficacité de la gouvernance |
| Durée des votes | Temps moyen | Optimiser la durée des séances |
| Top votants | Fréquence de participation | Reconnaître l'engagement (optionnel) |

---

## Ce que les analytics ne montrent PAS

- **Le contenu des votes individuels** : Aucune donnée sur qui a voté pour/contre
- **L'ordre des votes** : Les horodatages individuels ne sont pas exposés
- **Les corrélations vote/votant** : Aucun croisement n'est possible via l'API

---

## Risques potentiels et atténuations

### 1. Pression sociale via "Top votants"

**Risque** : Les membres pourraient se sentir obligés de voter plus souvent pour "bien paraître".

**Atténuation** :
- Cette fonctionnalité est **optionnelle** et peut être désactivée
- Elle mesure la **participation** (présence aux votes), pas la "qualité" des votes
- Aucune distinction entre votes pour/contre/abstention

**Recommandation** : Ne pas utiliser cette métrique comme critère d'évaluation des membres.

### 2. Temps de réponse

**Risque** : En théorie, croiser le temps de réponse avec l'ordre d'arrivée des votes pourrait compromettre l'anonymat.

**Atténuation** :
- Les données de timing sont **agrégées** (distribution, pas individuel)
- L'API ne retourne pas les horodatages individuels
- Pour les votes secrets, l'ordre d'enregistrement est déjà protégé

### 3. Surveillance des dissidents

**Risque** : Identifier les membres qui votent "différemment" de la majorité.

**Protection** :
- **Impossible** avec les analytics actuels : aucune donnée de vote individuel n'est accessible
- Les résultats agrégés (adoptées/rejetées) sont déjà publics après chaque vote

---

## Bonnes pratiques d'utilisation

### À faire

- Utiliser les taux de participation pour **améliorer l'accessibilité** (horaires, formats)
- Analyser les durées pour **optimiser les séances** (votes trop courts/longs)
- Partager les statistiques globales pour **la transparence**

### À éviter

- Utiliser "Top votants" comme critère de performance
- Créer des rapports nominatifs basés sur les analytics
- Exiger des justifications basées sur les patterns de participation

---

## Accès aux analytics

Les tableaux de bord sont accessibles uniquement aux **opérateurs** (rôle minimum).

Pour les organisations sensibles, il est recommandé de :
1. Limiter l'accès aux analytics aux administrateurs
2. Désactiver les métriques "Top votants" si non pertinentes
3. Documenter l'usage prévu dans la charte de gouvernance

---

## Conformité RGPD

Les analytics respectent les principes de :
- **Minimisation** : Seules les données agrégées sont traitées
- **Finalité** : Usage limité à l'amélioration opérationnelle
- **Transparence** : Ce document explique les traitements

Les membres ont le droit de :
- Connaître les métriques collectées (ce document)
- Demander la suppression de leurs données individuelles (via admin)

---

## Configuration recommandée

Pour désactiver certaines métriques sensibles, modifier les appels API :

```javascript
// Désactiver "Top votants"
// Dans analytics.htmx.html, commenter la ligne :
// loadTopVoters();

// Limiter les données de timing
// Modifier analytics.php pour exclure vote_timing
```

---

## Conclusion

Les analytics d'AG-Vote sont conçus pour la **transparence organisationnelle**, pas pour la surveillance individuelle. Leur utilisation éthique dépend de la culture de gouvernance de votre organisation.

**En cas de doute** : privilégiez la confidentialité des votants sur l'exhaustivité des métriques.
