<?php
// public/api/v1/votes/unanimity.php
declare(strict_types=1);

require __DIR__ . '/../../../../app/api.php';

// Ces endpoints existaient comme wrappers vers d’anciens scripts.
// Pour éviter les 500 (require manquant) et garder une API stable,
// on expose une réponse minimale.
//
// NOTE: si l’UI/UX commence à s’appuyer dessus, on pourra implémenter
// une logique complète (compteurs, confirmations, unanimité) via BallotsService.

api_require_role('operator');
$data = api_request($_SERVER['REQUEST_METHOD'] ?? 'GET');

api_ok([
  'endpoint' => 'votes/unanimity',
  'not_implemented' => true,
  'received' => $data,
]);
