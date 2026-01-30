# Utilisation en séance (Live)

Ce document décrit **le déroulé opérationnel complet d’une séance**, depuis la préparation jusqu’à la validation finale, tel qu’il se déroule **le jour J**.

Il est destiné :
* à l’**opérateur** qui conduit la séance,
* au **président** qui engage la responsabilité juridique,
* au rôle **auditor / contrôle**,
* et, indirectement, aux **auditeurs**.

---

## Périmètre de ce document

Ce document couvre :

* la conduite d’une séance en conditions réelles,
* les actions attendues par rôle,
* les contrôles automatiques et garde-fous,
* la gestion des incidents et cas dégradés.

L’installation technique est décrite dans `INSTALLATION.md`
La démonstration guidée est décrite dans `RECETTE_DEMO.md`.

---

## Rôles et responsabilités

### Operator (opérateur de séance)

* prépare la séance,
* ouvre les résolutions,
* surveille le bon déroulement du vote,
* assiste le président.

### Président

* clôture les votes,
* valide la séance,
* engage la responsabilité juridique.

### Auditor (contrôle)

* observe la cohérence des données,
* vérifie les anomalies,
* documente les incidents.

### Votant

* exprime son vote via tablette ou mobile,
* n’a accès qu’à l’interface de vote.

---

## Interfaces utilisées

| Rôle        | Interface              |
| ----------- | ---------------------- |
| Operator    | `/operator.htmx.html`  |
| Président   | `/president.htmx.html` |
| Auditor     | `/trust.htmx.html`     |
| Vote        | `/vote.php?token=…`    |
| Validation  | `/validate.htmx.html`  |
| PV / Export | `/report.htmx.html`    |

---

## Phase de préparation (avant le live)

### 1.1 Présences

L’opérateur saisit ou vérifie :

* les membres **présents**,
* les membres **distants**,
* les **absents**.

Les présences conditionnent :

* le quorum,
* les éligibilités au vote,
* les attentes de ballots.

---

### 1.2 Procurations

Les procurations sont saisies avant le vote.

Contrôles automatiques :

* plafond de procurations par mandataire,
* impossibilité de chaînes de procuration,
* impact immédiat sur pondération et quorum.

Toute anomalie est visible dans l'interface **Auditor**.

---

### 1.3 Vérification de l’état “Ready”

Avant de passer en live :

* aucune résolution ouverte,
* données cohérentes (présences, procurations),
* aucun verrouillage actif.

L’état global est visible via les indicateurs de readiness.

---

## Conduite du vote (Live)

### 2.1 Ouverture d’une résolution (Operator)

L’opérateur ouvre une résolution.

Effets automatiques :

* génération des tokens de vote (idempotente),
* une seule résolution ouverte à la fois,
* horodatage d’ouverture.

Si une résolution est déjà ouverte, l’action est refusée.

---

### 2.2 Vote électronique (Votants)

Les votants accèdent à l’interface via :

```
/vote.php?token=…
```

Garanties :

* token unique et non rejouable,
* confirmation obligatoire,
* enregistrement immédiat du ballot.

L’opérateur et le président voient :

* le nombre de votes reçus,
* les votes manquants,
* les anomalies éventuelles.

---

### 2.3 Déclaration d’incident (CDC)

À tout moment, l'opérateur, le président ou l'auditeur peuvent déclarer un incident :

* problème réseau,
* matériel défaillant,
* décision exceptionnelle.

Chaque incident :

* est horodaté,
* est tracé,
* apparaît dans le PV final (annexe).

---

### 2.4 Mode dégradé (si nécessaire)

En cas d’impossibilité de vote électronique :

* un vote manuel peut être saisi,
* une justification est obligatoire,
* l’action est auditée.

Le mode dégradé est **visible et documenté**, jamais silencieux.

---

### 2.5 Clôture du vote (Président)

Le président clôt la résolution.

Effets automatiques :

* fermeture définitive de la résolution,
* calculs :
  * pondération (tantièmes),
  * quorum par résolution,
  * majorité pondérée,
* détermination du résultat juridique :
  * **ADOPTÉE**
  * **REJETÉE**
  * **NON VALABLE (quorum)**

Les résultats sont persistés et auditables.

---

## Répétition du cycle

Les étapes **2.1 à 2.5** sont répétées pour chaque résolution
de l’ordre du jour.

À tout instant :
* une seule résolution peut être ouverte,
* l’état global reste visible.

---

## Contrôles et anomalies

### 4.1 Vue anomalies (Auditor)

La vue de contrôle permet de détecter :

* votes manquants,
* tokens expirés ou non utilisés,
* votes hors statut,
* dépassements de procurations.

Ces informations servent :
* à décider d’attendre,
* à documenter un incident,
* ou à justifier une décision.

---

### 4.2 Ready-check avant validation

Avant validation finale :

* toutes les résolutions doivent être clôturées,
* aucune anomalie bloquante ne doit subsister
  (ou être explicitement justifiée).

L’interface **Validation** indique clairement si la séance est prête.

---

## Validation finale (Président)

La validation :

* engage juridiquement la séance,
* horodate la décision,
* **verrouille définitivement** la base.

Après validation :

* toute modification est refusée (HTTP 409),
* seuls la consultation, le PV et les exports sont autorisés.

---

## Post-séance

Après validation, il est possible de :
* consulter le **PV**,
* exporter les données (CSV),
* archiver les documents.

Le PV inclut notamment :
* participants et procurations,
* résolutions et résultats pondérés,
* quorum par résolution,
* incidents et actions manuelles,
* règle appliquée pour chaque décision.

---

## Principes clés à retenir

* Une seule résolution ouverte à la fois
* Aucun calcul caché (tout est traçable)
* Les incidents sont documentés, pas ignorés
* La validation est irréversible
* Le PV est la synthèse juridique finale

---

## ✔️ Conclusion

Ce déroulé garantit :

* une séance fluide,
* des décisions explicites,
* une traçabilité complète,
* un niveau de conformité CDC assumé.

Pour un déroulé chronométré et reproductible, voir **RECETTE_DEMO.md**.
