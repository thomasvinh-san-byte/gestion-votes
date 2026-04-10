<?php

declare(strict_types=1);

namespace AgVote\Controller;

use AgVote\Core\Http\Request;

/**
 * Serves raw markdown content for the documentation viewer.
 * No authentication required — documentation is public.
 * Uses bootstrap.php (not api.php) — returns plain text, not JSON.
 */
final class DocContentController {
    public function show(): void {
        header('Content-Type: text/plain; charset=utf-8');

        $request = new Request();
        $page = trim((string) $request->query('page', ''));

        if ($page === '') {
            http_response_code(400);
            echo 'Missing page parameter.';
            return;
        }

        // Security: sanitize path — prevent directory traversal
        $page = str_replace('\\', '/', $page);
        if (preg_match('/\.\./', $page) || preg_match('#[^a-zA-Z0-9_/\-.]#', $page)) {
            http_response_code(400);
            echo 'Invalid page parameter.';
            return;
        }

        // Strip .md if provided
        $page = preg_replace('/\.md$/i', '', $page);

        // Docs root is at project root /docs (not public/docs)
        $docsRoot = realpath(dirname(__DIR__, 2) . '/docs');
        if ($docsRoot === false) {
            http_response_code(500);
            echo 'Documentation directory not found.';
            return;
        }

        $filePath = $docsRoot . '/' . $page . '.md';

        // Resolve symlinks and verify the real path stays inside /docs
        $realPath = realpath($filePath);
        if ($realPath === false || !str_starts_with($realPath, $docsRoot . '/')) {
            http_response_code(404);
            echo 'Document not found.';
            return;
        }

        echo file_get_contents($realPath);
    }
}
