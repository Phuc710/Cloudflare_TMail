<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/Auth.php';
Auth::requireLogin();

require_once __DIR__ . '/../includes/AdminLayout.php';
require_once __DIR__ . '/../includes/Core/App.php';

use KaiMail\Core\App;
use KaiMail\Core\Services\DomainService;

$admin = ['username' => 'admin'];
$domains = [];
$stats = [
    'total' => 0,
    'active' => 0,
    'inactive' => 0,
    'total_emails' => 0,
];

try {
    /** @var DomainService $domainService */
    $domainService = App::getService(DomainService::class);
    $domains = $domainService->listAll();

    $stats['total'] = count($domains);
    foreach ($domains as $d) {
        $isActive = (int) ($d['is_active'] ?? 0) === 1;
        if ($isActive) {
            $stats['active']++;
        } else {
            $stats['inactive']++;
        }
        $stats['total_emails'] += (int) ($d['email_count'] ?? 0);
    }
} catch (Throwable $e) {
    error_log('Admin managerdomain: load failed - ' . $e->getMessage());
}

AdminLayout::begin('Quản lý domain', 'managerdomain', (string) ($admin['username'] ?? 'admin'));
?>
<header class="page-header">
    <div class="page-header-title">
        <h1>Quản lý domain</h1>
        <p>Quản lý các tên miền tiếp nhận email, kiểm soát trạng thái hoạt động và cấu hình Cloudflare Email Routing.</p>
    </div>
    <div class="page-actions">
        <a href="<?= htmlspecialchars(BASE_URL, ENT_QUOTES, 'UTF-8') ?>/adminkaishop/docs-domain" class="btn secondary">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path>
                <polyline points="14 2 14 8 20 8"></polyline>
                <line x1="12" y1="18" x2="12" y2="12"></line>
                <line x1="9" y1="15" x2="15" y2="15"></line>
            </svg>
            <span>Hướng dẫn domain</span>
        </a>
        <button id="addDomainBtn" class="btn primary" type="button" data-modal-open="addDomainModal">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <line x1="12" y1="5" x2="12" y2="19"></line>
                <line x1="5" y1="12" x2="19" y2="12"></line>
            </svg>
            <span>Thêm domain</span>
        </button>
    </div>
</header>

<section class="stats-grid-4">
    <article class="stat-card stat-total">
        <div class="stat-icon">
            <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <circle cx="12" cy="12" r="10"></circle>
                <line x1="2" y1="12" x2="22" y2="12"></line>
                <path d="M12 2a15.3 15.3 0 0 1 4 10 15.3 15.3 0 0 1-4 10 15.3 15.3 0 0 1-4-10z"></path>
            </svg>
        </div>
        <div class="stat-info">
            <span class="stat-value" id="statTotalDomains"><?= number_format($stats['total']) ?></span>
            <span class="stat-label">Tổng tên miền</span>
        </div>
    </article>

    <article class="stat-card stat-admin">
        <div class="stat-icon" style="background: #ecfdf5; color: #059669;">
            <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path>
                <polyline points="22 4 12 14.01 9 11.01"></polyline>
            </svg>
        </div>
        <div class="stat-info">
            <span class="stat-value" id="statActiveDomains"><?= number_format($stats['active']) ?></span>
            <span class="stat-label">Đang hoạt động</span>
        </div>
    </article>

    <article class="stat-card stat-user">
        <div class="stat-icon" style="background: #fef2f2; color: #dc2626;">
            <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <circle cx="12" cy="12" r="10"></circle>
                <line x1="15" y1="9" x2="9" y2="15"></line>
                <line x1="9" y1="9" x2="15" y2="15"></line>
            </svg>
        </div>
        <div class="stat-info">
            <span class="stat-value" id="statInactiveDomains"><?= number_format($stats['inactive']) ?></span>
            <span class="stat-label">Tạm tắt</span>
        </div>
    </article>

    <article class="stat-card stat-api">
        <div class="stat-icon" style="background: #eff6ff; color: #2563eb;">
            <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1-0-2-.9-2-2V6c0-1.1.9-2 2-2z"></path>
                <polyline points="22,6 12,13 2,6"></polyline>
            </svg>
        </div>
        <div class="stat-info">
            <span class="stat-value" id="statTotalEmailsLinked"><?= number_format($stats['total_emails']) ?></span>
            <span class="stat-label">Email đang liên kết</span>
        </div>
    </article>
</section>

<!-- Status Filter Tabs -->
<div class="source-tabs-bar" role="tablist" aria-label="Phân loại trạng thái Domain">
    <button type="button" class="source-tab active" data-domain-filter="all" role="tab" aria-selected="true">
        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <circle cx="12" cy="12" r="10"></circle>
            <line x1="2" y1="12" x2="22" y2="12"></line>
            <path d="M12 2a15.3 15.3 0 0 1 4 10 15.3 15.3 0 0 1-4 10 15.3 15.3 0 0 1-4-10z"></path>
        </svg>
        <span>Tất cả domain</span>
        <span class="tab-badge" id="tabCountAllDomains"><?= number_format($stats['total']) ?></span>
    </button>
    <button type="button" class="source-tab" data-domain-filter="active" role="tab" aria-selected="false">
        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path>
            <polyline points="22 4 12 14.01 9 11.01"></polyline>
        </svg>
        <span>Đang hoạt động</span>
        <span class="tab-badge" id="tabCountActiveDomains"><?= number_format($stats['active']) ?></span>
    </button>
    <button type="button" class="source-tab" data-domain-filter="inactive" role="tab" aria-selected="false">
        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <circle cx="12" cy="12" r="10"></circle>
            <line x1="15" y1="9" x2="9" y2="15"></line>
            <line x1="9" y1="9" x2="15" y2="15"></line>
        </svg>
        <span>Tạm tắt</span>
        <span class="tab-badge" id="tabCountInactiveDomains"><?= number_format($stats['inactive']) ?></span>
    </button>
</div>

<!-- Search box -->
<div class="filters">
    <div class="search-box">
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <circle cx="11" cy="11" r="8"></circle>
            <line x1="21" y1="21" x2="16.65" y2="16.65"></line>
        </svg>
        <input type="search" id="domainSearchInput" placeholder="Tìm kiếm theo tên domain..." autocomplete="off">
    </div>
</div>

<section class="table-container">
    <table class="data-table" id="domainsTable">
        <colgroup>
            <col class="col-domain-name" style="width: auto;">
            <col class="col-domain-status" style="width: 140px;">
            <col class="col-domain-emails" style="width: 130px;">
            <col class="col-domain-date" style="width: 200px;">
            <col class="col-domain-actions" style="width: 90px;">
        </colgroup>
        <thead>
            <tr>
                <th class="col-domain-name">DOMAIN</th>
                <th class="col-domain-status" style="text-align: center;">TRẠNG THÁI</th>
                <th class="col-domain-emails" style="text-align: center;">SỐ EMAIL</th>
                <th class="col-domain-date" style="white-space: nowrap;">NGÀY TẠO</th>
                <th class="col-domain-actions" style="text-align: center;">THAO TÁC</th>
            </tr>
        </thead>
        <tbody id="domainsTableBody">
            <?php if (empty($domains)): ?>
                <tr id="emptyDomainsRow">
                    <td colspan="5" style="text-align: center; padding: 48px 16px; color: var(--slate-500);">
                        <div style="display: flex; flex-direction: column; align-items: center; gap: 12px;">
                            <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="var(--slate-400)" stroke-width="1.5">
                                <circle cx="12" cy="12" r="10"></circle>
                                <line x1="2" y1="12" x2="22" y2="12"></line>
                                <path d="M12 2a15.3 15.3 0 0 1 4 10 15.3 15.3 0 0 1-4 10 15.3 15.3 0 0 1-4-10z"></path>
                            </svg>
                            <p style="font-size: 1rem; font-weight: 500;">Chưa có tên miền nào trong hệ thống</p>
                            <button type="button" class="btn primary btn-sm" data-modal-open="addDomainModal">Thêm tên miền đầu tiên</button>
                        </div>
                    </td>
                </tr>
            <?php else: ?>
                <?php foreach ($domains as $domain): ?>
                    <?php
                        $domId = (int) $domain['id'];
                        $domName = (string) $domain['domain'];
                        $isActive = ((int) ($domain['is_active'] ?? 0)) === 1;
                        $emailCount = (int) ($domain['email_count'] ?? 0);
                        $createdAt = (string) ($domain['created_at'] ?? '2026-09-07 00:00:00');
                    ?>
                    <tr id="domainRow_<?= $domId ?>" data-domain-id="<?= $domId ?>" class="<?= $isActive ? '' : 'is-inactive-row' ?>">
                        <td class="col-domain-name">
                            <div style="display: flex; align-items: center; gap: 8px;">
                                <strong class="domain-name-tag"><code>@<?= htmlspecialchars($domName, ENT_QUOTES, 'UTF-8') ?></code></strong>
                                <button type="button" class="token-icon-btn btn-copy-key" data-copy-value="<?= htmlspecialchars($domName, ENT_QUOTES, 'UTF-8') ?>" title="Sao chép tên domain">
                                    <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                        <rect x="9" y="9" width="13" height="13" rx="2" ry="2"></rect>
                                        <path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"></path>
                                    </svg>
                                </button>
                            </div>
                        </td>
                        <td class="col-domain-status" style="text-align: center;">
                            <div style="display: flex; align-items: center; justify-content: center;">
                                <label class="ios-switch" title="<?= $isActive ? 'Bấm để tắt domain này' : 'Bấm để bật domain này' ?>">
                                    <input type="checkbox" class="domain-toggle-switch domain-page-status-toggle" data-domain-id="<?= $domId ?>" data-domain-name="<?= htmlspecialchars($domName, ENT_QUOTES, 'UTF-8') ?>" <?= $isActive ? 'checked' : '' ?>>
                                    <span class="ios-switch-slider"></span>
                                </label>
                            </div>
                        </td>
                        <td class="col-domain-emails" style="text-align: center;">
                            <span class="domain-email-pill <?= $emailCount > 0 ? 'has-emails' : 'zero-emails' ?>">
                                <?= $emailCount ?> email
                            </span>
                        </td>
                        <td class="col-domain-date" style="white-space: nowrap;"><?= htmlspecialchars($createdAt, ENT_QUOTES, 'UTF-8') ?></td>
                        <td class="col-domain-actions" style="text-align: center;">
                            <button type="button" class="btn danger btn-sm domain-delete-btn btn-delete-domain-row" data-domain-delete="<?= $domId ?>" data-domain-id="<?= $domId ?>" data-domain-name="<?= htmlspecialchars($domName, ENT_QUOTES, 'UTF-8') ?>" data-email-count="<?= $emailCount ?>" title="<?= $emailCount > 0 ? "Không thể xóa: đang có {$emailCount} email liên kết" : "Xóa domain này" ?>">
                                Xóa
                            </button>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>
</section>

<!-- Phân trang 1 2 3 ... -->
<div class="pagination" id="domainsPagination"></div>

<script id="initialDomainsJson" type="application/json"><?= json_encode($domains, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) ?></script>

<!-- Modal: Thêm domain mới -->
<div id="addDomainModal" class="modal hidden">
    <div class="modal-backdrop"></div>
    <div class="modal-content modal-md">
        <div class="modal-header">
            <div>
                <h2 style="display: flex; align-items: center; gap: 8px;">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <circle cx="12" cy="12" r="10"></circle>
                        <line x1="2" y1="12" x2="22" y2="12"></line>
                        <path d="M12 2a15.3 15.3 0 0 1 4 10 15.3 15.3 0 0 1-4 10 15.3 15.3 0 0 1-4-10z"></path>
                    </svg>
                    Thêm domain mới
                </h2>
                <p class="modal-subtitle">Thêm tên miền tiếp nhận email vào hệ thống KaiMail</p>
            </div>
            <button class="btn-close" type="button" data-modal-close="addDomainModal">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <line x1="18" y1="6" x2="6" y2="18"></line>
                    <line x1="6" y1="6" x2="18" y2="18"></line>
                </svg>
            </button>
        </div>
        <div class="modal-body">
            <form id="addDomainForm">
                <div class="form-group">
                    <label for="domainName">Tên domain <span style="color: var(--danger);">*</span></label>
                    <input type="text" id="domainName" name="domain" placeholder="VD: mail.kaishop.id.vn hoặc kaishop.id.vn" pattern="[a-zA-Z0-9\.\-]+" required autocomplete="off">
                    <p class="field-note">Không nhập tiền tố `http://`, `https://` hoặc `www`.</p>
                </div>

                <div class="form-group">
                    <label>Trạng thái ban đầu</label>
                    <div class="radio-group">
                        <label class="radio-item">
                            <input type="radio" name="domain_status" value="1" checked>
                            <span>Hoạt động</span>
                        </label>
                        <label class="radio-item">
                            <input type="radio" name="domain_status" value="0">
                            <span>Tạm tắt</span>
                        </label>
                    </div>
                </div>

                <div class="hint-box" style="margin-top: 12px; margin-bottom: 20px;">
                    <div style="display: flex; gap: 8px; align-items: flex-start;">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="flex-shrink: 0; margin-top: 2px;">
                            <circle cx="12" cy="12" r="10"></circle>
                            <line x1="12" y1="16" x2="12" y2="12"></line>
                            <line x1="12" y1="8" x2="12.01" y2="8"></line>
                        </svg>
                        <div>
                            <span>Cần cấu hình DNS Cloudflare Email Routing trước để domain có thể nhận email mượt mà.</span>
                            <a href="<?= htmlspecialchars(BASE_URL, ENT_QUOTES, 'UTF-8') ?>/adminkaishop/docs-domain" target="_blank" style="display: inline-block; margin-top: 4px; font-weight: 600; text-decoration: underline;">
                                Xem hướng dẫn chi tiết &rarr;
                            </a>
                        </div>
                    </div>
                </div>

                <div class="form-actions">
                    <button type="button" class="btn secondary" data-modal-close="addDomainModal">Hủy</button>
                    <button type="submit" class="btn primary" id="btnSubmitAddDomain">Thêm domain</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php
AdminLayout::end();
