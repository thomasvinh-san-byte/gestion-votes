# Parcours de test A-Z — Séance complète

Guide de test de bout en bout pour valider le cycle de vie complet d'une séance,
de la création à l'archivage. Basé sur le jeu de données `database/seeds/04_e2e.sql`.

---

## Prérequis

### 1. Initialiser la base de données

```bash
sudo bash database/setup.sh --seed
```

Cela applique dans l'ordre : schéma, seed minimal, utilisateurs de test, démo, **seed E2E**.

### 2. Lancer le serveur

```bash
php -S 0.0.0.0:8000 -t public
```

### 3. Vérifier le `.env`

```
APP_ENV=dev
APP_AUTH_ENABLED=1
CSRF_ENABLED=0
RATE_LIMIT_ENABLED=0
```

L'authentification doit être **activée** pour tester les rôles.

---

## Comptes de test

| Rôle | Email | Mot de passe | Rôle système | Rôle de séance |
|---|---|---|---|---|
| Admin | `admin@ag-vote.local` | `Admin2024!` | admin | — |
| Opérateur | `operator@ag-vote.local` | `Operator2024!` | operator | assesseur |
| Président | `president@ag-vote.local` | `President2024!` | operator | président |
| Votant | `votant@ag-vote.local` | `Votant2024!` | viewer | électeur |

---

## Données du scénario

**Séance** : Conseil Municipal — Séance E2E
- État initial : `draft`
- Quorum par défaut : 50 % des personnes (6/12 minimum)
- Vote par défaut : majorité simple (> 50 % des exprimés)
- Lieu : Salle du Conseil Municipal — Hôtel de Ville

**12 membres** (CM-001 à CM-012), poids de vote égal (1 voix chacun) :

| Réf. | Nom | Note |
|---|---|---|
| CM-001 | Mme Dupont (Maire) | Reçoit la procuration de Fontaine |
| CM-002 | M. Lefebvre (1er Adjoint) | |
| CM-003 | Mme Girard (2e Adjointe) | |
| CM-004 | M. Blanc | |
| CM-005 | Mme Rousseau | |
| CM-006 | M. Mercier | |
| CM-007 | Mme Faure | |
| CM-008 | M. André | |
| CM-009 | Mme Bonnet | |
| CM-010 | M. Clément | |
| CM-011 | Mme Dumas | |
| CM-012 | M. Fontaine | **Absent** — procuration à Dupont |

**5 résolutions** :

| # | Titre | Politique de vote | Quorum | Secret |
|---|---|---|---|---|
| 1 | Approbation du PV du 15 janvier 2026 | Majorité simple | 50 % | Non |
| 2 | Budget supplémentaire 2026 — 150 000 EUR | Majorité absolue | 50 % | Non |
| 3 | Rénovation salle des fêtes — 280 000 EUR | Majorité 2/3 | 50 % | Non |
| 4 | Convention intercommunale déchets 2026-2029 | Majorité simple | — | Non |
| 5 | Désignation du délégué intercommunal | Majorité simple | 33 % | **Oui** |

**1 procuration** : Fontaine → Dupont (portée : toutes les résolutions)

---

## Parcours étape par étape

### Phase 1 — Préparation (opérateur)

**Se connecter** sur `/login.html` avec `operator@ag-vote.local` / `Operator2024!`

#### Étape 1.1 — Vérifier la séance

- Aller sur `/meetings.htmx.html`
- La séance "Conseil Municipal — Séance E2E" apparaît en statut **Brouillon**
- Cliquer dessus pour accéder à la fiche séance

**Résultat attendu** : la séance est visible avec ses 12 membres, 5 résolutions, 5 points à l'ordre du jour.

#### Étape 1.2 — Vérifier les membres

- Aller sur `/members.htmx.html`
- Vérifier que les 12 élus municipaux (CM-001 à CM-012) sont listés
- Chacun a un poids de vote de 1.0

**Résultat attendu** : 12 membres actifs affichés.

#### Étape 1.3 — Vérifier les résolutions

- Aller sur `/motions.htmx.html?meeting_id=eeeeeeee-e2e0-e2e0-e2e0-eeeeeeee0001`
- 5 résolutions en statut **Brouillon**
- La résolution 5 est marquée comme scrutin secret

**Résultat attendu** : 5 résolutions visibles, toutes en brouillon.

#### Étape 1.4 — Vérifier la procuration

- Aller sur `/proxies.htmx.html?meeting_id=eeeeeeee-e2e0-e2e0-e2e0-eeeeeeee0001`
- 1 procuration : Fontaine → Dupont

**Résultat attendu** : 1 procuration active, Dupont reçoit 1 voix déléguée.

#### Étape 1.5 — Transition DRAFT → SCHEDULED

- Depuis la fiche séance (`/operator.htmx.html`)
- Cliquer sur le bouton de transition d'état → **Programmée**

**Résultat attendu** : la séance passe en statut `scheduled`.

---

### Phase 2 — Verrouillage et ouverture (président)

**Se connecter** sur `/login.html` avec `president@ag-vote.local` / `President2024!`

#### Étape 2.1 — Transition SCHEDULED → FROZEN

- Depuis `/president.htmx.html`
- Sélectionner la séance E2E
- Cliquer sur le bouton de verrouillage → **Verrouillée**

**Résultat attendu** : la séance passe en statut `frozen`. La configuration (membres, résolutions) est figée.

#### Étape 2.2 — Transition FROZEN → LIVE

- Cliquer sur le bouton d'ouverture → **En cours**

**Résultat attendu** : la séance passe en statut `live`. L'horodatage d'ouverture est enregistré.

---

### Phase 3 — Présences (opérateur)

**Se connecter** avec `operator@ag-vote.local`

#### Étape 3.1 — Enregistrer les présences

- Aller sur `/attendance.htmx.html?meeting_id=eeeeeeee-e2e0-e2e0-e2e0-eeeeeeee0001`
- Cocher **présent** pour les 11 premiers membres (CM-001 à CM-011)
- Cocher **absent** pour CM-012 (M. Fontaine) — il a donné procuration

**Résultat attendu** :
- 11 présents, 1 absent
- Quorum atteint : 11/12 = 91,7 % (seuil requis : 50 %)
- Fontaine absent mais sa voix est portée par Dupont via procuration

---

### Phase 4 — Votes (opérateur + votant)

#### Étape 4.1 — Ouvrir le vote sur la résolution 1

En tant qu'**opérateur** :
- Aller sur `/operator_flow.htmx.html?meeting_id=eeeeeeee-e2e0-e2e0-e2e0-eeeeeeee0001`
- Ou depuis `/motions.htmx.html`, cliquer **Ouvrir** sur la résolution 1

**Résultat attendu** : la résolution 1 passe en statut `open`. Vote en cours.

#### Étape 4.2 — Voter en tant que votant

**Se connecter** dans un autre navigateur avec `votant@ag-vote.local` / `Votant2024!`

- Aller sur `/vote.htmx.html?meeting_id=eeeeeeee-e2e0-e2e0-e2e0-eeeeeeee0001`
- La résolution 1 est affichée
- Voter **POUR**

**Résultat attendu** : "Vote enregistré." Le bulletin est comptabilisé.

#### Étape 4.3 — Compléter les votes (opérateur)

En tant qu'opérateur, depuis la page des résolutions ou le panneau de vote :
- Enregistrer les votes des autres membres présents
- Scénario suggéré pour la résolution 1 :
  - **POUR** : Dupont, Lefebvre, Girard, Blanc, Rousseau, Mercier, Faure, André, Bonnet (9 voix + 1 procuration = 10)
  - **CONTRE** : Clément (1 voix)
  - **ABSTENTION** : Dumas (1 voix)

**Résultat attendu** : 10 POUR, 1 CONTRE, 1 ABSTENTION → majorité simple atteinte → **ADOPTÉE**

#### Étape 4.4 — Clôturer le vote sur la résolution 1

En tant qu'opérateur :
- Cliquer **Clôturer** sur la résolution 1

**Résultat attendu** :
- Résolution 1 → statut `closed`
- Décision : **Adoptée** (10/11 exprimés = 90,9 %, seuil > 50 %)
- Quorum vérifié : 11 présents sur 12 = 91,7 % (seuil 50 %)

#### Étape 4.5 — Répéter pour les résolutions 2 à 5

| Résolution | Scénario suggéré | Résultat attendu |
|---|---|---|
| **2. Budget** (maj. absolue) | 8 POUR, 2 CONTRE, 1 ABSTENTION | Adoptée (8/12 = 66,7 %, seuil > 50 % des éligibles) |
| **3. Rénovation** (maj. 2/3) | 7 POUR, 3 CONTRE, 1 ABSTENTION | Rejetée (7/11 = 63,6 %, seuil > 66,7 %) |
| **4. Convention** (maj. simple) | 10 POUR, 0 CONTRE, 1 ABSTENTION | Adoptée (10/10 = 100 %) |
| **5. Élection** (secret, maj. simple) | 6 POUR, 4 CONTRE, 1 ABSTENTION | Adoptée (6/10 = 60 %, seuil > 50 %) |

Pour chaque résolution : Ouvrir → Voter → Clôturer → Vérifier le résultat.

> **Note** : la résolution 5 est à bulletin secret — le détail des votes individuels ne doit pas être visible.

---

### Phase 5 — Contrôle (auditeur)

**Se connecter** avec `operator@ag-vote.local` (rôle assesseur sur cette séance, accès au contrôle)

#### Étape 5.1 — Vérifier les anomalies

- Aller sur `/trust.htmx.html`
- Sélectionner la séance E2E
- Vérifier : **0 anomalie** (vert)

**Résultat attendu** : aucune anomalie détectée. Tous les contrôles passent.

#### Étape 5.2 — Vérifier la cohérence des résultats

- Tableau des résolutions : chaque résolution a un résultat affiché
- Les votes correspondent aux bulletins enregistrés
- La colonne "Cohérent" affiche ✓ OK pour chaque résolution

**Résultat attendu** : toutes les résolutions sont cohérentes.

---

### Phase 6 — Clôture (président)

**Se connecter** avec `president@ag-vote.local`

#### Étape 6.1 — Transition LIVE → CLOSED

- Depuis `/president.htmx.html`
- Vérifier que toutes les résolutions sont clôturées
- Cliquer sur le bouton de clôture → **Terminée**

**Résultat attendu** : la séance passe en statut `closed`. Plus aucun vote n'est possible.

#### Étape 6.2 — Validation et signature du PV

- Aller sur `/validate.htmx.html?meeting_id=eeeeeeee-e2e0-e2e0-e2e0-eeeeeeee0001`
- Le bilan affiche : 12 membres, 11 présents, 5 résolutions, X adoptées, Y rejetées
- Les contrôles de validation passent → badge "Prêt"
- Saisir le nom du président : "Mme Dupont"
- Cliquer **Valider** (double confirmation demandée)

**Résultat attendu** :
- Séance validée → statut `validated`
- Horodatage de validation enregistré
- **Verrouillage définitif** : toute modification est refusée

#### Étape 6.3 — Vérifier le verrouillage

- Tenter de modifier une présence → refus (HTTP 409)
- Tenter d'ouvrir une résolution → refus (HTTP 409)

**Résultat attendu** : toute action de modification échoue avec un message clair.

---

### Phase 7 — Archivage et livrables (admin)

**Se connecter** avec `admin@ag-vote.local` / `Admin2024!`

#### Étape 7.1 — Archiver la séance

- Depuis `/admin.htmx.html`, ou par transition d'état
- Passer la séance de `validated` → `archived`

**Résultat attendu** : la séance passe en statut `archived`.

#### Étape 7.2 — Consulter le procès-verbal

- Aller sur `/report.htmx.html?meeting_id=eeeeeeee-e2e0-e2e0-e2e0-eeeeeeee0001`
- Le PV affiche :
  - participants et absents
  - procurations
  - résolutions et résultats détaillés (POUR / CONTRE / ABSTENTION)
  - quorum par résolution
  - règle de vote appliquée pour chaque décision

**Résultat attendu** : PV complet et conforme.

#### Étape 7.3 — Télécharger les exports

- Depuis la page rapport ou `/archives.htmx.html`
- Exporter :
  - PV (HTML)
  - Présences (CSV)
  - Votes (CSV)
  - Résolutions (CSV)
  - Journal d'audit (CSV)

**Résultat attendu** : tous les exports se téléchargent correctement.

#### Étape 7.4 — Vérifier dans les archives

- Aller sur `/archives.htmx.html`
- La séance "Conseil Municipal — Séance E2E" apparaît dans la liste
- Le SHA-256 du PV est affiché (preuve d'intégrité)

**Résultat attendu** : séance visible dans les archives avec le PV associé.

---

## Résumé du parcours

```
DRAFT ──→ SCHEDULED ──→ FROZEN ──→ LIVE ──→ CLOSED ──→ VALIDATED ──→ ARCHIVED
  │           │            │         │          │           │            │
  │           │            │         │          │           │            └─ PV + exports
  │           │            │         │          │           └─ Verrouillage définitif
  │           │            │         │          └─ Clôture des votes
  │           │            │         └─ Présences + votes (5 résolutions)
  │           │            └─ Configuration figée
  │           └─ Séance programmée
  └─ Création et préparation
```

| Phase | Acteur | Action principale |
|---|---|---|
| 1. Préparation | Opérateur | Vérifier données, DRAFT → SCHEDULED |
| 2. Ouverture | Président | SCHEDULED → FROZEN → LIVE |
| 3. Présences | Opérateur | Enregistrer 11/12 présents |
| 4. Votes | Opérateur + Votant | 5 résolutions : ouvrir, voter, clôturer |
| 5. Contrôle | Assesseur | Vérifier anomalies et cohérence |
| 6. Clôture | Président | LIVE → CLOSED → VALIDATED |
| 7. Archivage | Admin | VALIDATED → ARCHIVED, PV, exports |

---

## Réinitialiser le scénario

Pour repartir de zéro :

```bash
sudo bash database/setup.sh --seed
```

Le seed E2E est idempotent : il remet la séance en `draft`, supprime les bulletins et présences existants, et recrée toutes les données proprement.

---

## Références

- `database/seeds/04_e2e.sql` — Données du scénario
- `database/seeds/02_test_users.sql` — Comptes de test
- `docs/RECETTE_DEMO.md` — Scénario de démonstration rapide (10 min)
- `docs/UTILISATION_LIVE.md` — Guide opérationnel des séances
