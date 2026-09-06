<?php
declare(strict_types=1);

/**
 * KaiMail RESTful API Router.
 * Maps REST paths (/api/emails, /api/emails/{email}/messages) into unified Core Controllers.
 */

require_once __DIR__ . '/../includes/Core/App.php';

use KaiMail\Core\App;
use KaiMail\Core\Controllers\EmailController;
use KaiMail\Core\Controllers\MessageController;
use KaiMail\Core\Http\ApiException;
use KaiMail\Core\Http\Request;
use KaiMail\Core\Http\Response;

App::boot();

$request = Request::capture();

// Base info endpoint
$requestUri = (string) ($_SERVER['REQUEST_URI'] ?? '/');
$path = (string) parse_url($requestUri, PHP_URL_PATH);
$path = trim((string) preg_replace('#^.*?/api#i', '', $path), '/');
$segments = $path !== '' ? explode('/', $path) : [];

if (empty($segments)) {
    if ($request->isMethod('GET')) {
        Response::json([
            'name' => 'KaiMail Unified API',
            'version' => '3.0',
            'endpoints' => [
                'POST /api/emails' => 'Tạo email mới (1-10 email cho Bot, tối đa 50 cho Admin)',
                'GET /api/emails/{email}/messages' => 'Lấy danh sách tin nhắn của email',
                'GET /api/messages.php?email={email}' => 'Danh sách tin nhắn (Direct)',
                'GET /api/long-poll.php' => 'Long polling nhận tin nhắn mới realtime',
            ],
        ])->setCors($request, BASE_URL)->send();
    } else {
        Response::error('Method not allowed', 405)->send();
    }
}

// REST route: /api/emails
if ($segments[0] === 'emails') {
    // GET /api/emails/{email}/messages
    if (count($segments) === 3 && $segments[2] === 'messages' && $request->isMethod('GET')) {
        $_GET['email'] = urldecode($segments[1]);
        App::run(MessageController::class);
        exit;
    }

    // POST /api/emails
    if (count($segments) === 1 && $request->isMethod('POST')) {
        App::run(EmailController::class);
        exit;
    }
}

// REST route: /api/messages
if ($segments[0] === 'messages') {
    App::run(MessageController::class);
    exit;
}

// No route matched
Response::error('Not found', 404, 'NotFound', [
    'path' => '/' . $path,
    'method' => $request->getMethod(),
])->setCors($request, BASE_URL)->send();
