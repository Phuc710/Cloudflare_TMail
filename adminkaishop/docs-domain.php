<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/Auth.php';
Auth::requireLogin();

require_once __DIR__ . '/../includes/AdminLayout.php';
require_once __DIR__ . '/../includes/Core/App.php';

$admin = ['username' => 'admin'];

$cfAccountId = defined('CF_ACCOUNT_ID') ? (string) CF_ACCOUNT_ID : (getenv('CF_ACCOUNT_ID') ?: '');
$cfEmailRoutingUrl = $cfAccountId !== ''
    ? 'https://dash.cloudflare.com/' . rawurlencode($cfAccountId) . '/email-service/routing'
    : 'https://dash.cloudflare.com/?to=/:account/email-service/routing';

AdminLayout::begin('Hướng dẫn domain', 'docs-domain', (string) ($admin['username'] ?? 'admin'));
?>
<div class="docs-container">
    <header class="docs-header" style="display: flex; justify-content: space-between; align-items: flex-start; gap: 16px; flex-wrap: wrap;">
        <div>
            <h1>Hướng dẫn cấu hình domain</h1>
            <p>Thiết lập DNS và Cloudflare Email Routing để nhận email cho KaiMail đúng cách, ổn định và an toàn.</p>
        </div>
        <div style="display: flex; gap: 10px; align-items: center; flex-wrap: wrap;">
            <a href="<?= htmlspecialchars($cfEmailRoutingUrl, ENT_QUOTES, 'UTF-8') ?>" target="_blank" rel="noopener noreferrer" class="btn secondary" style="display: inline-flex; align-items: center; gap: 8px;">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#f38020" stroke-width="2">
                    <path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"></path>
                    <polyline points="22,6 12,13 2,6"></polyline>
                </svg>
                Mở Cloudflare Email Routing
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"></path>
                    <polyline points="15 3 21 3 21 9"></polyline>
                    <line x1="10" y1="14" x2="21" y2="3"></line>
                </svg>
            </a>
        </div>
    </header>

    <div class="docs-content">
       
        <section style="margin-top: 24px;">
            <h2>Quy trình cấu hình chuẩn (2 Bước)</h2>
            <p style="margin-bottom: 20px; color: var(--color-text-secondary);">Vì worker của bạn đã được thiết kế
                Universal, bạn chỉ cần cấu hình DNS trên Cloudflare và thêm vào Admin là xong.</p>

            <article class="step-card">
                <h3><span class="step-number">1</span> Cấu hình trên Cloudflare Email Routing</h3>
                <p>Truy cập Cloudflare Dashboard để cấu hình nhận email cho domain (VD: <code>dewii.dpdns.org</code>):</p>
                <div style="margin: 12px 0; padding: 12px 16px; background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 8px; display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 12px;">
                    <div>
                        <strong style="color: #0f172a; font-size: 0.92rem;">Đường dẫn giao diện Cloudflare mới (2026):</strong>
                        <div style="font-family: var(--font-mono); color: #475569; font-size: 0.85rem; margin-top: 3px;">
                            Build &rarr; Compute &rarr; Email Service &rarr; <strong>Email Routing</strong>
                        </div>
                    </div>
                </div>
                <ul style="padding-left: 20px; margin-top: 10px; color: var(--color-text-secondary); line-height: 1.7;">
                    <li><strong>Bật Email Routing:</strong> Tại tab <strong>Overview</strong> hoặc <strong>Settings</strong>, nhấn <strong>Enable Email Routing</strong> để Cloudflare tự thêm và khóa các bản ghi DNS (MX & TXT SPF - trạng thái <code>Locked</code> xanh).</li>
                    <li><strong>Cấu hình luật Catch-all chuyển tiếp về Worker:</strong>
                        <div style="margin: 8px 0 4px; padding: 12px 16px; background: #f1f5f9; border-left: 3px solid var(--primary); border-radius: 6px;">
                            <p style="font-weight: 700; color: var(--slate-900); margin-bottom: 8px;">Các bước chi tiết ở tab <code>Routing rules</code>:</p>
                            <ol style="padding-left: 18px; margin: 0; line-height: 1.7; font-size: 0.88rem; color: var(--slate-700);">
                                <li>Chuyển sang tab <strong>Routing rules</strong> (ngay cạnh tab <code>Activity Log</code>).</li>
                                <li>Nhìn sang tận cùng bên phải của dòng <strong>Catch-all</strong>, bấm vào nút <strong><code>...</code></strong> (3 dấu chấm).</li>
                                <li>Chọn <strong>Edit</strong>.</li>
                                <li>Sửa lại các trường như sau:
                                    <ul style="padding-left: 18px; margin: 4px 0;">
                                        <li><strong>Action:</strong> Đổi từ <code>Drop</code> sang <code>Send to a Worker</code>.</li>
                                        <li><strong>Destination:</strong> Chọn worker <code>kaishop</code> (URL: <code>https://kaishop.phucngx0710it.workers.dev/</code>).</li>
                                        <li><strong>Status:</strong> Đảm bảo công tắc gạt bật <code>Active</code>.</li>
                                    </ul>
                                </li>
                                <li>Bấm <strong>Save</strong>. Khi thấy Action đổi từ <code>Drop</code> thành chip <code>kaishop</code> là luồng mail đã thông thẳng về Worker!</li>
                            </ol>
                        </div>
                    </li>
                </ul>
            </article>

            <article class="step-card">
                <h3><span class="step-number">2</span> Thêm domain vào KaiMail Admin</h3>
                <p>Khai báo domain để hệ thống bắt đầu chấp nhận email:</p>
                <div class="code-box" style="margin: 10px 0;">Trang quản lý: <?= htmlspecialchars(BASE_URL, ENT_QUOTES, 'UTF-8') ?>/adminkaishop/managerdomain
Nút: "Thêm domain" (Góc phải trên cùng)
Tên miền: dewii.dpdns.org
Trạng thái: Hoạt động ngay</div>
            </article>

            <article class="step-card">
                <h3><span class="step-number">3</span> Kiểm tra nhận email</h3>
                <p>Sau khi thiết lập xong, gửi thử 1 email từ bên ngoài vào bất kỳ địa chỉ nào của domain:</p>
                <div class="code-box">Ví dụ gửi mail tới: test@dewii.dpdns.org
Kiểm tra API: curl "<?= htmlspecialchars(BASE_URL, ENT_QUOTES, 'UTF-8') ?>/api/emails.php?email=test@dewii.dpdns.org"</div>
                <p style="margin-top: 10px; font-size: 0.9em; color: var(--color-text-secondary);">* Lưu ý: Khi DNS records hiển thị trạng thái <strong>Locked</strong> màu xanh là DNS Cloudflare đã nhận diện hoàn tất. Mail sẽ được Worker tiếp nhận và gửi webhook về KaiMail ngay lập tức.</p>
            </article>

            <article class="step-card" style="border-left: 4px solid var(--color-warning);">
                <h3><span class="step-number" style="background: var(--color-warning); color: #ff9500ff;">!</span> Sử dụng domain từ tài khoản Cloudflare khác?</h3>
                <p>Cloudflare không cho phép chọn Worker giữa các tài khoản khác nhau. Cách xử lý:</p>
                <ul style="padding-left: 20px; margin-top: 10px; color: var(--color-text-secondary); font-size: 0.9em; line-height: 1.6;">
                    <li><strong>Tại Tài khoản B:</strong> Tạo 1 Worker mới (ví dụ: <code>v-bridge</code>).</li>
                    <li>Copy toàn bộ code từ <code>cloudflare-worker.js</code> dán vào đó.</li>
                    <li>Cài đặt <strong>Environment Variables</strong> (<code>WEBHOOK_URL</code>, <code>WEBHOOK_SECRET</code>) giống hệt Tài khoản A.</li>
                    <li>Trong <strong>Email Routing</strong> (Tài khoản B), trỏ Catch-all về Worker vừa tạo này.</li>
                </ul>
            </article>
        </section>

        <section style="margin-top: 20px;">
            <div class="hint-box warning">
                Nên đợi tối đa 24-48 giờ sau khi đổi DNS trước khi kết luận cấu hình lỗi.
            </div>
        </section>
    </div>
</div>

<?php
AdminLayout::end();
