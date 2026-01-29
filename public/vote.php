<?php
// public/vote.php — interface voteur (token)
// NOTE: Ceci est un MVP HTML (hors HTMX) ; l’essentiel est la sécurité et l’écriture en DB.

require_once __DIR__ . '/../app/bootstrap.php';

$pdo = $pdo ?? null;
if (!$pdo) {
  http_response_code(500);
  echo "DB non initialisée";
  exit;
}

$token = $_GET['token'] ?? null;
if (!$token) {
  http_response_code(400);
  echo "Token manquant";
  exit;
}

// IMPORTANT: les tokens sont HMACés avec APP_SECRET (même secret que operator_open_vote.php)
$hash = hash_hmac('sha256', (string)$token, APP_SECRET);

$stmt = $pdo->prepare("SELECT * FROM vote_tokens WHERE token_hash = :h AND used_at IS NULL AND expires_at > NOW()");
$stmt->execute(['h'=>$hash]);
$row = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$row) {
  http_response_code(403);
  echo "Token invalide ou expiré";
  exit;
}

// Intégrité LIVE :
// - refuser si la motion n'est pas ouverte (opened_at NULL ou closed_at non NULL)
// - refuser si la séance est validée (validated_at non NULL)
$stmt2 = $pdo->prepare(
  "SELECT
     m.opened_at,
     m.closed_at,
     mt.validated_at
   FROM motions m
   JOIN meetings mt ON mt.id = m.meeting_id AND mt.tenant_id = m.tenant_id
   WHERE m.id = :motion_id AND m.meeting_id = :meeting_id AND m.tenant_id = :tenant_id
   LIMIT 1"
);
$stmt2->execute([
  'motion_id'  => $row['motion_id'],
  'meeting_id' => $row['meeting_id'],
  'tenant_id'  => $row['tenant_id'],
]);
$ctx = $stmt2->fetch(PDO::FETCH_ASSOC);

if (!$ctx) {
  http_response_code(404);
  echo "Motion introuvable";
  exit;
}

// Meeting validé => plus aucun vote possible
if (!empty($ctx['validated_at'])) {
  http_response_code(409);
  header('Content-Type: text/plain; charset=utf-8');
  echo "meeting_validated";
  exit;
}

// Motion pas ouverte / déjà clôturée
if (empty($ctx['opened_at']) || !empty($ctx['closed_at'])) {
  http_response_code(409);
  header('Content-Type: text/plain; charset=utf-8');
  echo "motion_not_open";
  exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $vote = $_POST['vote'] ?? null;
  $confirm = ($_POST['confirm'] ?? '0') === '1';

  // On accepte les libellés FR (UI) et on mappe vers l'ENUM DB.
  $map = [
    'pour' => 'for',
    'contre' => 'against',
    'abstention' => 'abstain',
    'blanc' => 'nsp',
  ];
  if (!is_string($vote) || !isset($map[$vote])) {
    http_response_code(400);
    echo "Vote invalide";
    exit;
  }

  $dbVote = $map[$vote];

  // Étape 1 : confirmation obligatoire
  if (!$confirm) {
    $labels = ['pour'=>'Pour','contre'=>'Contre','abstention'=>'Abstention','blanc'=>'Blanc'];
    $chosen = $labels[$vote] ?? $vote;
    ?>
    <!doctype html>
    <html lang="fr">
    <head>
      <meta charset="utf-8">
      <meta name="viewport" content="width=device-width, initial-scale=1">
      <title>Confirmer votre vote</title>
      <link rel="stylesheet" href="/assets/css/app.css">
      <link rel="stylesheet" href="/assets/css/ui-kit.css">
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
    <?php
    exit;
  }

  // Tenant id via meeting
  $mt = $pdo->prepare("SELECT tenant_id FROM meetings WHERE id = :m");
  $mt->execute(['m'=>$row['meeting_id']]);
  $mrow = $mt->fetch(PDO::FETCH_ASSOC);
  if (!$mrow) {
    http_response_code(400);
    echo "Séance inconnue";
    exit;
  }

  // Marque le token utilisé
  $pdo->prepare("UPDATE vote_tokens SET used_at = NOW() WHERE token_hash = :h")->execute(['h'=>$hash]);

  // Enregistre le ballot (motion_id, tenant_id)
  $pdo->prepare("INSERT INTO ballots(tenant_id, meeting_id, member_id, motion_id, value, weight, cast_at, source) VALUES (:t,:m,:mb,:mo,:v,1.0,NOW(),'tablet')")
      ->execute([
        't'=>$mrow['tenant_id'],
        'm'=>$row['meeting_id'],
        'mb'=>$row['member_id'],
        'mo'=>$row['motion_id'],
        'v'=>$dbVote
      ]);

  echo "Vote enregistré";
  exit;
}
?>
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
        <button type="submit" name="vote" value="pour">✅ Pour</button>
        <button type="submit" name="vote" value="contre">❌ Contre</button>
        <button type="submit" name="vote" value="abstention">➖ Abstention</button>
        <button type="submit" name="vote" value="blanc">⚪ Blanc</button>
      </form>
    </div>
  </div>
</body>
</html>
