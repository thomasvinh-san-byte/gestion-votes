# AG-Vote — Directive de projet

> **Source de vérité** : (wireframe interactif React, 2 829 lignes, 16 pages, 203 interactions)

---

## 1. Cadre méthodologique

### 1.1 Principe fondamental

Le wireframe est la **seule référence**. Toute page, composant, interaction, flux ou règle métier visible dans le wireframe doit être implémenté. Rien n'est ajouté, rien n'est omis.

### 1.2 Étapes de développement

| # | Phase | Entrée | Sortie |
|---|-------|--------|--------|
| 1 | Analyse du wireframe | `ag_vote_v3_19_2.html` | Spécifications fonctionnelles (ce document) |
| 2 | Architecture frontend | Spécifications | Arborescence composants, choix techniques |
| 3 | Modèles de données | Spécifications | Schéma BDD, migrations |
| 4 | API backend | Modèles + flux wireframe | Endpoints REST + WebSocket |
| 5 | Implémentation frontend | Wireframe + API | Pages et composants React |
| 6 | Validation | Wireframe vs rendu | Recette visuelle et fonctionnelle |

### 1.3 Règles impératives

- **Fidélité absolue** au wireframe — pas de créativité graphique non justifiée.
- **Exception unique** : la page d'accueil nécessite de vrais champs utilisateur / mot de passe (le wireframe simule un `onLogin`).
- **Traçabilité** : chaque ambiguïté du wireframe est documentée avec l'interprétation retenue.
- **Cohérence** : les 16 pages partagent le même design system, la même sidebar, le même header.
- **Communication** : si un point critique manque de précision, proposer une interprétation raisonnable et la signaler.

---

## 2. Contexte métier

### 2.1 Domaine

Application de **vote électronique pour assemblées générales de copropriété**, conforme à la législation française.

### 2.2 Cadre juridique

Le wireframe référence explicitement ces textes. L'application **doit** les implémenter :

| Texte | Objet | Où dans le wireframe |
|-------|-------|---------------------|
| **Loi du 10 juillet 1965** (art. 22, 24, 25, 25-1, 26, 26-1) | Régimes de majorité | Wizard, Operator, Hub, Aide |
| **Décret du 17 mars 1967** (art. 9, 47) | Délais convocations (21 jours), formalités | Wizard, Séances |
| **Délibération CNIL n°2019-053** | Vote électronique — 3 niveaux de sécurité, séparation identité/bulletin | Paramètres, Aide, FAQ |
| **Règlement eIDAS** | Signature électronique (avancée / qualifiée) | PostSession (signature PV) |
| **Loi n°2005-102, Décret n°2019-768** (art. 47) | Accessibilité numérique, déclaration obligatoire | Paramètres (déclaration RGAA) |
| **RGPD** | Protection des données personnelles | Paramètres, mentions légales |

### 2.3 Régimes de majorité (logique métier critique)

| Régime | Règle de calcul | Cas d'usage |
|--------|----------------|-------------|
| **Majorité simple (art. 24)** | >50% des voix des présents et représentés | Approbation comptes, budget prévisionnel |
| **Majorité absolue (art. 25)** | >50% des voix de tous les copropriétaires | Élection syndic, travaux importants |
| **Double majorité (art. 26)** | ≥2/3 des voix de tous les copropriétaires | Modification règlement, vente parties communes |
| **Unanimité (art. 26-1)** | 100% des voix de tous les copropriétaires | Aliénation parties communes, changement destination |
| **Passerelle art. 25-1** | Si art. 25 échoue mais ≥1/3 des voix → 2nd vote en art. 24 | Renvoi automatique proposé par l'opérateur |

### 2.4 Quorum

- **Calcul** : nombre de présents + représentés (par procuration/correspondance) vs nombre d'inscrits
- **Seuil par défaut** : 50% + 1
- **Quorum non atteint** → modale avec 3 options : Reporter (2e convocation, quorum réduit à 33%), Suspendre 30 min, Continuer sous réserve (risque juridique)
- **2e convocation** : quorum automatiquement réduit

---

## 3. Rôles et permissions

Le wireframe expose ces rôles dans la sidebar, les paramètres et les écrans de pilotage :

| Rôle | Accès | Description |
|------|-------|-------------|
| **Administrateur** | Tout | Configuration système, gestion utilisateurs, paramètres CNIL/eIDAS |
| **Gestionnaire** | Séances + membres + archives + stats | Crée et gère les séances, importe les membres |
| **Opérateur** | Pilotage en direct | Ouvre/ferme les votes, proclame, gère la parole |
| **Président** | Séance en cours | Voix prépondérante (art. 22), signe le PV |
| **Secrétaire** | Séance en cours | Rédige le PV, gère l'émargement |
| **Scrutateur** | Séance en cours | Supervise le dépouillement, contre-signe |
| **Votant** | Écran tablette | Vote sur chaque résolution, demande la parole |

> **Matrice de permissions** : à définir en phase 2, dérivée des accès constatés dans le wireframe (quelles pages sont accessibles, quelles actions sont disponibles par rôle).

---

## 4. Pages et flux utilisateur

### 4.1 Inventaire des 16 pages

| Page | Route suggérée | Rôle principal | Fonction |
|------|---------------|----------------|----------|
| Landing | `/` | Tous | Connexion, présentation fonctionnalités |
| Dashboard | `/dashboard` | Gestionnaire+ | Vue d'ensemble : KPI, séances, tâches |
| Séances | `/seances` | Gestionnaire+ | Liste paginée, filtres, tri |
| Wizard | `/seances/new` | Gestionnaire+ | Création séance en 4 étapes |
| Hub | `/seances/:id` | Gestionnaire+ | Fiche séance guidée (6 étapes) |
| Operator | `/seances/:id/live` | Opérateur | Pilotage vote en direct |
| PostSession | `/seances/:id/cloture` | Gestionnaire+ | PV, signature, envoi, archivage |
| Votant | `/vote/:token` | Votant | Interface tablette de vote |
| Ecran | `/seances/:id/ecran` | Projection | Affichage salle (responsive) |
| Audit | `/audit` | Administrateur | Journal traçabilité avec timeline |
| Archives | `/archives` | Gestionnaire+ | Consultation séances archivées |
| Membres | `/membres` | Gestionnaire+ | Gestion membres, import CSV |
| Utilisateurs | `/utilisateurs` | Administrateur | Gestion comptes et rôles |
| Paramètres | `/parametres` | Administrateur | Config système, CNIL, eIDAS, RGAA |
| Stats | `/stats` | Gestionnaire+ | Tableaux de bord analytiques |
| Aide | `/aide` | Tous | FAQ (23 questions), visites guidées |

### 4.2 Flux principal (cycle de vie d'une AG)

```
Créer séance (Wizard 4 étapes)
    → Fiche séance (Hub — 6 étapes guidées)
        → 1. Préparer (résolutions, documents)
        → 2. Convoquer (envoi, suivi, rappels)
        → 3. Émargement (présences, procurations, quorum)
        → 4. Voter (pilotage en direct — PageOperator)
            ↳ Ouvrir le vote → Attendre → Fermer → Proclamer
            ↳ Passerelle art. 25-1 si applicable
            ↳ Comptage manuel si nécessaire
        → 5. Clôturer (PV, signature eIDAS, envoi)
        → 6. Archiver (ZIP horodaté, SHA-256)
```

### 4.3 Flux secondaires

- **Quorum non atteint** → Reporter / Suspendre / Continuer sous réserve
- **Égalité Pour/Contre** → Voix prépondérante du président (art. 22)
- **Vote par correspondance** → Bulletins intégrés avant ouverture du scrutin
- **Demande de parole** → File d'attente, accord/refus par l'opérateur
- **Session timeout** → Bannière d'alerte, prolongation ou déconnexion

---

## 5. Modèles de données

Dérivés des données affichées et manipulées dans le wireframe :

### 5.1 Entités principales

```
Utilisateur
├── id, email, mot_de_passe (hashé), nom, prénom
├── rôle (enum: admin, gestionnaire, opérateur)
├── avatar, dernière_connexion
└── actif (bool)

Séance
├── id, titre, type (enum: AG ordinaire, AG extra, conseil syndical)
├── date_heure, lieu, fuseau_horaire (parmi 60 fuseaux dont DOM-TOM)
├── statut (enum: brouillon, convocations, en_cours, clôturée, pv_envoyé, archivée)
├── quorum_requis (nombre), quorum_type (enum: standard, réduit_2e_convocation)
├── participants_inscrits, voix_totales
├── créé_par (→ Utilisateur), créé_le, modifié_le
└── est_2e_convocation (bool), séance_parent_id (nullable → Séance)

Résolution
├── id, séance_id (→ Séance), ordre, titre, description
├── majorité (enum: art24, art25, art26, art26_1)
├── vote_secret (bool)
├── statut (enum: à_venir, en_cours, close, proclamée)
├── résultat_pour, résultat_contre, résultat_abstention
├── résultat (enum: adoptée, rejetée, null)
├── passerelle_25_1_applicable (bool), passerelle_activée (bool)
└── comptage_manuel (bool)

Participant
├── id, séance_id (→ Séance), membre_id (→ Membre)
├── statut_présence (enum: absent, présent, procuration, correspondance)
├── procuration_vers (nullable → Participant)
├── heure_émargement, signature_émargement
└── voix (nombre — clé de répartition)

Membre
├── id, nom, prénom, email, téléphone
├── lot(s), tantièmes, quote_part
├── actif (bool)
└── importé_le, source (enum: manuel, csv)

Vote (bulletin)
├── id, résolution_id (→ Résolution)
├── choix (enum: pour, contre, abstention)
├── horodatage, empreinte_sha256
├── participant_id (→ Participant) — SÉPARÉ (CNIL : séparation identité/bulletin)
└── type (enum: direct, correspondance, procuration)

ProcèsVerbal
├── id, séance_id (→ Séance)
├── contenu (texte/PDF), généré_le
├── signataire_président, signataire_secrétaire, signataire_scrutateurs[]
├── type_signature (enum: avancée_eidas, qualifiée_eidas)
├── signature_hash, signé_le
├── envoyé_le, envoyé_à[] (liste emails)
└── archivé_le

ÉvénementAudit
├── id, séance_id (nullable → Séance)
├── horodatage, catégorie (enum: auth, vote, quorum, procuration, émargement, système, export, signature, paramètre)
├── sévérité (enum: info, succès, avertissement, erreur)
├── action, détail, utilisateur
├── empreinte_sha256 (intégrité)
└── adresse_ip

Notification
├── id, utilisateur_id (→ Utilisateur)
├── message, type, lien_page
├── lue (bool), créée_le
└── urgence (enum: normal, important, critique)

ParamètresSystème
├── niveau_cnil (enum: 1, 2, 3)
├── séparation_identité_bulletin (bool)
├── type_signature_eidas (enum: avancée, qualifiée)
├── durée_session_minutes, délai_convocation_jours
├── fuseau_horaire_défaut
├── smtp_config, logo, nom_entité
└── déclaration_rgaa (texte, taux, date)

TemplateCourriel
├── id, type (enum: convocation, rappel, pv, 2e_convocation, résultat)
├── sujet, corps (HTML avec variables)
└── modifié_le
```

### 5.2 Point critique : séparation identité / bulletin (CNIL)

Le wireframe montre explicitement un toggle « Séparation identité / bulletin — Activée (CNIL) ». Cela implique :

- La table `Vote` ne doit **pas** contenir de FK directe vers `Participant` en mode activé
- Utiliser une table intermédiaire d'émargement (`a_voté` : oui/non) sans lien avec le choix
- L'audit enregistre « Participant X a voté » et « Bulletin déposé » comme 2 événements distincts, sans corrélation

---

## 6. API — Endpoints

### 6.1 Authentification

| Méthode | Endpoint | Description |
|---------|----------|-------------|
| POST | `/api/auth/login` | Connexion (email + mot de passe) → JWT |
| POST | `/api/auth/logout` | Déconnexion, invalidation token |
| POST | `/api/auth/refresh` | Renouvellement session |
| GET | `/api/auth/me` | Profil utilisateur connecté |

### 6.2 Séances (CRUD + actions)

| Méthode | Endpoint | Description |
|---------|----------|-------------|
| GET | `/api/seances` | Liste paginée + filtres (statut, date, recherche) |
| POST | `/api/seances` | Créer une séance (wizard) |
| GET | `/api/seances/:id` | Détail séance (hub) |
| PUT | `/api/seances/:id` | Modifier séance |
| DELETE | `/api/seances/:id` | Supprimer (brouillon uniquement) |
| POST | `/api/seances/:id/start` | Démarrer la séance (vérifie quorum) |
| POST | `/api/seances/:id/suspend` | Suspendre (30 min) |
| POST | `/api/seances/:id/close` | Clôturer la séance |
| POST | `/api/seances/:id/archive` | Archiver (génère ZIP + SHA-256) |
| POST | `/api/seances/:id/2e-convocation` | Générer la 2e convocation (quorum réduit) |

### 6.3 Convocations

| Méthode | Endpoint | Description |
|---------|----------|-------------|
| POST | `/api/seances/:id/convocations/send` | Envoyer les convocations |
| POST | `/api/seances/:id/convocations/remind` | Envoyer les rappels |
| GET | `/api/seances/:id/convocations/status` | Statut d'envoi par participant |

### 6.4 Résolutions

| Méthode | Endpoint | Description |
|---------|----------|-------------|
| GET | `/api/seances/:id/resolutions` | Liste ordonnée |
| POST | `/api/seances/:id/resolutions` | Ajouter une résolution |
| PUT | `/api/resolutions/:id` | Modifier |
| DELETE | `/api/resolutions/:id` | Supprimer |
| PUT | `/api/seances/:id/resolutions/reorder` | Réordonner |

### 6.5 Présences et émargement

| Méthode | Endpoint | Description |
|---------|----------|-------------|
| GET | `/api/seances/:id/presences` | Liste des participants + statut |
| PUT | `/api/seances/:id/presences/:participantId` | Modifier statut (présent, procuration…) |
| POST | `/api/seances/:id/presences/emarger` | Émargement (horodaté) |
| GET | `/api/seances/:id/quorum` | Calcul quorum temps réel |

### 6.6 Vote en direct

| Méthode | Endpoint | Description |
|---------|----------|-------------|
| POST | `/api/resolutions/:id/vote/open` | Ouvrir le scrutin |
| POST | `/api/resolutions/:id/vote/close` | Fermer le scrutin |
| POST | `/api/resolutions/:id/vote` | Soumettre un bulletin (pour/contre/abstention) |
| POST | `/api/resolutions/:id/proclaim` | Proclamer le résultat |
| POST | `/api/resolutions/:id/passerelle-25-1` | Déclencher le 2nd vote en art. 24 |
| POST | `/api/resolutions/:id/comptage-manuel` | Saisir résultat manuel |
| **WS** | `/ws/seances/:id/live` | **WebSocket** — diffusion temps réel : progression votes, quorum, résultats, demandes de parole |

### 6.7 Procès-verbal

| Méthode | Endpoint | Description |
|---------|----------|-------------|
| POST | `/api/seances/:id/pv/generate` | Générer le PV (PDF) |
| GET | `/api/seances/:id/pv` | Télécharger le PV |
| POST | `/api/seances/:id/pv/sign` | Signer (eIDAS avancée ou qualifiée) |
| POST | `/api/seances/:id/pv/send` | Envoyer le PV aux participants |

### 6.8 Membres

| Méthode | Endpoint | Description |
|---------|----------|-------------|
| GET | `/api/membres` | Liste paginée + recherche |
| POST | `/api/membres` | Ajouter |
| PUT | `/api/membres/:id` | Modifier |
| DELETE | `/api/membres/:id` | Supprimer |
| POST | `/api/membres/import-csv` | Import CSV (gestion doublons) |
| GET | `/api/membres/export` | Export CSV |

### 6.9 Autres

| Méthode | Endpoint | Description |
|---------|----------|-------------|
| GET | `/api/audit` | Journal d'audit (paginé, filtrable par catégorie/sévérité/date) |
| GET | `/api/audit/:id` | Détail événement (SHA-256 complet) |
| GET | `/api/stats` | Données statistiques agrégées |
| GET | `/api/archives` | Liste des séances archivées |
| GET | `/api/archives/:id/download` | Télécharger archive ZIP |
| GET/PUT | `/api/parametres` | Lire / modifier les paramètres système |
| GET/PUT | `/api/templates-courriel` | Lire / modifier les templates |
| GET | `/api/notifications` | Notifications de l'utilisateur |
| PUT | `/api/notifications/read-all` | Tout marquer comme lu |
| GET | `/api/utilisateurs` | Liste des utilisateurs |
| POST/PUT/DELETE | `/api/utilisateurs/:id` | CRUD utilisateurs |

---

## 7. Exports et fichiers

Le wireframe montre des exports dans plusieurs pages :

| Format | Contenu | Déclenché depuis |
|--------|---------|-----------------|
| PDF | Procès-verbal | PostSession |
| PDF | Feuille d'émargement | Hub (étape Émargement) |
| PDF | Synthèse votes par correspondance | Hub |
| CSV | Liste des présences | Hub, Membres |
| CSV | Détail des votes (anonymisé si CNIL) | PostSession, Stats |
| CSV | Résultats par clé de répartition | PostSession |
| CSV | Journal d'audit | Audit |
| ZIP | Archive complète (PV + émargement + votes + audit) | PostSession → Archiver |

---

## 8. Templates courriel

Le wireframe montre 5 templates éditables dans les Paramètres :

| Template | Variables attendues | Moment d'envoi |
|----------|-------------------|----------------|
| Convocation | `{nom}`, `{date}`, `{lieu}`, `{ordre_du_jour}`, `{lien_vote}` | Envoi convocations |
| Rappel | `{nom}`, `{date}`, `{jours_restants}` | J-3 avant AG |
| 2e convocation | `{nom}`, `{date}`, `{nouveau_quorum}` | Après report pour quorum |
| Résultat vote | `{nom}`, `{résolution}`, `{résultat}` | Après proclamation |
| PV distribué | `{nom}`, `{date_ag}`, `{lien_pv}` | Envoi du PV signé |

---

## 9. Temps réel (WebSocket)

Le wireframe montre des interactions en temps réel sur 3 pages simultanées :

| Page | Données temps réel |
|------|-------------------|
| **Operator** | Progression votes (jauge), votes entrants, demandes de parole |
| **Votant** | Timer dégressif, état du vote (ouvert/fermé), résolution en cours |
| **Ecran** | Résultats en direct, barre quorum, animation résultat proclamé |

**Événements WebSocket** à implémenter :

```
vote:open        → { resolutionId, timer }
vote:close       → { resolutionId }
vote:cast        → { resolutionId, totalVotes, progress }
vote:proclaim    → { resolutionId, result, pour, contre, abstention }
quorum:update    → { presents, required, percentage }
parole:request   → { participantId, name }
parole:grant     → { participantId }
session:start    → {}
session:suspend  → { duration }
session:close    → {}
```

---

## 10. Sécurité

### 10.1 Exigences dérivées du wireframe

| Exigence | Source wireframe | Implémentation |
|----------|-----------------|----------------|
| SHA-256 sur chaque bulletin | PageAudit, Paramètres | Hacher `vote_id + choix + horodatage + sel` |
| SHA-256 sur chaque événement audit | PageAudit (détail) | Chaînage : `hash(événement_N) = SHA-256(données + hash_N-1)` |
| Séparation identité / bulletin | Paramètres (toggle CNIL) | Tables séparées, pas de jointure directe |
| Signature eIDAS | PostSession | Intégration prestataire de signature (API externe) |
| Lien unique de vote | Hub (convocations) | Token JWT à usage unique, durée limitée |
| Session timeout | Header (bannière) | Inactivité → alerte à 2 min, déconnexion auto |
| 3 niveaux CNIL | Paramètres | Adapter la rigueur crypto selon le niveau choisi |

### 10.2 Recommandations (non visibles dans le wireframe mais nécessaires)

- Mots de passe : hachage bcrypt, politique de complexité
- JWT : access token courte durée + refresh token
- HTTPS obligatoire
- Rate limiting sur `/auth/login`
- CORS restrictif
- Logs d'accès (contribuent au journal d'audit)

---

## 11. Design system

### 11.1 Identité visuelle « Acte Officiel »

Le wireframe définit un design system complet avec 52 tokens CSS :

| Catégorie | Valeurs clés |
|-----------|-------------|
| **Polices** | Bricolage Grotesque (UI), Fraunces (display), JetBrains Mono (code/hash) |
| **Couleurs** | Fond parchemin `#EDECE6`, surface `#FAFAF7`, accent bleu encre `#1650E0` |
| **Sidebar** | Quasi-noir `#0C1018`, rail 58px, expanded 252px |
| **Sémantique** | Danger `#C42828`, Succès `#0B7A40`, Warning `#B56700`, Purple `#5038C0` |
| **Ombres** | 4 niveaux (xs → lg) + focus ring |
| **Rayons** | 6px, 8px, 10px, 999px (pill) |
| **Mode sombre** | Complet — tous les tokens redéfinis sous `[data-theme="dark"]` |

### 11.2 Composants réutilisables (22)

À reproduire fidèlement depuis le wireframe :

| Composant | Usage | Occurrences |
|-----------|-------|-------------|
| `LI` (Lucide Icons) | Icônes partout | ~126 |
| `Modal` | Dialogues, confirmations | ~20 |
| `HelpTip` | Info-bulles contextuelles | ~24 |
| `Toast` | Feedback actions | ~156 appels |
| `Popover` | Menus contextuels | ~4 |
| `Donut` | Graphiques circulaires | ~2 |
| `MiniBar` | Graphiques barres | Stats |
| `TZPicker` | Sélection fuseau horaire (60 fuseaux dont DOM-TOM) | ~1 |
| `GuidedTour` | Visite guidée interactive | 7 tours, 23 étapes |
| `DeviceBar` | Prévisualisation tablette/projecteur | ~4 |
| `Breadcrumb` | Fil d'Ariane | ~2 |
| `Pg` (Pagination) | Navigation listes | ~6 |
| `GlobalSearch` | Recherche transversale (Ctrl+K) | ~1 |
| `ConfirmDialog` | Confirmation destructrice | ~2 |
| `ScrollTop` | Retour en haut | ~1 |
| `CTA` | Boutons d'action principaux | ~10 |
| `Stepper` | Étapes wizard | Wizard |
| `TimeInput` | Saisie horaire | Wizard |
| `PH` (Placeholder) | États vides / skeleton | ~22 |
| `Av` (Avatar) | Avatars utilisateurs | ~2 |
| `Tip` (Tooltip) | Tooltips simples | ~4 |

### 11.3 Patterns UX à respecter (42 identifiés)

**Navigation** : sidebar collapsible + rail, breadcrumb, recherche globale Ctrl+K, pagination, scroll-to-top

**Feedback** : toasts (succès/erreur/info/warn), modales de confirmation, barres de progression, timers en direct, empty states

**Formulaires** : validation inline, champs obligatoires (`*` + `aria-required`), selects, chips toggle, help tips

**Données** : tableaux avec filtres/tri, KPI cards avec hover, graphiques Donut + MiniBar, tags statut colorés avec icônes

**Accessibilité** : skip link, aria-labels, focus trap modales, `aria-live` régions, `prefers-reduced-motion`, RGAA 97%

**Responsive** : sidebar rail mobile, bottom nav mobile, DeviceBar pour prévisualisation

**Extras** : visite guidée 7 parcours, mode clair/sombre, session timeout, notifications en temps réel

---

## 12. Accessibilité (RGAA 97%)

Le wireframe implémente déjà 97% de conformité RGAA. Le code production doit maintenir ou dépasser ce niveau :

| Critère | Implémentation wireframe |
|---------|-------------------------|
| RGAA 3.1 — Couleur seule | Icônes systématiques sur chaque tag statut |
| WCAG 2.4.13 — Focus visible | Double anneau (surface + accent) en mode clair et sombre |
| WCAG 2.3.3 — Reduced motion | `prefers-reduced-motion` désactive toutes animations |
| WCAG 1.3.5 — Champs requis | `aria-required="true"` sur les 13+ champs obligatoires |
| Focus trap | Toutes les modales piègent le focus |
| Skip link | Lien « Aller au contenu principal » |
| aria-live | Régions polite sur toasts et résultats temps réel |
| Déclaration RGAA | Page complète dans Paramètres (obligatoire art. 47) |

---

## 13. Fuseaux horaires

Le wireframe supporte 60 fuseaux dont tous les DOM-TOM français :

| Territoire | Fuseau | UTC |
|-----------|--------|-----|
| Métropole | Europe/Paris | +1/+2 |
| Réunion | Indian/Reunion | +4 |
| Mayotte | Indian/Mayotte | +3 |
| Nouvelle-Calédonie | Pacific/Noumea | +11 |
| Martinique | America/Martinique | −4 |
| Guadeloupe | America/Guadeloupe | −4 |
| Guyane | America/Guyane | −3 |
| Saint-Pierre-et-Miquelon | America/Miquelon | −3 |
| Tahiti | Pacific/Tahiti | −10 |
| Wallis-et-Futuna | Pacific/Wallis | +12 |
| TAAF (Kerguelen) | Indian/Kerguelen | +5 |

Le composant `TZPicker` offre une recherche textuelle parmi ces 60 fuseaux.

---

## 14. Stack technique suggérée

Le wireframe utilise React + Babel. Stack recommandée pour la production :

| Couche | Technologie | Justification |
|--------|------------|---------------|
| **Frontend** | React 18+ (Vite) | Cohérence avec le wireframe |
| **Style** | CSS Modules ou Tailwind | Reproduire les 52 tokens du design system |
| **État** | Zustand ou React Context | État global léger (auth, thème, notifications) |
| **Temps réel** | Socket.IO ou native WebSocket | Vote en direct, 3 écrans simultanés |
| **Backend** | Node.js (Express/Fastify) ou Django/FastAPI | API REST + WebSocket |
| **BDD** | PostgreSQL | Relations complexes, intégrité transactionnelle |
| **Auth** | JWT (access + refresh) | Session timeout visible dans le wireframe |
| **Crypto** | Node `crypto` / Python `hashlib` | SHA-256 bulletins et audit |
| **Email** | Nodemailer / SendGrid | Templates éditables, envoi convocations |
| **PDF** | Puppeteer ou WeasyPrint | Génération PV, feuilles émargement |
| **Signature** | API eIDAS externe (Yousign, DocuSign) | Signature avancée/qualifiée |
| **Stockage** | S3-compatible | Archives ZIP, PV signés |

> Ce choix est une suggestion. L'existant technique du projet prime.

---

## 15. Validation et recette

### 15.1 Checklist de conformité au wireframe

Pour chaque page, vérifier :

- [ ] Layout identique (sidebar, header, contenu)
- [ ] Tous les composants présents (boutons, cartes, tableaux, modales)
- [ ] Toutes les interactions fonctionnelles (clics, hovers, toggles)
- [ ] États intermédiaires (chargement, vide, erreur)
- [ ] Données cohérentes avec le modèle
- [ ] Feedback utilisateur (toasts, confirmations)
- [ ] Accessibilité maintenue (focus, aria, contraste)
- [ ] Mode sombre fonctionnel
- [ ] Responsive (mobile, tablette si applicable)

### 15.2 Tests fonctionnels prioritaires

1. **Parcours complet** : créer séance → convoquer → émarger → voter → proclamer → signer PV → archiver
2. **Quorum non atteint** : tester les 3 options (reporter, suspendre, continuer)
3. **Passerelle art. 25-1** : vote art. 25 échoue → proposition art. 24
4. **Égalité** : voix prépondérante du président
5. **Vote en direct** : 3 écrans simultanés (opérateur + votant + projection)
6. **Séparation identité/bulletin** : vérifier l'impossibilité de lier un votant à son choix
7. **SHA-256** : vérifier l'intégrité de chaque bulletin et événement audit
8. **Session timeout** : bannière + déconnexion automatique
9. **Import CSV** : fichier valide, doublons, erreurs
10. **Export ZIP** : archive complète, intègre, téléchargeable

---

## 16. Ambiguïtés du wireframe et interprétations

| Point | Constat dans le wireframe | Interprétation retenue |
|-------|--------------------------|----------------------|
| Authentification | Simulée (`onLogin` sans formulaire réel) | Implémenter email + mot de passe + JWT |
| Mots de passe | Aucun champ visible | Ajouter reset password, politique complexité |
| Multi-copropriétés | Une seule entité visible | V1 mono-copropriété, préparer multi |
| Drag & drop résolutions | Absent du wireframe | Non implémenté en V1 (réordonner via boutons ↑↓) |
| Hors-ligne | Absent du wireframe | Non implémenté en V1 |
| Notifications push | Polling simulé dans le wireframe | WebSocket en production |
| Tri tableaux | Absent du wireframe (en-têtes non cliquables) | Non implémenté en V1 sauf si trivial |
| Données de démo | Noms fictifs (« Utilisateur A ») | Remplacer par seed de données réalistes |
