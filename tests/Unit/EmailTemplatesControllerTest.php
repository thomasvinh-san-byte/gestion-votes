<?php

declare(strict_types=1);

namespace Tests\Unit;

use AgVote\Controller\EmailTemplatesController;
use AgVote\Repository\EmailTemplateRepository;
use AgVote\Repository\MeetingRepository;
use AgVote\Repository\MeetingStatsRepository;
use AgVote\Repository\MemberRepository;

/**
 * Unit tests for EmailTemplatesController.
 *
 * Endpoints:
 *  - list():   GET  — list templates or get one by id
 *  - create(): POST — create or special actions (create_defaults, duplicate)
 *  - update(): PUT  — update a template
 *  - delete(): DELETE — delete a template
 *
 * Response structure:
 *   - api_ok($data)  => body['data'][...] (wrapped in 'data' key)
 *   - api_fail(...)  => body['error'] (direct)
 *
 * Note: EmailTemplateService is instantiated in each controller method and its
 * constructor calls RepositoryFactory for emailTemplate, meeting, member,
 * meetingStats repos. All 4 repos must be injected.
 *
 * Pattern: extends ControllerTestCase, uses injectRepos() + callController().
 */
class EmailTemplatesControllerTest extends ControllerTestCase
{
    private const TENANT  = 'tenant-uuid-001';
    private const TPL_ID  = 'aaaaaaaa-1111-2222-3333-000000000010';
    private const SRC_ID  = 'bbbbbbbb-1111-2222-3333-000000000010';
    private const USER_ID = 'user-uuid-0010';

    // =========================================================================
    // HELPER — inject all repos required by EmailTemplateService constructor
    // =========================================================================

    /**
     * EmailTemplateService.__construct() calls factory for all 4 repos.
     * Always inject all of them to avoid RuntimeException from null PDO.
     */
    private function injectEmailRepos(
        EmailTemplateRepository $tplRepo,
        ?MeetingRepository $meetingRepo = null,
        ?MemberRepository $memberRepo = null,
        ?MeetingStatsRepository $statsRepo = null,
    ): void {
        $this->injectRepos([
            EmailTemplateRepository::class => $tplRepo,
            MeetingRepository::class       => $meetingRepo ?? $this->createMock(MeetingRepository::class),
            MemberRepository::class        => $memberRepo  ?? $this->createMock(MemberRepository::class),
            MeetingStatsRepository::class  => $statsRepo   ?? $this->createMock(MeetingStatsRepository::class),
        ]);
    }

    // =========================================================================
    // CONTROLLER STRUCTURE
    // =========================================================================

    public function testControllerIsFinal(): void
    {
        $ref = new \ReflectionClass(EmailTemplatesController::class);
        $this->assertTrue($ref->isFinal());
    }

    public function testControllerHasRequiredMethods(): void
    {
        foreach (['list', 'create', 'update', 'delete'] as $method) {
            $this->assertTrue(method_exists(EmailTemplatesController::class, $method));
        }
    }

    // =========================================================================
    // list() — GET
    // =========================================================================

    public function testListReturnsAllTemplates(): void
    {
        $this->setAuth(self::USER_ID, 'operator', self::TENANT);
        $this->setHttpMethod('GET');
        $this->setQueryParams([]);

        $repo = $this->createMock(EmailTemplateRepository::class);
        $repo->method('listForTenant')->willReturn([
            ['id' => self::TPL_ID, 'name' => 'Invitation standard'],
        ]);

        $this->injectEmailRepos($repo);

        $res = $this->callController(EmailTemplatesController::class, 'list');

        $this->assertSame(200, $res['status']);
        $this->assertCount(1, $res['body']['data']['items']);
        $this->assertSame(self::TPL_ID, $res['body']['data']['items'][0]['id']);
    }

    public function testListFiltersByType(): void
    {
        $this->setAuth(self::USER_ID, 'operator', self::TENANT);
        $this->setHttpMethod('GET');
        $this->setQueryParams(['type' => 'reminder']);

        $repo = $this->createMock(EmailTemplateRepository::class);
        $repo->expects($this->once())
            ->method('listForTenant')
            ->with(self::TENANT, 'reminder')
            ->willReturn([]);

        $this->injectEmailRepos($repo);

        $res = $this->callController(EmailTemplatesController::class, 'list');

        $this->assertSame(200, $res['status']);
        $this->assertSame([], $res['body']['data']['items']);
    }

    public function testListByIdInvalidUuidReturns400(): void
    {
        $this->setAuth(self::USER_ID, 'operator', self::TENANT);
        $this->setHttpMethod('GET');
        $this->setQueryParams(['id' => 'not-a-uuid']);

        $repo = $this->createMock(EmailTemplateRepository::class);
        $this->injectEmailRepos($repo);

        $res = $this->callController(EmailTemplatesController::class, 'list');

        $this->assertSame(400, $res['status']);
        $this->assertSame('invalid_template_id', $res['body']['error']);
    }

    public function testListByIdNotFoundReturns404(): void
    {
        $this->setAuth(self::USER_ID, 'operator', self::TENANT);
        $this->setHttpMethod('GET');
        $this->setQueryParams(['id' => self::TPL_ID]);

        $repo = $this->createMock(EmailTemplateRepository::class);
        $repo->method('findById')->willReturn(null);

        $this->injectEmailRepos($repo);

        $res = $this->callController(EmailTemplatesController::class, 'list');

        $this->assertSame(404, $res['status']);
        $this->assertSame('template_not_found', $res['body']['error']);
    }

    public function testListByIdReturnsTemplate(): void
    {
        $this->setAuth(self::USER_ID, 'operator', self::TENANT);
        $this->setHttpMethod('GET');
        $this->setQueryParams(['id' => self::TPL_ID]);

        $tplData = ['id' => self::TPL_ID, 'name' => 'Invitation', 'template_type' => 'invitation'];

        $repo = $this->createMock(EmailTemplateRepository::class);
        $repo->method('findById')->willReturn($tplData);

        $this->injectEmailRepos($repo);

        $res = $this->callController(EmailTemplatesController::class, 'list');

        $this->assertSame(200, $res['status']);
        $this->assertSame(self::TPL_ID, $res['body']['data']['template']['id']);
    }

    // =========================================================================
    // create() — POST
    // =========================================================================

    public function testCreateRequiresPost(): void
    {
        $this->setAuth(self::USER_ID, 'operator', self::TENANT);
        $this->setHttpMethod('GET');

        $repo = $this->createMock(EmailTemplateRepository::class);
        $this->injectEmailRepos($repo);

        $res = $this->callController(EmailTemplatesController::class, 'create');

        $this->assertSame(405, $res['status']);
    }

    public function testCreateMissingNameReturns400(): void
    {
        $this->setAuth(self::USER_ID, 'operator', self::TENANT);
        $this->setHttpMethod('POST');
        $this->injectJsonBody([
            'subject'   => 'Subj',
            'body_html' => '<p>Hello</p>',
        ]);

        $repo = $this->createMock(EmailTemplateRepository::class);
        $this->injectEmailRepos($repo);

        $res = $this->callController(EmailTemplatesController::class, 'create');

        $this->assertSame(400, $res['status']);
        $this->assertSame('missing_name', $res['body']['error']);
    }

    public function testCreateMissingSubjectReturns400(): void
    {
        $this->setAuth(self::USER_ID, 'operator', self::TENANT);
        $this->setHttpMethod('POST');
        $this->injectJsonBody([
            'name'      => 'My Template',
            'body_html' => '<p>Hello</p>',
        ]);

        $repo = $this->createMock(EmailTemplateRepository::class);
        $this->injectEmailRepos($repo);

        $res = $this->callController(EmailTemplatesController::class, 'create');

        $this->assertSame(400, $res['status']);
        $this->assertSame('missing_subject', $res['body']['error']);
    }

    public function testCreateMissingBodyHtmlReturns400(): void
    {
        $this->setAuth(self::USER_ID, 'operator', self::TENANT);
        $this->setHttpMethod('POST');
        $this->injectJsonBody([
            'name'    => 'My Template',
            'subject' => 'Hello',
        ]);

        $repo = $this->createMock(EmailTemplateRepository::class);
        $this->injectEmailRepos($repo);

        $res = $this->callController(EmailTemplatesController::class, 'create');

        $this->assertSame(400, $res['status']);
        $this->assertSame('missing_body_html', $res['body']['error']);
    }

    public function testCreateInvalidTypeReturns400(): void
    {
        $this->setAuth(self::USER_ID, 'operator', self::TENANT);
        $this->setHttpMethod('POST');
        $this->injectJsonBody([
            'name'          => 'My Template',
            'subject'       => 'Hello',
            'body_html'     => '<p>Body</p>',
            'template_type' => 'invalid_type',
        ]);

        $repo = $this->createMock(EmailTemplateRepository::class);
        $this->injectEmailRepos($repo);

        $res = $this->callController(EmailTemplatesController::class, 'create');

        $this->assertSame(400, $res['status']);
        $this->assertSame('invalid_template_type', $res['body']['error']);
    }

    public function testCreateNameExistsReturns400(): void
    {
        $this->setAuth(self::USER_ID, 'operator', self::TENANT);
        $this->setHttpMethod('POST');
        $this->injectJsonBody([
            'name'          => 'My Template',
            'subject'       => 'Hello',
            'body_html'     => '<p>Body</p>',
            'template_type' => 'invitation',
        ]);

        $repo = $this->createMock(EmailTemplateRepository::class);
        $repo->method('nameExists')->willReturn(true);

        $this->injectEmailRepos($repo);

        $res = $this->callController(EmailTemplatesController::class, 'create');

        $this->assertSame(400, $res['status']);
        $this->assertSame('template_name_exists', $res['body']['error']);
    }

    public function testCreateSucceeds(): void
    {
        $this->setAuth(self::USER_ID, 'operator', self::TENANT);
        $this->setHttpMethod('POST');
        $this->injectJsonBody([
            'name'          => 'My Template',
            'subject'       => 'Hello',
            'body_html'     => '<p>Body</p>',
            'template_type' => 'invitation',
        ]);

        $tplData = ['id' => self::TPL_ID, 'name' => 'My Template'];

        $repo = $this->createMock(EmailTemplateRepository::class);
        $repo->method('nameExists')->willReturn(false);
        $repo->method('create')->willReturn($tplData);

        $this->injectEmailRepos($repo);

        $res = $this->callController(EmailTemplatesController::class, 'create');

        $this->assertSame(201, $res['status']);
        $this->assertSame(self::TPL_ID, $res['body']['data']['template']['id']);
    }

    public function testCreateDuplicateActionInvalidSourceIdReturns400(): void
    {
        $this->setAuth(self::USER_ID, 'operator', self::TENANT);
        $this->setHttpMethod('POST');
        $this->injectJsonBody([
            'action'    => 'duplicate',
            'source_id' => 'not-a-uuid',
            'new_name'  => 'Copy',
        ]);

        $repo = $this->createMock(EmailTemplateRepository::class);
        $this->injectEmailRepos($repo);

        $res = $this->callController(EmailTemplatesController::class, 'create');

        $this->assertSame(400, $res['status']);
        $this->assertSame('invalid_source_id', $res['body']['error']);
    }

    public function testCreateDuplicateActionMissingNewNameReturns400(): void
    {
        $this->setAuth(self::USER_ID, 'operator', self::TENANT);
        $this->setHttpMethod('POST');
        $this->injectJsonBody([
            'action'    => 'duplicate',
            'source_id' => self::SRC_ID,
            'new_name'  => '',
        ]);

        $repo = $this->createMock(EmailTemplateRepository::class);
        $this->injectEmailRepos($repo);

        $res = $this->callController(EmailTemplatesController::class, 'create');

        $this->assertSame(400, $res['status']);
        $this->assertSame('missing_new_name', $res['body']['error']);
    }

    public function testCreateDuplicateActionSucceeds(): void
    {
        $this->setAuth(self::USER_ID, 'operator', self::TENANT);
        $this->setHttpMethod('POST');
        $this->injectJsonBody([
            'action'    => 'duplicate',
            'source_id' => self::SRC_ID,
            'new_name'  => 'Copy of Template',
        ]);

        $repo = $this->createMock(EmailTemplateRepository::class);
        $repo->method('duplicate')->willReturn([
            'id'   => self::TPL_ID,
            'name' => 'Copy of Template',
        ]);

        $this->injectEmailRepos($repo);

        $res = $this->callController(EmailTemplatesController::class, 'create');

        $this->assertSame(200, $res['status']);
        $this->assertSame(self::TPL_ID, $res['body']['data']['template']['id']);
    }

    // =========================================================================
    // update() — PUT
    // Note: update() validates id/findById BEFORE calling api_request('PUT').
    // So method enforcement (405) only triggers after those checks pass.
    // =========================================================================

    public function testUpdateRequiresPut(): void
    {
        // update() checks id validity and findById BEFORE api_request('PUT').
        // To reach the 405 check, id must be valid and template must exist.
        $this->setAuth(self::USER_ID, 'operator', self::TENANT);
        $this->setHttpMethod('GET');
        $this->setQueryParams(['id' => self::TPL_ID]);

        $existing = [
            'id'        => self::TPL_ID,
            'name'      => 'Old Name',
            'subject'   => 'Hello',
            'body_html' => '<p>Body</p>',
            'body_text' => null,
        ];

        $repo = $this->createMock(EmailTemplateRepository::class);
        $repo->method('findById')->willReturn($existing);
        $this->injectEmailRepos($repo);

        $res = $this->callController(EmailTemplatesController::class, 'update');

        $this->assertSame(405, $res['status']);
    }

    public function testUpdateInvalidIdReturns400(): void
    {
        $this->setAuth(self::USER_ID, 'operator', self::TENANT);
        $this->setHttpMethod('PUT');
        $this->setQueryParams(['id' => 'bad-id']);

        $repo = $this->createMock(EmailTemplateRepository::class);
        $this->injectEmailRepos($repo);

        $res = $this->callController(EmailTemplatesController::class, 'update');

        $this->assertSame(400, $res['status']);
        $this->assertSame('invalid_template_id', $res['body']['error']);
    }

    public function testUpdateNotFoundReturns404(): void
    {
        $this->setAuth(self::USER_ID, 'operator', self::TENANT);
        $this->setHttpMethod('PUT');
        $this->setQueryParams(['id' => self::TPL_ID]);
        $this->injectJsonBody(['name' => 'Updated']);

        $repo = $this->createMock(EmailTemplateRepository::class);
        $repo->method('findById')->willReturn(null);

        $this->injectEmailRepos($repo);

        $res = $this->callController(EmailTemplatesController::class, 'update');

        $this->assertSame(404, $res['status']);
        $this->assertSame('template_not_found', $res['body']['error']);
    }

    public function testUpdateSucceeds(): void
    {
        $this->setAuth(self::USER_ID, 'operator', self::TENANT);
        $this->setHttpMethod('PUT');
        $this->setQueryParams(['id' => self::TPL_ID]);
        $this->injectJsonBody(['name' => 'Updated Name']);

        $existing = [
            'id'        => self::TPL_ID,
            'name'      => 'Old Name',
            'subject'   => 'Hello',
            'body_html' => '<p>Body</p>',
            'body_text' => null,
        ];

        $repo = $this->createMock(EmailTemplateRepository::class);
        $repo->method('findById')->willReturn($existing);
        $repo->method('nameExists')->willReturn(false);
        $repo->method('update')->willReturn([
            'id' => self::TPL_ID, 'name' => 'Updated Name',
        ]);

        $this->injectEmailRepos($repo);

        $res = $this->callController(EmailTemplatesController::class, 'update');

        $this->assertSame(200, $res['status']);
        $this->assertSame(self::TPL_ID, $res['body']['data']['template']['id']);
    }

    // =========================================================================
    // delete() — DELETE
    // =========================================================================

    public function testDeleteInvalidIdReturns400(): void
    {
        $this->setAuth(self::USER_ID, 'operator', self::TENANT);
        $this->setHttpMethod('DELETE');
        $this->setQueryParams(['id' => 'not-a-uuid']);

        $repo = $this->createMock(EmailTemplateRepository::class);
        $this->injectEmailRepos($repo);

        $res = $this->callController(EmailTemplatesController::class, 'delete');

        $this->assertSame(400, $res['status']);
        $this->assertSame('invalid_template_id', $res['body']['error']);
    }

    public function testDeleteNotFoundReturns404(): void
    {
        $this->setAuth(self::USER_ID, 'operator', self::TENANT);
        $this->setHttpMethod('DELETE');
        $this->setQueryParams(['id' => self::TPL_ID]);

        $repo = $this->createMock(EmailTemplateRepository::class);
        $repo->method('findById')->willReturn(null);

        $this->injectEmailRepos($repo);

        $res = $this->callController(EmailTemplatesController::class, 'delete');

        $this->assertSame(404, $res['status']);
        $this->assertSame('template_not_found', $res['body']['error']);
    }

    public function testDeleteDefaultTemplateReturns400(): void
    {
        $this->setAuth(self::USER_ID, 'operator', self::TENANT);
        $this->setHttpMethod('DELETE');
        $this->setQueryParams(['id' => self::TPL_ID]);

        $repo = $this->createMock(EmailTemplateRepository::class);
        $repo->method('findById')->willReturn([
            'id' => self::TPL_ID, 'name' => 'Default', 'is_default' => true,
        ]);

        $this->injectEmailRepos($repo);

        $res = $this->callController(EmailTemplatesController::class, 'delete');

        $this->assertSame(400, $res['status']);
        $this->assertSame('cannot_delete_default', $res['body']['error']);
    }

    public function testDeleteSucceeds(): void
    {
        $this->setAuth(self::USER_ID, 'operator', self::TENANT);
        $this->setHttpMethod('DELETE');
        $this->setQueryParams(['id' => self::TPL_ID]);

        $repo = $this->createMock(EmailTemplateRepository::class);
        $repo->method('findById')->willReturn([
            'id' => self::TPL_ID, 'name' => 'My Template', 'is_default' => false,
        ]);
        $repo->method('delete')->willReturn(true);

        $this->injectEmailRepos($repo);

        $res = $this->callController(EmailTemplatesController::class, 'delete');

        $this->assertSame(200, $res['status']);
        $this->assertTrue($res['body']['data']['deleted']);
    }
}
