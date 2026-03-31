# Guide fonctionnel : AG-VOTE

Guide exhaustif de toutes les pages, fonctionnalites et pistes d'amelioration.

---

## Architecture generale

AG-VOTE est une application web de gestion de seances de vote formelles (assemblees generales, conseils, bureaux). Elle couvre le cycle complet : preparation, vote en direct, depouillement, validation, archivage.

**Stack** : PHP 8.4 / PostgreSQL 16 / JavaScript vanilla / Web Components / CSS design system "Acte Officiel" / Docker
**Auth** : RBAC a deux niveaux (roles systeme + roles par seance)
**Multi-tenant** : isolation complete par `tenant_id` sur chaque table

### Roles systeme

| Role | Acces |
|------|-------|
| `admin` | Tout : configuration, utilisateurs, politiques |
| `operator` | Gestion seances, membres, resolutions, emargement |
| `auditor` | Lecture + controles d'integrite |
| `viewer` | Lecture seule |

### Roles par seance (meeting_roles)

`president`, `secretary`, `assessor`, `voter` : attribues par seance, permettent des permissions contextuelles (ex : un `viewer` systeme peut etre `president` d'une seance specifique).

### Navigation (sidebar)

```
Preparation           En direct              Apres
-- Dashboard          -- Fiche seance        -- Cloture & PV
-- Seances            -- Operateur           -- Archives
-- Membres            -- Affichage salle
-- Creer une seance

Controle              Systeme                Aide
-- Audit              -- Utilisateurs        -- Guide & FAQ
-- Statistiques       -- Parametres

Apercu votant (tablette)
```

---

## 1. Page d'accueil (`index.html`)

**Acces** : public, pas d'authentification
**Role** : vitrine et presentation du produit

### Fonctionnalites actuelles
- Presentation du produit avec sections hero, fonctionnalites, securite
- Navigation responsive avec hamburger menu mobile
- Liens vers `/login.html`
- Mode clair/sombre

### Ameliorations possibles
- **Demo interactive** : lien vers une instance de demonstration pre-remplie
- **Video de presentation** : walkthrough en 2 minutes
- **Temoignages et chiffres cles** : nombre de votes traites, clients
- **Changelog public** : "Quoi de neuf" visible depuis la landing

---

## 2. Connexion (`login.html`)

**Acces** : public
**Role** : authentification

### Fonctionnalites actuelles
- Formulaire email + mot de passe
- Bascule theme clair/sombre
- Redirection post-login vers la page demandee
- CSRF token automatique

### Ameliorations possibles
- **"Mot de passe oublie"** : flux de reset par email (actuellement absent)
- **2FA / TOTP** : second facteur pour les roles admin/operator
- **SSO / OAuth** : integration LDAP, SAML, OpenID Connect pour les collectivites
- **Tentatives ratees visibles** : "Trop de tentatives, reessayez dans X secondes" cote UI
- **QR code de connexion rapide** : scan depuis mobile pour les votants

---

## 3. Tableau de bord (`/dashboard`)

**Acces** : tous les roles (`viewer` minimum)
**Role** : vue d'ensemble, actions urgentes, acces rapides

### Fonctionnalites actuelles
- **Carte action urgente** : bordure rouge, lien direct vers la fiche seance en cours
- **4 KPIs** : AG a venir, en cours, convocations en attente, PV a envoyer
- **Prochaines seances** : liste avec date, statut, lien direct
- **Taches en attente** : validations, envois, preparations a traiter
- **Acces rapides** : 3 cards (Creer une seance, Piloter, Audit)
- **Skeleton loading** : placeholders animes pendant le chargement
- **Donnees demo** : fallback wireframe si l'API n'est pas disponible

---

## 4. Seances (`/meetings`)

**Acces** : `admin`, `operator`, `auditor`, `viewer`
**Role** : CRUD des seances, vue d'ensemble

### Fonctionnalites actuelles
- **Barre de stats** : seances en cours, planifiees, brouillons, total, resolutions, participation moyenne
- **Creation rapide** : titre + date + type (AG ordinaire, extraordinaire, conseil, bureau, autre) + pieces jointes PDF
- **Filtrage** : onglets Toutes / En cours / Planifiees / Brouillons / Archives
- **Recherche** : filtre par titre en temps reel (debounce 250ms)
- **Tri** : par statut, date (asc/desc), titre A-Z
- **Vue grille** : cards avec badge statut, type, date, nombre de resolutions et presents
- **Vue calendrier** : calendrier mensuel avec evenements colores par statut
- **Edition** : modal pour modifier titre, date, type
- **Suppression** : modal de confirmation (brouillons uniquement)
- **Pagination** : "Afficher plus" par blocs de 12
- **Glisser-deposer PDF** : zone de drop avec validation type/taille

### Cards de seance
- Variant `.is-live` : bordure verte + glow anime + fond degrade
- Variant `.is-draft` : bordure pointillee + opacite reduite
- Variant `.is-archived` : opacite reduite, fond neutre
- Boutons contextuels : Rejoindre (live), Ouvrir (draft/scheduled)
- Actions secondaires : Modifier, Supprimer (selon statut)

### Ameliorations possibles
- **Duplication** : bouton "Dupliquer cette seance" pour creer une nouvelle seance avec les memes resolutions/politiques
- **Templates de seance** : modeles reutilisables (ex : "AG annuelle type")
- **Import ordre du jour** : upload d'un fichier structure (CSV/JSON) pour pre-remplir les resolutions
- **Notifications** : badge "nouvelle activite" sur les seances en cours
- **Export liste** : CSV/Excel de la liste des seances filtrees
- **Recherche avancee** : filtre par date, type, nombre de resolutions
- **Drag & drop reordonnement** : changer l'ordre des seances dans la vue grille
- **Apercu rapide** : survol d'une card pour voir un resume sans ouvrir

---

## 5. Creer une seance (`/wizard`)

**Acces** : `admin`, `operator`
**Role** : assistant guide de creation de seance en 4 etapes

### Fonctionnalites actuelles
- **Etape 1, Informations** : titre, type (AG ordinaire/extraordinaire/conseil/bureau), date, heure (HH:MM split input avec auto-advance), lieu, politique de quorum
- **Etape 2, Participants** : import CSV/XLSX, zone de drag & drop, ajout manuel
- **Etape 3, Resolutions** : ajout avec titre, description, majorite (art. 24/25/26/26-1), cle de repartition, toggle vote secret
- **Etape 4, Recapitulatif** : resume de tous les champs, bouton de creation
- **Confirmation** : page de succes avec liens vers la fiche seance
- **Stepper** : barre de progression cliquable (retour aux etapes precedentes)

---

## 6. Fiche seance, Hub (`/hub`)

**Acces** : `admin`, `operator`
**Role** : vue guidee du cycle de vie d'une seance (6 etapes)

### Fonctionnalites actuelles
- **Bandeau d'identite** : titre, date, lieu, nombre de participants
- **Stepper 6 etapes** : Preparer, Convoquer, Pointer, Voter, Cloturer, Archiver
- **Carte d'action** : icone, titre, description, bouton principal pour chaque etape
- **Checklist de progression** : barre de progression + items a cocher par etape
- **Section details** : collapsible avec 4 KPIs, liste de documents, panneau 2e convocation
- **Navigation** : clic sur une etape du stepper pour y acceder directement
- **Donnees demo** : AG Ordinaire avec 67 participants, 8 resolutions

---

## 7. Console operateur (`/operator/{uuid}`)

**Acces** : `admin`, `operator`
**Role** : page principale pendant une seance, pilotage complet

### Fonctionnalites actuelles
- **Barre de seance** : selecteur de seance + badge statut + role utilisateur + indicateur de sante (pre-requis)
- **Onglets** :
  - **Resolutions** : CRUD des motions, ouverture/fermeture du vote, resultats en temps reel, comptage manuel, politique de vote/quorum par resolution
  - **Emargement** : liste des membres avec statut (present, distant, procuration, excuse), check-in/check-out, recherche, statistiques de presence
  - **Demandes de parole** : file d'attente, passage en parole, fin de parole, annulation
  - **Tableau de bord** : vue synthetique de la seance (stats, progression)
- **Transitions d'etat** : draft, scheduled, frozen, live, paused, closed, validated, archived
- **Actions rapides** depuis la barre : demarrer/suspendre/reprendre/cloturer la seance

### Resolutions (onglet motions)
- Ajout rapide (titre seul) ou detaille (description, vote secret, politique)
- Reordonnement par drag & drop
- Ouverture/fermeture individuelle du vote
- Resultats : pour/contre/abstention avec pourcentages
- Comptage manuel : saisie papier pour les votes non-electroniques
- Decision : adopte/rejete/ajourne
- Politique de vote configurable par resolution (majorite simple, absolue, 2/3, unanimite)
- Politique de quorum configurable par resolution

### Emargement (onglet attendance)
- Liste complete des membres avec filtres par statut
- Check-in en un clic (present / distant)
- Gestion des procurations (mandant vers mandataire)
- Recherche par nom
- Compteurs temps reel : presents, distants, procurations, excuses, absents

### Ameliorations possibles
- **Vote en lot** : ouvrir/fermer plusieurs resolutions en serie automatiquement
- **Chronometre** : temps de parole par orateur et par resolution
- **Notes de seance** : champ libre pour le secretaire (integre au PV)
- **Mode "salle"** : QR code geant projetable pour le check-in des membres
- **Alertes quorum** : notification temps reel quand le quorum est atteint/perdu
- **Import resolutions** : depuis un fichier CSV ou copier/coller structure
- **Resolutions conditionnelles** : "si resolution N adoptee, ouvrir resolution N+1"
- **Historique des actions** : timeline des evenements de la seance (qui a fait quoi, quand)
- **Mode hors-ligne** : Service Worker pour continuer en cas de coupure reseau
- **Raccourcis clavier** : Ctrl+O ouvrir vote, Ctrl+C cloturer, etc.

---

## 8. Vote (`/vote`)

**Acces** : `voter`, `operator` (ou via token d'invitation)
**Role** : interface de vote pour les electeurs

### Fonctionnalites actuelles
- **Authentification** : par session (utilisateur connecte) ou par token d'invitation (lien unique)
- **Selection membre** : dropdown si l'operateur vote au nom d'un membre
- **Affichage motion** : titre, description, politique de vote, badge secret/public
- **Bulletin** : boutons Pour / Contre / Abstention (+ Ne se prononce pas selon config)
- **Confirmation** : modal de confirmation avant enregistrement
- **Feedback** : animation de succes apres vote
- **Heartbeat** : signal periodique pour detecter les deconnexions
- **Gestion block/kick** : l'operateur peut bloquer ou ejecter un votant
- **Mode secret** : aucune indication visuelle du choix des autres
- **Responsive** : optimise tablette et mobile

### Ameliorations possibles
- **Accessibilite renforcee** : navigation 100% clavier, lecteur d'ecran, contraste WCAG AAA
- **Vote par QR code** : scanner un QR code pour pre-remplir meeting + member
- **Confirmation biometrique** : empreinte / Face ID sur mobile pour le vote secret
- **Recapitulatif post-vote** : "Vous avez vote Pour la resolution 3" (seulement si vote public)
- **Indicateur de progression** : "Resolution 3/7, 4 restantes"
- **Mode gros caracteres** : bouton pour augmenter la taille de police (assemblees de membres ages)
- **Animations de transition** : entre les resolutions, pour guider visuellement
- **Aide contextuelle** : tooltip expliquant chaque politique de vote

---

## 9. Membres (`/members`)

**Acces** : `admin`, `operator`
**Role** : gestion du registre des membres votants

### Fonctionnalites actuelles
- **Liste** : tableau des membres avec nom, email, reference externe, role, statut actif/inactif
- **CRUD** : ajout, modification, suppression de membres
- **Groupes** : creation de groupes (ex : "Lot A", "Commission finances"), affectation des membres
- **Import CSV** : import en masse depuis un fichier CSV avec mapping de colonnes
- **Filtrage** : par groupe, statut actif/inactif, recherche par nom/email
- **Tri** : par nom, groupe
- **Pagination** : affichage progressif
- **Detail membre** : dialog avec historique de participation

### Ameliorations possibles
- **Export CSV/Excel** : exporter la liste filtree
- **Import Excel** : support .xlsx en plus du CSV
- **Fusion de doublons** : detection et merge de membres similaires
- **Photo de profil** : avatar optionnel pour faciliter l'identification
- **Invitation par email** : envoyer un lien d'inscription au membre
- **Tags** : systeme de tags libres en plus des groupes
- **Validation d'email** : verification de l'adresse email a l'import
- **Champs personnalises** : permettre d'ajouter des champs metier (numero de lot, etage, etc.)

---

## 10. Projection (`/public`)

**Acces** : `admin`, `operator`, `president` (ou sans auth selon config)
**Role** : ecran projete en salle, visible par tous les participants

### Fonctionnalites actuelles
- **Standalone** : pas de sidebar, pas de shell, page autonome
- **Selecteur de seance** : dropdown pour choisir la seance a afficher
- **Resultats en direct** : barres animees pour/contre/abstention avec pourcentages
- **Polling** : rafraichissement automatique par intervalle
- **Theme** : bascule clair/sombre
- **Plein ecran** : bouton fullscreen pour projection
- **Heartbeat** : indicateur de connexion active

### Ameliorations possibles
- **Affichage des orateurs** : "Parole a Mme Martin" en surimpression
- **Chronometre visible** : temps restant pour le vote en cours
- **Resultats progressifs** : barre qui se remplit en temps reel pendant le vote (vote public uniquement)
- **Mode "attente"** : ecran d'attente stylise entre les resolutions (logo + "Vote en cours de preparation")
- **Multi-resolution** : afficher les resultats de toutes les resolutions votees en mosaique
- **QR code d'acces** : afficher le QR code pour que les votants scannent et accedent au vote
- **Personnalisation** : logo du client, couleurs, nom de l'assemblee en grand
- **SSE (Server-Sent Events)** : remplacement du polling pour des mises a jour instantanees

---

## 11. Administration (`/admin`)

**Acces** : `admin` uniquement
**Role** : configuration systeme

### Fonctionnalites actuelles
- **Utilisateurs** : CRUD des comptes utilisateurs, attribution des roles systeme
- **Roles par seance** : attribution de roles contextuels (president, secretary, etc.) par seance
- **Politiques de vote** : CRUD des politiques (majorite simple, absolue, 2/3, unanimite, personnalisee)
- **Politiques de quorum** : CRUD (quorum 50% personnes, 33% personnes, etc.)
- **Matrice de permissions** : visualisation role x permission
- **Machine a etats** : visualisation des transitions possibles (draft, live, closed, etc.)
- **Statut systeme** : health check, version, infos serveur
- **Reset demo** : reinitialisation des donnees de demonstration

### Ameliorations possibles
- **Audit log viewer** : interface pour consulter les logs d'audit (actuellement en base uniquement)
- **Gestion multi-tenant** : interface pour creer/gerer des tenants (actuellement un seul)
- **Backup/restore** : export et import de la base de donnees depuis l'UI
- **Configuration email** : SMTP settings depuis l'interface (actuellement en .env)
- **Personnalisation visuelle** : logo, couleurs, nom de l'instance
- **Gestion des API keys** : creer/revoquer des cles API depuis l'UI
- **Limites et quotas** : configurer les limites (nombre de seances, membres, etc.)
- **Import/export de configuration** : sauvegarder/restaurer la config complete

---

## 12. Audit & Conformite (`/audit`)

**Acces** : `admin`, `auditor`, `assessor`
**Role** : controle d'integrite et verification de conformite

### Fonctionnalites actuelles
- **Checklist de verification** : verifications automatiques (presence >= 1, quorum atteint, resolutions fermees, etc.)
- **Integrite des votes** : verification que chaque bulletin correspond a un membre eligible
- **Coherence des comptages** : comparaison bulletins vs resultats
- **Chaine d'audit** : verification de la chaine de hachage des evenements
- **Resume par resolution** : statut de conformite par motion
- **Indicateurs visuels** : vert/orange/rouge par critere

### Ameliorations possibles
- **Export rapport d'audit** : PDF signe attestant de la conformite
- **Horodatage certifie** : integration avec un service de timestamp (RFC 3161)
- **Preuve de non-alteration** : export des hashes pour verification independante
- **Comparaison PV / donnees brutes** : diff visuel entre le PV et les donnees de vote
- **Alertes temps reel** : notification quand une anomalie est detectee pendant la seance
- **Score de confiance** : indicateur global de fiabilite de la seance (0-100%)

---

## 13. Cloture & PV (`/postsession`)

**Acces** : `admin`, `operator`, `president`
**Role** : workflow guide post-seance en 4 etapes

### Fonctionnalites actuelles
- **Etape 1, Verification** : resume statistique + checklist de coherence
- **Etape 2, Validation** : transition officielle closed vers validated (irreversible)
- **Etape 3, Proces-verbal** : generation, previsualisation, export PDF
- **Etape 4, Envoi & Archivage** : envoi du PV par email, export des donnees, archivage

### Ameliorations possibles
- **Signature electronique** : signature du president et du secretaire integree au PV
- **Annotations** : permettre d'ajouter des commentaires/corrections au PV avant validation
- **PV multi-format** : export Word (.docx) en plus du PDF
- **Envoi selectif** : choisir les destinataires du PV (pas forcement tous les membres)
- **Brouillon de PV** : sauvegarder un brouillon avant validation definitive
- **Rappel automatique** : email de relance si le PV n'est pas valide sous X jours

---

## 14. Exports / Proces-verbal (`/report`)

**Acces** : `admin`, `operator`, `president`, `auditor`
**Role** : generation et consultation du proces-verbal

### Fonctionnalites actuelles
- **PV HTML** : rendu du proces-verbal en HTML avec mise en page formelle
- **Export PDF** : impression / export PDF via le navigateur
- **Donnees incluses** : titre, date, presents, resolutions, resultats, decisions

### Ameliorations possibles
- **Templates de PV** : plusieurs modeles de mise en page
- **En-tete/pied personnalisable** : logo, adresse, mentions legales
- **Annexes** : joindre les pieces justificatives au PV
- **Export donnees brutes** : CSV des bulletins (anonymises), feuille d'emargement
- **Versioning** : historique des modifications du PV
- **Conformite legale** : numerotation des pages, paraphe virtuel

---

## 15. Validation (`/validate`)

**Acces** : `operator`
**Role** : page dediee a la validation officielle d'une seance

### Fonctionnalites actuelles
- **Checklist pre-validation** : verification que toutes les conditions sont remplies
- **Saisie du nom du president** : requis pour la validation
- **Transition irreversible** : passage en statut "valide"
- **Generation automatique du PV** : le PV est fige au moment de la validation

### Ameliorations possibles
- **Double validation** : president + secretaire doivent valider
- **Code de confirmation** : saisie d'un code PIN ou mot de passe pour valider
- **Apercu avant validation** : voir le PV final avant de confirmer

---

## 16. Archives (`/archives`)

**Acces** : `admin`, `operator`, `auditor`, `viewer`
**Role** : consultation des seances passees

### Fonctionnalites actuelles
- **Liste** : seances archivees avec titre, date, statut
- **Filtrage** : par periode (date de/a)
- **Acces au PV** : lien vers le proces-verbal archive
- **Consultation** : ouverture en lecture seule

### Ameliorations possibles
- **Recherche full-text** : recherche dans le contenu des PV
- **Export en masse** : telecharger tous les PV d'une periode en ZIP
- **Statistiques croisees** : evolution de la participation sur N seances
- **Comparaison** : comparer deux seances cote a cote
- **Retention policy** : suppression automatique apres N annees (RGPD)

---

## 17. Statistiques (`/analytics`)

**Acces** : `admin`, `operator`, `auditor`
**Role** : tableaux de bord et visualisations

### Fonctionnalites actuelles
- **Graphiques** : charts Chart.js (barres, lignes, camemberts)
- **Indicateurs** : KPIs globaux (nombre de seances, resolutions, taux d'adoption)
- **Filtrage par periode** : annee, trimestre, mois, personnalise
- **Donnees agregees** : participation moyenne, votes par type, tendances

### Ameliorations possibles
- **Export des graphiques** : PNG/SVG des charts
- **Rapport PDF** : generation d'un rapport statistique complet
- **Benchmarking** : comparaison avec les moyennes (si multi-tenant)
- **Predictions** : estimation du quorum futur basee sur l'historique
- **Heatmap de participation** : qui vote / qui s'absente regulierement
- **Temps moyen par resolution** : optimiser la duree des seances

---

## 18. Templates Email (`/email-templates`)

**Acces** : `admin`
**Role** : personnalisation des emails envoyes par l'application

### Fonctionnalites actuelles
- **Editeur** : edition du contenu des templates email
- **Variables** : insertion de variables dynamiques (nom, date, lien)
- **Previsualisation** : apercu du rendu HTML
- **Types** : invitation, convocation, rappel, envoi de PV

### Ameliorations possibles
- **Editeur WYSIWYG** : remplacer le textarea par un editeur rich-text
- **Test d'envoi** : bouton "Envoyer un test a mon adresse"
- **Multi-langue** : templates en plusieurs langues
- **Pieces jointes** : attacher des fichiers aux templates (convocation PDF, etc.)
- **Planification** : programmer l'envoi a une date/heure

---

## 19. Documentation (`/docs`)

**Acces** : tous les roles
**Role** : documentation in-app

### Fonctionnalites actuelles
- **Viewer** : affichage de la documentation markdown
- **Navigation** : sommaire et liens internes
- **Recherche** : filtre dans les documents

### Ameliorations possibles
- **Versioning** : documentation liee a la version de l'application
- **Videos integrees** : tutoriels video dans la doc
- **Mode interactif** : "Essayez maintenant" avec liens vers les pages concernees
- **Traduction** : documentation en anglais

---

## 20. Aide & FAQ (`/help`)

**Acces** : tous les roles
**Role** : questions frequentes et aide contextuelle

### Fonctionnalites actuelles
- **FAQ** : questions/reponses organisees par categorie
- **Accordion** : sections depliables
- **Recherche** : filtre par mot-cle

### Ameliorations possibles
- **Aide contextuelle** : bouton "?" sur chaque page qui ouvre la section FAQ pertinente
- **Chat integre** : assistant IA pour repondre aux questions
- **Tickets support** : formulaire de contact integre
- **Tutoriel guide** : walkthrough interactif pour les nouveaux utilisateurs (onboarding)
- **Raccourcis clavier** : page recapitulative des raccourcis disponibles

---

## Fonctionnalites transversales

### Design system
- Theme clair/sombre avec bascule persistante (localStorage)
- Design tokens CSS (couleurs, espacements, typographie)
- Composants reutilisables (badges, boutons, modals, formulaires, toasts)
- Responsive : 3 breakpoints (desktop, tablette 768px, mobile 480px)
- Accessibilite : skip links, ARIA, focus visible, roles semantiques

### Securite
- CSRF token sur toutes les requetes POST
- Rate limiting (nginx + applicatif)
- Content Security Policy sans `unsafe-inline` pour les scripts
- HSTS conditionnel (actif uniquement derriere TLS)
- Hachage des tokens d'invitation (SHA-256)
- Audit log de toutes les actions sensibles
- Container non-root (www-data)

### API
- 142 endpoints REST en PHP
- Format uniforme : `{ ok: true, data: {...} }` ou `{ ok: false, error: "code", message: "..." }`
- Timeout configurable, retry automatique cote client
- Upload de fichiers avec validation MIME + taille

---

## Ameliorations globales prioritaires

### Court terme (quick wins)
1. **Mot de passe oublie** : flux de reset par email
2. **Export CSV** depuis la page membres et seances
3. **Aide contextuelle** : bouton "?" par page
4. **Dupliquer une seance** : bouton dans le menu de la card

### Moyen terme
5. **SSE (Server-Sent Events)** : remplacer le polling par des mises a jour push (vote temps reel, projection)
6. **Mode hors-ligne** : Service Worker pour la console operateur
7. **Signature electronique** : integree au workflow de validation du PV
8. **Multi-langue** : i18n francais/anglais minimum
9. **Raccourcis clavier** : pour l'operateur pendant la seance

### Long terme
10. **SSO / SAML / OAuth** : integration avec les annuaires existants
11. **Application mobile native** : PWA ou app dediee pour les votants
12. **Horodatage certifie** : preuve legale de non-alteration
13. **Multi-tenant UI** : gestion de plusieurs organisations depuis une seule instance
14. **IA** : resume automatique des debats, suggestion de formulation pour les resolutions
