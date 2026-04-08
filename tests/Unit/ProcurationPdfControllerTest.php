<?php

declare(strict_types=1);

namespace Tests\Unit;

use AgVote\Controller\ProcurationPdfController;
use AgVote\Repository\MeetingRepository;
use AgVote\Repository\ProxyRepository;
use AgVote\Repository\SettingsRepository;

/**
 * Unit tests for ProcurationPdfController.
 *
 * Tests validation and error paths for the procuration PDF download endpoint.
 * Uses ControllerTestCase pattern with mocked repositories.
 */
class ProcurationPdfControllerTest extends ControllerTestCase
{
    // =========================================================================
    // HELPER
    // =========================================================================

    private function call(array $params = []): array
    {
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_GET = $params;
        return $this->callController(ProcurationPdfController::class, 'download');
    }

    private string $validUuid = 'a1b2c3d4-e5f6-7890-abcd-ef1234567890';
    private string $validUuid2 = 'b2c3d4e5-f6a7-8901-bcde-f01234567891';

    // =========================================================================
    // CONTROLLER STRUCTURE TESTS
    // =========================================================================

    public function testControllerIsFinal(): void
    {
        $ref = new \ReflectionClass(ProcurationPdfController::class);
        $this->assertTrue($ref->isFinal(), 'ProcurationPdfController should be final');
    }

    public function testControllerExtendsAbstractController(): void
    {
        $controller = new ProcurationPdfController();
        $this->assertInstanceOf(\AgVote\Controller\AbstractController::class, $controller);
    }

    public function testControllerHasDownloadMethod(): void
    {
        $ref = new \ReflectionClass(ProcurationPdfController::class);
        $this->assertTrue($ref->hasMethod('download'), 'ProcurationPdfController should have a download method');
        $this->assertTrue($ref->getMethod('download')->isPublic(), 'download() should be public');
    }

    // =========================================================================
    // MISSING PARAMS VALIDATION
    // =========================================================================

    public function testMissingProxyIdReturns400(): void
    {
        $result = $this->call([]);
        $this->assertEquals(400, $result['status']);
        $this->assertEquals('missing_proxy_id', $result['body']['error']);
    }

    public function testEmptyProxyIdReturns400(): void
    {
        $result = $this->call(['proxy_id' => '']);
        $this->assertEquals(400, $result['status']);
        $this->assertEquals('missing_proxy_id', $result['body']['error']);
    }

    public function testMissingMeetingIdReturns400(): void
    {
        $result = $this->call(['proxy_id' => $this->validUuid]);
        $this->assertEquals(400, $result['status']);
        $this->assertEquals('missing_meeting_id', $result['body']['error']);
    }

    public function testEmptyMeetingIdReturns400(): void
    {
        $result = $this->call(['proxy_id' => $this->validUuid, 'meeting_id' => '']);
        $this->assertEquals(400, $result['status']);
        $this->assertEquals('missing_meeting_id', $result['body']['error']);
    }

    // =========================================================================
    // UUID FORMAT VALIDATION
    // =========================================================================

    public function testInvalidProxyIdUuidReturns400(): void
    {
        $result = $this->call(['proxy_id' => 'not-a-uuid', 'meeting_id' => $this->validUuid]);
        $this->assertEquals(400, $result['status']);
        $this->assertEquals('invalid_proxy_id', $result['body']['error']);
    }

    public function testInvalidMeetingIdUuidReturns400(): void
    {
        $result = $this->call(['proxy_id' => $this->validUuid, 'meeting_id' => 'not-a-uuid']);
        $this->assertEquals(400, $result['status']);
        $this->assertEquals('invalid_meeting_id', $result['body']['error']);
    }

    // =========================================================================
    // REPO NOT FOUND / TENANT MISMATCH
    // =========================================================================

    public function testMeetingNotFoundReturns404(): void
    {
        $mockMeeting = $this->createMock(MeetingRepository::class);
        $mockMeeting->method('findByIdForTenant')->willReturn(null);

        $this->injectRepos([MeetingRepository::class => $mockMeeting]);

        $result = $this->call(['proxy_id' => $this->validUuid, 'meeting_id' => $this->validUuid2]);
        $this->assertEquals(404, $result['status']);
        $this->assertEquals('meeting_not_found', $result['body']['error']);
    }

    public function testProxyNotFoundReturns404(): void
    {
        // Use the default tenant ID that api_current_tenant_id() returns in tests
        $tenantId = 'aaaaaaaa-1111-2222-3333-444444444444';

        $mockMeeting = $this->createMock(MeetingRepository::class);
        $mockMeeting->method('findByIdForTenant')->willReturn([
            'id' => $this->validUuid2,
            'title' => 'AG Test',
            'scheduled_at' => '2026-06-15 10:00:00',
            'tenant_id' => $tenantId,
            'status' => 'open',
        ]);

        $mockProxy = $this->createMock(ProxyRepository::class);
        $mockProxy->method('findWithNames')->willReturn(null);

        $mockSettings = $this->createMock(SettingsRepository::class);
        $mockSettings->method('get')->willReturn('Association Test');

        $this->injectRepos([
            MeetingRepository::class => $mockMeeting,
            ProxyRepository::class => $mockProxy,
            SettingsRepository::class => $mockSettings,
        ]);

        $result = $this->call(['proxy_id' => $this->validUuid, 'meeting_id' => $this->validUuid2]);
        $this->assertEquals(404, $result['status']);
        $this->assertEquals('proxy_not_found', $result['body']['error']);
    }

    // =========================================================================
    // HAPPY PATH — PDF byte stream
    // =========================================================================

    /**
     * Integration test: download() must emit a real PDF byte stream.
     *
     * Mocks repos via RepositoryFactory injection. Calls download() directly
     * inside an output buffer because the method echoes the PDF without calling
     * api_ok() (it streams binary data directly to stdout).
     */
    public function testDownloadHappyPathEmitsPdfBytes(): void
    {
        // The default test tenant ID matches what api_current_tenant_id() returns
        $tenantId = 'aaaaaaaa-1111-2222-3333-444444444444';

        $mockMeeting = $this->createMock(MeetingRepository::class);
        $mockMeeting->method('findByIdForTenant')->willReturn([
            'id'           => $this->validUuid2,
            'tenant_id'    => $tenantId,
            'title'        => 'AG 2026',
            'scheduled_at' => '2026-04-10 10:00:00',
            'status'       => 'open',
        ]);

        $mockProxy = $this->createMock(ProxyRepository::class);
        $mockProxy->method('findWithNames')->willReturn([
            'id'                 => $this->validUuid,
            'giver_name'         => 'Jean Dupont',
            'receiver_name'      => 'Marie Curie',
            'giver_member_id'    => 'gggggggg-gggg-gggg-gggg-gggggggggggg',
            'receiver_member_id' => 'rrrrrrrr-rrrr-rrrr-rrrr-rrrrrrrrrrrr',
        ]);

        $mockSettings = $this->createMock(SettingsRepository::class);
        $mockSettings->method('get')->willReturn('Assoc Test');

        $this->injectRepos([
            MeetingRepository::class  => $mockMeeting,
            ProxyRepository::class    => $mockProxy,
            SettingsRepository::class => $mockSettings,
        ]);

        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_GET = [
            'proxy_id'   => $this->validUuid,
            'meeting_id' => $this->validUuid2,
        ];

        ob_start();
        try {
            (new ProcurationPdfController())->download();
        } catch (\AgVote\Core\Http\ApiResponseException $e) {
            ob_end_clean();
            $this->fail('download() threw an ApiResponseException unexpectedly: ' . $e->getMessage());
        }
        $output = ob_get_clean();

        $this->assertStringStartsWith('%PDF-', $output, 'Output must be a PDF byte stream starting with %PDF-');
        $this->assertNotEmpty($output, 'PDF output must not be empty');
    }

    /**
     * Integration test: download() with proxy not found must return 404.
     *
     * This verifies the error path executes api_fail('proxy_not_found', 404).
     */
    public function testDownloadProxyNotFoundReturns404(): void
    {
        $tenantId = 'aaaaaaaa-1111-2222-3333-444444444444';

        $mockMeeting = $this->createMock(MeetingRepository::class);
        $mockMeeting->method('findByIdForTenant')->willReturn([
            'id'           => $this->validUuid2,
            'tenant_id'    => $tenantId,
            'title'        => 'AG 2026',
            'scheduled_at' => '2026-04-10 10:00:00',
            'status'       => 'open',
        ]);

        $mockProxy = $this->createMock(ProxyRepository::class);
        $mockProxy->method('findWithNames')->willReturn(null);

        $mockSettings = $this->createMock(SettingsRepository::class);
        $mockSettings->method('get')->willReturn('Assoc Test');

        $this->injectRepos([
            MeetingRepository::class  => $mockMeeting,
            ProxyRepository::class    => $mockProxy,
            SettingsRepository::class => $mockSettings,
        ]);

        $result = $this->call([
            'proxy_id'   => $this->validUuid,
            'meeting_id' => $this->validUuid2,
        ]);

        $this->assertEquals(404, $result['status']);
        $this->assertEquals('proxy_not_found', $result['body']['error']);
    }
}
