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
        <a href="/adminkaishop/docs-api" class="btn secondary">
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

<section class="table-container">
    <table class="data-table" id="tokensTable">
        <thead>
            <tr>
                <th style="width: 18%;">Tên Token & Định danh</th>
                <th style="width: 22%;">Key ID (Public)</th>
                <th style="width: 26%;">Secret Key (Bí mật)</th>
                <th style="width: 10%; text-align: center;">Tần suất</th>
                <th style="width: 10%; text-align: center;">Lượt gọi</th>
                <th style="width: 8%; text-align: center;">Trạng thái</th>
                <th style="width: 6%; text-align: center;">Thao tác</th>
            </tr>
        </thead>
        <tbody id="tokensTableBody">
            <?php if (empty($tokens)): ?>
            <tr id="emptyTokensRow">
                <td colspan="7" style="text-align: center; padding: 48px 16px; color: var(--slate-500);">
                    <div style="display: flex; flex-direction: column; align-items: center; gap: 12px;">
                        <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="var(--slate-400)" stroke-width="1.5">
                            <path d="M21 2l-2 2m-1.5 1.5L14 9a5 5 0 1 0 3 3l3.5-3.5 2-2zM9 18a3 3 0 1 1 0-6 3 3 0 0 1 0 6z"></path>
                        </svg>
                        <p style="font-size: 1rem; font-weight: 500;">Chưa có API Token nào được tạo</p>
                        <button type="button" class="btn primary btn-sm" data-modal-open="addTokenModal">Tạo Token đầu tiên</button>
                    </div>
                </td>
            </tr>
            <?php else: ?>
                <?php foreach ($tokens as $token):
                    $isExpired = !empty($token['expires_at']) && strtotime((string) $token['expires_at']) < time();
                    $isActive = (int) $token['status'] === 1 && !$isExpired;
                ?>
                <tr id="tokenRow-<?= (int) $token['id'] ?>" data-token-id="<?= (int) $token['id'] ?>">
                    <td>
                        <span class="table-token-name"><?= htmlspecialchars((string) $token['name'], ENT_QUOTES, 'UTF-8') ?></span>
                        <span class="table-token-sub">
                            Tạo: <?= date('d/m/Y H:i', strtotime((string) $token['created_at'])) ?>
                            <?php if (!empty($token['expires_at'])): ?>
                                <br>Hết hạn: <span style="color: <?= $isExpired ? 'var(--danger)' : 'inherit' ?>;"><?= date('d/m/Y', strtotime((string) $token['expires_at'])) ?></span>
                            <?php endif; ?>
                        </span>
                    </td>
                    <td>
                        <div class="token-key-display">
                            <span class="key-text" title="<?= htmlspecialchars((string) $token['key_id'], ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars((string) $token['key_id'], ENT_QUOTES, 'UTF-8') ?></span>
                            <button type="button" class="token-icon-btn btn-copy-key" data-copy-value="<?= htmlspecialchars((string) $token['key_id'], ENT_QUOTES, 'UTF-8') ?>" title="Sao chép Key ID">
                                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <rect x="9" y="9" width="13" height="13" rx="2" ry="2"></rect>
                                    <path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"></path>
                                </svg>
                            </button>
                        </div>
                    </td>
                    <td>
                        <div class="token-key-display">
                            <span class="key-text secret-masked" id="secretText-<?= (int) $token['id'] ?>" data-raw-secret="<?= htmlspecialchars((string) $token['secret_key'], ENT_QUOTES, 'UTF-8') ?>">••••••••••••••••••••••••••••</span>
                            <button type="button" class="token-icon-btn btn-toggle-secret" data-target="secretText-<?= (int) $token['id'] ?>" title="Hiện/Ẩn Secret Key">
                                <svg class="icon-eye" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path>
                                    <circle cx="12" cy="12" r="3"></circle>
                                </svg>
                                <svg class="icon-eye-off hidden" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24"></path>
                                    <line x1="1" y1="1" x2="23" y2="23"></line>
                                </svg>
                            </button>
                            <button type="button" class="token-icon-btn btn-copy-key" data-copy-value="<?= htmlspecialchars((string) $token['secret_key'], ENT_QUOTES, 'UTF-8') ?>" title="Sao chép Secret Key">
                                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <rect x="9" y="9" width="13" height="13" rx="2" ry="2"></rect>
                                    <path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"></path>
                                </svg>
                            </button>
                        </div>
                    </td>
                    <td style="text-align: center;">
                        <span class="token-rate-badge">
                            <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <polygon points="13 2 3 14 12 14 11 22 21 10 12 10 13 2"></polygon>
                            </svg>
                            <?= (int) $token['rate_limit_per_min'] ?>/m
                        </span>
                    </td>
                    <td style="text-align: center;">
                        <span style="font-weight: 600; color: var(--slate-900); font-family: var(--font-mono); font-size: 0.85rem;">
                            <?= number_format((int) $token['total_requests']) ?>
                        </span>
                        <?php if (!empty($token['last_used_at'])): ?>
                            <div style="font-size: 0.72rem; color: var(--slate-500);" title="<?= htmlspecialchars((string) $token['last_used_at'], ENT_QUOTES, 'UTF-8') ?>">
                                <?= date('d/m H:i', strtotime((string) $token['last_used_at'])) ?>
                            </div>
                        <?php endif; ?>
                    </td>
                    <td style="text-align: center;">
                        <label class="ios-switch" title="<?= $isActive ? 'Đang hoạt động' : ($isExpired ? 'Đã hết hạn' : 'Đang tạm tắt') ?>">
                            <input type="checkbox" class="token-status-toggle" data-token-id="<?= (int) $token['id'] ?>" <?= $isActive ? 'checked' : '' ?> <?= $isExpired ? 'disabled' : '' ?>>
                            <span class="ios-switch-slider"></span>
                        </label>
                    </td>
                    <td style="text-align: center;">
                        <button type="button" class="btn-action-icon btn-delete-token" data-token-id="<?= (int) $token['id'] ?>" data-token-name="<?= htmlspecialchars((string) $token['name'], ENT_QUOTES, 'UTF-8') ?>" title="Thu hồi và xóa Token này" style="color: var(--danger);">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <polyline points="3 6 5 6 21 6"></polyline>
                                <path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path>
                            </svg>
                        </button>
                    </td>
                </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>
</section>

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
                <button type="button" class="btn primary" data-modal-close="tokenCreatedModal">Đã hiểu & Đóng</button>
            </div>
        </div>
    </div>
</div>

<?php
AdminLayout::end();
