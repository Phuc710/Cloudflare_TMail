<?php
declare(strict_types=1);

/**
 * Admin Domains API.
 * Dispatches to unified DomainController with RBAC permissions.
 */

require_once __DIR__ . '/../../includes/Core/App.php';

use KaiMail\Core\App;
use KaiMail\Core\Controllers\DomainController;

App::run(DomainController::class);
