# Kiến Trúc Quản Lý Tài Nguyên & Cache Busting (Asset Pipeline)

Tài liệu này mô tả chi tiết cơ chế **Content Hashing**, **Terser AST Mangling**, **Minification**, và **Immutable Caching** chuẩn Enterprise được triển khai trên hệ thống KaiMail, giải quyết triệt để vấn đề xung đột cache trình duyệt và tối ưu bảo mật chống dịch ngược logic frontend khi triển khai sản phẩm lên Production.

---

## 1. Triết Lý Thiết Kế & Vấn Đề Cần Giải Quyết

### 1.1. Vấn nạn Cache Trình Duyệt trên Production
Trong các ứng dụng web truyền thống, trình duyệt thường lưu cache các tệp `.css` và `.js` để tăng tốc độ tải trang. Tuy nhiên:
- Khi lập trình viên cập nhật tính năng hoặc sửa lỗi, khách hàng cũ truy cập website vẫn tải phiên bản file CSS/JS cũ trong ổ cứng, dẫn đến **vỡ giao diện**, **lỗi logic JavaScript**, hoặc không nhận giao diện mới.
- Bắt khách hàng bấm `Ctrl + Shift + R` (xóa cache) là giải pháp tối kỵ trong trải nghiệm người dùng (UX).
- Sử dụng Query String (`style.css?v=1.0.1`) có nhược điểm: Một số CDN, Proxy trung gian hoặc trình duyệt mobile bỏ qua chuỗi query và tiếp tục cache tệp cũ.

### 1.2. Giải Pháp Chuẩn Công Nghiệp (Content Hashing + AST Mangling + Immutable Caching)
Hệ thống KaiMail áp dụng mô hình phân phối tài nguyên tương tự các nền tảng lớn (Anthropic, Shopify, Next.js, Vercel):
1. **Nội dung quyết định tên file**: Mã băm (SHA-256, 10 ký tự) được tính toán trực tiếp từ nội dung đã nén và mangled của file và gắn vào tên tệp: `home.cfd215a304.min.css`.
2. **AST Mangling (Chuẩn Anthropic)**: Thu gọn toàn bộ biến cục bộ và tham số thành `t, e, i, a, b...`, nén boolean `!0 / !1`, loại bỏ source maps hoàn toàn để vừa giảm 50% dung lượng vừa ngăn chặn dịch ngược mã nguồn qua DevTools.
3. **Cache vĩnh viễn (1 Năm)**: Thiết lập HTTP Header `Cache-Control: public, max-age=31536000, immutable`.
4. **Cập nhật tức thì (Zero-Cache Collision)**: Khi mã nguồn thay đổi $\rightarrow$ nội dung thay đổi $\rightarrow$ mã hash thay đổi $\rightarrow$ URL thay đổi hoàn toàn. Trình duyệt bắt buộc phải tải tệp mới ngay lập tức mà không cần xóa cache.

---

## 2. Cấu Trúc Thư Mục Tài Nguyên Đã Biên Dịch

Toàn bộ tài nguyên sau khi biên dịch được lưu trữ tập trung tại thư mục `static/`:

```text
static/
├── css/
│   ├── home.cfd215a304.min.css         <-- CSS Trang chủ (Nén -26.2%)
│   └── admin.7538597227.min.css        <-- CSS Admin Dashboard (Nén -27.9%)
├── js/
│   ├── app.4c82f01c62.min.js           <-- JS App chính (Mangled -43.5%, 52.8KB)
│   ├── longPolling.8419155615.min.js   <-- JS Long Polling thời gian thực (Mangled -43.5%, 4.6KB)
│   ├── admin.8da0c29c03.min.js         <-- JS Core Admin Layout (Mangled -48.5%, 12.4KB)
│   ├── admin-dashboard.c7920f71a8.min.js <-- JS Dashboard & Fast Checker (Mangled -31.3%, 29.4KB)
│   └── admin-login.4aacbbfde7.min.js   <-- JS Xử lý đăng nhập Admin (Mangled -46.0%, 4.0KB)
└── manifest.json                       <-- Bảng ánh xạ định tuyến tài nguyên
```

### Bảng Ánh Xạ `static/manifest.json`
Tệp JSON này là "Single Source of Truth" để tầng Backend ánh xạ đường dẫn logic sang tệp hash thực tế:

```json
{
    "/css/home.css": "/static/css/home.cfd215a304.min.css",
    "/css/admin.css": "/static/css/admin.7538597227.min.css",
    "/js/app.js": "/static/js/app.4c82f01c62.min.js",
    "/js/longPolling.js": "/static/js/longPolling.8419155615.min.js",
    "/js/admin.js": "/static/js/admin.8da0c29c03.min.js",
    "/js/admin-dashboard.js": "/static/js/admin-dashboard.c7920f71a8.min.js",
    "/js/admin-login.js": "/static/js/admin-login.4aacbbfde7.min.js"
}
```

---

## 3. Bộ Công Cụ Biên Dịch & Vận Hành (Compilers & DevOps)

### 3.1. Node.js Compiler: `scripts/build.mjs` (Enterprise Mangler & Minifier)
- **Mục đích**: Dành cho môi trường phát triển (Local Dev) và CI/CD có sẵn Node.js.
- **Cách chạy**:
  ```bash
  npm run build
  # Hoặc
  node scripts/build.mjs
  ```
- **Tính năng (Chuẩn Anthropic / Big Tech)**:
  - **Terser AST Mangling**: Thu gọn toàn bộ biến cục bộ, tham số hàm thành `t, e, i, a, b...` (Giảm gần 50% kích thước JavaScript).
  - **Dead-Code Elimination & Syntax Inlining**: Ép hằng số, ép boolean `!0 / !1`, loại bỏ debugger/console thừa.
  - **Source Map Suppression**: Tắt hoàn toàn bản đồ mã nguồn, người ngoài xem DevTools chỉ thấy 1 khối code đặc quánh không thể đảo ngược logic.
  - **Nén CSS**: Loại bỏ chú thích, khoảng trắng thừa, nén bộ chọn.
  - **Path Rewriting**: Đổi `../assets/` thành `../../assets/` để font/cursor/ảnh load chuẩn từ `static/css/`.
  - **Content Hashing**: Sinh mã hash SHA-256 10 ký tự và ghi đè vào `static/manifest.json`.

### 3.2. PHP Pure Compiler: `scripts/build.php` (Server-Side Fallback)
- **Mục đích**: Dành cho máy chủ Production (Shared Hosting cPanel/DirectAdmin, VPS) không cài đặt Node.js/NPM.
- **Cách chạy**:
  ```bash
  php scripts/build.php
  ```
- **Tính năng**: Sử dụng 100% PHP thuần (hàm chuỗi, regex và `hash('sha256')`), không phụ thuộc vào bất kỳ thư viện bên ngoài nào (`vendor` hay `node_modules`).

### 3.3. Database Migration: `scripts/migrate.php` (Đồng Bộ Cơ Sở Dữ Liệu)
- **Mục đích**: Tự động kiểm tra và bù các bảng, cột, chỉ mục (indexes) bị thiếu trên Database Production.
- **Cách chạy**:
  ```bash
  php scripts/migrate.php
  ```
- **Tính năng**:
  - Tự động bổ sung cột `snippet` và backfill trích đoạn xem trước cho hàng trăm email cũ.
  - Tự động bổ sung cột `created_by` + index `idx_created_by` (phục vụ lọc email Admin / Guest / API).
  - Tự động bổ sung cột `note`, `is_done`, `name_type` và xác thực bảng `settings`.
  - Hoàn toàn an toàn (idempotent), không bao giờ làm mất mát dữ liệu hiện có.

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

## 5. Cấu Hình Máy Chủ Web (.htaccess) & Tại Sao Cache 1 Năm?

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

### Tại Sao Lại Là 1 Năm (`max-age=31536000, immutable`)?

Nhiều người e ngại: *"Set cache 1 năm lỡ sửa code thì khách hàng bị kẹt giao diện cũ 1 năm à?"*
**Câu trả lời là KHÔNG BAO GIỜ!** Đây chính là sức mạnh tối thượng của kiến trúc **Content Hashing**:

1. **Chuẩn RFC 8246 & Google Web Vitals**: Con số `31536000` giây (365 ngày) là mức tối đa tiêu chuẩn cho tài nguyên bất biến.
2. **Cờ `immutable` = Tải trang trong 0ms**: Ra lệnh cho trình duyệt: *"File này không bao giờ bị thay đổi nội dung trong suốt vòng đời của nó. Đừng tốn công gửi request hỏi lại server (No 304 Revalidation) khi người dùng F5 hoặc chuyển trang."* Kết quả là tốc độ tải file đạt **0ms (from disk cache)**.
3. **Cơ Chế Bẻ Cache Tức Thì (Instant Cache Invalidation)**:
   - File CSS có tên: `home.cfd215a304.min.css`.
   - Khi bạn sửa dù chỉ 1 dòng CSS, mã SHA-256 đổi thành `home.9a8b7c6d5e.min.css`.
   - File HTML (`index.php`) **KHÔNG** bị cache bất biến. Khi người dùng mở trang, trình duyệt nhận HTML mới có chứa đường dẫn file mới.
   - Vì đường dẫn file hoàn toàn mới, trình duyệt coi đây là một tài nguyên chưa từng thấy và lập tức tải ngay file mới về trong tích tắc.
   - **Tóm lại**: File cũ vẫn nằm trong cache nhưng không ai gọi tới nó nữa. File mới được nạp tức thì trong 0 giây, không cần khách hàng phải bấm `Ctrl + Shift + R`.

---

## 6. Quy Trình Triển Khai Trên Hosting (Deployment Workflow)

### Bước 1: Phát Triển & Đóng Gói Tại Local
```bash
# 1. Sửa code trong css/ và js/
# 2. Biên dịch nén, mangling và tạo hash
npm run build

# 3. Đẩy code lên GitHub
git add .
git commit -m "feat: updates"
git push origin main
```

---

### Bước 2: Triển Khai Lên Máy Chủ Hosting

Bạn có hai cách triển khai trên hosting (chọn cách phù hợp):

#### Cách A: Dùng Trực Tiếp Terminal Trên Hosting (Khuyên Dùng khi có SSH/Terminal)
Nếu Hosting của bạn (cPanel / DirectAdmin) có hỗ trợ tính năng **Terminal** trong bảng điều khiển:

1. Đăng nhập vào Control Panel của Hosting $\rightarrow$ Mở mục **Terminal**.
2. Chuyển vào thư mục web root:
   ```bash
   cd ~/domains/tmail.kaishop.id.vn/public_html
   ```
3. Chạy 1 lệnh duy nhất để tự động kéo code và biên dịch:
   ```bash
   bash git_deploy.sh
   ```
   *(Script sẽ tự động kéo `git pull`, bảo toàn các file mangled xịn từ local, và in log trực tiếp ra màn hình terminal).*

4. **Nếu có cập nhật Database**:
   ```bash
   php scripts/migrate.php
   ```

---

#### Cách B: Tự Động Triển Khai Bằng Cron Job (Không cần gõ lệnh)
Nếu bạn không muốn mỗi lần update phải mở terminal gõ lệnh:

1. Vào mục **Cron Jobs** trên trang quản trị Hosting.
2. Thêm một Cron Job chạy định kỳ mỗi 2 đến 5 phút:
   ```bash
   /bin/bash /home/kaishopi/domains/tmail.kaishop.id.vn/public_html/git_deploy.sh
   ```
3. Mỗi khi bạn `git push` từ máy tính lên GitHub, máy chủ sẽ tự động `git pull` và cập nhật web. Nhật ký triển khai được lưu lại tại `storage/logs/cron_deploy.log`.
