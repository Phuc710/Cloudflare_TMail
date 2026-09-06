<?php
declare(strict_types=1);

/**
 * Admin System Long Polling API.
 * Dispatches to unified LongPollController with RBAC permissions.
 */

require_once __DIR__ . '/../../includes/Core/App.php';

use KaiMail\Core\App;
use KaiMail\Core\Controllers\LongPollController;

App::run(LongPollController::class);
