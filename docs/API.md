# Đặc Tả API & Tích Hợp Bot (API Reference)

KaiMail cung cấp hệ thống API toàn diện, hỗ trợ cả hai phong cách gọi:
1. **RESTful Routes**: Truy cập theo tài nguyên thông qua bộ định tuyến [api/index.php](../api/index.php) (ví dụ: `POST /api/emails`, `GET /api/emails/{email}/messages`).
2. **Direct Thin Dispatchers**: Các tệp trực tiếp tương thích 100% với Web UI và code bot hiện có (ví dụ: `api/emails.php`, `api/messages.php`, `api/long-poll.php`).

---

## 1. Cơ Chế Xác Thực & Ký Số HMAC-SHA256

Hệ thống sử dụng chữ ký điện tử HMAC-SHA256 chống giả mạo gói tin và chống Replay Attack dành cho khách hàng API / Bot.

### 1.1. Bộ 4 Header Bắt Buộc

| Header | Kiểu | Mô tả |
|---|---|---|
| `X-API-KEY` | String | API Access Key từ file `.env` (`API_ACCESS_KEY`). |
| `X-API-TIMESTAMP` | Integer | Unix Timestamp hiện tại (tính bằng giây). Sai lệch tối đa cho phép là **300 giây**. |
| `X-API-NONCE` | String | Chuỗi ngẫu nhiên duy nhất (16-32 ký tự hex) cho mỗi request để chống Replay Attack. |
| `X-API-SIGNATURE` | String | Chữ ký HMAC-SHA256 hex dạng chữ thường. |

### 1.2. Công Thức Sinh Chữ Ký

Chuỗi Payload trước khi ký được tạo từ 5 trường nối với nhau bằng dấu xuống dòng `\n`:
```
PAYLOAD = METHOD + "\n" + PATH + "\n" + TIMESTAMP + "\n" + NONCE + "\n" + SHA256(RAW_BODY)
```

- `METHOD`: Tên phương thức HTTP viết hoa (`GET`, `POST`, `PUT`, `DELETE`).
- `PATH`: Đường dẫn URL tương đối (ví dụ: `/api/emails.php` hoặc `/api/emails`).
- `TIMESTAMP`: Giá trị header `X-API-TIMESTAMP`.
- `NONCE`: Giá trị header `X-API-NONCE`.
- `SHA256(RAW_BODY)`: Mã băm SHA-256 (hex) của chuỗi body thô. Nếu không có body (request GET), băm chuỗi rỗng `""` (kết quả là `e3b0c44298fc1c149afbf4c8996fb92427ae41e4649b934ca495991b7852b855`).

Chữ ký chuẩn xác:
```
SIGNATURE = HMAC_SHA256(PAYLOAD, API_SECRET_KEY)
```

---

## 2. Code Mẫu Ký Chữ Ký (SDK Examples)

### 🐍 Python 3
```python
import time
import secrets
import hashlib
import hmac
import json
import requests

API_KEY = "your_api_access_key_here"
API_SECRET = "your_api_secret_key_here"
BASE_URL = "https://yourdomain.com"

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
        return requests.post(url, data=body_str, headers=headers)
    elif method.upper() == "GET":
        return requests.get(url, headers=headers)

# Tạo 1 email tiếng Việt
res = call_api("POST", "/api/emails.php", {"domain": "kaishop.id.vn", "name_type": "vn"})
print(res.json())
```

### 🟨 Node.js (JavaScript / TypeScript)
```javascript
import crypto from 'crypto';
import axios from 'axios';

const API_KEY = 'your_api_access_key_here';
const API_SECRET = 'your_api_secret_key_here';
const BASE_URL = 'https://yourdomain.com';

async function callApi(method, path, body = null) {
  const ts = Math.floor(Date.now() / 1000).toString();
  const nonce = crypto.randomBytes(16).toString('hex');
  const bodyStr = body ? JSON.stringify(body) : '';
  const bodyHash = crypto.createHash('sha256').update(bodyStr).digest('hex');

  const payload = `${method.toUpperCase()}\n${path}\n${ts}\n${nonce}\n${bodyHash}`;
  const signature = crypto.createHmac('sha256', API_SECRET).update(payload).digest('hex');

  return axios({
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
}
```

---

## 3. Danh Sách Endpoint Cho Bot & Khách Hàng

### 3.1. Tạo Hộp Thư (Create Email)
- **Endpoint**: `POST /api/emails.php` hoặc `POST /api/emails`
- **Quyền**: `API_USER` (tối đa 10 email/lần), `PUBLIC_USER` (1 email/lần), `ADMIN` (tối đa 50 email/lần).
- **Request Body (JSON)**:
```json
{
  "domain": "kaishop.id.vn",
  "name_type": "en",
  "quantity": 1,
  "custom_email": "",
  "note": ""
}
```
  - `domain` *(tùy chọn)*: Tên miền muốn tạo. Bỏ trống sẽ tự động lấy tên miền active đầu tiên.
  - `name_type`: `'random'`, `'en'`, `'vn'`, hoặc `'custom'`.
  - `quantity`: Số lượng email muốn tạo (Mặc định: 1).
  - `custom_email`: Tiền tố tên nếu `name_type` là `'custom'`.
  - `note` *(tùy chọn)*: Ghi chú đính kèm email (tối đa 500 ký tự).
- **Response thành công (HTTP 200)**:
```json
{
  "success": true,
  "emails": [
    {
      "id": 105,
      "email": "johndoe842@kaishop.id.vn",
      "created_at": "2026-09-04 22:40:00"
    }
  ]
}
```

### 3.2. Kiểm Tra Tồn Tại Email (Check Single Email)
- **Endpoint**: `GET /api/emails.php?email={email}`
- **Response thành công (HTTP 200)**:
```json
{
  "success": true,
  "email": "johndoe842@kaishop.id.vn",
  "exists": true,
  "created_at": "2026-09-04 22:40:00"
}
```

### 3.3. Lấy Danh Sách Tin Nhắn Của Hộp Thư (List Messages)
- **Endpoint**: `GET /api/messages.php?email={email}&limit=20` hoặc `GET /api/emails/{email}/messages`
- **Response thành công (HTTP 200)**:
```json
{
  "success": true,
  "messages": [
    {
      "id": 501,
      "from_email": "verify@openai.com",
      "from_name": "OpenAI",
      "subject": "Your verification code is 849201",
      "preview": "Your single-use code is 849201...",
      "is_read": 0,
      "received_at": "2026-09-04 22:41:15"
    }
  ],
  "count": 1
}
```

### 3.4. Xem Chi Tiết Tin Nhắn (Get Message Detail)
- **Endpoint**: `GET /api/messages.php?id={id}&email={email}`
- **Bảo mật**: Bắt buộc phải truyền đúng `email` của người nhận để vượt qua cơ chế chống đọc trộm IDOR. Nếu sai email sẽ bị trả về `403 Forbidden`.
- **Response thành công (HTTP 200)**:
```json
{
  "success": true,
  "message": {
    "id": 501,
    "from_email": "verify@openai.com",
    "from_name": "OpenAI",
    "subject": "Your verification code is 849201",
    "body_text": "Your verification code is 849201. Valid for 10 minutes.",
    "body_html": "<p>Your verification code is <b>849201</b>. Valid for 10 minutes.</p>",
    "is_read": true,
    "received_at": "2026-09-04 22:41:15",
    "recipient": "johndoe842@kaishop.id.vn"
  }
}
```

### 3.5. Nhận Tin Nhắn Mới Thời Gian Thực (Long Polling)
- **Endpoint**: `GET /api/long-poll.php?email={email}&email_id={id}&last_check={Y-m-d H:i:s}`
- **Mô tả**: Giữ kết nối tối đa 25 giây. Ngay khi có tin nhắn mới cho hộp thư này, server trả về ngay lập tức.
- **Response có tin mới (HTTP 200)**:
```json
{
  "has_new": true,
  "count": 1,
  "messages": [ /* danh sách tin nhắn mới */ ],
  "last_check": "2026-09-04 22:42:00"
}
```

---

## 4. Webhook Ingestion Từ Cloudflare Worker

- **Endpoint**: `POST /api/webhook/receive-email.php`
- **Header bắt buộc**: `X-Webhook-Secret: {WEBHOOK_SECRET}`
- **Request Body (JSON)**:
```json
{
  "from": "Support <support@github.com>",
  "from_name": "Support",
  "to": "johndoe842@kaishop.id.vn",
  "subject": "=?UTF-8?B?WGFjIG1pbmg=?=",
  "text": "Your OTP code is=20849201",
  "html": "<p>Your OTP code is=20849201</p>",
  "message_id": "<unique-worker-msg-id>"
}
```
- **Response thành công**: Trả về `HTTP 201 Created` kèm `id` tin nhắn vừa lưu trong database.
- **Cơ chế tự động**:
  - Tự động giải mã tiêu đề MIME RFC 2047.
  - Tự động giải mã nội dung Quoted-Printable.
  - Tự động bỏ qua nếu `message_id` đã tồn tại trong CSDL.

---

## 5. Danh Sách Endpoint Dành Cho Admin (`/api/admin/`)

Yêu cầu Session Admin hoặc Header `X-ADMIN-ACCESS-KEY`:

| Phương thức | Đường dẫn | Chức năng | Tham số / Body |
|---|---|---|---|
| `GET` | `/api/admin/stats.php` | Thống kê số lượng email, tin nhắn, số liệu 7 ngày qua | Không |
| `GET` | `/api/admin/domains.php` | Danh sách tên miền và số lượng email trực thuộc | Không |
| `POST` | `/api/admin/domains.php` | Thêm tên miền mới | `{"domain": "newdomain.com"}` |
| `PUT` | `/api/admin/domains.php` | Bật/tắt trạng thái hoạt động của domain | `{"id": 1, "is_active": 1}` |
| `DELETE` | `/api/admin/domains.php` | Xóa domain (chặn xóa nếu đang có email) | `{"id": 1}` |
| `GET` | `/api/admin/emails.php` | Phân trang danh sách email kèm bộ lọc đa chiều | `?page=1&limit=13&search=...&domain=...&created_by=admin\|api\|user&no_message=1` |
| `POST` | `/api/admin/emails.php` | Tạo email số lượng lớn (tối đa 50) kèm ghi chú | `{"domain": "...", "quantity": 50, "note": "..."}` |
| `POST` | `/api/admin/emails.php` | Cập nhật hoặc xóa ghi chú (note) cho email | `{"action": "update_note", "id": 105, "note": "VIP"}` |
| `POST` | `/api/admin/emails.php` | Đánh dấu email đã sử dụng (`is_done`) | `{"action": "toggle_done", "id": 105, "is_done": 1}` |
| `DELETE` | `/api/admin/emails.php` | Xóa email theo danh sách ID | `{"ids": [105, 106]}` |
| `GET` | `/api/admin/messages.php` | Danh sách toàn bộ tin nhắn hệ thống | `?page=1&limit=20&search=...` |
| `DELETE` | `/api/admin/messages.php` | Xóa tin nhắn theo danh sách ID | `{"ids": [501, 502]}` |
| `POST` | `/api/admin/checker.php` | Fast Checker: Tìm kiếm OTP theo từ khóa FULLTEXT | `{"keyword": "GitHub", "limit": 50}` |
| `GET` | `/api/admin/long-poll.php` | Long poll toàn hệ thống (báo khi có email/tin nhắn mới) | `?last_check=...` |
| `POST` | `/api/admin/auth.php?action=login` | Đăng nhập Admin | `{"password": "ADMIN_ACCESS_KEY"}` |
| `POST` | `/api/admin/auth.php?action=logout` | Đăng xuất Admin | Không |
| `GET` | `/api/admin/auth.php?action=check` | Kiểm tra trạng thái đăng nhập | Không |

---

## 6. Cấu Trúc Phản Hồi Lỗi Chuẩn Hóa

Mọi lỗi phát sinh đều được trả về dưới định dạng JSON thống nhất kèm mã trạng thái HTTP:

```json
{
  "error": "Forbidden",
  "message": "Bạn không có quyền xem tin nhắn này",
  "status": 403
}
```

Các mã trạng thái HTTP:
- `400 Bad Request`: Dữ liệu gửi lên thiếu hoặc không hợp lệ.
- `401 Unauthorized`: Sai API Key, sai chữ ký HMAC, hoặc chưa đăng nhập.
- `403 Forbidden`: Người dùng không có quyền truy cập tài nguyên (hoặc đọc trộm mail người khác).
- `404 Not Found`: Không tìm thấy dữ liệu.
- `405 Method Not Allowed`: Phương thức HTTP không được hỗ trợ.
- `429 Too Many Requests`: Gửi quá nhiều yêu cầu (bị Rate Limiter chặn).
