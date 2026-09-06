# Hướng Dẫn Cài Đặt & Triển Khai (Setup & Deployment)

Tài liệu này hướng dẫn chi tiết từng bước cài đặt, cấu hình môi trường và triển khai hệ thống KaiMail trên máy chủ thực tế.

---

## 1. Yêu Cầu Môi Trường (Prerequisites)

- **PHP**: Phiên bản tối thiểu **PHP 8.2**.
  - Các extension bắt buộc: `pdo`, `pdo_mysql`, `mbstring`, `openssl`, `json`.
- **Cơ sở dữ liệu**: MySQL 5.7+ hoặc MariaDB 10.3+.
- **Máy chủ Web**: Apache 2.4+ (có bật `mod_rewrite`) hoặc Nginx 1.18+.
- **Tên miền**: Đã kích hoạt trên Cloudflare để sử dụng Email Routing.

---

## 2. Cấu Hình Tệp Môi Trường (`.env`)

Tạo tệp `.env` tại thư mục gốc từ mẫu `.env.example`:
```bash
cp .env.example .env
```

Cấu hình các thông số quan trọng:

```ini
# --- CƠ SỞ DỮ LIỆU ---
DB_HOST=127.0.0.1
DB_PORT=3306
DB_NAME=kaimail
DB_USER=root
DB_PASS=your_db_password

# --- ĐƯỜNG DẪN GỐC WEBSITE ---
BASE_URL=https://kaishop.id.vn

# --- KHÓA BẢO MẬT HỆ THỐNG ---
# Khóa đăng nhập Admin (/adminkaishop)
ADMIN_ACCESS_KEY=TaoChuoiNgauNhienDaiItNhat32KyTu

# Cặp khóa cho Bot & Client gọi API qua HMAC
API_ACCESS_KEY=ChuoiHexNgauNhien64KyTuLamAccessKey
API_SECRET_KEY=ChuoiHexNgauNhien64KyTuLamSecretKey

# Khóa bí mật giao tiếp giữa Cloudflare Worker và Backend
WEBHOOK_SECRET=ChuoiNgauNhienBiMatChoCloudflareWorker

# --- CẤU HÌNH LOG & RATE LIMIT ---
APP_ENV=production
APP_DEBUG=false
API_RATE_LIMIT_PER_MIN=120
ADMIN_RATE_LIMIT_PER_MIN=60
```

---

## 3. Khởi Tạo Cơ Sở Dữ Liệu

1. Đăng nhập MySQL và tạo database:
```sql
CREATE DATABASE `kaimail` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
```

2. Nhập cấu trúc bảng từ tệp [data.sql](../data.sql):
```bash
mysql -u root -p kaimail < data.sql
```

3. Thêm tên miền chính của bạn vào bảng `domains`:
```sql
INSERT INTO `domains` (`domain`, `is_active`) VALUES ('kaishop.id.vn', 1);
```

---

## 4. Phân Quyền Thư Mục Lưu Trữ (`storage/`)

Thư mục `storage/` dùng để ghi log nhận thư và lưu trữ cache rate limit/nonce. Cần cấp quyền ghi cho Web Server:

```bash
chmod -R 775 storage/
chown -R www-data:www-data storage/
```

---

## 5. Cấu Hình Máy Chủ Web

### 5.1. Apache
Đảm bảo đã kích hoạt module `mod_rewrite`:
```bash
sudo a2enmod rewrite
sudo systemctl restart apache2
```
Tệp [.htaccess](../.htaccess) tại thư mục gốc sẽ tự động chuyển hướng các đường dẫn `/api/...` về [api/index.php](../api/index.php).

### 5.2. Nginx
Cấu hình VirtualHost mẫu cho Nginx:

```nginx
server {
    listen 80;
    server_name kaishop.id.vn;
    return 301 https://$host$request_uri;
}

server {
    listen 443 ssl http2;
    server_name kaishop.id.vn;
    root /var/www/kaimail;
    index index.php index.html;

    ssl_certificate /path/to/fullchain.pem;
    ssl_certificate_key /path/to/privkey.pem;

    # Chặn truy cập trực tiếp vào các file nhạy cảm
    location ~ /\.(env|git|htaccess) {
        deny all;
        return 404;
    }

    location ~ ^/(storage|config|docs)/ {
        deny all;
        return 404;
    }

    # Định tuyến API
    location /api/ {
        try_files $uri $uri/ /api/index.php?$query_string;
    }

    # Xử lý PHP
    location ~ \.php$ {
        include snippets/fastcgi-php.conf;
        fastcgi_pass unix:/run/php/php8.2-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
        include fastcgi_params;
    }
}
```

---

## 6. Tích Hợp Cloudflare Email Routing & Worker

### Bước 1: Bật Email Routing trên Cloudflare
1. Truy cập Cloudflare Dashboard -> chọn tên miền (ví dụ: `kaishop.id.vn`).
2. Vào tab **Email** -> **Email Routing**.
3. Làm theo hướng dẫn của Cloudflare để thêm các bản ghi DNS `MX` và `TXT` (SPF).

### Bước 2: Tạo và Triển Khai Cloudflare Worker
1. Vào **Workers & Pages** -> Bấm **Create Application** -> **Create Worker**.
2. Đặt tên worker (ví dụ: `kaimail-worker`).
3. Dán toàn bộ mã nguồn từ tệp [cloudflare-worker.js](../cloudflare-worker.js) vào trình soạn thảo và bấm **Deploy**.
4. Vào tab **Settings** của Worker -> chọn **Variables**:
   - Thêm biến `BACKEND_URL`: `https://kaishop.id.vn/api/webhook/receive-email.php`
   - Thêm biến `WEBHOOK_SECRET`: Nhập chính xác chuỗi `WEBHOOK_SECRET` đã cài đặt trong file `.env`.

### Bước 3: Cấu Hình Routing Rule
1. Quay lại Cloudflare Dashboard -> Tên miền -> **Email Routing** -> tab **Routing rules**.
2. Ở phần **Catch-all address**:
   - Bấm **Edit**.
   - Action: Chọn **Send to a Worker**.
   - Destination: Chọn worker `kaimail-worker`.
   - Bấm **Save**.

---

## 7. Kiểm Thử Hệ Thống (Automated Testing)

Sau khi cài đặt xong, bạn có thể chạy bộ kiểm thử tự động để xác nhận toàn bộ 44 chỉ mục an ninh và nghiệp vụ hoạt động hoàn hảo:

```bash
php scratch/test_refactor.php
```

Khi kết quả hiển thị:
```
=== ALL TESTS FINISHED: 44 PASSED, 0 FAILED ===
```
Hệ thống đã đạt tiêu chuẩn triển khai Production.

---

## 8. Bảng Xử Lý Sự Cố (Troubleshooting)

| Vấn đề | Nguyên nhân | Cách khắc phục |
|---|---|---|
| **HTTP 500 Internal Server Error** | Sai thông tin kết nối DB hoặc chưa có `.env`. | Kiểm tra `DB_HOST`, `DB_USER`, `DB_PASS` trong file `.env`. Mở file log lỗi của máy chủ web. |
| **Worker không đẩy thư về được** | Sai `WEBHOOK_SECRET` hoặc sai URL backend. | Kiểm tra biến `WEBHOOK_SECRET` trong Worker Settings và đối chiếu với file `.env`. Xem log tại `storage/logs/webhook.log`. |
| **HTTP 401 Unauthorized khi gọi API** | Sai lệch thời gian hệ thống hoặc sai chữ ký HMAC. | Đảm bảo máy client đồng bộ giờ qua NTP (`abs(time() - ts) <= 300s`). Kiểm tra lại thuật toán ký chữ ký. |
| **HTTP 403 Forbidden khi xem thư** | Tham số `email` không trùng với người nhận trong DB. | Đây là tính năng bảo mật chống đọc trộm IDOR của hệ thống. Client phải truyền kèm `email` chính xác của hộp thư. |
| **Không đăng nhập được Admin** | Chưa nhập đúng `ADMIN_ACCESS_KEY`. | Đăng nhập tại `/adminkaishop/login.php` bằng mật khẩu là chuỗi `ADMIN_ACCESS_KEY` trong `.env`. |
