<?php
declare(strict_types=1);

require __DIR__ . '/../../app/api.php';

function badge_class(string $kind): string {
  switch ($kind) {
    case 'success': return 'badge success';
    case 'info': return 'badge info';
    case 'warning': return 'badge warning';
    case 'danger': return 'badge danger';
    default: return 'badge neutral';
  }
}

function h(?string $s): string { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
function get_meeting_id(): string {
  $id = (string)($_GET['meeting_id'] ?? '');
  return $id;
}
