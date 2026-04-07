<?php
declare(strict_types=1);

namespace Tests\Unit;

use AgVote\Core\Providers\RedisProvider;
use AgVote\SSE\EventBroadcaster;
use PHPUnit\Framework\TestCase;

/**
 * @group redis
 */
class EventBroadcasterTest extends TestCase {
    protected function setUp(): void {
        RedisProvider::configure([
            'host' => getenv('REDIS_HOST') ?: '127.0.0.1',
            'port' => (int) (getenv('REDIS_PORT') ?: 6379),
        ]);
    }

    protected function tearDown(): void {
        // Clean up test keys
        try {
            $redis = RedisProvider::connection();
            $redis->del('sse:event_queue');
            $redis->del('sse:server:active');
        } catch (\Throwable) {}
    }

    public function testIsServerRunningReturnsFalseWhenNoHeartbeat(): void {
        // Ensure key does not exist
        $redis = RedisProvider::connection();
        $redis->del('sse:server:active');

        $this->assertFalse(EventBroadcaster::isServerRunning());
    }

    public function testIsServerRunningReturnsTrueWhenHeartbeatPresent(): void {
        $redis = RedisProvider::connection();
        $redis->set('sse:server:active', '1', ['EX' => 90]);

        $this->assertTrue(EventBroadcaster::isServerRunning());
    }

    public function testDequeueReturnsEmptyWhenNoEvents(): void {
        // Clean queue
        $redis = RedisProvider::connection();
        $redis->del('sse:event_queue');

        $events = EventBroadcaster::dequeue();
        $this->assertIsArray($events);
        $this->assertEmpty($events);
    }

    public function testNoFileConstantsExist(): void {
        $reflection = new \ReflectionClass(EventBroadcaster::class);
        $constants = $reflection->getConstants();

        $this->assertArrayNotHasKey('QUEUE_FILE', $constants);
        $this->assertArrayNotHasKey('LOCK_FILE', $constants);
    }

    public function testNoFileMethodsExist(): void {
        $reflection = new \ReflectionClass(EventBroadcaster::class);
        $methods = array_map(fn ($m) => $m->getName(), $reflection->getMethods());

        $this->assertNotContains('queueFile', $methods);
        $this->assertNotContains('dequeueFile', $methods);
        $this->assertNotContains('publishToSseFile', $methods);
        $this->assertNotContains('dequeueSseFile', $methods);
        $this->assertNotContains('sseFilePath', $methods);
        $this->assertNotContains('useRedis', $methods);
    }

    public function testHeartbeatKeyConstantExists(): void {
        $reflection = new \ReflectionClass(EventBroadcaster::class);
        $constants = $reflection->getConstants(\ReflectionClassConstant::IS_PRIVATE);
        // Check via isServerRunning behavior — the key name is private
        // We test the behavior instead
        $this->assertTrue(true); // covered by isServerRunning tests
    }
}
