# FAQ — AG-Vote

## General

### Qu'est-ce qu'AG-Vote ?
AG-Vote est une application web de gestion de seances de vote formelles (assemblees generales, conseils syndicaux, reunions de copropriete). Elle couvre le cycle complet : preparation, conduite en direct, vote electronique, calcul des resultats, validation et generation des livrables (PV, exports CSV).

### Quelles sont les technologies utilisees ?
- **Backend** : PHP 8.3+, sans framework
- **Base de donnees** : PostgreSQL 16+
- **Frontend** : HTML statique + HTMX pour les interactions dynamiques, JavaScript vanilla
- **Serveur** : Apache 2.4+ avec mod_rewrite (ou serveur PHP integre pour le dev)

Aucune dependance npm, aucun bundler, aucun framework JavaScript.

### Faut-il une connexion Internet ?
Non. AG-Vote fonctionne en reseau local ferme. L'application est auto-hebergee et ne depend d'aucun service externe. Seul l'envoi d'invitations par email necessite une connexion SMTP.

---

## Installation & Configuration

### Comment installer AG-Vote ?
Voir le guide complet dans [INSTALLATION.md](INSTALLATION.md). En resume :
1. Installer PHP 8.3+ et PostgreSQL 16+
2. Creer la base de donnees et executer `database/schema.sql`
3. Configurer le fichier `.env` (copier depuis `.env.production`)
4. Lancer le serveur PHP ou configurer Apache

### Ou se trouve la configuration ?
Le fichier `.env` a la racine du projet. Les variables principales :
- `DB_DSN`, `DB_USER`, `DB_PASS` — connexion PostgreSQL
- `APP_AUTH_ENABLED` — activer l'authentification (1 en production)
- `CSRF_ENABLED` — activer la protection CSRF (1 en production)
- `APP_SECRET` — secret cryptographique (64 caracteres minimum)

### Comment creer un utilisateur ?
1. Via l'interface Admin (`/admin.htmx.html`) > onglet Utilisateurs > Nouveau
2. Ou directement en base :
```sql
INSERT INTO users (id, tenant_id, email, display_name, api_key_hash, role, is_active)
VALUES (gen_random_uuid(), 'aaaaaaaa-1111-2222-3333-444444444444',
        'operateur@example.com', 'Jean Dupont',
        encode(sha256('ma-cle-api-secrete'::bytea), 'hex'),
        'operator', true);
```

### Comment se connecter ?
Ouvrir `/login.html` et saisir la cle API de l'utilisateur. Cela cree une session PHP — la cle n'a pas besoin d'etre resaisie tant que la session est active.

### Quelles sont les cles de test par defaut ?
Les cles definies dans `database/seeds/02_test_users.sql` :
- `admin-key-2026-secret` (role admin)
- `operator-key-2026-secret` (role operator)
- `auditor-key-2026-secret` (role auditor)
- `viewer-key-2026-secret` (role viewer)

**Changer ces cles en production.**

---

## Roles & Permissions

### Quelle est la difference entre roles systeme et roles de seance ?
- **Roles systeme** (admin, operator, auditor, viewer) : permanents, definis dans la table `users`. Determinant l'acces aux pages et endpoints.
- **Roles de seance** (president, assessor, voter) : assignes par reunion dans la table `meeting_roles`. Un operateur peut etre aussi president d'une seance specifique.

### Qui peut faire quoi ?
| Action | admin | operator | auditor | viewer | president |
|--------|-------|----------|---------|--------|-----------|
| Gerer les utilisateurs | Oui | — | — | — | — |
| Creer une seance | Oui | Oui | — | — | — |
| Ouvrir/fermer un vote | Oui | Oui | — | — | — |
| Valider la seance | Oui | — | — | — | Oui |
| Consulter l'audit | Oui | — | Oui | — | — |
| Voir les archives | Oui | Oui | Oui | Oui | — |
| Exporter CSV/PV | Oui | Oui | Oui | — | — |

### Qu'est-ce que le role auditor ?
Anciennement appele "trust", c'est le role de controle et de conformite. L'auditeur peut consulter le journal d'audit, les anomalies detectees et les verifications de coherence. Il ne peut pas modifier les donnees.

---

## Conduite d'une seance

### Quel est le deroulement type d'une seance ?
1. **Preparation** : Creer la seance, ajouter les membres, configurer les resolutions et les politiques de quorum/vote
2. **Ouverture** : Passer la seance en `live`, pointer les presences
3. **Vote** : Ouvrir chaque resolution, attendre les votes, cloturer
4. **Consolidation** : Verifier les resultats, traiter les anomalies
5. **Validation** : Le president valide, la seance est verrouillee
6. **Livrables** : Generer le PV, exporter les CSV

Voir [UTILISATION_LIVE.md](UTILISATION_LIVE.md) pour le guide operationnel complet.

### Peut-on modifier une seance validee ?
Non. Apres validation, la base de donnees est verrouillee par des triggers PostgreSQL. Toute tentative de modification retourne une erreur HTTP 409. C'est une garantie d'integrite juridique.

### Que faire en cas de panne reseau pendant un vote ?
Utiliser le **mode degrade** : l'operateur saisit manuellement les resultats via `degraded_tally.php` avec une justification obligatoire. L'incident est trace dans le journal d'audit.

### Comment fonctionne le vote electronique ?
1. L'operateur genere des tokens de vote (un par votant) via `vote_tokens_generate.php`
2. Chaque votant recoit un lien unique (QR code ou URL)
3. Le votant accede a `/vote.htmx.html`, choisit son vote et confirme
4. Le token est consomme (anti-rejeu) — impossible de voter deux fois
5. Le bulletin est enregistre avec le poids du votant

### Comment fonctionne le quorum ?
Le quorum est calcule automatiquement a partir des presences et des procurations. Deux modes :
- **Par personnes** : nombre de presents + representes >= seuil
- **Par poids** : somme des poids (tantiemes) des presents >= seuil

Chaque resolution peut avoir sa propre politique de quorum.

### Que se passe-t-il si le quorum n'est pas atteint ?
La resolution reste valide mais le resultat est marque "quorum non atteint". L'auditeur est notifie d'une anomalie. Le president decide de la suite (reporter, vote consultatif, etc.).

---

## Exports & Livrables

### Quels exports sont disponibles ?
- **PV (Proces-Verbal)** : HTML et PDF, genere apres validation
- **Presences CSV** : Liste des pointages avec mode et horodatage
- **Votes CSV** : Detail des votes par resolution
- **Resultats CSV** : Synthese des resultats par resolution
- **Membres CSV** : Registre des membres
- **Audit CSV** : Journal d'audit complet

### Quand peut-on exporter ?
Les exports CSV sont disponibles a tout moment. Le PV officiel est genere apres validation de la seance.

---

## Securite

### Comment fonctionne l'authentification ?
Par cle API. Chaque utilisateur possede une cle unique dont le hash SHA256 est stocke en base. Deux modes :
1. **Header HTTP** : `X-Api-Key: ma-cle` (pour les appels API directs)
2. **Session PHP** : apres connexion via `/login.html`, une session est creee

### Les cles API sont-elles stockees en clair ?
Non. Seul le hash SHA256 de la cle est stocke dans la table `users.api_key_hash`. La cle en clair n'est jamais conservee cote serveur.

### Le journal d'audit est-il infalsifiable ?
Le journal `audit_events` utilise une chaine de hachage SHA256 : chaque evenement inclut le hash de l'evenement precedent. Toute suppression ou modification casse la chaine, rendant la falsification detectable.

### Qu'est-ce que la protection CSRF ?
Activee via `CSRF_ENABLED=1`, elle verifie un token specifique sur les requetes POST/PUT/DELETE pour empecher les attaques de type Cross-Site Request Forgery. Desactivee en developpement pour simplifier les tests.

---

## Developpement

### Comment lancer l'environnement de dev ?
```bash
php -S 0.0.0.0:8000 -t public
```
Puis acceder a `http://localhost:8000/login.html`.

### Comment reinitialiser les donnees de demo ?
Via l'interface Admin > Reset demo, ou par API :
```bash
curl -X POST http://localhost:8000/api/v1/meeting_reset_demo.php \
  -H "X-Api-Key: admin-key-2026-secret" \
  -H "Content-Type: application/json" \
  -d '{"meeting_id": "UUID_DE_LA_SEANCE"}'
```

### Comment ajouter un endpoint API ?
1. Creer un fichier PHP dans `public/api/v1/`
2. Commencer par `require __DIR__ . '/../../../app/api.php';`
3. Appeler `api_require_role(...)` pour securiser
4. Utiliser `api_request('POST')` pour valider la methode
5. Utiliser `api_ok(...)` / `api_fail(...)` pour les reponses
6. Utiliser `api_current_tenant_id()` pour le tenant
7. Appeler `audit_log(...)` pour tracer l'action

### Ou trouver la documentation technique ?
- [ARCHITECTURE.md](ARCHITECTURE.md) — Architecture, patterns, conventions
- [API.md](API.md) — Reference complete des endpoints
- [SECURITY.md](SECURITY.md) — Securite et authentification

### Comment lancer les tests ?
```bash
./vendor/bin/phpunit tests/
```
Voir [TESTS_README.md](TESTS_README.md) pour la configuration.

---

## Production

### Checklist de deploiement ?
Voir [PRODUCTION.md](../PRODUCTION.md) pour la checklist complete. Points critiques :
1. `APP_ENV=production`, `APP_DEBUG=0`
2. `APP_AUTH_ENABLED=1`, `CSRF_ENABLED=1`, `RATE_LIMIT_ENABLED=1`
3. Changer `APP_SECRET` (64 caracteres aleatoires)
4. Changer les cles API de test
5. Configurer HTTPS
6. Restreindre les permissions `.env` (chmod 600)

### Comment sauvegarder la base ?
```bash
pg_dump -U vote_app vote_app > backup_$(date +%Y%m%d_%H%M%S).sql
```
Planifier via cron pour des sauvegardes automatiques.

### Comment mettre a jour ?
1. Sauvegarder la base
2. Tirer la nouvelle version (`git pull`)
3. Executer les migrations SQL si necessaire (`database/migrations/`)
4. Verifier les variables `.env` (nouvelles variables ?)
5. Tester les endpoints critiques
6. Redemarrer Apache
