<?php
declare(strict_types=1);

namespace AgVote\WebSocket;

use Ratchet\MessageComponentInterface;
use Ratchet\ConnectionInterface;
use SplObjectStorage;

/**
 * WebSocket Server pour AG-Vote.
 *
 * Gere les connexions clients et le broadcast d'evenements temps reel.
 *
 * Events supportes:
 * - motion.opened, motion.closed, motion.updated
 * - vote.cast, vote.updated
 * - attendance.updated
 * - meeting.status_changed
 * - quorum.updated
 */
class Server implements MessageComponentInterface
{
    /** @var SplObjectStorage<ConnectionInterface, array> */
    protected SplObjectStorage $clients;

    /** @var array<string, SplObjectStorage> Clients par meeting_id */
    protected array $meetingRooms = [];

    /** @var array<string, SplObjectStorage> Clients par tenant_id */
    protected array $tenantRooms = [];

    public function __construct()
    {
        $this->clients = new SplObjectStorage();
        echo "[WS] Server initialized\n";
    }

    public function onOpen(ConnectionInterface $conn): void
    {
        $this->clients->attach($conn, [
            'subscriptions' => [],
            'tenant_id' => null,
            'user_id' => null,
            'authenticated' => false,
        ]);

        echo "[WS] New connection: {$conn->resourceId}\n";

        // Envoyer un message de bienvenue
        $conn->send(json_encode([
            'type' => 'connected',
            'connection_id' => $conn->resourceId,
            'timestamp' => date('c'),
        ]));
    }

    public function onMessage(ConnectionInterface $from, $msg): void
    {
        $data = json_decode($msg, true);
        if (!$data || !isset($data['action'])) {
            $from->send(json_encode(['type' => 'error', 'message' => 'Invalid message format']));
            return;
        }

        switch ($data['action']) {
            case 'authenticate':
                $this->handleAuthenticate($from, $data);
                break;

            case 'subscribe':
                $this->handleSubscribe($from, $data);
                break;

            case 'unsubscribe':
                $this->handleUnsubscribe($from, $data);
                break;

            case 'ping':
                $from->send(json_encode(['type' => 'pong', 'timestamp' => date('c')]));
                break;

            default:
                $from->send(json_encode(['type' => 'error', 'message' => 'Unknown action']));
        }
    }

    public function onClose(ConnectionInterface $conn): void
    {
        // Retirer de toutes les rooms
        $info = $this->clients[$conn] ?? [];
        foreach ($info['subscriptions'] ?? [] as $room) {
            $this->leaveRoom($conn, $room);
        }

        $this->clients->detach($conn);
        echo "[WS] Connection closed: {$conn->resourceId}\n";
    }

    public function onError(ConnectionInterface $conn, \Exception $e): void
    {
        echo "[WS] Error on {$conn->resourceId}: {$e->getMessage()}\n";
        $conn->close();
    }

    /**
     * Authentifie une connexion avec un token HMAC signe.
     *
     * Le token est genere cote PHP (voir ws_auth_token()) :
     *   base64( tenant_id:user_id:timestamp:hmac_sha256(secret, tenant_id|user_id|timestamp) )
     *
     * Validite : 5 minutes (300 secondes).
     */
    protected function handleAuthenticate(ConnectionInterface $conn, array $data): void
    {
        $token = $data['token'] ?? null;
        $tenantId = $data['tenant_id'] ?? null;

        if (!$token || !$tenantId) {
            $conn->send(json_encode([
                'type' => 'auth_error',
                'message' => 'Missing token or tenant_id',
            ]));
            return;
        }

        // Decode and validate HMAC token
        $decoded = base64_decode($token, true);
        if ($decoded === false) {
            $conn->send(json_encode(['type' => 'auth_error', 'message' => 'Invalid token format']));
            return;
        }

        $parts = explode(':', $decoded, 4);
        if (count($parts) !== 4) {
            $conn->send(json_encode(['type' => 'auth_error', 'message' => 'Malformed token']));
            return;
        }

        [$tokenTenant, $tokenUser, $tokenTs, $tokenHmac] = $parts;

        // Verify tenant_id matches
        if ($tokenTenant !== $tenantId) {
            $conn->send(json_encode(['type' => 'auth_error', 'message' => 'Tenant mismatch']));
            return;
        }

        // Verify timestamp (max 5 minutes old)
        $age = time() - (int) $tokenTs;
        if ($age < 0 || $age > 300) {
            $conn->send(json_encode(['type' => 'auth_error', 'message' => 'Token expired']));
            return;
        }

        // Verify HMAC signature
        $secret = getenv('APP_SECRET') ?: '';
        $expected = hash_hmac('sha256', "{$tokenTenant}|{$tokenUser}|{$tokenTs}", $secret);
        if (!hash_equals($expected, $tokenHmac)) {
            $conn->send(json_encode(['type' => 'auth_error', 'message' => 'Invalid signature']));
            return;
        }

        $info = $this->clients[$conn];
        $info['authenticated'] = true;
        $info['tenant_id'] = $tenantId;
        $info['user_id'] = $tokenUser ?: null;
        $this->clients[$conn] = $info;

        // Rejoindre automatiquement la room tenant
        $this->joinRoom($conn, "tenant:{$tenantId}");

        $conn->send(json_encode([
            'type' => 'authenticated',
            'tenant_id' => $tenantId,
        ]));

        echo "[WS] Authenticated: {$conn->resourceId} for tenant {$tenantId}\n";
    }

    /**
     * Abonne une connexion a un meeting.
     */
    protected function handleSubscribe(ConnectionInterface $conn, array $data): void
    {
        $info = $this->clients[$conn];

        if (!$info['authenticated']) {
            $conn->send(json_encode([
                'type' => 'error',
                'message' => 'Not authenticated',
            ]));
            return;
        }

        $meetingId = $data['meeting_id'] ?? null;
        if (!$meetingId) {
            $conn->send(json_encode(['type' => 'error', 'message' => 'Missing meeting_id']));
            return;
        }

        $room = "meeting:{$meetingId}";
        $this->joinRoom($conn, $room);

        $conn->send(json_encode([
            'type' => 'subscribed',
            'meeting_id' => $meetingId,
        ]));

        echo "[WS] {$conn->resourceId} subscribed to meeting {$meetingId}\n";
    }

    /**
     * Desabonne une connexion d'un meeting.
     */
    protected function handleUnsubscribe(ConnectionInterface $conn, array $data): void
    {
        $meetingId = $data['meeting_id'] ?? null;
        if ($meetingId) {
            $this->leaveRoom($conn, "meeting:{$meetingId}");
            $conn->send(json_encode([
                'type' => 'unsubscribed',
                'meeting_id' => $meetingId,
            ]));
        }
    }

    /**
     * Fait rejoindre une room a une connexion.
     */
    protected function joinRoom(ConnectionInterface $conn, string $room): void
    {
        if (!isset($this->meetingRooms[$room])) {
            $this->meetingRooms[$room] = new SplObjectStorage();
        }
        $this->meetingRooms[$room]->attach($conn);

        $info = $this->clients[$conn];
        $info['subscriptions'][] = $room;
        $this->clients[$conn] = $info;
    }

    /**
     * Fait quitter une room a une connexion.
     */
    protected function leaveRoom(ConnectionInterface $conn, string $room): void
    {
        if (isset($this->meetingRooms[$room])) {
            $this->meetingRooms[$room]->detach($conn);
            if ($this->meetingRooms[$room]->count() === 0) {
                unset($this->meetingRooms[$room]);
            }
        }

        $info = $this->clients[$conn];
        $info['subscriptions'] = array_filter(
            $info['subscriptions'] ?? [],
            fn($r) => $r !== $room
        );
        $this->clients[$conn] = $info;
    }

    /**
     * Broadcast un evenement a tous les clients d'un meeting.
     */
    public function broadcastToMeeting(string $meetingId, array $event): void
    {
        $room = "meeting:{$meetingId}";
        $this->broadcastToRoom($room, $event);
    }

    /**
     * Broadcast un evenement a tous les clients d'un tenant.
     */
    public function broadcastToTenant(string $tenantId, array $event): void
    {
        $room = "tenant:{$tenantId}";
        $this->broadcastToRoom($room, $event);
    }

    /**
     * Broadcast un evenement a une room.
     */
    protected function broadcastToRoom(string $room, array $event): void
    {
        if (!isset($this->meetingRooms[$room])) {
            return;
        }

        $message = json_encode(array_merge($event, [
            'timestamp' => date('c'),
        ]));

        $count = 0;
        foreach ($this->meetingRooms[$room] as $client) {
            $client->send($message);
            $count++;
        }

        echo "[WS] Broadcast to {$room}: {$event['type']} ({$count} clients)\n";
    }

    /**
     * Broadcast un evenement a tous les clients connectes.
     */
    public function broadcastAll(array $event): void
    {
        $message = json_encode(array_merge($event, [
            'timestamp' => date('c'),
        ]));

        foreach ($this->clients as $client) {
            $client->send($message);
        }
    }

    /**
     * Retourne les statistiques du serveur.
     */
    public function getStats(): array
    {
        return [
            'total_connections' => $this->clients->count(),
            'rooms' => array_map(
                fn($room) => $room->count(),
                $this->meetingRooms
            ),
        ];
    }
}
