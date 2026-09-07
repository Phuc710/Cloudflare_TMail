<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../includes/Auth.php';

if (Auth::isLoggedIn()) {
    header('Location: ' . BASE_URL . '/adminkaishop');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $raw = (string) file_get_contents('php://input');
    $data = json_decode($raw, true);
    if (!is_array($data)) {
        $data = $_POST;
    }

    $stealthToken = (string) ($data['stealth_token'] ?? $data['token'] ?? '');
    if ($stealthToken !== '') {
        $expected = hash_hmac('sha256', 'stealth_click_' . date('Y-m-d'), (string) ADMIN_ACCESS_KEY);
        if (hash_equals($expected, $stealthToken)) {
            Auth::login((string) ADMIN_ACCESS_KEY);
            header('Content-Type: application/json; charset=UTF-8');
            echo json_encode([
                'ok' => true,
                'redirect' => BASE_URL . '/adminkaishop',
            ]);
            exit;
        }

        header('Content-Type: application/json; charset=UTF-8', true, 401);
        echo json_encode([
            'ok' => false,
            'message' => 'Token không hợp lệ',
        ]);
        exit;
    }
}

require_once __DIR__ . '/../includes/AdminLoginPage.php';

$page = new AdminLoginPage(BASE_URL);
$page->render();
