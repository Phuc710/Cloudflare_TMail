# Kiến Trúc Bảo Mật & Phân Quyền (Security & Access Control)

KaiMail triển khai chiến lược bảo mật phòng thủ chiều sâu (Defense-in-Depth), kết hợp giữa kiểm soát truy cập dựa trên vai trò (**RBAC**) và kiểm soát truy cập dựa trên thuộc tính (**ABAC**).

---

## 1. Mô Hình Phân Quyền Kép (RBAC & ABAC)

### 1.1. Hệ Thống 5 Vai Trò (Roles)

Theo [Role.php](../includes/Core/Auth/Role.php):

| Vai trò | Định danh | Đối tượng áp dụng |
|---|---|---|
| `Role::ADMIN` | `admin` | Người quản trị hệ thống đăng nhập qua `/adminkaishop` hoặc gửi header `X-ADMIN-ACCESS-KEY`. |
| `Role::API_USER` | `api_user` | Các ứng dụng Bot, Third-party client gửi yêu cầu xác thực bằng chữ ký số HMAC-SHA256. |
| `Role::PUBLIC_USER` | `public_user` | Khách dùng giao diện web tạo email tạm và đọc hộp thư của chính họ. |
| `Role::WEBHOOK` | `webhook` | Dịch vụ tiếp nhận mail từ Cloudflare Worker gửi kèm header `X-Webhook-Secret`. |
| `Role::ANONYMOUS` | `anonymous` | Người dùng vãng lai chưa xác thực, chỉ có quyền xem trang chủ hoặc đăng nhập. |

---

### 1.2. Ma Trận Quyền Hạn (Permissions Matrix)

Khai báo tại [Permission.php](../includes/Core/Auth/Permission.php):

| Quyền hạn | ADMIN | API_USER | PUBLIC_USER | WEBHOOK |
|---|:---:|:---:|:---:|:---:|
| `emails:list_all` (Xem toàn bộ email hệ thống) | ✅ | ❌ | ❌ | ❌ |
| `emails:create_single` (Tạo 1 email) | ✅ | ✅ | ✅ | ❌ |
| `emails:create_batch` (Tạo nhiều email 10 - 50) | ✅ | ✅ *(tối đa 10)* | ❌ | ❌ |
| `emails:update_note` (Cập nhật ghi chú note) | ✅ | ❌ | ❌ | ❌ |
| `emails:delete` (Xóa email) | ✅ | ❌ | ❌ | ❌ |
| `emails:toggle_done` (Đánh dấu `is_done`) | ✅ | ❌ | ❌ | ❌ |
| `messages:list_all` (Xem toàn bộ tin nhắn) | ✅ | ❌ | ❌ | ❌ |
| `messages:list_by_email` (Xem thư của 1 email) | ✅ | ✅ | ✅ | ❌ |
| `messages:view_detail` (Đọc nội dung thư) | ✅ | ✅ *(chính chủ)* | ✅ *(chính chủ)* | ❌ |
| `messages:delete` (Xóa tin nhắn) | ✅ | ❌ | ❌ | ❌ |
| `domains:manage` (Quản lý tên miền) | ✅ | ❌ | ❌ | ❌ |
| `stats:view` (Xem số liệu thống kê) | ✅ | ❌ | ❌ | ❌ |
| `checker:run` (Tìm kiếm OTP bằng Checker) | ✅ | ❌ | ❌ | ❌ |
| `long_poll:system` (Long poll toàn hệ thống) | ✅ | ❌ | ❌ | ❌ |
| `webhook:ingest` (Nạp email từ Worker) | ❌ | ❌ | ❌ | ✅ |

---

### 1.3. Cơ Chế Chống Đọc Trộm IDOR (ABAC Ownership Check)

Lỗ hổng **IDOR (Insecure Direct Object References)** xảy ra khi kẻ tấn công đoán ID của một tin nhắn và gọi API đọc thư để xem trộm mã OTP của người khác.

Tại [MessageController.php](../includes/Core/Controllers/MessageController.php), hệ thống thẩm định quyền sở hữu bắt buộc:
```php
if (!$context->isAdmin()) {
    $providedEmail = strtolower($request->string('email'));
    if ($providedEmail === '' || strtolower($message['recipient']) !== $providedEmail) {
        throw ApiException::forbidden('Bạn không có quyền xem tin nhắn này');
    }
}
```
Client bắt buộc phải cung cấp đúng địa chỉ email của người nhận tin nhắn. Nếu tham số `email` không trùng với trường `recipient` của tin nhắn đó trong CSDL, hệ thống từ chối ngay lập tức với mã lỗi `403 Forbidden`.

---

## 2. Pipeline Phân Giải Danh Tính (Authenticator Pipeline)

Mỗi request khi đi vào hệ thống được [Authenticator.php](../includes/Core/Auth/Authenticator.php) phân giải danh tính theo 4 bậc ưu tiên:

1. **Bậc 1 — Admin Session / Access Key**:
   - Kiểm tra cookie session của trang `/adminkaishop` hoặc header `X-ADMIN-ACCESS-KEY`.
   - Nếu hợp lệ, gán quyền `Role::ADMIN`.
2. **Bậc 2 — Webhook Secret**:
   - Kiểm tra header `X-Webhook-Secret` so khớp với hằng số `WEBHOOK_SECRET`.
   - Nếu hợp lệ, gán quyền `Role::WEBHOOK`.
3. **Bậc 3 — External API HMAC**:
   - Kiểm tra bộ 4 header: `X-API-KEY`, `X-API-TIMESTAMP`, `X-API-NONCE`, `X-API-SIGNATURE`.
   - Thẩm định độ lệch thời gian: `abs(time() - ts) <= 300` giây.
   - Thẩm định Nonce duy nhất qua `ReplayGuard`.
   - Tính toán và so khớp chữ ký HMAC-SHA256 với body thô.
   - Nếu hợp lệ, gán quyền `Role::API_USER`.
4. **Bậc 4 — Web UI Session Token**:
   - Kiểm tra header `X-WEB-UI-TOKEN` và xác minh nguồn gốc trình duyệt cùng Origin (`isSameOrigin(BASE_URL)`).
   - Nếu hợp lệ, gán quyền `Role::PUBLIC_USER`.
5. **Mặc định**:
   - Gán quyền `Role::ANONYMOUS` (chỉ được xem trang chủ, các API khác sẽ bị Gate chặn lại).

---

## 3. Các Cơ Chế Bảo Vệ Tấn Công

### 3.1. Chống Tấn Công Phát Lại (Anti-Replay Attack)
- Được xử lý bởi [ReplayGuard.php](../includes/Core/Security/ReplayGuard.php).
- Mỗi chuỗi `X-API-NONCE` được lưu trữ vào tệp cache có khóa nguyên tử (`LOCK_EX`) kèm thời gian sống (TTL = 300 giây).
- Nếu cùng một Nonce xuất hiện lần thứ 2 trong vòng 300 giây, request sẽ bị từ chối với mã lỗi `401 Unauthorized`.

### 3.2. Chống Tấn Công Từ Chối Dịch Vụ (Rate Limiter)
- Được xử lý bởi [RateLimiter.php](../includes/Core/Security/RateLimiter.php).
- Sử dụng thuật toán Fixed-Window Counter có khóa file nguyên tử, ngăn chặn tình trạng race condition khi có nhiều request đồng thời.
- **Hạn mức**:
  - `Role::ADMIN`: 60 req/phút.
  - `Role::API_USER`: 120 req/phút (cấu hình qua `API_RATE_LIMIT_PER_MIN`).
  - `Role::WEBHOOK`: 600 req/phút (băng thông lớn để tiếp nhận mail dồn dập).
  - `Role::PUBLIC_USER`: 120 req/phút.
- Trả về đầy đủ các header tiêu chuẩn: `X-RateLimit-Limit`, `X-RateLimit-Remaining`, `X-RateLimit-Reset`.

### 3.3. Chống Tấn Công SQL Injection & XSS
- **100% Prepared Statements**: Tất cả truy vấn cơ sở dữ liệu đều truyền tham số qua PDO, không nối chuỗi SQL.
- **Cách ly XSS**: Nội dung HTML của thư khi hiển thị trên giao diện người dùng được đặt bên trong thẻ `<iframe sandbox>` cách ly hoàn toàn môi trường thực thi JavaScript của website.
