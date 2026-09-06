<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/Auth.php';
Auth::requireLogin();

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../includes/AdminLayout.php';

$adminName = 'admin';
$baseUrl = rtrim((string) BASE_URL, '/');
$apiBase = $baseUrl . '/api';

AdminLayout::begin('API Integration Docs', 'docs-api', $adminName);
?>
<style>
    .docs-container {
        width: 100%;
        max-width: 100%;
        margin: 0;
        color: #334155;
    }

    .docs-header {
        margin-bottom: 2rem;
        border-bottom: 1px solid #e2e8f0;
        padding-bottom: 1rem;
    }

    .docs-header h1 {
        color: #0f172a;
        font-size: 1.875rem;
        font-weight: 700;
        margin-bottom: 0.5rem;
    }

    .docs-header p {
        color: #64748b;
        font-size: 1rem;
    }

    .section-title {
        font-size: 1.25rem;
        font-weight: 600;
        color: #0f172a;
        margin: 2rem 0 1rem;
        border-left: 4px solid #10b981;
        padding-left: 1rem;
    }

    .info-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
        gap: 1rem;
        margin-bottom: 2rem;
    }

    .info-card {
        background: #fff;
        padding: 1.25rem;
        border-radius: 0.75rem;
        border: 1px solid #e2e8f0;
        box-shadow: 0 1px 2px rgba(0, 0, 0, 0.05);
    }

    .info-card h3 {
        font-size: 0.875rem;
        font-weight: 600;
        text-transform: uppercase;
        color: #64748b;
        margin-bottom: 0.5rem;
    }

    .info-card p {
        font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace;
        color: #059669;
        font-weight: 600;
        font-size: 0.95rem;
    }

    .code-wrapper {
        position: relative;
        margin: 1rem 0;
        background: #1e293b;
        border-radius: 0.5rem;
        padding: 1rem;
        overflow-x: auto;
    }

    .code-wrapper pre {
        margin: 0;
        color: #e2e8f0;
        font-family: 'Fira Code', Consolas, monospace;
        font-size: 0.875rem;
        line-height: 1.5;
    }

    .code-lang {
        position: absolute;
        top: 0;
        right: 1rem;
        font-size: 0.75rem;
        color: #94a3b8;
        font-weight: 500;
        text-transform: uppercase;
    }

    .endpoint-card {
        background: #fff;
        border: 1px solid #e2e8f0;
        border-radius: 1rem;
        overflow: hidden;
        margin-bottom: 1.5rem;
        box-shadow: 0 2px 6px rgba(0, 0, 0, 0.06);
    }

    .endpoint-header {
        padding: 1rem 1.5rem;
        background: #f8fafc;
        display: flex;
        align-items: center;
        gap: 1rem;
    }

    .method {
        padding: 0.25rem 0.625rem;
        border-radius: 0.375rem;
        font-weight: 700;
        font-size: 0.75rem;
        color: white;
    }

    .method.post {
        background: #10b981;
    }

    .method.get {
        background: #3b82f6;
    }

    .method.delete {
        background: #ef4444;
    }

    .url {
        font-family: monospace;
        font-weight: 600;
        color: #334155;
    }

    .endpoint-body {
        padding: 1.25rem 1.5rem;
    }

    .table-params {
        width: 100%;
        border-collapse: collapse;
        margin-top: 1rem;
        font-size: 0.875rem;
    }

    .table-params th,
    .table-params td {
        text-align: left;
        padding: 0.75rem;
        border-bottom: 1px solid #f1f5f9;
    }

    .table-params th {
        color: #64748b;
        font-weight: 600;
        text-transform: uppercase;
        font-size: 0.75rem;
    }

    .badge-req {
        background: #fee2e2;
        color: #b91c1c;
        padding: 0.125rem 0.375rem;
        border-radius: 0.25rem;
        font-size: 0.7rem;
        font-weight: 700;
    }

    .badge-opt {
        background: #f1f5f9;
        color: #475569;
        padding: 0.125rem 0.375rem;
        border-radius: 0.25rem;
        font-size: 0.7rem;
        font-weight: 700;
    }

    .hint-box {
        background: #fffbeb;
        border-left: 4px solid #f59e0b;
        padding: 1rem;
        border-radius: 0.5rem;
        margin: 1rem 0;
        font-size: 0.9rem;
        color: #92400e;
    }

    .hint-box code {
        background: #fef3c7;
        padding: 0.125rem 0.25rem;
        border-radius: 0.25rem;
    }
</style>

<div class="docs-container">
    <header class="docs-header">
        <h1>API Integration Docs</h1>
        <p>Single source of truth for external API (HMAC) and admin API usage.</p>
    </header>

    <div class="info-grid">
        <div class="info-card">
            <h3>Base URL</h3>
            <p><?= htmlspecialchars($apiBase, ENT_QUOTES, 'UTF-8') ?></p>
        </div>
        <div class="info-card">
            <h3>External Auth</h3>
            <p>HMAC (X-API-*)</p>
        </div>
        <div class="info-card">
            <h3>Admin Auth</h3>
            <p>X-ADMIN-ACCESS-KEY</p>
        </div>
    </div>

    <h2 class="section-title">Auth Headers</h2>
    <table class="table-params" style="margin-bottom: 1.25rem;">
        <thead>
            <tr>
                <th>Header</th>
                <th>Used By</th>
                <th>Notes</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td><code>X-API-KEY</code></td>
                <td>External API</td>
                <td>Public access key.</td>
            </tr>
            <tr>
                <td><code>X-API-TIMESTAMP</code></td>
                <td>External API</td>
                <td>Unix timestamp.</td>
            </tr>
            <tr>
                <td><code>X-API-NONCE</code></td>
                <td>External API</td>
                <td>Unique value per request.</td>
            </tr>
            <tr>
                <td><code>X-API-SIGNATURE</code></td>
                <td>External API</td>
                <td><code>HMAC-SHA256</code> signature.</td>
            </tr>
            <tr>
                <td><code>X-ADMIN-ACCESS-KEY</code></td>
                <td>Admin API</td>
                <td>Admin panel/API secret key.</td>
            </tr>
        </tbody>
    </table>

    <div class="code-wrapper">
        <div class="code-lang">HMAC Signature Base</div>
        <pre>METHOD + "\n" + PATH + "\n" + TIMESTAMP + "\n" + NONCE + "\n" + SHA256(BODY_RAW)</pre>
    </div>

    <h2 class="section-title">1. External API (HMAC)</h2>

    <div class="endpoint-card">
        <div class="endpoint-header">
            <span class="method post">POST</span>
            <span class="url">/api/emails.php</span>
        </div>
        <div class="endpoint-body">
            <p>Create one or many mailboxes.</p>
            <table class="table-params">
                <thead>
                    <tr>
                        <th>Field</th>
                        <th>Type</th>
                        <th>Required</th>
                        <th>Notes</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td><code>domain</code></td>
                        <td>string</td>
                        <td><span class="badge-req">Required</span></td>
                        <td>Must exist and be active.</td>
                    </tr>
                    <tr>
                        <td><code>name_type</code></td>
                        <td>string</td>
                        <td><span class="badge-opt">Optional</span></td>
                        <td><code>en</code> (default), <code>vn</code>, <code>custom</code>.</td>
                    </tr>
                    <tr>
                        <td><code>quantity</code></td>
                        <td>integer</td>
                        <td><span class="badge-opt">Optional</span></td>
                        <td>Range: <code>1..10</code>. Default <code>1</code>.</td>
                    </tr>
                    <tr>
                        <td><code>email</code></td>
                        <td>string</td>
                        <td><span class="badge-opt">Optional</span></td>
                        <td>Required when <code>name_type=custom</code>; lowercased by server.</td>
                    </tr>
                    <tr>
                        <td><code>note</code></td>
                        <td>string</td>
                        <td><span class="badge-opt">Optional</span></td>
                        <td>Ghi chú lưu kèm email (tối đa 500 ký tự).</td>
                    </tr>
                </tbody>
            </table>
            <div class="code-wrapper">
                <div class="code-lang">Example Body</div>
                <pre>{ "domain": "kaishop.id.vn", "name_type": "vn", "quantity": 5 }</pre>
            </div>
        </div>
    </div>

    <div class="endpoint-card">
        <div class="endpoint-header">
            <span class="method get">GET</span>
            <span class="url">/api/emails.php?email=user@domain.com</span>
        </div>
        <div class="endpoint-body">
            <p>Check if mailbox exists and get basic metadata.</p>
        </div>
    </div>

    <div class="endpoint-card">
        <div class="endpoint-header">
            <span class="method delete">DELETE</span>
            <span class="url">/api/emails.php</span>
        </div>
        <div class="endpoint-body">
            <p>Delete mailbox by full email.</p>
            <div class="code-wrapper">
                <div class="code-lang">Example Body</div>
                <pre>{ "email": "user@kaishop.id.vn" }</pre>
            </div>
        </div>
    </div>

    <div class="endpoint-card">
        <div class="endpoint-header">
            <span class="method get">GET</span>
            <span class="url">/api/messages.php?email=user@domain.com&amp;limit=30</span>
        </div>
        <div class="endpoint-body">
            <p>List messages for a mailbox.</p>
        </div>
    </div>

    <div class="endpoint-card">
        <div class="endpoint-header">
            <span class="method get">GET</span>
            <span class="url">/api/messages.php?id=123&amp;email=user@domain.com</span>
        </div>
        <div class="endpoint-body">
            <p>Get message detail by message ID. The <code>email</code> parameter is required to verify ownership of the message.</p>
        </div>
    </div>

    <div class="endpoint-card">
        <div class="endpoint-header">
            <span class="method delete">DELETE</span>
            <span class="url">/api/messages.php</span>
        </div>
        <div class="endpoint-body">
            <p>Delete selected messages or all messages in a mailbox.</p>
            <div class="code-wrapper">
                <div class="code-lang">Delete Selected</div>
                <pre>{ "email": "user@kaishop.id.vn", "ids": [101, 102] }</pre>
            </div>
            <div class="code-wrapper">
                <div class="code-lang">Delete All</div>
                <pre>{ "email": "user@kaishop.id.vn", "delete_all": true }</pre>
            </div>
        </div>
    </div>

    <div class="endpoint-card">
        <div class="endpoint-header">
            <span class="method get">GET</span>
            <span class="url">/api/long-poll.php?email_id=1&amp;email=user@domain.com&amp;last_check=YYYY-mm-dd HH:ii:ss</span>
        </div>
        <div class="endpoint-body">
            <p>Long polling for near real-time message updates. The <code>email</code> parameter is required to verify ownership.</p>
        </div>
    </div>

    <h2 class="section-title">2. Admin API (X-ADMIN-ACCESS-KEY)</h2>

    <div class="endpoint-card">
        <div class="endpoint-header">
            <span class="method post">POST</span>
            <span class="url">/api/admin/emails.php</span>
        </div>
        <div class="endpoint-body">
            <p>Create emails from admin channel.</p>
            <table class="table-params">
                <thead>
                    <tr>
                        <th>Field</th>
                        <th>Type</th>
                        <th>Required</th>
                        <th>Notes</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td><code>domain</code></td>
                        <td>string</td>
                        <td><span class="badge-req">Required</span></td>
                        <td>Target domain.</td>
                    </tr>
                    <tr>
                        <td><code>name_type</code></td>
                        <td>string</td>
                        <td><span class="badge-opt">Optional</span></td>
                        <td><code>vn</code>, <code>en</code>, <code>custom</code>.</td>
                    </tr>
                    <tr>
                        <td><code>quantity</code></td>
                        <td>integer</td>
                        <td><span class="badge-opt">Optional</span></td>
                        <td>Range: <code>1..50</code>. Default <code>1</code>.</td>
                    </tr>
                    <tr>
                        <td><code>email</code></td>
                        <td>string</td>
                        <td><span class="badge-opt">Optional</span></td>
                        <td>When custom, allowed chars: <code>[A-Za-z0-9._-]</code>, lowercased.</td>
                    </tr>
                    <tr>
                        <td><code>note</code></td>
                        <td>string</td>
                        <td><span class="badge-opt">Optional</span></td>
                        <td>Ghi chú lưu kèm email khi tạo.</td>
                    </tr>
                </tbody>
            </table>
            <div class="code-wrapper">
                <div class="code-lang">Example Body</div>
                <pre>{ "name_type": "custom", "email": "Admin.Test", "domain": "kaishop.id.vn", "quantity": 1, "note": "Email test" }</pre>
            </div>
        </div>
    </div>

    <div class="endpoint-card">
        <div class="endpoint-header">
            <span class="method get">GET</span>
            <span class="url">/api/admin/emails.php?page=1&amp;limit=13&amp;search=&amp;domain=&amp;created_by=&amp;no_message=1</span>
        </div>
        <div class="endpoint-body">
            <p>List emails with pagination and multi-dimensional filters:</p>
            <table class="table-params">
                <thead>
                    <tr>
                        <th>Param</th>
                        <th>Type</th>
                        <th>Notes</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td><code>page</code></td>
                        <td>integer</td>
                        <td>Trang hiện tại. Default <code>1</code>.</td>
                    </tr>
                    <tr>
                        <td><code>limit</code></td>
                        <td>integer</td>
                        <td>Số bản ghi/trang. Default <code>13</code>.</td>
                    </tr>
                    <tr>
                        <td><code>search</code></td>
                        <td>string</td>
                        <td>Tìm kiếm theo địa chỉ email hoặc nội dung ghi chú (note).</td>
                    </tr>
                    <tr>
                        <td><code>domain</code></td>
                        <td>string</td>
                        <td>Lọc theo tên miền cụ thể.</td>
                    </tr>
                    <tr>
                        <td><code>created_by</code></td>
                        <td>string</td>
                        <td>Lọc theo nguồn tạo: <code>admin</code>, <code>api</code>, hoặc <code>user</code>.</td>
                    </tr>
                    <tr>
                        <td><code>no_message</code></td>
                        <td>string</td>
                        <td>Truyền <code>1</code> để chỉ lấy các email chưa có tin nhắn.</td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>

    <div class="endpoint-card">
        <div class="endpoint-header">
            <span class="method post">POST</span>
            <span class="url">/api/admin/emails.php (action: update_note)</span>
        </div>
        <div class="endpoint-body">
            <p>Cập nhật hoặc xóa ghi chú (note) cho email.</p>
            <table class="table-params">
                <thead>
                    <tr>
                        <th>Field</th>
                        <th>Type</th>
                        <th>Required</th>
                        <th>Notes</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td><code>action</code></td>
                        <td>string</td>
                        <td><span class="badge-req">Required</span></td>
                        <td>Cố định: <code>update_note</code>.</td>
                    </tr>
                    <tr>
                        <td><code>id</code></td>
                        <td>integer</td>
                        <td><span class="badge-req">Required</span></td>
                        <td>ID của email trong CSDL.</td>
                    </tr>
                    <tr>
                        <td><code>note</code></td>
                        <td>string</td>
                        <td><span class="badge-opt">Optional</span></td>
                        <td>Chuỗi ghi chú mới (tối đa 500 ký tự). Bỏ trống để xóa ghi chú.</td>
                    </tr>
                </tbody>
            </table>
            <div class="code-wrapper">
                <div class="code-lang">Example Body</div>
                <pre>{ "action": "update_note", "id": 105, "note": "Ghi chú tài khoản VIP" }</pre>
            </div>
        </div>
    </div>

    <div class="endpoint-card">
        <div class="endpoint-header">
            <span class="method delete">DELETE</span>
            <span class="url">/api/admin/emails.php</span>
        </div>
        <div class="endpoint-body">
            <p>Delete selected emails by ID list.</p>
            <div class="code-wrapper">
                <div class="code-lang">Example Body</div>
                <pre>{ "ids": [11, 12, 13] }</pre>
            </div>
        </div>
    </div>

    <div class="endpoint-card">
        <div class="endpoint-header">
            <span class="method get">GET</span>
            <span class="method post" style="margin-left: 4px;">POST</span>
            <span class="method put" style="margin-left: 4px;">PUT</span>
            <span class="method delete" style="margin-left: 4px;">DEL</span>
            <span class="url" style="margin-left: 8px;">/api/admin/domains.php</span>
        </div>
        <div class="endpoint-body">
            <p>Quản lý danh sách domain của hệ thống:</p>
            <ul style="padding-left: 1.25rem; line-height: 1.7; color: #475569; font-size: 0.9rem;">
                <li><code>GET /api/admin/domains.php</code>: Lấy danh sách toàn bộ domain kèm số lượng email trực thuộc.</li>
                <li><code>POST /api/admin/domains.php</code>: Thêm domain mới (Body: <code>{"domain": "example.com"}</code>).</li>
                <li><code>PUT /api/admin/domains.php</code>: Bật/tắt trạng thái hoạt động (Body: <code>{"id": 1, "is_active": 1}</code>).</li>
                <li><code>DELETE /api/admin/domains.php</code>: Xóa domain (Body: <code>{"id": 1}</code>; tự động chặn xóa nếu domain đang có email).</li>
            </ul>
        </div>
    </div>

    <h2 class="section-title">3. Performance API (Fast Checker)</h2>

    <div class="endpoint-card">
        <div class="endpoint-header">
            <span class="method get" style="background: #8b5cf6;">GET</span>
            <span class="url">/api/admin/checker.php?keyword=deactivating&amp;domain=kaishop.id.vn</span>
        </div>
        <div class="endpoint-body">
            <p>Quét nhanh nội dung email hàng loạt (Batch scanning). Trả về danh sách email và tin nhắn khớp từ khóa
                trong <1s.< /p>
                    <table class="table-params">
                        <thead>
                            <tr>
                                <th>Field</th>
                                <th>Type</th>
                                <th>Required</th>
                                <th>Notes</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td><code>keyword</code></td>
                                <td>string</td>
                                <td><span class="badge-req">Required</span></td>
                                <td>Từ khóa cần quét (vd: <code>deactivating</code>).</td>
                            </tr>
                            <tr>
                                <td><code>domain</code></td>
                                <td>string</td>
                                <td><span class="badge-opt">Optional</span></td>
                                <td>Lọc theo tên miền.</td>
                            </tr>
                            <tr>
                                <td><code>days</code></td>
                                <td>integer</td>
                                <td><span class="badge-opt">Optional</span></td>
                                <td>Quét trong X ngày gần nhất. Default: <code>7</code>.</td>
                            </tr>
                            <tr>
                                <td><code>limit</code></td>
                                <td>integer</td>
                                <td><span class="badge-opt">Optional</span></td>
                                <td>Giới hạn kết quả. Max <code>500</code>.</td>
                            </tr>
                        </tbody>
                    </table>

                    <div class="hint-box" style="margin-top: 1rem;">
                        <strong>🔥 Tối ưu hóa tốc độ:</strong> Để đạt tốc độ &lt; 1s khi dữ liệu lớn, hãy chạy lệnh SQL
                        này trong database:<br>
                        <code>ALTER TABLE messages ADD FULLTEXT INDEX idx_content_search (subject, body_text);</code>
                    </div>

                    <div class="code-wrapper">
                        <div class="code-lang">Example Response</div>
                        <pre>{
  "success": true,
  "count": 1,
  "keyword": "deactivating",
  "results": [
    {
      "email": "jeffreyrivera608@kaishop.id.vn",
      "subject": "ChatGPT - Account Deactivation",
      "received_at": "2026-03-18 08:35:00"
    }
  ],
  "execution_time": "0.045s"
}</pre>
                    </div>
        </div>
    </div>

    <div class="hint-box">
        <strong>Rate Limit:</strong> API endpoints are rate-limited. If exceeded, server returns <code>429</code>.
    </div>
</div>

<?php
AdminLayout::end();
