# Tài Liệu Đặc Tả API & Tích Hợp Bot (KaiMail API Reference)

KaiMail cung cấp hệ thống API mạnh mẽ, bảo mật cao và tối ưu tốc độ dành cho Bot Telegram/Discord, phần mềm tự động (Tools, Automation Scripts) và khách hàng tích hợp dịch vụ Email tạm thời.

Hệ thống hỗ trợ 2 phong cách gọi:
1. **Direct API Dispatchers**: Gọi trực tiếp tới các tệp API nhanh gọn (ví dụ: `POST /api/emails.php`, `GET /api/messages.php`, `GET /api/domains.php`).
2. **RESTful Routing**: Truy cập thông qua bộ định tuyến RESTful chuẩn mực (`POST /api/emails`, `GET /api/emails/{email}/messages`).

---

## 1. Cơ Chế Xác Thực & Phân Quyền Khóa API

Hệ thống hỗ trợ **Multi-tenant Dynamic API Token** (cấp phát theo từng Bot / Khách hàng trực tiếp trên Admin Portal) và bảo vệ bằng chữ ký điện tử **HMAC-SHA256** chống Replay Attack & chống giả mạo dữ liệu.

### 1.1. Cặp Khóa Xác Thực (API Token Pair)
Khi tạo API Token trong trang Quản trị (`/adminkaishop/tokens`), bạn sẽ nhận được một cặp khóa:

| Tên Khóa | Tiền Tố | Header Truyền | Mục Đích |
|---|---|---|---|
| **Key ID (Public)** | `km_live_...` | `X-API-KEY` | Định danh công khai của bạn, gửi kèm trong mọi HTTP request. |
| **Secret Key (Bí mật)** | `km_sec_...` | Dùng để ký HMAC | Khóa bí mật dùng để sinh chữ ký `X-API-SIGNATURE`. **Không bao giờ gửi trực tiếp lên mạng.** |

> 💡 *Hệ thống cũng hỗ trợ Master API Key tĩnh từ file `.env` (`API_ACCESS_KEY` & `API_SECRET_KEY`) dành cho root developer.*

---

### 1.2. Bộ 4 Header Bắt Buộc Khi Gọi API

Mọi request từ Bot/Client gọi tới các API (`/api/emails.php`, `/api/messages.php`, `/api/domains.php`, v.v.) phải đính kèm đủ 4 Header sau:

```http
Content-Type: application/json
X-API-KEY: km_live_646588caa50808bdbf5c97c836a07cf8
X-API-TIMESTAMP: 1788757000
X-API-NONCE: a1b2c3d4e5f60718293a4b5c6d7e8f90
X-API-SIGNATURE: 4f1a2b3c4d5e6f7a8b9c0d1e2f3a4b5c6d7e8f9a0b1c2d3e4f5a6b7c8d9e0f1a
```

| Header | Kiểu Dữ Liệu | Mô Tả |
|---|---|---|
| `X-API-KEY` | String | Key ID của bạn (ví dụ: `km_live_...`). |
| `X-API-TIMESTAMP` | Integer | Unix Timestamp hiện tại (tính bằng giây). Độ lệch cho phép tối đa **300 giây**. |
| `X-API-NONCE` | String | Chuỗi ngẫu nhiên duy nhất (16-32 ký tự hex) cho mỗi request để chống Replay Attack. |
| `X-API-SIGNATURE` | String | Chữ ký HMAC-SHA256 dạng hex (chữ thường). |

---

### 1.3. Công Thức Ký Chữ Ký HMAC-SHA256

Chuỗi **Payload** trước khi ký được tạo từ 5 trường nối với nhau bằng ký tự xuống dòng `\n`:

```
PAYLOAD = METHOD + "\n" + PATH + "\n" + TIMESTAMP + "\n" + NONCE + "\n" + SHA256(RAW_BODY)
```

- `METHOD`: Tên phương thức HTTP viết hoa (`GET`, `POST`, `PUT`, `DELETE`).
- `PATH`: Đường dẫn URL tương đối (ví dụ: `/api/emails.php` hoặc `/api/domains.php`).
- `TIMESTAMP`: Giá trị header `X-API-TIMESTAMP`.
- `NONCE`: Giá trị header `X-API-NONCE`.
- `SHA256(RAW_BODY)`: Mã băm SHA-256 (hex) của chuỗi Body thô. Nếu request không có body (GET request), băm chuỗi rỗng `""` (kết quả là `e3b0c44298fc1c149afbf4c8996fb92427ae41e4649b934ca495991b7852b855`).

Chữ ký chuẩn xác:
```
SIGNATURE = HMAC_SHA256(PAYLOAD, SECRET_KEY)
```

---

## 2. Mã Nguồn Mẫu Tích Hợp (SDK Code Samples)

### 🐍 Python 3
```python
import time
import secrets
import hashlib
import hmac
import json
import requests

API_KEY = "km_live_your_key_id_here"
API_SECRET = "km_sec_your_secret_key_here"
BASE_URL = "https://tmail.kaishop.id.vn"

def call_api(method: str, path: str, data: dict = None):
    ts = str(int(time.time()))
    nonce = secrets.token_hex(16)
    body_str = json.dumps(data) if data else ""
    body_hash = hashlib.sha256(body_str.encode("utf-8")).hexdigest()
    
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
    if method.upper() == "POST":
        return requests.post(url, data=body_str, headers=headers).json()
    elif method.upper() == "GET":
        return requests.get(url, headers=headers).json()

# 1. Lấy danh sách domain
domains = call_api("GET", "/api/domains.php")
print("Domains:", domains)

# 2. Tạo 1 email tạm tiếng Anh
new_mail = call_api("POST", "/api/emails.php", {
    "domain": "kaishop.id.vn",
    "name_type": "en"
})
print("New Email:", new_mail)
```

---

### 🟨 Node.js (JavaScript / TypeScript)
```javascript
import crypto from 'crypto';
import axios from 'axios';

const API_KEY = 'km_live_your_key_id_here';
const API_SECRET = 'km_sec_your_secret_key_here';
const BASE_URL = 'https://tmail.kaishop.id.vn';

async function callApi(method, path, body = null) {
  const ts = Math.floor(Date.now() / 1000).toString();
  const nonce = crypto.randomBytes(16).toString('hex');
  const bodyStr = body ? JSON.stringify(body) : '';
  const bodyHash = crypto.createHash('sha256').update(bodyStr).digest('hex');

  const payload = `${method.toUpperCase()}\n${path}\n${ts}\n${nonce}\n${bodyHash}`;
  const signature = crypto.createHmac('sha256', API_SECRET).update(payload).digest('hex');

  const res = await axios({
    method,
    url: `${BASE_URL}${path}`,
    data: bodyStr || undefined,
    headers: {
      'Content-Type': 'application/json',
      'X-API-KEY': API_KEY,
      'X-API-TIMESTAMP': ts,
      'X-API-NONCE': nonce,
      'X-API-SIGNATURE': signature
    }
  });

  return res.data;
}

// Chạy test
(async () => {
  const mail = await callApi('POST', '/api/emails.php', {
    domain: 'kaishop.id.vn',
    name_type: 'vn'
  });
  console.log('Created Mail:', mail);
})();
```

---

### 🐘 PHP (cURL Thuần)
```php
<?php
$baseUrl = 'https://tmail.kaishop.id.vn';
$apiKey = 'km_live_your_key_id_here';
$apiSecret = 'km_sec_your_secret_key_here';

function callKaiMail(string $baseUrl, string $method, string $path, array $data, string $apiKey, string $apiSecret): array
{
    $url = $baseUrl . $path;
    $timestamp = (string) time();
    $nonce = bin2hex(random_bytes(16));
    
    $rawBody = ($method === 'GET' || empty($data)) ? '' : json_encode($data, JSON_UNESCAPED_UNICODE);
    $payloadHash = hash('sha256', $rawBody);
    
    $signPayload = strtoupper($method) . "\n" . $path . "\n" . $timestamp . "\n" . $nonce . "\n" . $payloadHash;
    $signature = hash_hmac('sha256', $signPayload, $apiSecret);
    
    $headers = [
        'Content-Type: application/json',
        'X-API-KEY: ' . $apiKey,
        'X-API-TIMESTAMP: ' . $timestamp,
        'X-API-NONCE: ' . $nonce,
        'X-API-SIGNATURE: ' . $signature,
    ];
    
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, strtoupper($method));
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 15);
    
    if ($rawBody !== '') {
        curl_setopt($ch, CURLOPT_POSTFIELDS, $rawBody);
    }
    
    $response = curl_exec($ch);
    curl_close($ch);
    
    return json_decode((string) $response, true) ?: [];
}

// Tạo email tiếng Việt
$res = callKaiMail($baseUrl, 'POST', '/api/emails.php', ['name_type' => 'vn', 'domain' => 'kaishop.id.vn'], $apiKey, $apiSecret);
print_r($res);
```

---

### 🔷 C# (.NET 8 / HttpClient)
```csharp
using System.Security.Cryptography;
using System.Text;
using System.Text.Json;

public class KaiMailClient
{
    private readonly HttpClient _http = new();
    private const string BaseUrl = "https://tmail.kaishop.id.vn";
    private const string ApiKey = "km_live_your_key_id_here";
    private const string ApiSecret = "km_sec_your_secret_key_here";

    public async Task<string> CallApiAsync(string method, string path, object? body = null)
    {
        string ts = DateTimeOffset.UtcNow.ToUnixTimeSeconds().ToString();
        string nonce = Convert.ToHexString(RandomNumberGenerator.GetBytes(16)).ToLower();
        string bodyStr = body != null ? JsonSerializer.Serialize(body) : "";

        string bodyHash;
        using (var sha = SHA256.Create()) {
            bodyHash = Convert.ToHexString(sha.ComputeHash(Encoding.UTF8.GetBytes(bodyStr))).ToLower();
        }

        string signPayload = $"{method.ToUpper()}\n{path}\n{ts}\n{nonce}\n{bodyHash}";
        string signature;
        using (var hmac = new HMACSHA256(Encoding.UTF8.GetBytes(ApiSecret))) {
            signature = Convert.ToHexString(hmac.ComputeHash(Encoding.UTF8.GetBytes(signPayload))).ToLower();
        }

        var req = new HttpRequestMessage(new HttpMethod(method), BaseUrl + path);
        req.Headers.Add("X-API-KEY", ApiKey);
        req.Headers.Add("X-API-TIMESTAMP", ts);
        req.Headers.Add("X-API-NONCE", nonce);
        req.Headers.Add("X-API-SIGNATURE", signature);

        if (!string.IsNullOrEmpty(bodyStr)) {
            req.Content = new StringContent(bodyStr, Encoding.UTF8, "application/json");
        }

        var res = await _http.SendAsync(req);
        return await res.Content.ReadAsStringAsync();
    }
}
```

---

## 3. Danh Sách Các Endpoint Dành Cho Bot & Khách Hàng

### 3.1. Lấy Danh Sách Tên Miền Đang Hoạt Động (Get Domains)
- **Phương thức**: `GET`
- **Đường dẫn**: `/api/domains.php` hoặc `/api/domains`
- **Response thành công (HTTP 200)**:
```json
{
  "success": true,
  "domains": [
    "dewii.dpdns.org",
    "kaishop.id.vn"
  ]
}
```

---

### 3.2. Tạo Email Tạm Thời Mới (Create Temp Email)
- **Phương thức**: `POST`
- **Đường dẫn**: `/api/emails.php` hoặc `/api/emails`
- **Request Body (JSON)**:
```json
{
  "domain": "kaishop.id.vn",
  "name_type": "en",
  "quantity": 1,
  "email": "mycustomname",
  "note": "Bot Telegram Task 01"
}
```

| Tham Số | Kiểu | Bắt Buộc | Mô Tả |
|---|---|---|---|
| `domain` | String | Không | Domain muốn tạo. Nếu bỏ trống sẽ tự chọn domain khả dụng đầu tiên. |
| `name_type` | String | Không | `vn` (tên Việt: `nguyenvana101`), `en` (tên Anh: `johndoe202`), `random`, hoặc `custom`. |
| `quantity` | Integer | Không | Số lượng email tạo cùng lúc (Mặc định: 1, tối đa 10 cho Bot API). |
| `email` | String | Nếu `custom` | Tên email tự chọn (không gồm đuôi domain). |
| `note` | String | Không | Ghi chú cho email (tối đa 500 ký tự). |

- **Response thành công (HTTP 201 Created)**:
```json
{
  "success": true,
  "created": 1,
  "emails": [
    {
      "id": 174,
      "email": "benjaminperez101@kaishop.id.vn",
      "name_type": "en",
      "created_by": "api",
      "note": "Bot Telegram Task 01"
    }
  ],
  "errors": []
}
```

---

### 3.3. Lấy Danh Sách Hộp Thư / Tin Nhắn (List Messages)
- **Phương thức**: `GET`
- **Đường dẫn**: `/api/messages.php?email=benjaminperez101@kaishop.id.vn` hoặc `?email_id=174`
- **Tham số URL**:
  - `email`: Địa chỉ email cần kiểm tra tin nhắn.
  - `limit`: Số lượng tin tối đa (Mặc định: 20).
- **Response thành công (HTTP 200)**:
```json
{
  "success": true,
  "count": 1,
  "messages": [
    {
      "id": 502,
      "from_email": "verify@facebook.com",
      "from_name": "Facebook",
      "subject": "123456 là mã xác nhận tài khoản của bạn",
      "snippet": "123456 là mã xác minh của bạn. Vui lòng không chia sẻ mã này...",
      "is_read": 0,
      "received_at": "2026-09-07 12:10:00"
    }
  ]
}
```

---

### 3.4. Xem Chi Tiết Một Tin Nhắn (Get Message Detail & OTP)
- **Phương thức**: `GET`
- **Đường dẫn**: `/api/messages.php?id=502&email=benjaminperez101@kaishop.id.vn`
- **Response thành công (HTTP 200)**:
```json
{
  "success": true,
  "message": {
    "id": 502,
    "from_email": "verify@facebook.com",
    "from_name": "Facebook",
    "subject": "123456 là mã xác nhận tài khoản của bạn",
    "snippet": "123456 là mã xác minh của bạn...",
    "body_text": "Mã xác nhận Facebook của bạn là 123456.",
    "body_html": "<p>Mã xác nhận Facebook của bạn là <b>123456</b>.</p>",
    "is_read": 1,
    "received_at": "2026-09-07 12:10:00",
    "recipient": "benjaminperez101@kaishop.id.vn"
  }
}
```

---

### 3.5. Nhận Tin Nhắn Mới Tức Thì (Long Polling)
- **Phương thức**: `GET`
- **Đường dẫn**: `/api/long-poll.php?email=benjaminperez101@kaishop.id.vn&last_check=2026-09-07+12:00:00`
- **Mô tả**: Giữ kết nối HTTP mở tối đa **25 giây**. Khi có email mới gửi tới, server lập tức phản hồi mà không cần spam gọi liên tục (giảm tải 95% tài nguyên máy chủ).
- **Response có tin mới (HTTP 200)**:
```json
{
  "has_new": true,
  "count": 1,
  "messages": [ /* danh sách tin nhắn mới */ ],
  "last_check": "2026-09-07 12:10:05"
}
```

---

### 3.6. Xóa Tin Nhắn Trong Hộp Thư (Delete Message)
- **Phương thức**: `POST` (với `_method: DELETE` hoặc method `DELETE`)
- **Đường dẫn**: `/api/messages.php`
- **Request Body (JSON)**:
```json
{
  "ids": [502]
}
```
- **Response thành công (HTTP 200)**:
```json
{
  "success": true,
  "deleted": 1
}
```

---

## 4. Danh Sách Endpoint Quản Trị Hệ Thống (`/api/admin/`)

Yêu cầu Session Quản trị Admin hoặc Header `X-ADMIN-ACCESS-KEY`:

| Phương thức | Đường dẫn | Chức năng | Body / Tham số |
|---|---|---|---|
| `GET` | `/api/admin/stats.php` | Thống kê số lượng email, tin nhắn, token | Không |
| `GET` | `/api/admin/tokens.php` | Lấy toàn bộ danh sách API Tokens đa người dùng | Không |
| `POST` | `/api/admin/tokens.php` | Tạo API Token mới (cấp Key ID + Secret Key) | `{"name":"Bot Discord","rate_limit_per_min":120,"expires_days":30}` |
| `PUT` | `/api/admin/tokens.php` | Bật/tắt trạng thái hoạt động token | `{"id": 1, "status": 1}` |
| `DELETE` | `/api/admin/tokens.php` | Thu hồi và xóa vĩnh viễn API Token | `{"id": 1}` |
| `GET` | `/api/admin/domains.php` | Danh sách tên miền và số lượng email trực thuộc | Không |
| `POST` | `/api/admin/domains.php` | Thêm domain mới | `{"domain":"newdomain.com"}` |
| `PUT` | `/api/admin/domains.php` | Bật/tắt trạng thái domain | `{"id":1,"is_active":1}` |
| `DELETE` | `/api/admin/domains.php` | Xóa domain | `{"id":1}` |
| `GET` | `/api/admin/emails.php` | Danh sách email quản trị phân trang & bộ lọc | `?page=1&limit=13&search=...` |
| `POST` | `/api/admin/emails.php` | Admin tạo email số lượng lớn (tối đa 50) | `{"domain":"...","quantity":50}` |
| `DELETE` | `/api/admin/emails.php` | Xóa email theo ID | `{"ids":[101,102]}` |
| `POST` | `/api/admin/checker.php` | Fast Scanner: Quét tìm OTP theo từ khóa FULLTEXT | `{"keyword":"Facebook","days":7}` |

---

## 5. Bảng Mã Lỗi & Phản Hồi Chuẩn Hóa

Mọi lỗi trả về theo định dạng JSON đồng nhất:

```json
{
  "success": false,
  "error": "Unauthorized",
  "message": "Chữ ký API không hợp lệ",
  "status": 401
}
```

| HTTP Status | Nguyên Nhân & Cách Khắc Phục |
|---|---|
| `400 Bad Request` | Thiếu tham số hoặc dữ liệu gửi lên không đúng định dạng. |
| `401 Unauthorized` | Sai `X-API-KEY`, sai `X-API-SIGNATURE` HMAC, hoặc timestamp bị lệch quá 300s. |
| `403 Forbidden` | Token bị vô hiệu hóa hoặc không có quyền thao tác trên tài nguyên. |
| `404 Not Found` | Không tìm thấy email, tin nhắn hoặc tài nguyên tương ứng. |
| `429 Too Many Requests` | Vượt quá giới hạn tần suất gọi (Rate Limit) được cấp cho Token. Header `Retry-After` sẽ cho biết thời gian cần chờ. |
| `500 Internal Error` | Lỗi máy chủ hoặc mất kết nối CSDL tạm thời. |
