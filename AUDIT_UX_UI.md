# Audit UX/UI — AG-VOTE

> Date : 2026-03-11
> Methode : Playwright headless (1440x900 desktop + 375x812 mobile)
> Pages auditees : 16 pages + login + vue publique
> Navigateur : Chromium headless, sans acces CDN (Google Fonts, jsdelivr)

---

## 1. Problemes globaux (toutes pages)

### 1.1 Sidebar : contenu tronque derriere le rail

| Severite | Impact |
|----------|--------|
| **Haute** | Toutes les pages sauf login et public |

Le `padding-left` du `.app-main` est `calc(var(--sidebar-rail) + 22px)` = **80px**.
La sidebar collapsed fait 58px, donc le contenu commence a 80px.

**Probleme** : Le premier element de contenu (stat cards, titres, bannieres) est systematiquement coupe sur ~10-15px a gauche. Visible sur :
- Admin : "e sur AG-VOTE" au lieu de "Bienvenue sur AG-VOTE"
- Hub : "026 a 18 h 30" au lieu de la date complete
- Meetings : "ne nouvelle seance" au lieu de "Preparer une nouvelle seance"
- Analytics : le premier stat card est coupe (le label "SEANCES" n'est pas visible)
- Validate : "e la seance" au lieu de "Resume de la seance"
- Archives : "d." tronque dans les filtres tags
- Post-session : "es resultats" au lieu de "Verifier les resultats"
- Report : "ation du PV" au lieu de "Generation du PV"

**Cause probable** : Les stat-cards et bannieres utilisent `margin-left` negatif ou un container qui deborde sous la sidebar.

**Fix suggere** : Verifier que `.app-main > *:first-child` n'a pas de `margin-left` negatif. Ou augmenter le padding-left a `calc(var(--sidebar-rail) + 32px)`.

---

### 1.2 ~~Search modal (Cmd+K) s'ouvre inopinement~~ CORRIGE

| Severite | Impact |
|----------|--------|
| ~~**Moyenne**~~ | ~~Meetings, Operator, Validate~~ |

**Cause racine** : Deux systemes de search coexistaient — un template HTML statique `.cmd-palette-overlay` (dans operator.htmx.html et meetings.htmx.html) ET le systeme dynamique `.search-overlay` de shell.js. Les deux se superposaient.

**Corrections appliquees** :
- Suppression du template `#cmdPalette` de `operator.htmx.html` et `meetings.htmx.html`
- Suppression du CSS orphelin `.cmd-palette-*` de `design-system.css`
- Le systeme unique shell.js (Ctrl+K) est conserve avec Escape pour fermer

---

### 1.3 Dependance CDN sans fallback

| Severite | Impact |
|----------|--------|
| **Haute** | Page Docs |

| Ressource | CDN | Fallback local |
|-----------|-----|----------------|
| Google Fonts (Bricolage, Fraunces, JetBrains Mono) | fonts.googleapis.com | System fonts (ok) |
| `marked@12.0.0` | cdn.jsdelivr.net | **Aucun** |

La page Docs est **totalement cassee** sans CDN : affiche "Document non disponible — marked is not defined".

**Fix** : Vendoriser `marked.min.js` dans `/assets/js/vendor/` avec fallback :
```html
<script src="/assets/js/vendor/marked.min.js"></script>
<script>
  if (typeof marked === 'undefined') {
    // CDN fallback
    document.write('<script src="https://cdn.jsdelivr.net/npm/marked@12.0.0/marked.min.js"><\/script>');
  }
</script>
```

---

### 1.4 Timer de session intrusif

| Severite | Impact |
|----------|--------|
| **Moyenne** | Operator, Meetings |

La banniere orange "Votre session expire dans 2:00" avec boutons "Prolonger / Deconnexion" prend une ligne entiere en haut. Sur mobile, elle comprime fortement le contenu utile.

**Ameliorations possibles** :
- Afficher seulement quand il reste < 1 minute (pas 2 min)
- Utiliser une notification plus discrete (toast ou badge) au lieu d'une barre pleine largeur
- Rendre le countdown configurable

---

## 2. Page par page

### 2.1 Login (`login.html`)

**Points positifs** :
- Design clean, centre, accessible
- Comptes de demo clairement identifies avec boutons "Utiliser"
- Toggle dark mode present
- Lien "Mot de passe oublie" visible

**Ameliorations** :
- [ ] Le placeholder "admin@example.test" n'est pas un vrai placeholder — c'est la valeur pre-remplie en demo. Confusant si l'utilisateur ne sait pas que c'est un compte demo
- [ ] Le bouton "Voir" pour le mot de passe est du texte brut — un icone oeil serait plus standard
- [ ] Pas d'indication visuelle de force du mot de passe
- [ ] Mobile : rendu correct, bon responsive

---

### 2.2 Hub / Fiche seance (`hub.htmx.html`)

**Points positifs** :
- Breadcrumb "Seance en direct / Fiche seance" clair
- Barre de progression des convocations bien visible
- Actions "Envoyer les convocations" et "Apercu courriel" bien placees

**Ameliorations** :
- [ ] Le texte de la seance est coupe a gauche (date, lieu tronques par sidebar)
- [ ] L'accordion "Voir les informations detaillees de la seance" est a peine visible
- [ ] Pas de breadcrumb retour vers le dashboard global
- [ ] Le label "e PV" en bas est tronque — l'action liee n'est pas comprehensible
- [ ] Zone vide significative en bas de page

---

### 2.3 Seances (`meetings.htmx.html`)

**Points positifs** :
- Wizard steps (1-Seance, 2-Participants, 3-Resolutions, 4-Recap) bien visible
- Formulaire de creation de seance complet
- Warning orange "delais legaux de convocation" utile et bien visible
- Tags de type de seance (AG extra., Conseil, Bureau, Autre) clairs

**Ameliorations** :
- [ ] La banniere "Bienvenue sur AG-VOTE" reste visible meme apres la premiere utilisation — ajouter un dismiss persistent (cookie/localStorage)
- [ ] Les stat-cards en haut (TOTAL, RESOLUTIONS, PARTICIPATION) sont coupes par la sidebar
- [ ] Le selecteur de quorum "Simple (50%+1)" est un `<select>` natif — un composant custom serait plus explicite pour expliquer les regles
- [ ] La search modal s'ouvre automatiquement — bug de focus

---

### 2.4 Membres (`members.htmx.html`)

**Points positifs** :
- Wizard steps pour guider l'onboarding (1-Membres, 2-Ponderations, 3-Groupes, 4-Seance)
- Formulaire d'ajout inline rapide
- Gestion des groupes avec couleurs
- Export CSV present

**Ameliorations** :
- [ ] Les stat-cards en haut montrent "0 ACTIFS, 0 INACTIFS, 0 VOIX" alors qu'il y a 12 membres en base — probleme d'affichage ou de comptage
- [ ] Le premier stat card est tronque (texte invisible)
- [ ] Le label "Nombre de voix" avec "?" tooltip aide mais le "1" par defaut est-il toujours le bon choix ?
- [ ] "Importation CSV" en bas a gauche est presque invisible et mal place
- [ ] Aucune table de membres visible dans le viewport initial — il faut scroller alors que c'est la donnee principale
- [ ] Mobile : le formulaire d'ajout est fonctionnel mais serre

---

### 2.5 Operator (`operator.htmx.html`)

**Points positifs** :
- Selecteur de seance en dropdown clair
- Barre de statut (Preparation > Programmee > Verrouillee > En cours > Terminee) tres visuelle
- Boutons "Ouvrir la seance" et "Projection" bien places
- Command palette riche avec pages + actions

**Ameliorations** :
- [ ] La barre de session timer mange l'espace vertical
- [ ] Le texte "Preparez la seance avant de la lancer." est coupe par la sidebar
- [ ] L'etat "pre-requis" est un point vert avec texte "pre-requis" — pas assez informatif sur ce qui manque
- [ ] La search modal s'ouvre automatiquement — a corriger

---

### 2.6 Administration (`admin.htmx.html`)

**Points positifs** :
- Dashboard resume clair (AG a venir, En cours, Convocations, PV en attente)
- Quick actions (Creer seance, Piloter, Consulter audit) bien conçues
- Onglets admin bien organises (Roles, Politiques, Permissions, Machine a etats, Parametres, Systeme)
- Tableau des roles avec badges colores

**Ameliorations** :
- [ ] Banniere "Bienvenue sur AG-VOTE" encore presente — devrait etre dismissable de facon permanente
- [ ] Le texte "e sur AG-VOTE" est tronque (sidebar overlap)
- [ ] Les stats cards "0 A VENIR" — le "0" et le label sont coupes sur le premier card
- [ ] Le texte des roles en bas ("plet au systeme, Operateur, Auditeur, Observateur") est coupe par la sidebar
- [ ] Mobile : les boutons CTA "Creer une seance / Guide rapide" se chevauchent legerement

---

### 2.7 Validation finale (`validate.htmx.html`)

**Points positifs** :
- Zone "Action irreversible" tres visible avec bordure rouge en pointilles
- Stats de la seance (Presents, Resolutions, Adoptees, Rejetees, Quorum, Duree) bien presentees
- Signature du president bien identifiee

**Ameliorations** :
- [ ] "e la seance" tronque => "Resume de la seance"
- [ ] "re-validation" tronque => "Pre-validation"
- [ ] "Verification en cours..." semble bloque — pas de spinner ou feedback de progression
- [ ] Le bouton "Relancer les verifications" devrait etre plus visible si la verif echoue
- [ ] Les stat-cards montrent tous "—" — pas de distinction entre "pas de donnees" et "zero"

---

### 2.8 Archives (`archives.htmx.html`)

**Points positifs** :
- Filtres par annee et recherche texte
- Toggle grille/liste
- Empty state clair ("Aucune seance archivee")
- Tags de filtre (AG Extra., Conseil) utiles

**Ameliorations** :
- [ ] Le premier tag est tronque ("d." au lieu du texte complet)
- [ ] La barre de stats en gris ("0 AVEC PV, — RESOLUTIONS, — BULLETINS") est peu lisible — le contraste texte/fond est faible
- [ ] L'empty state pourrait proposer un CTA ("Aller valider une seance")

---

### 2.9 Tableaux de bord / Analytics (`analytics.htmx.html`)

**Points positifs** :
- KPIs clairs en haut (Membres, Resolutions, Votes, Participation, Taux d'adoption)
- Tendances "stable" avec indicateur
- Onglets temporels (Trimestre, Annee, Tout)
- Sous-onglets thematiques (Resolutions, Temps de vote, Anomalies)
- Export PDF disponible

**Ameliorations** :
- [ ] Premier stat card tronque (sidebar overlap)
- [ ] Les graphiques "Participation par seance", "Seances par mois" sont vides avec texte "Chargement des participations" — le loading state reste indefiniment visible
- [ ] "Detail des presences" montre "Aucune donnee" — un empty state plus informatif serait utile
- [ ] Le bandeau bleu "les donnees sont agregees et ne revelent pas les votes individuels" est coupe a gauche

---

### 2.10 Proces-verbal / Report (`report.htmx.html`)

**Points positifs** :
- Export PDF en un clic dans le header
- Envoi du PV par email integre
- Preview dans un nouvel onglet

**Ameliorations** :
- [ ] "PV par email" et "ation du PV" tronques par la sidebar
- [ ] Le champ email "xemple.com" est tronque — le placeholder devrait etre visible en entier
- [ ] La zone de preview est vide avec aucun feedback — "Aucun PV genere" serait mieux que du vide
- [ ] Le bouton "Ouvrir dans un nouvel onglet" est isole — grouper avec "Exporter PDF"

---

### 2.11 Controle & Audit / Trust (`trust.htmx.html`)

**Points positifs** :
- Tableau de bord d'integrite avec "CONFORME" bien visible
- Checklist (President renseigne, Membres presents, Quorum atteint) tres claire
- Barre de progression "9 sur 10 controles valides (90%)"
- Hash SHA-256 pour verification
- Toast d'erreur "Erreur chargement resolutions" visible

**Ameliorations** :
- [ ] Le modal "Detail de l'evenement" s'ouvre avec tous les champs a "—" — il devrait soit ne pas s'ouvrir, soit afficher un message explicite
- [ ] "bord d'integrite" tronque => "Tableau de bord d'integrite"
- [ ] Le toast d'erreur rouge "Erreur chargement resolutions" persiste — devrait avoir un bouton retry ou autodismiss
- [ ] "INTEGRITE ? —" n'est pas clair — que signifie "?" ?

---

### 2.12 Cloture / Post-session (`postsession.htmx.html`)

**Points positifs** :
- Parcours guide en 4 etapes clair (Selection, Validation, PV, Envoi & Archivage)
- Select de seance terminee explicite
- Resume des resultats avec stats

**Ameliorations** :
- [ ] "es resultats" et "otes ont ete correctement enregistres" tronques
- [ ] "r resolution" tronque => "Par resolution"
- [ ] Les stat-cards montrent tous "—" sans distinction zero/absent
- [ ] Le tableau "RESOLUTION, DECISION, POUR, CONTRE, ABST., MAJORITE" est vide sans message

---

### 2.13 Vote public (`public.htmx.html`)

**Points positifs** :
- Interface de projection tres claire et lisible
- Gros chiffres, couleurs semantiques (vert/rouge/gris)
- Titre de resolution bien visible avec montant
- Statuts "En attente" et "Non atteint" clairement differencies
- Compteur de votants en bas

**Ameliorations** :
- [ ] Les 3 jauges "0% / 0 voix" sont identiques visuellement — sans label POUR/CONTRE/ABSTENTION au-dessus, c'est ambigu
- [ ] L'espace entre les jauges et les labels POUR/CONTRE/ABSTENTION est trop grand
- [ ] Le footer "AG-VOTE / Mis a jour : 06:12:04" est utile mais pourrait inclure le nom de la seance

---

### 2.14 Documentation (`docs.htmx.html`)

**Points positifs** :
- Layout deux colonnes (sommaire + contenu) bien conçu (quand ça marche)

**Ameliorations** :
- [ ] **BLOQUANT** : "marked is not defined" — page completement cassee sans CDN
- [ ] Le bouton bleu sans texte visible (probablement "Retour" ou "Recharger") est inutilisable
- [ ] La sidebar docs a gauche montre des labels tronques ("ux)", "ur)")

---

### 2.15 Aide / Help (`help.htmx.html`)

**Points positifs** :
- Tours guides par section (Seances, Membres, Operateur, Vote, etc.) avec durees estimees
- FAQ avec recherche et filtres par categorie
- Structure claire : tours > FAQ > sections thematiques
- Questions bien formulees

**Ameliorations** :
- [ ] Le titre "es reponses a vos questions" est tronque
- [ ] Les tours guides montrent "3 etapes, 2 min" — mais pas de bouton "Lancer" visible directement (il faut cliquer la card)
- [ ] La barre de recherche FAQ pourrait avoir un placeholder plus explicite

---

### 2.16 Templates Email (`email-templates.htmx.html`)

**Points positifs** :
- Empty state propre avec CTA "Creer un template"
- Double action : "Creer templates par defaut" et "Nouveau template"
- Design minimaliste et clair

**Ameliorations** :
- [ ] "Creer templates par defaut" n'explique pas quels templates seront crees — ajouter un tooltip ou description
- [ ] L'icone d'enveloppe dans l'empty state est generique — un apercu de template serait plus parlant

---

## 3. Mobile (375x812)

### 3.1 Login mobile

**Resultat** : Excellent. Le formulaire est bien centre, les boutons "Utiliser" des comptes demo sont accessibles. Pas de probleme de responsive.

### 3.2 Hub mobile

**Ameliorations** :
- [ ] Le texte de progression deborde a droite ("convocations re..." est coupe)
- [ ] Les boutons header (Guide, Modifier, Seances) sont tres serres
- [ ] Le stepper vertical (Preparer > Envoyer > Pointer > Piloter > Cloturer > Archiver) prend trop de place verticale

### 3.3 Meetings mobile

- [ ] La search modal occupe presque tout l'ecran — normal sur mobile mais le clavier virtuel va comprimer davantage
- [ ] Le wizard (1-2-3-4) est a peine lisible
- [ ] Le formulaire est fonctionnel

### 3.4 Members mobile

**Resultat** : Correct. Le formulaire d'ajout est bien adapte. Les stat-cards se reorganisent en grille 3 colonnes.

**Ameliorations** :
- [ ] Les stats montrent "0 TOTAL, 0 ACTIFS, 0 INACTIFS" alors qu'il y a des membres en base

### 3.5 Admin mobile

- [ ] Les boutons CTA "Creer une seance / Guide rapide" se chevauchent (le bleu deborde sur le contour)
- [ ] Les stat-cards en grille 2x2 sont lisibles et bien proportionnees
- [ ] La banniere de bienvenue prend beaucoup de place — plus problematique sur mobile

---

## 4. Recapitulatif des actions prioritaires

### Priorite 1 — Bloquants / Haute severite

| # | Page | Action |
|---|------|--------|
| ~~1~~ | ~~Toutes~~ | ~~Corriger le padding-left de .app-main pour eviter la troncature du contenu derriere la sidebar~~ CORRIGE (CSS `.app-sidebar.pinned ~ .app-main` rule) |
| ~~2~~ | ~~Docs~~ | ~~Vendoriser marked.min.js en local~~ CORRIGE (vendor/marked.min.js) |
| ~~3~~ | ~~Members~~ | ~~Corriger l'affichage des stats~~ CORRIGE (KPIs initiaux a mdash au lieu de 0) |

### Priorite 2 — UX importante

| # | Page | Action |
|---|------|--------|
| ~~4~~ | ~~Admin/Meetings~~ | ~~Rendre la banniere Bienvenue dismissable de facon permanente~~ CORRIGE (localStorage meetings) |
| ~~5~~ | ~~Operator/Meetings~~ | ~~Empecher l'ouverture automatique de la search modal~~ CORRIGE |
| ~~6~~ | ~~Trust~~ | ~~Wiring du modal Detail de l'evenement~~ CORRIGE (click row + close + overlay dismiss) |
| ~~7~~ | ~~Toutes~~ | ~~Distinguer — vs 0 dans les stat-cards~~ CORRIGE (meetings + members init a mdash) |
| ~~8~~ | ~~Report~~ | ~~Ajouter un empty state dans la zone de preview PV~~ CORRIGE |

### Priorite 3 — Polish / Ameliorations

| # | Page | Action |
|---|------|--------|
| ~~9~~ | ~~Session timer~~ | ~~Warning reduit a 1 min avant expiration + templates HTML morts supprimes~~ CORRIGE |
| ~~10~~ | ~~Login~~ | ~~Icone oeil SVG au lieu de "Voir" texte~~ CORRIGE |
| ~~11~~ | ~~Public~~ | Labels POUR/CONTRE/ABSTENTION deja presents dans le bar-chart — N/A |
| ~~12~~ | ~~Analytics~~ | ~~Spinner CSS sur les chart-containers pendant le chargement~~ CORRIGE |
| ~~13~~ | ~~Help~~ | ~~Badge "Lancer" visible sur chaque tour-card~~ CORRIGE |
| ~~14~~ | ~~Archives~~ | ~~CTA "Voir les seances" dans l'empty state~~ CORRIGE |
| ~~15~~ | ~~Email templates~~ | ~~Bouton "Creer par defaut" avec tooltip dans le card-header~~ CORRIGE |
| ~~16~~ | ~~Mobile~~ | ~~CSS .ob-body/.ob-actions + responsive wrap a 600px~~ CORRIGE |
