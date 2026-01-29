<?php
// public/admin/meeting_report.php

require __DIR__ . '/../../app/bootstrap.php';

// ⚠️ Ajuste ce chemin si ton vendor est ailleurs
require __DIR__ . '/../../vendor/autoload.php';

use Dompdf\Dompdf;

$meetingId = trim($_GET['meeting_id'] ?? '');
if ($meetingId === '') {
    http_response_code(400);
    echo "meeting_id manquant";
    exit;
}

// 1) Récupérer la séance
$meeting = db_select_one("
    SELECT
      id,
      title,
      status,
      created_at,
      validated_by,
      validated_at
    FROM meetings
    WHERE id = ?
      AND tenant_id = ?
", [$meetingId, DEFAULT_TENANT_ID]);

if (!$meeting) {
    http_response_code(404);
    echo "Séance introuvable";
    exit;
}

// 2) Récupérer les points d'ODJ
global $pdo;

$stmt = $pdo->prepare("
    SELECT
      id,
      idx,
      title,
      description
    FROM agendas
    WHERE meeting_id = ?
    ORDER BY idx ASC
");
$stmt->execute([$meetingId]);
$agendas = $stmt->fetchAll(PDO::FETCH_ASSOC);

// 3) Récupérer les résolutions + stats (reprise de meeting_stats.php logique)

$stmt = $pdo->prepare("
    SELECT
        mo.id   AS motion_id,
        mo.title,
        mo.agenda_id,

        -- Stats depuis les bulletins
        COUNT(b.id)                                        AS ballots_total,
        COUNT(b.id) FILTER (WHERE b.value = 'for')         AS ballots_for,
        COUNT(b.id) FILTER (WHERE b.value = 'against')     AS ballots_against,
        COUNT(b.id) FILTER (WHERE b.value = 'abstain')     AS ballots_abstain,
        COUNT(b.id) FILTER (WHERE b.value = 'nsp')         AS ballots_nsp,

        -- Comptage manuel
        mo.manual_total,
        mo.manual_for,
        mo.manual_against,
        mo.manual_abstain

    FROM motions mo
    LEFT JOIN ballots b ON b.motion_id = mo.id
    WHERE mo.meeting_id = ?
    GROUP BY
        mo.id,
        mo.title,
        mo.agenda_id,
        mo.manual_total,
        mo.manual_for,
        mo.manual_against,
        mo.manual_abstain
    ORDER BY mo.title
");
$stmt->execute([$meetingId]);
$motionRows = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Construire un tableau [agenda_id => [motions...]]
$motionsByAgenda = [];
foreach ($motionRows as $r) {
    $ballotsTotal = (int)($r['ballots_total'] ?? 0);

    if ($ballotsTotal > 0) {
        $source        = 'ballots';
        $total         = $ballotsTotal;
        $votes_for     = (int)($r['ballots_for']     ?? 0);
        $votes_against = (int)($r['ballots_against'] ?? 0);
        $votes_abstain = (int)($r['ballots_abstain'] ?? 0);
        $votes_nsp     = (int)($r['ballots_nsp']     ?? 0);
    } else {
        $source        = 'manual';
        $total         = (int)($r['manual_total']    ?? 0);
        $votes_for     = (int)($r['manual_for']      ?? 0);
        $votes_against = (int)($r['manual_against']  ?? 0);
        $votes_abstain = (int)($r['manual_abstain']  ?? 0);
        $votes_nsp     = max(0, $total - $votes_for - $votes_against - $votes_abstain);
    }

    $agendaId = $r['agenda_id'];

    if (!isset($motionsByAgenda[$agendaId])) {
        $motionsByAgenda[$agendaId] = [];
    }

    $motionsByAgenda[$agendaId][] = [
        'motion_id'      => $r['motion_id'],
        'title'          => $r['title'],
        'total'          => $total,
        'votes_for'      => $votes_for,
        'votes_against'  => $votes_against,
        'votes_abstain'  => $votes_abstain,
        'votes_nsp'      => $votes_nsp,
        'tally_source'   => $source,
    ];
}

// 4) Construire le HTML du rapport

function h($s) {
    return htmlspecialchars($s ?? '', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

$meetingTitle = h($meeting['title']);
$createdAt    = $meeting['created_at'] ? (new DateTime($meeting['created_at']))->format('d/m/Y H:i') : 'date inconnue';
$validatedBy  = $meeting['validated_by'] ?? null;
$validatedAt  = $meeting['validated_at'] ? (new DateTime($meeting['validated_at']))->format('d/m/Y H:i') : null;

$html = <<<HTML
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<title>Compte-rendu — {$meetingTitle}</title>
<style>
    body {
        font-family: DejaVu Sans, Arial, sans-serif;
        font-size: 12px;
        color: #111827;
        margin: 40px;
    }
    h1, h2, h3 {
        margin-top: 0;
    }
    h1 {
        font-size: 20px;
        margin-bottom: 6px;
    }
    h2 {
        font-size: 16px;
        margin-top: 18px;
        margin-bottom: 4px;
    }
    h3 {
        font-size: 14px;
        margin-top: 12px;
        margin-bottom: 4px;
    }
    p {
        margin: 4px 0;
        line-height: 1.4;
    }
    ol {
        margin: 0;
        padding-left: 18px;
    }
    ul {
        margin: 0;
        padding-left: 16px;
    }
    .muted {
        color: #6b7280;
    }
    .section {
        margin-top: 16px;
    }
    .line-space {
        margin-top: 8px;
    }
    .resolution {
        margin-top: 4px;
        margin-bottom: 4px;
    }
</style>
</head>
<body>
HTML;

// En-tête
$html .= "<h1>Compte-rendu de séance</h1>";
$html .= "<p>La séance intitulée <strong>« {$meetingTitle} »</strong> ";
$html .= "s'est tenue le <strong>{$createdAt}</strong>.</p>";

// Status de validation éventuelle
if ($validatedBy && $validatedAt) {
    $vb = h($validatedBy);
    $va = $validatedAt;
    $html .= "<p>Cette séance a été validée par <strong>{$vb}</strong> ";
    $html .= "le <strong>{$va}</strong>.</p>";
} else {
    $html .= "<p class=\"muted\">Cette séance n'a pas été formellement validée par un président.</p>";
}

// Section ODJ
$html .= "<div class=\"section\">";
$html .= "<h2>Ordre du jour</h2>";

if (!$agendas) {
    $html .= "<p>Aucun point à l'ordre du jour n'a été saisi.</p>";
} else {
    $html .= "<ol>";
    foreach ($agendas as $agenda) {
        $aTitle = h($agenda['title']);
        $aDesc  = h($agenda['description'] ?? '');
        $html  .= "<li>";
        $html  .= "<p><strong>{$aTitle}</strong>";
        if ($aDesc !== '') {
            $html .= " — {$aDesc}";
        }
        $html .= "</p>";

        // Résolutions de ce point
        $agendaId = $agenda['id'];
        $motions  = $motionsByAgenda[$agendaId] ?? [];

        if (!$motions) {
            $html .= "<p class=\"muted\">Aucune résolution n'a été associée à ce point.</p>";
        } else {
            $html .= "<h3>Résolutions</h3>";
            $html .= "<ul>";
            foreach ($motions as $mo) {
                $mTitle   = h($mo['title']);
                $total    = (int)$mo['total'];
                $for      = (int)$mo['votes_for'];
                $against  = (int)$mo['votes_against'];
                $abstain  = (int)$mo['votes_abstain'];
                $nsp      = (int)$mo['votes_nsp'];
                $source   = $mo['tally_source'] ?? 'ballots';

                $majority = $total > 0 ? intdiv($total, 2) + 1 : 0;
                $adopted  = ($for >= $majority && $majority > 0);

                $sourceLabel = $source === 'manual'
                    ? "<span class=\"muted\">(comptage manuel)</span>"
                    : "<span class=\"muted\">(bulletins individuels)</span>";

                $html .= "<li class=\"resolution\">";
                $html .= "La résolution <strong>« {$mTitle} »</strong> ";
                if ($total > 0) {
                    $html .= "a fait l'objet de <strong>{$total}</strong> votants : ";
                    $html .= "<strong>{$for}</strong> pour, ";
                    $html .= "<strong>{$against}</strong> contre, ";
                    $html .= "<strong>{$abstain}</strong> abstentions";
                    if ($nsp > 0) {
                        $html .= " et <strong>{$nsp}</strong> NSP (ne se prononce pas)";
                    }
                    $html .= ". ";

                    if ($majority > 0) {
                        $html .= "La majorité absolue était de <strong>{$majority}</strong> voix pour. ";
                        $html .= $adopted
                            ? "La résolution est donc <strong>adoptée</strong>."
                            : "La résolution est donc <strong>rejetée</strong>.";
                    }
                } else {
                    $html .= "n'a pas de résultats de vote enregistrés.";
                }

                $html .= " {$sourceLabel}";
                $html .= "</li>";
            }
            $html .= "</ul>";
        }

        $html .= "</li>";
    }
    $html .= "</ol>";
}
$html .= "</div>";

// Section Président (rappel)
$html .= "<div class=\"section\">";
$html .= "<h2>Président de séance</h2>";
if ($validatedBy) {
    $vb = h($validatedBy);
    $va = $validatedAt ?: 'date inconnue';
    $html .= "<p>Cette séance a été présidée et validée par <strong>{$vb}</strong> ";
    $html .= "le <strong>{$va}</strong>.</p>";
} else {
    $html .= "<p>Aucun nom de président validant n'a été enregistré pour cette séance.</p>";
}
$html .= "</div>";

$html .= "</body></html>";

// 5) Générer le PDF avec Dompdf

$dompdf = new Dompdf([
    'isRemoteEnabled' => false,
    'defaultFont'     => 'DejaVu Sans',
]);
$dompdf->loadHtml($html, 'UTF-8');
$dompdf->setPaper('A4', 'portrait');
$dompdf->render();

// Nom de fichier sympa
$filename = 'compte-rendu-' . preg_replace('/[^a-z0-9]+/i', '-', strtolower($meeting['title'])) . '.pdf';

$dompdf->stream($filename, [
    'Attachment' => true, // passe à false si tu veux l'ouvrir directement dans le navigateur
]);
