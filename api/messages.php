<?php
declare(strict_types=1);

/**
 * Public & External Messages API.
 * Dispatches to unified MessageController with RBAC permissions.
 */

require_once __DIR__ . '/../includes/Core/App.php';

use KaiMail\Core\App;
use KaiMail\Core\Controllers\MessageController;

App::run(MessageController::class);
