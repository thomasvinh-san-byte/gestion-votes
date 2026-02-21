<?php
declare(strict_types=1);

namespace AgVote\Controller;

use AgVote\Repository\MemberRepository;
use AgVote\Repository\AttendanceRepository;

/**
 * Dev-only endpoints for seeding test data.
 */
final class DevSeedController extends AbstractController
{
    private function guardProduction(): void
    {
        $env = config('env', 'dev');
        if (in_array($env, ['production', 'prod'], true)) {
            api_fail('endpoint_disabled', 403, [
                'detail' => 'Cet endpoint de développement est désactivé en production.',
            ]);
        }
    }

    public function seedMembers(): void
    {
        $this->guardProduction();

        $in = api_request('POST');
        $count = max(1, min(100, (int)($in['count'] ?? 10)));

        $firstNames = ['Jean', 'Marie', 'Pierre', 'Sophie', 'Michel', 'Isabelle', 'François', 'Catherine', 'André', 'Nathalie',
            'Philippe', 'Monique', 'Claude', 'Anne', 'Bernard', 'Christine', 'Alain', 'Dominique', 'Patrick', 'Sylvie',
            'Daniel', 'Martine', 'Jacques', 'Jacqueline', 'René', 'Françoise', 'Thierry', 'Nicole', 'Marc', 'Brigitte'];
        $lastNames = ['Martin', 'Bernard', 'Thomas', 'Petit', 'Robert', 'Richard', 'Durand', 'Dubois', 'Moreau', 'Laurent',
            'Simon', 'Michel', 'Lefebvre', 'Leroy', 'Roux', 'David', 'Bertrand', 'Morel', 'Fournier', 'Girard',
            'Bonnet', 'Dupont', 'Lambert', 'Fontaine', 'Rousseau', 'Vincent', 'Muller', 'Lefevre', 'Faure', 'Andre'];

        $repo = new MemberRepository();
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
            } catch (\AgVote\Core\Http\ApiResponseException $__apiResp) { throw $__apiResp;
        } catch (\Throwable $e) {
                // skip duplicates
            }
        }

        api_ok(['created' => $created, 'requested' => $count]);
    }

    public function seedAttendances(): void
    {
        $this->guardProduction();

        $in = api_request('POST');
        $meetingId = trim((string)($in['meeting_id'] ?? ''));
        if ($meetingId === '') {
            throw new \InvalidArgumentException('meeting_id requis');
        }

        $presentRatio = (float)($in['present_ratio'] ?? 0.7);

        $memberRepo     = new MemberRepository();
        $attendanceRepo = new AttendanceRepository();

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
            } catch (\AgVote\Core\Http\ApiResponseException $__apiResp) { throw $__apiResp;
        } catch (\Throwable $e) {
                // skip errors
            }
        }

        api_ok(['created' => $created, 'total_members' => count($members)]);
    }
}
