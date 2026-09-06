# KaiMail — Nền Tảng Temporary Mail Chuẩn Senior

Hệ thống tài liệu kỹ thuật chính thức của dự án **KaiMail (Cloudflare_TMail)**. Toàn bộ kiến trúc đã được chuẩn hóa theo mô hình phân tầng hướng dịch vụ (Clean Layered Architecture), tự động nạp lớp PSR-4, bảo mật đa lớp (RBAC + ABAC) và đồng bộ 100% với mã nguồn thực tế.

---

## 📑 Danh Mục Tài Liệu Kỹ Thuật

| Tài liệu | Mô tả nội dung |
|---|---|
| 🏛️ **[ARCHITECTURE.md](file:///c:/Users/Phucx/Desktop/tmail/docs/ARCHITECTURE.md)** | Cấu trúc phân tầng, Kernel `App::run()`, Cơ sở dữ liệu chuẩn `data.sql`, và Cơ chế nạp tự động PSR-4. |
| 🚀 **[API.md](file:///c:/Users/Phucx/Desktop/tmail/docs/API.md)** | Toàn tập đặc tả API: Thuật toán ký số HMAC-SHA256 (kèm code mẫu Python, Node.js), Webhook Ingestion, Long-polling, và Admin API. |
| 🛡️ **[SECURITY.md](file:///c:/Users/Phucx/Desktop/tmail/docs/SECURITY.md)** | Hệ thống phân quyền 5 cấp độ (Roles), Cổng thẩm định quyền (Gate), Chống IDOR, Replay Attack (Nonce), và Rate Limiting. |
| ⚙️ **[SETUP.md](file:///c:/Users/Phucx/Desktop/tmail/docs/SETUP.md)** | Hướng dẫn cấu hình môi trường `.env`, Database, Nginx/Apache, tích hợp Cloudflare Email Routing & Worker script. |

---

## 🗺️ Bản Đồ Cấu Trúc Mã Nguồn Thực Tế

Toàn bộ logic cốt lõi của hệ thống được đóng gói tập trung trong [includes/Core/](file:///c:/Users/Phucx/Desktop/tmail/includes/Core/):

```
includes/Core/
├── App.php                      # Application Kernel & Service Container
├── bootstrap.php                # SPL Autoloader chuẩn PSR-4
├── Auth/
│   ├── Role.php                 # Enum 5 vai trò: ADMIN, API_USER, PUBLIC_USER, WEBHOOK, ANONYMOUS
│   ├── Permission.php           # Danh bạ quyền hạn tập trung (Permissions Registry)
│   ├── AuthContext.php          # Object danh tính bất biến của Request
│   ├── Gate.php                 # Cổng kiểm tra quyền hạn (Gate::authorize)
│   └── Authenticator.php        # Bộ phân giải danh tính (HMAC, Session, Webhook Secret)
├── Database/
│   └── DatabaseOptimizer.php   # Tự động thẩm định và tạo composite index lúc runtime
├── Http/
│   ├── Request.php              # Bọc Request bất biến, trích xuất IP, body, query, headers
│   ├── Response.php             # Phát hành JSON Response, tự động gắn CORS & Cache headers
│   └── ApiException.php         # Lớp ngoại lệ chuẩn hóa mã lỗi HTTP (400, 401, 403, 404, 429)
├── Security/
│   ├── RateLimiter.php          # Giới hạn tần suất gửi request với Atomic File-Locking
│   └── ReplayGuard.php          # Ngăn chặn tấn công phát lại bằng Nonce cache có TTL
├── Services/
│   ├── EmailService.php         # Quản lý hộp thư, tạo đơn/lô, xóa, đánh dấu is_done
│   ├── MessageService.php       # Ingestion thư, deduplicate, Quoted-Printable auto decode, brand name
│   ├── DomainService.php        # Quản lý tên miền, ràng buộc toàn vẹn dữ liệu
│   ├── StatsService.php         # Tổng hợp số liệu thống kê realtime
│   ├── CheckerService.php       # Bộ lọc tìm kiếm thư bằng FULLTEXT & LIKE
│   ├── NameGenerator.php        # Sinh username tự nhiên (Anh/Việt + số ngẫu nhiên)
│   └── EmailDecoder.php         # Giải mã MIME RFC 2047 & Quoted-Printable từ Cloudflare
└── Controllers/
    ├── EmailController.php      # Controller dùng chung cho Admin, API Bot và Public User
    ├── MessageController.php    # Controller đọc thư và quản lý thư dùng chung
    ├── DomainController.php     # Controller CRUD Domain
    ├── StatsController.php      # Controller số liệu hệ thống
    ├── CheckerController.php    # Controller lọc từ khóa
    ├── LongPollController.php   # Controller giữ kết nối long-polling realtime
    ├── AuthController.php       # Controller Admin authentication
    └── WebhookController.php    # Controller tiếp nhận mail từ Cloudflare Worker
```

Các file tại `api/` đóng vai trò là **Thin Dispatchers (1 dòng duy nhất)**:
- `api/emails.php` ──> gọi `App::run(EmailController::class)`
- `api/messages.php` ──> gọi `App::run(MessageController::class)`
- `api/long-poll.php` ──> gọi `App::run(LongPollController::class)`
- `api/webhook/receive-email.php` ──> gọi `App::run(WebhookController::class)`
- `api/admin/*.php` ──> gọi các Controller tương ứng
- `api/index.php` ──> Router RESTful tự động ánh xạ path
