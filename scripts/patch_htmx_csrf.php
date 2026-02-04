#!/usr/bin/env php
<?php
/**
 * patch_htmx_csrf.php - Ajoute automatiquement le support CSRF aux pages HTMX
 * 
 * Usage: php patch_htmx_csrf.php [--dry-run]
 */

$dryRun = in_array('--dry-run', $argv);
$publicDir = __DIR__ . '/../public';

// Pages à patcher
$pages = [
    'operator.htmx.html',
    'trust.htmx.html',
    'vote.htmx.html',
    'validate.htmx.html',
    'speaker.htmx.html',
    'president-dashboard.htmx.html',
    'admin.htmx.html',
    'archives.htmx.html',
    'meetings.htmx.html',
    'members.htmx.html',
    'public.htmx.html',
    'report.htmx.html',
];

$csrfHead = '<?php require_once __DIR__ . \'/../app/Core/Security/CsrfMiddleware.php\'; ?>';
$csrfMeta = '<?= CsrfMiddleware::metaTag() ?>';
$csrfScript = '<?= CsrfMiddleware::jsSnippet() ?>';

$stats = ['patched' => 0, 'skipped' => 0, 'errors' => 0];

foreach ($pages as $page) {
    $path = $publicDir . '/' . $page;
    
    if (!file_exists($path)) {
        echo "[SKIP] $page - fichier non trouvé\n";
        $stats['skipped']++;
        continue;
    }
    
    $content = file_get_contents($path);
    $original = $content;
    $modified = false;
    
    // Vérifie si déjà patché
    if (strpos($content, 'CsrfMiddleware') !== false) {
        echo "[SKIP] $page - déjà patché\n";
        $stats['skipped']++;
        continue;
    }
    
    // 1. Ajouter le require PHP au début (si c'est un fichier HTML sans PHP)
    if (strpos($content, '<?php') === false && strpos($content, '<!doctype') !== false) {
        $content = $csrfHead . "\n" . $content;
        $modified = true;
    }
    
    // 2. Ajouter le meta tag CSRF après <meta charset>
    if (preg_match('/(<meta\s+charset="[^"]+"\s*\/?>)/i', $content, $matches)) {
        $content = str_replace(
            $matches[1],
            $matches[1] . "\n  " . $csrfMeta,
            $content
        );
        $modified = true;
    }
    
    // 3. Ajouter le script CSRF avant le premier <script src=
    if (preg_match('/(<script\s+src="[^"]+"><\/script>)/i', $content, $matches)) {
        $content = preg_replace(
            '/(<script\s+src="[^"]+"><\/script>)/i',
            $csrfScript . "\n$1",
            $content,
            1 // Remplace uniquement la première occurrence
        );
        $modified = true;
    }
    
    if (!$modified) {
        echo "[WARN] $page - impossible de patcher automatiquement\n";
        $stats['errors']++;
        continue;
    }
    
    if ($dryRun) {
        echo "[DRY-RUN] $page serait patché\n";
    } else {
        // Sauvegarde
        copy($path, $path . '.bak');
        file_put_contents($path, $content);
        echo "[OK] $page patché (backup: $page.bak)\n";
    }
    
    $stats['patched']++;
}

echo "\n=== Résumé ===\n";
echo "Patchés: {$stats['patched']}\n";
echo "Ignorés: {$stats['skipped']}\n";
echo "Erreurs: {$stats['errors']}\n";

if ($dryRun) {
    echo "\n(Mode dry-run - aucune modification effectuée)\n";
}
