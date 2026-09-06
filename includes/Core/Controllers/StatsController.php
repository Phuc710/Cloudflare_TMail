<?php
declare(strict_types=1);

namespace KaiMail\Core\Controllers;

use KaiMail\Core\Auth\AuthContext;
use KaiMail\Core\Auth\Gate;
use KaiMail\Core\Auth\Permission;
use KaiMail\Core\Http\ApiException;
use KaiMail\Core\Http\Request;
use KaiMail\Core\Http\Response;
use KaiMail\Core\Services\StatsService;

/**
 * Dashboard & System Statistics Controller.
 */
final class StatsController
{
    private StatsService $service;

    public function __construct(StatsService $service)
    {
        $this->service = $service;
    }

    public function handle(Request $request, AuthContext $context): Response
    {
        Gate::authorize($context, Permission::STATS_VIEW);

        if (!$request->isMethod('GET')) {
            throw ApiException::methodNotAllowed();
        }

        $stats = $this->service->getDashboardStats();
        return Response::json($stats);
    }
}
