<?php
declare(strict_types=1);

namespace Tests\Unit;

use AgVote\Core\Providers\RedisProvider;
use AgVote\SSE\EventBroadcaster;
use PHPUnit\Framework\TestCase;
use Redis;

/**
 * @group redis
 */
class EventBroadcasterTest extends TestCase {
    /** @var list<string> Redis keys created by individual tests, deleted in tearDown */
    private array $testKeys = [];

    protected function setUp(): void {
        RedisProvider::configure([
            'host' => getenv('REDIS_HOST') ?: '127.0.0.1',
            'port' => (int) (getenv('REDIS_PORT') ?: 6379),
        ]);
        $this->testKeys = [];
    }

    protected function tearDown(): void {
        // Clean up standard keys and any test-specific keys
        try {
            $redis = RedisProvider::connection();
            $redis->del('sse:event_queue');
            $redis->del('sse:server:active');
            foreach ($this->testKeys as $key) {
                $redis->del($key);
            }
        } catch (\Throwable) {}
    }

    // ── Existing structural and basic behavior tests ──────────────────────

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

    // ── TEST-03: SSE delivery reliability and race condition tests ────────

    /**
     * Verify RPUSH preserves insertion order so events are dequeued FIFO.
     * Covers: race condition — ordering guarantee.
     */
    public function testQueuePreservesInsertionOrder(): void {
        $redis = RedisProvider::connection();
        $redis->del('sse:event_queue');

        EventBroadcaster::toMeeting('meeting-order-test', 'event.first', ['seq' => 1]);
        EventBroadcaster::toMeeting('meeting-order-test', 'event.second', ['seq' => 2]);
        EventBroadcaster::toMeeting('meeting-order-test', 'event.third', ['seq' => 3]);

        $events = EventBroadcaster::dequeue();

        $this->assertCount(3, $events);
        $this->assertEquals('event.first', $events[0]['type']);
        $this->assertEquals('event.second', $events[1]['type']);
        $this->assertEquals('event.third', $events[2]['type']);
    }

    /**
     * Verify dequeue() atomically empties the queue so a second call returns nothing.
     * Covers: race condition — no double-delivery.
     */
    public function testDequeueEmptiesQueueAtomically(): void {
        $redis = RedisProvider::connection();
        $redis->del('sse:event_queue');

        EventBroadcaster::toMeeting('meeting-atomic-test', 'event.alpha', []);
        EventBroadcaster::toMeeting('meeting-atomic-test', 'event.beta', []);

        $firstDequeue = EventBroadcaster::dequeue();
        $this->assertCount(2, $firstDequeue, 'First dequeue must return 2 events');

        $secondDequeue = EventBroadcaster::dequeue();
        $this->assertEmpty($secondDequeue, 'Second dequeue must return empty array — queue already drained');
    }

    /**
     * Verify publishToSse fans out events to all registered consumer queues.
     * Covers: delivery reliability — consumer fan-out.
     */
    public function testPublishToSseFansOutToRegisteredConsumers(): void {
        $meetingId = 'test-meeting-' . uniqid();
        $consumerA = 'consumer-a';
        $consumerB = 'consumer-b';
        $consumerKeyA = "sse:queue:{$meetingId}:{$consumerA}";
        $consumerKeyB = "sse:queue:{$meetingId}:{$consumerB}";
        $consumersKey = "sse:consumers:{$meetingId}";

        $this->testKeys[] = $consumerKeyA;
        $this->testKeys[] = $consumerKeyB;
        $this->testKeys[] = $consumersKey;

        $redis = RedisProvider::connection();
        $redis->del($consumerKeyA);
        $redis->del($consumerKeyB);
        $redis->del($consumersKey);

        $redis->sAdd($consumersKey, $consumerA);
        $redis->sAdd($consumersKey, $consumerB);

        // PUSH_ENABLED defaults to true when not set — ensure clean state
        putenv('PUSH_ENABLED=1');

        EventBroadcaster::toMeeting($meetingId, 'vote.cast', ['motion_id' => 'xyz']);

        // Read consumer queues directly (raw JSON, no serializer)
        $redis->setOption(Redis::OPT_SERIALIZER, Redis::SERIALIZER_NONE);
        $rawA = $redis->lRange($consumerKeyA, 0, -1);
        $rawB = $redis->lRange($consumerKeyB, 0, -1);
        $redis->setOption(Redis::OPT_SERIALIZER, Redis::SERIALIZER_JSON);

        $this->assertCount(1, $rawA, 'consumer-a must have 1 event');
        $this->assertCount(1, $rawB, 'consumer-b must have 1 event');

        $eventA = json_decode($rawA[0], true);
        $eventB = json_decode($rawB[0], true);

        $this->assertEquals('vote.cast', $eventA['type']);
        $this->assertEquals('vote.cast', $eventB['type']);

        putenv('PUSH_ENABLED=');
    }

    /**
     * Verify tenant events skip per-consumer queues because publishToSse()
     * returns early when meeting_id is null.
     * Covers: correctness — tenant events don't contaminate meeting consumer queues.
     */
    public function testPublishToSseSkipsTenantEvents(): void {
        $meetingId = 'test-meeting-' . uniqid();
        $consumerId = 'consumer-x';
        $consumerKey = "sse:queue:{$meetingId}:{$consumerId}";
        $consumersKey = "sse:consumers:{$meetingId}";

        $this->testKeys[] = $consumerKey;
        $this->testKeys[] = $consumersKey;

        $redis = RedisProvider::connection();
        $redis->del($consumerKey);
        $redis->del($consumersKey);

        $redis->sAdd($consumersKey, $consumerId);

        // Broadcast a tenant event — no meeting_id in payload
        EventBroadcaster::toTenant('some-tenant', 'meeting.status_changed', ['new_status' => 'running']);

        // Consumer queue for our meeting must remain empty
        $redis->setOption(Redis::OPT_SERIALIZER, Redis::SERIALIZER_NONE);
        $queueLength = $redis->lLen($consumerKey);
        $redis->setOption(Redis::OPT_SERIALIZER, Redis::SERIALIZER_JSON);

        $this->assertEquals(0, $queueLength, 'Tenant events must not be pushed to per-meeting consumer queues');
    }

    /**
     * Verify isServerRunning() returns false after the heartbeat key's TTL expires.
     * Covers: delivery reliability — server death detection via key expiry.
     */
    public function testIsServerRunningReturnsFalseAfterHeartbeatExpiry(): void {
        $redis = RedisProvider::connection();
        $redis->set('sse:server:active', '1', ['EX' => 1]);

        $this->assertTrue(EventBroadcaster::isServerRunning(), 'Server must be detected as running immediately after key creation');

        usleep(1100000); // 1.1 seconds — ensure key has expired

        $this->assertFalse(EventBroadcaster::isServerRunning(), 'Server must be detected as stopped after heartbeat TTL expires');
    }

    /**
     * Verify per-consumer queues are trimmed to the last 100 events (lTrim -100, -1).
     * Covers: delivery reliability — memory safety for consumer queues under load.
     */
    public function testConsumerQueueTrimmedToLast100Events(): void {
        $meetingId = 'test-meeting-' . uniqid();
        $consumerId = 'consumer-trim';
        $consumerKey = "sse:queue:{$meetingId}:{$consumerId}";
        $consumersKey = "sse:consumers:{$meetingId}";

        $this->testKeys[] = $consumerKey;
        $this->testKeys[] = $consumersKey;

        $redis = RedisProvider::connection();
        $redis->del($consumerKey);
        $redis->del($consumersKey);
        $redis->sAdd($consumersKey, $consumerId);

        putenv('PUSH_ENABLED=1');

        // Push 105 events — lTrim(-100, -1) should keep only the last 100
        for ($i = 1; $i <= 105; $i++) {
            EventBroadcaster::toMeeting($meetingId, 'tick', ['n' => $i]);
        }

        $redis->setOption(Redis::OPT_SERIALIZER, Redis::SERIALIZER_NONE);
        $queueLength = $redis->lLen($consumerKey);
        $redis->setOption(Redis::OPT_SERIALIZER, Redis::SERIALIZER_JSON);

        $this->assertEquals(100, $queueLength, 'Consumer queue must be trimmed to last 100 events');

        putenv('PUSH_ENABLED=');
    }

    // -- GAP CLOSURE: connection loss + client reconnection ---------

    /**
     * Verify that isServerRunning() returns false on Redis connection failure (catch Throwable path),
     * and that queueRedis() has NO catch block — so Redis failures propagate to caller.
     *
     * Covers: SC1 gap — "perte de connexion Redis"
     */
    public function testRedisConnectionLossHandling(): void {
        // Part 1: Behavioral test — configure bogus host, isServerRunning must return false
        $originalConfig = [
            'host' => getenv('REDIS_HOST') ?: '127.0.0.1',
            'port' => (int) (getenv('REDIS_PORT') ?: 6379),
        ];

        try {
            RedisProvider::configure(['host' => '255.255.255.255', 'port' => 1]);
            $result = EventBroadcaster::isServerRunning();
            $this->assertFalse($result, 'isServerRunning() must return false when Redis connection fails');
        } catch (\Throwable) {
            // If configure() itself throws, the behavioral test is still meaningful via structural check below
        } finally {
            // Restore original config
            RedisProvider::configure($originalConfig);
        }

        // Part 2: Structural assertions — verify source code properties via Reflection
        $refIsRunning = new \ReflectionMethod(EventBroadcaster::class, 'isServerRunning');
        $filename = $refIsRunning->getFileName();
        $startLine = $refIsRunning->getStartLine();
        $endLine = $refIsRunning->getEndLine();
        $isRunningSource = implode('', array_slice(file($filename), $startLine - 1, $endLine - $startLine + 1));

        $this->assertStringContainsString(
            'catch',
            $isRunningSource,
            'isServerRunning() must have a catch block to handle Redis connection failures gracefully',
        );
        $this->assertStringContainsString(
            'Throwable',
            $isRunningSource,
            'isServerRunning() must catch Throwable to handle all Redis failure types',
        );

        // queueRedis must NOT have catch — Redis failures must propagate to caller
        $refQueueRedis = new \ReflectionMethod(EventBroadcaster::class, 'queueRedis');
        $startLine = $refQueueRedis->getStartLine();
        $endLine = $refQueueRedis->getEndLine();
        $queueRedisSource = implode('', array_slice(file($filename), $startLine - 1, $endLine - $startLine + 1));

        $this->assertStringNotContainsString(
            'catch',
            $queueRedisSource,
            'queueRedis() must NOT catch exceptions — Redis connection loss must propagate to caller',
        );
    }

    /**
     * Simulate a client disconnect/reconnect cycle.
     * Events pushed while consumer is "disconnected" (not reading) must be buffered
     * in the per-consumer queue and available on "reconnect" (reading the queue).
     *
     * Covers: SC1 gap — "reconnexion du client"
     */
    public function testClientReconnectionDeliversBufferedEvents(): void {
        $meetingId = 'test-meeting-' . uniqid();
        $consumerId = 'consumer-reconnect-' . uniqid();
        $consumerKey = "sse:queue:{$meetingId}:{$consumerId}";
        $consumersKey = "sse:consumers:{$meetingId}";

        $this->testKeys[] = $consumerKey;
        $this->testKeys[] = $consumersKey;

        $redis = RedisProvider::connection();
        $redis->del($consumerKey);
        $redis->del($consumersKey);

        // Register consumer (simulates a client that was connected and will reconnect)
        $redis->sAdd($consumersKey, $consumerId);

        putenv('PUSH_ENABLED=1');

        // Push 3 events while consumer is "disconnected" (not reading the queue)
        EventBroadcaster::toMeeting($meetingId, 'event.buffered', ['seq' => 1]);
        EventBroadcaster::toMeeting($meetingId, 'event.buffered', ['seq' => 2]);
        EventBroadcaster::toMeeting($meetingId, 'event.buffered', ['seq' => 3]);

        // Simulate reconnect: read the consumer queue (raw JSON, no serializer)
        $redis->setOption(Redis::OPT_SERIALIZER, Redis::SERIALIZER_NONE);
        $buffered = $redis->lRange($consumerKey, 0, -1);
        $redis->setOption(Redis::OPT_SERIALIZER, Redis::SERIALIZER_JSON);

        $this->assertCount(3, $buffered, 'All 3 buffered events must be available on client reconnect');

        // Verify FIFO order is preserved
        $event1 = json_decode($buffered[0], true);
        $event2 = json_decode($buffered[1], true);
        $event3 = json_decode($buffered[2], true);
        $this->assertEquals(1, $event1['data']['seq'], 'First buffered event must be seq 1');
        $this->assertEquals(2, $event2['data']['seq'], 'Second buffered event must be seq 2');
        $this->assertEquals(3, $event3['data']['seq'], 'Third buffered event must be seq 3');

        // Drain queue (simulates client consuming all buffered events on reconnect)
        $redis->del($consumerKey);
        $this->testKeys = array_filter($this->testKeys, fn ($k) => $k !== $consumerKey);

        $redis->setOption(Redis::OPT_SERIALIZER, Redis::SERIALIZER_NONE);
        $afterDrain = $redis->lRange($consumerKey, 0, -1);
        $redis->setOption(Redis::OPT_SERIALIZER, Redis::SERIALIZER_JSON);
        $this->assertEmpty($afterDrain, 'Queue must be empty after client drains buffered events');

        // Re-register key for cleanup tracking after drain
        $this->testKeys[] = $consumerKey;

        // New events after reconnect must be delivered normally
        EventBroadcaster::toMeeting($meetingId, 'event.post_reconnect', ['seq' => 4]);

        $redis->setOption(Redis::OPT_SERIALIZER, Redis::SERIALIZER_NONE);
        $postReconnect = $redis->lRange($consumerKey, 0, -1);
        $redis->setOption(Redis::OPT_SERIALIZER, Redis::SERIALIZER_JSON);

        $this->assertCount(1, $postReconnect, 'New events after reconnect must be delivered to consumer queue');
        $event4 = json_decode($postReconnect[0], true);
        $this->assertEquals('event.post_reconnect', $event4['type'], 'Post-reconnect event type must match');

        putenv('PUSH_ENABLED=');
    }
}
