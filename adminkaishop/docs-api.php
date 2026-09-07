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
<style>
    .docs-container {
        width: 100%;
        max-width: 100%;
        margin: 0;
        color: var(--slate-700);
    }

    .docs-header {
        margin-bottom: 2rem;
        border-bottom: 1px solid var(--border-subtle);
        padding-bottom: 1.25rem;
    }

    .docs-header h1 {
        color: var(--slate-900);
        font-size: 1.875rem;
        font-weight: 800;
        letter-spacing: -0.025em;
        margin-bottom: 0.5rem;
    }

    .docs-header p {
        color: var(--slate-500);
        font-size: 1rem;
    }

    .section-title {
        font-size: 1.25rem;
        font-weight: 700;
        color: var(--slate-900);
        margin: 2.25rem 0 1rem;
        border-left: 4px solid var(--primary);
        padding-left: 1rem;
        display: flex;
        align-items: center;
        gap: 8px;
    }

    .endpoint-card {
        background: #fff;
        border: 1px solid var(--border-subtle);
        border-radius: var(--radius-lg);
        overflow: hidden;
        margin-bottom: 1.5rem;
        box-shadow: var(--shadow-xs);
    }

    .endpoint-header {
        padding: 1rem 1.5rem;
        background: var(--slate-50);
        border-bottom: 1px solid var(--border-subtle);
        display: flex;
        align-items: center;
        gap: 1rem;
        flex-wrap: wrap;
    }

    .method {
        padding: 0.25rem 0.625rem;
        border-radius: var(--radius-sm);
        font-weight: 800;
        font-size: 0.75rem;
        color: white;
        text-transform: uppercase;
        letter-spacing: 0.05em;
    }

    .method.post { background: #059669; }
    .method.get { background: #2563eb; }
    .method.put { background: #d97706; }
    .method.delete { background: #dc2626; }

    .url {
        font-family: var(--font-mono);
        font-weight: 600;
        color: var(--slate-900);
        font-size: 0.95rem;
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
        border-bottom: 1px solid var(--border-subtle);
    }

    .table-params th {
        color: var(--slate-500);
        font-weight: 700;
        text-transform: uppercase;
        font-size: 0.75rem;
        background: var(--slate-50);
    }

    .badge-req {
        background: #fee2e2;
        color: #b91c1c;
        padding: 0.15rem 0.5rem;
        border-radius: var(--radius-full);
        font-size: 0.7rem;
        font-weight: 700;
    }

    .badge-opt {
        background: var(--slate-100);
        color: var(--slate-600);
        padding: 0.15rem 0.5rem;
        border-radius: var(--radius-full);
        font-size: 0.7rem;
        font-weight: 700;
    }

    .hint-box {
        background: #fffbeb;
        border-left: 4px solid #f59e0b;
        padding: 1rem 1.25rem;
        border-radius: var(--radius-sm);
        margin: 1rem 0;
        font-size: 0.9rem;
        color: #92400e;
    }

    .hint-box code {
        background: #fef3c7;
        padding: 0.15rem 0.4rem;
        border-radius: 4px;
        font-family: var(--font-mono);
        font-weight: 600;
    }

    .code-wrapper {
        position: relative;
        margin: 1rem 0;
        background: var(--slate-900);
        border-radius: var(--radius-md);
        padding: 1rem 1.25rem;
        overflow-x: auto;
    }

    .code-wrapper pre {
        margin: 0;
        color: #e2e8f0;
        font-family: var(--font-mono);
        font-size: 0.875rem;
        line-height: 1.55;
    }

    .code-header-bar {
        display: flex;
        justify-content: space-between;
        align-items: center;
        background: var(--slate-800);
        padding: 8px 14px;
        border-top-left-radius: var(--radius-md);
        border-top-right-radius: var(--radius-md);
        border-bottom: 1px solid rgba(255, 255, 255, 0.1);
    }

    .code-title {
        color: var(--slate-300);
        font-size: 0.8rem;
        font-weight: 600;
        display: flex;
        align-items: center;
        gap: 6px;
    }

    .btn-copy-code {
        background: rgba(255, 255, 255, 0.1);
        border: 1px solid rgba(255, 255, 255, 0.2);
        color: #fff;
        padding: 4px 10px;
        border-radius: 6px;
        font-size: 0.75rem;
        font-weight: 600;
        cursor: pointer;
        transition: all var(--transition-fast);
        display: inline-flex;
        align-items: center;
        gap: 4px;
    }

    .btn-copy-code:hover {
        background: var(--primary);
        border-color: var(--primary);
    }

    .tab-content-panel {
        display: none;
    }

    .tab-content-panel.active {
        display: block;
    }
</style>

<div class="docs-container">
    <header class="docs-header">
        <h1>API Integration Docs & Bot Reference</h1>
        <p>Tài liệu tích hợp toàn diện cho External API (Xác thực HMAC-SHA256) và Admin Management API.</p>
    </header>

    <!-- SECTION: CREDENTIALS & KEYS -->
    <h2 class="section-title">
        <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <rect x="3" y="11" width="18" height="11" rx="2" ry="2"></rect>
            <path d="M7 11V7a5 5 0 0 1 10 0v4"></path>
        </svg>
        Khóa Kết Nối & Khóa Bí Mật (API Credentials)
    </h2>
    <p style="color: var(--slate-500); margin-bottom: 14px; font-size: 0.92rem;">
        Các khóa được trích xuất trực tiếp từ hệ thống (<code>.env</code>). Nhấn nút <strong>Copy</strong> để sao chép nhanh vào mã nguồn bot của bạn:
    </p>

    <div class="credentials-container">
        <!-- Box 1: API_ACCESS_KEY -->
        <div class="credential-box">
            <div class="credential-header">
                <span class="credential-label">External API Key (Header: X-API-KEY)</span>
                <span class="credential-header-badge badge-public">Public Key</span>
            </div>
            <p style="font-size: 0.82rem; color: var(--slate-500); margin: 0 0 6px;">Định danh client khi gửi yêu cầu đến External API:</p>
            <div class="credential-value-wrapper">
                <code><?= htmlspecialchars($apiAccessKey, ENT_QUOTES, 'UTF-8') ?></code>
                <button type="button" class="btn-copy-mini-cred" onclick="window.adminCore?.copyToClipboard('<?= htmlspecialchars($apiAccessKey, ENT_QUOTES, 'UTF-8') ?>')">
                    Copy
                </button>
            </div>
        </div>

        <!-- Box 2: API_SECRET_KEY -->
        <div class="credential-box highlight">
            <div class="credential-header">
                <span class="credential-label">HMAC Secret Key (Dùng Ký Signature)</span>
                <span class="credential-header-badge badge-secret">Bí Mật Tuyệt Đối</span>
            </div>
            <p style="font-size: 0.82rem; color: var(--slate-500); margin: 0 0 6px;">Khóa bí mật dùng để sinh chữ ký HMAC-SHA256 (Không để lộ ra frontend):</p>
            <div class="credential-value-wrapper">
                <code><?= htmlspecialchars($apiSecretKey, ENT_QUOTES, 'UTF-8') ?></code>
                <button type="button" class="btn-copy-mini-cred" onclick="window.adminCore?.copyToClipboard('<?= htmlspecialchars($apiSecretKey, ENT_QUOTES, 'UTF-8') ?>')">
                    Copy
                </button>
            </div>
        </div>

        <!-- Box 3: ADMIN_ACCESS_KEY -->
        <div class="credential-box">
            <div class="credential-header">
                <span class="credential-label">Admin Master Key (Header: X-ADMIN-ACCESS-KEY)</span>
                <span class="credential-header-badge badge-admin">Admin Key</span>
            </div>
            <p style="font-size: 0.82rem; color: var(--slate-500); margin: 0 0 6px;">Toàn quyền truy cập và thao tác trên mọi API quản trị <code>/api/admin/*</code>:</p>
            <div class="credential-value-wrapper">
                <code><?= htmlspecialchars($adminAccessKey, ENT_QUOTES, 'UTF-8') ?></code>
                <button type="button" class="btn-copy-mini-cred" onclick="window.adminCore?.copyToClipboard('<?= htmlspecialchars($adminAccessKey, ENT_QUOTES, 'UTF-8') ?>')">
                    Copy
                </button>
            </div>
        </div>

        <!-- Box 4: Base URL -->
        <div class="credential-box">
            <div class="credential-header">
                <span class="credential-label">API Base URL</span>
                <span class="credential-header-badge badge-public">Server Endpoint</span>
            </div>
            <p style="font-size: 0.82rem; color: var(--slate-500); margin: 0 0 6px;">Đường dẫn cơ sở của toàn bộ API hệ thống:</p>
            <div class="credential-value-wrapper">
                <code><?= htmlspecialchars($apiBase, ENT_QUOTES, 'UTF-8') ?></code>
                <button type="button" class="btn-copy-mini-cred" onclick="window.adminCore?.copyToClipboard('<?= htmlspecialchars($apiBase, ENT_QUOTES, 'UTF-8') ?>')">
                    Copy
                </button>
            </div>
        </div>
    </div>

    <!-- SECTION: AUTH HEADERS & HMAC SPECIFICATION -->
    <h2 class="section-title">
        <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"></path>
        </svg>
        Cơ Chế Xác Thực & Ký Số HMAC-SHA256
    </h2>
    <p style="color: var(--slate-600); margin-bottom: 12px; font-size: 0.92rem;">
        External API yêu cầu gửi kèm <strong>4 headers</strong> bắt buộc để chống giả mạo và chống tấn công Replay Attack:
    </p>

    <table class="table-params" style="margin-bottom: 1.25rem;">
        <thead>
            <tr>
                <th>Header</th>
                <th>Kiểu dữ liệu</th>
                <th>Mô tả chi tiết</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td><code>X-API-KEY</code></td>
                <td>String</td>
                <td>Khóa truy cập public (giá trị của <code>API_ACCESS_KEY</code>).</td>
            </tr>
            <tr>
                <td><code>X-API-TIMESTAMP</code></td>
                <td>Integer (String)</td>
                <td>Unix timestamp tại thời điểm gọi (giây). Cho phép độ lệch tối đa <strong>300 giây</strong> so với server.</td>
            </tr>
            <tr>
                <td><code>X-API-NONCE</code></td>
                <td>String</td>
                <td>Chuỗi ngẫu nhiên duy nhất (16-32 ký tự hex) cho mỗi request. Server tự động hủy nonce sau khi dùng để chống replay.</td>
            </tr>
            <tr>
                <td><code>X-API-SIGNATURE</code></td>
                <td>String</td>
                <td>Chữ ký điện tử dạng hex chữ thường sinh từ thuật toán <code>HMAC-SHA256</code>.</td>
            </tr>
        </tbody>
    </table>

    <div class="code-wrapper">
        <div class="code-lang" style="position: absolute; top: 12px; right: 16px; color: #94a3b8; font-size: 0.75rem; text-transform: uppercase;">Công thức chuẩn</div>
        <pre><span style="color: #94a3b8;">// 1. Chuỗi Payload cần ký (5 thành phần nối nhau bằng ký tự xuống dòng \n):</span>
PAYLOAD = METHOD + "\n" + PATH + "\n" + TIMESTAMP + "\n" + NONCE + "\n" + SHA256(RAW_BODY)

<span style="color: #94a3b8;">// 2. Chữ ký HMAC-SHA256 chính xác:</span>
SIGNATURE = HMAC_SHA256(PAYLOAD, API_SECRET_KEY)</pre>
    </div>

    <div class="hint-box">
        <strong>⚠️ Lưu ý về PATH & BODY:</strong><br>
        • <code>PATH</code>: Là đường dẫn URL tương đối tính từ root domain của host (ví dụ: <code><?= htmlspecialchars(parse_url($apiBase . '/emails.php', PHP_URL_PATH), ENT_QUOTES, 'UTF-8') ?></code>). <strong>Không</strong> bao gồm query string.<br>
        • <code>RAW_BODY</code>: Với request không có body (như GET), hãy hash chuỗi rỗng <code>""</code>. Kết quả SHA256 chuỗi rỗng là: <code>e3b0c44298fc1c149afbf4c8996fb92427ae41e4649b934ca495991b7852b855</code>.
    </div>

    <!-- SECTION: READY-TO-RUN SDK EXAMPLES -->
    <h2 class="section-title">
        <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <polyline points="16 18 22 12 16 6"></polyline>
            <polyline points="8 6 2 12 8 18"></polyline>
        </svg>
        Mã Nguồn Mẫu (Ready-to-Run Code)
    </h2>
    <p style="color: var(--slate-600); margin-bottom: 12px; font-size: 0.92rem;">
        Mã nguồn mẫu đầy đủ, đã được điền sẵn thông tin khóa của hệ thống bạn. Chọn ngôn ngữ bạn sử dụng để copy:
    </p>

    <div class="code-tabs-wrapper">
        <div class="code-tabs-nav" id="sdkNavTabs">
            <button type="button" class="code-tab-btn active" data-tab-target="tab_python">🐍 Python 3</button>
            <button type="button" class="code-tab-btn" data-tab-target="tab_nodejs">🟨 Node.js</button>
            <button type="button" class="code-tab-btn" data-tab-target="tab_php">🐘 PHP</button>
            <button type="button" class="code-tab-btn" data-tab-target="tab_curl">💻 cURL (Bash)</button>
        </div>

        <!-- TAB: PYTHON -->
        <div class="tab-content-panel active" id="tab_python">
            <div class="code-header-bar">
                <span class="code-title">Python 3 (yêu cầu thư viện requests)</span>
                <button type="button" class="btn-copy-code" onclick="copyCodeFromElement('pythonCodeBlock')">Sao chép Python</button>
            </div>
            <div class="code-wrapper" style="margin-top: 0; border-top-left-radius: 0; border-top-right-radius: 0;">
                <pre id="pythonCodeBlock">import time
import secrets
import hashlib
import hmac
import json
import requests

API_KEY = "<?= htmlspecialchars($apiAccessKey, ENT_QUOTES, 'UTF-8') ?>"
API_SECRET = "<?= htmlspecialchars($apiSecretKey, ENT_QUOTES, 'UTF-8') ?>"
BASE_URL = "<?= htmlspecialchars($baseUrl, ENT_QUOTES, 'UTF-8') ?>"

def call_kaimail_api(method: str, path: str, data: dict = None, params: dict = None):
    ts = str(int(time.time()))
    nonce = secrets.token_hex(16)
    body_str = json.dumps(data) if data else ""
    body_hash = hashlib.sha256(body_str.encode("utf-8")).hexdigest()

    # Tạo chuỗi ký
    payload = f"{method.upper()}\n{path}\n{ts}\n{nonce}\n{body_hash}"
    signature = hmac.new(API_SECRET.encode("utf-8"), payload.encode("utf-8"), hashlib.sha256).hexdigest()

    headers = {
        "Content-Type": "application/json",
        "X-API-KEY": API_KEY,
        "X-API-TIMESTAMP": ts,
        "X-API-NONCE": nonce,
        "X-API-SIGNATURE": signature
    }

    url = f"{BASE_URL}{path}"
    resp = requests.request(method.upper(), url, headers=headers, data=body_str if body_str else None, params=params)
    return resp.json()

# --- VÍ DỤ SỬ DỤNG ---
# 1. Tạo 1 hộp thư tiếng Việt mới
print("--- 1. Tạo email ---")
create_res = call_kaimail_api("POST", "<?= htmlspecialchars(parse_url($apiBase . '/emails.php', PHP_URL_PATH), ENT_QUOTES, 'UTF-8') ?>", {
    "domain": "kaishop.id.vn",
    "name_type": "vn",
    "quantity": 1
})
print(create_res)

# 2. Lấy danh sách tin nhắn của email
if create_res.get("success") and create_res.get("emails"):
    email_addr = create_res["emails"][0]
    print(f"\n--- 2. Lấy thư của {email_addr} ---")
    messages = call_kaimail_api("GET", "<?= htmlspecialchars(parse_url($apiBase . '/messages.php', PHP_URL_PATH), ENT_QUOTES, 'UTF-8') ?>", params={"email": email_addr})
    print(messages)
</pre>
            </div>
        </div>

        <!-- TAB: NODEJS -->
        <div class="tab-content-panel" id="tab_nodejs">
            <div class="code-header-bar">
                <span class="code-title">Node.js (sử dụng module crypto có sẵn & fetch)</span>
                <button type="button" class="btn-copy-code" onclick="copyCodeFromElement('nodejsCodeBlock')">Sao chép Node.js</button>
            </div>
            <div class="code-wrapper" style="margin-top: 0; border-top-left-radius: 0; border-top-right-radius: 0;">
                <pre id="nodejsCodeBlock">import crypto from "crypto";

const API_KEY = "<?= htmlspecialchars($apiAccessKey, ENT_QUOTES, 'UTF-8') ?>";
const API_SECRET = "<?= htmlspecialchars($apiSecretKey, ENT_QUOTES, 'UTF-8') ?>";
const BASE_URL = "<?= htmlspecialchars($baseUrl, ENT_QUOTES, 'UTF-8') ?>";

async function callKaiMailApi(method, path, data = null, queryParams = {}) {
    const ts = Math.floor(Date.now() / 1000).toString();
    const nonce = crypto.randomBytes(16).toString("hex");
    const bodyStr = data ? JSON.stringify(data) : "";
    const bodyHash = crypto.createHash("sha256").update(bodyStr).digest("hex");

    const payload = `${method.toUpperCase()}\n${path}\n${ts}\n${nonce}\n${bodyHash}`;
    const signature = crypto.createHmac("sha256", API_SECRET).update(payload).digest("hex");

    const headers = {
        "Content-Type": "application/json",
        "X-API-KEY": API_KEY,
        "X-API-TIMESTAMP": ts,
        "X-API-NONCE": nonce,
        "X-API-SIGNATURE": signature,
    };

    let url = `${BASE_URL}${path}`;
    const qs = new URLSearchParams(queryParams).toString();
    if (qs) url += `?${qs}`;

    const res = await fetch(url, {
        method: method.toUpperCase(),
        headers,
        body: bodyStr || undefined,
    });

    return await res.json();
}

// --- VÍ DỤ SỬ DỤNG ---
(async () => {
    // 1. Tạo 1 email tiếng Anh
    console.log("--- 1. Tạo email ---");
    const created = await callKaiMailApi("POST", "<?= htmlspecialchars(parse_url($apiBase . '/emails.php', PHP_URL_PATH), ENT_QUOTES, 'UTF-8') ?>", {
        domain: "kaishop.id.vn",
        name_type: "en",
        quantity: 1,
    });
    console.log(created);

    // 2. Lấy hộp thư
    if (created.success && created.emails?.length) {
        const email = created.emails[0];
        console.log(`\n--- 2. Lấy thư của ${email} ---`);
        const inbox = await callKaiMailApi("GET", "<?= htmlspecialchars(parse_url($apiBase . '/messages.php', PHP_URL_PATH), ENT_QUOTES, 'UTF-8') ?>", null, { email });
        console.log(inbox);
    }
})();
</pre>
            </div>
        </div>

        <!-- TAB: PHP -->
        <div class="tab-content-panel" id="tab_php">
            <div class="code-header-bar">
                <span class="code-title">PHP (curl + hash_hmac)</span>
                <button type="button" class="btn-copy-code" onclick="copyCodeFromElement('phpCodeBlock')">Sao chép PHP</button>
            </div>
            <div class="code-wrapper" style="margin-top: 0; border-top-left-radius: 0; border-top-right-radius: 0;">
                <pre id="phpCodeBlock">&lt;?php
$apiKey = "<?= htmlspecialchars($apiAccessKey, ENT_QUOTES, 'UTF-8') ?>";
$apiSecret = "<?= htmlspecialchars($apiSecretKey, ENT_QUOTES, 'UTF-8') ?>";
$baseUrl = "<?= htmlspecialchars($baseUrl, ENT_QUOTES, 'UTF-8') ?>";

function callKaiMailApi(string $method, string $path, ?array $data = null, array $queryParams = []): array {
    global $apiKey, $apiSecret, $baseUrl;

    $ts = (string) time();
    $nonce = bin2hex(random_bytes(16));
    $bodyStr = ($data !== null) ? json_encode($data, JSON_UNESCAPED_SLASHES) : "";
    $bodyHash = hash('sha256', $bodyStr);

    $payload = strtoupper($method) . "\n" . $path . "\n" . $ts . "\n" . $nonce . "\n" . $bodyHash;
    $signature = hash_hmac('sha256', $payload, $apiSecret);

    $url = $baseUrl . $path;
    if (!empty($queryParams)) {
        $url .= '?' . http_build_query($queryParams);
    }

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, strtoupper($method));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'X-API-KEY: ' . $apiKey,
        'X-API-TIMESTAMP: ' . $ts,
        'X-API-NONCE: ' . $nonce,
        'X-API-SIGNATURE: ' . $signature,
    ]);

    if ($bodyStr !== "") {
        curl_setopt($ch, CURLOPT_POSTFIELDS, $bodyStr);
    }

    $response = curl_exec($ch);
    curl_close($ch);

    return json_decode((string) $response, true) ?: [];
}

// 1. Tạo hộp thư
$result = callKaiMailApi("POST", "<?= htmlspecialchars(parse_url($apiBase . '/emails.php', PHP_URL_PATH), ENT_QUOTES, 'UTF-8') ?>", [
    "domain" => "kaishop.id.vn",
    "name_type" => "vn",
    "quantity" => 1
]);
print_r($result);
</pre>
            </div>
        </div>

        <!-- TAB: CURL BASH -->
        <div class="tab-content-panel" id="tab_curl">
            <div class="code-header-bar">
                <span class="code-title">Bash cURL (Tự động tính chữ ký với openssl)</span>
                <button type="button" class="btn-copy-code" onclick="copyCodeFromElement('curlCodeBlock')">Sao chép cURL</button>
            </div>
            <div class="code-wrapper" style="margin-top: 0; border-top-left-radius: 0; border-top-right-radius: 0;">
                <pre id="curlCodeBlock">#!/usr/bin/env bash

API_KEY="<?= htmlspecialchars($apiAccessKey, ENT_QUOTES, 'UTF-8') ?>"
API_SECRET="<?= htmlspecialchars($apiSecretKey, ENT_QUOTES, 'UTF-8') ?>"
BASE_URL="<?= htmlspecialchars($baseUrl, ENT_QUOTES, 'UTF-8') ?>"

METHOD="POST"
PATH="<?= htmlspecialchars(parse_url($apiBase . '/emails.php', PHP_URL_PATH), ENT_QUOTES, 'UTF-8') ?>"
BODY='{"domain":"kaishop.id.vn","name_type":"vn","quantity":1}'

TS=$(date +%s)
NONCE=$(openssl rand -hex 16)
BODY_HASH=$(printf "%s" "$BODY" | openssl dgst -sha256 -r | cut -d ' ' -f 1)

PAYLOAD="${METHOD}
${PATH}
${TS}
${NONCE}
${BODY_HASH}"

SIGNATURE=$(printf "%s" "$PAYLOAD" | openssl dgst -sha256 -hmac "$API_SECRET" -r | cut -d ' ' -f 1)

curl -X "$METHOD" "${BASE_URL}${PATH}" \
  -H "Content-Type: application/json" \
  -H "X-API-KEY: $API_KEY" \
  -H "X-API-TIMESTAMP: $TS" \
  -H "X-API-NONCE: $NONCE" \
  -H "X-API-SIGNATURE: $SIGNATURE" \
  -d "$BODY"
</pre>
            </div>
        </div>
    </div>

    <!-- SECTION: EXTERNAL API ENDPOINTS -->
    <h2 class="section-title">
        <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"></path>
            <polyline points="22,6 12,13 2,6"></polyline>
        </svg>
        1. Danh Sách Endpoint Cho Bot & Khách Hàng (External API)
    </h2>

    <!-- Endpoint 1: Create Email -->
    <div class="endpoint-card">
        <div class="endpoint-header">
            <span class="method post">POST</span>
            <span class="url"><?= htmlspecialchars(parse_url($apiBase . '/emails.php', PHP_URL_PATH), ENT_QUOTES, 'UTF-8') ?></span>
        </div>
        <div class="endpoint-body">
            <p>Tạo một hoặc nhiều hộp thư tạm thời mới (Tối đa 10 email/lần cho External API).</p>
            <table class="table-params">
                <thead>
                    <tr>
                        <th>Trường</th>
                        <th>Kiểu</th>
                        <th>Yêu cầu</th>
                        <th>Mô tả</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td><code>domain</code></td>
                        <td>string</td>
                        <td><span class="badge-req">Bắt buộc</span></td>
                        <td>Domain đang hoạt động trong hệ thống (vd: <code>kaishop.id.vn</code>).</td>
                    </tr>
                    <tr>
                        <td><code>name_type</code></td>
                        <td>string</td>
                        <td><span class="badge-opt">Tùy chọn</span></td>
                        <td><code>vn</code> (Tên người Việt), <code>en</code> (Tên tiếng Anh), hoặc <code>custom</code> (Tùy chỉnh). Mặc định: <code>en</code>.</td>
                    </tr>
                    <tr>
                        <td><code>quantity</code></td>
                        <td>integer</td>
                        <td><span class="badge-opt">Tùy chọn</span></td>
                        <td>Số lượng email muốn tạo (từ <code>1</code> đến <code>10</code>). Mặc định: <code>1</code>.</td>
                    </tr>
                    <tr>
                        <td><code>email</code></td>
                        <td>string</td>
                        <td><span class="badge-opt">Tùy chọn</span></td>
                        <td>Chỉ bắt buộc khi <code>name_type=custom</code>. Chấp nhận ký tự: <code>[a-z0-9._-]</code>.</td>
                    </tr>
                    <tr>
                        <td><code>note</code></td>
                        <td>string</td>
                        <td><span class="badge-opt">Tùy chọn</span></td>
                        <td>Ghi chú lưu kèm email để bot hoặc quản trị viên dễ theo dõi.</td>
                    </tr>
                </tbody>
            </table>
            <div class="code-wrapper">
                <pre>{
  "success": true,
  "count": 1,
  "emails": ["phuc.nguyen@kaishop.id.vn"]
}</pre>
            </div>
        </div>
    </div>

    <!-- Endpoint 2: Get Mailbox Info -->
    <div class="endpoint-card">
        <div class="endpoint-header">
            <span class="method get">GET</span>
            <span class="url"><?= htmlspecialchars(parse_url($apiBase . '/emails.php', PHP_URL_PATH), ENT_QUOTES, 'UTF-8') ?>?email=user@kaishop.id.vn</span>
        </div>
        <div class="endpoint-body">
            <p>Kiểm tra trạng thái tồn tại của hộp thư và lấy thông tin cơ bản.</p>
        </div>
    </div>

    <!-- Endpoint 3: List Messages -->
    <div class="endpoint-card">
        <div class="endpoint-header">
            <span class="method get">GET</span>
            <span class="url"><?= htmlspecialchars(parse_url($apiBase . '/messages.php', PHP_URL_PATH), ENT_QUOTES, 'UTF-8') ?>?email=user@kaishop.id.vn</span>
        </div>
        <div class="endpoint-body">
            <p>Lấy danh sách toàn bộ thư đã nhận của một địa chỉ email (Kèm mã OTP được tự động trích xuất).</p>
            <table class="table-params">
                <thead>
                    <tr>
                        <th>Tham số Query</th>
                        <th>Kiểu</th>
                        <th>Yêu cầu</th>
                        <th>Mô tả</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td><code>email</code></td>
                        <td>string</td>
                        <td><span class="badge-req">Bắt buộc</span></td>
                        <td>Địa chỉ email cần kiểm tra hòm thư.</td>
                    </tr>
                    <tr>
                        <td><code>limit</code></td>
                        <td>integer</td>
                        <td><span class="badge-opt">Tùy chọn</span></td>
                        <td>Số lượng thư tối đa cần lấy. Mặc định: <code>50</code>.</td>
                    </tr>
                </tbody>
            </table>
            <div class="code-wrapper">
                <pre>{
  "success": true,
  "count": 1,
  "messages": [
    {
      "id": 142,
      "sender": "noreply@openai.com",
      "subject": "Your verification code: 829104",
      "otp_code": "829104",
      "created_at": "2026-09-07 10:05:00"
    }
  ]
}</pre>
            </div>
        </div>
    </div>

    <!-- Endpoint 4: Get Message Detail -->
    <div class="endpoint-card">
        <div class="endpoint-header">
            <span class="method get">GET</span>
            <span class="url"><?= htmlspecialchars(parse_url($apiBase . '/messages.php', PHP_URL_PATH), ENT_QUOTES, 'UTF-8') ?>?id=142&amp;email=user@kaishop.id.vn</span>
        </div>
        <div class="endpoint-body">
            <p>Xem toàn bộ nội dung chi tiết của một bức thư (gồm định dạng HTML và văn bản thuần). Tham số <code>email</code> là bắt buộc để xác thực quyền sở hữu.</p>
        </div>
    </div>

    <!-- Endpoint 5: Long Polling Real-time -->
    <div class="endpoint-card">
        <div class="endpoint-header">
            <span class="method get">GET</span>
            <span class="url"><?= htmlspecialchars(parse_url($apiBase . '/long-poll.php', PHP_URL_PATH), ENT_QUOTES, 'UTF-8') ?>?email=user@kaishop.id.vn&amp;last_check=...</span>
        </div>
        <div class="endpoint-body">
            <p>Treo kết nối (Long-polling tối đa 25s) để nhận thư mới ngay lập tức khi mail vừa tới, giúp bot không cần spam query liên tục.</p>
        </div>
    </div>

    <!-- SECTION: ADMIN API -->
    <h2 class="section-title">
        <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"></polygon>
        </svg>
        2. Quản Trị Hệ Thống (Admin Master API)
    </h2>
    <p style="color: var(--slate-600); margin-bottom: 12px; font-size: 0.92rem;">
        Các endpoint sau chỉ cần header <code>X-ADMIN-ACCESS-KEY: <?= htmlspecialchars($adminAccessKey, ENT_QUOTES, 'UTF-8') ?></code>:
    </p>

    <!-- Admin Emails -->
    <div class="endpoint-card">
        <div class="endpoint-header">
            <span class="method get">GET</span>
            <span class="method post" style="margin-left: 4px;">POST</span>
            <span class="method delete" style="margin-left: 4px;">DEL</span>
            <span class="url" style="margin-left: 8px;"><?= htmlspecialchars(parse_url($apiBase . '/admin/emails.php', PHP_URL_PATH), ENT_QUOTES, 'UTF-8') ?></span>
        </div>
        <div class="endpoint-body">
            <ul style="padding-left: 1.25rem; line-height: 1.8; color: var(--slate-700); font-size: 0.9rem;">
                <li><code>GET /api/admin/emails.php?page=1&amp;limit=13&amp;search=...&amp;domain=...&amp;created_by=...</code>: Lọc và phân trang email toàn hệ thống.</li>
                <li><code>POST /api/admin/emails.php</code>: Tạo hàng loạt tối đa 50 email một lần.</li>
                <li><code>POST /api/admin/emails.php</code> (với <code>action=update_note</code>): Cập nhật ghi chú cho email.</li>
                <li><code>DELETE /api/admin/emails.php</code> (Body: <code>{"ids": [1, 2, 3]}</code>): Xóa danh sách email theo ID.</li>
            </ul>
        </div>
    </div>

    <!-- Admin Domains -->
    <div class="endpoint-card">
        <div class="endpoint-header">
            <span class="method get">GET</span>
            <span class="method post" style="margin-left: 4px;">POST</span>
            <span class="method put" style="margin-left: 4px;">PUT</span>
            <span class="method delete" style="margin-left: 4px;">DEL</span>
            <span class="url" style="margin-left: 8px;"><?= htmlspecialchars(parse_url($apiBase . '/admin/domains.php', PHP_URL_PATH), ENT_QUOTES, 'UTF-8') ?></span>
        </div>
        <div class="endpoint-body">
            <ul style="padding-left: 1.25rem; line-height: 1.8; color: var(--slate-700); font-size: 0.9rem;">
                <li><code>GET /api/admin/domains.php</code>: Lấy danh sách domain kèm số email và trạng thái hoạt động.</li>
                <li><code>POST /api/admin/domains.php</code> (Body: <code>{"domain": "newdomain.com", "is_active": 1}</code>): Thêm domain mới.</li>
                <li><code>PUT /api/admin/domains.php</code> (Body: <code>{"id": 1, "is_active": 0}</code>): Bật / tắt trạng thái hoạt động của domain.</li>
                <li><code>DELETE /api/admin/domains.php</code> (Body: <code>{"id": 1}</code>): Xóa domain (Tự động chặn nếu đang có email để bảo vệ dữ liệu).</li>
            </ul>
        </div>
    </div>

    <!-- Admin Checker -->
    <div class="endpoint-card">
        <div class="endpoint-header">
            <span class="method get" style="background: #8b5cf6;">GET</span>
            <span class="url"><?= htmlspecialchars(parse_url($apiBase . '/admin/checker.php', PHP_URL_PATH), ENT_QUOTES, 'UTF-8') ?>?keyword=otp&amp;days=7</span>
        </div>
        <div class="endpoint-body">
            <p>Bộ quét siêu tốc (Fast Checker): Quét từ khóa/OTP xuyên suốt hàng chục nghìn email trong thời gian dưới 0.1 giây.</p>
        </div>
    </div>
</div>

<script>
    // Tab switcher logic
    document.querySelectorAll('#sdkNavTabs .code-tab-btn').forEach(btn => {
        btn.addEventListener('click', () => {
            const targetId = btn.getAttribute('data-tab-target');
            document.querySelectorAll('#sdkNavTabs .code-tab-btn').forEach(b => b.classList.remove('active'));
            document.querySelectorAll('.tab-content-panel').forEach(p => p.classList.remove('active'));
            
            btn.classList.add('active');
            const panel = document.getElementById(targetId);
            if (panel) panel.classList.add('active');
        });
    });

    // Copy code helper
    function copyCodeFromElement(elemId) {
        const elem = document.getElementById(elemId);
        if (!elem) return;
        const text = elem.innerText || elem.textContent;
        window.adminCore?.copyToClipboard(text);
    }
</script>
<?php
AdminLayout::end();
