<?php
declare(strict_types=1);

namespace KaiMail\Core\Controllers;

use KaiMail\Core\Auth\AuthContext;
use KaiMail\Core\Auth\Gate;
use KaiMail\Core\Auth\Permission;
use KaiMail\Core\Http\ApiException;
use KaiMail\Core\Http\Request;
use KaiMail\Core\Http\Response;
use KaiMail\Core\Services\DomainService;

/**
 * Domain Management Controller for Admin.
 */
final class DomainController
{
    private DomainService $service;

    public function __construct(DomainService $service)
    {
        $this->service = $service;
    }

    public function handle(Request $request, AuthContext $context): Response
    {
        if ($request->getMethod() === 'GET') {
            if (Gate::allows($context, Permission::DOMAIN_MANAGE)) {
                return $this->handleGet(false);
            }
            Gate::authorize($context, Permission::DOMAIN_LIST_ACTIVE);
            return $this->handleGet(true);
        }

        Gate::authorize($context, Permission::DOMAIN_MANAGE);

        return match ($request->getMethod()) {
            'POST' => $this->handlePost($request),
            'PUT' => $this->handlePut($request),
            'DELETE' => $this->handleDelete($request),
            default => throw ApiException::methodNotAllowed(),
        };
    }

    private function handleGet(bool $activeOnly = false): Response
    {
        if ($activeOnly) {
            $domains = $this->service->listActiveNames();
            return Response::json([
                'success' => true,
                'domains' => $domains,
            ]);
        }

        $domains = $this->service->listAll();
        return Response::json([
            'success' => true,
            'domains' => $domains,
        ]);
    }

    private function handlePost(Request $request): Response
    {
        $domain = $request->string('domain');
        $isActive = $request->int('is_active', 1);

        $id = $this->service->create($domain, $isActive);

        return Response::json([
            'success' => true,
            'id' => $id,
            'domain' => strtolower($domain),
            'is_active' => $isActive,
        ], 201);
    }

    private function handlePut(Request $request): Response
    {
        $id = $request->int('id');
        $isActive = $request->input('is_active') !== null ? $request->int('is_active') : null;

        $this->service->update($id, $isActive);
        return Response::success();
    }

    private function handleDelete(Request $request): Response
    {
        $id = $request->int('id');
        $this->service->delete($id);
        return Response::success();
    }
}
