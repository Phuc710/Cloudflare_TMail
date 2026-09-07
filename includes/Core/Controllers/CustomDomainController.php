<?php
declare(strict_types=1);

namespace KaiMail\Core\Controllers;

use KaiMail\Core\Auth\AuthContext;
use KaiMail\Core\Http\ApiException;
use KaiMail\Core\Http\Request;
use KaiMail\Core\Http\Response;
use KaiMail\Core\Services\CustomDomainService;
use KaiMail\Core\Services\DomainService;

/**
 * Custom Domain API Controller.
 * Handles user custom domain onboarding, worker script download, and live activation.
 */
final class CustomDomainController
{
    private CustomDomainService $customDomainService;
    private DomainService $domainService;

    public function __construct(CustomDomainService $customDomainService, DomainService $domainService)
    {
        $this->customDomainService = $customDomainService;
        $this->domainService = $domainService;
    }

    public function handle(Request $request, AuthContext $context): Response
    {
        $action = $request->string('action');
        $siteUrl = rtrim((string) (defined('BASE_URL') ? BASE_URL : ''), '/');
        if ($siteUrl === '') {
            $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https://' : 'http://';
            $siteUrl = $protocol . ($_SERVER['HTTP_HOST'] ?? 'localhost');
        }

        $webhookUrl = $siteUrl . '/api/webhook/receive-email.php';

        // 1. Download Worker Script (.js file)
        if ($action === 'download_worker' && $request->isMethod('GET')) {
            $domainParam = $request->string('domain');
            $domainRecord = $this->customDomainService->find($domainParam);

            if (!$domainRecord) {
                // If not registered yet, setup temporarily
                if ($domainParam !== '') {
                    $domainRecord = $this->customDomainService->setupCustomDomain($domainParam);
                } else {
                    throw ApiException::badRequest('Thiếu thông tin domain');
                }
            }

            $secret = (string) ($domainRecord['webhook_secret'] ?? '');
            $scriptContent = $this->customDomainService->generateWorkerScript($webhookUrl, $secret);

            header('Content-Type: application/javascript; charset=utf-8');
            header('Content-Disposition: attachment; filename="cloudflare-worker.js"');
            header('Cache-Control: no-store, no-cache, must-revalidate');
            echo $scriptContent;
            exit;
        }

        // 2. Setup / Register Custom Domain
        if ($request->isMethod('POST') && ($action === 'setup' || $action === 'create' || $action === '')) {
            $domain = $request->string('domain');

            if ($domain === '') {
                throw ApiException::badRequest('Vui lòng nhập tên miền');
            }

            $result = $this->customDomainService->setupCustomDomain($domain);
            $scriptContent = $this->customDomainService->generateWorkerScript($webhookUrl, $result['webhook_secret']);

            return Response::json([
                'success' => true,
                'domain_id' => $result['id'],
                'domain' => $result['domain'],
                'webhook_url' => $webhookUrl,
                'webhook_secret' => $result['webhook_secret'],
                'verify_token' => $result['verify_token'],
                'is_active' => (bool) $result['is_active'],
                'worker_code' => $scriptContent,
                'download_url' => $siteUrl . '/api/custom-domains.php?action=download_worker&domain=' . urlencode($result['domain']),
            ]);
        }

        // 3. Verify DNS & Activate Domain
        if ($request->isMethod('POST') && $action === 'verify') {
            $domain = $request->string('domain');

            if ($domain === '') {
                throw ApiException::badRequest('Vui lòng cung cấp tên miền cần kiểm tra');
            }

            $record = $this->customDomainService->find($domain);
            if (!$record) {
                // Setup if not exists
                $record = $this->customDomainService->setupCustomDomain($domain);
            }

            $dnsCheck = $this->customDomainService->verifyDns($domain);

            if (!$dnsCheck['valid']) {
                throw ApiException::badRequest($dnsCheck['message'], ['dns_check' => $dnsCheck]);
            }

            // Activate domain ONLY when DNS is valid
            $this->customDomainService->activate((int) $record['id']);

            return Response::json([
                'success' => true,
                'activated' => true,
                'domain' => $record['domain'],
                'dns_check' => $dnsCheck,
                'message' => 'Tên miền đã được kích hoạt thành công! Bạn có thể sử dụng ngay để nhận email.',
            ]);
        }

        // 4. Get Domain Info & Worker Script
        if ($request->isMethod('GET') && $action === 'info') {
            $domain = $request->string('domain');
            if ($domain === '') {
                throw ApiException::badRequest('Thiếu tham số domain');
            }

            $record = $this->customDomainService->find($domain);
            if (!$record) {
                throw ApiException::notFound('Không tìm thấy domain');
            }

            $scriptContent = $this->customDomainService->generateWorkerScript($webhookUrl, (string) $record['webhook_secret']);

            return Response::json([
                'success' => true,
                'domain' => $record['domain'],
                'webhook_url' => $webhookUrl,
                'webhook_secret' => $record['webhook_secret'],
                'is_active' => (bool) $record['is_active'],
                'worker_code' => $scriptContent,
                'download_url' => $siteUrl . '/api/custom-domains.php?action=download_worker&domain=' . urlencode((string) $record['domain']),
            ]);
        }

        throw ApiException::badRequest('Action không hợp lệ');
    }
}
