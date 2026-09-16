<?php
declare(strict_types=1);

/**
 * Admin QR Presets API.
 * Dispatches to QrPresetController with RBAC permissions.
 */

require_once __DIR__ . '/../../includes/Core/App.php';

use KaiMail\Core\App;
use KaiMail\Core\Controllers\QrPresetController;

App::run(QrPresetController::class);
