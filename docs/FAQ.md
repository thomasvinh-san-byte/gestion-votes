# FAQ — AG-Vote

## Général

### Qu'est-ce qu'AG-Vote ?
AG-Vote est une application web de gestion de séances de vote formelles (assemblées générales, conseils syndicaux, réunions de copropriété). Elle couvre le cycle complet : préparation, conduite en direct, vote électronique, calcul des résultats, validation et génération des livrables (PV, exports CSV).

### Quelles sont les technologies utilisées ?
- **Backend** : PHP 8.3+, sans framework
- **Base de données** : PostgreSQL 16+
- **Frontend** : HTML statique + HTMX pour les interactions dynamiques, JavaScript vanilla
- **Serveur** : Apache 2.4+ avec mod_rewrite (ou serveur PHP intégré pour le dev)

Aucune dépendance npm, aucun bundler, aucun framework JavaScript.

### Faut-il une connexion Internet ?
Non. AG-Vote fonctionne en réseau local fermé. L'application est auto-hébergée et ne dépend d'aucun service externe. Seul l'envoi d'invitations par email nécessite une connexion SMTP.

---

## Installation et configuration

### Comment installer AG-Vote ?
Voir le guide complet dans [dev/INSTALLATION.md](dev/INSTALLATION.md). En résumé :
1. Installer PHP 8.3+ et PostgreSQL 16+
2. Créer la base de données et exécuter `database/schema.sql`
3. Configurer le fichier `.env` (copier depuis `.env.example`)
4. Lancer le serveur PHP ou configurer Apache

### Où se trouve la configuration ?
Le fichier `.env` à la racine du projet. Les variables principales :
- `DB_DSN`, `DB_USER`, `DB_PASS` — connexion PostgreSQL
- `APP_AUTH_ENABLED` — activer l'authentification (1 en production)
- `CSRF_ENABLED` — activer la protection CSRF (1 en production)
- `APP_SECRET` — secret cryptographique (64 caractères minimum)

### Comment créer un utilisateur ?
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
Ouvrir `/login.html` et saisir les identifiants de l'utilisateur (email et mot de passe). Cela crée une session PHP — les identifiants n'ont pas besoin d'être ressaisis tant que la session est active.

### Quels sont les comptes de test par défaut ?
Les comptes créés par `database/seeds/02_test_users.sql` :

| Rôle | Email | Mot de passe |
|------|-------|--------------|
| admin | `admin@ag-vote.local` | `Admin2026!` |
| operator | `operator@ag-vote.local` | `Operator2026!` |
| president | `president@ag-vote.local` | `President2026!` |
| votant | `votant@ag-vote.local` | `Votant2026!` |
| auditor | `auditor@ag-vote.local` | `Auditor2026!` |
| viewer | `viewer@ag-vote.local` | `Viewer2026!` |

**Changer ces identifiants en production.**

---

## Rôles et permissions

### Quelle est la différence entre rôles système et rôles de séance ?
- **Rôles système** (admin, operator, auditor, viewer) : permanents, définis dans la table `users`. Déterminent l'accès aux pages et endpoints.
- **Rôles de séance** (president, assessor, voter) : assignés par réunion dans la table `meeting_roles`. Un opérateur peut être aussi président d'une séance spécifique.

### Qui peut faire quoi ?
| Action | admin | operator | auditor | viewer | president |
|--------|-------|----------|---------|--------|-----------|
| Gérer les utilisateurs | Oui | — | — | — | — |
| Créer une séance | Oui | Oui | — | — | — |
| Ouvrir/fermer un vote | Oui | Oui | — | — | — |
| Valider la séance | Oui | — | — | — | Oui |
| Consulter l'audit | Oui | — | Oui | — | — |
| Voir les archives | Oui | Oui | Oui | Oui | — |
| Exporter CSV/PV | Oui | Oui | Oui | — | — |

### Qu'est-ce que le rôle auditor ?
Anciennement appelé "trust", c'est le rôle de contrôle et de conformité. L'auditeur peut consulter le journal d'audit, les anomalies détectées et les vérifications de cohérence. Il ne peut pas modifier les données.

---

## Conduite d'une séance

### Quel est le déroulement type d'une séance ?
1. **Préparation** : Créer la séance, ajouter les membres, configurer les résolutions et les politiques de quorum/vote
2. **Ouverture** : Passer la séance en `live`, pointer les présences
3. **Vote** : Ouvrir chaque résolution, attendre les votes, clôturer
4. **Consolidation** : Vérifier les résultats, traiter les anomalies
5. **Validation** : Le président valide, la séance est verrouillée
6. **Livrables** : Générer le PV, exporter les CSV

Voir [UTILISATION_LIVE.md](UTILISATION_LIVE.md) pour le guide opérationnel complet.

### Peut-on modifier une séance validée ?
Non. Après validation, la base de données est verrouillée par des triggers PostgreSQL. Toute tentative de modification retourne une erreur HTTP 409. C'est une garantie d'intégrité juridique.

### Que faire en cas de panne réseau pendant un vote ?
Utiliser le **mode dégradé** : l'opérateur saisit manuellement les résultats via `degraded_tally.php` avec une justification obligatoire. L'incident est tracé dans le journal d'audit.

### Comment fonctionne le vote électronique ?
1. L'opérateur génère des tokens de vote (un par votant) via `vote_tokens_generate.php`
2. Chaque votant reçoit un lien unique (QR code ou URL)
3. Le votant accède à `/vote.htmx.html`, choisit son vote et confirme
4. Le token est consommé (anti-rejeu) — impossible de voter deux fois
5. Le bulletin est enregistré avec le poids du votant

### Comment fonctionne le quorum ?
Le quorum est calculé automatiquement à partir des présences et des procurations. Deux modes :
- **Par personnes** : nombre de présents + représentés >= seuil
- **Par poids** : somme des poids (tantièmes) des présents >= seuil

Chaque résolution peut avoir sa propre politique de quorum.

### Que se passe-t-il si le quorum n'est pas atteint ?
La résolution reste valide mais le résultat est marqué "quorum non atteint". L'auditeur est notifié d'une anomalie. Le président décide de la suite (reporter, vote consultatif, etc.).

---

## Exports et livrables

### Quels exports sont disponibles ?
- **PV (Procès-Verbal)** : HTML et PDF, généré après validation
- **Présences CSV** : Liste des pointages avec mode et horodatage
- **Votes CSV** : Détail des votes par résolution
- **Résultats CSV** : Synthèse des résultats par résolution
- **Membres CSV** : Registre des membres
- **Audit CSV** : Journal d'audit complet

### Quand peut-on exporter ?
Les exports CSV sont disponibles à tout moment. Le PV officiel est généré après validation de la séance.

---

## Sécurité

### Comment fonctionne l'authentification ?
Par mot de passe (connexion via `/login.html`) ou par clé API. Chaque utilisateur possède un mot de passe hashé (bcrypt) et optionnellement une clé API dont le hash SHA256 est stocké en base. Deux modes :
1. **Session PHP** : après connexion via `/login.html`, une session est créée
2. **Header HTTP** : `X-Api-Key: ma-cle` (pour les appels API directs)

### Les mots de passe sont-ils stockés en clair ?
Non. Les mots de passe sont hashés avec bcrypt. Les clés API sont hashées avec SHA256. Rien n'est stocké en clair côté serveur.

### Le journal d'audit est-il infalsifiable ?
Le journal `audit_events` utilise une chaîne de hachage SHA256 : chaque événement inclut le hash de l'événement précédent. Toute suppression ou modification casse la chaîne, rendant la falsification détectable.

### Qu'est-ce que la protection CSRF ?
Activée via `CSRF_ENABLED=1`, elle vérifie un token spécifique sur les requêtes POST/PUT/DELETE pour empêcher les attaques de type Cross-Site Request Forgery. Désactivée en développement pour simplifier les tests.

---

## Développement

### Comment lancer l'environnement de dev ?
```bash
php -S 0.0.0.0:8080 -t public
```
Puis accéder à `http://localhost:8080/login.html`.

### Comment réinitialiser les données de démo ?
Via l'interface Admin > Reset demo, ou par API :
```bash
curl -X POST http://localhost:8080/api/v1/meeting_reset_demo.php \
  -H "X-Api-Key: admin-key-2026-secret" \
  -H "Content-Type: application/json" \
  -d '{"meeting_id": "UUID_DE_LA_SEANCE"}'
```

### Comment ajouter un endpoint API ?
1. Créer un fichier PHP dans `public/api/v1/`
2. Commencer par `require __DIR__ . '/../../../app/api.php';`
3. Appeler `api_require_role(...)` pour sécuriser
4. Utiliser `api_request('POST')` pour valider la méthode
5. Utiliser `api_ok(...)` / `api_fail(...)` pour les réponses
6. Utiliser `api_current_tenant_id()` pour le tenant
7. Appeler `audit_log(...)` pour tracer l'action

### Où trouver la documentation technique ?
- [dev/ARCHITECTURE.md](dev/ARCHITECTURE.md) — Architecture, patterns, conventions
- [dev/API.md](dev/API.md) — Référence complète des endpoints
- [dev/SECURITY.md](dev/SECURITY.md) — Sécurité et authentification

### Comment lancer les tests ?
```bash
./vendor/bin/phpunit tests/
```
Voir [dev/TESTS.md](dev/TESTS.md) pour la configuration complète.

---

## Production

### Checklist de déploiement ?
Voir [dev/SECURITY.md](dev/SECURITY.md) pour la checklist complète. Points critiques :
1. `APP_ENV=production`, `APP_DEBUG=0`
2. `APP_AUTH_ENABLED=1`, `CSRF_ENABLED=1`, `RATE_LIMIT_ENABLED=1`
3. Changer `APP_SECRET` (64 caractères aléatoires)
4. Changer les mots de passe et clés API de test
5. Configurer HTTPS
6. Restreindre les permissions `.env` (chmod 600)

### Comment sauvegarder la base ?
```bash
pg_dump -U vote_app vote_app > backup_$(date +%Y%m%d_%H%M%S).sql
```
Planifier via cron pour des sauvegardes automatiques.

### Comment mettre à jour ?
1. Sauvegarder la base
2. Tirer la nouvelle version (`git pull`)
3. Exécuter les migrations SQL si nécessaire (`database/migrations/`)
4. Vérifier les variables `.env` (nouvelles variables ?)
5. Tester les endpoints critiques
6. Redémarrer Apache
