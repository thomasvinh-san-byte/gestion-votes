<?php
// Fragment HTML : badge quorum + justification (polling HTMX)
// Params: meeting_id=... OR motion_id=...
require __DIR__ . '/../../../app/api.php';

use AgVote\Service\QuorumEngine;

api_require_role('public');

$meetingId = trim((string)($_GET['meeting_id'] ?? ''));
$motionId  = trim((string)($_GET['motion_id'] ?? ''));

header('Content-Type: text/html; charset=utf-8');

try {
    // Use tenant context if available for security
    $tenantId = api_current_tenant_id();

    if ($motionId !== '') {
        $r = QuorumEngine::computeForMotion($motionId);
        $title = $r['applies_to']['motion_title'] ?? 'Motion';
        $scope = 'Motion';
    } elseif ($meetingId !== '') {
        $r = QuorumEngine::computeForMeeting($meetingId, $tenantId);
        $title = null;
        $scope = 'Séance';
    } else {
        echo '<section class="card"><div class="muted">Quorum: meeting_id ou motion_id requis.</div></section>';
        exit;
    }

    $applied = $r['applied'] ?? false;
    $met = $r['met'] ?? null;
    $just = (string)($r['justification'] ?? '');

    if (!$applied) {
        echo '<section class="card"><div class="row between"><div><div class="k">Quorum</div><div class="muted tiny">Aucune politique appliquée.</div></div><span class="badge muted">—</span></div></section>';
        exit;
    }

    $badgeClass = 'muted';
    $badgeText  = '—';
    if ($met === true) { $badgeClass = 'success'; $badgeText = 'atteint'; }
    if ($met === false){ $badgeClass = 'danger';  $badgeText = 'non atteint'; }

    $safeJust = htmlspecialchars($just, ENT_QUOTES, 'UTF-8');
    $safeScope = htmlspecialchars($scope, ENT_QUOTES, 'UTF-8');
    $safeTitle = $title ? htmlspecialchars((string)$title, ENT_QUOTES, 'UTF-8') : '';

    echo '<section class="card">';
    echo '  <div class="row between">';
    echo '    <div>';
    echo '      <div class="k">Quorum <span class="muted tiny">(' . $safeScope . ')</span></div>';
    if ($safeTitle !== '') {
        echo '      <div class="muted tiny"><strong>' . $safeTitle . '</strong></div>';
    }
    echo '      <div class="muted tiny">' . $safeJust . '</div>';
    echo '    </div>';
    echo '    <span class="badge ' . $badgeClass . '">' . $badgeText . '</span>';
    echo '  </div>';
    echo '</section>';

} catch (Throwable $e) {
    error_log("quorum_card error: " . $e->getMessage());
    $safe = htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8');
    echo '<section class="card"><div class="row between"><div><div class="k">Quorum</div><div class="muted tiny">' . $safe . '</div></div><span class="badge danger">erreur</span></div></section>';
}
