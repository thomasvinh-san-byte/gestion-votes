# **CAHIER DES CHARGES TECHNIQUE**

## Logiciel de gestion d’assemblées délibératives et de votes

**Version :** 1.1 (Finale – Document figé)
**Référence :** AG-VOTE/CDC/2026-001
**Date :** 08 janvier 2026
**Statut :** Validé pour finalisation, exploitation et démonstration

---

## **DOCUMENT CONTROL**

| Version |       Date | Auteur           | Modifications                                                    | Approbation |
| ------- | ---------: | ---------------- | ---------------------------------------------------------------- | ----------- |
| 1.0     | 2026-01-08 | Équipe technique | Consolidation initiale cahier                                    | En attente  |
| 1.1     | 2026-01-08 | Lead Technique   | Alignement implémentation + priorité UI/UX + URLs lisibles (low) | ✅ Validé    |

**Documents référencés (livrables)** : INSTALL.md, SECURITY.md, docs/ (guides rôles), tests/ (recette), schema.sql (final), database/seeds/03_demo.sql.

---

## **1. CONTEXTE STRATÉGIQUE & CADRE PROJET**

### 1.1 Contexte opérationnel

Reprise et consolidation d’une solution existante de gestion d’assemblées délibératives, avec pour objectif une livraison rapide d’un produit exploitable en conditions réelles.

### 1.2 Cadre technique imposé (Option A)

* **Backend :** PHP (architecture modulaire, sans framework lourd)
* **Base de données :** PostgreSQL
* **Frontend :** HTML + HTMX + JavaScript minimal (vanilla)
* **Rafraîchissement temps réel :** Polling HTMX (pas de WebSockets)

### 1.3 Contraintes et exclusions

```yaml
contraintes:
  interdictions:
    - frameworks JavaScript lourds (React/Vue/Angular)
    - vote cryptographique avancé (hors périmètre v1)
    - refonte technologique complète
  exigences:
    - livraison rapide (time-to-market)
    - exploitabilité immédiate en production
    - maintenance par équipe PHP standard
```

### 1.4 Priorités absolues

1. **Lisibilité métier** : compréhensible par utilisateurs non-techniques
2. **Robustesse** : résilience aux incidents terrain (réseau, tablettes, opérateur)
3. **Traçabilité juridique** : audit complet, PV défendable, reproductible
4. **UI/UX qualité** : interface professionnelle, démo-ready, accessible

**Règle d’or :** toute évolution hors du présent document requiert un avenant formel.

---

## **2. OBJECTIF STRATÉGIQUE**

### 2.1 Mission

Concevoir, piloter, sécuriser et archiver une séance décisionnelle de bout en bout en intégrant les réalités opérationnelles : absences, retards, procurations, pondération des voix, quorum variables et contraintes juridiques.

### 2.2 Périmètre fonctionnel

* Cycle de vie complet d’une séance (création → conduite → consolidation → validation → archivage)
* Gestion des cas réels : absences, retardataires, sorties temporaires/définitives
* Procurations (globale/limitée) avec traçabilité
* Pondération des voix (tantièmes / voting power)
* Moteur de quorum paramétrable et justifiable (y compris double / évolutif / par motion)
* Vote tablette sécurisé (token) + mode hors-ligne et signalement incident
* Mode dégradé : saisie manuelle + bulletins papier + réconciliation
* PV généré et **figé** (snapshot) + audit exportable

### 2.3 Critères de qualité

* **Simplicité** : prise en main par secrétaire occasionnel (≤ 30 min)
* **Lisibilité** : état de séance compris en < 5 s
* **Défendabilité** : audit trail complet + PV figé et horodaté
* **Performance** : 1000 votants simultanés avec temps de réponse < 2 s
* **Résilience** : séance terminable malgré panne réseau/tablette

---

## **3. PRINCIPES ARCHITECTURAUX & UX**

### 3.1 Philosophie de conception

**Less is more** :

* pages réduites au strict nécessaire
* une page = une intention
* logique métier centralisée (source de vérité unique)
* code maintenable par développeur PHP junior

### 3.2 Principes UX

1. **Parcours guidés par l’état** : aucune action critique hors contexte
2. **Lisibilité immédiate** : statuts / badges / étapes partout
3. **Messages explicites** : “Pourquoi bloqué ? → Que faire ?”
4. **Accessibilité cognitive** : vocabulaire métier, pas de jargon technique exposé

### 3.3 UI/UX public non-tech (PRIORITÉ MAJEURE)

Exigences :

* conventions UI actuelles (boutons, formulaires, focus, états)
* typographie lisible (≥ 16px)
* espacement cohérent (grille 8px recommandée)
* états visibles : chargement, erreur, vide, succès
* cohérence visuelle : Admin / Opérateur / Président / Tablette / Archives
* **démo-ready** : parcours fluide, écrans polis, pas de zones “techniques”

### 3.4 Réalité terrain

* **Secrétaire = Opérateur** : pilote opérationnel
* **Président** : valide sans saisir
* **Votants** : tablettes dédiées (vote uniquement)

---

## **4. GOUVERNANCE DES ACCÈS & MODÈLE DE RÔLES**

### 4.1 Matrice des responsabilités

| Rôle                   | Périmètre     | Responsabilités clés                             | Interface                 |
| ---------------------- | ------------- | ------------------------------------------------ | ------------------------- |
| **ADMIN**              | Global        | paramétrage (policies), monitoring, utilisateurs | `admin.htmx.html`         |
| **OPÉRATEUR**          | Séance        | préparation, conduite, incidents, consolidation  | `operator_flow.htmx.html` |
| **PRÉSIDENT (TRUST)**  | Séance        | pilotage, validation, archivage                  | `trust.htmx.html`         |
| **LECTURE / CONTRÔLE** | Lecture seule | archives, PV, export audit                       | `archives.htmx.html`      |

### 4.2 Authentification (direction actuelle)

* **Méthode :** API Key simple via header `X-API-Key`
* **Granularité :** rôle (admin/operator/trust/readonly)
* **Activation :** variable d’environnement (mode dev possible sans auth)
* **Journalisation :** échecs d’auth (monitoring)

---

## **5. ARCHITECTURE FONCTIONNELLE & ÉCRANS**

### 5.1 Pages principales

1. **Tableau de bord global** : liste des séances + statuts
2. **Fiche séance unique** (page centrale) : paramètres, membres, ordre du jour, résolutions, quorum & règles, documents, checklist readiness
3. **Conduite de séance** (opérateur) : présences → procurations → votes → consolidation
4. **Cockpit président** : vision temps réel + actions de validation
5. **Interface votant (tablette)** : vote uniquement
6. **Archives** : PV figé, résultats, audit export

### 5.2 URLs lisibles (low priority)

Objectif : faciliter lecture/navigation **sans dépendre de l’opacité**.

* Exemples cibles :

  * `/meeting/{uuid}/overview`
  * `/meeting/{uuid}/attendance`
  * `/meeting/{uuid}/motions`
  * `/meeting/{uuid}/archives`

---

## **6. CRÉATION DE SÉANCE A→Z**

### 6.1 Workflow de configuration

1. **Métadonnées** : type, dates, lieu
2. **Membres** : CRUD + import CSV
3. **Pondérations** : voting power / tantièmes
4. **Ordre du jour** : structuration
5. **Résolutions** : création, description, ordre
6. **Choix politiques par défaut** : quorum + majorité
7. **Overrides par résolution** (optionnels)
8. **Convocations / documents** (si applicable)
9. **Checklist readiness** : validation de cohérence (bloquante)

### 6.2 Import membres (CSV)

Exemple minimal :

```csv
id,nom,prenom,email,ponderation,type,adresse
1,Dupont,Jean,jean@email.com,1250,proprietaire,"1 Rue Exemple"
2,Martin,Sophie,sophie@email.com,850,locataire,"2 Rue Exemple"
```

### 6.3 Checklist readiness (bloquante)

* membres importés
* pondérations cohérentes
* motions créées
* politiques quorum/vote définies
* aucune motion ouverte
* consolidations possibles
* président renseigné

Sortie :

* ✅ “prêt”
* ❌ “bloqué” + liste actions

---

## **7. PRÉSENCES, ABSENCES, RETARDS**

### 7.1 Statuts

| Statut                                | Description                      |
| ------------------------------------- | -------------------------------- |
| Présent                               | participant actif                |
| Présent à partir de X                 | retardataire (`present_from_at`) |
| Absent                                | non présent                      |
| Représenté                            | procuration / proxy              |
| Sorti temporairement / définitivement | sortie (`checked_out_at`)        |

### 7.2 Impact paramétrable par séance

* inclusion/exclusion du **quorum**
* inclusion/exclusion du **vote** (tablette) selon motion

**Direction actuelle** :

* `late_rule_quorum` : exclure les retardataires du quorum **par motion** après ouverture
* `late_rule_vote` : bloquer le vote tablette si arrivée après ouverture

---

## **8. PROCURATIONS**

* Une procuration max par donneur
* Globale ou limitée à certaines résolutions
* Plafond configurable
* Traçabilité complète (audit)

---

## **9. QUORUM – MODÈLES PARAMÉTRABLES**

### 9.1 Modèles fournis

* Quorum simple (personnes)
* Quorum pondéré (tantièmes)
* Double quorum
* Quorum évolutif (1re / 2e convocation)
* Quorum dynamique par motion
* Aucun quorum (consultatif)

### 9.2 Paramétrage

* base de calcul : `eligible_members` / `eligible_weight`
* seuils (0..1)
* inclusion proxies / remote
* gestion retardataires

### 9.3 Visibilité

* calcul temps réel
* justification automatique lisible

---

## **10. CONDUITE DE SÉANCE**

### 10.1 Opérateur

* contrôle du flot
* ouverture / clôture des votes
* gestion incidents (mode dégradé)
* consolidation + rapport anomalies

### 10.2 Président

* vision synthétique
* actions : démarrer, pause, clôture, validation
* validation bloquée sans cohérence

---

## **11. VOTE PAR TABLETTE**

### 11.1 Exigences

* interface dédiée
* accès QR / token
* confirmation de vote
* procurations intégrées (au niveau des pouvoirs)
* signalement incidents réseau

### 11.2 Sécurité tokens

* token brut (UUID) jamais stocké
* stockage du hash HMAC SHA-256
* expiration
* usage unique
* anti-replay

### 11.3 Hors-ligne / incidents

* badge réseau
* retry
* stockage local en cas d’échec
* signalement incident best-effort

---

## **12. CONSOLIDATION, VALIDATION, POST-SÉANCE**

* rapprochement des totaux
* rapport d’anomalies
* validation président
* génération PV
* archivage

**Direction actuelle :** PV figé (snapshot) au moment de l’archivage.

---

## **13. NOTIFICATIONS CONTEXTUELLES**

Principes :

* contextuelles
* actionnables
* hiérarchisées
* sans spam (sur transition d’état)

Exemples :

* séance prête / incomplète
* quorum atteint / perdu
* vote manquant
* incident technique
* séance prête à validation

---

## **14. AUDIT & JOURNAL DES DÉCISIONS**

* journal append-only
* audit exportable
* journal lisible : motion, règle, résultat, validation

---

## **15. DESIGN SYSTEM MINIMAL**

* cards
* badges
* stepper
* tables claires
* boutons hiérarchisés

Objectif : lecture immédiate de l’état de séance.

---

## **16. SPÉCIFICATIONS TECHNIQUES CRITIQUES**

### 16.1 Rafraîchissement temps réel (polling)

* `hx-trigger="every 3s"` sur composants critiques
* fréquence adaptative (votes actifs → plus rapide)
* composants ciblés (fragments)

### 16.2 Sécurité

* requêtes préparées
* validation stricte des entrées
* CSRF pour actions critiques (si applicable)
* headers sécurité recommandés (CSP/HSTS)

### 16.3 Mode dégradé

* saisie manuelle
* bulletins papier (QR)
* réconciliation via scan/saisie

### 16.4 Monitoring & alertes (ADMIN)

* connexions actives
* latence API/DB
* erreurs DB
* disque libre
* alertes : auth failures, latence >2s, disque <10%

---

## **17. CRITÈRES D’ACCEPTATION**

### 17.1 Fonctionnel

* séance créée, conduite, archivée sans contournement
* règles de quorum adaptables et justifiables
* président pilote réellement
* opérateur contrôle le déroulé
* votants votent simplement
* notifications guident
* chaque décision traçable et explicable

### 17.2 Technique

* résilience : séance termine malgré panne réseau
* sécurité : pas de falsification par URL
* temps réel perçu < 3s
* maintenabilité : bug corrigé en 1h par dev PHP junior
* performance : 1000 votants simultanés < 2s

Critère final : séance test avec pannes simulées menée à terme avec audit complet.

---

## **18. LIVRABLES**

### 18.1 Code source

* dépôt complet
* scripts déploiement
* documentation installation

### 18.2 Documentation

* guides par rôle
* doc technique (architecture, API)
* procédures d’urgence

### 18.3 Tests

* datasets de test
* scénarios d’acceptation
* recette (go/no-go)

---

**Document figé – v1.1 – Aligné implémentation & direction actuelle**