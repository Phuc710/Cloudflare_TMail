<?php
declare(strict_types=1);

final class AdminLoginPage
{
    private const ADMIN_AUTH_API_PATH = '/api/admin/auth.php';
    private const ADMIN_DASHBOARD_PATH = '/adminkaishop';
    private const ADMIN_STORAGE_KEY = 'kaimail_admin_access_key';

    public function __construct(private readonly string $baseUrl)
    {
    }

    public function render(): void
    {
        header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
        header('Pragma: no-cache');

        $baseUrl = $this->escape($this->baseUrl);
        $authEndpoint = $this->escape(self::ADMIN_AUTH_API_PATH);
        $dashboardPath = $this->escape(self::ADMIN_DASHBOARD_PATH);
        $storageKey = $this->escape(self::ADMIN_STORAGE_KEY);
        $adminCssHref = $this->escape($this->buildVersionedAssetUrl('/css/admin.css'));
        $adminLoginJsHref = $this->escape($this->buildVersionedAssetUrl('/js/admin-login.js'));

        echo <<<HTML
<!DOCTYPE html>
<html lang="vi" data-base-url="{$baseUrl}">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Đăng nhập Admin - KaiMail</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@400;500;700&display=swap" rel="stylesheet">
    <link rel="icon" type="image/png" href="{$baseUrl}/assets/kaishop_favicon.png">
    <link rel="shortcut icon" type="image/png" href="{$baseUrl}/assets/kaishop_favicon.png">
    <link rel="stylesheet" href="{$adminCssHref}">
</head>
<body class="login-page" data-auth-endpoint="{$authEndpoint}" data-admin-home="{$dashboardPath}" data-storage-key="{$storageKey}">
    <div class="login-container">
        <div class="login-box">
            <div class="login-header">
                <h1>KaiMail Admin</h1>
                <p class="field-note">Đăng nhập khu vực dashboard admin.</p>
            </div>

            <form id="loginForm" novalidate>
                <div class="form-group">
                    <label for="passwordInput">Khóa truy cập</label>
                    <input
                        type="password"
                        id="passwordInput"
                        name="password"
                        required
                        autocomplete="current-password"
                        placeholder="Nhập khóa truy cập..."
                    >
                </div>

                <div id="loginStatus" class="status-message hidden" aria-live="polite"></div>
                <div id="errorMsg" class="error-message hidden" aria-live="assertive"></div>

                <button type="submit" id="loginSubmitBtn" class="btn-login">
                    <span id="loginSubmitText">Đăng nhập</span>
                </button>
            </form>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script src="{$adminLoginJsHref}" defer></script>
</body>
</html>
HTML;
    }

    private function escape(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
    }

    private function buildVersionedAssetUrl(string $assetPath): string
    {
        return \KaiMail\Core\Services\AssetService::url($assetPath);
    }
}
