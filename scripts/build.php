<?php
declare(strict_types=1);

/**
 * KaiMail Production Asset Compiler & Content Hasher
 * Builds minified, content-hashed assets into static/ directory
 * and writes static/manifest.json for enterprise-grade cache busting.
 */

$rootDir = dirname(__DIR__);
$staticDir = $rootDir . DIRECTORY_SEPARATOR . 'static';
$cssOutDir = $staticDir . DIRECTORY_SEPARATOR . 'css';
$jsOutDir = $staticDir . DIRECTORY_SEPARATOR . 'js';

echo "🚀 [KaiMail Builder] Starting Production Asset Compilation...\n";

// 1. Ensure target directories exist
if (!is_dir($cssOutDir)) {
    mkdir($cssOutDir, 0755, true);
}
if (!is_dir($jsOutDir)) {
    mkdir($jsOutDir, 0755, true);
}

// 2. Clean previous build files
foreach (glob($cssOutDir . DIRECTORY_SEPARATOR . '*.css') ?: [] as $oldFile) {
    @unlink($oldFile);
}
foreach (glob($jsOutDir . DIRECTORY_SEPARATOR . '*.js') ?: [] as $oldFile) {
    @unlink($oldFile);
}

// CSS Minifier & Path Rewriter
function minifyCss(string $css): string {
    // Rewrite relative asset URLs: ../assets/ -> ../../assets/
    $css = str_replace('../assets/', '../../assets/', $css);
    // Remove comments
    $css = preg_replace('!/\*[^*]*\*+([^/][^*]*\*+)*/!', '', $css);
    // Remove space after colons, semicolons, braces
    $css = preg_replace('/\s*([\{\};:,>])\s*/', '$1', $css);
    // Remove multiple whitespaces/newlines
    $css = preg_replace('/\s+/', ' ', $css);
    // Trim
    return trim($css);
}

// Safe JS Minifier
function minifyJs(string $js): string {
    // Remove block comments (preserving comments inside strings is typically safe with non-greedy multiline)
    $js = preg_replace('!/\*[\s\S]*?\*/!', '', $js);
    // Remove single-line comments that start at line beginning or after whitespace
    $lines = explode("\n", $js);
    $cleaned = [];
    foreach ($lines as $line) {
        $trimmed = trim($line);
        if ($trimmed === '' || str_starts_with($trimmed, '//')) {
            continue;
        }
        $cleaned[] = $line;
    }
    return implode("\n", $cleaned);
}

$cssTargets = [
    '/css/home.css' => $rootDir . '/css/home.css',
    '/css/admin.css' => $rootDir . '/css/admin.css',
];

$jsTargets = [
    '/js/app.js' => $rootDir . '/js/app.js',
    '/js/longPolling.js' => $rootDir . '/js/longPolling.js',
    '/js/admin.js' => $rootDir . '/js/admin.js',
    '/js/admin-dashboard.js' => $rootDir . '/js/admin-dashboard.js',
    '/js/admin-login.js' => $rootDir . '/js/admin-login.js',
];

$manifest = [];

// 3. Compile CSS
echo "\n📦 Compiling Stylesheets:\n";
foreach ($cssTargets as $logicalPath => $sourcePath) {
    if (!is_file($sourcePath)) {
        echo "   ⚠️ Warning: $sourcePath not found, skipping\n";
        continue;
    }
    $raw = (string) file_get_contents($sourcePath);
    $minified = minifyCss($raw);
    $hash = substr(hash('sha256', $minified), 0, 10);
    $baseName = pathinfo($sourcePath, PATHINFO_FILENAME);
    $hashedName = "{$baseName}.{$hash}.min.css";
    $targetFile = $cssOutDir . DIRECTORY_SEPARATOR . $hashedName;
    file_put_contents($targetFile, $minified);

    $publicHashedPath = "/static/css/{$hashedName}";
    $manifest[$logicalPath] = $publicHashedPath;

    $rawKb = round(strlen($raw) / 1024, 1);
    $minKb = round(strlen($minified) / 1024, 1);
    $saved = round((1 - (strlen($minified) / (strlen($raw) ?: 1))) * 100, 1);
    echo "   ✓ {$logicalPath} -> {$publicHashedPath} ({$rawKb}KB -> {$minKb}KB, -{$saved}%)\n";
}

// 4. Compile JS
echo "\n⚡ Compiling JavaScript:\n";
foreach ($jsTargets as $logicalPath => $sourcePath) {
    if (!is_file($sourcePath)) {
        echo "   ⚠️ Warning: $sourcePath not found, skipping\n";
        continue;
    }
    $raw = (string) file_get_contents($sourcePath);
    $minified = minifyJs($raw);
    $hash = substr(hash('sha256', $minified), 0, 10);
    $baseName = pathinfo($sourcePath, PATHINFO_FILENAME);
    $hashedName = "{$baseName}.{$hash}.min.js";
    $targetFile = $jsOutDir . DIRECTORY_SEPARATOR . $hashedName;
    file_put_contents($targetFile, $minified);

    $publicHashedPath = "/static/js/{$hashedName}";
    $manifest[$logicalPath] = $publicHashedPath;

    $rawKb = round(strlen($raw) / 1024, 1);
    $minKb = round(strlen($minified) / 1024, 1);
    $saved = round((1 - (strlen($minified) / (strlen($raw) ?: 1))) * 100, 1);
    echo "   ✓ {$logicalPath} -> {$publicHashedPath} ({$rawKb}KB -> {$minKb}KB, -{$saved}%)\n";
}

// 5. Write manifest.json
$manifestPath = $staticDir . DIRECTORY_SEPARATOR . 'manifest.json';
file_put_contents($manifestPath, json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

echo "\n📑 Manifest generated at: static/manifest.json\n";
echo "🎉 Build finished successfully!\n";
