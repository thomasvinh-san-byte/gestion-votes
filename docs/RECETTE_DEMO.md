# Recette & démonstration (≈10 minutes)

Ce document décrit un **scénario de démonstration complet, chronométré et reproductible** permettant de valider le fonctionnement de l’application **Gestion des votes** de bout en bout.

Il est conçu pour :
* une **recette fonctionnelle**,
* une **démonstration client ou décideur**,
* une **lecture par un auditeur** souhaitant comprendre la chaîne de preuves.

---

## Objectifs de la recette

À l’issue de ce scénario, on doit avoir démontré :
* l’ouverture et la conduite d’une séance,
* le vote électronique par token,
* les calculs de quorum et de pondération,
* la gestion d’incidents et du mode dégradé,
* la validation finale et le verrouillage,
* la production des livrables (PV, exports).

Le scénario est volontairement **court**, mais couvre **tous les points critiques**.

---

## Pré-requis (1 minute)

Avant de commencer :
* l’application est lancée (`php -S 0.0.0.0:8000 -t public`),
* la base est initialisée,
* une séance de démonstration est disponible (via un script de seed ou des données existantes).

Pages ouvertes dans le navigateur :
* `/operator.htmx.html`
* `/president.htmx.html`
* `/trust.htmx.html`

---

## Préparation de la séance (2 minutes)

### 1.1 Vérification des présences

Depuis l’interface **Operator** :

* vérifier que plusieurs membres sont :
  * présents ou distants,
  * au moins un membre absent (optionnel).

À montrer :
* impact direct sur les compteurs,
* base du calcul de quorum.

---

### 1.2 Procurations

Saisir au moins **une procuration**.

À montrer :
* lien mandant / mandataire,
* prise en compte immédiate dans la pondération,
* visibilité dans la vue **Auditor**.

---

## Vote électronique — Résolution 1 (3 minutes)

### 2.1 Ouverture de la résolution

Depuis **Operator** :
* ouvrir une résolution.

À expliquer :
* génération automatique des tokens,
* impossibilité d’ouvrir deux résolutions simultanément.

---

### 2.2 Vote par tablette / mobile

Depuis deux navigateurs ou appareils :
* accéder à `/vote.php?token=…`,
* voter (ex. POUR et CONTRE),
* confirmer le vote.

À montrer :
* confirmation obligatoire,
* consommation du token,
* mise à jour en temps réel côté Operator / Président.

---

### 2.3 Déclaration d’incident (CDC)

Déclarer un incident volontairement :
* type : réseau / matériel,
* description : “incident de démonstration”.

À expliquer :
* l’incident est horodaté,
* il est conservé,
* il apparaîtra dans le PV.

---

### 2.4 Mode dégradé (optionnel mais recommandé)

Simuler un vote impossible électroniquement :
* saisir un vote manuel,
* fournir une justification explicite.

À expliquer :
* traçabilité renforcée,
* distinction claire avec les votes électroniques.

---

### 2.5 Clôture de la résolution

Depuis **Président** :
* clôturer la résolution.

À montrer :
* calcul automatique :
  * pondération (tantièmes),
  * quorum par résolution,
  * majorité pondérée,
* résultat juridique explicite :
  * ADOPTÉE / REJETÉE / NON VALABLE.

---

## Vote rapide — Résolution 2 (1 minute)
* ouvrir une deuxième résolution,
* exprimer un seul vote,
* clôturer immédiatement.

Objectif :
* montrer la répétabilité du cycle,
* confirmer l’absence d’effets de bord.

---

## Contrôles et anomalies (1 minute)

Depuis l'interface **Auditor** :
* vérifier :
  * votes manquants = 0 (ou expliqués),
  * procurations conformes,
  * absence d’anomalies bloquantes.

À expliquer :
* les anomalies sont visibles,
* elles servent à décider ou à justifier.

---

## Validation finale (2 minutes)

### 5.1 Ready-check

Depuis l’interface **Validation** :

* vérifier que la séance est “prête” :
  * aucune résolution ouverte,
  * toutes les décisions clôturées.

---

### 5.2 Validation

Depuis **Président** :
* valider la séance.

À montrer :
* horodatage,
* responsabilité engagée,
* **verrouillage immédiat**.

---

### 5.3 Preuve du verrouillage

Tenter volontairement :
* de modifier une présence,
* d’ouvrir une résolution.

Résultat attendu :
* refus explicite (HTTP 409),
* message clair.

---

## Livrables post-séance (1 minute)

### 6.1 Procès-verbal

Ouvrir le **PV** :

À vérifier :
* participants et procurations,
* résolutions et résultats pondérés,
* quorum par résolution,
* incidents et votes manuels,
* règles appliquées.

---

### 6.2 Exports CSV

Télécharger les exports :
* présences,
* votes,
* résultats,
* audit.

À rappeler :
* exports autorisés **uniquement après validation**.

---

## Points clés à faire passer pendant la démo

* Rien n’est modifiable après validation
* Les règles sont appliquées automatiquement
* Les exceptions sont documentées, pas masquées
* Les calculs sont rejouables
* Le PV est la synthèse juridique finale

---

## ✔️ Conclusion

En moins de 10 minutes, ce scénario démontre :

* la maîtrise opérationnelle du live,
* la robustesse des calculs,
* la traçabilité complète,
* la conformité CDC sur le périmètre annoncé.

Pour le cadre juridique détaillé, voir **CONFORMITE_CDC.md**.

