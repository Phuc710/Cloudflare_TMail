<?php
declare(strict_types=1);

/**
 * Admin Authentication API.
 * Dispatches to unified AuthController.
 */

require_once __DIR__ . '/../../includes/Core/App.php';

use KaiMail\Core\App;
use KaiMail\Core\Controllers\AuthController;

App::run(AuthController::class);
