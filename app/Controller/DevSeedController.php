<?php

declare(strict_types=1);

namespace AgVote\Controller;

use AgVote\Repository\UserRepository;
use InvalidArgumentException;
use PDOException;
use Throwable;

/**
 * Dev-only endpoints for seeding test data.
 */
final class DevSeedController extends AbstractController {
    private function guardProduction(): void {
        $env = config('env', 'dev');
        if (in_array($env, ['production', 'prod'], true)) {
            api_fail('endpoint_disabled', 403, [
                'detail' => 'Cet endpoint de développement est désactivé en production.',
            ]);
        }
    }

    public function seedMembers(): void {
        $this->guardProduction();

        $in = api_request('POST');
        $count = max(1, min(100, (int) ($in['count'] ?? 10)));

        $firstNames = ['Jean', 'Marie', 'Pierre', 'Sophie', 'Michel', 'Isabelle', 'François', 'Catherine', 'André', 'Nathalie',
            'Philippe', 'Monique', 'Claude', 'Anne', 'Bernard', 'Christine', 'Alain', 'Dominique', 'Patrick', 'Sylvie',
            'Daniel', 'Martine', 'Jacques', 'Jacqueline', 'René', 'Françoise', 'Thierry', 'Nicole', 'Marc', 'Brigitte'];
        $lastNames = ['Martin', 'Bernard', 'Thomas', 'Petit', 'Robert', 'Richard', 'Durand', 'Dubois', 'Moreau', 'Laurent',
            'Simon', 'Michel', 'Lefebvre', 'Leroy', 'Roux', 'David', 'Bertrand', 'Morel', 'Fournier', 'Girard',
            'Bonnet', 'Dupont', 'Lambert', 'Fontaine', 'Rousseau', 'Vincent', 'Muller', 'Lefevre', 'Faure', 'Andre'];

        $repo = $this->repo()->member();
        $created = 0;
        for ($i = 0; $i < $count; $i++) {
            $first = $firstNames[array_rand($firstNames)];
            $last = $lastNames[array_rand($lastNames)];
            $fullName = $first . ' ' . $last;
            $id = api_uuid4();

            try {
                if ($repo->insertSeedMember($id, api_current_tenant_id(), $fullName)) {
                    $created++;
                }
            } catch (Throwable) {
                // skip duplicates
            }
        }

        api_ok(['created' => $created, 'requested' => $count]);
    }

    public function seedAttendances(): void {
        $this->guardProduction();

        $in = api_request('POST');
        $meetingId = trim((string) ($in['meeting_id'] ?? ''));
        if ($meetingId === '') {
            throw new InvalidArgumentException('meeting_id requis');
        }

        $presentRatio = (float) ($in['present_ratio'] ?? 0.7);

        $memberRepo = $this->repo()->member();
        $attendanceRepo = $this->repo()->attendance();

        $members = $memberRepo->listActiveIds(api_current_tenant_id());

        if (empty($members)) {
            api_fail('no_members', 400, ['detail' => 'Aucun membre actif trouvé. Créez des membres d\'abord.']);
        }

        $created = 0;
        foreach ($members as $m) {
            $rand = mt_rand(1, 100) / 100.0;
            if ($rand <= $presentRatio) {
                $mode = (mt_rand(1, 10) <= 8) ? 'present' : 'remote';
            } else {
                $mode = 'absent';
            }

            if ($mode === 'absent') {
                continue;
            }

            $id = api_uuid4();
            try {
                $attendanceRepo->upsertSeed($id, api_current_tenant_id(), $meetingId, $m['id'], $mode);
                $created++;
            } catch (Throwable) {
                // skip errors
            }
        }

        api_ok(['created' => $created, 'total_members' => count($members)]);
    }

    /**
     * Cree un utilisateur de test avec role systeme et role de seance optionnel.
     * Usage: POST /api/v1/test/seed-user
     * Double garde : route-level (env gate) + controller-level (guardProduction).
     */
    public function seedUser(): void {
        $this->guardProduction();

        $in = api_request('POST');
        $email = trim((string) ($in['email'] ?? ''));
        $password = trim((string) ($in['password'] ?? ''));
        $name = trim((string) ($in['name'] ?? 'Test User'));
        $systemRole = trim((string) ($in['system_role'] ?? 'viewer'));
        $meetingId = trim((string) ($in['meeting_id'] ?? ''));
        $meetingRole = trim((string) ($in['meeting_role'] ?? ''));

        if ($email === '' || $password === '') {
            throw new InvalidArgumentException('email and password required');
        }

        $tenantId = api_current_tenant_id();
        $userId = api_uuid4();
        $hash = password_hash($password, PASSWORD_BCRYPT);

        /** @var UserRepository $userRepo */
        $userRepo = $this->repo()->user();

        try {
            $userRepo->createUser($userId, $tenantId, $email, $name, $systemRole, $hash);
        } catch (PDOException $e) {
            if ($e->getCode() === '23505') {
                // Utilisateur existant — recuperer l'ID
                $existing = $userRepo->findByEmail($tenantId, $email);
                if ($existing) {
                    $userId = $existing['id'];
                }
            } else {
                throw $e;
            }
        }

        if ($meetingId !== '' && $meetingRole !== '') {
            $userRepo->assignMeetingRole($tenantId, $meetingId, $userId, $meetingRole, $userId);
        }

        api_ok(['user_id' => $userId, 'email' => $email]);
    }
}
