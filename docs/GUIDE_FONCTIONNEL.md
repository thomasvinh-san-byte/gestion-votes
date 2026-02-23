# Guide fonctionnel — AG-VOTE

Guide exhaustif de toutes les pages, fonctionnalités et pistes d'amélioration.

---

## Architecture générale

AG-VOTE est une application web de gestion de séances de vote formelles (assemblées générales, conseils, bureaux). Elle couvre le cycle complet : préparation, vote en direct, dépouillement, validation, archivage.

**Stack** : PHP 8.3 / PostgreSQL / Vanilla JS / CSS custom design system / Docker
**Auth** : RBAC à deux niveaux (rôles système + rôles par séance)
**Multi-tenant** : isolation complète par `tenant_id` sur chaque table

### Rôles système

| Rôle | Accès |
|------|-------|
| `admin` | Tout — configuration, utilisateurs, politiques |
| `operator` | Gestion séances, membres, résolutions, émargement |
| `auditor` | Lecture + contrôles d'intégrité |
| `viewer` | Lecture seule |

### Rôles par séance (meeting_roles)

`president`, `secretary`, `assessor`, `voter` — attribués par séance, permettent des permissions contextuelles (ex: un `viewer` système peut être `president` d'une séance spécifique).

### Navigation (sidebar)

```
Préparation          Séance en direct       Après la séance
├── Séances          ├── Voter              ├── Clôture & PV
├── Membres          └── Projection         ├── Exports
└── Fiche séance                            └── Archives

Contrôle             Système                Aide
├── Audit            └── Configuration      └── Guide & FAQ
└── Statistiques
```

---

## 1. Page d'accueil (`index.html`)

**Accès** : public, pas d'authentification
**Rôle** : vitrine / marketing

### Fonctionnalités actuelles
- Présentation du produit avec sections hero, fonctionnalités, sécurité
- Navigation responsive avec hamburger menu mobile
- Liens vers `/login.html`
- Mode clair/sombre

### Améliorations possibles
- **Démo interactive** : lien vers une instance de démonstration pré-remplie
- **Vidéo de présentation** : walkthrough en 2 minutes
- **Témoignages / chiffres clés** : nombre de votes traités, clients
- **Changelog public** : "Quoi de neuf" visible depuis la landing

---

## 2. Connexion (`login.html`)

**Accès** : public
**Rôle** : authentification

### Fonctionnalités actuelles
- Formulaire email + mot de passe
- Bascule thème clair/sombre
- Redirection post-login vers la page demandée
- CSRF token automatique

### Améliorations possibles
- **"Mot de passe oublié"** : flux de reset par email (actuellement absent)
- **2FA / TOTP** : second facteur pour les rôles admin/operator
- **SSO / OAuth** : intégration LDAP, SAML, OpenID Connect pour les collectivités
- **Tentatives ratées visibles** : "Trop de tentatives, réessayez dans X secondes" côté UI
- **QR code de connexion rapide** : scan depuis mobile pour les votants

---

## 3. Séances (`meetings.htmx.html`)

**Accès** : `admin`, `operator`, `auditor`, `viewer`
**Rôle** : CRUD des séances, vue d'ensemble

### Fonctionnalités actuelles
- **Barre de stats** : séances en cours, planifiées, brouillons, total, résolutions, participation moyenne
- **Création rapide** : titre + date + type (AG ordinaire, extraordinaire, conseil, bureau, autre) + pièces jointes PDF
- **Filtrage** : onglets Toutes / En cours / Planifiées / Brouillons / Archives
- **Recherche** : filtre par titre en temps réel (debounce 250ms)
- **Tri** : par statut, date (asc/desc), titre A-Z
- **Vue grille** : cards avec badge statut, type, date, nombre de résolutions et présents
- **Vue calendrier** : calendrier mensuel avec événements colorés par statut
- **Édition** : modal pour modifier titre, date, type
- **Suppression** : modal de confirmation (brouillons uniquement)
- **Pagination** : "Afficher plus" par blocs de 12
- **Glisser-déposer PDF** : zone de drop avec validation type/taille

### Cards de séance
- Variant `.is-live` : bordure verte + glow animé + fond dégradé
- Variant `.is-draft` : bordure pointillée + opacité réduite
- Variant `.is-archived` : opacité réduite, fond neutre
- Boutons contextuels : Rejoindre (live), Ouvrir (draft/scheduled)
- Actions secondaires : Modifier, Supprimer (selon statut)

### Améliorations possibles
- **Duplication** : bouton "Dupliquer cette séance" pour créer une nouvelle séance avec les mêmes résolutions/politiques
- **Templates de séance** : modèles réutilisables (ex: "AG annuelle type")
- **Import ordre du jour** : upload d'un fichier structuré (CSV/JSON) pour pré-remplir les résolutions
- **Notifications** : badge "nouvelle activité" sur les séances en cours
- **Export liste** : CSV/Excel de la liste des séances filtrées
- **Recherche avancée** : filtre par date, type, nombre de résolutions
- **Drag & drop réordonnement** : changer l'ordre des séances dans la vue grille
- **Aperçu rapide** : survol d'une card pour voir un résumé sans ouvrir

---

## 4. Fiche séance / Console opérateur (`operator.htmx.html`)

**Accès** : `admin`, `operator`
**Rôle** : page principale pendant une séance — pilotage complet

### Fonctionnalités actuelles
- **Barre de séance** : sélecteur de séance + badge statut + rôle utilisateur + indicateur de santé (pré-requis)
- **Onglets** :
  - **Résolutions** : CRUD des motions, ouverture/fermeture du vote, résultats en temps réel, comptage manuel, politique de vote/quorum par résolution
  - **Émargement** : liste des membres avec statut (présent, distant, procuration, excusé), check-in/check-out, recherche, statistiques de présence
  - **Demandes de parole** : file d'attente, passage en parole, fin de parole, annulation
  - **Tableau de bord** : vue synthétique de la séance (stats, progression)
- **Transitions d'état** : draft → scheduled → frozen → live → paused → closed → validated → archived
- **Actions rapides** depuis la barre : démarrer/suspendre/reprendre/clôturer la séance

### Résolutions (onglet motions)
- Ajout rapide (titre seul) ou détaillé (description, vote secret, politique)
- Réordonnement par drag & drop
- Ouverture/fermeture individuelle du vote
- Résultats : pour/contre/abstention avec poids et pourcentages
- Comptage manuel : saisie papier pour les votes non-électroniques
- Décision : adopté/rejeté/ajourné
- Politique de vote configurable par résolution (majorité simple, absolue, 2/3, unanimité)
- Politique de quorum configurable par résolution

### Émargement (onglet attendance)
- Liste complète des membres avec filtres par statut
- Check-in en un clic (présent / distant)
- Gestion des procurations (mandant → mandataire)
- Recherche par nom
- Compteurs temps réel : présents, distants, procurations, excusés, absents

### Améliorations possibles
- **Vote en lot** : ouvrir/fermer plusieurs résolutions en série automatiquement
- **Chronomètre** : temps de parole par orateur et par résolution
- **Notes de séance** : champ libre pour le secrétaire (intégré au PV)
- **Mode "salle"** : QR code géant projetable pour le check-in des membres
- **Alertes quorum** : notification temps réel quand le quorum est atteint/perdu
- **Import résolutions** : depuis un fichier CSV ou copier/coller structuré
- **Résolutions conditionnelles** : "si résolution N adoptée, ouvrir résolution N+1"
- **Historique des actions** : timeline des événements de la séance (qui a fait quoi, quand)
- **Mode hors-ligne** : Service Worker pour continuer en cas de coupure réseau
- **Raccourcis clavier** : Ctrl+O ouvrir vote, Ctrl+C clôturer, etc.

---

## 5. Vote (`vote.htmx.html`)

**Accès** : `voter`, `operator` (ou via token d'invitation)
**Rôle** : interface de vote pour les électeurs

### Fonctionnalités actuelles
- **Authentification** : par session (utilisateur connecté) ou par token d'invitation (lien unique)
- **Sélection membre** : dropdown si l'opérateur vote au nom d'un membre
- **Affichage motion** : titre, description, politique de vote, badge secret/public
- **Bulletin** : boutons Pour / Contre / Abstention (+ Ne se prononce pas selon config)
- **Confirmation** : modal de confirmation avant enregistrement
- **Feedback** : animation de succès après vote
- **Heartbeat** : signal périodique pour détecter les déconnexions
- **Gestion block/kick** : l'opérateur peut bloquer ou éjecter un votant
- **Mode secret** : aucune indication visuelle du choix des autres
- **Responsive** : optimisé tablette et mobile

### Améliorations possibles
- **Accessibilité renforcée** : navigation 100% clavier, lecteur d'écran, contraste WCAG AAA
- **Vote par QR code** : scanner un QR code pour pré-remplir meeting + member
- **Confirmation biométrique** : empreinte / Face ID sur mobile pour le vote secret
- **Récapitulatif post-vote** : "Vous avez voté Pour la résolution 3" (seulement si vote public)
- **Indicateur de progression** : "Résolution 3/7 — 4 restantes"
- **Mode gros caractères** : bouton pour augmenter la taille de police (assemblées de copropriétaires âgés)
- **Animations de transition** : entre les résolutions, pour guider visuellement
- **Aide contextuelle** : tooltip expliquant chaque politique de vote

---

## 6. Membres (`members.htmx.html`)

**Accès** : `admin`, `operator`
**Rôle** : gestion du registre des membres votants

### Fonctionnalités actuelles
- **Liste** : tableau des membres avec nom, email, référence externe, poids de vote, rôle, statut actif/inactif
- **CRUD** : ajout, modification, suppression de membres
- **Groupes** : création de groupes (ex: "Lot A", "Commission finances"), affectation des membres
- **Import CSV** : import en masse depuis un fichier CSV avec mapping de colonnes
- **Filtrage** : par groupe, statut actif/inactif, recherche par nom/email
- **Tri** : par nom, poids, groupe
- **Pagination** : affichage progressif
- **Détail membre** : dialog avec historique de participation

### Améliorations possibles
- **Export CSV/Excel** : exporter la liste filtrée
- **Import Excel** : support .xlsx en plus du CSV
- **Fusion de doublons** : détection et merge de membres similaires
- **Historique de poids** : traçabilité des changements de tantièmes/poids
- **Photo de profil** : avatar optionnel pour faciliter l'identification
- **Invitation par email** : envoyer un lien d'inscription au membre
- **Tags** : système de tags libres en plus des groupes
- **Validation d'email** : vérification de l'adresse email à l'import
- **Champs personnalisés** : permettre d'ajouter des champs métier (numéro de lot, étage, etc.)

---

## 7. Projection (`public.htmx.html`)

**Accès** : `admin`, `operator`, `president` (ou sans auth selon config)
**Rôle** : écran projeté en salle, visible par tous les participants

### Fonctionnalités actuelles
- **Standalone** : pas de sidebar, pas de shell — page autonome
- **Sélecteur de séance** : dropdown pour choisir la séance à afficher
- **Résultats en direct** : barres animées pour/contre/abstention avec pourcentages
- **Polling** : rafraîchissement automatique par intervalle (pas de WebSocket)
- **Thème** : bascule clair/sombre
- **Plein écran** : bouton fullscreen pour projection
- **Heartbeat** : indicateur de connexion active

### Améliorations possibles
- **Affichage des orateurs** : "Parole à Mme Martin" en surimpression
- **Chronomètre visible** : temps restant pour le vote en cours
- **Résultats progressifs** : barre qui se remplit en temps réel pendant le vote (vote public uniquement)
- **Mode "attente"** : écran d'attente stylé entre les résolutions (logo + "Vote en cours de préparation")
- **Multi-résolution** : afficher les résultats de toutes les résolutions votées en mosaïque
- **QR code d'accès** : afficher le QR code pour que les votants scannent et accèdent au vote
- **Personnalisation** : logo du client, couleurs, nom de l'assemblée en grand
- **WebSocket** : remplacement du polling pour des mises à jour instantanées

---

## 8. Administration (`admin.htmx.html`)

**Accès** : `admin` uniquement
**Rôle** : configuration système

### Fonctionnalités actuelles
- **Utilisateurs** : CRUD des comptes utilisateurs, attribution des rôles système
- **Rôles par séance** : attribution de rôles contextuels (president, secretary, etc.) par séance
- **Politiques de vote** : CRUD des politiques (majorité simple, absolue, 2/3, unanimité, personnalisée)
- **Politiques de quorum** : CRUD (quorum 50% personnes, 33% personnes, par poids, etc.)
- **Matrice de permissions** : visualisation role × permission
- **Machine à états** : visualisation des transitions possibles (draft → live → closed, etc.)
- **Statut système** : health check, version, infos serveur
- **Reset démo** : réinitialisation des données de démonstration

### Améliorations possibles
- **Audit log viewer** : interface pour consulter les logs d'audit (actuellement en base uniquement)
- **Gestion multi-tenant** : interface pour créer/gérer des tenants (actuellement un seul)
- **Backup/restore** : export et import de la base de données depuis l'UI
- **Configuration email** : SMTP settings depuis l'interface (actuellement en .env)
- **Personnalisation visuelle** : logo, couleurs, nom de l'instance
- **Gestion des API keys** : créer/révoquer des clés API depuis l'UI
- **Limites et quotas** : configurer les limites (nombre de séances, membres, etc.)
- **Import/export de configuration** : sauvegarder/restaurer la config complète

---

## 9. Audit & Conformité (`trust.htmx.html`)

**Accès** : `admin`, `auditor`, `assessor`
**Rôle** : contrôle d'intégrité et vérification de conformité

### Fonctionnalités actuelles
- **Checklist de vérification** : vérifications automatiques (présence >= 1, quorum atteint, résolutions fermées, etc.)
- **Intégrité des votes** : vérification que chaque bulletin correspond à un membre éligible
- **Cohérence des comptages** : comparaison bulletins vs résultats
- **Chaîne d'audit** : vérification de la chaîne de hachage des événements
- **Résumé par résolution** : statut de conformité par motion
- **Indicateurs visuels** : vert/orange/rouge par critère

### Améliorations possibles
- **Export rapport d'audit** : PDF signé attestant de la conformité
- **Horodatage certifié** : intégration avec un service de timestamp (RFC 3161)
- **Preuve de non-altération** : export des hashes pour vérification indépendante
- **Comparaison PV / données brutes** : diff visuel entre le PV et les données de vote
- **Alertes temps réel** : notification quand une anomalie est détectée pendant la séance
- **Score de confiance** : indicateur global de fiabilité de la séance (0-100%)

---

## 10. Clôture & PV (`postsession.htmx.html`)

**Accès** : `admin`, `operator`, `president`
**Rôle** : workflow guidé post-séance en 4 étapes

### Fonctionnalités actuelles
- **Étape 1 — Vérification** : résumé statistique + checklist de cohérence
- **Étape 2 — Validation** : transition officielle closed → validated (irréversible)
- **Étape 3 — Procès-verbal** : génération, prévisualisation, export PDF
- **Étape 4 — Envoi & Archivage** : envoi du PV par email, export des données, archivage

### Améliorations possibles
- **Signature électronique** : signature du président et du secrétaire intégrée au PV
- **Annotations** : permettre d'ajouter des commentaires/corrections au PV avant validation
- **PV multi-format** : export Word (.docx) en plus du PDF
- **Envoi sélectif** : choisir les destinataires du PV (pas forcément tous les membres)
- **Brouillon de PV** : sauvegarder un brouillon avant validation définitive
- **Rappel automatique** : email de relance si le PV n'est pas validé sous X jours

---

## 11. Exports / Procès-verbal (`report.htmx.html`)

**Accès** : `admin`, `operator`, `president`, `auditor`
**Rôle** : génération et consultation du procès-verbal

### Fonctionnalités actuelles
- **PV HTML** : rendu du procès-verbal en HTML avec mise en page formelle
- **Export PDF** : impression / export PDF via le navigateur
- **Données incluses** : titre, date, présents, résolutions, résultats, décisions

### Améliorations possibles
- **Templates de PV** : plusieurs modèles de mise en page
- **En-tête/pied personnalisable** : logo, adresse, mentions légales
- **Annexes** : joindre les pièces justificatives au PV
- **Export données brutes** : CSV des bulletins (anonymisés), feuille d'émargement
- **Versioning** : historique des modifications du PV
- **Conformité légale** : numérotation des pages, paraphe virtuel

---

## 12. Validation (`validate.htmx.html`)

**Accès** : `operator`
**Rôle** : page dédiée à la validation officielle d'une séance

### Fonctionnalités actuelles
- **Checklist pré-validation** : vérification que toutes les conditions sont remplies
- **Saisie du nom du président** : requis pour la validation
- **Transition irréversible** : passage en statut "validé"
- **Génération automatique du PV** : le PV est figé au moment de la validation

### Améliorations possibles
- **Double validation** : président + secrétaire doivent valider
- **Code de confirmation** : saisie d'un code PIN ou mot de passe pour valider
- **Aperçu avant validation** : voir le PV final avant de confirmer

---

## 13. Archives (`archives.htmx.html`)

**Accès** : `admin`, `operator`, `auditor`, `viewer`
**Rôle** : consultation des séances passées

### Fonctionnalités actuelles
- **Liste** : séances archivées avec titre, date, statut
- **Filtrage** : par période (date de/à)
- **Accès au PV** : lien vers le procès-verbal archivé
- **Consultation** : ouverture en lecture seule

### Améliorations possibles
- **Recherche full-text** : recherche dans le contenu des PV
- **Export en masse** : télécharger tous les PV d'une période en ZIP
- **Statistiques croisées** : évolution de la participation sur N séances
- **Comparaison** : comparer deux séances côte à côte
- **Retention policy** : suppression automatique après N années (RGPD)

---

## 14. Statistiques (`analytics.htmx.html`)

**Accès** : `admin`, `operator`, `auditor`
**Rôle** : tableaux de bord et visualisations

### Fonctionnalités actuelles
- **Graphiques** : charts Chart.js (barres, lignes, camemberts)
- **Indicateurs** : KPIs globaux (nombre de séances, résolutions, taux d'adoption)
- **Filtrage par période** : année, trimestre, mois, personnalisé
- **Données agrégées** : participation moyenne, votes par type, tendances

### Améliorations possibles
- **Export des graphiques** : PNG/SVG des charts
- **Rapport PDF** : génération d'un rapport statistique complet
- **Benchmarking** : comparaison avec les moyennes (si multi-tenant)
- **Prédictions** : estimation du quorum futur basée sur l'historique
- **Heatmap de participation** : qui vote / qui s'absente régulièrement
- **Temps moyen par résolution** : optimiser la durée des séances

---

## 15. Templates Email (`email-templates.htmx.html`)

**Accès** : `admin`
**Rôle** : personnalisation des emails envoyés par l'application

### Fonctionnalités actuelles
- **Éditeur** : édition du contenu des templates email
- **Variables** : insertion de variables dynamiques (nom, date, lien)
- **Prévisualisation** : aperçu du rendu HTML
- **Types** : invitation, convocation, rappel, envoi de PV

### Améliorations possibles
- **Éditeur WYSIWYG** : remplacer le textarea par un éditeur rich-text
- **Test d'envoi** : bouton "Envoyer un test à mon adresse"
- **Multi-langue** : templates en plusieurs langues
- **Pièces jointes** : attacher des fichiers aux templates (convocation PDF, etc.)
- **Planification** : programmer l'envoi à une date/heure

---

## 16. Documentation (`docs.htmx.html`)

**Accès** : tous les rôles
**Rôle** : documentation in-app

### Fonctionnalités actuelles
- **Viewer** : affichage de la documentation markdown
- **Navigation** : sommaire et liens internes
- **Recherche** : filtre dans les documents

### Améliorations possibles
- **Versioning** : documentation liée à la version de l'application
- **Vidéos intégrées** : tutoriels vidéo dans la doc
- **Mode interactif** : "Essayez maintenant" avec liens vers les pages concernées
- **Traduction** : documentation en anglais

---

## 17. Aide & FAQ (`help.htmx.html`)

**Accès** : tous les rôles
**Rôle** : questions fréquentes et aide contextuelle

### Fonctionnalités actuelles
- **FAQ** : questions/réponses organisées par catégorie
- **Accordion** : sections dépliables
- **Recherche** : filtre par mot-clé

### Améliorations possibles
- **Aide contextuelle** : bouton "?" sur chaque page qui ouvre la section FAQ pertinente
- **Chat intégré** : assistant IA pour répondre aux questions
- **Tickets support** : formulaire de contact intégré
- **Tutoriel guidé** : walkthrough interactif pour les nouveaux utilisateurs (onboarding)
- **Raccourcis clavier** : page récapitulative des raccourcis disponibles

---

## Fonctionnalités transversales

### Design system
- Thème clair/sombre avec bascule persistante (localStorage)
- Design tokens CSS (couleurs, espacements, typographie)
- Composants réutilisables (badges, boutons, modals, formulaires, toasts)
- Responsive : 3 breakpoints (desktop, tablette 768px, mobile 480px)
- Accessibilité : skip links, ARIA, focus visible, rôles sémantiques

### Sécurité
- CSRF token sur toutes les requêtes POST
- Rate limiting (nginx + applicatif)
- Content Security Policy sans `unsafe-inline` pour les scripts
- HSTS conditionnel (actif uniquement derrière TLS)
- Hachage des tokens d'invitation (SHA-256)
- Audit log de toutes les actions sensibles
- Container non-root (www-data)

### API
- 120+ endpoints REST en PHP
- Format uniforme : `{ ok: true, data: {...} }` ou `{ ok: false, error: "code", message: "..." }`
- Timeout configurable, retry automatique côté client
- Upload de fichiers avec validation MIME + taille

---

## Améliorations globales prioritaires

### Court terme (quick wins)
1. **Mot de passe oublié** : flux de reset par email
2. **Export CSV** depuis la page membres et séances
3. **Aide contextuelle** : bouton "?" par page
4. **Dupliquer une séance** : bouton dans le menu de la card

### Moyen terme
5. **WebSocket** : remplacer le polling par des mises à jour push (vote temps réel, projection)
6. **Mode hors-ligne** : Service Worker pour la console opérateur
7. **Signature électronique** : intégrée au workflow de validation du PV
8. **Multi-langue** : i18n français/anglais minimum
9. **Raccourcis clavier** : pour l'opérateur pendant la séance

### Long terme
10. **SSO / SAML / OAuth** : intégration avec les annuaires existants
11. **Application mobile native** : PWA ou app dédiée pour les votants
12. **Horodatage certifié** : preuve légale de non-altération
13. **Multi-tenant UI** : gestion de plusieurs organisations depuis une seule instance
14. **IA** : résumé automatique des débats, suggestion de formulation pour les résolutions
