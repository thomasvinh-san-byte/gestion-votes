<?php
// public/api/v1/dev_seed_members.php
// Dev endpoint: seed demo members for testing
declare(strict_types=1);

require __DIR__ . '/../../../app/api.php';

try {
    api_require_role('operator');
    $in = api_request('POST');
    $count = max(1, min(100, (int)($in['count'] ?? 10)));

    $firstNames = ['Jean', 'Marie', 'Pierre', 'Sophie', 'Michel', 'Isabelle', 'François', 'Catherine', 'André', 'Nathalie',
        'Philippe', 'Monique', 'Claude', 'Anne', 'Bernard', 'Christine', 'Alain', 'Dominique', 'Patrick', 'Sylvie',
        'Daniel', 'Martine', 'Jacques', 'Jacqueline', 'René', 'Françoise', 'Thierry', 'Nicole', 'Marc', 'Brigitte'];
    $lastNames = ['Martin', 'Bernard', 'Thomas', 'Petit', 'Robert', 'Richard', 'Durand', 'Dubois', 'Moreau', 'Laurent',
        'Simon', 'Michel', 'Lefebvre', 'Leroy', 'Roux', 'David', 'Bertrand', 'Morel', 'Fournier', 'Girard',
        'Bonnet', 'Dupont', 'Lambert', 'Fontaine', 'Rousseau', 'Vincent', 'Muller', 'Lefevre', 'Faure', 'Andre'];

    $created = 0;
    for ($i = 0; $i < $count; $i++) {
        $first = $firstNames[array_rand($firstNames)];
        $last = $lastNames[array_rand($lastNames)];
        $fullName = $first . ' ' . $last;
        $id = api_uuid4();

        try {
            db_execute(
                "INSERT INTO members (id, tenant_id, full_name, is_active, vote_weight, created_at, updated_at)
                 VALUES (:id, :tid, :name, true, 1, now(), now())
                 ON CONFLICT DO NOTHING",
                [':id' => $id, ':tid' => DEFAULT_TENANT_ID, ':name' => $fullName]
            );
            $created++;
        } catch (Throwable $e) {
            // skip duplicates
        }
    }

    api_ok(['created' => $created, 'requested' => $count]);
} catch (InvalidArgumentException $e) {
    api_fail('invalid_request', 422, ['detail' => $e->getMessage()]);
} catch (Throwable $e) {
    error_log('Error in dev_seed_members.php: ' . $e->getMessage());
    api_fail('internal_error', 500, ['detail' => 'Erreur interne']);
}
