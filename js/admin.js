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
        this.bindDomainManagement();
        this.bindTokenManagement();
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
            "email_id required": "Thiếu email_id",
            badrequest: "Yêu cầu không hợp lệ",
            "domain is required": "Tên domain là bắt buộc",
            "invalid domain format": "Định dạng domain không hợp lệ",
            "domain already exists": "Domain này đã tồn tại trong hệ thống",
            "domain not found": "Không tìm thấy domain",
            "cannot delete domain with associated emails": "Không thể xóa domain vì vẫn còn email liên kết"
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
                this.showToast("Vui lòng nhập tên domain", "error");
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
                    const errorMsg = data?.message || data?.error || "Không thể thêm domain";
                    this.showToast(errorMsg, "error");
                    return;
                }

                this.showToast(`Đã thêm domain "${domainName}" thành công`, "success");
                form.reset();

                // Cập nhật lại danh sách domain trong modal mượt mà
                await this.refreshDomainList();
            } catch (error) {
                this.showToast("Lỗi kết nối máy chủ", "error");
            } finally {
                submitBtn.disabled = false;
                submitBtn.textContent = "Thêm domain";
            }
        });
    }

    bindDomainManagement() {
        // Bật / tắt trạng thái hoạt động của domain qua Toggle Switch (hỗ trợ cả modal card và table row)
        document.addEventListener("change", async (event) => {
            const toggle = event.target.closest(".domain-toggle-switch");
            if (!toggle) return;

            const domainId = Number(toggle.dataset.domainId || "0");
            const domainName = String(toggle.dataset.domainName || "");
            const isChecked = toggle.checked;

            if (!domainId) return;

            toggle.disabled = true;

            try {
                const { ok, data } = await this.fetchJson("/api/admin/domains.php", {
                    method: "POST",
                    headers: {
                        "Content-Type": "application/json",
                        "X-HTTP-Method-Override": "PUT",
                    },
                    body: JSON.stringify({
                        _method: "PUT",
                        id: domainId,
                        is_active: isChecked ? 1 : 0,
                    }),
                });

                if (!ok) {
                    toggle.checked = !isChecked; // Khôi phục trạng thái cũ
                    const msg = data?.message || data?.error || "Không thể cập nhật trạng thái domain";
                    this.showToast(msg, "error");
                    return;
                }

                // Cập nhật card nếu có
                const card = document.getElementById(`domainCard_${domainId}`);
                if (card) {
                    card.classList.toggle("is-inactive", !isChecked);
                }

                // Cập nhật table row nếu có
                const row = document.getElementById(`domainRow_${domainId}`);
                if (row) {
                    row.classList.toggle("is-inactive-row", !isChecked);
                }

                // Cập nhật badge (hỗ trợ cả modal và table)
                document.querySelectorAll(`[id^="domainStatusBadge_"][id$="_${domainId}"], #domainStatusBadge_${domainId}`).forEach((badge) => {
                    badge.className = `domain-status-badge ${isChecked ? "active" : "inactive"}`;
                    badge.textContent = isChecked ? "Hoạt động" : "Tạm tắt";
                });

                const label = toggle.closest(".ios-switch");
                if (label) {
                    label.title = isChecked ? "Bấm để tắt domain này" : "Bấm để bật domain này";
                }

                this.showToast(`Đã ${isChecked ? "bật hoạt động" : "tạm tắt"} domain "${domainName}"`, "success");

                // Cập nhật các select domain trên trang
                this.updatePageDomainOptions(domainName, isChecked);
            } catch (error) {
                toggle.checked = !isChecked;
                this.showToast("Lỗi kết nối máy chủ", "error");
            } finally {
                toggle.disabled = false;
            }
        });

        // Xóa domain (hỗ trợ cả modal card và table row)
        document.addEventListener("click", async (event) => {
            const deleteBtn = event.target.closest(".domain-delete-btn, [data-domain-delete]");
            if (!deleteBtn) return;

            const id = Number(deleteBtn.dataset.domainDelete || "0");
            const name = deleteBtn.dataset.domainName || "";
            const emailCount = Number(deleteBtn.dataset.emailCount || "0");

            if (!id) return;

            if (emailCount > 0) {
                this.showToast(`Không thể xóa domain "${name}" vì đang có ${emailCount} email liên kết. Vui lòng xóa email trước!`, "warning");
                return;
            }

            const confirmed = await this.confirmAction({
                title: "Xác nhận xóa domain",
                text: `Bạn có chắc muốn xóa vĩnh viễn domain "${name}" khỏi hệ thống?`,
                confirmButtonText: "Xóa domain",
                cancelButtonText: "Hủy",
                icon: "warning",
            });
            if (!confirmed) return;

            deleteBtn.disabled = true;

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
                    const msg = data?.message || data?.error || "Không thể xóa domain";
                    this.showToast(msg, "error");
                    deleteBtn.disabled = false;
                    return;
                }

                this.showToast(`Đã xóa domain "${name}" thành công`, "success");

                // Xóa thẻ / hàng khỏi giao diện
                const targetElement = document.getElementById(`domainCard_${id}`)
                    || document.getElementById(`domainRow_${id}`)
                    || deleteBtn.closest("tr")
                    || deleteBtn.closest(".domain-card");

                if (targetElement) {
                    targetElement.style.transition = "all 0.25s ease";
                    targetElement.style.opacity = "0";
                    targetElement.style.transform = "scale(0.95)";
                    setTimeout(() => {
                        targetElement.remove();
                        this.updateDomainCountPill();
                    }, 250);
                }

                this.removeDomainFromSelects(name);
            } catch (error) {
                this.showToast("Lỗi kết nối máy chủ", "error");
                deleteBtn.disabled = false;
            }
        });
    }

    async refreshDomainList() {
        const cardContainer = document.getElementById("domainItemsContainer");
        const tableBody = document.getElementById("docsDomainTableBody");
        if (!cardContainer && !tableBody) return;

        try {
            const { ok, data } = await this.fetchJson("/api/admin/domains.php");
            if (!ok || !Array.isArray(data?.domains)) return;

            const domains = data.domains;
            if (cardContainer) {
                this.renderDomainCards(domains);
            }
            if (tableBody) {
                this.renderDomainTableRows(domains);
            }
            this.updateDomainCountPill(domains.length);

            const activeDomains = domains.filter((d) => Number(d.is_active) === 1).map((d) => d.domain);
            this.syncDomainSelects(activeDomains);
        } catch (e) {
            console.error("Refresh domain list error:", e);
        }
    }

    renderDomainCards(domains) {
        const container = document.getElementById("domainItemsContainer");
        if (!container) return;

        if (!domains || domains.length === 0) {
            container.innerHTML = '<div class="domain-empty-card" id="domainEmptyMsg">Chưa có domain nào trong hệ thống.</div>';
            return;
        }

        container.innerHTML = domains.map((dom) => {
            const id = Number(dom.id);
            const name = this.escapeHtml(dom.domain);
            const isActive = Number(dom.is_active) === 1;
            const emailCount = Number(dom.email_count || 0);

            return `
                <div class="domain-card ${isActive ? '' : 'is-inactive'}" id="domainCard_${id}" data-domain-id="${id}">
                    <div class="domain-card-main">
                        <div class="domain-card-icon">
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <circle cx="12" cy="12" r="10"></circle>
                                <line x1="2" y1="12" x2="22" y2="12"></line>
                                <path d="M12 2a15.3 15.3 0 0 1 4 10 15.3 15.3 0 0 1-4 10 15.3 15.3 0 0 1-4-10z"></path>
                            </svg>
                        </div>
                        <div class="domain-card-info">
                            <div class="domain-card-name">${name}</div>
                            <div class="domain-card-meta">
                                <span class="domain-email-count" id="domainEmailCount_${id}">${emailCount} email</span>
                                <span class="domain-meta-sep">•</span>
                                <span class="domain-status-badge ${isActive ? 'active' : 'inactive'}" id="domainStatusBadge_${id}">
                                    ${isActive ? 'Hoạt động' : 'Tạm tắt'}
                                </span>
                            </div>
                        </div>
                    </div>
                    <div class="domain-card-actions">
                        <label class="ios-switch" title="${isActive ? 'Bấm để tắt domain này' : 'Bấm để bật domain này'}">
                            <input type="checkbox" class="domain-toggle-switch" 
                                data-domain-id="${id}" 
                                data-domain-name="${name}" 
                                ${isActive ? 'checked' : ''}>
                            <span class="ios-switch-slider"></span>
                        </label>
                        <button type="button" class="domain-delete-btn" 
                            data-domain-delete="${id}" 
                            data-domain-name="${name}" 
                            data-email-count="${emailCount}"
                            title="${emailCount > 0 ? `Không thể xóa: đang có ${emailCount} email liên kết` : 'Xóa domain này'}">
                            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <polyline points="3 6 5 6 21 6"></polyline>
                                <path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path>
                            </svg>
                        </button>
                    </div>
                </div>
            `;
        }).join("");
    }

    renderDomainTableRows(domains) {
        const tbody = document.getElementById("docsDomainTableBody");
        if (!tbody) return;

        if (!domains || domains.length === 0) {
            tbody.innerHTML = '<tr id="docsDomainEmptyRow"><td colspan="5" style="text-align: center; color: var(--slate-400); padding: 32px 16px;">Chưa có domain nào trong hệ thống.</td></tr>';
            return;
        }

        tbody.innerHTML = domains.map((dom) => {
            const id = Number(dom.id);
            const name = this.escapeHtml(dom.domain);
            const isActive = Number(dom.is_active) === 1;
            const emailCount = Number(dom.email_count || 0);
            const createdAt = this.escapeHtml(dom.created_at || "");

            return `
                <tr id="domainRow_${id}" data-domain-id="${id}" class="${isActive ? '' : 'is-inactive-row'}">
                    <td><strong class="domain-name-tag"><code>@${name}</code></strong></td>
                    <td style="text-align: center;">
                        <div class="domain-status-cell">
                            <label class="ios-switch" title="${isActive ? 'Bấm để tắt domain này' : 'Bấm để bật domain này'}">
                                <input type="checkbox" class="domain-toggle-switch"
                                    data-domain-id="${id}"
                                    data-domain-name="${name}"
                                    ${isActive ? 'checked' : ''}>
                                <span class="ios-switch-slider"></span>
                            </label>
                            <span class="domain-status-badge ${isActive ? 'active' : 'inactive'}" id="domainStatusBadge_table_${id}">
                                ${isActive ? 'Hoạt động' : 'Tạm tắt'}
                            </span>
                        </div>
                    </td>
                    <td style="text-align: center;">
                        <span class="domain-email-pill ${emailCount > 0 ? 'has-emails' : 'zero-emails'}">
                            ${emailCount} email
                        </span>
                    </td>
                    <td>${createdAt}</td>
                    <td style="text-align: center;">
                        <button type="button" class="btn danger btn-sm domain-delete-btn"
                            data-domain-delete="${id}"
                            data-domain-name="${name}"
                            data-email-count="${emailCount}"
                            title="${emailCount > 0 ? `Không thể xóa: đang có ${emailCount} email liên kết` : 'Xóa domain này'}">
                            Xóa
                        </button>
                    </td>
                </tr>
            `;
        }).join("");
    }

    updateDomainCountPill(count) {
        const modalPill = document.getElementById("domainListCount");
        const docsPill = document.getElementById("docsDomainListCount");
        const total = count !== undefined ? count : (
            modalPill ? document.querySelectorAll("#domainItemsContainer .domain-card").length :
            document.querySelectorAll("#docsDomainTableBody tr[data-domain-id]").length
        );

        if (modalPill) {
            modalPill.textContent = `${total} domain`;
        }
        if (docsPill) {
            docsPill.textContent = `${total} domain`;
        }

        const container = document.getElementById("domainItemsContainer");
        if (total === 0 && container && !document.getElementById("domainEmptyMsg")) {
            container.innerHTML = '<div class="domain-empty-card" id="domainEmptyMsg">Chưa có domain nào trong hệ thống.</div>';
        }

        const tableBody = document.getElementById("docsDomainTableBody");
        if (total === 0 && tableBody && !document.getElementById("docsDomainEmptyRow")) {
            tableBody.innerHTML = '<tr id="docsDomainEmptyRow"><td colspan="5" style="text-align: center; color: var(--slate-400); padding: 32px 16px;">Chưa có domain nào trong hệ thống.</td></tr>';
        }
    }

    updatePageDomainOptions(domainName, isChecked) {
        const domainSelect = document.getElementById("domainSelect");
        if (!domainSelect) return;

        if (isChecked) {
            let exists = false;
            for (const opt of domainSelect.options) {
                if (opt.value === domainName) {
                    exists = true;
                    break;
                }
            }
            if (!exists) {
                const newOpt = document.createElement("option");
                newOpt.value = domainName;
                newOpt.textContent = `@${domainName}`;
                domainSelect.appendChild(newOpt);
            }
        } else {
            for (let i = 0; i < domainSelect.options.length; i++) {
                if (domainSelect.options[i].value === domainName) {
                    domainSelect.remove(i);
                    break;
                }
            }
        }
    }

    removeDomainFromSelects(domainName) {
        const domainSelect = document.getElementById("domainSelect");
        if (domainSelect) {
            for (let i = 0; i < domainSelect.options.length; i++) {
                if (domainSelect.options[i].value === domainName) {
                    domainSelect.remove(i);
                    break;
                }
            }
        }
        const domainFilter = document.getElementById("domainFilter");
        if (domainFilter) {
            for (let i = 0; i < domainFilter.options.length; i++) {
                if (domainFilter.options[i].value === domainName) {
                    domainFilter.remove(i);
                    break;
                }
            }
        }
    }

    syncDomainSelects(activeDomains) {
        const domainSelect = document.getElementById("domainSelect");
        if (domainSelect && Array.isArray(activeDomains)) {
            const currentVal = domainSelect.value;
            domainSelect.innerHTML = activeDomains
                .map((d) => `<option value="${this.escapeHtml(d)}">@${this.escapeHtml(d)}</option>`)
                .join("");
            if (currentVal && activeDomains.includes(currentVal)) {
                domainSelect.value = currentVal;
            }
        }
    }

    bindTokenManagement() {
        const tokensTable = document.getElementById("tokensTable");
        const addTokenForm = document.getElementById("addTokenForm");
        if (!tokensTable && !addTokenForm) return;

        // Parse initial tokens from embedded JSON
        let tokensList = [];
        const jsonEl = document.getElementById("initialTokensJson");
        if (jsonEl && jsonEl.textContent) {
            try {
                tokensList = JSON.parse(jsonEl.textContent) || [];
            } catch (err) {
                tokensList = [];
            }
        }

        let currentPage = 1;
        const itemsPerPage = 10;
        let currentFilter = "all";
        let currentSearch = "";

        const updateStatsCounters = () => {
            const total = tokensList.length;
            let active = 0;
            let inactive = 0;
            let totalRequests = 0;
            const now = Date.now();

            tokensList.forEach((t) => {
                const isAct = Number(t.status) === 1;
                const isExp = t.expires_at && new Date(t.expires_at).getTime() < now;
                if (isAct && !isExp) {
                    active++;
                } else {
                    inactive++;
                }
                totalRequests += Number(t.total_requests || 0);
            });

            const statTotal = document.getElementById("statTotalTokens");
            const statActive = document.getElementById("statActiveTokens");
            const statInactive = document.getElementById("statInactiveTokens");
            const statReqs = document.getElementById("statTotalRequests");
            const tabAll = document.getElementById("tabCountAllTokens");
            const tabAct = document.getElementById("tabCountActiveTokens");
            const tabInact = document.getElementById("tabCountInactiveTokens");

            if (statTotal) statTotal.textContent = total.toLocaleString();
            if (statActive) statActive.textContent = active.toLocaleString();
            if (statInactive) statInactive.textContent = inactive.toLocaleString();
            if (statReqs) statReqs.textContent = totalRequests.toLocaleString();
            if (tabAll) tabAll.textContent = total.toLocaleString();
            if (tabAct) tabAct.textContent = active.toLocaleString();
            if (tabInact) tabInact.textContent = inactive.toLocaleString();
        };

        const getFilteredTokens = () => {
            const query = currentSearch.toLowerCase().trim();
            const now = Date.now();

            return tokensList.filter((t) => {
                const isAct = Number(t.status) === 1;
                const isExp = t.expires_at && new Date(t.expires_at).getTime() < now;

                if (currentFilter === "active" && (!isAct || isExp)) return false;
                if (currentFilter === "inactive" && isAct && !isExp) return false;

                if (query) {
                    const name = String(t.name || "").toLowerCase();
                    const keyId = String(t.key_id || "").toLowerCase();
                    if (!name.includes(query) && !keyId.includes(query)) return false;
                }
                return true;
            });
        };

        const renderPagination = (totalPages) => {
            const pag = document.getElementById("tokensPagination");
            if (!pag) return;

            if (totalPages <= 1) {
                pag.innerHTML = "";
                return;
            }

            let html = "";
            if (currentPage > 1) {
                html += `
                    <button type="button" class="btn-page-nav" data-page="${currentPage - 1}" title="Trang trước" aria-label="Trang trước">
                        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                            <polyline points="15 18 9 12 15 6"></polyline>
                        </svg>
                    </button>
                `;
            } else {
                html += `
                    <button type="button" class="btn-page-nav" disabled title="Trang trước" aria-label="Trang trước">
                        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                            <polyline points="15 18 9 12 15 6"></polyline>
                        </svg>
                    </button>
                `;
            }

            for (let i = 1; i <= totalPages; i++) {
                if (i === 1 || i === totalPages || (i >= currentPage - 2 && i <= currentPage + 2)) {
                    html += `<button type="button" class="btn-page-num ${i === currentPage ? 'active' : ''}" data-page="${i}">${i}</button>`;
                } else if (i === currentPage - 3 || i === currentPage + 3) {
                    html += `<button type="button" class="btn-page-ellipsis" disabled>...</button>`;
                }
            }

            if (currentPage < totalPages) {
                html += `
                    <button type="button" class="btn-page-nav" data-page="${currentPage + 1}" title="Trang sau" aria-label="Trang sau">
                        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                            <polyline points="9 18 15 12 9 6"></polyline>
                        </svg>
                    </button>
                `;
            } else {
                html += `
                    <button type="button" class="btn-page-nav" disabled title="Trang sau" aria-label="Trang sau">
                        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                            <polyline points="9 18 15 12 9 6"></polyline>
                        </svg>
                    </button>
                `;
            }

            pag.innerHTML = html;
            pag.querySelectorAll("button[data-page]").forEach((btn) => {
                btn.addEventListener("click", () => {
                    currentPage = Number(btn.getAttribute("data-page") || "1");
                    renderTable();
                });
            });
        };

        const renderTable = () => {
            const tbody = document.getElementById("tokensTableBody");
            if (!tbody) return;

            const filtered = getFilteredTokens();
            const totalItems = filtered.length;
            const totalPages = Math.ceil(totalItems / itemsPerPage) || 1;

            if (currentPage > totalPages) currentPage = totalPages;
            if (currentPage < 1) currentPage = 1;

            const startIdx = (currentPage - 1) * itemsPerPage;
            const pageTokens = filtered.slice(startIdx, startIdx + itemsPerPage);

            if (pageTokens.length === 0) {
                tbody.innerHTML = `
                    <tr id="emptyTokensRow">
                        <td colspan="7" style="text-align: center; padding: 48px 16px; color: var(--slate-500);">
                            <div style="display: flex; flex-direction: column; align-items: center; gap: 12px;">
                                <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="var(--slate-400)" stroke-width="1.5">
                                    <path d="M21 2l-2 2m-1.5 1.5L14 9a5 5 0 1 0 3 3l3.5-3.5 2-2zM9 18a3 3 0 1 1 0-6 3 3 0 0 1 0 6z"></path>
                                </svg>
                                <p style="font-size: 1rem; font-weight: 500;">${tokensList.length === 0 ? "Chưa có API Token nào được tạo" : "Không tìm thấy Token nào phù hợp"}</p>
                                ${tokensList.length === 0 ? '<button type="button" class="btn primary btn-sm" data-modal-open="addTokenModal">Tạo Token đầu tiên</button>' : ''}
                            </div>
                        </td>
                    </tr>
                `;
            } else {
                const now = Date.now();
                tbody.innerHTML = pageTokens.map((token) => {
                    const id = Number(token.id);
                    const isExp = token.expires_at && new Date(token.expires_at).getTime() < now;
                    const isAct = Number(token.status) === 1 && !isExp;
                    const nameEsc = this.escapeHtml(token.name || "");
                    const keyIdEsc = this.escapeHtml(token.key_id || "");
                    const secretEsc = this.escapeHtml(token.secret_key || "");
                    const createdDate = this.formatDateTimeVN(token.created_at || new Date());
                    const expiresDate = token.expires_at ? this.formatDateVN(token.expires_at) : "";
                    const lastUsed = token.last_used_at ? this.formatDateTimeVN(token.last_used_at) : "";
                    const rateLimit = Number(token.rate_limit_per_min || 120);
                    const totalReqs = Number(token.total_requests || 0).toLocaleString();

                    return `
                        <tr id="tokenRow-${id}" data-token-id="${id}">
                            <td>
                                <span class="table-token-name">${nameEsc}</span>
                                <span class="table-token-sub">
                                    Tạo: ${createdDate}
                                    ${expiresDate ? `<br>Hết hạn: <span style="color: ${isExp ? 'var(--danger)' : 'inherit'}; font-weight: ${isExp ? '600' : 'normal'};">${expiresDate} ${isExp ? '(Đã hết hạn)' : ''}</span>` : ''}
                                </span>
                            </td>
                            <td>
                                <div class="token-key-display">
                                    <span class="key-text" title="${keyIdEsc}">${keyIdEsc}</span>
                                    <button type="button" class="token-icon-btn btn-copy-key" data-copy-value="${keyIdEsc}" title="Sao chép Key ID">
                                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                            <rect x="9" y="9" width="13" height="13" rx="2" ry="2"></rect>
                                            <path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"></path>
                                        </svg>
                                    </button>
                                </div>
                            </td>
                            <td>
                                <div class="token-key-display">
                                    <span class="key-text secret-masked" id="secretText-${id}" data-raw-secret="${secretEsc}">••••••••••••••••••••••••••••</span>
                                    <button type="button" class="token-icon-btn btn-toggle-secret" data-target="secretText-${id}" title="Hiện/Ẩn Secret Key">
                                        <svg class="icon-eye" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                            <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8z"></path>
                                            <circle cx="12" cy="12" r="3"></circle>
                                        </svg>
                                        <svg class="icon-eye-off hidden" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                            <path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24"></path>
                                            <line x1="1" y1="1" x2="23" y2="23"></line>
                                        </svg>
                                    </button>
                                    <button type="button" class="token-icon-btn btn-copy-key" data-copy-value="${secretEsc}" title="Sao chép Secret Key">
                                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                            <rect x="9" y="9" width="13" height="13" rx="2" ry="2"></rect>
                                            <path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"></path>
                                        </svg>
                                    </button>
                                </div>
                            </td>
                            <td style="text-align: center;">
                                <span class="token-rate-badge">
                                    <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                        <polygon points="13 2 3 14 12 14 11 22 21 10 12 10 13 2"></polygon>
                                    </svg>
                                    ${rateLimit}/m
                                </span>
                            </td>
                            <td style="text-align: center;">
                                <span style="font-weight: 600; color: var(--slate-900); font-family: var(--font-mono); font-size: 0.85rem;">
                                    ${totalReqs}
                                </span>
                                ${lastUsed ? `<div style="font-size: 0.72rem; color: var(--slate-500);">${lastUsed}</div>` : ''}
                            </td>
                            <td style="text-align: center;">
                                <label class="ios-switch" title="${isAct ? 'Đang hoạt động' : (isExp ? 'Đã hết hạn' : 'Đang tạm tắt')}">
                                    <input type="checkbox" class="token-status-toggle" data-token-id="${id}" ${isAct ? 'checked' : ''} ${isExp ? 'disabled' : ''}>
                                    <span class="ios-switch-slider"></span>
                                </label>
                            </td>
                            <td style="text-align: center;">
                                <button type="button" class="btn-action-icon btn-delete-token" data-token-id="${id}" data-token-name="${nameEsc}" title="Thu hồi và xóa Token này" style="color: var(--danger);">
                                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                        <polyline points="3 6 5 6 21 6"></polyline>
                                        <path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path>
                                    </svg>
                                </button>
                            </td>
                        </tr>
                    `;
                }).join("");
            }

            renderPagination(totalPages);
        };

        // Tab filter click
        document.querySelectorAll("[data-token-filter]").forEach((tab) => {
            tab.addEventListener("click", () => {
                const filter = tab.getAttribute("data-token-filter") || "all";
                if (currentFilter === filter) return;
                currentFilter = filter;
                document.querySelectorAll("[data-token-filter]").forEach((t) => {
                    const active = t === tab;
                    t.classList.toggle("active", active);
                    t.setAttribute("aria-selected", active ? "true" : "false");
                });
                currentPage = 1;
                renderTable();
            });
        });

        // Search input debounce
        let searchTimer = null;
        const searchInput = document.getElementById("tokenSearchInput");
        if (searchInput) {
            searchInput.addEventListener("input", () => {
                if (searchTimer) clearTimeout(searchTimer);
                searchTimer = setTimeout(() => {
                    currentSearch = searchInput.value;
                    currentPage = 1;
                    renderTable();
                }, 200);
            });
        }

        // Copy buttons (delegated)
        document.addEventListener("click", (e) => {
            const btnCopy = e.target.closest(".btn-copy-key");
            if (btnCopy) {
                const val = btnCopy.getAttribute("data-copy-value");
                if (val) {
                    this.copyToClipboard(val);
                }
                return;
            }

            // Reveal/Hide Secret Key (delegated)
            const btnToggle = e.target.closest(".btn-toggle-secret");
            if (btnToggle) {
                const targetId = btnToggle.getAttribute("data-target");
                const targetEl = document.getElementById(targetId);
                if (!targetEl) return;

                const iconEye = btnToggle.querySelector(".icon-eye");
                const iconEyeOff = btnToggle.querySelector(".icon-eye-off");
                const rawSecret = targetEl.getAttribute("data-raw-secret") || "";

                if (targetEl.classList.contains("secret-masked")) {
                    targetEl.classList.remove("secret-masked");
                    targetEl.textContent = rawSecret;
                    btnToggle.classList.add("active");
                    if (iconEye) iconEye.classList.add("hidden");
                    if (iconEyeOff) iconEyeOff.classList.remove("hidden");
                } else {
                    targetEl.classList.add("secret-masked");
                    targetEl.textContent = "••••••••••••••••••••••••••••";
                    btnToggle.classList.remove("active");
                    if (iconEye) iconEye.classList.remove("hidden");
                    if (iconEyeOff) iconEyeOff.classList.add("hidden");
                }
                return;
            }

            // Delete token button (delegated)
            const btnDelete = e.target.closest(".btn-delete-token");
            if (btnDelete) {
                const tokenId = btnDelete.getAttribute("data-token-id");
                const tokenName = btnDelete.getAttribute("data-token-name") || "Token";
                if (!tokenId) return;

                this.confirmAction({
                    title: "Thu hồi API Token?",
                    text: `Bạn có chắc muốn thu hồi và xóa token "${tokenName}"? Mọi bot hoặc client dùng token này sẽ bị từ chối ngay lập tức.`,
                    confirmButtonText: "Thu hồi & Xóa",
                    cancelButtonText: "Hủy",
                    icon: "warning",
                }).then(async (confirmed) => {
                    if (!confirmed) return;
                    try {
                        const res = await this.fetchJson("/api/admin/tokens.php", {
                            method: "POST",
                            headers: {
                                "Content-Type": "application/json",
                                "X-HTTP-Method-Override": "DELETE",
                            },
                            body: JSON.stringify({ _method: "DELETE", id: Number(tokenId) }),
                        });

                        if (res?.ok && res?.data?.success) {
                            this.showToast("Đã thu hồi token thành công", "success");
                            tokensList = tokensList.filter((t) => Number(t.id) !== Number(tokenId));
                            updateStatsCounters();
                            renderTable();
                        } else {
                            const errMsg = res?.data?.message || res?.data?.error || "Không thể xóa token";
                            this.showToast(errMsg, "error");
                        }
                    } catch (err) {
                        this.showToast("Lỗi mạng khi xóa token", "error");
                    }
                });
                return;
            }
        });

        // Status switch change (delegated)
        document.addEventListener("change", async (e) => {
            const toggle = e.target.closest(".token-status-toggle");
            if (!toggle) return;

            const tokenId = toggle.getAttribute("data-token-id");
            if (!tokenId) return;

            const newStatus = toggle.checked ? 1 : 0;
            toggle.disabled = true;

            try {
                const res = await this.fetchJson("/api/admin/tokens.php", {
                    method: "POST",
                    headers: {
                        "Content-Type": "application/json",
                        "X-HTTP-Method-Override": "PUT",
                    },
                    body: JSON.stringify({ _method: "PUT", id: Number(tokenId), status: newStatus }),
                });

                if (res?.ok && res?.data?.success) {
                    this.showToast(
                        newStatus === 1 ? "Đã kích hoạt API Token" : "Đã tạm dừng API Token",
                        "success"
                    );
                    const found = tokensList.find((t) => Number(t.id) === Number(tokenId));
                    if (found) {
                        found.status = newStatus;
                    }
                    updateStatsCounters();
                } else {
                    toggle.checked = !toggle.checked;
                    const errMsg = res?.data?.message || res?.data?.error || "Không thể cập nhật trạng thái";
                    this.showToast(errMsg, "error");
                }
            } catch (err) {
                toggle.checked = !toggle.checked;
                this.showToast("Lỗi kết nối khi đổi trạng thái", "error");
            } finally {
                toggle.disabled = false;
            }
        });

        // Create token form submit
        if (addTokenForm) {
            addTokenForm.addEventListener("submit", async (e) => {
                e.preventDefault();
                const btnSubmit = document.getElementById("btnSubmitAddToken");
                const nameInput = document.getElementById("tokenName");
                const rateLimitInput = document.getElementById("tokenRateLimit");
                const expiresDaysInput = document.getElementById("tokenExpiresDays");

                const name = (nameInput?.value || "").trim();
                const rateLimit = Number(rateLimitInput?.value || 120);
                const expiresDays = Number(expiresDaysInput?.value || 0);

                if (!name) {
                    this.showToast("Vui lòng nhập tên định danh cho Token", "error");
                    return;
                }

                if (btnSubmit) {
                    btnSubmit.disabled = true;
                    btnSubmit.textContent = "Đang tạo...";
                }

                try {
                    const res = await this.fetchJson("/api/admin/tokens.php", {
                        method: "POST",
                        body: JSON.stringify({
                            name,
                            rate_limit_per_min: rateLimit,
                            expires_days: expiresDays,
                        }),
                    });

                    if (res?.ok && res?.data?.success && res.data.token) {
                        const createdToken = res.data.token;
                        this.closeModal("addTokenModal");
                        addTokenForm.reset();

                        const keyIdInput = document.getElementById("createdKeyId");
                        const secretKeyInput = document.getElementById("createdSecretKey");
                        if (keyIdInput) keyIdInput.value = createdToken.key_id;
                        if (secretKeyInput) secretKeyInput.value = createdToken.secret_key;

                        const btnCopyKeyId = document.getElementById("btnCopyCreatedKeyId");
                        if (btnCopyKeyId) {
                            btnCopyKeyId.onclick = () => this.copyToClipboard(createdToken.key_id);
                        }

                        const btnCopySecret = document.getElementById("btnCopyCreatedSecretKey");
                        if (btnCopySecret) {
                            btnCopySecret.onclick = () => this.copyToClipboard(createdToken.secret_key);
                        }

                        this.openModal("tokenCreatedModal");

                        // Add to local tokens list and refresh
                        tokensList.unshift(createdToken);
                        updateStatsCounters();
                        renderTable();
                    } else {
                        const errMsg = res?.data?.message || res?.data?.error || "Không thể tạo token";
                        this.showToast(errMsg, "error");
                    }
                } catch (err) {
                    this.showToast("Lỗi mạng khi tạo token", "error");
                } finally {
                    if (btnSubmit) {
                        btnSubmit.disabled = false;
                        btnSubmit.textContent = "Tạo Token";
                    }
                }
            });
        }

        // Initial render
        renderTable();
        updateStatsCounters();
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
