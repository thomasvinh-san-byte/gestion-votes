<?php

declare(strict_types=1);

// Permet d'utiliser bootstrap.php en CLI (scripts) sans warning
if (PHP_SAPI === 'cli' && !isset($_SERVER['REQUEST_METHOD'])) {
    $_SERVER['REQUEST_METHOD'] = 'CLI';
}

/**
 * Script de démo e-vote :
 * - crée (ou réutilise) un tenant "demo-evote"
 * - crée 3 membres
 * - crée 1 quorum policy + 1 vote policy
 * - crée une séance "live" + 1 motion ouverte
 * - enregistre quelques bulletins
 * - affiche le résultat calculé par VoteEngine
 *
 * À lancer depuis la racine du projet :
 *   php scripts/seed_demo_evote.php
 */

require __DIR__ . '/../app/bootstrap.php';

use AgVote\Service\BallotsService;
use AgVote\Service\VoteEngine;

// ── Local PDO helpers (replaces deprecated db_select_one / db_scalar / db_execute) ──

function seed_select_one(string $sql, array $params = []): ?array {
    $st = db()->prepare($sql);
    $st->execute($params);
    $row = $st->fetch();
    return $row === false ? null : $row;
}

function seed_scalar(string $sql, array $params = []): mixed {
    $st = db()->prepare($sql);
    $st->execute($params);
    return $st->fetchColumn();
}

function seed_execute(string $sql, array $params = []): int {
    $st = db()->prepare($sql);
    $st->execute($params);
    return $st->rowCount();
}

// ── Seed functions ──

/**
 * Retourne un UUID de tenant pour le slug donné, en le créant si besoin.
 */
function seedTenant(string $slug, string $name): string {
    $row = seed_select_one(
        'SELECT id FROM tenants WHERE slug = :slug',
        [':slug' => $slug],
    );

    if ($row && !empty($row['id'])) {
        echo "✔ Tenant déjà présent : {$name} ({$row['id']})\n";
        return (string) $row['id'];
    }

    $id = seed_scalar(
        "INSERT INTO tenants (id, name, slug, timezone)
         VALUES (gen_random_uuid(), :name, :slug, 'Europe/Paris')
         RETURNING id",
        [
            ':name' => $name,
            ':slug' => $slug,
        ],
    );

    echo "✔ Tenant créé : {$name} ({$id})\n";
    return (string) $id;
}

/**
 * Crée ou récupère un membre (unique par tenant + full_name).
 */
function seedMember(string $tenantId, string $fullName, string $email, float $power): string {
    $row = seed_select_one(
        'SELECT id FROM members WHERE tenant_id = :tenant_id AND full_name = :full_name',
        [
            ':tenant_id' => $tenantId,
            ':full_name' => $fullName,
        ],
    );

    if ($row && !empty($row['id'])) {
        echo "  • Membre déjà présent : {$fullName} ({$row['id']})\n";
        return (string) $row['id'];
    }

    $id = seed_scalar(
        'INSERT INTO members (id, tenant_id, full_name, email, voting_power, is_active)
         VALUES (gen_random_uuid(), :tenant_id, :full_name, :email, :power, true)
         RETURNING id',
        [
            ':tenant_id' => $tenantId,
            ':full_name' => $fullName,
            ':email' => $email,
            ':power' => $power,
        ],
    );

    echo "  • Membre créé : {$fullName} ({$id}), pouvoir = {$power}\n";
    return (string) $id;
}

/**
 * Crée ou récupère une quorum policy (unique par tenant + name).
 */
function seedQuorumPolicy(string $tenantId): string {
    $name = 'Quorum 50 % membres (démo)';

    $row = seed_select_one(
        'SELECT id FROM quorum_policies WHERE tenant_id = :tenant_id AND name = :name',
        [
            ':tenant_id' => $tenantId,
            ':name' => $name,
        ],
    );

    if ($row && !empty($row['id'])) {
        echo "✔ Quorum policy déjà présente : {$name} ({$row['id']})\n";
        return (string) $row['id'];
    }

    $id = seed_scalar(
        "INSERT INTO quorum_policies (
            id, tenant_id, name, description,
            denominator, include_proxies, count_remote, threshold
         )
         VALUES (
            gen_random_uuid(),
            :tenant_id,
            :name,
            :description,
            'eligible_members',
            true,
            true,
            0.5000
         )
         RETURNING id",
        [
            ':tenant_id' => $tenantId,
            ':name' => $name,
            ':description' => 'Quorum atteint si au moins 50 % des membres éligibles participent au vote.',
        ],
    );

    echo "✔ Quorum policy créée : {$name} ({$id})\n";
    return (string) $id;
}

/**
 * Crée ou récupère une vote policy (majorité simple sur exprimés).
 */
function seedVotePolicy(string $tenantId): string {
    $name = 'Majorité simple (1/2 exprimés – démo)';

    $row = seed_select_one(
        'SELECT id FROM vote_policies WHERE tenant_id = :tenant_id AND name = :name',
        [
            ':tenant_id' => $tenantId,
            ':name' => $name,
        ],
    );

    if ($row && !empty($row['id'])) {
        echo "✔ Vote policy déjà présente : {$name} ({$row['id']})\n";
        return (string) $row['id'];
    }

    $id = seed_scalar(
        "INSERT INTO vote_policies (
            id, tenant_id, name, description,
            base, threshold, abstention_as_against
         )
         VALUES (
            gen_random_uuid(),
            :tenant_id,
            :name,
            :description,
            'expressed',
            0.50000,
            false
         )
         RETURNING id",
        [
            ':tenant_id' => $tenantId,
            ':name' => $name,
            ':description' => 'Résolution adoptée si les voix « pour » représentent au moins 50 % des voix exprimées.',
        ],
    );

    echo "✔ Vote policy créée : {$name} ({$id})\n";
    return (string) $id;
}

/**
 * Crée ou récupère une séance de démo "live".
 */
function seedMeeting(string $tenantId, string $title, string $quorumPolicyId): string {
    $row = seed_select_one(
        'SELECT id FROM meetings WHERE tenant_id = :tenant_id AND title = :title',
        [
            ':tenant_id' => $tenantId,
            ':title' => $title,
        ],
    );

    if ($row && !empty($row['id'])) {
        echo "✔ Séance déjà présente : {$title} ({$row['id']})\n";
        return (string) $row['id'];
    }

    $id = seed_scalar(
        "INSERT INTO meetings (
            id, tenant_id, title, description,
            status, scheduled_at, started_at, location,
            quorum_policy_id
         )
         VALUES (
            gen_random_uuid(),
            :tenant_id,
            :title,
            :description,
            'live',
            now(),
            now(),
            :location,
            :quorum_policy_id
         )
         RETURNING id",
        [
            ':tenant_id' => $tenantId,
            ':title' => $title,
            ':description' => 'Séance de démonstration du module de vote électronique.',
            ':location' => 'Salle du conseil',
            ':quorum_policy_id' => $quorumPolicyId,
        ],
    );

    echo "✔ Séance créée : {$title} ({$id})\n";
    return (string) $id;
}

/**
 * Crée ou récupère un point d'ODJ pour la séance.
 */
function seedAgenda(string $meetingId): string {
    $row = seed_select_one(
        'SELECT id FROM agendas WHERE meeting_id = :meeting_id AND idx = 1',
        [':meeting_id' => $meetingId],
    );

    if ($row && !empty($row['id'])) {
        echo "✔ Point d'ODJ déjà présent (idx=1) ({$row['id']})\n";
        return (string) $row['id'];
    }

    $id = seed_scalar(
        'INSERT INTO agendas (id, meeting_id, idx, title, description)
         VALUES (
            gen_random_uuid(),
            :meeting_id,
            1,
            :title,
            :description
         )
         RETURNING id',
        [
            ':meeting_id' => $meetingId,
            ':title' => '1. Adoption du budget 2025',
            ':description' => 'Point de démo pour le vote électronique.',
        ],
    );

    echo "✔ Point d'ODJ créé (idx=1) ({$id})\n";
    return (string) $id;
}

/**
 * Crée ou récupère une motion de démo ouverte.
 */
function seedMotion(string $meetingId, string $agendaId, string $votePolicyId): string {
    $title = 'Adoption du budget 2025';

    $row = seed_select_one(
        'SELECT id FROM motions WHERE meeting_id = :meeting_id AND title = :title',
        [
            ':meeting_id' => $meetingId,
            ':title' => $title,
        ],
    );

    if ($row && !empty($row['id'])) {
        echo "✔ Motion déjà présente : {$title} ({$row['id']})\n";

        // S'assurer qu'elle est ouverte et qu'elle utilise la bonne policy
        seed_execute(
            'UPDATE motions
             SET opened_at = COALESCE(opened_at, now()),
                 closed_at = NULL,
                 vote_policy_id = :vp_id
             WHERE id = :id',
            [
                ':id' => $row['id'],
                ':vp_id' => $votePolicyId,
            ],
        );

        return (string) $row['id'];
    }

    $id = seed_scalar(
        'INSERT INTO motions (
            id, meeting_id, agenda_id,
            title, description,
            secret, vote_policy_id,
            opened_at
         )
         VALUES (
            gen_random_uuid(),
            :meeting_id,
            :agenda_id,
            :title,
            :description,
            false,
            :vote_policy_id,
            now()
         )
         RETURNING id',
        [
            ':meeting_id' => $meetingId,
            ':agenda_id' => $agendaId,
            ':title' => $title,
            ':description' => 'Vote de démo sur le budget 2025.',
            ':vote_policy_id' => $votePolicyId,
        ],
    );

    echo "✔ Motion créée : {$title} ({$id})\n";
    return (string) $id;
}

/**
 * Met à jour la séance pour pointer sur la motion courante.
 */
function linkCurrentMotionToMeeting(string $meetingId, string $motionId): void {
    seed_execute(
        'UPDATE meetings
         SET current_motion_id = :motion_id
         WHERE id = :meeting_id',
        [
            ':motion_id' => $motionId,
            ':meeting_id' => $meetingId,
        ],
    );

    echo "✔ current_motion_id mis à jour sur la séance\n";
}

/**
 * Enregistre quelques bulletins de démo.
 *
 * @param string $motionId
 * @param string[] $members
 */
function seedBallots(string $motionId, array $members): void {
    echo "✔ Enregistrement de bulletins de démo…\n";

    // On force quelques votes : 2 pour, 1 contre
    $patterns = [
        ['member' => $members[0], 'value' => 'for'],
        ['member' => $members[1], 'value' => 'for'],
        ['member' => $members[2], 'value' => 'against'],
    ];

    foreach ($patterns as $p) {
        $data = [
            'motion_id' => $motionId,
            'member_id' => $p['member'],
            'value' => $p['value'],
        ];

        try {
            $ballot = (new BallotsService())->castBallot($data);
            echo "  • Vote enregistré : motion {$ballot['motion_id']} / membre {$ballot['member_id']} = {$ballot['value']}\n";
        } catch (Throwable $e) {
            echo '  ! Erreur enregistrement vote : ' . $e->getMessage() . "\n";
        }
    }
}

/**
 * Affiche le résultat calculé par VoteEngine.
 */
function showComputedResult(string $motionId): void {
    echo "\n=== Résultat calculé par VoteEngine ===\n";
    try {
        $result = (new VoteEngine())->computeMotionResult($motionId);
        print_r($result);
    } catch (Throwable $e) {
        echo 'Erreur computeMotionResult: ' . $e->getMessage() . "\n";
    }
}

/* -------------------------------------------------------------------------- */

echo "=== Script de peuplement e-vote (démo) ===\n\n";

// 1) Tenant
$tenantId = seedTenant('demo-evote', 'Collectivité démo e-vote');

// 2) Membres
echo "✔ Création / récupération des membres…\n";
$member1 = seedMember($tenantId, 'Alice Martin', 'alice.martin@example.test', 1.0);
$member2 = seedMember($tenantId, 'Bruno Dupont', 'bruno.dupont@example.test', 1.0);
$member3 = seedMember($tenantId, 'Chloé Bernard', 'chloe.bernard@example.test', 1.0);
$members = [$member1, $member2, $member3];

// 3) Policies
$quorumPolicyId = seedQuorumPolicy($tenantId);
$votePolicyId = seedVotePolicy($tenantId);

// 4) Séance + ODJ + motion
$meetingId = seedMeeting($tenantId, 'Séance de démo e-vote', $quorumPolicyId);
$agendaId = seedAgenda($meetingId);
$motionId = seedMotion($meetingId, $agendaId, $votePolicyId);
linkCurrentMotionToMeeting($meetingId, $motionId);

// 5) Bulletins
seedBallots($motionId, $members);

// 6) Résultat
showComputedResult($motionId);

echo "\n=== Terminé ===\n";
