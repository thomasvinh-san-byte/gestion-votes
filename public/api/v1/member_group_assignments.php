<?php
declare(strict_types=1);
require __DIR__ . '/../../../app/api.php';
$c = new \AgVote\Controller\MemberGroupsController();
$m = api_method();
if ($m === 'POST') {
    $in = api_request('POST');
    if (isset($in['member_ids'])) {
        $c->handle('bulkAssign');
    } else {
        $c->handle('assign');
    }
} elseif ($m === 'PUT') {
    $c->handle('setMemberGroups');
} elseif ($m === 'DELETE') {
    $c->handle('unassign');
} else {
    api_fail('method_not_allowed', 405);
}
