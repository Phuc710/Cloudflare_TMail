/**
 * KaiMail Admin - Shared UI Core (không dùng cookie/session).
 */
class AdminCore {
    constructor(baseUrl) {
        this.baseUrl = (baseUrl || "").replace(/\/+$/, "");
        this.toastTimer = null;
        this.activeModalCount = 0;
        this.adminKeyStorage = "kaimail_admin_access_key";
        this.vnLocale = "vi-VN";
        this.vnTimeZone = "Asia/Ho_Chi_Minh";
    }

    async init() {
        const isAuthenticated = await this.ensureAdminUiAuthenticated();
        if (isAuthenticated === false) {
            return;
        }
        this.bindLogout();
        this.bindModalSystem();
        this.bindCreateEmailForm();
        this.bindAddDomainForm();
        this.bindDomainDeleteButtons();
        this.bindMobileMenu();
    }

    buildUrl(path) {
        if (!path) return this.baseUrl;
        if (/^https?:\/\//i.test(path)) return path;
        const normalized = path.startsWith("/") ? path : `/${path}`;
        return `${this.baseUrl}${normalized}`;
    }

    clearAdminAccessKey() {
        // No-op, cookies are handled by server
    }

    isLoginPage() {
        return window.location.pathname.includes("/adminkaishop/login");
    }

    isAdminUiPage() {
        return window.location.pathname.includes("/adminkaishop");
    }

    async ensureAdminUiAuthenticated() {
        if (!this.isAdminUiPage() || this.isLoginPage()) {
            return true;
        }

        try {
            const response = await fetch(this.buildUrl("/api/admin/auth.php"), {
                method: "GET",
            });

            if (!response.ok) {
                if (response.status === 401) {
                    window.location.href = this.buildUrl("/adminkaishop/login");
                    return false;
                }

                let data = null;
                try {
                    data = await response.json();
                } catch (error) {
                    data = null;
                }

                const message = data?.message || data?.error || "Không thể xác thực phiên admin";
                this.showToast(message, "error");
                return false;
            }

            return true;
        } catch (error) {
            // Lỗi mạng tạm thời: giữ nguyên trang để người dùng thử lại.
            return true;
        }
    }

    buildHeaders(path, existingHeaders = {}) {
        const headers = new Headers(existingHeaders || {});
        if (!headers.has("Content-Type")) {
            headers.set("Content-Type", "application/json");
        }
        return headers;
    }

    async fetchJson(path, options = {}) {
        const requestOptions = { ...options };
        requestOptions.headers = this.buildHeaders(path, requestOptions.headers || {});

        const response = await fetch(this.buildUrl(path), requestOptions);
        let data = null;

        try {
            data = await response.json();
        } catch (error) {
            data = null;
        }

        if (response.status === 401 && this.isAdminUiPage() && !this.isLoginPage() && path.startsWith("/api/admin/")) {
            this.clearAdminAccessKey();
            window.location.href = this.buildUrl("/adminkaishop/login");
        }

        return { ok: response.ok, status: response.status, data };
    }

    async postJson(path, payload) {
        return this.fetchJson(path, {
            method: "POST",
            headers: { "Content-Type": "application/json" },
            body: JSON.stringify(payload),
        });
    }

    showToast(message, type = "") {
        const localizedMessage = this.localizeApiError(message);
        const swal = window.Swal;
        if (swal && typeof swal.fire === "function") {
            const iconMap = {
                success: "success",
                error: "error",
                warning: "warning",
                info: "info",
            };

            swal.fire({
                toast: true,
                position: "top-end",
                icon: iconMap[type] || "info",
                title: localizedMessage,
                showConfirmButton: false,
                timer: 3000,
                timerProgressBar: true,
            });
            return;
        }

        const toast = document.getElementById("toast");
        if (!toast) return;

        toast.textContent = localizedMessage;
        toast.className = `toast show ${type}`.trim();

        if (this.toastTimer) {
            clearTimeout(this.toastTimer);
        }

        this.toastTimer = setTimeout(() => {
            toast.className = "toast";
        }, 3000);
    }

    async confirmAction({
        title = "Xác nhận thao tác",
        text = "Bạn có chắc muốn tiếp tục?",
        confirmButtonText = "Xác nhận",
        cancelButtonText = "Hủy",
        icon = "warning",
    } = {}) {
        const swal = window.Swal;
        if (swal && typeof swal.fire === "function") {
            const result = await swal.fire({
                title,
                text,
                icon,
                showCancelButton: true,
                confirmButtonText,
                cancelButtonText,
                reverseButtons: true,
                focusCancel: true,
            });
            return Boolean(result.isConfirmed);
        }

        return window.confirm(text || title);
    }

    localizeApiError(message) {
        if (!message) return "Đã xảy ra lỗi";
        const text = String(message).trim();
        const lowered = text.toLowerCase();

        const dictionary = {
            unauthorized: "Không được phép truy cập",
            "method not allowed": "Phương thức không được hỗ trợ",
            "not found": "Không tìm thấy dữ liệu",
            "an error occurred": "Đã xảy ra lỗi",
            "internal server error": "Lỗi máy chủ nội bộ",
            "server error": "Lỗi máy chủ",
            "fatal error": "Lỗi nghiêm trọng",
            "email is required": "Email là bắt buộc",
            "invalid email format": "Định dạng email không hợp lệ",
            "email not found": "Email không tồn tại trong hệ thống",
            "email has expired": "Email đã hết hạn",
            "message not found": "Không tìm thấy tin nhắn",
            "polling failed": "Không thể đồng bộ hộp thư",
            "database connection failed": "Không thể kết nối cơ sở dữ liệu",
            "invalid json": "Dữ liệu JSON không hợp lệ",
            "missing required fields": "Thiếu trường dữ liệu bắt buộc",
            "email_id required": "Thiếu email_id"
        };

        return dictionary[lowered] || text;
    }

    escapeHtml(value) {
        if (value === null || value === undefined) return "";
        const div = document.createElement("div");
        div.textContent = String(value);
        return div.innerHTML;
    }

    async copyToClipboard(text) {
        const safeText = String(text || "");
        if (!safeText) return;

        try {
            await navigator.clipboard.writeText(safeText);
            this.showToast("Đã sao chép vào clipboard", "success");
            return;
        } catch (error) {
            const input = document.createElement("input");
            input.value = safeText;
            document.body.appendChild(input);
            input.select();
            document.execCommand("copy");
            document.body.removeChild(input);
            this.showToast("Đã sao chép vào clipboard", "success");
        }
    }

    parseDateInput(dateValue) {
        if (dateValue instanceof Date) {
            return Number.isNaN(dateValue.getTime()) ? null : dateValue;
        }

        const raw = String(dateValue || "").trim();
        if (raw === "") {
            return null;
        }

        // Chuẩn "YYYY-MM-DD HH:mm:ss" từ MySQL: hiểu là giờ VN (+07:00).
        const sqlMatch = raw.match(
            /^(\d{4})-(\d{2})-(\d{2})(?:[ T](\d{2}):(\d{2})(?::(\d{2}))?)?$/
        );
        const hasZone = /[zZ]$|[+-]\d{2}:\d{2}$/.test(raw);

        if (sqlMatch && !hasZone) {
            const year = Number(sqlMatch[1]);
            const month = Number(sqlMatch[2]);
            const day = Number(sqlMatch[3]);
            const hour = Number(sqlMatch[4] || "0");
            const minute = Number(sqlMatch[5] || "0");
            const second = Number(sqlMatch[6] || "0");
            const utcMs = Date.UTC(year, month - 1, day, hour - 7, minute, second);
            return new Date(utcMs);
        }

        const parsed = new Date(raw);
        if (Number.isNaN(parsed.getTime())) {
            return null;
        }
        return parsed;
    }

    getTimePartsVN(dateValue) {
        const parsedDate = this.parseDateInput(dateValue);
        if (!parsedDate) return null;

        const parts = new Intl.DateTimeFormat("en-GB", {
            timeZone: this.vnTimeZone,
            hour12: false,
            year: "numeric",
            month: "2-digit",
            day: "2-digit",
            hour: "2-digit",
            minute: "2-digit",
            second: "2-digit",
        }).formatToParts(parsedDate);

        const map = Object.fromEntries(parts.map((part) => [part.type, part.value]));
        return {
            year: map.year,
            month: map.month,
            day: map.day,
            hour: map.hour,
            minute: map.minute,
            second: map.second,
        };
    }

    parseToGMT7(dateValue) {
        const vnParts = this.getTimePartsVN(dateValue);
        if (!vnParts) return new Date(NaN);

        const utcMs = Date.UTC(
            Number(vnParts.year),
            Number(vnParts.month) - 1,
            Number(vnParts.day),
            Number(vnParts.hour),
            Number(vnParts.minute),
            Number(vnParts.second)
        );
        return new Date(utcMs);
    }

    formatDateVN(dateValue) {
        const vnParts = this.getTimePartsVN(dateValue);
        if (!vnParts) return "";
        return `${vnParts.day}/${vnParts.month}/${vnParts.year}`;
    }

    formatDateTimeVN(dateValue) {
        const vnParts = this.getTimePartsVN(dateValue);
        if (!vnParts) return "";
        return `${vnParts.day}/${vnParts.month}/${vnParts.year} ${vnParts.hour}:${vnParts.minute}`;
    }

    getCurrentSqlDateTimeVN() {
        const nowParts = this.getTimePartsVN(new Date());
        if (!nowParts) return "";
        return `${nowParts.year}-${nowParts.month}-${nowParts.day} ${nowParts.hour}:${nowParts.minute}:${nowParts.second}`;
    }

    formatTimeVN(dateValue) {
        const date = this.parseDateInput(dateValue);
        if (!date) return "";
        const diff = Date.now() - date.getTime();
        const minutes = Math.floor(diff / 60000);
        const hours = Math.floor(diff / 3600000);
        const days = Math.floor(diff / 86400000);

        if (minutes < 1) return "Vừa xong";
        if (minutes < 60) return `${minutes} phút trước`;
        if (hours < 24) return `${hours} giờ trước`;
        if (days < 7) return `${days} ngày trước`;
        return this.formatDateVN(dateValue);
    }

    cleanEmail(email, name = null) {
        if (name && String(name).trim() && !String(name).includes("@")) {
            return String(name).trim();
        }

        const safeEmail = String(email || "").trim();
        if (!safeEmail) return "";

        if (safeEmail.includes("bounces+") && safeEmail.includes("=")) {
            const match = safeEmail.match(/([^=]+)=([^@]+)@/);
            if (match) {
                return `${match[1]}@${match[2]}`;
            }
        }

        return safeEmail;
    }

    openModal(modalId) {
        const modal = document.getElementById(modalId);
        if (!modal) return;
        modal.classList.remove("hidden");
        this.syncBodyScroll();
    }

    closeModal(modalId) {
        const modal = document.getElementById(modalId);
        if (!modal) return;
        modal.classList.add("hidden");
        this.syncBodyScroll();
    }

    closeModalElement(modalElement) {
        if (!modalElement) return;
        modalElement.classList.add("hidden");
        this.syncBodyScroll();
    }

    syncBodyScroll() {
        this.activeModalCount = document.querySelectorAll(".modal:not(.hidden)").length;
        document.body.style.overflow = this.activeModalCount > 0 ? "hidden" : "";
    }

    bindModalSystem() {
        document.querySelectorAll("[data-modal-open]").forEach((button) => {
            button.addEventListener("click", () => {
                const targetId = button.getAttribute("data-modal-open");
                if (targetId) this.openModal(targetId);
            });
        });

        document.querySelectorAll("[data-modal-close]").forEach((button) => {
            button.addEventListener("click", () => {
                const targetId = button.getAttribute("data-modal-close");
                if (targetId) {
                    this.closeModal(targetId);
                } else {
                    this.closeModalElement(button.closest(".modal"));
                }
            });
        });

        document.querySelectorAll(".modal-backdrop").forEach((backdrop) => {
            backdrop.addEventListener("click", () => {
                this.closeModalElement(backdrop.closest(".modal"));
            });
        });

        document.addEventListener("keydown", (event) => {
            if (event.key !== "Escape") return;
            const visibleModals = Array.from(document.querySelectorAll(".modal:not(.hidden)"));
            const latestModal = visibleModals[visibleModals.length - 1];
            if (latestModal) this.closeModalElement(latestModal);
        });
    }

    bindLogout() {
        const logoutBtn = document.getElementById("logoutBtn");
        if (!logoutBtn) return;

        logoutBtn.addEventListener("click", async () => {
            this.clearAdminAccessKey();
            try {
                await this.fetchJson("/api/admin/auth.php", { method: "DELETE" });
            } catch (error) {
                // Bỏ qua lỗi đăng xuất phía API.
            }
            window.location.href = this.buildUrl("/adminkaishop/login");
        });
    }

    sanitizeCustomEmailName(rawValue) {
        return String(rawValue || "")
            .trim()
            .toLowerCase()
            .replace(/\s+/g, "")
            .replace(/[^a-z0-9._-]/g, "");
    }

    normalizeCreateEmailError(errorItem) {
        if (!errorItem) return "";
        if (typeof errorItem === "string") return errorItem;
        if (typeof errorItem === "object") {
            const message = String(errorItem.message || errorItem.error || "").trim();
            if (message) return message;
            const email = String(errorItem.email || "").trim();
            if (email) return `Không thể tạo ${email}`;
        }
        return String(errorItem).trim();
    }

    bindCreateEmailForm() {
        const form = document.getElementById("createEmailForm");
        if (!form) return;

        const customEmailGroup = document.getElementById("customEmailGroup");
        const quantityGroup = document.getElementById("quantityGroup");
        const customEmailInput = document.getElementById("customEmail");
        const quantityInput = document.getElementById("emailQuantity");

        form.querySelectorAll('input[name="name_type"]').forEach((radio) => {
            radio.addEventListener("change", () => {
                const showCustom = radio.checked && radio.value === "custom";
                if (customEmailGroup) customEmailGroup.classList.toggle("hidden", !showCustom);
                if (quantityGroup) quantityGroup.classList.toggle("hidden", showCustom);
            });
        });

        form.addEventListener("submit", async (event) => {
            event.preventDefault();

            const submitBtn = form.querySelector('button[type="submit"]');
            if (!submitBtn) return;

            const formData = new FormData(form);
            const nameType = String(formData.get("name_type") || "vn");
            const customEmail = this.sanitizeCustomEmailName(customEmailInput?.value || "");
            const selectedDomain = String(document.getElementById("domainSelect")?.value || "").trim();
            const quantityRaw = Number.parseInt(String(quantityInput?.value || "1"), 10);
            const quantity = Number.isFinite(quantityRaw) ? Math.min(50, Math.max(1, quantityRaw)) : 1;

            if (customEmailInput) {
                customEmailInput.value = customEmail;
            }
            if (quantityInput) {
                quantityInput.value = String(quantity);
            }

            if (!selectedDomain) {
                this.showToast("Vui lòng chọn domain hoạt động", "error");
                return;
            }

            if (nameType === "custom" && !customEmail) {
                this.showToast("Vui lòng nhập tên email tùy chỉnh", "error");
                return;
            }

            submitBtn.disabled = true;
            submitBtn.textContent = "Đang tạo...";

            const noteInput = document.getElementById("createEmailNote");
            const note = String(noteInput?.value || "").trim();

            const payload = { name_type: nameType, domain: selectedDomain, quantity: quantity };
            if (nameType === "custom") {
                payload.email = customEmail;
            }
            if (note) {
                payload.note = note;
            }

            try {
                const { ok, data } = await this.postJson("/api/admin/emails.php", payload);
                if (!ok) {
                    this.showToast(data?.error || "Không thể tạo email", "error");
                    return;
                }

                const created = Number(data?.created || 0);
                const errors = Array.isArray(data?.errors)
                    ? data.errors.map((item) => this.normalizeCreateEmailError(item)).filter(Boolean)
                    : [];

                if (created > 0) {
                    const successMessage = `Đã tạo ${created}/${quantity} email`;
                    if (errors.length > 0) {
                        this.showToast(`${successMessage} (${errors.length} lỗi). Lỗi đầu tiên: ${errors[0]}`, "warning");
                    } else {
                        this.showToast(successMessage, "success");
                    }
                } else if (errors.length > 0) {
                    this.showToast(errors[0], "error");
                } else {
                    this.showToast("Không thể tạo email", "error");
                }

                form.reset();
                if (quantityInput) quantityInput.value = "1";
                if (customEmailGroup) customEmailGroup.classList.add("hidden");
                if (quantityGroup) quantityGroup.classList.remove("hidden");
                this.closeModal("createModal");

                if (window.adminDashboard && typeof window.adminDashboard.reloadData === "function") {
                    window.adminDashboard.reloadData().catch(console.error);
                }
            } catch (error) {
                this.showToast("Lỗi kết nối máy chủ", "error");
            } finally {
                submitBtn.disabled = false;
                submitBtn.textContent = "Tạo email";
            }
        });
    }

    bindAddDomainForm() {
        const form = document.getElementById("addDomainForm");
        if (!form) return;

        form.addEventListener("submit", async (event) => {
            event.preventDefault();

            const submitBtn = form.querySelector('button[type="submit"]');
            if (!submitBtn) return;

            const domainName = String(document.getElementById("domainName")?.value || "").toLowerCase().trim();
            const statusInput = form.querySelector('input[name="domain_status"]:checked');
            const isActive = Number(statusInput?.value || "1");

            if (!domainName) {
                this.showToast("Vui lòng nhập domain", "error");
                return;
            }

            if (!/^[a-z0-9\.\-]+\.[a-z]{2,}$/.test(domainName)) {
                this.showToast("Domain không đúng định dạng", "error");
                return;
            }

            submitBtn.disabled = true;
            submitBtn.textContent = "Đang thêm...";

            try {
                const { ok, data } = await this.postJson("/api/admin/domains.php", {
                    domain: domainName,
                    is_active: isActive,
                });

                if (!ok) {
                    this.showToast(data?.error || "Không thể thêm domain", "error");
                    return;
                }

                this.showToast("Đã thêm domain thành công", "success");
                this.closeModal("addDomainModal");
                form.reset();
                setTimeout(() => window.location.reload(), 800);
            } catch (error) {
                this.showToast("Lỗi kết nối máy chủ", "error");
            } finally {
                submitBtn.disabled = false;
                submitBtn.textContent = "Thêm domain";
            }
        });
    }

    bindDomainDeleteButtons() {
        document.querySelectorAll("[data-domain-delete]").forEach((btn) => {
            btn.addEventListener("click", async () => {
                const id = Number(btn.getAttribute("data-domain-delete") || "0");
                const name = btn.getAttribute("data-domain-name") || "";
                await this.deleteDomain(id, name);
            });
        });
    }

    async deleteDomain(id, name = "") {
        if (!id) return;

        const confirmed = await this.confirmAction({
            title: "Xác nhận xóa domain",
            text: `Bạn có chắc muốn xóa domain "${name}"? Chỉ xóa được domain chưa có email.`,
            confirmButtonText: "Xóa domain",
            cancelButtonText: "Hủy",
            icon: "warning",
        });
        if (!confirmed) return;

        try {
            const { ok, data } = await this.fetchJson("/api/admin/domains.php", {
                method: "POST",
                headers: {
                    "Content-Type": "application/json",
                    "X-HTTP-Method-Override": "DELETE",
                },
                body: JSON.stringify({
                    _method: "DELETE",
                    id,
                }),
            });

            if (!ok) {
                this.showToast(data?.error || "Không thể xóa domain", "error");
                return;
            }

            this.showToast("Đã xóa domain", "success");
            setTimeout(() => window.location.reload(), 600);
        } catch (error) {
            this.showToast("Lỗi kết nối máy chủ", "error");
        }
    }

    bindMobileMenu() {
        const menuBtn = document.getElementById("mobileMenuBtn");
        const sidebar = document.getElementById("adminSidebar");
        const overlay = document.getElementById("sidebarOverlay");

        if (!menuBtn || !sidebar || !overlay) return;

        const closeSidebar = () => {
            sidebar.classList.remove("show");
            overlay.classList.remove("show");
        };

        menuBtn.addEventListener("click", () => {
            sidebar.classList.toggle("show");
            overlay.classList.toggle("show");
        });

        overlay.addEventListener("click", closeSidebar);
        sidebar.querySelectorAll(".nav-item").forEach((item) => {
            item.addEventListener("click", closeSidebar);
        });
    }
}

document.addEventListener("DOMContentLoaded", async () => {
    const baseUrl = document.documentElement.dataset.baseUrl || "";
    const core = new AdminCore(baseUrl);
    window.adminCore = core;
    await core.init();
    window.dispatchEvent(new CustomEvent("admin-core-ready"));
});
