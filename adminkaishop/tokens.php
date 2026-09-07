<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/Auth.php';
Auth::requireLogin();

require_once __DIR__ . '/../includes/AdminLayout.php';
require_once __DIR__ . '/../includes/Core/App.php';

use KaiMail\Core\App;
use KaiMail\Core\Services\TokenService;

$admin = ['username' => 'admin'];
$tokens = [];
$stats = [
    'total' => 0,
    'active' => 0,
    'inactive' => 0,
    'total_requests' => 0,
];

try {
    /** @var TokenService $tokenService */
    $tokenService = App::getService(TokenService::class);
    $tokens = $tokenService->listAll();

    $stats['total'] = count($tokens);
    foreach ($tokens as $t) {
        $isActive = (int) $t['status'] === 1;
        $isExpired = !empty($t['expires_at']) && strtotime((string) $t['expires_at']) < time();

        if ($isActive && !$isExpired) {
            $stats['active']++;
        } else {
            $stats['inactive']++;
        }
        $stats['total_requests'] += (int) ($t['total_requests'] ?? 0);
    }
} catch (Throwable $e) {
    error_log('Admin tokens: load failed - ' . $e->getMessage());
}

AdminLayout::begin('Quản lý API Token', 'tokens', (string) ($admin['username'] ?? 'admin'));
?>
<header class="page-header">
    <div class="page-header-title">
        <h1>Quản lý API Token</h1>
        <p>Cấp phát khóa API đa người dùng (Multi-tenant), phân quyền Rate Limit và giám sát lưu lượng gọi API.</p>
    </div>
    <div class="page-actions">
        <a href="<?= htmlspecialchars(BASE_URL, ENT_QUOTES, 'UTF-8') ?>/adminkaishop/docs-api" class="btn secondary">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <polyline points="16 18 22 12 16 6"></polyline>
                <polyline points="8 6 2 12 8 18"></polyline>
            </svg>
            <span>Tài liệu API</span>
        </a>
        <button id="createTokenBtn" class="btn primary" type="button" data-modal-open="addTokenModal">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <line x1="12" y1="5" x2="12" y2="19"></line>
                <line x1="5" y1="12" x2="19" y2="12"></line>
            </svg>
            <span>Tạo Token mới</span>
        </button>
    </div>
</header>

<section class="stats-grid-4">
    <article class="stat-card stat-total">
        <div class="stat-icon">
            <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path d="M21 2l-2 2m-1.5 1.5L14 9a5 5 0 1 0 3 3l3.5-3.5 2-2zM9 18a3 3 0 1 1 0-6 3 3 0 0 1 0 6z"></path>
            </svg>
        </div>
        <div class="stat-info">
            <span class="stat-value" id="statTotalTokens"><?= number_format($stats['total']) ?></span>
            <span class="stat-label">Tổng API Token</span>
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
            <span class="stat-value" id="statActiveTokens"><?= number_format($stats['active']) ?></span>
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
            <span class="stat-value" id="statInactiveTokens"><?= number_format($stats['inactive']) ?></span>
            <span class="stat-label">Tạm khóa / Hết hạn</span>
        </div>
    </article>

    <article class="stat-card stat-api">
        <div class="stat-icon">
            <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <polyline points="22 12 18 12 15 21 9 3 6 12 2 12"></polyline>
            </svg>
        </div>
        <div class="stat-info">
            <span class="stat-value" id="statTotalRequests"><?= number_format($stats['total_requests']) ?></span>
            <span class="stat-label">Tổng lượt gọi API</span>
        </div>
    </article>
</section>

<!-- Status Filter Tabs -->
<div class="source-tabs-bar" role="tablist" aria-label="Phân loại trạng thái Token">
    <button type="button" class="source-tab active" data-token-filter="all" role="tab" aria-selected="true">
        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <circle cx="12" cy="12" r="10"></circle>
            <line x1="2" y1="12" x2="22" y2="12"></line>
            <path d="M12 2a15.3 15.3 0 0 1 4 10 15.3 15.3 0 0 1-4 10 15.3 15.3 0 0 1-4-10z"></path>
        </svg>
        <span>Tất cả Token</span>
        <span class="tab-badge" id="tabCountAllTokens"><?= number_format($stats['total']) ?></span>
    </button>
    <button type="button" class="source-tab" data-token-filter="active" role="tab" aria-selected="false">
        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path>
            <polyline points="22 4 12 14.01 9 11.01"></polyline>
        </svg>
        <span>Đang hoạt động</span>
        <span class="tab-badge" id="tabCountActiveTokens"><?= number_format($stats['active']) ?></span>
    </button>
    <button type="button" class="source-tab" data-token-filter="inactive" role="tab" aria-selected="false">
        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <circle cx="12" cy="12" r="10"></circle>
            <line x1="15" y1="9" x2="9" y2="15"></line>
            <line x1="9" y1="9" x2="15" y2="15"></line>
        </svg>
        <span>Tạm khóa / Hết hạn</span>
        <span class="tab-badge" id="tabCountInactiveTokens"><?= number_format($stats['inactive']) ?></span>
    </button>
</div>

<!-- Search box -->
<div class="filters">
    <div class="search-box">
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <circle cx="11" cy="11" r="8"></circle>
            <line x1="21" y1="21" x2="16.65" y2="16.65"></line>
        </svg>
        <input type="search" id="tokenSearchInput" placeholder="Tìm kiếm theo tên Token, Key ID..." autocomplete="off">
    </div>
</div>

<section class="table-container">
    <table class="data-table" id="tokensTable">
        <thead>
            <tr>
                <th style="min-width: 170px;">Tên Token &amp; Định danh</th>
                <th style="min-width: 170px;">Key ID (Public)</th>
                <th style="min-width: 200px;">Secret Key (Bí mật)</th>
                <th style="width: 100px; text-align: center; white-space: nowrap;">Tần suất</th>
                <th style="width: 90px; text-align: center; white-space: nowrap;">Lượt gọi</th>
                <th class="col-token-status">Trạng thái</th>
                <th class="col-token-actions">Thao tác</th>
            </tr>
        </thead>
        <tbody id="tokensTableBody">
            <tr id="emptyTokensRow">
                <td colspan="7" style="text-align: center; padding: 48px 16px; color: var(--slate-500);">
                    <div style="display: flex; flex-direction: column; align-items: center; gap: 12px;">
                        <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="var(--slate-400)" stroke-width="1.5">
                            <path d="M21 2l-2 2m-1.5 1.5L14 9a5 5 0 1 0 3 3l3.5-3.5 2-2zM9 18a3 3 0 1 1 0-6 3 3 0 0 1 0 6z"></path>
                        </svg>
                        <p style="font-size: 1rem; font-weight: 500;">Đang tải danh sách API Token...</p>
                    </div>
                </td>
            </tr>
        </tbody>
    </table>
</section>

<!-- Phân trang 1 2 3 ... -->
<div class="pagination" id="tokensPagination"></div>

<script id="initialTokensJson" type="application/json"><?= json_encode($tokens, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) ?></script>

<!-- Modal: Tạo Token mới -->
<div id="addTokenModal" class="modal hidden">
    <div class="modal-backdrop"></div>
    <div class="modal-content modal-md">
        <div class="modal-header">
            <div>
                <h2 style="display: flex; align-items: center; gap: 8px;">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M21 2l-2 2m-1.5 1.5L14 9a5 5 0 1 0 3 3l3.5-3.5 2-2zM9 18a3 3 0 1 1 0-6 3 3 0 0 1 0 6z"></path>
                    </svg>
                    Tạo API Token mới
                </h2>
                <p class="modal-subtitle">Cấp phát cặp Key ID & Secret Key để tích hợp hệ thống ngoài hoặc cung cấp cho khách hàng</p>
            </div>
            <button class="btn-close" type="button" data-modal-close="addTokenModal">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <line x1="18" y1="6" x2="6" y2="18"></line>
                    <line x1="6" y1="6" x2="18" y2="18"></line>
                </svg>
            </button>
        </div>
        <div class="modal-body">
            <form id="addTokenForm">
                <div class="form-group">
                    <label for="tokenName">Tên định danh Token <span style="color: var(--danger);">*</span></label>
                    <input type="text" id="tokenName" name="name" placeholder="VD: Bot Discord, Tool Reg Acc, Client A" required autocomplete="off">
                    <p class="field-note">Đặt tên dễ nhớ để phân biệt mục đích sử dụng hoặc khách hàng.</p>
                </div>

                <div class="form-group">
                    <label for="tokenRateLimit">Giới hạn tần suất gọi (Requests / Phút)</label>
                    <input type="number" id="tokenRateLimit" name="rate_limit_per_min" value="120" min="10" max="6000" step="10">
                    <p class="field-note">Mặc định: 120 req/phút (tương đương 2 req/giây). Hệ thống tự động throttle khi vượt ngưỡng.</p>
                </div>

                <div class="form-group">
                    <label for="tokenExpiresDays">Thời hạn sử dụng</label>
                    <select id="tokenExpiresDays" name="expires_days">
                        <option value="0" selected>Không giới hạn (Vĩnh viễn)</option>
                        <option value="7">7 ngày</option>
                        <option value="30">30 ngày</option>
                        <option value="90">90 ngày</option>
                        <option value="365">1 năm (365 ngày)</option>
                    </select>
                </div>

                <div class="form-actions">
                    <button type="button" class="btn secondary" data-modal-close="addTokenModal">Hủy</button>
                    <button type="submit" class="btn primary" id="btnSubmitAddToken">Tạo Token</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal: Hiển thị Token vừa tạo -->
<div id="tokenCreatedModal" class="modal hidden">
    <div class="modal-backdrop"></div>
    <div class="modal-content modal-md">
        <div class="modal-header">
            <div>
                <h2 style="display: flex; align-items: center; gap: 8px; color: var(--primary);">
                    <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path>
                        <polyline points="22 4 12 14.01 9 11.01"></polyline>
                    </svg>
                    Tạo Token thành công!
                </h2>
                <p class="modal-subtitle">Thông tin xác thực API đã sẵn sàng sử dụng</p>
            </div>
            <button class="btn-close" type="button" data-modal-close="tokenCreatedModal">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <line x1="18" y1="6" x2="6" y2="18"></line>
                    <line x1="6" y1="6" x2="18" y2="18"></line>
                </svg>
            </button>
        </div>
        <div class="modal-body">
            <div style="background: var(--slate-50); border: 1px solid var(--border-subtle); border-radius: var(--radius-md); padding: 16px; margin-bottom: 20px;">
                <div style="margin-bottom: 14px;">
                    <label style="display: block; font-size: 0.8rem; font-weight: 600; color: var(--slate-600); margin-bottom: 6px;">KEY ID (X-API-KEY)</label>
                    <div style="display: flex; gap: 8px;">
                        <input type="text" id="createdKeyId" readonly style="font-family: var(--font-mono); font-size: 0.88rem; background: #fff;">
                        <button type="button" class="btn secondary btn-sm" id="btnCopyCreatedKeyId">Copy</button>
                    </div>
                </div>

                <div>
                    <label style="display: block; font-size: 0.8rem; font-weight: 600; color: var(--slate-600); margin-bottom: 6px;">SECRET KEY</label>
                    <div style="display: flex; gap: 8px;">
                        <input type="text" id="createdSecretKey" readonly style="font-family: var(--font-mono); font-size: 0.88rem; background: #fff;">
                        <button type="button" class="btn secondary btn-sm" id="btnCopyCreatedSecretKey">Copy</button>
                    </div>
                </div>
            </div>

            <p style="font-size: 0.88rem; color: var(--slate-600); margin-bottom: 16px; line-height: 1.5;">
                Bạn có thể xem lại Secret Key này bất cứ lúc nào trong bảng quản lý bằng cách bấm biểu tượng con mắt 👁️.
            </p>

            <div class="form-actions">
                <button type="button" class="btn primary" data-modal-close="tokenCreatedModal">Hoàn tất</button>
            </div>
        </div>
    </div>
</div>

<!-- Modal: Chỉnh sửa Token -->
<div id="editTokenModal" class="modal hidden">
    <div class="modal-backdrop"></div>
    <div class="modal-content modal-md">
        <div class="modal-header">
            <div>
                <h2 style="display: flex; align-items: center; gap: 8px;">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"></path>
                        <path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"></path>
                    </svg>
                    Chỉnh sửa API Token
                </h2>
                <p class="modal-subtitle">Thay đổi tên định danh, giới hạn tần suất gọi hoặc gia hạn thời gian sử dụng</p>
            </div>
            <button class="btn-close" type="button" data-modal-close="editTokenModal">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <line x1="18" y1="6" x2="6" y2="18"></line>
                    <line x1="6" y1="6" x2="18" y2="18"></line>
                </svg>
            </button>
        </div>
        <div class="modal-body">
            <form id="editTokenForm">
                <input type="hidden" id="editTokenId" name="id">

                <div class="form-group">
                    <label>Key ID (Public)</label>
                    <input type="text" id="editTokenKeyIdDisplay" readonly style="font-family: var(--font-mono); font-size: 0.88rem; background: var(--slate-100); color: var(--slate-600); cursor: not-allowed;">
                </div>

                <div class="form-group">
                    <label for="editTokenName">Tên định danh Token <span style="color: var(--danger);">*</span></label>
                    <input type="text" id="editTokenName" name="name" required autocomplete="off">
                </div>

                <div class="form-group">
                    <label for="editTokenRateLimit">Giới hạn tần suất gọi (Requests / Phút)</label>
                    <input type="number" id="editTokenRateLimit" name="rate_limit_per_min" min="10" max="6000" step="10" required>
                    <p class="field-note">Số lượng request tối đa mỗi phút. Mặc định 120 req/phút.</p>
                </div>

                <div class="form-group">
                    <label for="editTokenExpiresDays">Thời hạn sử dụng</label>
                    <select id="editTokenExpiresDays" name="expires_days">
                        <option value="keep" selected>Giữ nguyên thời hạn hiện tại</option>
                        <option value="never">Chuyển sang Không giới hạn (Vĩnh viễn)</option>
                        <option value="7">Gia hạn 7 ngày từ bây giờ</option>
                        <option value="30">Gia hạn 30 ngày từ bây giờ</option>
                        <option value="90">Gia hạn 90 ngày từ bây giờ</option>
                        <option value="365">Gia hạn 1 năm (365 ngày)</option>
                    </select>
                    <p class="field-note" id="editTokenCurrentExpiryNote" style="color: var(--slate-600); margin-top: 4px;"></p>
                </div>

                <div class="form-group">
                    <label for="editTokenStatus">Trạng thái</label>
                    <select id="editTokenStatus" name="status">
                        <option value="1">Đang hoạt động</option>
                        <option value="0">Tạm khóa</option>
                    </select>
                </div>

                <div class="form-actions">
                    <button type="button" class="btn secondary" data-modal-close="editTokenModal">Hủy</button>
                    <button type="submit" class="btn primary" id="btnSubmitEditToken">Lưu thay đổi</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal: Cấp lại / Xoay Secret Key mới (Regenerate Secret) -->
<div id="regenerateSecretModal" class="modal hidden">
    <div class="modal-backdrop"></div>
    <div class="modal-content modal-md">
        <div class="modal-header">
            <div>
                <h2 style="display: flex; align-items: center; gap: 8px; color: #d97706;">
                    <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M23 4v6h-6"></path>
                        <path d="M1 20v-6h6"></path>
                        <path d="M3.51 9a9 9 0 0 1 14.85-3.36L23 10M1 14l4.64 4.36A9 9 0 0 0 20.49 15"></path>
                    </svg>
                    Cấp lại Secret Key mới
                </h2>
                <p class="modal-subtitle">Tạo Secret Key mới cho Token và thu hồi khóa cũ</p>
            </div>
            <button class="btn-close" type="button" data-modal-close="regenerateSecretModal">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <line x1="18" y1="6" x2="6" y2="18"></line>
                    <line x1="6" y1="6" x2="18" y2="18"></line>
                </svg>
            </button>
        </div>
        <div class="modal-body">
            <div id="regenConfirmPane">
                <div style="background: #fffbeb; border: 1px solid #fde68a; border-radius: var(--radius-md); padding: 16px; margin-bottom: 20px;">
                    <div style="display: flex; gap: 10px; align-items: flex-start;">
                        <span style="font-size: 1.3rem; line-height: 1;">⚠️</span>
                        <div style="font-size: 0.88rem; color: #92400e; line-height: 1.5;">
                            <strong>Cảnh báo quan trọng:</strong> Secret Key cũ của token này sẽ lập tức mất hiệu lực vĩnh viễn. Các bot, cron job hoặc ứng dụng đang sử dụng Secret Key cũ sẽ bị lỗi <code>401 Unauthorized</code> cho tới khi cập nhật Secret Key mới.
                        </div>
                    </div>
                </div>

                <div style="margin-bottom: 18px;">
                    <span style="font-size: 0.85rem; color: var(--slate-500); display: block; margin-bottom: 4px;">Token được chọn:</span>
                    <div style="font-weight: 600; font-size: 1rem; color: var(--slate-900);" id="regenTokenName"></div>
                    <code style="font-size: 0.85rem; color: var(--slate-600);" id="regenTokenKeyId"></code>
                </div>

                <div class="form-actions">
                    <button type="button" class="btn secondary" data-modal-close="regenerateSecretModal">Hủy bỏ</button>
                    <button type="button" class="btn primary" id="btnConfirmRegenerateSecret" style="background: #d97706; border-color: #d97706;">Xác nhận tạo Key mới</button>
                </div>
            </div>

            <div id="regenSuccessPane" class="hidden">
                <div style="background: #ecfdf5; border: 1px solid #a7f3d0; border-radius: var(--radius-md); padding: 14px 16px; margin-bottom: 18px; color: #065f46; font-size: 0.88rem; display: flex; align-items: center; gap: 8px;">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <polyline points="20 6 9 17 4 12"></polyline>
                    </svg>
                    <strong>Đã cấp lại Secret Key mới thành công!</strong>
                </div>

                <div style="margin-bottom: 20px;">
                    <label style="display: block; font-size: 0.8rem; font-weight: 600; color: var(--slate-600); margin-bottom: 6px;">SECRET KEY MỚI</label>
                    <div style="display: flex; gap: 8px;">
                        <input type="text" id="newRegeneratedSecretKey" readonly style="font-family: var(--font-mono); font-size: 0.88rem; background: #fff; width: 100%;">
                        <button type="button" class="btn secondary btn-sm" id="btnCopyRegeneratedSecret">Copy</button>
                    </div>
                </div>

                <div class="form-actions">
                    <button type="button" class="btn primary" data-modal-close="regenerateSecretModal">Hoàn tất</button>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
.token-action-wrapper {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 6px;
}
.btn-icon {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 32px;
    height: 32px;
    padding: 0;
    border: 1px solid var(--border-subtle, #e2e8f0);
    border-radius: var(--radius-sm, 6px);
    background: #fff;
    color: var(--slate-600, #475569);
    cursor: pointer;
    transition: all 0.15s ease;
}
.btn-icon:hover {
    background: var(--slate-100, #f1f5f9);
    color: var(--slate-900, #0f172a);
    border-color: var(--border-strong, #cbd5e1);
}
.btn-icon.danger:hover {
    background: #fef2f2;
    color: #dc2626;
    border-color: #fca5a5;
}
.btn-icon.warning:hover {
    background: #fffbeb;
    color: #d97706;
    border-color: #fde68a;
}
</style>

<?php
AdminLayout::end();
