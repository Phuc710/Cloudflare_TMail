<?php
declare(strict_types=1);

/**
 * Fast Email Checker API.
 * Dispatches to unified CheckerController with RBAC permissions.
 */

require_once __DIR__ . '/../../includes/Core/App.php';

use KaiMail\Core\App;
use KaiMail\Core\Controllers\CheckerController;

App::run(CheckerController::class);
