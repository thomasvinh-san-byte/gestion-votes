<?php

declare(strict_types=1);

namespace AgVote\Controller;

use AgVote\Core\Providers\RepositoryFactory;
use AgVote\View\HtmlView;
use RuntimeException;
use Throwable;

/**
 * Token-authenticated public voting interface.
 *
 * This controller does NOT extend AbstractController because it outputs
 * HTML (via HtmlView), not JSON (via api_ok/api_fail).
 *
 * Accessed at: /vote.php?token={token}
 */
final class VotePublicController {
    private const VOTE_MAP = [
        'pour' => 'for',
        'contre' => 'against',
        'abstention' => 'abstain',
        'blanc' => 'nsp',
    ];

    private const VOTE_LABELS = [
        'pour' => 'Pour',
        'contre' => 'Contre',
        'abstention' => 'Abstention',
        'blanc' => 'Blanc',
    ];

    public function vote(): void {
        try {
            $this->doVote();
        } catch (\PDOException $e) {
            error_log('VotePublicController::vote [DB]: ' . $e->getMessage() . "\n" . $e->getTraceAsString());
            HtmlView::text('Erreur interne du serveur. Veuillez réessayer.', 500);
        } catch (Throwable $e) {
            error_log('VotePublicController::vote: ' . $e->getMessage() . "\n" . $e->getTraceAsString());
            HtmlView::text('Erreur interne du serveur. Veuillez réessayer.', 500);
        }
    }

    private function doVote(): void {
        // ── Validate token ──────────────────────────────────────────────
        $token = api_query('token');
        if ($token === '') {
            HtmlView::text('Token manquant', 400);
        }

        $hash = hash_hmac('sha256', (string) $token, APP_SECRET);

        $tokenRepo = RepositoryFactory::getInstance()->voteToken();
        $row = $tokenRepo->findValidByHash($hash);

        if (!$row) {
            HtmlView::render('vote_token_expired', [], 403);
        }

        // ── Verify motion/meeting state ─────────────────────────────────
        $motionRepo = RepositoryFactory::getInstance()->motion();
        $ctx = $motionRepo->findWithBallotContext($row['motion_id'], (string) $row['tenant_id']);

        if (!$ctx) {
            HtmlView::text('Motion introuvable', 404);
        }

        if (!empty($ctx['meeting_validated_at'])) {
            HtmlView::text('meeting_validated', 409);
        }

        if (empty($ctx['motion_opened_at']) || !empty($ctx['motion_closed_at'])) {
            HtmlView::text('motion_not_open', 409);
        }

        // ── GET: show vote form ─────────────────────────────────────────
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            HtmlView::render('vote_form');
            return;
        }

        // ── POST: process vote ──────────────────────────────────────────
        $postData = api_request('POST');
        $vote = $postData['vote'] ?? null;
        $confirm = ($postData['confirm'] ?? '0') === '1';

        if (!is_string($vote) || !isset(self::VOTE_MAP[$vote])) {
            HtmlView::text('Vote invalide', 400);
        }

        // Show confirmation page
        if (!$confirm) {
            HtmlView::render('vote_confirm', [
                'vote' => $vote,
                'chosen' => self::VOTE_LABELS[$vote],
            ]);
            return;
        }

        // ── Confirmed: atomic vote ──────────────────────────────────────
        $dbVote = self::VOTE_MAP[$vote];

        $memberRepo = RepositoryFactory::getInstance()->member();
        $member = $memberRepo->findByIdForTenant($row['member_id'], $ctx['tenant_id']);
        $weight = (float) ($member['voting_power'] ?? 1.0);
        if (!is_finite($weight) || $weight < 0.0) {
            HtmlView::text('Poids de vote invalide (doit être un nombre fini >= 0)', 422);
        }
        if ($weight > 1e6) {
            HtmlView::text('Poids de vote invalide', 422);
        }

        try {
            api_transaction(function () use ($tokenRepo, $hash, $row, $ctx, $dbVote, $weight) {
                // Lock the motion row before ballot insert to prevent TOCTOU race:
                // a concurrent motion close arriving between the pre-transaction guard
                // and the INSERT would let a ballot land on a closed motion.
                $motionRepo = RepositoryFactory::getInstance()->motion();
                $lockedMotion = $motionRepo->findByIdForTenantForUpdate($row['motion_id'], $ctx['tenant_id']);
                if (!$lockedMotion || !empty($lockedMotion['closed_at'])) {
                    throw new RuntimeException('motion_closed_concurrent');
                }

                $consumed = $tokenRepo->consumeIfValid($hash, $ctx['tenant_id']);
                if (!$consumed) {
                    throw new RuntimeException('token_already_used');
                }

                $ballotRepo = RepositoryFactory::getInstance()->ballot();
                $ballotRepo->insertFromToken(
                    $ctx['tenant_id'],
                    $row['meeting_id'],
                    $row['motion_id'],
                    $row['member_id'],
                    $dbVote,
                    $weight,
                    'tablet',
                );
            });
        } catch (RuntimeException $e) {
            if ($e->getMessage() === 'token_already_used') {
                HtmlView::text('Token déjà utilisé ou expiré', 409);
            }
            if ($e->getMessage() === 'motion_closed_concurrent') {
                HtmlView::text('Ce vote est clos', 409);
            }
            throw $e;
        }

        HtmlView::text('Vote enregistré');
    }
}
