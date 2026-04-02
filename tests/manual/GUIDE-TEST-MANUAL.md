# Guide de Test Manuel — AG-VOTE

Scenario : **AG Ordinaire 2025** d'une association loi 1901 avec 15 membres.

## Prerequis

```bash
# Demarrer l'app
./bin/dev.sh

# Verifier que tout tourne
make status
```

App accessible sur **http://localhost:8080**

---

## Etape 1 : Connexion admin

1. Ouvrir http://localhost:8080/login
2. Se connecter avec : `admin@ag-vote.local` / `Admin2026!`
3. Verifier : dashboard affiche les KPI

**A verifier :**
- [ ] Page de login s'affiche correctement
- [ ] Connexion reussie, redirect vers dashboard
- [ ] KPI visibles (pas de cartes vides)

---

## Etape 2 : Import des membres

1. Aller dans **Membres** (sidebar)
2. Cliquer **Importer CSV**
3. Selectionner `tests/manual/membres-association.csv`
4. Verifier : 15 membres importes, 3 groupes crees (College A, B, C + Bureau)

**A verifier :**
- [ ] Import reussi : "15 membres importes"
- [ ] Thomas Vinh-San a ponderation 3
- [ ] Sophie Bernard dans Bureau + College A
- [ ] Pagination fonctionne si >50 membres
- [ ] Recherche par nom fonctionne (taper "Vinh" dans le champ recherche)

---

## Etape 3 : Creer une seance

1. Aller dans **Wizard** (sidebar)
2. Remplir :
   - Nom : `AG Ordinaire 2025`
   - Date : date du jour
   - Lieu : `Salle des fetes, 12 rue de la Mairie`
   - Politique de quorum : `Majorite simple (50%)`
   - Politique de vote : `Majorite simple`
3. Valider la creation

**A verifier :**
- [ ] Seance creee en statut DRAFT
- [ ] Redirige vers le hub ou la console operateur

---

## Etape 4 : Import des resolutions

1. Depuis la console operateur, aller dans **Ordre du jour**
2. Cliquer **Importer CSV**
3. Selectionner `tests/manual/resolutions-ag2025.csv`

**A verifier :**
- [ ] 6 resolutions importees dans l'ordre
- [ ] "Election du bureau" est marquee vote secret
- [ ] Les descriptions sont presentes

---

## Etape 5 : Import des presences

1. Depuis la console operateur, aller dans **Presences**
2. Cliquer **Importer CSV**
3. Selectionner `tests/manual/presences-ag2025.csv`

**A verifier :**
- [ ] 12 presents/remote, 1 excuse, 2 absents
- [ ] Quorum calcule (ponderation totale : 20, presents : 16 → quorum atteint)
- [ ] Fatima El Amrani et Emilie Chen en "remote"

---

## Etape 6 : Import des procurations

1. Depuis la console operateur, aller dans **Procurations**
2. Cliquer **Importer CSV**
3. Selectionner `tests/manual/procurations-ag2025.csv`

**A verifier :**
- [ ] 3 procurations importees
- [ ] Nathalie Rousseau → Philippe Leclerc
- [ ] Bouton "Telecharger PDF" visible sur chaque procuration
- [ ] Cliquer sur un PDF : document avec mandant, mandataire, seance, mention legale

---

## Etape 7 : Deroulement du vote

1. Passer la seance en **FROZEN** puis **LIVE**
2. Ouvrir la resolution "Approbation du rapport moral 2024"
3. Dans un autre onglet, ouvrir http://localhost:8080/vote
4. Se connecter comme votant (ou utiliser le lien d'invitation)
5. Voter POUR
6. Revenir a la console operateur, fermer le vote

**A verifier :**
- [ ] Vote enregistre
- [ ] Resultats affiches (pour/contre/abstention)
- [ ] Vote secret : les noms ne sont pas visibles dans les resultats

---

## Etape 8 : Test session timeout + resume

1. Dans **Parametres > Securite**, mettre le timeout a **5 minutes**
2. Ouvrir http://localhost:8080/vote dans un autre onglet
3. Attendre 5+ minutes sans activite
4. Essayer de voter → devrait rediriger vers login avec `?return_to=/vote`
5. Se reconnecter → devrait revenir sur /vote avec la seance en cours

**A verifier :**
- [ ] Redirect vers login avec parametre return_to
- [ ] Apres reconnexion, retour sur la page de vote
- [ ] Contexte de seance preserve (bon meeting selectionne)

---

## Etape 9 : Page Mon Compte

1. Cliquer sur "Mon Compte" dans le header
2. Verifier que le profil affiche nom, email, role
3. Changer le mot de passe :
   - Ancien : `Admin2026!`
   - Nouveau : `NouveauMdp2026!`
   - Confirmation : `NouveauMdp2026!`
4. Se deconnecter et se reconnecter avec le nouveau mot de passe

**A verifier :**
- [ ] Profil affiche correctement
- [ ] Changement de mot de passe reussi
- [ ] Reconnexion avec nouveau mot de passe OK
- [ ] Bouton "Exporter mes donnees (RGPD)" present et telecharge un JSON

---

## Etape 10 : Confirmation 2 etapes (admin)

1. Aller dans **Admin > Utilisateurs**
2. Essayer de supprimer un utilisateur
3. La modale doit demander votre mot de passe admin avant d'executer

**A verifier :**
- [ ] Modale de confirmation avec champ mot de passe
- [ ] Mauvais mot de passe → erreur "Mot de passe incorrect"
- [ ] Bon mot de passe → suppression executee

---

## Etape 11 : Post-session et PV

1. Fermer tous les votes ouverts
2. Passer la seance en **CLOSED** puis **VALIDATED**
3. Generer le PV (Proces-Verbal)
4. Verifier le PDF inline

**A verifier :**
- [ ] PV genere avec en-tete organisation, date, lieu
- [ ] Liste de presence avec ponderation
- [ ] Quorum confirme
- [ ] Resultats de chaque resolution
- [ ] Blocs signature president + secretaire
- [ ] Re-generer ne change pas le PV (snapshot immutable)

---

## Etape 12 : Reset password (test email)

1. Se deconnecter
2. Cliquer "Mot de passe oublie ?" sur la page de login
3. Entrer `thomas.vinh-san@delivrex.io`
4. Verifier que le message de confirmation s'affiche
5. Verifier la table email_queue en base :
   ```bash
   make db
   SELECT recipient_email, subject, status FROM email_queue ORDER BY created_at DESC LIMIT 5;
   ```

**A verifier :**
- [ ] Message "Si cette adresse est associee..." affiche (anti-enumeration)
- [ ] Email en queue dans la table email_queue
- [ ] Sujet : "Reinitialisation de votre mot de passe — AG-VOTE"

---

## Fichiers CSV fournis

| Fichier | Contenu | Membres |
|---------|---------|---------|
| `membres-association.csv` | 15 membres, 3 colleges, ponderations variables | Thomas (3), Sophie (2), Philippe (2), reste (1) |
| `presences-ag2025.csv` | 12 presents, 1 excuse, 2 absents, 2 en visio | Quorum largement atteint |
| `resolutions-ag2025.csv` | 6 resolutions dont 1 vote secret | AG classique loi 1901 |
| `procurations-ag2025.csv` | 3 delegations | Absents/excuses → presents |

---

## Remettre a zero

```bash
# Reset complet (supprime toutes les donnees)
make reset

# Ou juste recharger les seeds
make db
\i database/seeds/01_minimal.sql
\i database/seeds/02_test_users.sql
\i database/seeds/03_demo.sql
\q
```
