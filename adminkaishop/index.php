<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/Auth.php';
Auth::requireLogin();

require_once __DIR__ . '/../includes/AdminLayout.php';
require_once __DIR__ . '/../includes/Core/App.php';

use KaiMail\Core\App;
use KaiMail\Core\Services\DomainService;

$admin = ['username' => 'admin'];

try {
    /** @var DomainService $domainService */
    $domainService = App::getService(DomainService::class);
    $domains = array_column($domainService->getActiveDomains(), 'domain');
} catch (Throwable $e) {
    $domains = [];
    error_log('Admin index: load domains failed - ' . $e->getMessage());
}

AdminLayout::begin('Quản lý email', 'emails', (string) ($admin['username'] ?? 'admin'));
?>
<header class="page-header">
    <div class="page-header-title">
        <h1>Quản lý email</h1>
        <p>Theo dõi, tạo mới và xử lý email trong hệ thống KaiMail.</p>
    </div>
    <div class="page-actions">
        <button id="fastCheckerBtn" class="btn btn-dark" type="button" data-modal-open="checkerModal">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                stroke-linecap="round" stroke-linejoin="round">
                <polygon points="13 2 3 14 12 14 11 22 21 10 12 10 13 2"></polygon>
            </svg>
            <span>Fast Checker</span>
        </button>
        <button id="addDomainBtn" class="btn secondary" type="button" data-modal-open="addDomainModal">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <circle cx="12" cy="12" r="10"></circle>
                <line x1="12" y1="8" x2="12" y2="16"></line>
                <line x1="8" y1="12" x2="16" y2="12"></line>
            </svg>
            <span>Thêm domain</span>
        </button>
        <button id="createEmailBtn" class="btn primary" type="button" data-modal-open="createModal">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <line x1="12" y1="5" x2="12" y2="19"></line>
                <line x1="5" y1="12" x2="19" y2="12"></line>
            </svg>
            <span>Tạo email</span>
        </button>
    </div>
</header>

<section class="stats-grid-5">
    <article class="stat-card stat-total">
        <div class="stat-icon">
            <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1-0-2-.9-2-2V6c0-1.1.9-2 2-2z"></path>
                <polyline points="22,6 12,13 2,6"></polyline>
            </svg>
        </div>
        <div class="stat-info">
            <span class="stat-value" id="statTotalEmails">0</span>
            <span class="stat-label">Tổng email</span>
        </div>
    </article>

    <article class="stat-card stat-admin">
        <div class="stat-icon">
            <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"></path>
            </svg>
        </div>
        <div class="stat-info">
            <span class="stat-value" id="statAdminEmails">0</span>
            <span class="stat-label">Email Admin tạo</span>
        </div>
    </article>

    <article class="stat-card stat-api">
        <div class="stat-icon">
            <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <polyline points="16 18 22 12 16 6"></polyline>
                <polyline points="8 6 2 12 8 18"></polyline>
            </svg>
        </div>
        <div class="stat-info">
            <span class="stat-value" id="statApiEmails">0</span>
            <span class="stat-label">Hệ thống / API tạo</span>
        </div>
    </article>

    <article class="stat-card stat-user">
        <div class="stat-icon">
            <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path>
                <circle cx="12" cy="7" r="4"></circle>
            </svg>
        </div>
        <div class="stat-info">
            <span class="stat-value" id="statUserEmails">0</span>
            <span class="stat-label">Khách tự tạo</span>
        </div>
    </article>

    <article class="stat-card stat-messages">
        <div class="stat-icon">
            <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <polyline points="22 12 16 12 14 15 10 15 8 12 2 12"></polyline>
                <path d="M5.45 5.11L2 12v6a2 2 0 0 0 2 2h16a2 2 0 0 0 2-2v-6l-3.45-6.89A2 2 0 0 0 16.76 4H7.24a2 2 0 0 0-1.79 1.11z"></path>
            </svg>
        </div>
        <div class="stat-info">
            <span class="stat-value" id="statTotalMessages">0</span>
            <span class="stat-label">Tổng tin nhắn</span>
        </div>
    </article>
</section>

<!-- Source Filter Tabs: Phân biệt Admin vs API vs Khách -->
<div class="source-tabs-bar" role="tablist" aria-label="Phân loại nguồn email">
    <button type="button" class="source-tab active" data-source="all" role="tab" aria-selected="true">
        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <circle cx="12" cy="12" r="10"></circle>
            <line x1="2" y1="12" x2="22" y2="12"></line>
            <path d="M12 2a15.3 15.3 0 0 1 4 10 15.3 15.3 0 0 1-4 10 15.3 15.3 0 0 1-4-10z"></path>
        </svg>
        <span>Tất cả email</span>
        <span class="tab-badge" id="tabCountAll">0</span>
    </button>
    <button type="button" class="source-tab tab-admin" data-source="admin" role="tab" aria-selected="false">
        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"></path>
        </svg>
        <span>Admin Mail</span>
        <span class="tab-badge admin-badge-count" id="tabCountAdmin">0</span>
    </button>
    <button type="button" class="source-tab tab-api" data-source="api" role="tab" aria-selected="false">
        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <polyline points="16 18 22 12 16 6"></polyline>
            <polyline points="8 6 2 12 8 18"></polyline>
        </svg>
        <span>API</span>
        <span class="tab-badge api-badge-count" id="tabCountApi">0</span>
    </button>
    <button type="button" class="source-tab" data-source="user" role="tab" aria-selected="false">
        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path>
            <circle cx="12" cy="7" r="4"></circle>
        </svg>
        <span>Khách tạo</span>
        <span class="tab-badge" id="tabCountUser">0</span>
    </button>
</div>

<section class="filters">
    <div class="search-box">
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <circle cx="11" cy="11" r="8"></circle>
            <line x1="21" y1="21" x2="16.65" y2="16.65"></line>
        </svg>
        <input type="text" id="searchInput" placeholder="Tìm theo email hoặc ghi chú...">
    </div>

    <div class="filter-group">
        <select id="domainFilter" class="select-filter" title="Lọc theo tên miền">
            <option value="">Tất cả tên miền</option>
            <?php foreach ($domains as $domain): ?>
                <option value="<?= htmlspecialchars((string) $domain, ENT_QUOTES, 'UTF-8') ?>">
                    <?= htmlspecialchars((string) $domain, ENT_QUOTES, 'UTF-8') ?>
                </option>
            <?php endforeach; ?>
        </select>

        <select id="expiryFilter" class="select-filter" title="Lọc theo tin nhắn">
            <option value="">Tất cả tin nhắn</option>
            <option value="no_message">Chưa có tin nhắn</option>
        </select>
    </div>

    <div class="bulk-actions-wrapper">
        <button id="deleteSelectedBtn" class="btn danger btn-sm hidden" type="button" title="Xóa các email đã chọn">
            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <polyline points="3 6 5 6 21 6"></polyline>
                <path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path>
            </svg>
            <span>Xóa (<span id="selectedDeleteCount">0</span>)</span>
        </button>

        <button id="copySelectedBtn" class="btn secondary btn-sm hidden" type="button" title="Sao chép danh sách email đã chọn">
            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <rect x="9" y="9" width="13" height="13" rx="2" ry="2"></rect>
                <path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"></path>
            </svg>
            <span>Copy</span>
        </button>
    </div>
</section>

<section class="table-container">
    <table class="data-table">
        <colgroup>
            <col style="width: 48px;">
            <col style="width: 38%;">
            <col style="width: 26%;">
            <col style="width: 11%;">
            <col style="width: 14%;">
            <col style="width: 11%;">
        </colgroup>
        <thead>
            <tr>
                <th class="col-check">
                    <input type="checkbox" id="selectAll" title="Chọn tất cả">
                </th>
                <th class="col-email">Email & Nguồn</th>
                <th class="col-note">Ghi chú (Note)</th>
                <th class="col-messages" style="text-align: center;">Tin nhắn</th>
                <th class="col-date" style="text-align: center;">Tạo lúc</th>
                <th class="col-actions" style="text-align: center;">Thao tác</th>
            </tr>
        </thead>
        <tbody id="emailsTableBody"></tbody>
    </table>
</section>

<section class="pagination" id="pagination"></section>

<div id="loadingState" class="loading-overlay hidden">
    <div class="spinner"></div>
</div>

<div id="createModal" class="modal hidden">
    <div class="modal-backdrop"></div>
    <div class="modal-content modal-sm">
        <div class="modal-header">
            <h2>Tạo email mới</h2>
            <button class="btn-close" type="button" data-modal-close="createModal">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <line x1="18" y1="6" x2="6" y2="18"></line>
                    <line x1="6" y1="6" x2="18" y2="18"></line>
                </svg>
            </button>
        </div>
        <div class="modal-body">
            <form id="createEmailForm">
                <div class="form-group">
                    <label>Kiểu tên email</label>
                    <div class="radio-group">
                        <label class="radio-item">
                            <input type="radio" name="name_type" value="vn" checked>
                            <span>Tên Việt Nam</span>
                        </label>
                        <label class="radio-item">
                            <input type="radio" name="name_type" value="en">
                            <span>Tên tiếng Anh</span>
                        </label>
                        <label class="radio-item">
                            <input type="radio" name="name_type" value="custom">
                            <span>Tự nhập</span>
                        </label>
                    </div>
                </div>

                <div class="form-group hidden" id="customEmailGroup">
                    <label for="customEmail">Tên email tùy chỉnh (không gồm @domain)</label>
                    <input type="text" id="customEmail" placeholder="vi-du: support123" pattern="[A-Za-z0-9\-\._]+">
                    <p class="field-note">Chỉ dùng chữ cái, số, dấu chấm, gạch ngang và gạch dưới.</p>
                </div>

                <div class="form-group" id="quantityGroup">
                    <label for="emailQuantity">Số lượng (Tối đa 50)</label>
                    <input type="number" id="emailQuantity" name="quantity" min="1" max="50" value="1"
                        class="custom-number-input">
                </div>

                <div class="form-group">
                    <label for="domainSelect">Domain</label>
                    <select id="domainSelect" class="select-filter" required>
                        <?php if (empty($domains)): ?>
                            <option value="">Chưa có domain hoạt động. Hãy thêm domain trước.</option>
                        <?php else: ?>
                            <?php foreach ($domains as $index => $domain): ?>
                                <option value="<?= htmlspecialchars((string) $domain, ENT_QUOTES, 'UTF-8') ?>" <?= $index === 0 ? 'selected' : '' ?>>
                                    <?= htmlspecialchars((string) $domain, ENT_QUOTES, 'UTF-8') ?>
                                </option>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label for="createEmailNote">Ghi chú (Tùy chọn)</label>
                    <input type="text" id="createEmailNote" placeholder="VD: Acc Facebook 1, Verify TikTok, Nick chính...">
                </div>

                <div class="form-actions">
                    <button type="button" class="btn secondary" data-modal-close="createModal">Hủy</button>
                    <button type="submit" class="btn primary">Tạo email</button>
                </div>
            </form>
        </div>
    </div>
</div>

<div id="messagesModal" class="modal hidden">
    <div class="modal-backdrop"></div>
    <div class="modal-content modal-lg">
        <div class="modal-header">
            <h2 id="messagesModalTitle">Danh sách tin nhắn</h2>
            <button class="btn-close" type="button" data-modal-close="messagesModal">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <line x1="18" y1="6" x2="6" y2="18"></line>
                    <line x1="6" y1="6" x2="18" y2="18"></line>
                </svg>
            </button>
        </div>
        <div class="modal-body" id="messagesModalBody"></div>
    </div>
</div>

<div id="viewMessageModal" class="modal hidden">
    <div class="modal-backdrop"></div>
    <div class="modal-content modal-lg">
        <div class="modal-header">
            <div class="modal-title-info">
                <h2 id="viewMessageSubject"></h2>
                <p id="viewMessageFrom"></p>
            </div>
            <button class="btn-close" type="button" data-modal-close="viewMessageModal">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <line x1="18" y1="6" x2="6" y2="18"></line>
                    <line x1="6" y1="6" x2="18" y2="18"></line>
                </svg>
            </button>
        </div>
        <div class="modal-body email-body" id="viewMessageBody"></div>
    </div>
</div>

<div id="addDomainModal" class="modal hidden">
    <div class="modal-backdrop"></div>
    <div class="modal-content modal-sm">
        <div class="modal-header">
            <h2>Thêm domain mới</h2>
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
                    <label for="domainName">Tên domain</label>
                    <input type="text" id="domainName" placeholder="example.com" pattern="[a-z0-9\.\-]+" required>
                    <p class="field-note">Không nhập tiền tố `http://` hoặc `www`.</p>
                </div>

                <div class="form-group">
                    <label>Trạng thái</label>
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

                <div class="hint-box">
                    Cần cấu hình DNS/MX trước khi dùng domain nhận mail.
                    <a href="<?= htmlspecialchars(BASE_URL, ENT_QUOTES, 'UTF-8') ?>/adminkaishop/docs-domain"
                        style="text-decoration: underline;">
                        Xem hướng dẫn
                    </a>
                </div>

                <div class="form-actions">
                    <button type="button" class="btn secondary" data-modal-close="addDomainModal">Hủy</button>
                    <button type="submit" class="btn primary">Thêm domain</button>
                </div>
            </form>
        </div>
    </div>
</div>

<div id="checkerModal" class="modal hidden">
    <div class="modal-backdrop"></div>
    <div class="modal-content modal-lg">
        <div class="modal-header" style="background: #f8fafc; border-bottom: 1px solid #e2e8f0;">
            <div>
                <h2 style="color: #0f172a; font-size: 1.25rem; display: flex; align-items: center; gap: 0.5rem;">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <polygon points="13 2 3 14 12 14 11 22 21 10 12 10 13 2"></polygon>
                    </svg>
                    Fast Email Checker
                </h2>
            </div>
            <button class="btn-close" type="button" data-modal-close="checkerModal">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <line x1="18" y1="6" x2="6" y2="18"></line>
                    <line x1="6" y1="6" x2="18" y2="18"></line>
                </svg>
            </button>
        </div>
        <div class="modal-body">
            <form id="checkerForm" class="checker-form-fancy">
                <div class="checker-input-group">
                    <label for="checkerKeyword">Từ khóa quét email</label>
                    <div class="checker-input-wrapper">
                        <svg class="checker-icon" width="16" height="16" viewBox="0 0 24 24" fill="none"
                            stroke="currentColor" stroke-width="2">
                            <circle cx="11" cy="11" r="8"></circle>
                            <line x1="21" y1="21" x2="16.65" y2="16.65"></line>
                        </svg>
                        <input type="text" id="checkerKeyword" value="deactivating"
                            placeholder="VD: deactivating, openai..." required>
                    </div>
                </div>
                <div class="checker-input-group days-group">
                    <label for="checkerDays">Trong vòng (ngày)</label>
                    <div class="checker-input-wrapper">
                        <svg class="checker-icon" width="16" height="16" viewBox="0 0 24 24" fill="none"
                            stroke="currentColor" stroke-width="2">
                            <rect x="3" y="4" width="18" height="18" rx="2" ry="2"></rect>
                            <line x1="16" y1="2" x2="16" y2="6"></line>
                            <line x1="8" y1="2" x2="8" y2="6"></line>
                            <line x1="3" y1="10" x2="21" y2="10"></line>
                        </svg>
                        <input type="number" id="checkerDays" value="30" min="1" max="90">
                    </div>
                </div>
                <button type="submit" class="checker-btn-submit">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <polygon points="13 2 3 14 12 14 11 22 21 10 12 10 13 2"></polygon>
                    </svg>
                    <span>Quét ngay</span>
                </button>
            </form>

            <div id="checkerResults" class="checker-results-container">
                <div style="text-align: center; color: #94a3b8; padding: 2rem;">
                    Nhập từ khóa và click "Quét ngay" để bắt đầu
                </div>
            </div>
        </div>
    </div>
</div>

<div id="noteModal" class="modal hidden">
    <div class="modal-backdrop"></div>
    <div class="modal-content modal-sm">
        <div class="modal-header">
            <h2>Ghi chú email</h2>
            <button class="btn-close" type="button" data-modal-close="noteModal">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <line x1="18" y1="6" x2="6" y2="18"></line>
                    <line x1="6" y1="6" x2="18" y2="18"></line>
                </svg>
            </button>
        </div>
        <div class="modal-body">
            <form id="updateNoteForm">
                <input type="hidden" id="noteEmailId" value="">
                <div class="target-email-chip">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1-0-2-.9-2-2V6c0-1.1.9-2 2-2z"></path>
                        <polyline points="22,6 12,13 2,6"></polyline>
                    </svg>
                    <span id="noteEmailTarget"></span>
                </div>
                <div class="form-group" style="margin-top: 14px;">
                    <label for="noteInputText">Nội dung ghi chú</label>
                    <textarea id="noteInputText" rows="3" class="form-control" placeholder="Nhập ghi chú cho email này (VD: Acc clone 01, Đã verify...)" maxlength="255"></textarea>
                    <p class="field-note">Ghi chú hỗ trợ bạn tìm kiếm và nhận diện tài khoản nhanh chóng.</p>
                </div>
                <div class="form-actions">
                    <button type="button" class="btn secondary" data-modal-close="noteModal">Hủy</button>
                    <button type="submit" class="btn primary">Lưu ghi chú</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php
AdminLayout::end(['/js/admin-dashboard.js']);
