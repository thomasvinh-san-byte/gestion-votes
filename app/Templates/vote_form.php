<!doctype html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Vote</title>
    <style>
        body{font-family:system-ui,-apple-system,Segoe UI,Roboto,Ubuntu,Cantarell,Noto Sans,sans-serif;margin:0;background:#f7f7f7;color:#111}
        .wrap{max-width:520px;margin:40px auto;padding:16px}
        .card{background:#fff;border-radius:16px;box-shadow:0 8px 22px rgba(0,0,0,.08);padding:18px}
        h1{margin:0 0 12px 0;font-size:20px}
        button{width:100%;padding:14px 16px;border:0;border-radius:12px;font-size:16px;margin:8px 0;cursor:pointer}
    </style>
</head>
<body>
    <div class="wrap">
        <div class="card">
            <h1>Exprimer votre vote</h1>
            <form method="post">
                <button type="submit" name="vote" value="pour">&#x2705; Pour</button>
                <button type="submit" name="vote" value="contre">&#x274C; Contre</button>
                <button type="submit" name="vote" value="abstention">&#x2796; Abstention</button>
                <button type="submit" name="vote" value="blanc">&#x26AA; Blanc</button>
            </form>
        </div>
    </div>
</body>
</html>
