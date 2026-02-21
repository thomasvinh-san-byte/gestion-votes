<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

/**
 * Tests for the api helper functions defined in api.php.
 * These tests verify the function signatures and basic contracts.
 */
class ApiHelpersTest extends TestCase
{
    private static bool $loaded = false;

    public static function setUpBeforeClass(): void
    {
        if (!self::$loaded) {
            // We can't safely require api.php (it sets exception handlers),
            // so we test via source code analysis.
            self::$loaded = true;
        }
    }

    public function testApiQueryFunctionExists(): void
    {
        $apiFile = PROJECT_ROOT . '/app/api.php';
        $content = file_get_contents($apiFile);

        $this->assertStringContainsString(
            'function api_query(string $key, string $default = \'\'): string',
            $content,
            'api_query() helper must be defined in api.php'
        );
    }

    public function testApiQueryIntFunctionExists(): void
    {
        $apiFile = PROJECT_ROOT . '/app/api.php';
        $content = file_get_contents($apiFile);

        $this->assertStringContainsString(
            'function api_query_int(string $key, int $default = 0): int',
            $content,
            'api_query_int() helper must be defined in api.php'
        );
    }

    public function testApiFileFunctionExists(): void
    {
        $apiFile = PROJECT_ROOT . '/app/api.php';
        $content = file_get_contents($apiFile);

        $this->assertStringContainsString(
            'function api_file(string ...$keys): ?array',
            $content,
            'api_file() helper must be defined in api.php'
        );
    }

    public function testNoRawSqlInApiGuards(): void
    {
        $apiFile = PROJECT_ROOT . '/app/api.php';
        $content = file_get_contents($apiFile);

        // Check that guard functions delegate to repositories
        $this->assertStringContainsString('MeetingRepository', $content);
        $this->assertStringNotContainsString('db()->prepare("SELECT', $content,
            'api.php must not contain raw SQL queries - use repositories instead');
    }

    public function testControllersDoNotUseGetSuperglobal(): void
    {
        $controllerDir = PROJECT_ROOT . '/app/Controller/';
        $files = glob($controllerDir . '*.php');
        $this->assertNotEmpty($files);

        // Non-API controllers use bootstrap.php (not api.php) and don't have api_query()
        $nonApiControllers = ['EmailTrackingController.php', 'DocContentController.php'];

        $violations = [];
        foreach ($files as $file) {
            $basename = basename($file);
            if (in_array($basename, $nonApiControllers, true)) {
                continue;
            }
            $content = file_get_contents($file);

            // Count $_GET occurrences (excluding comments)
            $lines = explode("\n", $content);
            foreach ($lines as $lineNum => $line) {
                $trimmed = ltrim($line);
                // Skip comment lines
                if (str_starts_with($trimmed, '//') || str_starts_with($trimmed, '*') || str_starts_with($trimmed, '/*')) {
                    continue;
                }
                if (str_contains($line, '$_GET[') || str_contains($line, '$_GET ')) {
                    $violations[] = "{$basename}:" . ($lineNum + 1) . ": " . trim($line);
                }
                if (str_contains($line, '$_FILES[') || str_contains($line, '$_FILES ')) {
                    $violations[] = "{$basename}:" . ($lineNum + 1) . ": " . trim($line);
                }
                // $_POST is acceptable in api_request() return pattern but not standalone
                if (preg_match('/\$_POST\b(?!\s*;)/', $line) && !str_contains($line, 'api_request')) {
                    // Allow in AuthController for session management
                    if ($basename === 'AuthController.php') continue;
                    $violations[] = "{$basename}:" . ($lineNum + 1) . ": " . trim($line);
                }
            }
        }

        $this->assertEmpty(
            $violations,
            "Controllers must not access \$_GET/\$_FILES/\$_POST directly. Use api_query(), api_file(), or api_request() instead.\n"
            . "Violations found:\n" . implode("\n", array_slice($violations, 0, 20))
        );
    }
}
