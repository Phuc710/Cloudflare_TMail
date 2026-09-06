<?php
declare(strict_types=1);

/**
 * Admin Stats API.
 * Dispatches to unified StatsController with RBAC permissions.
 */

require_once __DIR__ . '/../../includes/Core/App.php';

use KaiMail\Core\App;
use KaiMail\Core\Controllers\StatsController;

App::run(StatsController::class);
