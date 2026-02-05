#!/usr/bin/env php
<?php
/**
 * AG-Vote WebSocket Server
 *
 * Usage:
 *   php bin/websocket-server.php [--port=8080] [--host=0.0.0.0]
 *
 * En production, utiliser un process manager comme supervisord.
 */

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use Ratchet\Server\IoServer;
use Ratchet\Http\HttpServer;
use Ratchet\WebSocket\WsServer;
use AgVote\WebSocket\Server;
use AgVote\WebSocket\EventBroadcaster;
use React\EventLoop\Loop;

// Parse arguments
$options = getopt('', ['port:', 'host:', 'help']);

if (isset($options['help'])) {
    echo "AG-Vote WebSocket Server\n";
    echo "Usage: php bin/websocket-server.php [--port=8080] [--host=0.0.0.0]\n";
    exit(0);
}

$port = (int) ($options['port'] ?? getenv('WS_PORT') ?: 8080);
$host = $options['host'] ?? getenv('WS_HOST') ?: '0.0.0.0';

// PID file pour detection de process
$pidFile = '/tmp/agvote-ws.pid';
file_put_contents($pidFile, getmypid());

// Cleanup on exit
register_shutdown_function(function () use ($pidFile) {
    @unlink($pidFile);
});

// Signal handlers
if (function_exists('pcntl_signal')) {
    pcntl_signal(SIGTERM, function () use ($pidFile) {
        echo "\n[WS] Received SIGTERM, shutting down...\n";
        @unlink($pidFile);
        exit(0);
    });
    pcntl_signal(SIGINT, function () use ($pidFile) {
        echo "\n[WS] Received SIGINT, shutting down...\n";
        @unlink($pidFile);
        exit(0);
    });
}

echo "===========================================\n";
echo "  AG-Vote WebSocket Server\n";
echo "===========================================\n";
echo "Host: {$host}\n";
echo "Port: {$port}\n";
echo "PID:  " . getmypid() . "\n";
echo "===========================================\n\n";

// Creer le serveur WebSocket
$wsServer = new Server();

// Event loop pour traiter la queue d'evenements
$loop = Loop::get();

// Verifier la queue toutes les 100ms
$loop->addPeriodicTimer(0.1, function () use ($wsServer) {
    $events = EventBroadcaster::dequeue();

    foreach ($events as $event) {
        $target = $event['target'] ?? 'all';
        $type = $event['type'] ?? 'unknown';
        $data = $event['data'] ?? [];

        $message = [
            'type' => $type,
            'data' => $data,
        ];

        switch ($target) {
            case 'meeting':
                $meetingId = $event['meeting_id'] ?? null;
                if ($meetingId) {
                    $wsServer->broadcastToMeeting($meetingId, $message);
                }
                break;

            case 'tenant':
                $tenantId = $event['tenant_id'] ?? null;
                if ($tenantId) {
                    $wsServer->broadcastToTenant($tenantId, $message);
                }
                break;

            default:
                $wsServer->broadcastAll($message);
        }
    }
});

// Afficher les stats toutes les 30s
$loop->addPeriodicTimer(30, function () use ($wsServer) {
    $stats = $wsServer->getStats();
    echo "[WS] Stats: {$stats['total_connections']} connections, " .
         count($stats['rooms']) . " rooms\n";
});

// Creer le serveur HTTP/WebSocket
$webSock = new \React\Socket\SocketServer("{$host}:{$port}", [], $loop);
$server = new IoServer(
    new HttpServer(
        new WsServer($wsServer)
    ),
    $webSock,
    $loop
);

echo "[WS] Server started on ws://{$host}:{$port}\n";
echo "[WS] Press Ctrl+C to stop\n\n";

$loop->run();
