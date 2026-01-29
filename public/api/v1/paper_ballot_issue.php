<?php
require __DIR__ . '/../../../app/api.php';
api_require_role('operator');

$q = api_request('GET');
$meetingId = api_require_uuid($q, 'meeting_id');
$motionId  = api_require_uuid($q, 'motion_id');

$mo = db_select_one("SELECT id, title FROM motions WHERE id = ? AND meeting_id = ?", [$motionId, $meetingId]);
if (!$mo) api_fail('motion_not_found', 404);

$code = db_scalar("SELECT gen_random_uuid()");
$hash = hash_hmac('sha256', $code, APP_SECRET);

db_execute(
  "INSERT INTO paper_ballots(meeting_id, motion_id, code, code_hash) VALUES (:m,:mo,:c,:h)",
  [':m'=>$meetingId, ':mo'=>$motionId, ':c'=>$code, ':h'=>$hash]
);

header('Content-Type: text/html; charset=utf-8');
$h = fn($s) => htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');

echo "<!doctype html><html lang=\"fr\"><head><meta charset=\"utf-8\">";
echo "<meta name=\"viewport\" content=\"width=device-width,initial-scale=1\">";
echo "<title>Bulletin papier</title>";
echo "<style>
  body{font-family:system-ui,-apple-system,Segoe UI,Roboto,Ubuntu,Cantarell,Noto Sans,sans-serif;margin:24px;color:#111}
  .card{border:1px solid #e6e6e6;border-radius:14px;padding:16px;max-width:700px}
  .row{display:flex;gap:16px;align-items:flex-start;justify-content:space-between;flex-wrap:wrap}
  .muted{color:#666}
  .tiny{font-size:12px}
  .opt{border:1px solid #ddd;border-radius:10px;padding:10px 12px;margin:8px 0}
  .kbd{font-family:ui-monospace,Menlo,Monaco,Consolas,monospace;background:#f3f3f3;border:1px solid #e6e6e6;padding:2px 6px;border-radius:6px}
  @media print {.no-print{display:none} body{margin:8mm}}
</style>";
echo "</head><body>";
echo "<div class=\"no-print\" style=\"margin-bottom:12px;display:flex;gap:8px;align-items:center\">";
echo "<button onclick=\"window.print()\">Imprimer (PDF)</button>";
echo "<span class=\"muted tiny\">Astuce : « Enregistrer en PDF ».</span>";
echo "</div>";

echo "<div class=\"card\">";
echo "<div class=\"row\">";
echo "<div style=\"min-width:260px;flex:1\">";
echo "<h2 style=\"margin:0 0 6px 0\">Bulletin papier (secours)</h2>";
echo "<div class=\"muted\">Résolution : <strong>".$h($mo['title'])."</strong></div>";
echo "<div class=\"muted tiny\">Séance: <span class=\"kbd\">".$h($meetingId)."</span></div>";
echo "<div class=\"muted tiny\">Code bulletin: <span class=\"kbd\">".$h($code)."</span></div>";
echo "<div style=\"margin-top:12px\">";
echo "<div class=\"opt\"><strong>Pour</strong></div>";
echo "<div class=\"opt\"><strong>Contre</strong></div>";
echo "<div class=\"opt\"><strong>Abstention</strong></div>";
echo "<div class=\"opt\"><strong>Blanc</strong></div>";
echo "</div></div>";
echo "<div style=\"width:260px\">";
echo "<div id=\"qr\"></div>";
echo "<div class=\"muted tiny\" style=\"margin-top:8px\">Scanne ce QR ou saisis le code.</div>";
echo "</div>";
echo "</div>";
echo "</div>";

echo "<script src=\"/assets/js/qrcode.min.js\"></script>";
echo "<script>
  var el = document.getElementById('qr');
  var qr = qrcode(0,'M');
  qr.addData(" . json_encode((string)$code) . ");
  qr.make();
  el.innerHTML = qr.createSvgTag({cellSize:4, margin:0});
</script>";
echo "</body></html>";
