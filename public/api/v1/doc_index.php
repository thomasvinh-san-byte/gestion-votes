<?php
// public/api/v1/doc_index.php
// Returns the documentation index (list of available docs by category).
// No authentication required — documentation is public.
declare(strict_types=1);

require __DIR__ . '/../../../app/api.php';

$docsRoot = dirname(__DIR__, 3) . '/docs';

$categories = [
    'Utilisateur' => ['FAQ', 'UTILISATION_LIVE', 'RECETTE_DEMO', 'ANALYTICS_ETHICS'],
    'Installation' => ['INSTALL_MAC', 'DOCKER_INSTALL', 'dev/INSTALLATION'],
    'Technique'   => ['dev/ARCHITECTURE', 'dev/API', 'dev/SECURITY', 'dev/TESTS'],
    'Conformité'  => ['dev/MIGRATION', 'dev/WEB_COMPONENTS'],
];

$labels = [
    'FAQ' => 'FAQ',
    'UTILISATION_LIVE' => 'Guide opérateur',
    'RECETTE_DEMO' => 'Démo guidée',
    'ANALYTICS_ETHICS' => 'Éthique & RGPD',
    'INSTALL_MAC' => 'Installation macOS',
    'DOCKER_INSTALL' => 'Installation Docker (Linux)',
    'dev/INSTALLATION' => 'Installation (développeur)',
    'dev/ARCHITECTURE' => 'Architecture',
    'dev/API' => 'Référence API',
    'dev/SECURITY' => 'Sécurité',
    'dev/TESTS' => 'Tests',
    'dev/MIGRATION' => 'Migrations',
    'dev/WEB_COMPONENTS' => 'Web Components',
];

$result = [];
foreach ($categories as $catName => $docs) {
    $items = [];
    foreach ($docs as $doc) {
        if (file_exists($docsRoot . '/' . $doc . '.md')) {
            $items[] = [
                'page' => $doc,
                'label' => $labels[$doc] ?? basename($doc),
            ];
        }
    }
    if ($items) {
        $result[] = ['category' => $catName, 'items' => $items];
    }
}

api_ok($result);
