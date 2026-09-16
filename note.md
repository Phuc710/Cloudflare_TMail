 Kích hoạt Email Routing
Vào tên miền kaishop.id.vn trên Cloudflare.
Chọn Email -> Email Routing.
Ở phần Destination addresses, thêm email của mày để verify (nếu chưa làm).
Ở phần Routing rules, chọn Catch-all address hoặc tạo Rule mới, sau đó chọn Action là Send to Worker và chọn cái Worker kaishop của mày.


Edit Logo: https://jitter.video/



 Auto-Recovery thông minh: Tự check tài khoản trước khi restore; tự đổi server mới nếu IP cũ chết;
- Fix lỗi Check Live Die ảo: Khắc phục nghẽn kết nối xoay vòng 4 site Geo-IP không bị rate limit; mặc định 5 luồng check.
- Tối ưu Xoay Proxy Die: Xoay xong delay đúng 3s và chỉ check lại các proxy vừa xoay (không check lại toàn bộ).
- Tạm dừng tạo proxy Dcom: Do nhà mạng siết chặt phân phối IP nên không xoay được. Đang tìm giải pháp thay thế khác. 