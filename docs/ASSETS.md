# Kiến Trúc Quản Lý Tài Nguyên & Cache Busting (Asset Pipeline)

Tài liệu này mô tả chi tiết cơ chế **Content Hashing**, **Minification**, và **Immutable Caching** chuẩn Enterprise được triển khai trên hệ thống KaiMail, giải quyết triệt để vấn đề xung đột cache trình duyệt khi triển khai sản phẩm lên Production.

---

## 1. Triết Lý Thiết Kế & Vấn Đề Cần Giải Quyết

### 1.1. Vấn nạn Cache Trình Duyệt trên Production
Trong các ứng dụng web truyền thống, trình duyệt thường lưu cache các tệp `.css` và `.js` để tăng tốc độ tải trang. Tuy nhiên:
- Khi lập trình viên cập nhật tính năng hoặc sửa lỗi, khách hàng cũ truy cập website vẫn tải phiên bản file CSS/JS cũ trong ổ cứng, dẫn đến **vỡ giao diện**, **lỗi logic JavaScript**, hoặc không nhận giao diện mới.
- Bắt khách hàng bấm `Ctrl + Shift + R` (xóa cache) là giải pháp tối kỵ trong trải nghiệm người dùng (UX).
- Sử dụng Query String (`style.css?v=1.0.1`) có nhược điểm: Một số CDN, Proxy trung gian hoặc trình duyệt mobile bỏ qua chuỗi query và tiếp tục cache tệp cũ.

### 1.2. Giải Pháp Chuẩn Công Nghiệp (Content Hashing + Immutable Caching)
Hệ thống KaiMail áp dụng mô hình phân phối tài nguyên tương tự các nền tảng lớn (Shopify, Next.js, Vercel):
1. **Nội dung quyết định tên file**: Mã băm (SHA-256, 10 ký tự) được tính toán trực tiếp từ nội dung đã nén của file và gắn vào tên tệp: `home.cfd215a304.min.css`.
2. **Cache vĩnh viễn (1 Năm)**: Thiết lập HTTP Header `Cache-Control: public, max-age=31536000, immutable`.
3. **Cập nhật tức thì (Zero-Cache Collision)**: Khi mã nguồn thay đổi, nội dung thay đổi $\rightarrow$ mã hash thay đổi $\rightarrow$ URL thay đổi hoàn toàn. Trình duyệt bắt buộc phải tải tệp mới ngay lập tức mà không cần xóa cache.

---

## 2. Cấu Trúc Thư Mục Tài Nguyên Đã Biên Dịch

Toàn bộ tài nguyên sau khi biên dịch được lưu trữ tập trung tại thư mục `static/`:

```text
static/
├── css/
│   ├── home.cfd215a304.min.css         <-- CSS Trang chủ (Nén -26.2%)
│   └── admin.7538597227.min.css        <-- CSS Admin Dashboard (Nén -27.9%)
├── js/
│   ├── app.3d93c15d68.min.js           <-- JS App chính (Quản lý hộp thư, OTP, 2FA)
│   ├── longPolling.32fa8e2a1f.min.js   <-- JS Long Polling thời gian thực
│   ├── admin.04251f9c48.min.js         <-- JS Core Admin Layout
│   ├── admin-dashboard.dc225ea623.min.js <-- JS Dashboard & Fast Checker
│   └── admin-login.7b23501321.min.js   <-- JS Xử lý đăng nhập Admin
└── manifest.json                       <-- Bảng ánh xạ định tuyến tài nguyên
```

### Bảng Ánh Xạ `static/manifest.json`
Tệp JSON này là "Single Source of Truth" để tầng Backend ánh xạ đường dẫn logic sang tệp hash thực tế:

```json
{
    "/css/home.css": "/static/css/home.cfd215a304.min.css",
    "/css/admin.css": "/static/css/admin.7538597227.min.css",
    "/js/app.js": "/static/js/app.3d93c15d68.min.js",
    "/js/longPolling.js": "/static/js/longPolling.32fa8e2a1f.min.js",
    "/js/admin.js": "/static/js/admin.04251f9c48.min.js",
    "/js/admin-dashboard.js": "/static/js/admin-dashboard.dc225ea623.min.js",
    "/js/admin-login.js": "/static/js/admin-login.7b23501321.min.js"
}
```

---

## 3. Bộ Build Script Đa Nền Tảng (Cross-Platform Compilers)

Dự án trang bị hai bộ công cụ biên dịch chạy song song, đảm bảo hoạt động hoàn hảo trên mọi môi trường:

### 3.1. Node.js Compiler: `scripts/build.mjs`
- **Mục đích**: Dành cho môi trường phát triển (Local Dev) và CI/CD có sẵn Node.js.
- **Cách chạy**:
  ```bash
  npm run build
  # Hoặc
  node scripts/build.mjs
  ```
- **Tính năng**:
  - Tự động dọn dẹp các tệp build cũ trong `static/css/` và `static/js/`.
  - Nén CSS: Loại bỏ chú thích, khoảng trắng thừa, nén bộ chọn.
  - Tự động viết lại đường dẫn tương đối (Path Rewriting): Đổi `../assets/` thành `../../assets/` để tài nguyên font/cursor/ảnh load chính xác từ thư mục con `static/css/`.
  - Nén JS: Loại bỏ chú thích và dòng trống an toàn.
  - Sinh mã hash SHA-256 10 ký tự và xuất ra `static/manifest.json`.

### 3.2. PHP Pure Compiler: `scripts/build.php`
- **Mục đích**: Dành cho máy chủ Production (Shared Hosting cPanel/DirectAdmin, VPS) không cài đặt Node.js/NPM.
- **Cách chạy**:
  ```bash
  php scripts/build.php
  ```
- **Tính năng**: Sử dụng 100% PHP thuần (hàm chuỗi, regex và `hash('sha256')`), không phụ thuộc vào bất kỳ thư viện bên ngoài nào (`vendor` hay `node_modules`).

---

## 4. Cơ Chế Phục Vụ & Fallback An Toàn (AssetService)

Được quản lý bởi lớp [AssetService.php](../includes/Core/Services/AssetService.php):

```mermaid
flowchart TD
    A["Gọi asset_url('/css/home.css')"] --> B{"Tồn tại static/manifest.json?"}
    B -- Có --> C{"Khóa có trong manifest?"}
    C -- Có --> D["Trả về: BASE_URL + /static/css/home.[hash].min.css<br/>(Cache 1 Năm 0ms)"]
    C -- Không --> E["Fallback: BASE_URL + /css/home.css?v=filemtime"]
    B -- Không --> E
    E --> F["Hiển thị bình thường (Không bao giờ lỗi trang)"]
```

### 4.1. Hàm Trợ Giúp Toàn Cục `asset_url()`
Được nạp sẵn tại [bootstrap.php](../includes/Core/bootstrap.php). Sử dụng tiện lợi trong mọi file giao diện PHP:

```php
<!-- Nhúng CSS trang chủ -->
<link rel="stylesheet" href="<?= asset_url('/css/home.css') ?>">

<!-- Nhúng JavaScript -->
<script src="<?= asset_url('/js/app.js') ?>"></script>
```

### 4.2. Khả Năng Tự Phục Hồi (Self-Healing Fallback)
Nếu lập trình viên vô tình xóa thư mục `static/` hoặc chưa chạy build:
1. `AssetService` tự động phát hiện `manifest.json` không tồn tại.
2. Hệ thống chuyển sang cơ chế fallback: đọc tệp gốc trong `css/` hoặc `js/` và thêm tham số thời gian sửa file `?v=filemtime`.
3. **Cam kết**: Website hoạt động 100% bình thường, không bao giờ bị màn hình trắng hoặc lỗi 404.

---

## 5. Cấu Hình Máy Chủ Web (.htaccess)

Tại tệp [.htaccess](../.htaccess), các quy tắc phân phối cache hiệu năng cao được thiết lập:

```apache
# 1. Bật hạn sử dụng 1 năm cho tài nguyên tĩnh
<IfModule mod_expires.c>
    ExpiresActive On
    ExpiresByType text/css "access plus 1 year"
    ExpiresByType application/javascript "access plus 1 year"
    ExpiresByType image/png "access plus 1 year"
    ExpiresByType image/jpeg "access plus 1 year"
    ExpiresByType image/svg+xml "access plus 1 year"
</IfModule>

# 2. Gắn cờ Immutable cho các tệp đã gắn mã hash
<IfModule mod_headers.c>
    <FilesMatch "\.(?:[a-f0-9]{8,12}\.min\.(?:css|js)|[a-f0-9]{8,12}\.(?:png|jpg|svg))$">
        Header set Cache-Control "public, max-age=31536000, immutable"
    </FilesMatch>
</IfModule>
```

---

## 6. Quy Trình Làm Việc & Triển Khai (Deployment Workflow)

### Bước 1: Phát Triển Tại Local
Lập trình viên chỉnh sửa giao diện và tính năng trong các tệp gốc:
- `css/home.css`, `css/admin.css`
- `js/app.js`, `js/admin.js`, `js/admin-dashboard.js`, v.v.

### Bước 2: Đóng Gói Sản Phẩm
Trước khi đẩy code lên Git:
```bash
npm run build
```
*(Hoặc `php scripts/build.php` nếu dùng PHP).*

### Bước 3: Đẩy Code Lên GitHub
```bash
git add .
git commit -m "feat: your new feature"
git push origin main
```

### Bước 4: Tự Động Triển Khai Trên Máy Chủ (Auto-Deploy)
Tệp cron job [git_deploy.sh](../git_deploy.sh) trên máy chủ hosting sẽ:
1. Thực thi `git pull origin main --ff-only` để kéo các tệp code và thư mục `static/` mới nhất về.
2. Tự động kiểm tra và thực thi lệnh biên dịch dự phòng:
   ```bash
   if [ -f "$PROJECT_DIR/scripts/build.php" ]; then
       php "$PROJECT_DIR/scripts/build.php"
   fi
   ```
3. Website trên production cập nhật ngay lập tức sang phiên bản mã băm mới.
