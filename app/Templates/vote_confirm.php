<!doctype html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Confirmer votre vote</title>
    <link rel="stylesheet" href="/assets/css/app.css">
</head>
<body style="padding:18px;">
    <div class="card" style="max-width:520px;margin:0 auto;padding:16px;">
        <h1 style="margin:0 0 8px;">Confirmer votre vote</h1>
        <p class="muted" style="margin:0 0 12px;">Vous allez voter : <strong><?= htmlspecialchars($chosen) ?></strong></p>

        <form method="post">
            <input type="hidden" name="vote" value="<?= htmlspecialchars($vote) ?>">
            <input type="hidden" name="confirm" value="1">
            <button class="btn primary" style="width:100%;">Confirmer</button>
        </form>

        <form method="post" style="margin-top:10px;">
            <input type="hidden" name="confirm" value="0">
            <button class="btn" style="width:100%;">Modifier</button>
        </form>
    </div>
</body>
</html>
