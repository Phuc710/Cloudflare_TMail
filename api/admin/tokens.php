<?php
declare(strict_types=1);

/**
 * Admin API Tokens Management API.
 * Dispatches to TokenController with RBAC permissions.
 */

require_once __DIR__ . '/../../includes/Core/App.php';

use KaiMail\Core\App;
use KaiMail\Core\Controllers\TokenController;

App::run(TokenController::class);
