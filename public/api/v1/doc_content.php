<?php
// public/api/v1/doc_content.php
// Serves raw markdown content for the documentation viewer.
// No authentication required — documentation is public.
declare(strict_types=1);

header('Content-Type: text/plain; charset=utf-8');

$page = $_GET['page'] ?? '';

if ($page === '') {
    http_response_code(400);
    echo 'Missing page parameter.';
    exit;
}

// Security: sanitize path — prevent directory traversal
$page = str_replace('\\', '/', $page);
if (preg_match('/\.\./', $page) || preg_match('#[^a-zA-Z0-9_/\-.]#', $page)) {
    http_response_code(400);
    echo 'Invalid page parameter.';
    exit;
}

// Strip .md if provided
$page = preg_replace('/\.md$/i', '', $page);

// Docs root is at project root /docs (not public/docs)
$docsRoot = dirname(__DIR__, 3) . '/docs';
$filePath = $docsRoot . '/' . $page . '.md';

if (!file_exists($filePath) || !is_file($filePath)) {
    http_response_code(404);
    echo 'Document not found: ' . $page . '.md';
    exit;
}

// Serve the raw markdown
echo file_get_contents($filePath);
