<?php

declare(strict_types=1);

namespace AgVote\Controller;

use AgVote\Repository\BallotRepository;
use AgVote\Repository\MemberRepository;
use AgVote\Repository\MotionRepository;
use AgVote\Repository\VoteTokenRepository;
use AgVote\View\HtmlView;
use RuntimeException;

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
        // ── Validate token ──────────────────────────────────────────────
        $token = api_query('token');
        if ($token === '') {
            HtmlView::text('Token manquant', 400);
        }

        $hash = hash_hmac('sha256', (string) $token, APP_SECRET);

        $tokenRepo = new VoteTokenRepository();
        $row = $tokenRepo->findValidByHash($hash);

        if (!$row) {
            HtmlView::text('Token invalide ou expiré', 403);
        }

        // ── Verify motion/meeting state ─────────────────────────────────
        $motionRepo = new MotionRepository();
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
                'chosen' => self::VOTE_LABELS[$vote] ?? $vote,
            ]);
            return;
        }

        // ── Confirmed: atomic vote ──────────────────────────────────────
        $dbVote = self::VOTE_MAP[$vote];

        $memberRepo = new MemberRepository();
        $member = $memberRepo->findByIdForTenant($row['member_id'], $ctx['tenant_id']);
        $weight = (float) ($member['voting_power'] ?? 1.0);
        if ($weight < 0) {
            $weight = 0.0;
        }

        try {
            api_transaction(function () use ($tokenRepo, $hash, $row, $ctx, $dbVote, $weight) {
                $consumed = $tokenRepo->consume($hash, (string) $row['tenant_id']);
                if ($consumed === 0) {
                    throw new RuntimeException('token_already_used');
                }

                $ballotRepo = new BallotRepository();
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
                HtmlView::text('Token déjà utilisé', 409);
            }
            throw $e;
        }

        HtmlView::text('Vote enregistré');
    }
}
