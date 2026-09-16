/**
 * KaiMail Toast & Notification Helpers
 * Integrates with SweetAlert2 for premium toast alerts, with fallback to standard alert.
 */

export function toast(message, type = "info") {
    const iconMap = { success: "success", error: "error", warning: "warning", info: "info" };
    const text = localizeError(message);

    if (window.Swal && typeof window.Swal.fire === "function") {
        window.Swal.fire({
            toast: true,
            position: "top-end",
            icon: iconMap[type] || "info",
            title: text,
            showConfirmButton: false,
            timer: type === "error" ? 4000 : 2500,
            timerProgressBar: true,
            customClass: { popup: "km-toast" },
        });
        return;
    }

    alert(text);
}

export function localizeError(message) {
    if (!message) return "Đã xảy ra lỗi";
    const raw = String(message).trim();
    const key = raw.toLowerCase();

    // Prevent leaking backend fatal errors, uncaught exceptions, or stack traces
    if (/call to undefined|uncaught error|fatal error|pdoexception|syntax error|parse error|fatal/i.test(raw)) {
        return "Lỗi máy chủ. Vui lòng thử lại sau";
    }
    if (/chưa có email nào để xóa/i.test(raw)) {
        return "Vui lòng nhập địa chỉ email cần xóa";
    }

    const map = {
        unauthorized: "Không được phép truy cập",
        forbidden: "Truy cập bị từ chối",
        ratelimitexceeded: "Bạn đang thao tác quá nhanh, vui lòng đợi",
        "method not allowed": "Phương thức không được hỗ trợ",
        "not found": "Không tìm thấy dữ liệu",
        "an error occurred": "Đã xảy ra lỗi",
        "internal server error": "Lỗi máy chủ nội bộ",
        "server error": "Lỗi máy chủ",
        "email is required": "Email là bắt buộc",
        "email not found": "Email không tồn tại trong hệ thống",
        "email has expired": "Mail này đã hết hạn",
        "message not found": "Không tìm thấy tin nhắn",
        "polling failed": "Không thể đồng bộ hộp thư",
        "database connection failed": "Không thể kết nối cơ sở dữ liệu",
        "invalid json": "Dữ liệu JSON không hợp lệ",
        "missing required fields": "Thiếu dữ liệu bắt buộc",
        "email_id required": "Thiếu email_id",
    };
    const result = map[key] || raw;
    if (result === raw && key.includes("tồn tại")) return "Email không tồn tại trong hệ thống";
    if (result === raw && key.includes("hết hạn")) return "Mail này đã hết hạn";
    return result;
}
