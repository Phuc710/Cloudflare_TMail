<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/Core/bootstrap.php';
require_once __DIR__ . '/../includes/Core/App.php';

use KaiMail\Core\App;
use KaiMail\Core\Services\DomainService;

$siteUrl = rtrim((string) (defined('BASE_URL') ? BASE_URL : ''), '/');
if ($siteUrl === '') {
    $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https://' : 'http://';
    $siteUrl = $protocol . ($_SERVER['HTTP_HOST'] ?? 'localhost');
}

$sampleDomain = 'kaishop.id.vn';
try {
    $domainService = App::getService(DomainService::class);
    $activeDomains = $domainService->listActiveNames();
    if (!empty($activeDomains)) {
        $sampleDomain = $activeDomains[0];
    }
} catch (Throwable $e) {
    // Fallback default
}

$docsCssUrl = asset_url('/css/docs.css');
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tài liệu tích hợp API (Developer Portal) - KaiMail</title>
    <meta name="description" content="Tài liệu hướng dẫn tích hợp API nhận email tạm thời thời gian thực (Temp Mail) của KaiMail dành cho lập trình viên, bot automation và hệ thống bên ngoài.">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&family=JetBrains+Mono:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="icon" type="image/png" href="<?= htmlspecialchars($siteUrl, ENT_QUOTES, 'UTF-8') ?>/assets/kaishop_favicon.png">
    <link rel="stylesheet" href="<?= htmlspecialchars($docsCssUrl, ENT_QUOTES, 'UTF-8') ?>">
</head>
<body>
    <!-- Top Navbar -->
    <header class="docs-navbar">
        <div class="docs-brand">
            <button type="button" class="mobile-menu-btn" id="mobileMenuBtn" aria-label="Mở Menu">
                <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <line x1="3" y1="12" x2="21" y2="12"></line>
                    <line x1="3" y1="6" x2="21" y2="6"></line>
                    <line x1="3" y1="18" x2="21" y2="18"></line>
                </svg>
            </button>
            <a href="<?= htmlspecialchars($siteUrl, ENT_QUOTES, 'UTF-8') ?>/" class="docs-logo">
                <img src="<?= htmlspecialchars($siteUrl, ENT_QUOTES, 'UTF-8') ?>/assets/kaishop_favicon.png" alt="KaiMail Logo">
                <span>KaiMail <span style="font-weight: 500; color: var(--docs-slate-500);">Docs</span></span>
            </a>
            <span class="docs-version-badge">API v3.0</span>
        </div>

        <nav class="docs-nav-links">
            <a href="<?= htmlspecialchars($siteUrl, ENT_QUOTES, 'UTF-8') ?>/" class="docs-nav-link">Giao diện Web</a>
            <a href="<?= htmlspecialchars($siteUrl, ENT_QUOTES, 'UTF-8') ?>/2fa" class="docs-nav-link">Tool 2FA</a>
            <a href="https://t.me/KaiHub_bot" target="_blank" rel="noopener noreferrer" class="docs-nav-link">Hỗ trợ Telegram</a>
            <a href="<?= htmlspecialchars($siteUrl, ENT_QUOTES, 'UTF-8') ?>/adminkaishop" class="btn-docs-admin">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <rect x="3" y="11" width="18" height="11" rx="2" ry="2"></rect>
                    <path d="M7 11V7a5 5 0 0 1 10 0v4"></path>
                </svg>
                <span>Admin Login</span>
            </a>
        </nav>
    </header>

    <div class="docs-sidebar-overlay" id="sidebarOverlay"></div>

    <div class="docs-layout">
        <!-- Sticky Sidebar Navigation -->
        <aside class="docs-sidebar" id="docsSidebar">
            <div class="sidebar-group">
                <div class="sidebar-group-title">Bắt đầu</div>
                <a href="#overview" class="sidebar-link active">Giới thiệu tổng quan</a>
                <a href="#get-token" class="sidebar-link">Cấp phát API Token</a>
                <a href="#authentication" class="sidebar-link">Xác thực HMAC-SHA256</a>
                <a href="#quickstart" class="sidebar-link">Mã nguồn mẫu (SDK)</a>
            </div>

            <div class="sidebar-group">
                <div class="sidebar-group-title">API Endpoints</div>
                <a href="#endpoint-create-email" class="sidebar-link">
                    <span class="method-tag post">POST</span>
                    <span>Tạo Email</span>
                </a>
                <a href="#endpoint-list-messages" class="sidebar-link">
                    <span class="method-tag get">GET</span>
                    <span>Hộp thư Email</span>
                </a>
                <a href="#endpoint-get-message" class="sidebar-link">
                    <span class="method-tag get">GET</span>
                    <span>Chi tiết Tin nhắn</span>
                </a>
                <a href="#endpoint-long-poll" class="sidebar-link">
                    <span class="method-tag get">GET</span>
                    <span>Long Polling Realtime</span>
                </a>
                <a href="#endpoint-delete-email" class="sidebar-link">
                    <span class="method-tag delete">DEL</span>
                    <span>Xóa Email</span>
                </a>
                <a href="#endpoint-delete-message" class="sidebar-link">
                    <span class="method-tag delete">DEL</span>
                    <span>Xóa Tin nhắn</span>
                </a>
            </div>

            <div class="sidebar-group">
                <div class="sidebar-group-title">Quy chuẩn</div>
                <a href="#rate-limits" class="sidebar-link">Giới hạn tần suất (Rate Limit)</a>
                <a href="#error-codes" class="sidebar-link">Mã lỗi & HTTP Status</a>
            </div>
        </aside>

        <!-- Main Content Area -->
        <main class="docs-main">
            <!-- Hero Header -->
            <header class="docs-hero" id="overview">
                <h1>Tài Liệu Tích Hợp KaiMail API</h1>
                <p>
                    Hệ thống API RESTful hiệu năng cao cho phép bạn tự động hóa tạo email tạm thời, nhận email xác minh, đọc mã OTP và lắng nghe email mới theo thời gian thực (Realtime Long Polling).
                </p>
                <div class="docs-callout success">
                    <strong>Base URL chính thức:</strong> <code><?= htmlspecialchars($siteUrl, ENT_QUOTES, 'UTF-8') ?>/api</code>
                </div>
            </header>

            <!-- SECTION 1: LẤY TOKEN -->
            <section class="docs-section" id="get-token">
                <h2>
                    <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M21 2l-2 2m-1.5 1.5L14 9a5 5 0 1 0 3 3l3.5-3.5 2-2zM9 18a3 3 0 1 1 0-6 3 3 0 0 1 0 6z"></path>
                    </svg>
                    1. Cấp Phát & Nhận API Token
                </h2>
                <p>
                    Để đảm bảo an toàn và phân quyền tốc độ gọi (Rate Limit), KaiMail áp dụng cơ chế xác thực đa người dùng (Multi-tenant API Token). Mỗi bot, phần mềm hoặc khách hàng sẽ được cấp một cặp khóa độc lập:
                </p>

                <div class="docs-steps">
                    <div class="docs-step">
                        <div class="step-num">1</div>
                        <div class="step-content">
                            <h3>Key ID (X-API-KEY)</h3>
                            <p>Định danh công khai của bạn, có tiền tố <code>km_live_...</code>. Khóa này được gửi kèm trong mọi HTTP header request.</p>
                        </div>
                    </div>
                    <div class="docs-step">
                        <div class="step-num">2</div>
                        <div class="step-content">
                            <h3>Secret Key (Bí mật)</h3>
                            <p>Khóa bí mật có tiền tố <code>km_sec_...</code>. Dùng ở backend để ký chữ ký HMAC-SHA256 cho mỗi request. <strong>Tuyệt đối không để lộ Secret Key ra phía trình duyệt (Client-side)!</strong></p>
                        </div>
                    </div>
                </div>

                <div class="docs-callout">
                    <strong>Chưa có Token?</strong> Vui lòng liên hệ Quản trị viên hệ thống để được cấp Token và cấu hình hạn mức Rate Limit phù hợp với nhu cầu của bạn.
                </div>
            </section>

            <!-- SECTION 2: AUTHENTICATION -->
            <section class="docs-section" id="authentication">
                <h2>
                    <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <rect x="3" y="11" width="18" height="11" rx="2" ry="2"></rect>
                        <path d="M7 11V7a5 5 0 0 1 10 0v4"></path>
                    </svg>
                    2. Cơ Chế Xác Thực HMAC-SHA256
                </h2>
                <p>
                    Mọi yêu cầu gửi đến API đều phải chứa đủ <strong>4 HTTP Headers</strong> xác thực. Cơ chế này bảo vệ hệ thống khỏi các cuộc tấn công phát lại (Replay Attack) và ngăn chặn việc giả mạo tham số request.
                </p>

                <table class="param-table">
                    <thead>
                        <tr>
                            <th style="width: 25%;">Header Name</th>
                            <th style="width: 20%;">Kiểu dữ liệu</th>
                            <th>Mô tả chi tiết</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td><span class="param-name">X-API-KEY</span></td>
                            <td><span class="param-type">string</span></td>
                            <td>Key ID được cấp (Ví dụ: <code>km_live_9eb9b1...</code>).</td>
                        </tr>
                        <tr>
                            <td><span class="param-name">X-API-TIMESTAMP</span></td>
                            <td><span class="param-type">integer</span></td>
                            <td>Thời gian hiện tại theo định dạng Unix Timestamp (tính bằng giây). Thời gian sai lệch so với máy chủ không được vượt quá 300 giây (5 phút).</td>
                        </tr>
                        <tr>
                            <td><span class="param-name">X-API-NONCE</span></td>
                            <td><span class="param-type">string</span></td>
                            <td>Chuỗi ngẫu nhiên duy nhất cho mỗi request (độ dài 16 - 32 ký tự hex) để chống tấn công phát lại (Replay Attack).</td>
                        </tr>
                        <tr>
                            <td><span class="param-name">X-API-SIGNATURE</span></td>
                            <td><span class="param-type">string (hex)</span></td>
                            <td>Chữ ký điện tử HMAC-SHA256 tính từ Payload và Secret Key.</td>
                        </tr>
                    </tbody>
                </table>

                <h3 style="font-size: 1.1rem; font-weight: 700; margin: 24px 0 12px;">Công thức tính Chữ ký (Signature)</h3>
                <div class="code-container">
                    <div class="code-header">
                        <span class="code-title">Payload Signing Formula</span>
                        <button type="button" class="btn-copy-code" data-copy-target="formulaCode">Copy</button>
                    </div>
                    <pre class="code-body" id="formulaCode">BODY_HASH = sha256(RAW_REQUEST_BODY)   // Nếu method GET hoặc không có body: sha256("")
PAYLOAD   = METHOD + "\n" + PATH + "\n" + TIMESTAMP + "\n" + NONCE + "\n" + BODY_HASH
SIGNATURE = hmac_sha256(PAYLOAD, SECRET_KEY)</pre>
                </div>
            </section>

            <!-- SECTION 3: QUICKSTART SDK CODE EXAMPLES -->
            <section class="docs-section" id="quickstart">
                <h2>
                    <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <polyline points="16 18 22 12 16 6"></polyline>
                        <polyline points="8 6 2 12 8 18"></polyline>
                    </svg>
                    3. Mã Nguồn Mẫu Đa Ngôn Ngữ (Ready-to-Run)
                </h2>
                <p>
                    Chọn ngôn ngữ lập trình bạn sử dụng để xem đoạn mã mẫu hoàn chỉnh tự động tính toán HMAC Signature và gọi API:
                </p>

                <div class="lang-tabs-wrapper">
                    <div class="lang-tabs-nav">
                        <button type="button" class="lang-tab-btn active" data-lang="python">Python 3</button>
                        <button type="button" class="lang-tab-btn" data-lang="nodejs">Node.js (JavaScript)</button>
                        <button type="button" class="lang-tab-btn" data-lang="php">PHP</button>
                        <button type="button" class="lang-tab-btn" data-lang="curl">cURL (Bash)</button>
                        <button type="button" class="lang-tab-btn" data-lang="csharp">C# (.NET)</button>
                    </div>

                    <!-- Python Example -->
                    <div class="lang-panel active" id="lang-python">
                        <div class="code-container">
                            <div class="code-header">
                                <span class="code-title">Python 3 (requests + hashlib + hmac)</span>
                                <button type="button" class="btn-copy-code" data-copy-target="codePython">Copy Code</button>
                            </div>
                            <pre class="code-body" id="codePython">import time
import secrets
import hashlib
import hmac
import requests
import json

BASE_URL = "<?= htmlspecialchars($siteUrl, ENT_QUOTES, 'UTF-8') ?>"
API_KEY = "km_live_your_key_id"
SECRET_KEY = "km_sec_your_secret_key"

def call_api(method: str, path: str, payload: dict = None):
    timestamp = str(int(time.time()))
    nonce = secrets.token_hex(16)
    body_str = json.dumps(payload) if payload else ""
    body_hash = hashlib.sha256(body_str.encode('utf-8')).hexdigest()

    # Tạo chuỗi ký
    signing_string = f"{method.upper()}\n{path}\n{timestamp}\n{nonce}\n{body_hash}"
    signature = hmac.new(SECRET_KEY.encode('utf-8'), signing_string.encode('utf-8'), hashlib.sha256).hexdigest()

    headers = {
        "X-API-KEY": API_KEY,
        "X-API-TIMESTAMP": timestamp,
        "X-API-NONCE": nonce,
        "X-API-SIGNATURE": signature,
        "Content-Type": "application/json"
    }

    url = f"{BASE_URL}{path}"
    response = requests.request(method, url, headers=headers, data=body_str if body_str else None)
    return response.json()

# 1. Tạo 1 email mới
email_res = call_api("POST", "/api/emails", {"prefix": "mybot", "name_type": "vn"})
print("Tạo Email:", email_res)

# 2. Kiểm tra tin nhắn
if email_res.get("success"):
    email = email_res["data"]["email"]
    messages = call_api("GET", f"/api/emails/{email}/messages")
    print("Danh sách tin nhắn:", messages)</pre>
                        </div>
                    </div>

                    <!-- Node.js Example -->
                    <div class="lang-panel" id="lang-nodejs">
                        <div class="code-container">
                            <div class="code-header">
                                <span class="code-title">Node.js (ES Module / CommonJS with crypto)</span>
                                <button type="button" class="btn-copy-code" data-copy-target="codeNode">Copy Code</button>
                            </div>
                            <pre class="code-body" id="codeNode">import crypto from 'crypto';

const BASE_URL = "<?= htmlspecialchars($siteUrl, ENT_QUOTES, 'UTF-8') ?>";
const API_KEY = "km_live_your_key_id";
const SECRET_KEY = "km_sec_your_secret_key";

async function callApi(method, path, body = null) {
    const timestamp = Math.floor(Date.now() / 1000).toString();
    const nonce = crypto.randomBytes(16).toString('hex');
    const bodyStr = body ? JSON.stringify(body) : '';
    const bodyHash = crypto.createHash('sha256').update(bodyStr, 'utf8').digest('hex');

    const signingString = `${method.toUpperCase()}\n${path}\n${timestamp}\n${nonce}\n${bodyHash}`;
    const signature = crypto.createHmac('sha256', SECRET_KEY).update(signingString, 'utf8').digest('hex');

    const response = await fetch(`${BASE_URL}${path}`, {
        method,
        headers: {
            'X-API-KEY': API_KEY,
            'X-API-TIMESTAMP': timestamp,
            'X-API-NONCE': nonce,
            'X-API-SIGNATURE': signature,
            'Content-Type': 'application/json'
        },
        body: bodyStr || undefined
    });

    return await response.json();
}

// Chạy thử tạo email
const newMail = await callApi('POST', '/api/emails', { prefix: 'client' });
console.log('Tạo email thành công:', newMail);</pre>
                        </div>
                    </div>

                    <!-- PHP Example -->
                    <div class="lang-panel" id="lang-php">
                        <div class="code-container">
                            <div class="code-header">
                                <span class="code-title">PHP (cURL Native)</span>
                                <button type="button" class="btn-copy-code" data-copy-target="codePhp">Copy Code</button>
                            </div>
                            <pre class="code-body" id="codePhp">&lt;?php
$baseUrl = '<?= htmlspecialchars($siteUrl, ENT_QUOTES, 'UTF-8') ?>';
$apiKey = 'km_live_your_key_id';
$secretKey = 'km_sec_your_secret_key';

function callApi(string $method, string $path, array $data = null) {
    global $baseUrl, $apiKey, $secretKey;

    $timestamp = (string) time();
    $nonce = bin2hex(random_bytes(16));
    $body = $data ? json_encode($data) : '';
    $bodyHash = hash('sha256', $body);

    $payload = strtoupper($method) . "\n" . $path . "\n" . $timestamp . "\n" . $nonce . "\n" . $bodyHash;
    $signature = hash_hmac('sha256', $payload, $secretKey);

    $ch = curl_init($baseUrl . $path);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, strtoupper($method));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'X-API-KEY: ' . $apiKey,
        'X-API-TIMESTAMP: ' . $timestamp,
        'X-API-NONCE: ' . $nonce,
        'X-API-SIGNATURE: ' . $signature,
    ]);
    if ($body !== '') {
        curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
    }

    $res = curl_exec($ch);
    curl_close($ch);
    return json_decode($res, true);
}

// Gọi tạo email
$res = callApi('POST', '/api/emails', ['count' => 1]);
print_r($res);</pre>
                        </div>
                    </div>

                    <!-- cURL Example -->
                    <div class="lang-panel" id="lang-curl">
                        <div class="code-container">
                            <div class="code-header">
                                <span class="code-title">Bash / Terminal (openssl + curl)</span>
                                <button type="button" class="btn-copy-code" data-copy-target="codeCurl">Copy Code</button>
                            </div>
                            <pre class="code-body" id="codeCurl">#!/bin/bash
BASE_URL="<?= htmlspecialchars($siteUrl, ENT_QUOTES, 'UTF-8') ?>"
API_KEY="km_live_your_key_id"
SECRET_KEY="km_sec_your_secret_key"
METHOD="POST"
PATH_URL="/api/emails"
BODY='{"prefix":"autotest"}'

TIMESTAMP=$(date +%s)
NONCE=$(openssl rand -hex 16)
BODY_HASH=$(echo -n "$BODY" | openssl dgst -sha256 | awk '{print $2}')

# Ký HMAC
PAYLOAD="${METHOD}\n${PATH_URL}\n${TIMESTAMP}\n${NONCE}\n${BODY_HASH}"
SIGNATURE=$(echo -en "$PAYLOAD" | openssl dgst -sha256 -hmac "$SECRET_KEY" | awk '{print $2}')

curl -X POST "${BASE_URL}${PATH_URL}" \
  -H "Content-Type: application/json" \
  -H "X-API-KEY: ${API_KEY}" \
  -H "X-API-TIMESTAMP: ${TIMESTAMP}" \
  -H "X-API-NONCE: ${NONCE}" \
  -H "X-API-SIGNATURE: ${SIGNATURE}" \
  -d "$BODY"</pre>
                        </div>
                    </div>

                    <!-- C# Example -->
                    <div class="lang-panel" id="lang-csharp">
                        <div class="code-container">
                            <div class="code-header">
                                <span class="code-title">C# (.NET HttpClient + HMACSHA256)</span>
                                <button type="button" class="btn-copy-code" data-copy-target="codeCsharp">Copy Code</button>
                            </div>
                            <pre class="code-body" id="codeCsharp">using System;
using System.Net.Http;
using System.Security.Cryptography;
using System.Text;
using System.Threading.Tasks;

class Program {
    static async Task Main() {
        string baseUrl = "<?= htmlspecialchars($siteUrl, ENT_QUOTES, 'UTF-8') ?>";
        string apiKey = "km_live_your_key_id";
        string secretKey = "km_sec_your_secret_key";
        string method = "POST";
        string path = "/api/emails";
        string body = "{\"prefix\":\"csharp_bot\"}";

        string timestamp = DateTimeOffset.UtcNow.ToUnixTimeSeconds().ToString();
        string nonce = Guid.NewGuid().ToString("N");

        using var sha256 = SHA256.Create();
        string bodyHash = BitConverter.ToString(sha256.ComputeHash(Encoding.UTF8.GetBytes(body))).Replace("-", "").ToLower();

        string payload = $"{method}\n{path}\n{timestamp}\n{nonce}\n{bodyHash}";
        using var hmac = new HMACSHA256(Encoding.UTF8.GetBytes(secretKey));
        string signature = BitConverter.ToString(hmac.ComputeHash(Encoding.UTF8.GetBytes(payload))).Replace("-", "").ToLower();

        using var client = new HttpClient();
        var request = new HttpRequestMessage(HttpMethod.Post, baseUrl + path);
        request.Headers.Add("X-API-KEY", apiKey);
        request.Headers.Add("X-API-TIMESTAMP", timestamp);
        request.Headers.Add("X-API-NONCE", nonce);
        request.Headers.Add("X-API-SIGNATURE", signature);
        request.Content = new StringContent(body, Encoding.UTF8, "application/json");

        var response = await client.SendAsync(request);
        string result = await response.Content.ReadAsStringAsync();
        Console.WriteLine(result);
    }
}</pre>
                        </div>
                    </div>
                </div>
            </section>

            <!-- SECTION 4: API ENDPOINTS DETAILS -->
            <section class="docs-section" id="endpoints">
                <h2>
                    <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <line x1="8" y1="6" x2="21" y2="6"></line>
                        <line x1="8" y1="12" x2="21" y2="12"></line>
                        <line x1="8" y1="18" x2="21" y2="18"></line>
                        <line x1="3" y1="6" x2="3.01" y2="6"></line>
                        <line x1="3" y1="12" x2="3.01" y2="12"></line>
                        <line x1="3" y1="18" x2="3.01" y2="18"></line>
                    </svg>
                    4. Danh Sách Chi Tiết API Endpoints
                </h2>

                <!-- Endpoint 1: Tạo Email -->
                <div class="endpoint-card" id="endpoint-create-email">
                    <div class="endpoint-header">
                        <span class="endpoint-method post">POST</span>
                        <span class="endpoint-path">/api/emails</span>
                        <span class="endpoint-desc-brief">Tạo một hoặc nhiều địa chỉ email ngẫu nhiên / theo yêu cầu</span>
                    </div>
                    <div class="endpoint-body">
                        <p style="font-size: 0.9rem; color: var(--docs-slate-600); margin-bottom: 14px;">
                            Khởi tạo một địa chỉ email tạm thời mới. Hệ thống sẽ lắng nghe mọi thư gửi tới địa chỉ này tức thời qua Cloudflare.
                        </p>

                        <h4 style="font-size: 0.85rem; font-weight: 700; color: var(--docs-slate-700); text-transform: uppercase;">Tham số Request Body (JSON)</h4>
                        <table class="param-table">
                            <thead>
                                <tr>
                                    <th>Trường</th>
                                    <th>Kiểu</th>
                                    <th>Bắt buộc</th>
                                    <th>Mô tả</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr>
                                    <td><span class="param-name">prefix</span></td>
                                    <td><span class="param-type">string</span></td>
                                    <td><span class="badge-optional">Tùy chọn</span></td>
                                    <td>Tự chọn tiền tố email (Ví dụ: <code>mybot</code> &rarr; <code>mybot@<?= htmlspecialchars($sampleDomain, ENT_QUOTES, 'UTF-8') ?></code>).</td>
                                </tr>
                                <tr>
                                    <td><span class="param-name">domain</span></td>
                                    <td><span class="param-type">string</span></td>
                                    <td><span class="badge-optional">Tùy chọn</span></td>
                                    <td>Chọn domain cụ thể. Nếu không truyền sẽ lấy domain mặc định.</td>
                                </tr>
                                <tr>
                                    <td><span class="param-name">name_type</span></td>
                                    <td><span class="param-type">string</span></td>
                                    <td><span class="badge-optional">Tùy chọn</span></td>
                                    <td>Kiểu sinh tên ngẫu nhiên: <code>vn</code> (Tên Việt Nam) hoặc <code>en</code> (Tên tiếng Anh). Mặc định là <code>vn</code>.</td>
                                </tr>
                                <tr>
                                    <td><span class="param-name">count</span></td>
                                    <td><span class="param-type">integer</span></td>
                                    <td><span class="badge-optional">Tùy chọn</span></td>
                                    <td>Số lượng email tạo cùng lúc (Từ 1 đến 10). Mặc định là 1.</td>
                                </tr>
                            </tbody>
                        </table>

                        <h4 style="font-size: 0.85rem; font-weight: 700; color: var(--docs-slate-700); text-transform: uppercase;">Dữ liệu phản hồi mẫu (HTTP 201)</h4>
                        <div class="code-container">
                            <pre class="code-body">{
  "success": true,
  "count": 1,
  "data": {
    "id": 14285,
    "email": "nguyenvana123@<?= htmlspecialchars($sampleDomain, ENT_QUOTES, 'UTF-8') ?>",
    "created_at": "2026-09-07 11:30:00",
    "expires_at": null
  }
}</pre>
                        </div>
                    </div>
                </div>

                <!-- Endpoint 2: Danh sách tin nhắn -->
                <div class="endpoint-card" id="endpoint-list-messages">
                    <div class="endpoint-header">
                        <span class="endpoint-method get">GET</span>
                        <span class="endpoint-path">/api/emails/{email}/messages</span>
                        <span class="endpoint-desc-brief">Lấy danh sách các tin nhắn gửi đến một email</span>
                    </div>
                    <div class="endpoint-body">
                        <p style="font-size: 0.9rem; color: var(--docs-slate-600); margin-bottom: 14px;">
                            Truy xuất tất cả email đã nhận của địa chỉ email được chỉ định. Sắp xếp từ tin nhắn mới nhất đến cũ nhất.
                        </p>

                        <h4 style="font-size: 0.85rem; font-weight: 700; color: var(--docs-slate-700); text-transform: uppercase;">Tham số URL / Query</h4>
                        <table class="param-table">
                            <thead>
                                <tr>
                                    <th>Trường</th>
                                    <th>Vị trí</th>
                                    <th>Kiểu</th>
                                    <th>Mô tả</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr>
                                    <td><span class="param-name">email</span></td>
                                    <td>Path</td>
                                    <td><span class="param-type">string</span></td>
                                    <td>Địa chỉ email cần kiểm tra (URL-encoded).</td>
                                </tr>
                                <tr>
                                    <td><span class="param-name">limit</span></td>
                                    <td>Query</td>
                                    <td><span class="param-type">integer</span></td>
                                    <td>Số lượng tin nhắn tối đa cần lấy (Mặc định: 50, tối đa: 100).</td>
                                </tr>
                            </tbody>
                        </table>

                        <h4 style="font-size: 0.85rem; font-weight: 700; color: var(--docs-slate-700); text-transform: uppercase;">Dữ liệu phản hồi mẫu (HTTP 200)</h4>
                        <div class="code-container">
                            <pre class="code-body">{
  "success": true,
  "count": 1,
  "messages": [
    {
      "id": 9821,
      "email_id": 14285,
      "sender": "noreply@facebookmail.com",
      "subject": "671829 là mã xác nhận tài khoản của bạn",
      "preview": "Mã xác nhận Facebook của bạn là 671829. Vui lòng không chia sẻ mã này...",
      "otp_code": "671829",
      "created_at": "2026-09-07 11:32:15"
    }
  ]
}</pre>
                        </div>
                    </div>
                </div>

                <!-- Endpoint 3: Chi tiết tin nhắn -->
                <div class="endpoint-card" id="endpoint-get-message">
                    <div class="endpoint-header">
                        <span class="endpoint-method get">GET</span>
                        <span class="endpoint-path">/api/messages.php?id={id}</span>
                        <span class="endpoint-desc-brief">Xem toàn bộ nội dung HTML và Text của một tin nhắn</span>
                    </div>
                    <div class="endpoint-body">
                        <p style="font-size: 0.9rem; color: var(--docs-slate-600); margin-bottom: 14px;">
                            Lấy đầy đủ nội dung email gốc, bao gồm HTML rendered, văn bản thuần (plain text) và mã OTP được trích xuất tự động.
                        </p>

                        <h4 style="font-size: 0.85rem; font-weight: 700; color: var(--docs-slate-700); text-transform: uppercase;">Tham số Query</h4>
                        <table class="param-table">
                            <thead>
                                <tr>
                                    <th>Trường</th>
                                    <th>Kiểu</th>
                                    <th>Bắt buộc</th>
                                    <th>Mô tả</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr>
                                    <td><span class="param-name">id</span></td>
                                    <td><span class="param-type">integer</span></td>
                                    <td><span class="badge-required">Bắt buộc</span></td>
                                    <td>ID của tin nhắn (lấy từ API danh sách tin nhắn).</td>
                                </tr>
                            </tbody>
                        </table>

                        <h4 style="font-size: 0.85rem; font-weight: 700; color: var(--docs-slate-700); text-transform: uppercase;">Dữ liệu phản hồi mẫu (HTTP 200)</h4>
                        <div class="code-container">
                            <pre class="code-body">{
  "success": true,
  "message": {
    "id": 9821,
    "sender": "no-reply@tiktok.com",
    "subject": "Mã xác nhận tài khoản TikTok",
    "body_html": "&lt;div&gt;Mã xác nhận của bạn là &lt;b&gt;489102&lt;/b&gt;&lt;/div&gt;",
    "body_text": "Mã xác nhận của bạn là 489102",
    "otp_code": "489102",
    "received_at": "2026-09-07 11:32:15"
  }
}</pre>
                        </div>
                    </div>
                </div>

                <!-- Endpoint 4: Long Polling Realtime -->
                <div class="endpoint-card" id="endpoint-long-poll">
                    <div class="endpoint-header">
                        <span class="endpoint-method get">GET</span>
                        <span class="endpoint-path">/api/long-poll.php?email={email}</span>
                        <span class="endpoint-desc-brief">Lắng nghe tin nhắn mới Realtime (Không spam request)</span>
                    </div>
                    <div class="endpoint-body">
                        <p style="font-size: 0.9rem; color: var(--docs-slate-600); margin-bottom: 14px;">
                            Thay vì phải gửi request liên tục (Short polling) gây tốn tài nguyên, Long Polling sẽ giữ kết nối HTTP tối đa 25 giây. Ngay khi có email vừa gửi tới, máy chủ lập tức trả về phản hồi ngay trong mili-giây!
                        </p>

                        <h4 style="font-size: 0.85rem; font-weight: 700; color: var(--docs-slate-700); text-transform: uppercase;">Tham số Query</h4>
                        <table class="param-table">
                            <thead>
                                <tr>
                                    <th>Trường</th>
                                    <th>Kiểu</th>
                                    <th>Bắt buộc</th>
                                    <th>Mô tả</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr>
                                    <td><span class="param-name">email</span></td>
                                    <td><span class="param-type">string</span></td>
                                    <td><span class="badge-required">Bắt buộc</span></td>
                                    <td>Địa chỉ email cần lắng nghe thư mới.</td>
                                </tr>
                                <tr>
                                    <td><span class="param-name">last_id</span></td>
                                    <td><span class="param-type">integer</span></td>
                                    <td><span class="badge-optional">Tùy chọn</span></td>
                                    <td>ID của tin nhắn cuối cùng đã nhận để chỉ lấy các tin nhắn mới hơn.</td>
                                </tr>
                                <tr>
                                    <td><span class="param-name">timeout</span></td>
                                    <td><span class="param-type">integer</span></td>
                                    <td><span class="badge-optional">Tùy chọn</span></td>
                                    <td>Thời gian chờ tối đa (Mặc định: 25 giây, tối đa: 30 giây).</td>
                                </tr>
                            </tbody>
                        </table>

                        <h4 style="font-size: 0.85rem; font-weight: 700; color: var(--docs-slate-700); text-transform: uppercase;">Dữ liệu phản hồi khi có thư mới</h4>
                        <div class="code-container">
                            <pre class="code-body">{
  "status": "new_messages",
  "count": 1,
  "messages": [
    {
      "id": 9822,
      "sender": "verify@google.com",
      "subject": "Mã xác minh Google của bạn",
      "otp_code": "819201"
    }
  ]
}</pre>
                        </div>
                    </div>
                </div>

                <!-- Endpoint 5: Xóa Email -->
                <div class="endpoint-card" id="endpoint-delete-email">
                    <div class="endpoint-header">
                        <span class="endpoint-method delete">DELETE</span>
                        <span class="endpoint-path">/api/emails.php?email={email}</span>
                        <span class="endpoint-desc-brief">Xóa vĩnh viễn địa chỉ email và toàn bộ tin nhắn liên quan</span>
                    </div>
                    <div class="endpoint-body">
                        <p style="font-size: 0.9rem; color: var(--docs-slate-600); margin-bottom: 14px;">
                            Sau khi bot đã nhận OTP và hoàn tất tác vụ, gọi endpoint này để dọn dẹp sạch sẽ hòm thư.
                        </p>
                        <div class="code-container">
                            <pre class="code-body">{
  "success": true,
  "message": "Đã xóa email thành công"
}</pre>
                        </div>
                    </div>
                </div>
            </section>

            <!-- SECTION 5: RATE LIMITING -->
            <section class="docs-section" id="rate-limits">
                <h2>
                    <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <polygon points="13 2 3 14 12 14 11 22 21 10 12 10 13 2"></polygon>
                    </svg>
                    5. Giới Hạn Tần Suất (Rate Limiting)
                </h2>
                <p>
                    Để đảm bảo tính ổn định của cụm máy chủ, mỗi API Token được gán một định mức tốc độ riêng (Mặc định 120 requests/phút). Mỗi phản hồi từ máy chủ luôn đính kèm các header sau:
                </p>

                <table class="param-table">
                    <thead>
                        <tr>
                            <th>Header Response</th>
                            <th>Mô tả</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td><span class="param-name">X-RateLimit-Limit</span></td>
                            <td>Hạn mức tối đa số request được phép trong 1 phút của Token.</td>
                        </tr>
                        <tr>
                            <td><span class="param-name">X-RateLimit-Remaining</span></td>
                            <td>Số lượng request còn lại trong chu kỳ 1 phút hiện tại.</td>
                        </tr>
                        <tr>
                            <td><span class="param-name">X-RateLimit-Reset</span></td>
                            <td>Thời điểm (Unix Timestamp) hạn mức sẽ được làm mới lại.</td>
                        </tr>
                    </tbody>
                </table>

                <div class="docs-callout warning">
                    <strong>Khi vượt quá giới hạn:</strong> Hệ thống sẽ trả về mã lỗi <code>HTTP 429 Too Many Requests</code> kèm header <code>Retry-After: {giây}</code>. Vui lòng sleep theo thời gian này trước khi gửi request tiếp theo.
                </div>
            </section>

            <!-- SECTION 6: ERROR CODES -->
            <section class="docs-section" id="error-codes">
                <h2>
                    <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <circle cx="12" cy="12" r="10"></circle>
                        <line x1="12" y1="8" x2="12" y2="12"></line>
                        <line x1="12" y1="16" x2="12.01" y2="16"></line>
                    </svg>
                    6. Bảng Mã Trạng Thái HTTP & Xử Lý Lỗi
                </h2>
                <table class="param-table">
                    <thead>
                        <tr>
                            <th style="width: 15%;">Mã HTTP</th>
                            <th style="width: 25%;">Trạng thái</th>
                            <th>Ý nghĩa & Cách khắc phục</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td><strong>200 / 201</strong></td>
                            <td>Success / Created</td>
                            <td>Thao tác thành công. Dữ liệu trả về trong đối tượng JSON.</td>
                        </tr>
                        <tr>
                            <td><strong>400</strong></td>
                            <td>Bad Request</td>
                            <td>Tham số gửi lên không hợp lệ, thiếu trường bắt buộc hoặc JSON bị lỗi cú pháp.</td>
                        </tr>
                        <tr>
                            <td><strong>401</strong></td>
                            <td>Unauthorized</td>
                            <td>Key ID sai, Secret Key không khớp, Token bị tạm khóa hoặc Timestamp quá lệch so với giờ chuẩn.</td>
                        </tr>
                        <tr>
                            <td><strong>403</strong></td>
                            <td>Forbidden</td>
                            <td>Token không có quyền thực hiện hành động này.</td>
                        </tr>
                        <tr>
                            <td><strong>404</strong></td>
                            <td>Not Found</td>
                            <td>Email hoặc tin nhắn cần tìm không tồn tại trong hệ thống.</td>
                        </tr>
                        <tr>
                            <td><strong>429</strong></td>
                            <td>Too Many Requests</td>
                            <td>Vượt quá giới hạn tần suất. Cần giảm tốc độ hoặc liên hệ Admin nâng hạn mức.</td>
                        </tr>
                    </tbody>
                </table>
            </section>
        </main>
    </div>

    <!-- Client-side Interactive Script -->
    <script>
        document.addEventListener('DOMContentLoaded', () => {
            // 1. Language Tabs Switcher
            const tabButtons = document.querySelectorAll('.lang-tab-btn');
            const tabPanels = document.querySelectorAll('.lang-panel');

            tabButtons.forEach(btn => {
                btn.addEventListener('click', () => {
                    const lang = btn.getAttribute('data-lang');
                    tabButtons.forEach(b => b.classList.remove('active'));
                    tabPanels.forEach(p => p.classList.remove('active'));

                    btn.classList.add('active');
                    const targetPanel = document.getElementById(`lang-${lang}`);
                    if (targetPanel) {
                        targetPanel.classList.add('active');
                    }
                });
            });

            // 2. Copy Code to Clipboard
            document.querySelectorAll('.btn-copy-code').forEach(btn => {
                btn.addEventListener('click', async () => {
                    const targetId = btn.getAttribute('data-copy-target');
                    const targetEl = document.getElementById(targetId);
                    if (!targetEl) return;

                    try {
                        await navigator.clipboard.writeText(targetEl.textContent.trim());
                        const originalText = btn.textContent;
                        btn.textContent = 'Đã chép!';
                        btn.style.background = '#059669';
                        setTimeout(() => {
                            btn.textContent = originalText;
                            btn.style.background = '';
                        }, 2000);
                    } catch (err) {
                        console.error('Copy failed:', err);
                    }
                });
            });

            // 3. Mobile Sidebar Toggle
            const mobileBtn = document.getElementById('mobileMenuBtn');
            const sidebar = document.getElementById('docsSidebar');
            const overlay = document.getElementById('sidebarOverlay');

            const toggleSidebar = (show) => {
                sidebar.classList.toggle('show', show);
                overlay.classList.toggle('show', show);
            };

            if (mobileBtn && sidebar && overlay) {
                mobileBtn.addEventListener('click', () => toggleSidebar(true));
                overlay.addEventListener('click', () => toggleSidebar(false));
                sidebar.querySelectorAll('.sidebar-link').forEach(link => {
                    link.addEventListener('click', () => toggleSidebar(false));
                });
            }

            // 4. Highlight Active Link on Scroll
            const sections = document.querySelectorAll('.docs-section, .docs-hero');
            const navLinks = document.querySelectorAll('.sidebar-link');

            const observer = new IntersectionObserver((entries) => {
                entries.forEach(entry => {
                    if (entry.isIntersecting) {
                        const id = entry.target.getAttribute('id');
                        navLinks.forEach(link => {
                            const href = link.getAttribute('href');
                            if (href === `#${id}`) {
                                link.classList.add('active');
                            } else {
                                link.classList.remove('active');
                            }
                        });
                    }
                });
            }, { rootMargin: '-20% 0px -70% 0px' });

            sections.forEach(sec => observer.observe(sec));
        });
    </script>
</body>
</html>
