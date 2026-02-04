# Analytics - Usage éthique et conformité RGPD

## Objectif des tableaux de bord

Les analytics d'AG-Vote sont conçus pour **améliorer la gestion des séances**, pas pour surveiller les votants individuels.

**Principe fondamental** : Aucune donnée nominative n'est exposée dans les analytics. Toutes les statistiques sont agrégées.

---

## Ce que les analytics montrent

| Métrique | Données | Usage légitime |
|----------|---------|----------------|
| Taux de participation | % présents (agrégé) | Identifier les problèmes d'engagement |
| Résolutions adoptées | Comptage final public | Mesurer l'efficacité de la gouvernance |
| Durée des votes | Temps moyen | Optimiser la durée des séances |
| Anomalies | Indicateurs de qualité | Détecter les problèmes opérationnels |

---

## Ce que les analytics ne montrent PAS

- **Le contenu des votes individuels** : Aucune donnée sur qui a voté pour/contre
- **L'ordre des votes** : Les horodatages individuels ne sont pas exposés
- **Les corrélations vote/votant** : Aucun croisement n'est possible via l'API
- **Les classements de membres** : Pas de "top votants" ou autre système de ranking

---

## Onglet "Anomalies" - Détection de problèmes

L'onglet Anomalies remplace tout système de classement individuel. Il signale des **indicateurs agrégés** pour améliorer la qualité des processus :

| Indicateur | Description | Action suggérée |
|------------|-------------|-----------------|
| Participation faible | Séances avec <50% de présents | Vérifier horaires/accessibilité |
| Problèmes de quorum | Votes effectués sans quorum | Revoir la planification |
| Résolutions incomplètes | Votes ouverts non fermés | Finaliser les séances |
| Concentration procurations | >3 procurations/membre | Vérifier la répartition |
| Taux d'abstention | % d'abstentions | Améliorer la communication |
| Votes très courts | <30 secondes | Vérifier le temps de réflexion |

**Important** : Ces indicateurs ne révèlent JAMAIS l'identité des membres concernés.

---

## Pourquoi pas de "Top votants" ?

Les systèmes de classement individuel ("top votants", "membres les plus actifs") ont été **volontairement exclus** pour plusieurs raisons :

### 1. Pression sociale
Un classement visible peut créer une pression pour voter plus souvent, indépendamment de la pertinence.

### 2. Conformité RGPD
L'article 5 du RGPD impose la minimisation des données. Exposer des statistiques nominatives n'est pas nécessaire à la gestion des séances.

### 3. Culture de vote saine
Le vote doit être un acte libre. Les métriques individuelles peuvent transformer la participation en compétition.

---

## Risques potentiels et atténuations

### 1. Temps de réponse

**Risque** : En théorie, croiser le temps de réponse avec l'ordre d'arrivée des votes pourrait compromettre l'anonymat.

**Atténuation** :
- Les données de timing sont **agrégées** (distribution, pas individuel)
- L'API ne retourne pas les horodatages individuels
- Pour les votes secrets, l'ordre d'enregistrement est déjà protégé

### 2. Surveillance des dissidents

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
- Utiliser les anomalies pour **améliorer les processus**

### À ne JAMAIS faire

- Créer des rapports nominatifs basés sur les analytics
- Exiger des justifications basées sur les patterns de participation
- Utiliser les données pour évaluer la "performance" des membres
- Tenter de reconstituer des votes individuels par recoupement

---

## Accès aux analytics

Les tableaux de bord sont accessibles uniquement aux **opérateurs** (rôle minimum).

Pour les organisations sensibles, il est recommandé de :
1. Limiter l'accès aux analytics aux administrateurs
2. Documenter l'usage prévu dans la charte de gouvernance
3. Former les opérateurs à l'usage éthique des données

---

## Conformité RGPD

### Principes respectés

| Principe | Application |
|----------|-------------|
| **Minimisation** | Seules les données agrégées sont traitées |
| **Finalité** | Usage limité à l'amélioration opérationnelle |
| **Transparence** | Ce document explique les traitements |
| **Exactitude** | Les données sont calculées en temps réel |
| **Limitation de conservation** | Pas de stockage supplémentaire pour analytics |

### Droits des membres

Les membres ont le droit de :
- Connaître les métriques collectées (ce document)
- Demander la suppression de leurs données individuelles (via admin)
- S'opposer au traitement (les analytics n'utilisent que des données agrégées)

### Base légale

Les analytics reposent sur **l'intérêt légitime** de l'organisation à améliorer ses processus de gouvernance, dans le respect de la vie privée des votants.

---

## Audit RGPD - Points de vérification

Pour un audit de conformité, vérifier :

- [ ] Aucune API ne retourne de données nominatives
- [ ] Les requêtes SQL utilisent GROUP BY pour l'agrégation
- [ ] Les exports ne contiennent pas de données individuelles
- [ ] La documentation est accessible aux membres
- [ ] Les opérateurs sont formés à l'usage éthique

---

## Conclusion

Les analytics d'AG-Vote sont conçus pour la **transparence organisationnelle**, pas pour la surveillance individuelle.

**Principe directeur** : En cas de doute, privilégiez la confidentialité des votants sur l'exhaustivité des métriques.

---

*Dernière mise à jour : Février 2026*
