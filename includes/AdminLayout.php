<?php
declare(strict_types=1);

/**
 * Shared layout renderer for admin pages.
 */
final class AdminLayout
{
    /**
     * @var array<int, array{key: string, label: string, path: string, icon: string}>
     */
    private const NAV_ITEMS = [
        [
            'key' => 'emails',
            'label' => 'Quản lý email',
            'path' => '/adminkaishop',
            'icon' => '<path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z" /><polyline points="22,6 12,13 2,6" />',
        ],
        [
            'key' => 'docs-domain',
            'label' => 'Hướng dẫn domain',
            'path' => '/adminkaishop/docs-domain',
            'icon' => '<path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z" /><polyline points="14 2 14 8 20 8" /><line x1="12" y1="18" x2="12" y2="12" /><line x1="9" y1="15" x2="15" y2="15" />',
        ],
        [
            'key' => 'docs-api',
            'label' => 'Tài liệu API',
            'path' => '/adminkaishop/docs-api',
            'icon' => '<polyline points="16 18 22 12 16 6" /><polyline points="8 6 2 12 8 18" />',
        ],
    ];

    public static function begin(string $title, string $activePage, string $username): void
    {
        if (!headers_sent()) {
            header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
            header('Pragma: no-cache');
        }

        $safeTitle = htmlspecialchars($title, ENT_QUOTES, 'UTF-8');
        $safeUsername = htmlspecialchars($username, ENT_QUOTES, 'UTF-8');
        $baseUrl = htmlspecialchars(BASE_URL, ENT_QUOTES, 'UTF-8');
        $adminCssHref = htmlspecialchars(self::buildVersionedAssetUrl('/css/admin.css'), ENT_QUOTES, 'UTF-8');

        echo <<<HTML
<!DOCTYPE html>
<html lang="vi" data-base-url="{$baseUrl}">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{$safeTitle} - KaiMail Admin</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=JetBrains+Mono:wght@400;500;600&family=Roboto:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="icon" type="image/png" href="{$baseUrl}/assets/kaishop_favicon.png">
    <link rel="shortcut icon" type="image/png" href="{$baseUrl}/assets/kaishop_favicon.png">
    <link rel="stylesheet" href="{$adminCssHref}">
</head>
<body>
    <div id="toast" class="toast"></div>
    <button type="button" id="mobileMenuBtn" class="mobile-menu-btn" aria-label="Mở menu">
        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <line x1="3" y1="6" x2="21" y2="6"></line>
            <line x1="3" y1="12" x2="21" y2="12"></line>
            <line x1="3" y1="18" x2="21" y2="18"></line>
        </svg>
    </button>
    <div id="sidebarOverlay" class="sidebar-overlay"></div>
    <aside class="sidebar" id="adminSidebar">
        <div class="sidebar-header">
            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"></path>
                <polyline points="22,6 12,13 2,6"></polyline>
            </svg>
            <span>KaiMail Admin</span>
        </div>
        <nav class="sidebar-nav">
HTML;

        foreach (self::NAV_ITEMS as $item) {
            $isActive = $item['key'] === $activePage;
            $safePath = htmlspecialchars($item['path'], ENT_QUOTES, 'UTF-8');
            $safeLabel = htmlspecialchars($item['label'], ENT_QUOTES, 'UTF-8');
            $className = $isActive ? 'nav-item active' : 'nav-item';

            echo <<<HTML
            <a href="{$baseUrl}{$safePath}" class="{$className}" data-page="{$item['key']}">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">{$item['icon']}</svg>
                <span>{$safeLabel}</span>
            </a>
HTML;
        }

        echo <<<HTML
        </nav>
        <div class="sidebar-footer">
            <div class="admin-info">
                <span>{$safeUsername}</span>
            </div>
            <button id="logoutBtn" class="btn-logout" title="Đăng xuất" aria-label="Đăng xuất">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"></path>
                    <polyline points="16 17 21 12 16 7"></polyline>
                    <line x1="21" y1="12" x2="9" y2="12"></line>
                </svg>
            </button>
        </div>
    </aside>
    <main class="main-content">
HTML;
    }

    /**
     * @param string[] $extraScripts
     */
    public static function end(array $extraScripts = []): void
    {
        $baseUrl = htmlspecialchars(BASE_URL, ENT_QUOTES, 'UTF-8');
        echo '</main>';
        echo '<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>';
        $adminJsHref = htmlspecialchars(self::buildVersionedAssetUrl('/js/admin.js'), ENT_QUOTES, 'UTF-8');
        echo '<script src="' . $adminJsHref . '"></script>';

        foreach ($extraScripts as $scriptPath) {
            $scriptPath = trim($scriptPath);
            if ($scriptPath === '') {
                continue;
            }

            if (str_starts_with($scriptPath, 'http://') || str_starts_with($scriptPath, 'https://')) {
                $safePath = htmlspecialchars($scriptPath, ENT_QUOTES, 'UTF-8');
            } else {
                $safePath = htmlspecialchars(self::buildVersionedAssetUrl($scriptPath), ENT_QUOTES, 'UTF-8');
            }

            echo '<script src="' . $safePath . '"></script>';
        }

        echo '</body></html>';
    }

    private static function buildVersionedAssetUrl(string $assetPath): string
    {
        return \KaiMail\Core\Services\AssetService::url($assetPath);
    }
}
