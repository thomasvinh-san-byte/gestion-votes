# Gestion des votes

**Gestion des votes** est une application web de gestion de séances de vote avec **vote électronique sécurisé**, conçue pour un usage **opérationnel réel** (assemblées générales, conseils, réunions formelles) et un niveau de **conformité CDC** clairement défini.

Le produit couvre **l’intégralité du cycle de vie d’une séance** :
préparation → conduite en live → consolidation → validation → production des livrables (PV, exports).

---

## 🎯 Objectifs du produit

* Permettre la **conduite fluide d’une séance de vote** avec ou sans vote électronique
* Garantir des **résultats juridiquement défendables** (dans le périmètre CDC défini)
* Offrir une **traçabilité complète** des actions et incidents
* Rendre la séance **testable et rejouable** (seed, reset, audit)
* Fournir des **livrables exploitables** (PV, CSV) après validation

Le projet est volontairement :

* **PostgreSQL-first** (DB = source de vérité),
* **simple côté front** (HTML + HTMX, pas de SPA),
* **strict sur les règles métier** (garde-fous, verrouillage).

---

## ✅ Fonctionnalités principales

### Gestion de séance

* Création et pilotage de séances (`meetings`)
* Ordre du jour et résolutions (`motions`)
* Gestion des statuts (préparation, live, validée)

### Présences & procurations

* Présents / distants / absents
* Procurations avec contrôle de plafond
* Impact sur quorum et pondération

### Vote électronique

* Vote par **token unique** (QR / lien tablette)
* Anti-rejeu (token consommé)
* Confirmation obligatoire côté votant
* Support du **mode dégradé** (vote manuel justifié)

### Calculs automatiques

* Pondération (tantièmes / poids)
* Quorum (global et par résolution)
* Majorité **pondérée**
* Résultat juridique explicite par résolution

### Contrôle & traçabilité

* Déclaration d’incidents (réseau, matériel, décision exceptionnelle)
* Audit append-only
* Détection d’anomalies (votes manquants, procurations, tokens)

### Post-séance

* Validation finale par le président
* **Verrouillage complet** de la base après validation (409)
* Génération de **PV**
* Exports CSV (présences, votes, résultats, audit)

---

## 🧑‍🤝‍🧑 Rôles utilisateurs

* **Operator**
  Conduit la séance : ouvre les résolutions, surveille le live, assiste le président.

* **Président**
  Clôture les votes, valide la séance, engage la responsabilité juridique.

* **Trust**
  Rôle de contrôle et d’audit : anomalies, cohérence, conformité.

* **Votant**
  Exprime son vote via tablette ou mobile (interface minimale).

---

## 🖥️ Interfaces principales

| Rôle            | Page                   |
| --------------- | ---------------------- |
| Opérateur       | `/operator.htmx.html`  |
| Président       | `/president.htmx.html` |
| Trust           | `/trust.htmx.html`     |
| Vote (tablette) | `/vote.php?token=…`    |
| Validation      | `/validate.htmx.html`  |
| PV / Exports    | `/report.htmx.html`    |

---

## ⚖️ Conformité & cadre CDC (résumé)

Le système couvre notamment :

* Identification du votant par token unique
* Unicité du vote (anti-rejeu)
* Pondération et quorum
* Traçabilité complète (audit, incidents, actions manuelles)
* Calculs rejouables depuis les ballots
* Verrouillage total après validation
* PV et exports **uniquement post-validation**

Les limites connues (assumées et documentées) sont détaillées dans
👉 **CONFORMITE_CDC.md**

---

## 📚 Documentation

* **INSTALLATION.md**
  Installation complète sur Linux (PostgreSQL + PHP)

* **UTILISATION_LIVE.md**
  Déroulé opérateur / président / trust, le jour J

* **RECETTE_DEMO.md**
  Scénario de démonstration et de test (≈10 minutes)

* **CONFORMITE_CDC.md**
  Cadre juridique, garanties, limites

---

## 🚀 Démarrage rapide (dev)

```bash
php -S 0.0.0.0:8000 -t public
```

Puis ouvrir :

```
http://<IP>:8000/operator.htmx.html
```

---

## 🧠 Philosophie du projet

* **Clarté > complexité**
* **DB comme source de vérité**
* **Moins de magie, plus d’audit**
* **Ce qui n’est pas couvert est explicitement documenté**
