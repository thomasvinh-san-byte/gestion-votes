
## Tests — Regles strictes
- TOUJOURS cibler les tests : `php vendor/bin/phpunit tests/Unit/FichierConcerne.php --no-coverage`
- JAMAIS lancer toute la suite de tests sauf demande explicite
- Timeout : `timeout 60 php vendor/bin/phpunit ...`
- Si un test echoue 2 fois de suite, arrete-toi et demande
- Maximum 3 executions de tests par tache

## PHP
- Verifier la syntaxe avant de committer : `php -l fichier.php`
- Respecter les namespaces existants : AgVote\Controller, AgVote\Service, AgVote\Repository

## Git
- Messages de commit en anglais, format : `type(phase-plan): description`
- Ne jamais committer .env ou credentials

## Architecture
- Controllers HTML (login, setup, reset) : NE PAS etendre AbstractController, utiliser HtmlView::render()
- Controllers API : etendre AbstractController
- DI par constructeur avec parametres optionnels nullable pour les tests

## Langue
- Tout le texte visible par l'utilisateur est en francais
- Jamais mentionner "copropriete" ou "syndic" — l'app cible associations et collectivites uniquement
