<?php
/**
 * proxies_upsert.php - Creer ou mettre a jour une procuration
 *
 * POST /api/v1/proxies_upsert.php
 *
 * Body:
 *   - meeting_id (UUID, requis): ID de la seance
 *   - giver_member_id (UUID, requis): ID du mandant
 *   - receiver_member_id (UUID ou vide): ID du mandataire, vide pour revoquer
 *   - scope (string, optionnel): "full" par defaut
 *
 * Regles de validation:
 *   - Le mandataire ne peut pas etre le mandant (auto-delegation interdite)
 *   - Pas de chaines de procurations : si B a deja donne procuration,
 *     on ne peut pas donner a B (erreur "Chaine de procuration interdite")
 *   - Plafond : un mandataire ne peut pas recevoir plus de PROXY_MAX_PER_RECEIVER
 *     procurations (defaut: 99)
 *
 * Reponses:
 *   - 200 OK: { "ok": true, "meeting_id": "...", "proxy": {...} }
 *   - 200 OK (revocation): { "ok": true, "meeting_id": "...", "revoked": true }
 *   - 400: Erreur validation (chaine, plafond, tenant...)
 */
require __DIR__ . '/../../../app/api.php';

use AgVote\Service\ProxiesService;

api_require_role('operator');

$in = api_request('POST');

$meetingId = api_require_uuid($in, 'meeting_id');
api_guard_meeting_not_validated($meetingId);

$giverId   = api_require_uuid($in, 'giver_member_id');

$receiverRaw = trim((string)($in['receiver_member_id'] ?? ''));
$scope       = trim((string)($in['scope'] ?? 'full'));

try {
    // MVP: receiver_member_id vide => révoque
    if ($receiverRaw === '') {
        ProxiesService::revoke($meetingId, $giverId);

        if (function_exists('audit_log')) {
            audit_log('proxy_revoked', 'meeting', $meetingId, [
                'giver_member_id' => $giverId,
            ]);
        }

        api_ok([
            'ok'         => true,
            'meeting_id' => $meetingId,
            'giver_member_id' => $giverId,
            'revoked'    => true,
        ]);
    }

    if (!api_is_uuid($receiverRaw)) {
        api_fail('invalid_receiver_member_id', 400, ['detail' => 'receiver_member_id doit être un UUID ou vide (pour révoquer).']);
    }

    ProxiesService::upsert($meetingId, $giverId, $receiverRaw);

    if (function_exists('audit_log')) {
        audit_log('proxy_upsert', 'meeting', $meetingId, [
            'giver_member_id'    => $giverId,
            'receiver_member_id' => $receiverRaw,
            'scope'              => $scope ?: 'full',
        ]);
    }

    api_ok([
        'ok'                 => true,
        'meeting_id'         => $meetingId,
        'giver_member_id'    => $giverId,
        'receiver_member_id' => $receiverRaw,
        'scope'              => $scope ?: 'full',
    ]);
} catch (Throwable $e) {
    api_fail('proxy_upsert_failed', 400, ['detail' => $e->getMessage()]);
}