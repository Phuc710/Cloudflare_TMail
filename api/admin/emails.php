<?php
declare(strict_types=1);

/**
 * Admin Emails API.
 * Dispatches to unified EmailController with RBAC permissions.
 */

require_once __DIR__ . '/../../includes/Core/App.php';

use KaiMail\Core\App;
use KaiMail\Core\Controllers\EmailController;

App::run(EmailController::class);
