<?php
require_once __DIR__ . '/config/app.php';
require_once __DIR__ . '/includes/UserLayout.php';

// Disable caching
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Cache-Control: post-check=0, pre-check=0', false);
header('Pragma: no-cache');

if (session_status() !== PHP_SESSION_ACTIVE) {
    if (defined('SESSION_NAME') && SESSION_NAME !== '') {
        session_name((string) SESSION_NAME);
    }

    $https = strtolower((string) ($_SERVER['HTTPS'] ?? ''));
    $isHttps = ($https !== '' && $https !== 'off') || ((string) ($_SERVER['SERVER_PORT'] ?? '')) === '443';

    session_set_cookie_params([
        'lifetime' => defined('SESSION_LIFETIME') ? (int) SESSION_LIFETIME : 86400,
        'path' => defined('SESSION_COOKIE_PATH') ? (string) SESSION_COOKIE_PATH : '/',
        'domain' => defined('SESSION_COOKIE_DOMAIN') ? (string) SESSION_COOKIE_DOMAIN : '',
        'secure' => (defined('SESSION_COOKIE_SECURE') ? (bool) SESSION_COOKIE_SECURE : false) && $isHttps,
        'httponly' => defined('SESSION_COOKIE_HTTP_ONLY') ? (bool) SESSION_COOKIE_HTTP_ONLY : true,
        'samesite' => defined('SESSION_COOKIE_SAMESITE') ? (string) SESSION_COOKIE_SAMESITE : 'Lax',
    ]);

    session_start();
}

if (empty($_SESSION['kaimail_web_ui_token']) || !is_string($_SESSION['kaimail_web_ui_token'])) {
    try {
        $_SESSION['kaimail_web_ui_token'] = bin2hex(random_bytes(32));
    } catch (Throwable $e) {
        $_SESSION['kaimail_web_ui_token'] = hash('sha256', uniqid('km_web_', true));
    }
}

$webUiToken = (string) $_SESSION['kaimail_web_ui_token'];

// Release session lock early to keep API requests responsive.
if (session_status() === PHP_SESSION_ACTIVE) {
    session_write_close();
}

require_once __DIR__ . '/includes/Core/App.php';
$activeDomains = [];
try {
    $activeDomains = \KaiMail\Core\App::getService(\KaiMail\Core\Services\DomainService::class)->listActiveNames();
} catch (\Throwable $e) {
    $activeDomains = ['kaishop.id.vn'];
}
$requestUri = (string) ($_SERVER['REQUEST_URI'] ?? '/');
$requestPath = parse_url($requestUri, PHP_URL_PATH) ?? '/';
$isTwoFaRoute = (bool) preg_match('#/2fa/?$#i', $requestPath) || (($_GET['mode'] ?? '') === 'twofa');
$initialMode = $isTwoFaRoute ? 'twofa' : 'mail';

$siteUrl = rtrim(BASE_URL, '/');
$basePath = parse_url(BASE_URL, PHP_URL_PATH) ?? '';
$basePath = rtrim($basePath, '/');
$pathAfterBase = $requestPath;
if ($basePath !== '' && str_starts_with($pathAfterBase, $basePath)) {
    $pathAfterBase = substr($pathAfterBase, strlen($basePath));
}
$pathAfterBase = trim($pathAfterBase, '/');

$initialEmail = '';
if (!$isTwoFaRoute) {
    if (str_contains($pathAfterBase, '@')) {
        $candidate = urldecode($pathAfterBase);
        if (!str_contains($candidate, '/') && filter_var($candidate, FILTER_VALIDATE_EMAIL)) {
            $initialEmail = strtolower($candidate);
        }
    } elseif (!empty($_GET['email']) && filter_var($_GET['email'], FILTER_VALIDATE_EMAIL)) {
        $initialEmail = strtolower((string) $_GET['email']);
    }
}

if ($isTwoFaRoute) {
    $pageUrl = $siteUrl . '/2fa';
} elseif ($initialEmail !== '') {
    $pageUrl = $siteUrl . '/' . rawurlencode($initialEmail);
} else {
    $pageUrl = $siteUrl . '/';
}
$kaishopUrl = 'https://kaishop.id.vn';
$telegramBotUrl = 'https://t.me/KaiHub_bot';

if ($isTwoFaRoute) {
    $seoTitle = 'Trình Xác Thực 2FA (TOTP) Online Miễn Phí | KaiMail';
    $seoDescription = 'Lấy mã OTP 2FA (TOTP) trực tuyến thời gian thực, chuẩn thuật toán RFC 6238, bảo mật 100% trên trình duyệt cho Facebook, Google, Telegram, TikTok, Discord.';
    $seoKeywords = '2fa online, lay ma 2fa, get 2fa otp, totp generator, trinh xac thuc 2fa, kaimail 2fa';
} else {
    $seoTitle = 'Dịch vụ Temp Mail Free | KaiHub';
    $seoDescription = 'KaiMail là hệ thống Get Mail của KaiShop, hỗ trợ nhận email tạm thời theo thời gian thực để lấy OTP, xác minh tài khoản và kiểm tra luồng đăng ký an toàn.';
    $seoKeywords = 'KaiMail, get mail, email tạm thời, hộp thư tạm thời, nhận OTP, KaiShop, KaiHub';
}
$seoImage = $siteUrl . '/assets/kaishop_favicon.png';
$seoImageAlt = 'KaiMail - Hệ thống Get Mail thuộc KaiShop';
$organizationId = $pageUrl . '#organization';
$websiteId = $pageUrl . '#website';
$webpageId = $pageUrl . '#webpage';
$structuredData = [
    '@context' => 'https://schema.org',
    '@graph' => [
        [
            '@type' => 'Organization',
            '@id' => $organizationId,
            'name' => 'KaiHub',
            'url' => $kaishopUrl,
            'sameAs' => [$kaishopUrl, $telegramBotUrl],
        ],
        [
            '@type' => 'WebSite',
            '@id' => $websiteId,
            'name' => 'KaiMail',
            'url' => $pageUrl,
            'description' => $seoDescription,
            'inLanguage' => 'vi-VN',
            'publisher' => ['@id' => $organizationId],
            'isPartOf' => [
                '@type' => 'WebSite',
                'name' => 'KaiShop',
                'url' => $kaishopUrl,
            ],
        ],
        [
            '@type' => 'WebPage',
            '@id' => $webpageId,
            'name' => $seoTitle,
            'url' => $pageUrl,
            'description' => $seoDescription,
            'inLanguage' => 'vi-VN',
            'isPartOf' => ['@id' => $websiteId],
        ],
    ],
];
?>
<!DOCTYPE html>
<html lang="vi">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($seoTitle, ENT_QUOTES, 'UTF-8') ?></title>
    <meta name="description" content="<?= htmlspecialchars($seoDescription, ENT_QUOTES, 'UTF-8') ?>">
    <meta name="keywords" content="<?= htmlspecialchars($seoKeywords, ENT_QUOTES, 'UTF-8') ?>">
    <meta name="robots" content="index,follow,max-image-preview:large">
    <meta name="author" content="KaiMail">
    <meta name="application-name" content="KaiMail">
    <meta name="apple-mobile-web-app-title" content="KaiMail">
    <meta name="referrer" content="strict-origin-when-cross-origin">
    <meta name="format-detection" content="telephone=no">
    <meta name="theme-color" content="#ffffff">
    <link rel="canonical" href="<?= htmlspecialchars($pageUrl, ENT_QUOTES, 'UTF-8') ?>">

    <meta property="og:locale" content="vi_VN">
    <meta property="og:type" content="website">
    <meta property="og:site_name" content="KaiMail - KaiShop Ecosystem">
    <meta property="og:title" content="<?= htmlspecialchars($seoTitle, ENT_QUOTES, 'UTF-8') ?>">
    <meta property="og:description" content="<?= htmlspecialchars($seoDescription, ENT_QUOTES, 'UTF-8') ?>">
    <meta property="og:url" content="<?= htmlspecialchars($pageUrl, ENT_QUOTES, 'UTF-8') ?>">
    <meta property="og:image" content="<?= htmlspecialchars($seoImage, ENT_QUOTES, 'UTF-8') ?>">
    <meta property="og:image:alt" content="<?= htmlspecialchars($seoImageAlt, ENT_QUOTES, 'UTF-8') ?>">
    <meta property="og:image:type" content="image/png">
    <meta property="og:image:width" content="512">
    <meta property="og:image:height" content="512">

    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:title" content="<?= htmlspecialchars($seoTitle, ENT_QUOTES, 'UTF-8') ?>">
    <meta name="twitter:description" content="<?= htmlspecialchars($seoDescription, ENT_QUOTES, 'UTF-8') ?>">
    <meta name="twitter:image" content="<?= htmlspecialchars($seoImage, ENT_QUOTES, 'UTF-8') ?>">
    <meta name="twitter:image:alt" content="<?= htmlspecialchars($seoImageAlt, ENT_QUOTES, 'UTF-8') ?>">

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&family=JetBrains+Mono:wght@500;700;800&display=swap" rel="stylesheet">
    <link rel="icon" type="image/png" href="<?= BASE_URL ?>/assets/kaishop_favicon.png">
    <link rel="stylesheet" href="<?= asset_url('/css/home.css') ?>">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.all.min.js"></script>
    <script src="https://unpkg.com/@lottiefiles/lottie-player@latest/dist/lottie-player.js"></script>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">
    <script type="application/ld+json">
        <?= json_encode($structuredData, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT) ?>
    </script>
    <script src="https://cdn.jsdelivr.net/npm/otpauth@9.3.1/dist/otpauth.umd.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script>
</head>

<body class="user-page-body">
    <div class="user-page-container">
        <?php UserLayout::renderTopbar($initialMode, true); ?>

        <main class="user-main">
            <!-- Mail Mode Content -->
            <div id="mailModeContent" class="<?= $initialMode === 'mail' ? '' : 'hidden' ?>">
                <section class="compose-shell tmail-panel">
                    <div class="tmail-control-card">
                        <div class="compose-row">
                            <div class="email-input-wrapper">
                                <div class="input-icon-prefix" aria-hidden="true">
                                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                        <path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1-0-2-.9-2-2V6c0-1.1.9-2 2-2z" />
                                        <polyline points="22,6 12,13 2,6" />
                                    </svg>
                                </div>
                                <input type="text" id="emailInput" class="email-input"
                                    value="<?= htmlspecialchars($initialEmail, ENT_QUOTES, 'UTF-8') ?>"
                                    placeholder="Nhập địa chỉ email, ví dụ: name@domain.com" autocomplete="off"
                                    spellcheck="false" title="Nhập hoặc chỉnh sửa địa chỉ email">
                                <button id="emailClearBtn" class="btn-input-clear <?= empty($initialEmail) ? 'hidden' : '' ?>" type="button" title="Xóa nội dung">
                                    <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                        <line x1="18" y1="6" x2="6" y2="18"></line>
                                        <line x1="6" y1="6" x2="18" y2="18"></line>
                                    </svg>
                                </button>
                                <div id="emailSpinner" class="email-spinner-indicator hidden" title="Đang xử lý..."></div>
                            </div>
                            <button id="getMailBtn" class="btn-primary-action" type="button">
                                <span>Get Mail</span>
                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2">
                                    <line x1="5" y1="12" x2="19" y2="12" />
                                    <polyline points="12 5 19 12 12 19" />
                                </svg>
                            </button>
                        </div>

                        <!-- Standard TMail Action Toolbar -->
                        <div class="tmail-toolbar" role="toolbar" aria-label="Thanh công cụ Temp Mail">
                            <button id="copyBtn" class="tmail-tool-btn btn-copy <?= empty($initialEmail) ? 'is-disabled' : '' ?>" type="button" title="<?= empty($initialEmail) ? 'Chưa có email để sao chép' : 'Sao chép địa chỉ email' ?>" <?= empty($initialEmail) ? 'disabled' : '' ?>>
                                <svg class="tool-icon copy-icon" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <rect x="9" y="9" width="13" height="13" rx="2" ry="2" />
                                    <path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1" />
                                </svg>
                                <svg class="tool-icon check-icon hidden" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="#10b981" stroke-width="2.5">
                                    <polyline points="20 6 9 17 4 12" />
                                </svg>
                                <span class="btn-label">Sao chép</span>
                            </button>

                            <button id="randomMailBtn" class="tmail-tool-btn btn-random" type="button" title="Sinh ngay một địa chỉ email ngẫu nhiên mới">
                                <svg class="tool-icon icon-random" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <polyline points="23 4 23 10 17 10" />
                                    <polyline points="1 20 1 14 7 14" />
                                    <path d="M20.49 9A9 9 0 0 0 5.64 5.64L1 10m22 4l-4.64 4.36A9 9 0 0 1 3.51 15" />
                                </svg>
                                <span class="btn-label">Tạo mới</span>
                            </button>

                            <button id="customMailBtn" class="tmail-tool-btn btn-custom" type="button" title="Tùy chỉnh tên email và tên miền theo ý muốn">
                                <svg class="tool-icon icon-custom" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <path d="M12 20h9" />
                                    <path d="M16.5 3.5a2.121 2.121 0 0 1 3 3L7 19l-4 1 1-4L16.5 3.5z" />
                                </svg>
                                <span class="btn-label">Tùy chỉnh</span>
                            </button>

                            <button id="addDomainBtn" class="tmail-tool-btn btn-add-domain" type="button" title="Thêm tên miền riêng của bạn (Cloudflare Email Routing)">
                                <svg class="tool-icon icon-add-domain" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <circle cx="12" cy="12" r="10" />
                                    <line x1="12" y1="8" x2="12" y2="16" />
                                    <line x1="8" y1="12" x2="16" y2="12" />
                                </svg>
                                <span class="btn-label">Thêm Domain</span>
                            </button>

                            <button id="qrMailBtn" class="tmail-tool-btn btn-qr <?= empty($initialEmail) ? 'is-disabled' : '' ?>" type="button" title="<?= empty($initialEmail) ? 'Chưa có email để tạo mã QR' : 'Xem mã QR để quét mở trên điện thoại' ?>" <?= empty($initialEmail) ? 'disabled' : '' ?>>
                                <svg class="tool-icon icon-qr" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <rect x="3" y="3" width="7" height="7" rx="1" />
                                    <rect x="14" y="3" width="7" height="7" rx="1" />
                                    <rect x="14" y="14" width="7" height="7" rx="1" />
                                    <rect x="3" y="14" width="7" height="7" rx="1" />
                                </svg>
                                <span class="btn-label">Mã QR</span>
                            </button>

                            <button id="deleteMailBtn" class="tmail-tool-btn btn-delete <?= empty($initialEmail) ? 'is-disabled' : '' ?>" type="button" title="<?= empty($initialEmail) ? 'Chưa có email để xóa' : 'Xóa hộp thư hiện tại và tạo email mới' ?>" <?= empty($initialEmail) ? 'disabled' : '' ?>>
                                <svg class="tool-icon icon-delete" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <polyline points="3 6 5 6 21 6" />
                                    <path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2" />
                                </svg>
                                <span class="btn-label">Xóa</span>
                            </button>
                        </div>
                    </div>
                </section>

                <section id="inboxSection" class="inbox-section">
                    <div class="inbox-header">
                        <div class="inbox-title-group">
                            <div class="inbox-title-icon">
                                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <polyline points="22 12 16 12 14 15 10 15 8 12 2 12" />
                                    <path d="M5.45 5.11L2 12v6a2 2 0 0 0 2 2h16a2 2 0 0 0 2-2v-6l-3.45-6.89A2 2 0 0 0 16.76 4H7.24a2 2 0 0 0-1.79 1.11z" />
                                </svg>
                            </div>
                            <span class="inbox-title-text">Hộp thư</span>
                            <span id="unreadBadge" class="unread-badge hidden">0</span>
                        </div>
                        <button id="refreshBtn" class="btn-header-refresh <?= empty($initialEmail) ? 'is-disabled' : '' ?>" title="Làm mới hộp thư" type="button" <?= empty($initialEmail) ? 'disabled' : '' ?>>
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <polyline points="23 4 23 10 17 10" />
                                <path d="M20.49 15a9 9 0 1 1-2.12-9.36L23 10" />
                            </svg>
                        </button>
                    </div>

                    <div id="messagesList" class="messages-list"></div>

                    <div id="emptyState" class="empty-state">
                        <svg class="empty-inbox-svg" width="92" height="94" viewBox="0 0 92 87" fill="none" xmlns="http://www.w3.org/2000/svg">
                            <path d="M26 54.37V38.9C26.003 37.125 26.9469 35.4846 28.48 34.59L43.48 25.84C45.027 24.9468 46.933 24.9468 48.48 25.84L63.48 34.59C65.0285 35.4745 65.9887 37.1167 66 38.9V54.37C66 57.1314 63.7614 59.37 61 59.37H31C28.2386 59.37 26 57.1314 26 54.37Z" fill="#8C92A5"></path>
                            <path d="M46 47.7L26.68 36.39C26.2325 37.1579 25.9978 38.0312 26 38.92V54.37C26 57.1314 28.2386 59.37 31 59.37H61C63.7614 59.37 66 57.1314 66 54.37V38.9C66.0022 38.0112 65.7675 37.1379 65.32 36.37L46 47.7Z" fill="#CDCDD8"></path>
                            <path d="M27.8999 58.27C28.7796 58.9758 29.8721 59.3634 30.9999 59.37H60.9999C63.7613 59.37 65.9999 57.1314 65.9999 54.37V38.9C65.9992 38.0287 65.768 37.1731 65.3299 36.42L27.8999 58.27Z" fill="#E5E5F0"></path>
                            <g class="emptyInboxRotation">
                                <path class="emptyInboxRotation" d="M77.8202 29.21L89.5402 25.21C89.9645 25.0678 90.4327 25.1942 90.7277 25.5307C91.0227 25.8673 91.0868 26.348 90.8902 26.75L87.0002 34.62C86.8709 34.8874 86.6407 35.0924 86.3602 35.19C86.0798 35.2806 85.7751 35.2591 85.5102 35.13L77.6502 31.26C77.2436 31.0643 76.9978 30.6401 77.0302 30.19C77.0677 29.7323 77.3808 29.3438 77.8202 29.21Z" fill="#E5E5F0"></path>
                                <path class="emptyInboxRotation" d="M5.12012 40.75C6.36707 20.9791 21.5719 4.92744 41.2463 2.61179C60.9207 0.296147 79.4368 12.3789 85.2401 31.32" stroke="#E5E5F0" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"></path>
                                <path class="emptyInboxRotation" d="M14.18 57.79L2.46001 61.79C2.03313 61.9358 1.56046 61.8088 1.2642 61.4686C0.967927 61.1284 0.906981 60.6428 1.11001 60.24L5.00001 52.38C5.12933 52.1127 5.35954 51.9076 5.64001 51.81C5.92044 51.7194 6.22508 51.7409 6.49001 51.87L14.35 55.74C14.7224 55.9522 14.9394 56.36 14.9073 56.7874C14.8753 57.2149 14.5999 57.5857 14.2 57.74L14.18 57.79Z" fill="#E5E5F0"></path>
                                <path class="emptyInboxRotation" d="M86.9998 45.8C85.9593 65.5282 70.9982 81.709 51.4118 84.2894C31.8254 86.8697 13.1841 75.1156 7.06982 56.33" stroke="#E5E5F0" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"></path>
                            </g>
                        </svg>
                        <div class="empty-state-title">Chưa có thư</div>
                        <div class="empty-state-desc">Đang tự động kiểm tra và nhận thư mới auto</div>
                    </div>
                </section>
            </div>

            <!-- 2FA Mode Content -->
            <div id="twofaModeContent" class="<?= $initialMode === 'twofa' ? '' : 'hidden' ?>">
                <section class="compose-shell">
                    <div class="compose-row">
                        <div class="email-input-wrapper">
                            <div class="input-icon-prefix" aria-hidden="true">
                                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <rect width="18" height="11" x="3" y="11" rx="2" ry="2"/>
                                    <path d="M7 11V7a5 5 0 0 1 10 0v4"/>
                                </svg>
                            </div>
                            <input type="text" id="twofaInput" class="email-input"
                                placeholder="Nhập khóa bí mật" autocomplete="off"
                                spellcheck="false">
                            <button id="twofaClearBtn" class="btn-input-clear" type="button" title="Xóa khóa bí mật" style="display: none;">
                                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <line x1="18" y1="6" x2="6" y2="18"></line>
                                    <line x1="6" y1="6" x2="18" y2="18"></line>
                                </svg>
                            </button>
                        </div>
                        <button id="getOtpBtn" class="btn-primary-action" type="button">
                            <span>Get 2FA</span>
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2">
                                <line x1="5" y1="12" x2="19" y2="12" />
                                <polyline points="12 5 19 12 12 19" />
                            </svg>
                        </button>
                    </div>
                </section>

                <!-- 2FA Main Card -->
                <section id="twofaResultSection" class="inbox-section">
                    <div class="inbox-header">
                        <div class="inbox-title-group">
                            <div class="inbox-title-icon">
                                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <rect width="18" height="11" x="3" y="11" rx="2" ry="2" />
                                    <path d="M7 11V7a5 5 0 0 1 10 0v4" />
                                </svg>
                            </div>
                            <span class="inbox-title-text">Mã xác thực 2FA</span>
                        </div>
                        <button id="copyOtpBtn" class="btn-header-copy is-disabled" type="button" title="Sao chép mã xác thực" disabled>
                            <svg class="copy-icon" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <rect x="9" y="9" width="13" height="13" rx="2" ry="2" />
                                <path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1" />
                            </svg>
                            <svg class="check-icon hidden" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="#10b981" stroke-width="2.5">
                                <polyline points="20 6 9 17 4 12" />
                            </svg>
                        </button>
                    </div>
                    
                    <div class="twofa-card-body">
                        <!-- Senior 6-Digit Segmented OTP Display -->
                        <div class="twofa-otp-stage is-empty" id="otpCodeWrapper" role="button" tabindex="0" title="Nhấp để sao chép mã">
                            <div class="otp-boxes-grid">
                                <!-- 3 số đầu -->
                                <div class="otp-box-group" aria-label="3 số đầu">
                                    <div class="otp-digit-card is-empty"><span class="digit-text" data-digit="0">—</span></div>
                                    <div class="otp-digit-card is-empty"><span class="digit-text" data-digit="1">—</span></div>
                                    <div class="otp-digit-card is-empty"><span class="digit-text" data-digit="2">—</span></div>
                                </div>

                                <!-- Divider -->
                                <div class="otp-box-divider" aria-hidden="true">
                                    <span class="divider-pill"></span>
                                </div>

                                <!-- 3 số sau -->
                                <div class="otp-box-group" aria-label="3 số sau">
                                    <div class="otp-digit-card is-empty"><span class="digit-text" data-digit="3">—</span></div>
                                    <div class="otp-digit-card is-empty"><span class="digit-text" data-digit="4">—</span></div>
                                    <div class="otp-digit-card is-empty"><span class="digit-text" data-digit="5">—</span></div>
                                </div>
                            </div>

                            <!-- Fallback hidden spans for backwards compatibility -->
                            <span id="otpPart1" class="sr-only">···</span>
                            <span id="otpPart2" class="sr-only">···</span>
                        </div>

                        <!-- Progress Bar & Countdown Timer -->
                        <div class="twofa-timer-wrapper">
                            <div class="twofa-progress-bar-bg">
                                <div class="twofa-progress-bar" id="twofaProgress" style="width: 0%;"></div>
                            </div>
                            <span class="twofa-timer-text" id="twofaTimerText">Nhập khóa bí mật để sinh mã</span>
                        </div>
                    </div>

                    <!-- Recent Keys History (Chỉ hiện khi có lịch sử đã lưu) -->
                    <div id="twofaRecentWrapper" class="twofa-recent-section hidden">
                        <div class="twofa-recent-header">
                            <span>Khóa đã dùng gần đây</span>
                            <button id="twofaClearHistoryBtn" class="btn-clear-history" type="button">Xóa lịch sử</button>
                        </div>
                        <div id="twofaRecentList" class="twofa-recent-list"></div>
                    </div>
                </section>
            </div>
        </main>

        <!-- ==========================================
             NATIVE MODAL 1: TÙY CHỈNH EMAIL
             ========================================== -->
        <div id="customEmailModal" class="tmail-modal-overlay" aria-hidden="true">
            <div class="tmail-modal-dialog" role="dialog" aria-modal="true" aria-labelledby="customEmailModalTitle">
                <div class="tmail-modal-header">
                    <h3 id="customEmailModalTitle" class="tmail-modal-title">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#2563eb" stroke-width="2.2"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/><polyline points="22,6 12,13 2,6"/></svg>
                        <span>Tùy chỉnh email</span>
                    </h3>
                    <button type="button" class="tmail-modal-close-btn" id="customEmailCloseBtn" aria-label="Đóng">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
                    </button>
                </div>
                <div class="tmail-modal-body">
                    <div class="tmail-modal-form-group">
                        <label class="tmail-modal-label" for="customEmailPrefixInput">Tên hòm thư mong muốn</label>
                        <div style="display: flex; align-items: stretch; gap: 8px;">
                            <input type="text" id="customEmailPrefixInput" class="tmail-modal-input" placeholder="ví dụ: tester, phuc710" style="flex: 1.2;" autocomplete="off" spellcheck="false">
                            <select id="customEmailDomainSelect" class="tmail-modal-select" style="flex: 1;">
                                <?php foreach ($activeDomains as $d): ?>
                                    <option value="<?= htmlspecialchars($d, ENT_QUOTES, 'UTF-8') ?>">@<?= htmlspecialchars($d, ENT_QUOTES, 'UTF-8') ?></option>
                                <?php endforeach; ?>
                                <option value="__add_custom_domain__" style="font-weight: 700; color: #0284c7;">+ Add Domain ...</option>
                            </select>
                        </div>
                    </div>
                    <div class="tmail-modal-preview" id="customEmailPreviewText">
                        Email sẽ tạo: <span id="customEmailPreviewVal">...</span>
                    </div>
                    <div id="customEmailAlert" class="tmail-modal-alert alert-error" style="display: none; margin-top: 12px;"></div>
                </div>
                <div class="tmail-modal-footer">
                    <button type="button" class="tmail-modal-btn tmail-modal-btn-ghost" id="customEmailCancelBtn">Hủy</button>
                    <button type="button" class="tmail-modal-btn tmail-modal-btn-primary" id="customEmailSubmitBtn">
                        <span>Tạo mới</span>
                    </button>
                </div>
            </div>
        </div>

        <!-- ==========================================
             NATIVE MODAL 2: THÊM TÊN (CLOUDFLARE)
             ========================================== -->
        <div id="addDomainModal" class="tmail-modal-overlay" aria-hidden="true">
            <div class="tmail-modal-dialog modal-dialog-wide" role="dialog" aria-modal="true" aria-labelledby="addDomainModalTitle">
                <div class="tmail-modal-header">
                    <h3 id="addDomainModalTitle" class="tmail-modal-title">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#0284c7" stroke-width="2.2"><circle cx="12" cy="12" r="10"/><line x1="2" y1="12" x2="22" y2="12"/><path d="M12 2a15.3 15.3 0 0 1 4 10 15.3 15.3 0 0 1-4 10 15.3 15.3 0 0 1-4-10 15.3 15.3 0 0 1 4-10z"/></svg>
                        <span id="addDomainTitleText">Thêm tên miền riêng (Cloudflare)</span>
                    </h3>
                    <button type="button" class="tmail-modal-close-btn" id="addDomainCloseBtn" aria-label="Đóng">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
                    </button>
                </div>
                
                <!-- STEP 1: NHẬP TÊN MIỀN -->
                <div id="addDomainStep1" class="tmail-modal-body">
                    <p style="font-size: 13.5px; color: #475569; line-height: 1.6; margin: 0 0 14px 0;">
                        Sử dụng tên miền riêng của bạn qua <strong>Cloudflare Email Routing</strong> để nhận email không giới hạn.
                    </p>
                    <div class="tmail-modal-form-group">
                        <label class="tmail-modal-label" for="addDomainInputVal">Tên miền của bạn (Domain / Subdomain)</label>
                        <input type="text" id="addDomainInputVal" class="tmail-modal-input" placeholder="ví dụ: devmail.vn, dewii.dpdns.org" autocomplete="off" spellcheck="false" style="font-family: 'JetBrains Mono', monospace;">
                    </div>
                    <div id="addDomainStep1Alert" class="tmail-modal-alert alert-error" style="display: none;"></div>
                </div>
                <div id="addDomainStep1Footer" class="tmail-modal-footer">
                    <button type="button" class="tmail-modal-btn tmail-modal-btn-ghost" id="addDomainStep1CancelBtn">Đóng</button>
                    <button type="button" class="tmail-modal-btn tmail-modal-btn-accent" id="addDomainStep1NextBtn">
                        <span>Tiếp tục &rarr;</span>
                    </button>
                </div>

                <!-- STEP 2: CẤU HÌNH CLOUDFLARE -->
                <div id="addDomainStep2" class="tmail-modal-body" style="display: none;">
                    <div style="display: flex; align-items: center; justify-content: space-between; background: #f0f9ff; border: 1.5px solid #bae6fd; border-radius: 12px; padding: 12px 16px; margin-bottom: 14px; gap: 12px; flex-wrap: wrap;">
                        <div>
                            <span style="font-size: 11px; font-weight: 700; color: #0369a1; text-transform: uppercase; letter-spacing: 0.5px;">Tên miền của bạn:</span>
                            <div id="addDomainStep2DomainBadge" style="font-family: 'JetBrains Mono', monospace; font-weight: 700; color: #0f172a; font-size: 15px;">@...</div>
                        </div>
                        <div style="display: flex; gap: 8px; flex-wrap: wrap;">
                            <button type="button" id="addDomainCopyWorkerBtn" class="tmail-modal-btn tmail-modal-btn-ghost" style="padding: 7px 12px; font-size: 13px;">
                                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="9" y="9" width="13" height="13" rx="2" ry="2"/><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"/></svg>
                                <span>Sao chép Code</span>
                            </button>
                            <a href="#" id="addDomainDownloadWorkerLink" download="cloudflare-worker.js" class="tmail-modal-btn tmail-modal-btn-accent" style="padding: 7px 14px; font-size: 13px; text-decoration: none;">
                                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
                                <span>Tải File worker.js</span>
                            </a>
                        </div>
                    </div>

                    <div class="tmail-modal-alert alert-warning" style="margin-bottom: 12px;">
                        <div>⚡ <em>Do Cloudflare không cho chọn Worker giữa các tài khoản khác nhau, bạn chỉ cần tạo 1 Worker trên Cloudflare của bạn và dán code đã cấp sẵn ở trên.</em></div>
                    </div>

                    <div style="background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 12px; padding: 14px; margin-bottom: 12px;">
                        <div style="margin-bottom: 12px; padding-bottom: 10px; border-bottom: 1px dashed #e2e8f0;">
                            <div style="font-weight: 700; color: #0f172a; font-size: 13.5px; margin-bottom: 4px;">
                                Bước 1: Tạo Worker trên tài khoản Cloudflare của bạn
                            </div>
                            <ol style="margin: 0; padding-left: 18px; color: #475569; font-size: 13px; line-height: 1.6;">
                                <li>Vào <a href="https://dash.cloudflare.com/?to=/:account/workers-and-pages" target="_blank" rel="noopener" style="color: #0284c7; font-weight: 600; text-decoration: underline;">Workers & Pages</a> &rarr; Bấm <strong>Create Application</strong> &rarr; <strong>Create Worker</strong> (ví dụ đặt tên: <code>v-bridge</code>) &rarr; Nhấn <strong>Deploy</strong>.</li>
                                <li>Bấm <strong>Edit Code</strong> &rarr; Xóa hết code mẫu &rarr; Bấm nút <strong>"Sao chép Code"</strong> ở trên dán vào &rarr; Nhấn <strong>Deploy</strong>.</li>
                            </ol>
                        </div>

                        <div>
                            <div style="font-weight: 700; color: #0f172a; font-size: 13.5px; margin-bottom: 4px;">
                                Bước 2: Bật Email Routing & Trỏ Catch-all vào Worker
                            </div>
                            <ol style="margin: 0; padding-left: 18px; color: #475569; font-size: 13px; line-height: 1.6;">
                                <li>Truy cập <a href="https://dash.cloudflare.com/?to=/:account/email-service/routing" target="_blank" rel="noopener" style="color: #0284c7; font-weight: 600; text-decoration: underline;">Email Routing</a> tại domain của bạn &rarr; Bấm <strong>Enable Email Routing</strong> để Cloudflare tự thêm bản ghi DNS MX & SPF (Locked xanh).</li>
                                <li>Chuyển sang tab <strong>Routing rules</strong> &rarr; Tại dòng <strong>Catch-all</strong>, nhìn sang bên phải bấm nút <code>...</code> (3 dấu chấm) &rarr; chọn <strong>Edit</strong>.</li>
                                <li>Sửa các trường:
                                    <div style="background: #ffffff; border: 1px solid #cbd5e1; border-radius: 8px; padding: 6px 10px; margin: 6px 0; font-size: 12.5px; font-family: monospace;">
                                        <div>&bull; Action: <strong>Send to a Worker</strong></div>
                                        <div>&bull; Destination: <strong>v-bridge</strong> (Worker vừa tạo ở Bước 1)</div>
                                        <div>&bull; Status: <strong>Active (Bật xanh)</strong></div>
                                    </div>
                                </li>
                                <li>Bấm <strong>Save</strong>. Khi thấy dòng Catch-all hiển thị chip tên Worker là hoàn tất!</li>
                            </ol>
                        </div>
                    </div>

                    <div id="addDomainStep2Alert" class="tmail-modal-alert alert-error" style="display: none;"></div>
                </div>
                <div id="addDomainStep2Footer" class="tmail-modal-footer" style="display: none;">
                    <button type="button" class="tmail-modal-btn tmail-modal-btn-ghost" id="addDomainStep2BackBtn">Để sau</button>
                    <button type="button" class="tmail-modal-btn tmail-modal-btn-emerald" id="addDomainStep2VerifyBtn">
                        <span>🚀 Kiểm Tra Ngay</span>
                    </button>
                </div>
            </div>
        </div>

        <?php UserLayout::renderFooter(); ?>
    </div>

    <script>
        window.KAIMAIL_CONFIG = {
            baseUrl: "<?= BASE_URL ?>",
            userToken: "<?= htmlspecialchars($webUiToken, ENT_QUOTES, 'UTF-8') ?>",
            initialMode: "<?= $initialMode ?>",
            initialEmail: "<?= htmlspecialchars($initialEmail, ENT_QUOTES, 'UTF-8') ?>",
            isTwoFaRoute: <?= $isTwoFaRoute ? 'true' : 'false' ?>,
            domains: <?= json_encode($activeDomains, JSON_UNESCAPED_UNICODE) ?>
        };
    </script>
    <script src="<?= asset_url('/js/longPolling.js') ?>"></script>
    <script src="<?= asset_url('/js/app.js') ?>"></script>
</body>

</html>