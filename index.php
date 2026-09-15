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
$isDocsRoute = (bool) preg_match('#/(?:docs|user_docs)/?$#i', $requestPath) || (($_GET['mode'] ?? '') === 'docs');
$isQrRoute = (bool) preg_match('#/qr/?$#i', $requestPath) || (($_GET['mode'] ?? '') === 'qr');

if ($isDocsRoute) {
    $initialMode = 'docs';
} elseif ($isTwoFaRoute) {
    $initialMode = 'twofa';
} elseif ($isQrRoute) {
    $initialMode = 'qr';
} else {
    $initialMode = 'mail';
}

$siteUrl = rtrim(BASE_URL, '/');
$basePath = parse_url(BASE_URL, PHP_URL_PATH) ?? '';
$basePath = rtrim($basePath, '/');
$pathAfterBase = $requestPath;
if ($basePath !== '' && str_starts_with($pathAfterBase, $basePath)) {
    $pathAfterBase = substr($pathAfterBase, strlen($basePath));
}
$pathAfterBase = trim($pathAfterBase, '/');

$initialEmail = '';
if (!$isTwoFaRoute && !$isDocsRoute && !$isQrRoute) {
    if (str_contains($pathAfterBase, '@')) {
        $candidate = urldecode($pathAfterBase);
        if (!str_contains($candidate, '/') && filter_var($candidate, FILTER_VALIDATE_EMAIL)) {
            $initialEmail = strtolower($candidate);
        }
    } elseif (!empty($_GET['email']) && filter_var($_GET['email'], FILTER_VALIDATE_EMAIL)) {
        $initialEmail = strtolower((string) $_GET['email']);
    }
}

if ($isDocsRoute) {
    $pageUrl = $siteUrl . '/docs';
} elseif ($isTwoFaRoute) {
    $pageUrl = $siteUrl . '/2fa';
} elseif ($isQrRoute) {
    $pageUrl = $siteUrl . '/qr';
} elseif ($initialEmail !== '') {
    $pageUrl = $siteUrl . '/' . rawurlencode($initialEmail);
} else {
    $pageUrl = $siteUrl . '/';
}
$kaishopUrl = 'https://kaishop.id.vn';
$telegramBotUrl = 'https://t.me/KaiHub_bot';

if ($isDocsRoute) {
    $seoTitle = 'Tài Liệu Tích Hợp API - KaiMail | Temp Mail Service';
    $seoDescription = 'Tài liệu hướng dẫn tích hợp API nhận email tạm thời thời gian thực (Temp Mail) của KaiMail dành cho lập trình viên, bot automation và hệ thống bên ngoài.';
    $seoKeywords = 'KaiMail API, Temp Mail API, docs api, tích hợp email tạm thời, api get mail, kaimail docs';
} elseif ($isTwoFaRoute) {
    $seoTitle = 'Trình Xác Thực 2FA | KaiMail';
    $seoDescription = 'Lấy mã OTP 2FA (TOTP) trực tuyến thời gian thực, chuẩn thuật toán RFC 6238, bảo mật 100% trên trình duyệt cho Facebook, Google, Telegram, TikTok, Discord.';
    $seoKeywords = '2fa online, lay ma 2fa, get 2fa otp, totp generator, trinh xac thuc 2fa, kaimail 2fa';
} elseif ($isQrRoute) {
    $seoTitle = 'Tạo Mã QR Code Online Miễn Phí | KaiMail';
    $seoDescription = 'Công cụ tạo mã QR trực tuyến nhanh chóng, tinh gọn, miễn phí cho văn bản, đường dẫn và mã voucher.';
    $seoKeywords = 'tao ma qr, qr code generator, tao qr online, kaimail qr';
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
    <link rel="stylesheet" href="<?= asset_url('/css/docs.css') ?>">
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

        <!-- Main Shell for Mail, 2FA & QR -->
        <main class="user-main <?= $initialMode === 'docs' ? 'hidden' : '' ?>" id="userMain">
            <!-- Mail Mode Content -->
            <?php require __DIR__ . '/includes/views/mail_view.php'; ?>

            <!-- 2FA Mode Content -->
            <?php require __DIR__ . '/includes/views/twofa_view.php'; ?>

            <!-- QR Mode Content -->
            <?php require __DIR__ . '/includes/views/qr_view.php'; ?>
        </main>

        <!-- Docs Mode Content (Direct Child of user-page-container for full 1440px grid) -->
        <div id="docsModeContent" class="<?= $initialMode === 'docs' ? '' : 'hidden' ?>">
            <?php
            $sampleDomain = !empty($activeDomains) ? $activeDomains[0] : 'kaishop.id.vn';
            require __DIR__ . '/includes/views/docs_view.php';
            ?>
        </div>

        <!-- Native Modals (Custom Email & Add Domain) -->
        <?php require __DIR__ . '/includes/views/modals_view.php'; ?>

        <?php UserLayout::renderFooter(); ?>
    </div>

    <script>
        window.KAIMAIL_CONFIG = {
            baseUrl: "<?= BASE_URL ?>",
            userToken: "<?= htmlspecialchars($webUiToken, ENT_QUOTES, 'UTF-8') ?>",
            initialMode: "<?= $initialMode ?>",
            initialEmail: "<?= htmlspecialchars($initialEmail, ENT_QUOTES, 'UTF-8') ?>",
            isTwoFaRoute: <?= $isTwoFaRoute ? 'true' : 'false' ?>,
            isDocsRoute: <?= $isDocsRoute ? 'true' : 'false' ?>,
            isQrRoute: <?= $isQrRoute ? 'true' : 'false' ?>,
            domains: <?= json_encode($activeDomains, JSON_UNESCAPED_UNICODE) ?>
        };
    </script>
    <script src="<?= asset_url('/js/longPolling.js') ?>"></script>
    <script src="<?= asset_url('/js/app.js') ?>"></script>
</body>

</html>