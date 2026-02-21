<?php
declare(strict_types=1);

/**
 * Variables attendues:
 * - $meetingTitle (string)
 * - $memberName   (string)
 * - $voteUrl      (string)
 * - $appUrl       (string)
 */
$meetingTitle ??= 'Séance';
$memberName ??= '';
$voteUrl ??= '#';

function e(string $s): string {
    return htmlspecialchars($s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

$preheader = "Votre lien de vote pour: {$meetingTitle}";
?>
<!doctype html>
<html lang="fr">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= e($meetingTitle) ?> – Invitation de vote</title>
</head>
<body style="margin:0; padding:0; background:#f3f4f6;">
  <div style="display:none; max-height:0; overflow:hidden; opacity:0; color:transparent;">
    <?= e($preheader) ?>
  </div>

  <div style="max-width:640px; margin:0 auto; padding:24px;">
    <div style="background:#ffffff; border:1px solid #e5e7eb; border-radius:16px; padding:22px;">
      <div style="font:700 18px/1.2 system-ui, -apple-system, Segoe UI, Roboto, Arial;">
        Invitation de vote
      </div>
      <div style="margin-top:6px; color:#6b7280; font:14px/1.5 system-ui, -apple-system, Segoe UI, Roboto, Arial;">
        Séance : <strong><?= e($meetingTitle) ?></strong>
      </div>

      <?php if ($memberName): ?>
      <div style="margin-top:14px; font:14px/1.6 system-ui, -apple-system, Segoe UI, Roboto, Arial;">
        Bonjour <strong><?= e($memberName) ?></strong>,
      </div>
      <?php endif; ?>

      <div style="margin-top:10px; color:#111827; font:14px/1.6 system-ui, -apple-system, Segoe UI, Roboto, Arial;">
        Cliquez sur le bouton ci-dessous pour accéder à votre bulletin de vote.
      </div>

      <div style="margin-top:18px;">
        <a href="<?= e($voteUrl) ?>"
           style="display:inline-block; background:#2563eb; color:#ffffff; text-decoration:none;
                  padding:10px 16px; border-radius:10px; font:600 14px/1 system-ui, -apple-system, Segoe UI, Roboto, Arial;">
          Accéder au vote
        </a>
      </div>

      <div style="margin-top:14px; color:#6b7280; font:12px/1.5 system-ui, -apple-system, Segoe UI, Roboto, Arial;">
        Si le bouton ne fonctionne pas, copiez-collez ce lien dans votre navigateur :<br>
        <span style="word-break:break-all;"><?= e($voteUrl) ?></span>
      </div>

      <hr style="border:none; border-top:1px solid #e5e7eb; margin:18px 0;">

      <div style="color:#6b7280; font:12px/1.5 system-ui, -apple-system, Segoe UI, Roboto, Arial;">
        Ce lien est personnel. Ne le partagez pas.
      </div>
    </div>

    <div style="margin-top:12px; color:#9ca3af; font:12px/1.5 system-ui, -apple-system, Segoe UI, Roboto, Arial; text-align:center;">
      Envoyé par <?= e($appUrl) ?>
    </div>
  </div>
</body>
</html>
