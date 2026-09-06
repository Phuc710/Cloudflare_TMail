<?php
declare(strict_types=1);

/**
 * Cloudflare Email Routing Webhook Ingestion API.
 * Dispatches to unified WebhookController with RBAC permissions.
 */

require_once __DIR__ . '/../../includes/Core/App.php';

use KaiMail\Core\App;
use KaiMail\Core\Controllers\WebhookController;

App::run(WebhookController::class);
