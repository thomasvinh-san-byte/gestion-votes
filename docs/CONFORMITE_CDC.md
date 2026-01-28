# Conformité CDC — Cadre, garanties et limites

Ce document décrit le **niveau de conformité CDC** couvert par l’application **Gestion des votes**, ainsi que les **garanties apportées**, les **mécanismes de preuve**, et les **limites connues et assumées**.

Il a pour objectif de permettre :

* une **lecture claire par un auditeur**,
* une **compréhension par un décideur non technique**,
* une **traçabilité explicite** entre règles métier et implémentation.

---

## Périmètre de conformité

La conformité décrite ici couvre :

* la **conduite d’une séance de vote**,
* la **collecte et le calcul des votes**,
* la **traçabilité des actions et incidents**,
* la **production des livrables post-séance**.

Elle ne couvre **pas** :

* l’archivage légal long terme,
* la signature électronique qualifiée,
* l’horodatage par tiers de confiance.

Ces limites sont détaillées plus loin.

---

## Objectifs de conformité

Le système vise à garantir que :

1. chaque vote est **attribuable**,
2. chaque vote est **unique et non modifiable**,
3. les règles de décision sont **connues, appliquées et traçables**,
4. les résultats sont **rejouables et auditables**,
5. la séance devient **intangible après validation**.

---

## Garanties fonctionnelles couvertes

### Identification et unicité du vote

* Chaque votant dispose d’un **token unique** généré à l’ouverture d’une résolution.
* Le token :

  * est lié à un membre et à une résolution,
  * est à usage unique,
  * est marqué comme consommé après vote.
* Toute tentative de rejeu est refusée.

**Garantie apportée** :
un votant ne peut voter **qu’une seule fois par résolution**.

---

### Intégrité du vote

* Le vote exprimé est :

  * horodaté,
  * persisté en base PostgreSQL,
  * non modifiable après enregistrement.
* Aucun recalcul implicite n’écrase les données sources.

**Garantie apportée** :
le bulletin de vote (ballot) est une **preuve primaire immuable**.

---

### Pondération et tantièmes

* Chaque ballot peut porter un **poids** (tantièmes).
* Les calculs de résultat reposent sur :

  * la somme des poids exprimés,
  * et non sur le nombre brut de votants.
* En l’absence de pondération, un poids unitaire est appliqué.

**Garantie apportée** :
les règles de pondération sont **explicites, homogènes et traçables**.

---

### Quorum

* Le quorum est calculé :

  * globalement pour la séance,
  * **et par résolution** au moment de la clôture.
* Si le quorum n’est pas atteint :

  * la résolution est marquée **non valable**,
  * le résultat est conservé à titre informatif.

**Garantie apportée** :
aucune décision ne peut être validée sans quorum.

---

### Règle de majorité

* La règle appliquée est la **majorité pondérée** :

  * `poids POUR` > `poids CONTRE`.
* La règle est :

  * appliquée automatiquement,
  * persistée avec le résultat,
  * affichée explicitement dans le PV.

**Garantie apportée** :
la règle de décision est **connue, figée et vérifiable**.

---

## Traçabilité et audit

### Audit des actions

* Les actions critiques sont tracées :

  * ouverture / clôture de résolution,
  * votes manuels,
  * incidents déclarés,
  * validation finale.
* Les tables d’audit sont **append-only**.

---

### Incidents

* Tout incident peut être déclaré :

  * problème technique,
  * situation exceptionnelle,
  * décision humaine hors automatisme.
* Chaque incident est :

  * horodaté,
  * attribué,
  * visible dans le PV final.

**Principe clé** :
un incident est **documenté**, jamais masqué.

---

### Mode dégradé

* En cas d’impossibilité de vote électronique :

  * un vote manuel est possible,
  * une justification est obligatoire,
  * l’action est explicitement marquée.

**Garantie apportée** :
la résilience n’altère pas la traçabilité.

---

## Validation et verrouillage

### Validation finale

* La validation est effectuée par le président.
* Elle :

  * horodate la décision,
  * engage la responsabilité juridique,
  * déclenche le verrouillage.

---

### Verrouillage post-validation

Après validation :

* toute tentative de modification est refusée,
* les endpoints retournent un **HTTP 409 explicite**,
* seules la consultation, le PV et les exports restent accessibles.

**Garantie apportée** :
la séance devient **intangibile et figée**.

---

## Livrables de conformité

Après validation, le système produit :

* un **procès-verbal** incluant :

  * participants et procurations,
  * résolutions et résultats pondérés,
  * quorum par résolution,
  * incidents et actions manuelles,
  * règles appliquées.
* des **exports CSV** (présences, votes, résultats, audit).

Ces livrables constituent les **preuves exploitables**.

---

## ⚠️ Limites connues et assumées

Les éléments suivants **ne sont pas couverts** par le périmètre actuel :

* signature électronique qualifiée (eIDAS),
* horodatage par tiers de confiance (RFC 3161),
* chiffrement de bout en bout des bulletins,
* archivage légal long terme.

Ces limites sont **connues, documentées et non dissimulées**.

---

## Principe de transparence

Le système repose sur un principe fondamental :

> **Tout ce qui est automatisé est traçable.
> Tout ce qui est manuel est justifié.
> Tout ce qui n’est pas couvert est explicitement documenté.**

---

## ✔️ Conclusion

Sur le périmètre défini, **Gestion des votes** fournit :

* un cadre de vote structuré,
* des décisions explicites et auditables,
* une traçabilité complète,
* un niveau de conformité CDC **clair, assumé et défendable**.

Les autres documents (`INSTALLATION.md`, `UTILISATION_LIVE.md`, `RECETTE_DEMO.md`) complètent ce cadre sans le contredire.
