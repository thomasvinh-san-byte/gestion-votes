# Recette & demonstration (environ 10 minutes)

Ce document decrit un scenario de demonstration complet, chronometre et reproductible permettant de valider le fonctionnement de l'application AG-VOTE de bout en bout.

Il est concu pour :
* une **recette fonctionnelle**,
* une **demonstration client ou decideur**,
* une **lecture par un auditeur** souhaitant comprendre la chaine de preuves.

---

## Objectifs de la recette

A l'issue de ce scenario, on doit avoir demontre :
* l'ouverture et la conduite d'une seance,
* le vote electronique par token,
* les calculs de quorum et de majorite,
* la gestion d'incidents et du mode degrade,
* la validation finale et le verrouillage,
* la production des livrables (PV, exports).

Le scenario est volontairement court, mais couvre tous les points critiques.

---

## Pre-requis (1 minute)

Avant de commencer :
* l'application est lancee (`php -S 0.0.0.0:8000 -t public`),
* la base est initialisee,
* une seance de demonstration est disponible (via un script de seed ou des donnees existantes).

Pages ouvertes dans le navigateur :
* `/operator/{uuid}`
* `/hub`
* `/audit`

---

## Preparation de la seance (2 minutes)

### 1.1 Verification des presences

Depuis l'interface **Operator** :

* verifier que plusieurs membres sont :
  * presents ou distants,
  * au moins un membre absent (optionnel).

A montrer :
* impact direct sur les compteurs,
* base du calcul de quorum.

---

### 1.2 Procurations

Saisir au moins **une procuration**.

A montrer :
* lien mandant / mandataire,
* prise en compte immediate dans les calculs,
* visibilite dans la vue **Auditor**.

---

## Vote electronique, Resolution 1 (3 minutes)

### 2.1 Ouverture de la resolution

Depuis **Operator** :
* ouvrir une resolution.

A expliquer :
* generation automatique des tokens,
* impossibilite d'ouvrir deux resolutions simultanement.

---

### 2.2 Vote par tablette / mobile

Depuis deux navigateurs ou appareils :
* acceder a `/vote?token=...`,
* voter (ex. POUR et CONTRE),
* confirmer le vote.

A montrer :
* confirmation obligatoire,
* consommation du token,
* mise a jour en temps reel cote Operator / President.

---

### 2.3 Declaration d'incident (CDC)

Declarer un incident volontairement :
* type : reseau / materiel,
* description : "incident de demonstration".

A expliquer :
* l'incident est horodate,
* il est conserve,
* il apparaitra dans le PV.

---

### 2.4 Mode degrade (optionnel mais recommande)

Simuler un vote impossible electroniquement :
* saisir un vote manuel,
* fournir une justification explicite.

A expliquer :
* tracabilite renforcee,
* distinction claire avec les votes electroniques.

---

### 2.5 Cloture de la resolution

Depuis **President** :
* cloturer la resolution.

A montrer :
* calcul automatique :
  * quorum par resolution,
  * majorite,
* resultat juridique explicite :
  * ADOPTEE / REJETEE / NON VALABLE.

---

## Vote rapide, Resolution 2 (1 minute)
* ouvrir une deuxieme resolution,
* exprimer un seul vote,
* cloturer immediatement.

Objectif :
* montrer la repetabilite du cycle,
* confirmer l'absence d'effets de bord.

---

## Controles et anomalies (1 minute)

Depuis l'interface **Auditor** :
* verifier :
  * votes manquants = 0 (ou expliques),
  * procurations conformes,
  * absence d'anomalies bloquantes.

A expliquer :
* les anomalies sont visibles,
* elles servent a decider ou a justifier.

---

## Validation finale (2 minutes)

### 5.1 Ready-check

Depuis l'interface **Validation** :

* verifier que la seance est "prete" :
  * aucune resolution ouverte,
  * toutes les decisions cloturees.

---

### 5.2 Validation

Depuis **President** :
* valider la seance.

A montrer :
* horodatage,
* responsabilite engagee,
* **verrouillage immediat**.

---

### 5.3 Preuve du verrouillage

Tenter volontairement :
* de modifier une presence,
* d'ouvrir une resolution.

Resultat attendu :
* refus explicite (HTTP 409),
* message clair.

---

## Livrables post-seance (1 minute)

### 6.1 Proces-verbal

Ouvrir le **PV** :

A verifier :
* participants et procurations,
* resolutions et resultats,
* quorum par resolution,
* incidents et votes manuels,
* regles appliquees.

---

### 6.2 Exports CSV

Telecharger les exports :
* presences,
* votes,
* resultats,
* audit.

A rappeler :
* exports autorises **uniquement apres validation**.

---

## Points cles a faire passer pendant la demo

* Rien n'est modifiable apres validation
* Les regles sont appliquees automatiquement
* Les exceptions sont documentees, pas masquees
* Les calculs sont rejouables
* Le PV est la synthese juridique finale

---

## Conclusion

En moins de 10 minutes, ce scenario demontre :

* la maitrise operationnelle du live,
* la robustesse des calculs,
* la tracabilite complete,
* la conformite CDC sur le perimetre annonce.

Pour le cadre juridique detaille, voir **[directive-projet.md](directive-projet.md)**.
