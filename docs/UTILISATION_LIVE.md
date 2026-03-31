# Utilisation en seance (Live)

Ce document decrit le deroule operationnel complet d'une seance, depuis la preparation jusqu'a la validation finale, tel qu'il se deroule le jour J.

Il est destine :
* a l'**operateur** qui conduit la seance,
* au **president** qui engage la responsabilite juridique,
* au role **auditor / controle**,
* et, indirectement, aux **auditeurs**.

---

## Perimetre de ce document

Ce document couvre :

* la conduite d'une seance en conditions reelles,
* les actions attendues par role,
* les controles automatiques et garde-fous,
* la gestion des incidents et cas degrades.

L'installation technique est decrite dans `INSTALLATION.md`.
La demonstration guidee est decrite dans `RECETTE_DEMO.md`.

---

## Roles et responsabilites

### Operator (operateur de seance)

* prepare la seance,
* ouvre les resolutions,
* surveille le bon deroulement du vote,
* assiste le president.

### President

* cloture les votes,
* valide la seance,
* engage la responsabilite juridique.

### Auditor (controle)

* observe la coherence des donnees,
* verifie les anomalies,
* documente les incidents.

### Votant

* exprime son vote via tablette ou mobile,
* n'a acces qu'a l'interface de vote.

---

## Interfaces utilisees

| Role        | Interface           |
| ----------- | ------------------- |
| Operator    | `/operator/{uuid}`  |
| President   | `/hub`              |
| Auditor     | `/audit`            |
| Vote        | `/vote`             |
| Validation  | `/validate`         |
| PV / Export | `/report`           |

---

## Phase de preparation (avant le live)

### 1.1 Presences

L'operateur saisit ou verifie :

* les membres **presents**,
* les membres **distants**,
* les **absents**.

Les presences conditionnent :

* le quorum,
* les eligibilites au vote,
* les attentes de ballots.

---

### 1.2 Procurations

Les procurations sont saisies avant le vote.

Controles automatiques :

* plafond de procurations par mandataire,
* impossibilite de chaines de procuration,
* impact immediat sur le quorum.

Toute anomalie est visible dans l'interface **Auditor**.

---

### 1.3 Verification de l'etat "Ready"

Avant de passer en live :

* aucune resolution ouverte,
* donnees coherentes (presences, procurations),
* aucun verrouillage actif.

L'etat global est visible via les indicateurs de readiness.

---

## Conduite du vote (Live)

### 2.1 Ouverture d'une resolution (Operator)

L'operateur ouvre une resolution.

Effets automatiques :

* generation des tokens de vote (idempotente),
* une seule resolution ouverte a la fois,
* horodatage d'ouverture.

Si une resolution est deja ouverte, l'action est refusee.

---

### 2.2 Vote electronique (Votants)

Les votants accedent a l'interface via :

```
/vote
```

Garanties :

* token unique et non rejouable,
* confirmation obligatoire,
* enregistrement immediat du ballot.

L'operateur et le president voient :

* le nombre de votes recus,
* les votes manquants,
* les anomalies eventuelles.

---

### 2.3 Declaration d'incident (CDC)

A tout moment, l'operateur, le president ou l'auditeur peuvent declarer un incident :

* probleme reseau,
* materiel defaillant,
* decision exceptionnelle.

Chaque incident :

* est horodate,
* est trace,
* apparait dans le PV final (annexe).

---

### 2.4 Mode degrade (si necessaire)

En cas d'impossibilite de vote electronique :

* un vote manuel peut etre saisi,
* une justification est obligatoire,
* l'action est auditee.

Le mode degrade est **visible et documente**, jamais silencieux.

---

### 2.5 Cloture du vote (President)

Le president clot la resolution.

Effets automatiques :

* fermeture definitive de la resolution,
* calculs :
  * quorum par resolution,
  * majorite,
* determination du resultat juridique :
  * **ADOPTEE**
  * **REJETEE**
  * **NON VALABLE (quorum)**

Les resultats sont persistes et auditables.

---

## Repetition du cycle

Les etapes **2.1 a 2.5** sont repetees pour chaque resolution
de l'ordre du jour.

A tout instant :
* une seule resolution peut etre ouverte,
* l'etat global reste visible.

---

## Controles et anomalies

### 4.1 Vue anomalies (Auditor)

La vue de controle permet de detecter :

* votes manquants,
* tokens expires ou non utilises,
* votes hors statut,
* depassements de procurations.

Ces informations servent :
* a decider d'attendre,
* a documenter un incident,
* ou a justifier une decision.

---

### 4.2 Ready-check avant validation

Avant validation finale :

* toutes les resolutions doivent etre cloturees,
* aucune anomalie bloquante ne doit subsister
  (ou etre explicitement justifiee).

L'interface **Validation** indique clairement si la seance est prete.

---

## Validation finale (President)

La validation :

* engage juridiquement la seance,
* horodate la decision,
* **verrouille definitivement** la base.

Apres validation :

* toute modification est refusee (HTTP 409),
* seuls la consultation, le PV et les exports sont autorises.

---

## Post-seance

Apres validation, il est possible de :
* consulter le **PV**,
* exporter les donnees (CSV),
* archiver les documents.

Le PV inclut notamment :
* participants et procurations,
* resolutions et resultats,
* quorum par resolution,
* incidents et actions manuelles,
* regle appliquee pour chaque decision.

---

## Principes cles a retenir

* Une seule resolution ouverte a la fois
* Aucun calcul cache (tout est tracable)
* Les incidents sont documentes, pas ignores
* La validation est irreversible
* Le PV est la synthese juridique finale

---

## Conclusion

Ce deroule garantit :

* une seance fluide,
* des decisions explicites,
* une tracabilite complete,
* un niveau de conformite CDC assume.

Pour un deroule chronometre et reproductible, voir **RECETTE_DEMO.md**.
