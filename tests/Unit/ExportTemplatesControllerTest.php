<?php

declare(strict_types=1);

namespace Tests\Unit;

use AgVote\Controller\ExportTemplatesController;
use AgVote\Repository\ExportTemplateRepository;

/**
 * Unit tests for ExportTemplatesController.
 *
 * Endpoints:
 *  - list():   GET  — list templates, get one by id, or list available columns
 *  - create(): POST — create template or duplicate action
 *  - update(): PUT  — update a template
 *  - delete(): DELETE — delete a template
 *
 * Response structure:
 *   - api_ok($data)  => body['data'][...] (wrapped in 'data' key)
 *   - api_fail(...)  => body['error'] (direct)
 *
 * Pattern: extends ControllerTestCase, uses injectRepos() + callController().
 */
class ExportTemplatesControllerTest extends ControllerTestCase
{
    private const TENANT  = 'tenant-uuid-001';
    private const TPL_ID  = 'aaaaaaaa-1111-2222-3333-000000000050';
    private const SRC_ID  = 'bbbbbbbb-1111-2222-3333-000000000050';
    private const USER_ID = 'user-uuid-0050';

    // =========================================================================
    // CONTROLLER STRUCTURE
    // =========================================================================

    public function testControllerIsFinal(): void
    {
        $ref = new \ReflectionClass(ExportTemplatesController::class);
        $this->assertTrue($ref->isFinal());
    }

    public function testControllerHasRequiredMethods(): void
    {
        foreach (['list', 'create', 'update', 'delete'] as $method) {
            $this->assertTrue(method_exists(ExportTemplatesController::class, $method));
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

        $repo = $this->createMock(ExportTemplateRepository::class);
        $repo->method('listForTenant')->willReturn([
            ['id' => self::TPL_ID, 'name' => 'Export membres'],
        ]);

        $this->injectRepos([ExportTemplateRepository::class => $repo]);

        $res = $this->callController(ExportTemplatesController::class, 'list');

        $this->assertSame(200, $res['status']);
        $this->assertCount(1, $res['body']['data']['items']);
        $this->assertSame(self::TPL_ID, $res['body']['data']['items'][0]['id']);
    }

    public function testListAvailableColumnsReturnsTypesAndColumns(): void
    {
        $this->setAuth(self::USER_ID, 'operator', self::TENANT);
        $this->setHttpMethod('GET');
        $this->setQueryParams(['available_columns' => '1']);

        $repo = $this->createMock(ExportTemplateRepository::class);
        $repo->method('getAvailableColumns')->willReturn(['id', 'full_name']);

        $this->injectRepos([ExportTemplateRepository::class => $repo]);

        $res = $this->callController(ExportTemplatesController::class, 'list');

        $this->assertSame(200, $res['status']);
        $data = $res['body']['data'];
        $this->assertArrayHasKey('types', $data);
        $this->assertArrayHasKey('columns_by_type', $data);
    }

    public function testListByIdInvalidUuidReturns400(): void
    {
        $this->setAuth(self::USER_ID, 'operator', self::TENANT);
        $this->setHttpMethod('GET');
        $this->setQueryParams(['id' => 'not-a-uuid']);

        $repo = $this->createMock(ExportTemplateRepository::class);
        $this->injectRepos([ExportTemplateRepository::class => $repo]);

        $res = $this->callController(ExportTemplatesController::class, 'list');

        $this->assertSame(400, $res['status']);
        $this->assertSame('invalid_template_id', $res['body']['error']);
    }

    public function testListByIdNotFoundReturns404(): void
    {
        $this->setAuth(self::USER_ID, 'operator', self::TENANT);
        $this->setHttpMethod('GET');
        $this->setQueryParams(['id' => self::TPL_ID]);

        $repo = $this->createMock(ExportTemplateRepository::class);
        $repo->method('findById')->willReturn(null);

        $this->injectRepos([ExportTemplateRepository::class => $repo]);

        $res = $this->callController(ExportTemplatesController::class, 'list');

        $this->assertSame(404, $res['status']);
        $this->assertSame('template_not_found', $res['body']['error']);
    }

    public function testListByIdReturnsTemplate(): void
    {
        $this->setAuth(self::USER_ID, 'operator', self::TENANT);
        $this->setHttpMethod('GET');
        $this->setQueryParams(['id' => self::TPL_ID]);

        $tplData = [
            'id' => self::TPL_ID,
            'name' => 'Export membres',
            'export_type' => 'members',
        ];

        $repo = $this->createMock(ExportTemplateRepository::class);
        $repo->method('findById')->willReturn($tplData);

        $this->injectRepos([ExportTemplateRepository::class => $repo]);

        $res = $this->callController(ExportTemplatesController::class, 'list');

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

        $repo = $this->createMock(ExportTemplateRepository::class);
        $this->injectRepos([ExportTemplateRepository::class => $repo]);

        $res = $this->callController(ExportTemplatesController::class, 'create');

        $this->assertSame(405, $res['status']);
    }

    public function testCreateInvalidNameReturns422(): void
    {
        $this->setAuth(self::USER_ID, 'operator', self::TENANT);
        $this->setHttpMethod('POST');
        $this->injectJsonBody([
            'name'        => 'X',  // too short — less than 2 chars
            'export_type' => 'members',
        ]);

        $repo = $this->createMock(ExportTemplateRepository::class);
        $this->injectRepos([ExportTemplateRepository::class => $repo]);

        $res = $this->callController(ExportTemplatesController::class, 'create');

        $this->assertSame(422, $res['status']);
        $this->assertSame('invalid_name', $res['body']['error']);
    }

    public function testCreateInvalidTypeReturns422(): void
    {
        $this->setAuth(self::USER_ID, 'operator', self::TENANT);
        $this->setHttpMethod('POST');
        $this->injectJsonBody([
            'name'        => 'My Export',
            'export_type' => 'invalid_type',
        ]);

        $repo = $this->createMock(ExportTemplateRepository::class);
        $this->injectRepos([ExportTemplateRepository::class => $repo]);

        $res = $this->callController(ExportTemplatesController::class, 'create');

        $this->assertSame(422, $res['status']);
        $this->assertSame('invalid_export_type', $res['body']['error']);
    }

    public function testCreateNameExistsReturns409(): void
    {
        $this->setAuth(self::USER_ID, 'operator', self::TENANT);
        $this->setHttpMethod('POST');
        $this->injectJsonBody([
            'name'        => 'My Export',
            'export_type' => 'members',
        ]);

        $repo = $this->createMock(ExportTemplateRepository::class);
        $repo->method('nameExists')->willReturn(true);
        $repo->method('getDefaultColumns')->willReturn(['id', 'full_name']);

        $this->injectRepos([ExportTemplateRepository::class => $repo]);

        $res = $this->callController(ExportTemplatesController::class, 'create');

        $this->assertSame(409, $res['status']);
        $this->assertSame('name_already_exists', $res['body']['error']);
    }

    public function testCreateSucceeds(): void
    {
        $this->setAuth(self::USER_ID, 'operator', self::TENANT);
        $this->setHttpMethod('POST');
        $this->injectJsonBody([
            'name'        => 'My Export',
            'export_type' => 'members',
            'columns'     => ['id', 'full_name'],
        ]);

        $tplData = ['id' => self::TPL_ID, 'name' => 'My Export'];

        $repo = $this->createMock(ExportTemplateRepository::class);
        $repo->method('nameExists')->willReturn(false);
        $repo->method('create')->willReturn($tplData);

        $this->injectRepos([ExportTemplateRepository::class => $repo]);

        $res = $this->callController(ExportTemplatesController::class, 'create');

        $this->assertSame(201, $res['status']);
        $this->assertSame(self::TPL_ID, $res['body']['data']['template']['id']);
    }

    public function testCreateDuplicateInvalidSourceIdReturns400(): void
    {
        $this->setAuth(self::USER_ID, 'operator', self::TENANT);
        $this->setHttpMethod('POST');
        $this->injectJsonBody([
            'action'    => 'duplicate',
            'source_id' => 'not-a-uuid',
            'new_name'  => 'Copy',
        ]);

        $repo = $this->createMock(ExportTemplateRepository::class);
        $this->injectRepos([ExportTemplateRepository::class => $repo]);

        $res = $this->callController(ExportTemplatesController::class, 'create');

        $this->assertSame(400, $res['status']);
        $this->assertSame('invalid_source_id', $res['body']['error']);
    }

    public function testCreateDuplicateInvalidNameReturns422(): void
    {
        $this->setAuth(self::USER_ID, 'operator', self::TENANT);
        $this->setHttpMethod('POST');
        $this->injectJsonBody([
            'action'    => 'duplicate',
            'source_id' => self::SRC_ID,
            'new_name'  => '',
        ]);

        $repo = $this->createMock(ExportTemplateRepository::class);
        $this->injectRepos([ExportTemplateRepository::class => $repo]);

        $res = $this->callController(ExportTemplatesController::class, 'create');

        $this->assertSame(422, $res['status']);
        $this->assertSame('invalid_name', $res['body']['error']);
    }

    public function testCreateDuplicateSucceeds(): void
    {
        $this->setAuth(self::USER_ID, 'operator', self::TENANT);
        $this->setHttpMethod('POST');
        $this->injectJsonBody([
            'action'    => 'duplicate',
            'source_id' => self::SRC_ID,
            'new_name'  => 'Copy of Export',
        ]);

        $repo = $this->createMock(ExportTemplateRepository::class);
        $repo->method('duplicate')->willReturn([
            'id'   => self::TPL_ID,
            'name' => 'Copy of Export',
        ]);

        $this->injectRepos([ExportTemplateRepository::class => $repo]);

        $res = $this->callController(ExportTemplatesController::class, 'create');

        $this->assertSame(201, $res['status']);
        $this->assertSame(self::TPL_ID, $res['body']['data']['template']['id']);
    }

    // =========================================================================
    // update() — PUT
    // Note: update() checks id/findById BEFORE api_request('PUT').
    // =========================================================================

    public function testUpdateInvalidIdReturns400(): void
    {
        $this->setAuth(self::USER_ID, 'operator', self::TENANT);
        $this->setHttpMethod('PUT');
        $this->setQueryParams(['id' => 'bad-id']);

        $repo = $this->createMock(ExportTemplateRepository::class);
        $this->injectRepos([ExportTemplateRepository::class => $repo]);

        $res = $this->callController(ExportTemplatesController::class, 'update');

        $this->assertSame(400, $res['status']);
        $this->assertSame('invalid_template_id', $res['body']['error']);
    }

    public function testUpdateNotFoundReturns404(): void
    {
        $this->setAuth(self::USER_ID, 'operator', self::TENANT);
        $this->setHttpMethod('PUT');
        $this->setQueryParams(['id' => self::TPL_ID]);
        $this->injectJsonBody(['name' => 'Updated']);

        $repo = $this->createMock(ExportTemplateRepository::class);
        $repo->method('findById')->willReturn(null);

        $this->injectRepos([ExportTemplateRepository::class => $repo]);

        $res = $this->callController(ExportTemplatesController::class, 'update');

        $this->assertSame(404, $res['status']);
        $this->assertSame('template_not_found', $res['body']['error']);
    }

    public function testUpdateRequiresPut(): void
    {
        // update() checks id/findById before api_request('PUT').
        // Need valid id + existing template to reach 405 check.
        $this->setAuth(self::USER_ID, 'operator', self::TENANT);
        $this->setHttpMethod('GET');
        $this->setQueryParams(['id' => self::TPL_ID]);

        $existing = [
            'id'          => self::TPL_ID,
            'name'        => 'Export membres',
            'export_type' => 'members',
            'columns'     => ['id'],
        ];

        $repo = $this->createMock(ExportTemplateRepository::class);
        $repo->method('findById')->willReturn($existing);
        $this->injectRepos([ExportTemplateRepository::class => $repo]);

        $res = $this->callController(ExportTemplatesController::class, 'update');

        $this->assertSame(405, $res['status']);
    }

    public function testUpdateSucceeds(): void
    {
        $this->setAuth(self::USER_ID, 'operator', self::TENANT);
        $this->setHttpMethod('PUT');
        $this->setQueryParams(['id' => self::TPL_ID]);
        $this->injectJsonBody(['name' => 'Updated Export']);

        $existing = [
            'id'          => self::TPL_ID,
            'name'        => 'Old Export',
            'export_type' => 'members',
            'columns'     => ['id', 'full_name'],
        ];

        $repo = $this->createMock(ExportTemplateRepository::class);
        $repo->method('findById')->willReturn($existing);
        $repo->method('nameExists')->willReturn(false);
        $repo->method('update')->willReturn([
            'id' => self::TPL_ID, 'name' => 'Updated Export',
        ]);

        $this->injectRepos([ExportTemplateRepository::class => $repo]);

        $res = $this->callController(ExportTemplatesController::class, 'update');

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

        $repo = $this->createMock(ExportTemplateRepository::class);
        $this->injectRepos([ExportTemplateRepository::class => $repo]);

        $res = $this->callController(ExportTemplatesController::class, 'delete');

        $this->assertSame(400, $res['status']);
        $this->assertSame('invalid_template_id', $res['body']['error']);
    }

    public function testDeleteNotFoundReturns404(): void
    {
        $this->setAuth(self::USER_ID, 'operator', self::TENANT);
        $this->setHttpMethod('DELETE');
        $this->setQueryParams(['id' => self::TPL_ID]);

        $repo = $this->createMock(ExportTemplateRepository::class);
        $repo->method('findById')->willReturn(null);

        $this->injectRepos([ExportTemplateRepository::class => $repo]);

        $res = $this->callController(ExportTemplatesController::class, 'delete');

        $this->assertSame(404, $res['status']);
        $this->assertSame('template_not_found', $res['body']['error']);
    }

    public function testDeleteSucceeds(): void
    {
        $this->setAuth(self::USER_ID, 'operator', self::TENANT);
        $this->setHttpMethod('DELETE');
        $this->setQueryParams(['id' => self::TPL_ID]);

        $repo = $this->createMock(ExportTemplateRepository::class);
        $repo->method('findById')->willReturn([
            'id' => self::TPL_ID, 'name' => 'My Export', 'export_type' => 'members',
        ]);
        $repo->method('delete')->willReturn(true);

        $this->injectRepos([ExportTemplateRepository::class => $repo]);

        $res = $this->callController(ExportTemplatesController::class, 'delete');

        $this->assertSame(200, $res['status']);
        $this->assertTrue($res['body']['data']['deleted']);
    }
}
