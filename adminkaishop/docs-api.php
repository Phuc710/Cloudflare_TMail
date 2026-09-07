<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/Auth.php';
Auth::requireLogin();

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/AdminLayout.php';

$adminName = 'admin';
$baseUrl = rtrim((string) BASE_URL, '/');
$apiBase = $baseUrl . '/api';

$apiAccessKey = defined('API_ACCESS_KEY') ? (string) API_ACCESS_KEY : (getenv('API_ACCESS_KEY') ?: '');
$apiSecretKey = defined('API_SECRET_KEY') ? (string) API_SECRET_KEY : (getenv('API_SECRET_KEY') ?: '');
$adminAccessKey = defined('ADMIN_ACCESS_KEY') ? (string) ADMIN_ACCESS_KEY : (getenv('ADMIN_ACCESS_KEY') ?: '');

AdminLayout::begin('API Integration Docs', 'docs-api', $adminName);
?>
<div class="docs-container">
    <header class="docs-header">
        <h1>API Integration Docs & Bot Reference</h1>
        <p>Tài liệu tích hợp toàn diện cho External API (Xác thực HMAC-SHA256) và Admin Management API.</p>
    </header>

    <div class="docs-content">
        <!-- SECTION: CREDENTIALS & KEYS -->
        <h2 class="docs-section-title" style="margin-top: 0;">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <rect x="3" y="11" width="18" height="11" rx="2" ry="2"></rect>
                <path d="M7 11V7a5 5 0 0 1 10 0v4"></path>
            </svg>
            Khóa Kết Nối & Khóa Bí Mật (API Credentials)
        </h2>
        <p class="docs-section-desc">
            Các khóa được trích xuất trực tiếp từ hệ thống (<code>.env</code>). Nhấn nút <strong>Copy</strong> để sao chép nhanh vào mã nguồn bot của bạn:
        </p>

        <div class="credentials-container">
            <!-- Box 1: API_ACCESS_KEY -->
            <div class="credential-box">
                <div class="credential-header">
                    <span class="credential-label">External API Key</span>
                    <span class="credential-header-badge badge-public">Public Key</span>
                </div>
                <p class="credential-desc">Định danh client khi gửi yêu cầu qua header <code>X-API-KEY</code>:</p>
                <div class="credential-value-wrapper">
                    <code title="<?= htmlspecialchars($apiAccessKey, ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($apiAccessKey, ENT_QUOTES, 'UTF-8') ?></code>
                    <button type="button" class="btn-copy-mini-cred" onclick="window.adminCore?.copyToClipboard('<?= htmlspecialchars($apiAccessKey, ENT_QUOTES, 'UTF-8') ?>')">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="9" y="9" width="13" height="13" rx="2" ry="2"></rect><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"></path></svg>
                        <span>Copy</span>
                    </button>
                </div>
            </div>

            <!-- Box 2: API_SECRET_KEY -->
            <div class="credential-box highlight">
                <div class="credential-header">
                    <span class="credential-label">HMAC Secret Key</span>
                    <span class="credential-header-badge badge-secret">Bí Mật Tuyệt Đối</span>
                </div>
                <p class="credential-desc">Khóa bí mật dùng để sinh chữ ký HMAC-SHA256 (Không để lộ ra frontend):</p>
                <div class="credential-value-wrapper">
                    <code title="<?= htmlspecialchars($apiSecretKey, ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($apiSecretKey, ENT_QUOTES, 'UTF-8') ?></code>
                    <button type="button" class="btn-copy-mini-cred" onclick="window.adminCore?.copyToClipboard('<?= htmlspecialchars($apiSecretKey, ENT_QUOTES, 'UTF-8') ?>')">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="9" y="9" width="13" height="13" rx="2" ry="2"></rect><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"></path></svg>
                        <span>Copy</span>
                    </button>
                </div>
            </div>

            <!-- Box 3: ADMIN_ACCESS_KEY -->
            <div class="credential-box">
                <div class="credential-header">
                    <span class="credential-label">Admin Master Key</span>
                    <span class="credential-header-badge badge-admin">Admin Master</span>
                </div>
                <p class="credential-desc">Toàn quyền truy cập và thao tác qua header <code>X-ADMIN-ACCESS-KEY</code>:</p>
                <div class="credential-value-wrapper">
                    <code title="<?= htmlspecialchars($adminAccessKey, ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($adminAccessKey, ENT_QUOTES, 'UTF-8') ?></code>
                    <button type="button" class="btn-copy-mini-cred" onclick="window.adminCore?.copyToClipboard('<?= htmlspecialchars($adminAccessKey, ENT_QUOTES, 'UTF-8') ?>')">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="9" y="9" width="13" height="13" rx="2" ry="2"></rect><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"></path></svg>
                        <span>Copy</span>
                    </button>
                </div>
            </div>

            <!-- Box 4: Base URL -->
            <div class="credential-box">
                <div class="credential-header">
                    <span class="credential-label">API Base URL</span>
                    <span class="credential-header-badge badge-public">Server Endpoint</span>
                </div>
                <p class="credential-desc">Đường dẫn cơ sở của toàn bộ API hệ thống:</p>
                <div class="credential-value-wrapper">
                    <code title="<?= htmlspecialchars($apiBase, ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($apiBase, ENT_QUOTES, 'UTF-8') ?></code>
                    <button type="button" class="btn-copy-mini-cred" onclick="window.adminCore?.copyToClipboard('<?= htmlspecialchars($apiBase, ENT_QUOTES, 'UTF-8') ?>')">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="9" y="9" width="13" height="13" rx="2" ry="2"></rect><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"></path></svg>
                        <span>Copy</span>
                    </button>
                </div>
            </div>
        </div>

        <div style="margin-top: 16px; background: #f0fdf4; border: 1.5px dashed #86efac; border-radius: 12px; padding: 16px 20px; display: flex; align-items: center; justify-content: space-between; gap: 16px; flex-wrap: wrap;">
            <div>
                <strong style="color: #166534; font-size: 0.95rem; display: block; margin-bottom: 4px;">🚀 Cần cấp phát Token riêng cho từng Bot hoặc Khách hàng?</strong>
                <span style="color: #15803d; font-size: 0.85rem;">Bạn có thể tạo không giới hạn API Token đa người dùng (Multi-tenant) với giới hạn Rate Limit &amp; thời hạn sử dụng độc lập mà không cần chia sẻ Root Keys.</span>
            </div>
            <a href="/adminkaishop/tokens" class="btn primary btn-sm" style="flex-shrink: 0;">Quản lý API Token &rarr;</a>
        </div>

        <!-- SECTION: AUTH HEADERS & HMAC SPECIFICATION -->
        <h2 class="docs-section-title">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"></path>
            </svg>
            Cơ Chế Xác Thực & Ký Số HMAC-SHA256
        </h2>
        <p class="docs-section-desc">
            External API yêu cầu gửi kèm <strong>4 headers</strong> bắt buộc để bảo mật, chống giả mạo và chống tấn công Replay:
        </p>

        <div class="table-container" style="margin-bottom: 16px;">
            <table class="param-table">
                <thead>
                    <tr>
                        <th style="width: 200px;">Header</th>
                        <th style="width: 140px;">Kiểu dữ liệu</th>
                        <th>Mô tả chi tiết</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td><code>X-API-KEY</code></td>
                        <td><span class="type-pill">String</span></td>
                        <td>Khóa truy cập public (giá trị của <code>API_ACCESS_KEY</code>).</td>
                    </tr>
                    <tr>
                        <td><code>X-API-TIMESTAMP</code></td>
                        <td><span class="type-pill">Integer (String)</span></td>
                        <td>Unix timestamp tại thời điểm gọi (giây). Cho phép độ lệch tối đa <strong>300 giây</strong> so với server.</td>
                    </tr>
                    <tr>
                        <td><code>X-API-NONCE</code></td>
                        <td><span class="type-pill">String</span></td>
                        <td>Chuỗi ngẫu nhiên duy nhất (16-32 ký tự hex) cho mỗi request. Server tự động hủy nonce sau khi dùng để chống replay.</td>
                    </tr>
                    <tr>
                        <td><code>X-API-SIGNATURE</code></td>
                        <td><span class="type-pill">String</span></td>
                        <td>Chữ ký điện tử dạng hex chữ thường sinh từ thuật toán <code>HMAC-SHA256</code>.</td>
                    </tr>
                </tbody>
            </table>
        </div>

        <div class="formula-card">
            <div class="formula-header">
                <span>Công thức ký Payload & Signature</span>
                <span style="color: var(--primary);">HMAC-SHA256 Standard</span>
            </div>
            <pre class="formula-body"><span style="color: #64748b;">// 1. Chuỗi Payload cần ký (5 thành phần nối nhau bằng ký tự xuống dòng \n):</span>
PAYLOAD = METHOD + "\n" + PATH + "\n" + TIMESTAMP + "\n" + NONCE + "\n" + SHA256(RAW_BODY)

<span style="color: #64748b;">// 2. Chữ ký HMAC-SHA256 chính xác:</span>
SIGNATURE = HMAC_SHA256(PAYLOAD, API_SECRET_KEY)</pre>
        </div>

        <div class="hint-box" style="margin-top: 14px;">
            <strong>Lưu ý quan trọng về PATH & BODY:</strong>
            <ul style="padding-left: 20px; margin: 6px 0 0; line-height: 1.6;">
                <li><strong>PATH:</strong> Là đường dẫn URL tương đối tính từ root domain của host (ví dụ: <code><?= htmlspecialchars(parse_url($apiBase . '/emails.php', PHP_URL_PATH), ENT_QUOTES, 'UTF-8') ?></code>). <strong>Không</strong> bao gồm query string.</li>
                <li><strong>RAW_BODY:</strong> Với request không có body (như GET), hãy hash chuỗi rỗng <code>""</code>. Kết quả SHA256 chuỗi rỗng: <code>e3b0c44298fc1c149afbf4c8996fb92427ae41e4649b934ca495991b7852b855</code>.</li>
            </ul>
        </div>

        <!-- SECTION: EXTERNAL API ENDPOINTS -->
        <h2 class="docs-section-title">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"></path>
                <polyline points="22,6 12,13 2,6"></polyline>
            </svg>
            1. Danh Sách Endpoint Cho Bot & Khách Hàng (External API)
        </h2>
        <p class="docs-section-desc">Mọi endpoint bên dưới đều yêu cầu gửi đủ 4 headers xác thực HMAC.</p>

        <!-- Endpoint 1: Create Email -->
        <div class="endpoint-card">
            <div class="endpoint-header">
                <span class="method-badge post">POST</span>
                <span class="endpoint-url"><?= htmlspecialchars(parse_url($apiBase . '/emails.php', PHP_URL_PATH), ENT_QUOTES, 'UTF-8') ?></span>
                <span class="endpoint-description" style="margin-left: auto;">Tạo hộp thư tạm thời mới (Tối đa 10 email/lần)</span>
            </div>
            <div class="endpoint-body">
                <table class="param-table">
                    <thead>
                        <tr>
                            <th style="width: 140px;">Tham số Body</th>
                            <th style="width: 110px;">Kiểu</th>
                            <th style="width: 110px;">Yêu cầu</th>
                            <th>Mô tả chi tiết</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td><code>domain</code></td>
                            <td><span class="type-pill">String</span></td>
                            <td><span class="badge-req">Bắt buộc</span></td>
                            <td>Domain đang hoạt động trong hệ thống (vd: <code>dewii.dpdns.org</code>).</td>
                        </tr>
                        <tr>
                            <td><code>name_type</code></td>
                            <td><span class="type-pill">String</span></td>
                            <td><span class="badge-opt">Tùy chọn</span></td>
                            <td><code>vn</code> (Tên Việt), <code>en</code> (Tên tiếng Anh), hoặc <code>custom</code> (Tùy chỉnh). Mặc định: <code>en</code>.</td>
                        </tr>
                        <tr>
                            <td><code>quantity</code></td>
                            <td><span class="type-pill">Integer</span></td>
                            <td><span class="badge-opt">Tùy chọn</span></td>
                            <td>Số lượng email muốn tạo (từ <code>1</code> đến <code>10</code>). Mặc định: <code>1</code>.</td>
                        </tr>
                        <tr>
                            <td><code>email</code></td>
                            <td><span class="type-pill">String</span></td>
                            <td><span class="badge-opt">Tùy chọn</span></td>
                            <td>Bắt buộc khi <code>name_type=custom</code>. Chấp nhận ký tự: <code>[a-z0-9._-]</code>.</td>
                        </tr>
                        <tr>
                            <td><code>note</code></td>
                            <td><span class="type-pill">String</span></td>
                            <td><span class="badge-opt">Tùy chọn</span></td>
                            <td>Ghi chú kèm email để bot hoặc quản trị viên dễ theo dõi.</td>
                        </tr>
                    </tbody>
                </table>
                <div class="code-box" style="margin-top: 14px;">
// Phản hồi mẫu (JSON 200):
{
  "success": true,
  "count": 1,
  "emails": [
    "dewii.user@dewii.dpdns.org"
  ]
}</div>
            </div>
        </div>

        <!-- Endpoint 2: Get Mailbox Info -->
        <div class="endpoint-card">
            <div class="endpoint-header">
                <span class="method-badge get">GET</span>
                <span class="endpoint-url"><?= htmlspecialchars(parse_url($apiBase . '/emails.php', PHP_URL_PATH), ENT_QUOTES, 'UTF-8') ?>?email=...</span>
                <span class="endpoint-description" style="margin-left: auto;">Kiểm tra trạng thái tồn tại của hộp thư</span>
            </div>
            <div class="endpoint-body">
                <table class="param-table">
                    <thead>
                        <tr>
                            <th style="width: 140px;">Tham số Query</th>
                            <th style="width: 110px;">Kiểu</th>
                            <th style="width: 110px;">Yêu cầu</th>
                            <th>Mô tả chi tiết</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td><code>email</code></td>
                            <td><span class="type-pill">String</span></td>
                            <td><span class="badge-req">Bắt buộc</span></td>
                            <td>Địa chỉ email cần kiểm tra (vd: <code>user@dewii.dpdns.org</code>).</td>
                        </tr>
                    </tbody>
                </table>
                <div class="code-box" style="margin-top: 14px;">
// Phản hồi mẫu (JSON 200):
{
  "success": true,
  "data": {
    "email": "user@dewii.dpdns.org",
    "created_at": "2026-09-07 10:30:00",
    "total_messages": 3
  }
}</div>
            </div>
        </div>

        <!-- Endpoint 3: List Messages -->
        <div class="endpoint-card">
            <div class="endpoint-header">
                <span class="method-badge get">GET</span>
                <span class="endpoint-url"><?= htmlspecialchars(parse_url($apiBase . '/messages.php', PHP_URL_PATH), ENT_QUOTES, 'UTF-8') ?>?email=...</span>
                <span class="endpoint-description" style="margin-left: auto;">Lấy danh sách thư & OTP tự động trích xuất</span>
            </div>
            <div class="endpoint-body">
                <table class="param-table">
                    <thead>
                        <tr>
                            <th style="width: 140px;">Tham số Query</th>
                            <th style="width: 110px;">Kiểu</th>
                            <th style="width: 110px;">Yêu cầu</th>
                            <th>Mô tả chi tiết</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td><code>email</code></td>
                            <td><span class="type-pill">String</span></td>
                            <td><span class="badge-req">Bắt buộc</span></td>
                            <td>Địa chỉ email cần lấy danh sách thư.</td>
                        </tr>
                        <tr>
                            <td><code>limit</code></td>
                            <td><span class="type-pill">Integer</span></td>
                            <td><span class="badge-opt">Tùy chọn</span></td>
                            <td>Số lượng thư tối đa cần lấy. Mặc định: <code>50</code>.</td>
                        </tr>
                    </tbody>
                </table>
                <div class="code-box" style="margin-top: 14px;">
// Phản hồi mẫu (JSON 200):
{
  "success": true,
  "count": 1,
  "messages": [
    {
      "id": 142,
      "sender": "service@facebook.com",
      "subject": "123456 is your security code",
      "otp_code": "123456",
      "created_at": "2026-09-07 10:45:00"
    }
  ]
}</div>
            </div>
        </div>

        <!-- Endpoint 4: Message Detail -->
        <div class="endpoint-card">
            <div class="endpoint-header">
                <span class="method-badge get">GET</span>
                <span class="endpoint-url"><?= htmlspecialchars(parse_url($apiBase . '/messages.php', PHP_URL_PATH), ENT_QUOTES, 'UTF-8') ?>?id=...&amp;email=...</span>
                <span class="endpoint-description" style="margin-left: auto;">Xem toàn bộ nội dung chi tiết một bức thư (HTML & Text)</span>
            </div>
            <div class="endpoint-body">
                <table class="param-table">
                    <thead>
                        <tr>
                            <th style="width: 140px;">Tham số Query</th>
                            <th style="width: 110px;">Kiểu</th>
                            <th style="width: 110px;">Yêu cầu</th>
                            <th>Mô tả chi tiết</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td><code>id</code></td>
                            <td><span class="type-pill">Integer</span></td>
                            <td><span class="badge-req">Bắt buộc</span></td>
                            <td>ID của tin nhắn cần đọc.</td>
                        </tr>
                        <tr>
                            <td><code>email</code></td>
                            <td><span class="type-pill">String</span></td>
                            <td><span class="badge-req">Bắt buộc</span></td>
                            <td>Email sở hữu tin nhắn để xác minh quyền truy cập.</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Endpoint 5: Long Polling -->
        <div class="endpoint-card">
            <div class="endpoint-header">
                <span class="method-badge get">GET</span>
                <span class="endpoint-url"><?= htmlspecialchars(parse_url($apiBase . '/long-poll.php', PHP_URL_PATH), ENT_QUOTES, 'UTF-8') ?>?email=...</span>
                <span class="endpoint-description" style="margin-left: auto;">Long Polling thời gian thực (Giữ kết nối tối đa 25s)</span>
            </div>
            <div class="endpoint-body">
                <p style="margin: 0 0 10px; font-size: 0.9rem; color: var(--slate-600);">
                    Giữ kết nối HTTP mở tối đa 25 giây, tự động trả về ngay khi có thư mới tới. Giúp bot nhận OTP tức thì mà không cần spam query liên tục.
                </p>
                <table class="param-table">
                    <thead>
                        <tr>
                            <th style="width: 140px;">Tham số Query</th>
                            <th style="width: 110px;">Kiểu</th>
                            <th style="width: 110px;">Yêu cầu</th>
                            <th>Mô tả chi tiết</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td><code>email</code></td>
                            <td><span class="type-pill">String</span></td>
                            <td><span class="badge-req">Bắt buộc</span></td>
                            <td>Email cần theo dõi thư mới.</td>
                        </tr>
                        <tr>
                            <td><code>last_check</code></td>
                            <td><span class="type-pill">Integer</span></td>
                            <td><span class="badge-opt">Tùy chọn</span></td>
                            <td>Unix timestamp lần kiểm tra gần nhất để tránh nhận lại thư cũ.</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- SECTION: ADMIN API -->
        <h2 class="docs-section-title">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"></polygon>
            </svg>
            2. Quản Trị Hệ Thống (Admin Master API)
        </h2>
        <p class="docs-section-desc">
            Các endpoint quản trị chỉ yêu cầu gửi header: <code>X-ADMIN-ACCESS-KEY: <?= htmlspecialchars($adminAccessKey, ENT_QUOTES, 'UTF-8') ?></code>
        </p>

        <!-- Admin Resource 1: Emails -->
        <div class="api-op-card">
            <div class="api-op-header">
                <strong style="font-size: 0.95rem; color: var(--slate-900);">Quản lý Email Toàn Hệ Thống</strong>
                <code class="endpoint-url" style="color: var(--primary); font-size: 0.88rem;"><?= htmlspecialchars(parse_url($apiBase . '/admin/emails.php', PHP_URL_PATH), ENT_QUOTES, 'UTF-8') ?></code>
            </div>
            <div class="api-op-body">
                <div class="api-sub-list">
                    <div class="api-sub-item">
                        <span class="method-badge get">GET</span>
                        <div>
                            <div class="endpoint-url">/api/admin/emails.php?page=1&amp;limit=13&amp;search=...&amp;domain=...</div>
                            <div style="color: var(--slate-600); font-size: 0.84rem; margin-top: 2px;">Lọc, tìm kiếm và phân trang danh sách email toàn hệ thống.</div>
                        </div>
                    </div>
                    <div class="api-sub-item">
                        <span class="method-badge post">POST</span>
                        <div>
                            <div class="endpoint-url">/api/admin/emails.php</div>
                            <div style="color: var(--slate-600); font-size: 0.84rem; margin-top: 2px;">Tạo hàng loạt tối đa 50 email một lần. Body JSON: <code>{"domain": "dewii.dpdns.org", "quantity": 10, "name_type": "en"}</code></div>
                        </div>
                    </div>
                    <div class="api-sub-item">
                        <span class="method-badge post">POST</span>
                        <div>
                            <div class="endpoint-url">/api/admin/emails.php (action=update_note)</div>
                            <div style="color: var(--slate-600); font-size: 0.84rem; margin-top: 2px;">Cập nhật ghi chú cho email. Body JSON: <code>{"action": "update_note", "id": 142, "note": "Acc clone TikTok"}</code></div>
                        </div>
                    </div>
                    <div class="api-sub-item">
                        <span class="method-badge delete">DELETE</span>
                        <div>
                            <div class="endpoint-url">/api/admin/emails.php</div>
                            <div style="color: var(--slate-600); font-size: 0.84rem; margin-top: 2px;">Xóa hàng loạt email theo ID. Body JSON: <code>{"ids": [1, 2, 3]}</code></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Admin Resource 2: Domains -->
        <div class="api-op-card">
            <div class="api-op-header">
                <strong style="font-size: 0.95rem; color: var(--slate-900);">Quản lý Tên Miền (Domains)</strong>
                <code class="endpoint-url" style="color: var(--primary); font-size: 0.88rem;"><?= htmlspecialchars(parse_url($apiBase . '/admin/domains.php', PHP_URL_PATH), ENT_QUOTES, 'UTF-8') ?></code>
            </div>
            <div class="api-op-body">
                <div class="api-sub-list">
                    <div class="api-sub-item">
                        <span class="method-badge get">GET</span>
                        <div>
                            <div class="endpoint-url">/api/admin/domains.php</div>
                            <div style="color: var(--slate-600); font-size: 0.84rem; margin-top: 2px;">Lấy danh sách tất cả domain kèm số lượng email và trạng thái kích hoạt.</div>
                        </div>
                    </div>
                    <div class="api-sub-item">
                        <span class="method-badge post">POST</span>
                        <div>
                            <div class="endpoint-url">/api/admin/domains.php</div>
                            <div style="color: var(--slate-600); font-size: 0.84rem; margin-top: 2px;">Thêm domain mới vào hệ thống. Body JSON: <code>{"domain": "dewii.dpdns.org", "is_active": 1}</code></div>
                        </div>
                    </div>
                    <div class="api-sub-item">
                        <span class="method-badge put">PUT</span>
                        <div>
                            <div class="endpoint-url">/api/admin/domains.php</div>
                            <div style="color: var(--slate-600); font-size: 0.84rem; margin-top: 2px;">Bật / tắt trạng thái hoạt động của domain. Body JSON: <code>{"id": 1, "is_active": 0}</code></div>
                        </div>
                    </div>
                    <div class="api-sub-item">
                        <span class="method-badge delete">DELETE</span>
                        <div>
                            <div class="endpoint-url">/api/admin/domains.php</div>
                            <div style="color: var(--slate-600); font-size: 0.84rem; margin-top: 2px;">Xóa domain khỏi hệ thống. Body JSON: <code>{"id": 1}</code> (Hệ thống tự động bảo vệ dữ liệu, chặn xóa nếu domain đang chứa email).</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Admin Resource 3: Fast Checker -->
        <div class="api-op-card">
            <div class="api-op-header">
                <span class="method-badge get" style="background: #ede9fe; color: #6d28d9; border: 1px solid #ddd6fe;">GET</span>
                <span class="endpoint-url"><?= htmlspecialchars(parse_url($apiBase . '/admin/checker.php', PHP_URL_PATH), ENT_QUOTES, 'UTF-8') ?>?keyword=otp&amp;days=7</span>
                <span class="endpoint-description" style="margin-left: auto;">Bộ quét siêu tốc (Fast Checker)</span>
            </div>
            <div class="api-op-body">
                <p style="margin: 0; font-size: 0.88rem; color: var(--slate-600); line-height: 1.5;">
                    Quét từ khóa hoặc mã OTP xuyên suốt hàng chục nghìn email trong thời gian dưới 0.1 giây bằng index tối ưu của MySQL.
                </p>
            </div>
        </div>
    </div>
</div>
<?php
AdminLayout::end();
