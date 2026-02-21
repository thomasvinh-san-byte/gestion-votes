<?php

declare(strict_types=1);

/**
 * Generates an OpenAPI 3.0 spec from app/routes.php.
 *
 * Usage: php scripts/generate_openapi.php > docs/openapi.yaml
 */

$source = file_get_contents(__DIR__ . '/../app/routes.php');

// Parse mapAny calls
$routes = [];
// mapAny("{$prefix}/path", Controller::class, 'action', $middleware)
preg_match_all(
    '/\$router->mapAny\(\s*"\{\$prefix\}\/([^"]+)"\s*,\s*([A-Za-z]+)::class\s*,\s*\'([^\']+)\'\s*(?:,\s*(\$[a-zA-Z_]+|\[.*?\]))?\s*\)/s',
    $source,
    $matches,
    PREG_SET_ORDER,
);

foreach ($matches as $m) {
    $path = '/api/v1/' . $m[1];
    $controller = $m[2];
    $action = $m[3];
    $mw = $m[4] ?? '';
    $routes[] = ['path' => $path, 'methods' => ['GET', 'POST'], 'controller' => $controller, 'action' => $action, 'mw' => $mw];
}

// Parse mapMulti calls
preg_match_all(
    '/\$router->mapMulti\(\s*"\{\$prefix\}\/([^"]+)"\s*,\s*\[(.*?)\]\);/s',
    $source,
    $multiMatches,
    PREG_SET_ORDER,
);

foreach ($multiMatches as $mm) {
    $path = '/api/v1/' . $mm[1];
    $body = $mm[2];
    preg_match_all(
        '/\'(GET|POST|PUT|PATCH|DELETE)\'\s*=>\s*\[([A-Za-z]+)::class,\s*\'([^\']+)\'\s*,\s*(\$[a-zA-Z_]+|\[.*?\])\]/s',
        $body,
        $methodMatches,
        PREG_SET_ORDER,
    );
    foreach ($methodMatches as $methM) {
        $routes[] = ['path' => $path, 'methods' => [$methM[1]], 'controller' => $methM[2], 'action' => $methM[3], 'mw' => $methM[4]];
    }
}

// Parse standalone map/mapBootstrap
preg_match_all(
    '/\$router->map(?:Bootstrap)?\(\s*(?:\'(GET|POST)\'\s*,\s*)?\'([^\']+)\'\s*,\s*([A-Za-z]+)::class\s*,\s*\'([^\']+)\'/s',
    $source,
    $standaloneMatches,
    PREG_SET_ORDER,
);

foreach ($standaloneMatches as $sm) {
    $method = $sm[1] ?: 'GET';
    $path = $sm[2];
    $routes[] = ['path' => $path, 'methods' => [$method], 'controller' => $sm[3], 'action' => $sm[4], 'mw' => ''];
}

// Resolve middleware shortcuts
function resolveRole(string $mw): string {
    $map = [
        '$op' => 'operator',
        '$admin' => 'admin',
        '$opAdm' => 'operator, admin',
        '$pub' => 'public',
        '$view' => 'viewer',
        '$audit' => 'auditor',
        '$trOpPresAdm' => 'operator, president, admin',
        '$rlCsv' => 'operator, admin (rate limited)',
        '$rlXlsx' => 'operator, admin (rate limited)',
    ];
    if (isset($map[$mw])) {
        return $map[$mw];
    }
    if (preg_match("/role.*?'([^']+)'/", $mw, $rm)) {
        return $rm[1];
    }
    return 'public';
}

// Tag mapping
function controllerTag(string $controller): string {
    $map = [
        'AdminController' => 'Administration',
        'AgendaController' => 'Agendas',
        'AnalyticsController' => 'Analytics',
        'AttendancesController' => 'Attendances',
        'AuditController' => 'Audit',
        'AuthController' => 'Authentication',
        'BallotsController' => 'Ballots',
        'DashboardController' => 'Dashboard',
        'DevicesController' => 'Devices',
        'DevSeedController' => 'Development',
        'DocController' => 'Documentation',
        'EmailController' => 'Email',
        'EmailTemplatesController' => 'Email',
        'EmailTrackingController' => 'Email',
        'EmergencyController' => 'Emergency',
        'ExportController' => 'Export',
        'ExportTemplatesController' => 'Export',
        'ImportController' => 'Import',
        'InvitationsController' => 'Invitations',
        'MeetingAttachmentController' => 'Meetings',
        'MeetingReportsController' => 'Reports',
        'MeetingsController' => 'Meetings',
        'MeetingWorkflowController' => 'Workflow',
        'MemberGroupsController' => 'Members',
        'MembersController' => 'Members',
        'MotionsController' => 'Motions',
        'OperatorController' => 'Operator',
        'PoliciesController' => 'Policies',
        'ProjectorController' => 'Projector',
        'ProxiesController' => 'Proxies',
        'QuorumController' => 'Quorum',
        'ReminderController' => 'Reminders',
        'SpeechController' => 'Speech',
        'TrustController' => 'Trust',
        'VotePublicController' => 'Voting',
        'VoteTokenController' => 'Voting',
    ];
    return $map[$controller] ?? $controller;
}

// Build YAML output
$yaml = <<<'YAML'
openapi: 3.0.3
info:
  title: AG-Vote API
  description: |
    REST API for AG-Vote, a deliberative voting management platform
    for general assemblies and governance meetings.

    ## Authentication
    Session-based authentication via PHP session cookie.
    CSRF token required for mutating requests (X-CSRF-Token header).

    ## Roles
    - **admin**: Full system administration
    - **operator**: Meeting management and operations
    - **president**: Meeting chair with validation rights
    - **auditor**: Read-only access to audit trails
    - **trust**: Assessor/scrutineer role
    - **viewer**: Basic read access
    - **public**: Authenticated user (any role)

    ## Rate Limiting
    Some endpoints are rate-limited. When exceeded, a 429 response
    is returned with a Retry-After header.
  version: 1.0.0
  contact:
    name: AG-Vote Team
servers:
  - url: /api/v1
    description: API v1

components:
  securitySchemes:
    sessionCookie:
      type: apiKey
      in: cookie
      name: PHPSESSID
    csrfToken:
      type: apiKey
      in: header
      name: X-CSRF-Token
  schemas:
    SuccessResponse:
      type: object
      properties:
        ok:
          type: boolean
          example: true
        data:
          type: object
    ErrorResponse:
      type: object
      properties:
        ok:
          type: boolean
          example: false
        error:
          type: string
        code:
          type: integer

YAML;

// Collect tags
$tags = [];
foreach ($routes as $r) {
    $tag = controllerTag($r['controller']);
    $tags[$tag] = true;
}
ksort($tags);

$yaml .= "tags:\n";
foreach (array_keys($tags) as $tag) {
    $yaml .= "  - name: {$tag}\n";
}
$yaml .= "\n";

// Group by path
$grouped = [];
foreach ($routes as $r) {
    $path = $r['path'];
    foreach ($r['methods'] as $method) {
        $grouped[$path][$method] = $r;
    }
}
ksort($grouped);

$yaml .= "paths:\n";
foreach ($grouped as $path => $methods) {
    $yaml .= "  '{$path}':\n";
    foreach ($methods as $method => $route) {
        $m = strtolower($method);
        $tag = controllerTag($route['controller']);
        $ctrl = str_replace('Controller', '', $route['controller']);
        $opId = lcfirst($ctrl) . ucfirst($route['action']);
        $role = resolveRole($route['mw']);
        $summary = $ctrl . '::' . $route['action'] . '()';

        $yaml .= "    {$m}:\n";
        $yaml .= "      tags: [{$tag}]\n";
        $yaml .= "      operationId: {$opId}\n";
        $yaml .= "      summary: '{$summary}'\n";
        $yaml .= "      description: 'Role: {$role}'\n";
        if ($role !== 'public') {
            $yaml .= "      security:\n";
            $yaml .= "        - sessionCookie: []\n";
        }
        $yaml .= "      responses:\n";
        $yaml .= "        '200':\n";
        $yaml .= "          description: Success\n";
        $yaml .= "          content:\n";
        $yaml .= "            application/json:\n";
        $yaml .= "              schema:\n";
        $yaml .= "                \$ref: '#/components/schemas/SuccessResponse'\n";
        $yaml .= "        '401':\n";
        $yaml .= "          description: Unauthorized\n";
        $yaml .= "        '403':\n";
        $yaml .= "          description: Forbidden\n";
        if (str_contains($route['mw'], 'rate_limit')) {
            $yaml .= "        '429':\n";
            $yaml .= "          description: Rate limit exceeded\n";
        }
    }
}

echo $yaml;
