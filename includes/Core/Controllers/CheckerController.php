<?php
declare(strict_types=1);

namespace KaiMail\Core\Controllers;

use KaiMail\Core\Auth\AuthContext;
use KaiMail\Core\Auth\Gate;
use KaiMail\Core\Auth\Permission;
use KaiMail\Core\Http\ApiException;
use KaiMail\Core\Http\Request;
use KaiMail\Core\Http\Response;
use KaiMail\Core\Services\CheckerService;

/**
 * Fast Email Content Checker Controller for Admin.
 */
final class CheckerController
{
    private CheckerService $service;

    public function __construct(CheckerService $service)
    {
        $this->service = $service;
    }

    public function handle(Request $request, AuthContext $context): Response
    {
        Gate::authorize($context, Permission::CHECKER_RUN);

        if (!$request->isMethod('GET')) {
            throw ApiException::methodNotAllowed();
        }

        $keyword = $request->string('keyword', 'deactivating');
        $domain = $request->string('domain', '');
        $limit = $request->int('limit', 100);
        $days = $request->int('days', 7);

        $results = $this->service->search($keyword, $days, $limit, $domain);
        return Response::json(array_merge(['success' => true], $results));
    }
}
