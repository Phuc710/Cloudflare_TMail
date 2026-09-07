<?php
declare(strict_types=1);

/**
 * KaiMail Custom Domain API Endpoint.
 * Dispatches to unified CustomDomainController.
 */

require_once __DIR__ . '/../includes/Core/App.php';

use KaiMail\Core\App;
use KaiMail\Core\Controllers\CustomDomainController;

App::run(CustomDomainController::class);
