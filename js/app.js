/**
 * KaiMail User Inbox (basic + optimized).
 * Flow: email -> Get Mail -> read OTP quickly.
 */

class KaiMailTime {
    constructor() {
        this.vnTimeZone = "Asia/Ho_Chi_Minh";
    }

    parse(value) {
        if (value instanceof Date) {
            return Number.isNaN(value.getTime()) ? null : value;
        }

        const raw = String(value || "").trim();
        if (raw === "") return null;

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
        return Number.isNaN(parsed.getTime()) ? null : parsed;
    }

    getVnParts(value) {
        const date = this.parse(value);
        if (!date) return null;

        const parts = new Intl.DateTimeFormat("en-GB", {
            timeZone: this.vnTimeZone,
            hour12: false,
            year: "numeric",
            month: "2-digit",
            day: "2-digit",
            hour: "2-digit",
            minute: "2-digit",
            second: "2-digit",
        }).formatToParts(date);

        const map = Object.fromEntries(parts.map((p) => [p.type, p.value]));
        return {
            year: map.year,
            month: map.month,
            day: map.day,
            hour: map.hour,
            minute: map.minute,
            second: map.second,
        };
    }

    formatRelative(value) {
        const date = this.parse(value);
        if (!date) return "";

        const diff = Date.now() - date.getTime();
        const minutes = Math.floor(diff / 60000);
        const hours = Math.floor(diff / 3600000);
        const days = Math.floor(diff / 86400000);

        if (minutes < 1) return "Vừa xong";
        if (minutes < 60) return `${minutes} phút trước`;
        if (hours < 24) return `${hours} giờ trước`;
        if (days < 7) return `${days} ngày trước`;

        const p = this.getVnParts(date);
        if (!p) return "";
        return `${p.day}/${p.month}/${p.year}`;
    }

    formatDateTime(value) {
        const p = this.getVnParts(value);
        if (!p) return "";
        return `${p.day}/${p.month}/${p.year} ${p.hour}:${p.minute}`;
    }

    nowSqlVN() {
        const p = this.getVnParts(new Date());
        if (!p) return "";
        return `${p.year}-${p.month}-${p.day} ${p.hour}:${p.minute}:${p.second}`;
    }
}

class KaiMailApi {
    constructor(baseUrl, webToken) {
        this.baseUrl = String(baseUrl || "").trim().replace(/\/+$/, "");
        this.webToken = String(webToken || "").trim();
        this.requestTimeoutMs = 12000;
    }

    buildUrl(path, query = {}) {
        const cleanPath = path.startsWith("/") ? path : `/${path}`;
        const url = `${this.baseUrl}${cleanPath}`;
        const params = new URLSearchParams();

        Object.entries(query).forEach(([k, v]) => {
            if (v === null || v === undefined || v === "") return;
            params.set(k, String(v));
        });

        const queryString = params.toString();
        return queryString === "" ? url : `${url}?${queryString}`;
    }

    buildHeaders() {
        const headers = { Accept: "application/json" };
        if (this.webToken !== "") {
            headers["X-WEB-UI-TOKEN"] = this.webToken;
        }
        return headers;
    }

    async getJson(path, query = {}) {
        const controller = new AbortController();
        const timeoutId = setTimeout(() => controller.abort(), this.requestTimeoutMs);
        try {
            const response = await fetch(this.buildUrl(path, query), {
                method: "GET",
                headers: this.buildHeaders(),
                credentials: "same-origin",
                cache: "no-store",
                signal: controller.signal,
            });

            let data = null;
            try {
                data = await response.json();
            } catch (error) {
                data = null;
            }

            return { ok: response.ok, status: response.status, data };
        } catch (error) {
            if (error?.name === "AbortError") {
                throw new Error("Kết nối chậm, vui lòng thử lại");
            }
            throw error;
        } finally {
            clearTimeout(timeoutId);
        }
    }

    fetchMessages(email, limit = 25) {
        return this.getJson("/api/messages.php", { email, limit });
    }

    fetchMessageById(id, email = "") {
        return this.getJson("/api/messages.php", { id, email });
    }

    async postJson(path, body = {}) {
        const controller = new AbortController();
        const timeoutId = setTimeout(() => controller.abort(), this.requestTimeoutMs);
        try {
            const headers = this.buildHeaders();
            headers["Content-Type"] = "application/json";

            const response = await fetch(this.buildUrl(path), {
                method: "POST",
                headers,
                credentials: "same-origin",
                cache: "no-store",
                body: JSON.stringify(body),
                signal: controller.signal,
            });

            let data = null;
            try {
                data = await response.json();
            } catch {
                data = null;
            }

            return { ok: response.ok, status: response.status, data };
        } catch (error) {
            if (error?.name === "AbortError") {
                throw new Error("Kết nối chậm, vui lòng thử lại");
            }
            throw error;
        } finally {
            clearTimeout(timeoutId);
        }
    }

    async deleteJson(path, query = {}, body = null) {
        const controller = new AbortController();
        const timeoutId = setTimeout(() => controller.abort(), this.requestTimeoutMs);
        try {
            const headers = this.buildHeaders();
            const options = {
                method: "DELETE",
                headers,
                credentials: "same-origin",
                cache: "no-store",
                signal: controller.signal,
            };

            if (body !== null) {
                headers["Content-Type"] = "application/json";
                options.body = JSON.stringify(body);
            }

            const response = await fetch(this.buildUrl(path, query), options);

            let data = null;
            try {
                data = await response.json();
            } catch {
                data = null;
            }

            return { ok: response.ok, status: response.status, data };
        } catch (error) {
            if (error?.name === "AbortError") {
                throw new Error("Kết nối chậm, vui lòng thử lại");
            }
            throw error;
        } finally {
            clearTimeout(timeoutId);
        }
    }

    createEmail(payload = {}) {
        return this.postJson("/api/emails.php", payload);
    }

    deleteEmail(email) {
        return this.deleteJson("/api/emails.php", { email });
    }

    fetchDomains() {
        return this.getJson("/api/domains.php");
    }
}

/**
 * KaiMail Router - Chuẩn hóa điều hướng URL (/ và /2fa, hỗ trợ subfolder và History API).
 */
class KaiMailRouter {
    constructor(baseUrl = "") {
        this.baseUrl = String(baseUrl || "").trim().replace(/\/+$/, "");
        this.basePath = this.extractBasePath(this.baseUrl);
        this.listeners = [];
        this.bindEvents();
    }

    extractBasePath(url) {
        if (!url) return "";
        try {
            const parsed = new URL(url, window.location.origin);
            const path = parsed.pathname.replace(/\/+$/, "");
            return path === "/" ? "" : path;
        } catch {
            return "";
        }
    }

    bindEvents() {
        window.addEventListener("popstate", () => {
            const route = this.getCurrentRoute();
            this.notify(route);
        });
    }

    getCurrentRoute() {
        const path = window.location.pathname.replace(/\/+$/, "");
        const twofaPath = (this.basePath + "/2fa").replace(/\/+$/, "");
        const search = new URLSearchParams(window.location.search);

        if (path === twofaPath || search.get("mode") === "twofa") {
            return { mode: "twofa", email: "" };
        }

        let email = String(search.get("email") || "").trim().toLowerCase();
        if (!email && path.includes("@")) {
            const base = this.basePath.replace(/\/+$/, "");
            const raw = (base !== "" && path.startsWith(base))
                ? path.slice(base.length).replace(/^\/+/, "")
                : path.replace(/^\/+/, "");
            const decoded = decodeURIComponent(raw);
            if (!decoded.includes("/") && decoded.includes("@")) {
                email = decoded.trim().toLowerCase();
            }
        }

        return { mode: "mail", email };
    }

    navigate(mode, email = "", replace = false) {
        let targetPath = this.basePath || "";

        if (mode === "twofa") {
            targetPath = (this.basePath || "") + "/2fa";
        } else {
            const cleanEmail = String(email || "").trim().toLowerCase();
            if (cleanEmail && cleanEmail.includes("@")) {
                const encodedEmail = encodeURIComponent(cleanEmail).replace(/%40/g, "@");
                targetPath = (this.basePath ? `${this.basePath}/${encodedEmail}` : `/${encodedEmail}`);
            } else {
                targetPath = (this.basePath ? `${this.basePath}/` : "/");
            }
        }

        const fullUrl = targetPath.replace(/\/{2,}/g, "/");
        const currentUrl = (window.location.pathname + window.location.search).replace(/\/{2,}/g, "/");

        if (currentUrl !== fullUrl) {
            if (replace) {
                window.history.replaceState({ mode, email }, "", fullUrl);
            } else {
                window.history.pushState({ mode, email }, "", fullUrl);
            }
        }
    }

    onRoute(callback) {
        if (typeof callback === "function") {
            this.listeners.push(callback);
        }
    }

    notify(route) {
        this.listeners.forEach((cb) => {
            try {
                cb(route);
            } catch (err) {
                console.error("Router listener error:", err);
            }
        });
    }
}

/**
 * KaiMail 2FA Controller - Chuẩn hóa quản lý toàn bộ tính năng và UI của Trình xác thực 2FA.
 */
class KaiMailTwofaController {
    constructor({ toast }) {
        this.toast = typeof toast === "function" ? toast : console.log;
        this.storageSecretKey = "kaimail_2fa_secret";
        this.storageHistoryKey = "kaimail_2fa_history";
        this.timerId = null;
        this.currentSecret = "";

        this.bindDom();
    }

    bindDom() {
        this.twofaInput = document.getElementById("twofaInput");
        this.twofaPasteBtn = document.getElementById("twofaPasteBtn");
        this.twofaClearBtn = document.getElementById("twofaClearBtn");
        this.getOtpBtn = document.getElementById("getOtpBtn");

        this.resultSection = document.getElementById("twofaResultSection");
        this.resetBtn = document.getElementById("twofaResetBtn");
        this.statusBadge = document.getElementById("twofaStatusBadge");

        this.otpWrapper = document.getElementById("otpCodeWrapper");
        this.otpPart1 = document.getElementById("otpPart1");
        this.otpPart2 = document.getElementById("otpPart2");
        this.copyOtpBtn = document.getElementById("copyOtpBtn");
        this.twofaProgress = document.getElementById("twofaProgress");
        this.twofaTimerText = document.getElementById("twofaTimerText");
        this.twofaTimerSec = document.getElementById("twofaTimerSec");

        this.recentWrapper = document.getElementById("twofaRecentWrapper");
        this.recentList = document.getElementById("twofaRecentList");
        this.clearHistoryBtn = document.getElementById("twofaClearHistoryBtn");

        this.hasValidOtp = false;
        this.currentCode = "";
    }

    init() {
        if (!this.twofaInput) return;

        this.twofaInput.addEventListener("input", () => {
            const val = this.twofaInput.value.trim();
            if (this.twofaClearBtn) {
                this.twofaClearBtn.style.display = val !== "" ? "inline-flex" : "none";
            }
        });

        this.twofaInput.addEventListener("keydown", (e) => {
            if (e.key === "Enter") {
                e.preventDefault();
                this.generateOtp();
            }
        });

        if (this.getOtpBtn) {
            this.getOtpBtn.addEventListener("click", () => this.generateOtp());
        }

        if (this.twofaClearBtn) {
            this.twofaClearBtn.addEventListener("click", () => this.clearSecret());
        }

        if (this.resetBtn) {
            this.resetBtn.addEventListener("click", () => {
                this.resetBtn.classList.add("spinning");
                setTimeout(() => {
                    if (this.resetBtn) this.resetBtn.classList.remove("spinning");
                }, 650);
                this.clearSecret();
            });
        }

        if (this.twofaPasteBtn) {
            this.twofaPasteBtn.addEventListener("click", () => this.pasteFromClipboard());
        }

        if (this.copyOtpBtn) {
            this.copyOtpBtn.addEventListener("click", () => this.copyOtp());
        }

        if (this.otpWrapper) {
            this.otpWrapper.addEventListener("click", () => {
                if (this.hasValidOtp) {
                    this.copyOtp();
                }
            });
            this.otpWrapper.addEventListener("keydown", (e) => {
                if ((e.key === "Enter" || e.key === " ") && this.hasValidOtp) {
                    e.preventDefault();
                    this.copyOtp();
                }
            });
        }

        if (this.clearHistoryBtn) {
            this.clearHistoryBtn.addEventListener("click", () => this.clearHistory());
        }

        this.renderRecentKeys();

        const cached = localStorage.getItem(this.storageSecretKey) || "";
        if (cached !== "") {
            this.twofaInput.value = cached;
            if (this.twofaClearBtn) this.twofaClearBtn.style.display = "inline-flex";
            this.generateOtp(true);
        } else {
            this.renderEmptyState();
        }
    }

    async pasteFromClipboard() {
        try {
            const text = await navigator.clipboard.readText();
            const clean = String(text || "").trim();
            if (!clean) {
                this.toast("Bộ nhớ tạm đang trống", "error");
                return;
            }
            this.twofaInput.value = clean;
            if (this.twofaClearBtn) this.twofaClearBtn.style.display = "inline-flex";
            this.generateOtp();
        } catch {
            this.toast("Vui lòng nhấn Ctrl + V để dán khóa bí mật", "error");
            this.twofaInput.focus();
        }
    }

    generateOtp(autoRun = false) {
        if (!this.twofaInput) return;
        let secret = this.twofaInput.value.trim().replace(/\s+/g, "");

        if (secret === "") {
            if (!autoRun) {
                this.toast("Vui lòng nhập khóa bí mật 2FA", "error");
                this.twofaInput.focus();
            }
            this.renderEmptyState();
            return;
        }

        if (secret.startsWith("otpauth://")) {
            try {
                const url = new URL(secret);
                const secretParam = url.searchParams.get("secret");
                if (secretParam) secret = secretParam;
            } catch {}
        }

        const cleanSecret = secret.toUpperCase();
        if (!/^[A-Z2-7]+=*$/.test(cleanSecret)) {
            this.toast("Khóa bí mật không đúng định dạng Base32 (chỉ gồm chữ A-Z và số 2-7)", "error");
            this.twofaInput.focus();
            return;
        }

        this.currentSecret = cleanSecret;
        localStorage.setItem(this.storageSecretKey, secret);
        this.saveToHistory(cleanSecret);

        if (this.timerId) {
            clearInterval(this.timerId);
            this.timerId = null;
        }

        try {
            if (!window.OTPAuth) {
                throw new Error("Không thể tải thư viện sinh mã OTP (OTPAuth). Vui lòng kiểm tra lại mạng.");
            }

            const totp = new window.OTPAuth.TOTP({
                algorithm: "SHA1",
                digits: 6,
                period: 30,
                secret: window.OTPAuth.Secret.fromBase32(cleanSecret)
            });

            const updateLoop = () => {
                try {
                    const code = totp.generate();
                    const secondsRemaining = 30 - (Math.floor(Date.now() / 1000) % 30);
                    this.updateOtpDisplay(code, secondsRemaining);
                } catch (err) {
                    console.error("Error generating OTP:", err);
                    this.renderEmptyState();
                    if (this.twofaTimerText) this.twofaTimerText.textContent = "Lỗi: Khóa bí mật không hợp lệ";
                }
            };

            updateLoop();
            this.timerId = setInterval(updateLoop, 1000);

            if (!autoRun) {
                this.toast("Đã sinh mã OTP thành công", "success");
            }
        } catch (err) {
            this.toast(err.message || "Lỗi tạo mã OTP", "error");
            this.renderEmptyState();
        }
    }

    renderEmptyState() {
        this.hasValidOtp = false;
        this.currentCode = "";

        if (this.otpWrapper) {
            this.otpWrapper.classList.add("is-empty");
            this.otpWrapper.classList.remove("just-copied");
            this.otpWrapper.title = "Chưa có mã xác thực";

            const digitCards = this.otpWrapper.querySelectorAll(".otp-digit-card");
            digitCards.forEach(card => {
                const textEl = card.querySelector(".digit-text");
                if (textEl) textEl.textContent = "—";
                card.classList.add("is-empty");
                card.classList.remove("is-active", "digit-pop");
            });
        }

        if (this.otpPart1) {
            this.otpPart1.textContent = "···";
            this.otpPart1.classList.add("is-empty");
        }
        if (this.otpPart2) {
            this.otpPart2.textContent = "···";
            this.otpPart2.classList.add("is-empty");
        }

        if (this.copyOtpBtn) {
            this.copyOtpBtn.disabled = true;
            this.copyOtpBtn.classList.add("is-disabled");
            this.copyOtpBtn.classList.remove("copied");
            const textEl = this.copyOtpBtn.querySelector(".copy-text");
            if (textEl) textEl.textContent = "Sao chép";
            const checkIcon = this.copyOtpBtn.querySelector(".check-icon");
            if (checkIcon) checkIcon.classList.add("hidden");
            const copyIcon = this.copyOtpBtn.querySelector(".copy-icon");
            if (copyIcon) copyIcon.classList.remove("hidden");
        }

        if (this.statusBadge) {
            this.statusBadge.classList.remove("active");
            const label = this.statusBadge.querySelector(".status-label");
            if (label) label.textContent = "Chờ nhập khóa";
        }

        if (this.twofaProgress) {
            this.twofaProgress.style.width = "0%";
            this.twofaProgress.style.background = "linear-gradient(90deg, rgb(21, 115, 71), rgb(16, 185, 129))";
        }

        if (this.twofaTimerText) {
            this.twofaTimerText.textContent = "Nhập khóa bí mật để sinh mã";
        }

        if (this.twofaTimerSec) {
            this.twofaTimerSec.classList.add("hidden");
            this.twofaTimerSec.textContent = "";
        }
    }

    showEmptyState(show) {
        if (show) {
            this.renderEmptyState();
        }
    }

    updateOtpDisplay(code, secondsRemaining) {
        if (!code || code.length !== 6) return;

        const isCodeChanged = this.currentCode !== code;
        this.hasValidOtp = true;
        this.currentCode = code;

        const part1 = code.slice(0, 3);
        const part2 = code.slice(3, 6);

        if (this.otpWrapper) {
            this.otpWrapper.classList.remove("is-empty");
            this.otpWrapper.title = `Nhấp để sao chép: ${part1} · ${part2}`;

            const digitCards = this.otpWrapper.querySelectorAll(".otp-digit-card");
            digitCards.forEach((card, idx) => {
                const textEl = card.querySelector(".digit-text");
                const newChar = code[idx] || "—";
                if (textEl && textEl.textContent !== newChar) {
                    textEl.textContent = newChar;
                    card.classList.remove("is-empty");
                    card.classList.add("is-active");
                    if (isCodeChanged) {
                        card.classList.add("digit-pop");
                        setTimeout(() => card.classList.remove("digit-pop"), 240);
                    }
                }
            });
        }

        if (this.otpPart1) {
            this.otpPart1.textContent = part1;
            this.otpPart1.classList.remove("is-empty");
        }
        if (this.otpPart2) {
            this.otpPart2.textContent = part2;
            this.otpPart2.classList.remove("is-empty");
        }

        if (this.copyOtpBtn) {
            this.copyOtpBtn.disabled = false;
            this.copyOtpBtn.classList.remove("is-disabled");
        }

        if (this.statusBadge) {
            this.statusBadge.classList.add("active");
            const label = this.statusBadge.querySelector(".status-label");
            if (label) label.textContent = "Đang hoạt động";
        }

        if (this.twofaTimerText) {
            this.twofaTimerText.textContent = `Tự động cập nhật sau ${secondsRemaining}s`;
        }

        if (this.twofaTimerSec) {
            this.twofaTimerSec.classList.remove("hidden");
            this.twofaTimerSec.textContent = `${secondsRemaining}s`;
        }

        if (this.twofaProgress) {
            const percentage = (secondsRemaining / 30) * 100;
            this.twofaProgress.style.width = `${percentage}%`;
            if (secondsRemaining <= 5) {
                this.twofaProgress.style.background = "linear-gradient(90deg, #ef4444, #f97316)";
            } else {
                this.twofaProgress.style.background = "linear-gradient(90deg, rgb(21, 115, 71), rgb(16, 185, 129))";
            }
        }
    }

    async copyOtp() {
        if (!this.hasValidOtp || !this.currentCode) return;
        const code = this.currentCode;
        if (!/^\d{6}$/.test(code)) return;

        try {
            await navigator.clipboard.writeText(code);
        } catch {
            const input = document.createElement("input");
            input.value = code;
            document.body.appendChild(input);
            input.select();
            document.execCommand("copy");
            document.body.removeChild(input);
        }

        if (this.copyOtpBtn) {
            this.copyOtpBtn.classList.add("copied");
            const copyIcon = this.copyOtpBtn.querySelector(".copy-icon");
            const checkIcon = this.copyOtpBtn.querySelector(".check-icon");
            if (copyIcon) copyIcon.classList.add("hidden");
            if (checkIcon) checkIcon.classList.remove("hidden");

            setTimeout(() => {
                if (this.copyOtpBtn) this.copyOtpBtn.classList.remove("copied");
                if (copyIcon) copyIcon.classList.remove("hidden");
                if (checkIcon) checkIcon.classList.add("hidden");
            }, 1400);
        }

        this.toast("Sao chép thành công", "success");
    }

    clearSecret() {
        if (this.timerId) {
            clearInterval(this.timerId);
            this.timerId = null;
        }
        this.currentSecret = "";
        this.currentCode = "";
        localStorage.removeItem(this.storageSecretKey);
        if (this.twofaInput) {
            this.twofaInput.value = "";
            this.twofaInput.focus();
        }
        if (this.twofaClearBtn) {
            this.twofaClearBtn.style.display = "none";
        }
        this.renderEmptyState();
        this.toast("Đã xóa khóa bí mật", "info");
    }

    saveToHistory(secret) {
        try {
            let history = JSON.parse(localStorage.getItem(this.storageHistoryKey) || "[]");
            if (!Array.isArray(history)) history = [];
            history = history.filter((item) => (typeof item === "string" ? item : item.key) !== secret);
            history.unshift({
                key: secret,
                time: Date.now()
            });
            if (history.length > 5) history = history.slice(0, 5);
            localStorage.setItem(this.storageHistoryKey, JSON.stringify(history));
            this.renderRecentKeys();
        } catch {}
    }

    renderRecentKeys() {
        if (!this.recentWrapper || !this.recentList) return;
        try {
            const history = JSON.parse(localStorage.getItem(this.storageHistoryKey) || "[]");
            if (!Array.isArray(history) || history.length === 0) {
                this.recentWrapper.classList.add("hidden");
                this.recentList.innerHTML = "";
                return;
            }

            this.recentWrapper.classList.remove("hidden");
            this.recentList.innerHTML = history.map((item) => {
                const raw = typeof item === "string" ? item : item.key;
                const masked = raw.length > 8 ? (raw.slice(0, 4) + "••••••" + raw.slice(-4)) : raw;
                return `
                    <button type="button" class="recent-key-chip" data-key="${raw}" title="Nhấp để sử dụng khóa này">
                        <span>🔑 ${masked}</span>
                    </button>
                `;
            }).join("");

            this.recentList.querySelectorAll(".recent-key-chip").forEach((chip) => {
                chip.addEventListener("click", () => {
                    const key = chip.getAttribute("data-key");
                    if (key && this.twofaInput) {
                        this.twofaInput.value = key;
                        if (this.twofaClearBtn) this.twofaClearBtn.style.display = "inline-flex";
                        this.generateOtp();
                    }
                });
            });
        } catch {
            this.recentWrapper.classList.add("hidden");
        }
    }

    clearHistory() {
        localStorage.removeItem(this.storageHistoryKey);
        this.renderRecentKeys();
        this.toast("Đã xóa lịch sử khóa gần đây", "success");
    }

    stop() {
        if (this.timerId) {
            clearInterval(this.timerId);
            this.timerId = null;
        }
    }

    start() {
        if (this.currentSecret) {
            this.generateOtp(true);
        }
    }
}

class KaiMailUserPage {
    constructor() {
        this.config = window.KAIMAIL_CONFIG || {};
        this.baseUrl = String(this.config.baseUrl || "").trim();
        this.webToken = String(this.config.userToken || "").trim();
        this.basePath = this.extractBasePath(this.baseUrl);
        this.storageKey = "kaimail_email";

        this.time = new KaiMailTime();
        this.api = new KaiMailApi(this.baseUrl, this.webToken);
        this.router = new KaiMailRouter(this.baseUrl);
        this.twofaController = new KaiMailTwofaController({
            toast: (msg, type) => this.toast(msg, type)
        });

        this.state = {
            currentEmail: "",
            currentEmailId: 0,
            loading: false,
            unread: 0,
            renderedIds: new Set(),
            lastCheck: "",
            cooldowns: {}, // { key: nextAllowedTimestamp }
            clickStats: {}, // { key: { count: 0, last: 0 } }
            currentMode: "mail" // "mail" or "twofa"
        };

        this.poller = null;

        this.bindDom();
        // Fixed UI: Always show inbox section from start
        if (this.inboxSection) {
            this.inboxSection.classList.remove("hidden");
        }
    }

    bindDom() {
        this.emailInput = document.getElementById("emailInput");
        this.emailClearBtn = document.getElementById("emailClearBtn");
        this.getMailBtn = document.getElementById("getMailBtn");
        this.copyBtn = document.getElementById("copyBtn");
        this.randomMailBtn = document.getElementById("randomMailBtn");
        this.customMailBtn = document.getElementById("customMailBtn");
        this.qrMailBtn = document.getElementById("qrMailBtn");
        this.deleteMailBtn = document.getElementById("deleteMailBtn");
        this.emailSpinner = document.getElementById("emailSpinner");
        this.refreshBtn = document.getElementById("refreshBtn");
        this.inboxSection = document.getElementById("inboxSection");
        this.messagesList = document.getElementById("messagesList");
        this.emptyState = document.getElementById("emptyState");
        this.loadingState = document.getElementById("loadingState");
        this.unreadBadge = document.getElementById("unreadBadge");

        this.modal = null;
        this.modalSubject = null;
        this.modalFrom = null;
        this.modalBody = null;
        this.closeModalBtn = null;

        this.defaultGetBtnHtml = this.getMailBtn ? this.getMailBtn.innerHTML : "";
        
        this.mailModeContent = document.getElementById("mailModeContent");
        this.twofaModeContent = document.getElementById("twofaModeContent");
        this.modeTabs = document.querySelectorAll(".mode-tab");
    }

    ready() {
        return Boolean(
            this.emailInput &&
            this.copyBtn &&
            this.refreshBtn &&
            this.inboxSection &&
            this.messagesList &&
            this.emptyState &&
            this.unreadBadge
        );
    }

    init() {
        if (!this.ready()) return;

        if (this.getMailBtn) {
            this.getMailBtn.addEventListener("click", () => this.openInboxFromInput());
        }

        if (this.randomMailBtn) {
            this.randomMailBtn.addEventListener("click", () => this.onRandomEmail());
        }

        if (this.customMailBtn) {
            this.customMailBtn.addEventListener("click", () => this.onCustomEmail());
        }

        if (this.qrMailBtn) {
            this.qrMailBtn.addEventListener("click", () => this.onQrCode());
        }

        if (this.deleteMailBtn) {
            this.deleteMailBtn.addEventListener("click", () => this.onDeleteEmail());
        }

        this.copyBtn.addEventListener("click", () => this.copyEmail());

        let emailInputDebounce = null;
        this.emailInput.addEventListener("keydown", (event) => {
            if (event.key !== "Enter") return;
            event.preventDefault();
            if (emailInputDebounce) clearTimeout(emailInputDebounce);
            this.openInboxFromInput();
        });

        this.emailInput.addEventListener("input", () => {
            const rawVal = this.emailInput.value.trim();
            this.toggleEmailClearBtn();
            this.updateRefreshState();

            if (rawVal === "") {
                if (emailInputDebounce) clearTimeout(emailInputDebounce);
                this.resetToEmptyMailbox();
                return;
            }

            if (this.isValidEmail(rawVal)) {
                if (emailInputDebounce) clearTimeout(emailInputDebounce);
                emailInputDebounce = setTimeout(() => {
                    const norm = this.normalizeEmail(this.emailInput.value);
                    if (this.isValidEmail(norm)) {
                        this.updateUrl(norm);
                    }
                }, 350);
            }
        });

        if (this.emailClearBtn) {
            this.emailClearBtn.addEventListener("click", () => {
                if (emailInputDebounce) clearTimeout(emailInputDebounce);
                this.resetToEmptyMailbox();
                if (this.emailInput) {
                    this.emailInput.focus();
                }
            });
        }

        this.refreshBtn.addEventListener("click", () => {
            if (!this.state.currentEmail || this.refreshBtn.disabled || this.state.loading) return;

            const now = Date.now();
            if (now - (this.lastRefreshClick || 0) < 500) return;
            this.lastRefreshClick = now;

            this.loadMessages({ manual: true });
        });

        this.messagesList.addEventListener("click", (event) => {
            const item = event.target.closest(".message-item");
            if (!item) return;
            const header = event.target.closest(".message-header");
            if (header) {
                const id = Number(item.getAttribute("data-id") || "0");
                if (id > 0) this.toggleMessage(item, id);
            }
        });

        window.addEventListener("beforeunload", () => this.stopPolling());

        this.originalTitle = document.title || "KaiMail";
        window.addEventListener("focus", () => {
            if (this.originalTitle) document.title = this.originalTitle;
        });

        // 1. Initialize 2FA Controller
        this.twofaController.init();

        // 2. Determine Initial Mode FIRST (URL > Router > Config > Default 'mail')
        const path = window.location.pathname.replace(/\/+$/, "");
        const search = new URLSearchParams(window.location.search);
        const isTwoFaRoute = path.endsWith("/2fa") || search.get("mode") === "twofa" || this.config.isTwoFaRoute || this.config.initialMode === "twofa";
        const initialMode = isTwoFaRoute ? "twofa" : "mail";
        this.switchMode(initialMode, false);

        // 3. Mode Switching & Tab Click Events
        if (this.modeTabs && this.modeTabs.length > 0) {
            this.modeTabs.forEach(tab => {
                tab.addEventListener("click", () => {
                    const mode = tab.getAttribute("data-mode");
                    this.switchMode(mode, true);
                });
            });
        }

        // 4. Listen to browser Back/Forward (History API)
        this.router.onRoute((route) => {
            if (route.mode !== this.state.currentMode) {
                this.switchMode(route.mode, false);
            }
            if (route.mode === "mail") {
                const targetEmail = this.normalizeEmail(route.email);
                if (targetEmail !== this.state.currentEmail) {
                    if (targetEmail !== "") {
                        this.emailInput.value = targetEmail;
                        this.openInbox(targetEmail, true);
                    } else {
                        this.resetToEmptyMailbox();
                    }
                }
            }
        });

        // 5. If initial mode is mail, resolve and open mailbox
        if (initialMode === "mail") {
            const initialEmail = this.resolveInitialEmail();
            if (initialEmail !== "") {
                this.emailInput.value = initialEmail;
                this.openInbox(initialEmail, true);
            } else {
                this.resetToEmptyMailbox();
            }
        }

        this.updateRefreshState();
        this.toggleEmailClearBtn();
    }

    async openInboxFromInput() {
        if (this.state.loading) return;

        const now = Date.now();
        if (now - (this.lastSubmitClick || 0) < 400) return;
        this.lastSubmitClick = now;

        const email = this.normalizeEmail(this.emailInput.value);
        await this.openInbox(email, false);
    }

    async openInbox(email, autoOpen = false) {
        if (!this.isValidEmail(email)) {
            this.toast("Vui lòng nhập email đầy đủ (ví dụ: user@domain.com)", "error");
            this.emailInput.focus();
            return false;
        }

        this.state.currentEmail = email;
        if (this.emailInput && this.emailInput.value !== email) {
            this.emailInput.value = email;
        }
        this.toggleEmailClearBtn();
        this.updateRefreshState();
        this.showInbox(true);
        this.showCopyButton(true);
        this.setGetMailLoading(true);

        const loaded = await this.loadMessages({ manual: !autoOpen, showLoading: true, limit: 25 });
        this.setGetMailLoading(false);

        if (!loaded) return false;

        localStorage.setItem(this.storageKey, email);
        this.updateUrl(email);
        this.startPolling();
        return true;
    }

    async loadMessages({ manual = false, showLoading = false, limit = 25 } = {}) {
        const email = this.state.currentEmail;
        if (email === "") {
            if (manual) this.emailInput.focus();
            return false;
        }

        if (this.state.loading) return false;
        this.state.loading = true;

        this.setRefreshLoading(true);
        if (showLoading) this.showLoading(true);

        try {
            const { ok, status, data } = await this.api.fetchMessages(email, limit);
            if (!ok) {
                if (status === 404) {
                    // Stale / deleted email in localStorage or URL -> Auto-heal
                    localStorage.removeItem(this.storageKey);
                    this.resetToEmptyMailbox();
                    return false;
                }
                throw new Error(data?.error || `Không thể tải hộp thư (HTTP ${status || 0})`);
            }

            this.state.currentEmailId = Number(data?.email_id || 0);
            this.state.lastCheck = String(data?.server_time || "").trim();
            this.state.unread = Number(data?.unread || 0);
            this.state.renderedIds = new Set();

            const messages = Array.isArray(data?.messages) ? data.messages : [];
            this.renderMessages(messages, true);
            this.setUnread(this.state.unread);

            if (manual) {
                this.toast("Đã làm mới hộp thư", "success");
            }
            return true;
        } catch (error) {
            this.toast(error?.message || "Không thể tải hộp thư", "error");
            this.showEmpty(true);
            return false;
        }
        finally {
            this.state.loading = false;
            this.setRefreshLoading(false);
            this.showLoading(false);
            // Ensure something is visible if no messages
            if (this.state.renderedIds.size === 0) {
                this.showEmpty(true);
            }
        }
    }

    renderMessages(messages, reset = false) {
        const list = Array.isArray(messages) ? messages : [];

        if (reset) {
            this.messagesList.innerHTML = "";
            this.state.renderedIds = new Set();
        }

        if (reset && list.length === 0) {
            this.showEmpty(true);
            this.showMessageList(false);
            return;
        }

        if (list.length > 0) {
            const rows = [];
            list.forEach((msg) => {
                const id = Number(msg?.id || 0);
                if (id < 1 || this.state.renderedIds.has(id)) return;
                this.state.renderedIds.add(id);
                rows.push(this.buildMessageRow(msg));
            });

            if (rows.length > 0) {
                if (reset) {
                    this.messagesList.innerHTML = rows.join("");
                } else {
                    this.messagesList.insertAdjacentHTML("afterbegin", rows.join(""));
                }
            }
        }

        this.showEmpty(this.state.renderedIds.size === 0);
        this.showMessageList(this.state.renderedIds.size > 0);
    }

    buildMessageRow(msg) {
        const id = Number(msg?.id || 0);
        const unread = Number(msg?.is_read || 0) === 0;
        const sender = this.escapeHtml(this.getDisplayName(msg));
        const subject = this.escapeHtml(String(msg?.subject || "(Không có tiêu đề)"));
        const timeText = this.escapeHtml(this.time.formatRelative(msg?.received_at));

        return `
            <div class="message-item ${unread ? "unread" : ""}" data-id="${id}">
                <div class="message-header">
                    <div class="message-dot"></div>
                    <div class="message-content">
                        <div class="message-sender">${sender}</div>
                        <div class="message-subject">${subject}</div>
                    </div>
                    <div class="message-time-action">
                        <span class="message-time">${timeText}</span>
                        <svg class="message-chevron" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <polyline points="6 9 12 15 18 9"></polyline>
                        </svg>
                    </div>
                </div>
                <div class="message-collapse">
                    <div class="message-collapse-inner" id="detail-${id}">
                        <div class="message-loading-pane">
                            <div class="loading-pulse-line" style="width: 45%;"></div>
                            <div class="loading-pulse-line" style="width: 85%;"></div>
                            <div class="loading-pulse-line" style="width: 65%;"></div>
                        </div>
                    </div>
                </div>
            </div>
        `;
    }

    async toggleMessage(itemElement, id) {
        const isClosing = itemElement.classList.contains("active");
        const userMain = document.querySelector(".user-main");
        if (isClosing) {
            itemElement.classList.remove("active");
            if (userMain) userMain.classList.remove("has-active-mail");
            return;
        }

        // Close other opened messages
        const activeItems = this.messagesList.querySelectorAll(".message-item.active");
        activeItems.forEach(el => el.classList.remove("active"));

        itemElement.classList.add("active");
        if (userMain) userMain.classList.add("has-active-mail");

        setTimeout(() => {
            itemElement.scrollIntoView({ behavior: "smooth", block: "nearest" });
        }, 120);

        if (itemElement.classList.contains("unread")) {
            itemElement.classList.remove("unread");
            if (this.state.unread > 0) {
                this.state.unread -= 1;
                this.setUnread(this.state.unread);
            }
        }

        const detailContainer = document.getElementById(`detail-${id}`);
        if (!detailContainer || detailContainer.dataset.loaded === "true") return;

        try {
            const { ok, data } = await this.api.fetchMessageById(id, this.state.currentEmail);
            if (!ok || !data) {
                throw new Error(data?.error || "Không thể mở email");
            }

            const sender = this.getDisplayName(data);
            const receivedAt = this.time.formatDateTime(data?.received_at);
            const fromEmail = String(data?.from_email || "").trim();
            const subject = String(data?.subject || "(Không có tiêu đề)").trim();
            const bodyText = String(data?.body_text || "");

            const htmlBody = this.extractHtmlBody(data);

            detailContainer.innerHTML = `
                <div class="message-detail-pane">
                    <div class="message-detail-card">
                        <div class="detail-top-bar">
                            <div class="detail-meta-text">
                                <span class="detail-sender">${this.escapeHtml(sender)}</span>
                                ${fromEmail ? `<span class="detail-email">&lt;${this.escapeHtml(fromEmail)}&gt;</span>` : ""}
                                <span class="detail-sep">•</span>
                                <span class="detail-time">${this.escapeHtml(receivedAt)}</span>
                            </div>
                        </div>
                        <div class="detail-body-content" id="body-content-${id}"></div>
                    </div>
                </div>
            `;

            const bodyContent = document.getElementById(`body-content-${id}`);
            if (htmlBody !== "") {
                this.renderHtmlBody(bodyContent, htmlBody);
            } else {
                bodyContent.innerHTML = `<pre class="plain-email-text">${this.escapeHtml(bodyText || "(Không có nội dung)")}</pre>`;
            }

            detailContainer.dataset.loaded = "true";

        } catch (error) {
            if (detailContainer) {
                detailContainer.innerHTML = `
                    <div class="message-detail-pane">
                        <div class="message-detail-error">${this.escapeHtml(error?.message || "Không thể tải nội dung email")}</div>
                    </div>
                `;
            }
        }
    }

    extractOTP(subject, body) {
        // Look for 4 to 8 digit numbers in subject or body
        const otpRegex = /\b\d{4,8}\b/g;

        if (subject) {
            const subjectMatches = [...subject.matchAll(otpRegex)];
            if (subjectMatches.length > 0) {
                // Return the largest string if multiple or sequence, but usually it's the last token (e.g. "code is 123456")
                return subjectMatches[subjectMatches.length - 1][0];
            }
        }

        if (body) {
            const bodyMatches = [...body.matchAll(otpRegex)];
            if (bodyMatches.length > 0) {
                const uniqueOtps = [...new Set(bodyMatches.map(m => m[0]))];

                // If there's only one distinct number found, we are highly confident
                if (uniqueOtps.length === 1) {
                    return uniqueOtps[0];
                }

                // Search for strong visual cues in the text body if multiple numbers exist
                const lines = body.split('\n');
                for (const line of lines) {
                    if (line.toLowerCase().includes('code') || line.toLowerCase().includes('otp') || line.toLowerCase().includes('mã')) {
                        const lineMatches = line.match(otpRegex);
                        if (lineMatches && lineMatches.length === 1) {
                            return lineMatches[0];
                        }
                    }
                }

                // Fallback to first found number not heavily surrounded by random characters
                for (const m of bodyMatches) {
                    if (!body.includes(m[0] + '-') && !body.includes('-' + m[0]) && !body.includes(m[0] + '/')) { // Avoid dates
                        return m[0];
                    }
                }
            }
        }

        return null;
    }

    async copyOtp(code) {
        if (!code) return;
        const msg = `Đã copy thành công mã: ${code}`;
        try {
            await navigator.clipboard.writeText(code);
            this.toast(msg, "success");
        } catch (err) {
            const input = document.createElement("input");
            input.value = code;
            document.body.appendChild(input);
            input.select();
            document.execCommand("copy");
            document.body.removeChild(input);
            this.toast(msg, "success");
        }
    }

    extractHtmlBody(data) {
        const bodyHtml = String(data?.body_html || "").trim();
        if (bodyHtml !== "") return bodyHtml;

        const bodyText = String(data?.body_text || "").trim();
        return this.looksLikeHtml(bodyText) ? bodyText : "";
    }

    looksLikeHtml(value) {
        const text = String(value || "").trim();
        if (text === "") return false;
        if (!/<\/?[a-z][\s\S]*>/i.test(text)) return false;
        return /<!doctype\s+html|<html[\s>]|<head[\s>]|<body[\s>]|<table[\s>]|<div[\s>]|<p[\s>]|<a[\s>]|<img[\s>]|<style[\s>]/i.test(text);
    }

    renderHtmlBody(container, html) {
        container.textContent = "";

        const frame = document.createElement("iframe");
        frame.className = "email-body-frame";
        frame.title = "Email HTML content";
        frame.setAttribute("sandbox", "allow-popups allow-popups-to-escape-sandbox allow-same-origin");
        frame.setAttribute("referrerpolicy", "no-referrer");
        frame.srcdoc = this.buildEmailSrcdoc(html);

        const adjustHeight = () => {
            try {
                const doc = frame.contentDocument || frame.contentWindow?.document;
                if (doc && doc.body) {
                    const h = this.measureContentHeight(doc);
                    if (h > 20) {
                        frame.style.height = `${h}px`;
                    }
                }
            } catch (e) {}
        };

        frame.addEventListener("load", () => {
            adjustHeight();
            try {
                const doc = frame.contentDocument || frame.contentWindow?.document;
                if (doc) {
                    if (window.ResizeObserver && doc.body) {
                        const ro = new ResizeObserver(() => adjustHeight());
                        ro.observe(doc.body);
                    }
                    const images = doc.querySelectorAll("img");
                    images.forEach((img) => {
                        if (!img.complete) {
                            img.addEventListener("load", () => adjustHeight());
                            img.addEventListener("error", () => adjustHeight());
                        }
                    });
                }
            } catch (e) {}

            setTimeout(adjustHeight, 100);
            setTimeout(adjustHeight, 350);
            setTimeout(adjustHeight, 800);
        });

        requestAnimationFrame(() => adjustHeight());
        container.appendChild(frame);
    }

    measureContentHeight(doc) {
        if (!doc || !doc.body) return 0;
        const body = doc.body;

        try {
            doc.documentElement.style.height = "auto";
            doc.body.style.height = "auto";
        } catch {}

        let maxBottom = 0;
        const children = body.children;
        for (let i = 0; i < children.length; i++) {
            const el = children[i];
            if (el.tagName === "STYLE" || el.tagName === "SCRIPT") continue;
            const rect = el.getBoundingClientRect();
            const win = doc.defaultView || window;
            const style = win.getComputedStyle ? win.getComputedStyle(el) : null;
            const mb = style ? parseFloat(style.marginBottom) || 0 : 0;
            const bottom = rect.bottom + mb;
            if (bottom > maxBottom) maxBottom = bottom;
        }

        const win = doc.defaultView || window;
        const bodyStyle = win.getComputedStyle ? win.getComputedStyle(body) : null;
        const pb = bodyStyle ? parseFloat(bodyStyle.paddingBottom) || 0 : 0;

        if (maxBottom > 0) {
            return Math.ceil(maxBottom + pb);
        }

        const bodyRect = body.getBoundingClientRect();
        return Math.ceil(Math.max(bodyRect.height, body.offsetHeight, body.scrollHeight));
    }

    buildEmailSrcdoc(html) {
        const source = String(html || "");
        if (source === "") return "";
        const baseStyle = "<style>html,body{margin:0;padding:16px 20px;height:auto!important;min-height:0!important;overflow:hidden!important;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,sans-serif;color:#1e293b;line-height:1.6;overflow-wrap:break-word;background:#ffffff;box-sizing:border-box;}*,*:before,*:after{box-sizing:inherit;}img{max-width:100%!important;height:auto!important;}table{max-width:100%!important;}</style>";
        if (/<\s*head[\s>]/i.test(source)) {
            return source.replace(/<\s*head[\s>]/i, `$&${baseStyle}`);
        }
        if (/<\s*html[\s>]/i.test(source) || /<!doctype\s+html/i.test(source)) {
            return source.replace(/<\s*body[\s>]/i, `$&${baseStyle}`);
        }
        return `<!doctype html><html><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><base target="_blank">${baseStyle}</head><body>${source}</body></html>`;
    }

    async copyEmail() {
        if (this.copyBtn?.disabled) return;
        const email = this.state.currentEmail;
        if (!email) return;

        try {
            await navigator.clipboard.writeText(email);
        } catch (error) {
            const input = document.createElement("input");
            input.value = email;
            document.body.appendChild(input);
            input.select();
            document.execCommand("copy");
            document.body.removeChild(input);
        }

        // Icon swap feedback identical to copyOtpBtn (keep text static as "Sao chép")
        if (this.copyBtn) {
            this.copyBtn.classList.add("copied");
            const copyIcon = this.copyBtn.querySelector(".copy-icon, .icon-copy");
            const checkIcon = this.copyBtn.querySelector(".check-icon, .icon-check");
            if (copyIcon) copyIcon.classList.add("hidden");
            if (checkIcon) checkIcon.classList.remove("hidden");

            setTimeout(() => {
                if (this.copyBtn) this.copyBtn.classList.remove("copied");
                if (copyIcon) copyIcon.classList.remove("hidden");
                if (checkIcon) checkIcon.classList.add("hidden");
            }, 1400);
        }

        this.toast("Sao chép thành công", "success");
    }

    setAddressLoading(loading) {
        if (this.emailSpinner) {
            this.emailSpinner.classList.toggle("hidden", !loading);
        }
        if (this.emailInput) {
            if (loading) {
                this.emailInput.placeholder = "Đang tạo email tạm thời...";
            } else {
                this.emailInput.placeholder = "Địa chỉ email tạm thời";
            }
        }
    }

    startRandomCooldown(seconds = 3) {
        if (!this.randomMailBtn) return;
        this.state.randomCooldownUntil = Date.now() + (seconds * 1000);
        this.randomMailBtn.disabled = true;
        this.randomMailBtn.classList.add("is-cooldown");

        const label = this.randomMailBtn.querySelector(".btn-label");
        const originalText = label ? label.textContent.trim() : "Tạo mới";

        if (this._randomCooldownTimer) {
            clearInterval(this._randomCooldownTimer);
        }

        const tick = () => {
            const leftMs = (this.state.randomCooldownUntil || 0) - Date.now();
            const leftSec = Math.ceil(leftMs / 1000);
            if (leftSec <= 0) {
                clearInterval(this._randomCooldownTimer);
                this._randomCooldownTimer = null;
                this.state.randomCooldownUntil = 0;
                if (this.randomMailBtn) {
                    this.randomMailBtn.disabled = false;
                    this.randomMailBtn.classList.remove("is-cooldown");
                    if (label) label.textContent = originalText;
                }
            } else {
                if (label) label.textContent = `${originalText} (${leftSec}s)`;
            }
        };

        tick();
        this._randomCooldownTimer = setInterval(tick, 500);
    }

    async generateRandomEmail(isInitial = false) {
        if (this.state.generatingEmail) return false;
        this.state.generatingEmail = true;
        this.setAddressLoading(true);
        if (this.randomMailBtn) this.randomMailBtn.classList.add("is-loading");

        try {
            // Pick a random domain from active domains list
            const domains = Array.isArray(this.config.domains) && this.config.domains.length > 0
                ? this.config.domains
                : [];
            const randomDomain = domains.length > 0
                ? domains[Math.floor(Math.random() * domains.length)]
                : undefined;

            const payload = { name_type: "en" };
            if (randomDomain) {
                payload.domain = randomDomain;
            }

            const res = await this.api.createEmail(payload);
            if (!res.ok || !res.data?.success) {
                const errMsg = res.data?.message || res.data?.error || "Không thể tạo email";
                this.toast(errMsg, "error");
                return false;
            }

            const newEmail = res.data?.emails?.[0]?.email;
            if (!newEmail) {
                this.toast("Lỗi phản hồi tạo email", "error");
                return false;
            }

            this.emailInput.value = newEmail;
            this.state.currentEmail = newEmail;
            localStorage.setItem(this.storageKey, newEmail);
            this.updateUrl(newEmail);

            await this.openInbox(newEmail, true);

            if (!isInitial) {
                this.toast("Tạo mới thành công", "success");
            }
            return true;
        } catch (err) {
            this.toast(err?.message || "Lỗi khi tạo email ngẫu nhiên", "error");
            return false;
        } finally {
            this.state.generatingEmail = false;
            this.setAddressLoading(false);
            if (this.randomMailBtn) this.randomMailBtn.classList.remove("is-loading");
        }
    }

    async onRandomEmail() {
        if (this.state.generatingEmail) return;

        // Anti-spam client-side rate limit
        const remainingMs = (this.state.randomCooldownUntil || 0) - Date.now();
        if (remainingMs > 0) {
            const sec = Math.ceil(remainingMs / 1000);
            this.toast(`Vui lòng chờ ${sec}s trước khi tạo mới`, "warning");
            return;
        }

        const success = await this.generateRandomEmail(false);
        if (success) {
            this.startRandomCooldown(3);
        }
    }

    async onCustomEmail() {
        const domains = Array.isArray(this.config.domains) && this.config.domains.length > 0
            ? this.config.domains
            : ["kaishop.id.vn"];

        const domainOptions = domains
            .map((d) => `<option value="${this.escapeHtml(d)}">@${this.escapeHtml(d)}</option>`)
            .join("");

        if (!window.Swal) {
            const prefix = prompt("Nhập tên hòm thư mong muốn:");
            if (!prefix) return;
            const res = await this.api.createEmail({ name_type: "custom", email: prefix, domain: domains[0] });
            if (res.ok && res.data?.success) {
                const em = res.data.emails[0].email;
                this.emailInput.value = em;
                await this.openInbox(em, false);
                this.toast("Tạo mới thành công", "success");
            }
            return;
        }

        const defaultDomain = domains[0];
        const { value: formValues } = await Swal.fire({
            title: "Tùy chỉnh email",
            html: `
                <div class="swal-custom-box" style="text-align: left; padding: 6px 0;">
                    <label style="display: block; font-size: 13px; font-weight: 600; color: #334155; margin-bottom: 6px;">Tên tùy chọn</label>
                    <div style="display: flex; align-items: stretch; gap: 8px;">
                        <input id="swalCustomPrefix" class="clean-swal-input" placeholder="ví dụ: tester, phuc710" style="flex: 1.2; height: 42px; padding: 0 12px; font-size: 14px; font-weight: 600; font-family: 'JetBrains Mono', monospace; border: 1.5px solid #cbd5e1; border-radius: 10px; outline: none; box-shadow: none;" autocomplete="off" spellcheck="false">
                        <select id="swalCustomDomain" class="clean-swal-select" style="flex: 1; height: 42px; padding: 0 10px; font-size: 13.5px; font-weight: 600; border: 1.5px solid #cbd5e1; border-radius: 10px; background: #ffffff; outline: none; box-shadow: none; cursor: pointer;">
                            ${domainOptions}
                        </select>
                    </div>
                </div>
            `,
            didOpen: () => {
                const prefixInput = document.getElementById("swalCustomPrefix");
                const domainSelect = document.getElementById("swalCustomDomain");
                const previewEl = document.getElementById("swalEmailPreview");

                const updatePreview = () => {
                    const p = (prefixInput?.value || "").trim().toLowerCase() || "...";
                    const d = domainSelect?.value || defaultDomain;
                    if (previewEl) {
                        previewEl.innerHTML = `Email: <span style="color: #0f172a;">${p}@${d}</span>`;
                    }
                };

                if (prefixInput) {
                    prefixInput.focus();
                    prefixInput.addEventListener("input", updatePreview);
                    prefixInput.addEventListener("focus", () => {
                        prefixInput.style.borderColor = "#0f172a";
                    });
                    prefixInput.addEventListener("blur", () => {
                        prefixInput.style.borderColor = "#cbd5e1";
                    });
                }
                if (domainSelect) {
                    domainSelect.addEventListener("change", updatePreview);
                    domainSelect.addEventListener("focus", () => {
                        domainSelect.style.borderColor = "#0f172a";
                    });
                    domainSelect.addEventListener("blur", () => {
                        domainSelect.style.borderColor = "#cbd5e1";
                    });
                }
            },
            focusConfirm: false,
            showCancelButton: true,
            confirmButtonText: "Tạo mới",
            cancelButtonText: "Hủy",
            confirmButtonColor: "#0f172a",
            cancelButtonColor: "#94a3b8",
            preConfirm: () => {
                const prefix = document.getElementById("swalCustomPrefix")?.value.trim().toLowerCase();
                const domain = document.getElementById("swalCustomDomain")?.value.trim().toLowerCase();
                if (!prefix) {
                    Swal.showValidationMessage("Vui lòng nhập tên hòm thư");
                    return false;
                }
                if (!/^[a-z0-9\-\._]+$/.test(prefix)) {
                    Swal.showValidationMessage("Tên chỉ được chứa chữ cái, số, gạch ngang, gạch dưới, chấm");
                    return false;
                }
                if (!domain) {
                    Swal.showValidationMessage("Vui lòng chọn tên miền");
                    return false;
                }
                return { prefix, domain };
            }
        });

        if (!formValues) return;

        this.setAddressLoading(true);
        try {
            const res = await this.api.createEmail({
                name_type: "custom",
                email: formValues.prefix,
                domain: formValues.domain
            });

            if (!res.ok || !res.data?.success) {
                const errMsg = res.data?.errors?.[0] || res.data?.message || "Không thể tạo email tùy chỉnh";
                this.toast(errMsg, "error");
                return;
            }

            const newEmail = res.data?.emails?.[0]?.email;
            if (!newEmail) {
                this.toast("Lỗi phản hồi tạo email", "error");
                return;
            }

            this.emailInput.value = newEmail;
            this.state.currentEmail = newEmail;
            localStorage.setItem(this.storageKey, newEmail);
            this.updateUrl(newEmail);

            await this.openInbox(newEmail, true);
            this.toast("Tạo mới thành công", "success");
        } catch (err) {
            this.toast(err?.message || "Lỗi khi tạo email tùy chỉnh", "error");
        } finally {
            this.setAddressLoading(false);
        }
    }

    async onDeleteEmail() {
        if (this.state.loading || this.state.generatingEmail || this.deleteMailBtn?.disabled) return;

        const inputVal = this.normalizeEmail(this.emailInput?.value);
        const email = inputVal || this.state.currentEmail;

        if (!email) {
            this.toast("Vui lòng nhập địa chỉ email cần xóa", "warning");
            if (this.emailInput) this.emailInput.focus();
            return;
        }

        if (!this.isValidEmail(email)) {
            this.toast("Định dạng email không hợp lệ", "error");
            if (this.emailInput) this.emailInput.focus();
            return;
        }

        // Senior Confirmation Modal before destructive permanent deletion
        if (window.Swal && typeof window.Swal.fire === "function") {
            const result = await Swal.fire({
                title: "Xác nhận xóa hộp thư?",
                html: `
                    <div style="font-size: 14px; color: #475569; line-height: 1.6; margin-top: 6px;">
                        Bạn có chắc chắn muốn xóa vĩnh viễn địa chỉ email:
                        <div style="font-family: 'JetBrains Mono', monospace; font-size: 14.5px; font-weight: 700; color: #0f172a; background: #f8fafc; border: 1px solid #e2e8f0; padding: 10px 12px; border-radius: 10px; margin: 12px 0; word-break: break-all;">
                            ${this.escapeHtml(email)}
                        </div>
                        <span style="color: #ef4444; font-weight: 600;">Lưu ý:</span> Hành động này sẽ xóa vĩnh viễn hộp thư và toàn bộ thư bên trong khỏi hệ thống, không thể khôi phục lại.
                    </div>
                `,
                icon: "warning",
                showCancelButton: true,
                confirmButtonText: "Đồng ý xóa",
                cancelButtonText: "Hủy bỏ",
                confirmButtonColor: "#ef4444",
                cancelButtonColor: "#64748b",
                focusCancel: true,
                reverseButtons: true,
                customClass: {
                    popup: "km-swal-modal"
                }
            });

            if (!result.isConfirmed) return;
        } else {
            const ok = confirm(`Bạn có chắc chắn muốn xóa vĩnh viễn hộp thư ${email} không? Toàn bộ thư bên trong sẽ bị xóa và không thể khôi phục.`);
            if (!ok) return;
        }

        this.setAddressLoading(true);
        try {
            this.stopPolling();
            const res = await this.api.deleteEmail(email);
            if (!res.ok) {
                const errMsg = res.data?.error || res.data?.message || "Email không tồn tại trong hệ thống";
                this.toast(errMsg, "error");

                // Never retain invalid or non-existent email in cache
                if (this.state.currentEmail === email || localStorage.getItem(this.storageKey) === email) {
                    localStorage.removeItem(this.storageKey);
                    this.state.currentEmail = "";
                }
                return;
            }

            // Purge cache completely
            localStorage.removeItem(this.storageKey);
            this.resetToEmptyMailbox();
            this.toast("Đã xóa email thành công", "success");
        } catch (err) {
            this.toast(err?.message || "Lỗi khi xóa email", "error");
        } finally {
            this.setAddressLoading(false);
        }
    }

    async onQrCode() {
        if (this.qrMailBtn?.disabled) return;
        const email = this.state.currentEmail;
        if (!email) {
            this.toast("Chưa có email nào để hiển thị QR", "warning");
            return;
        }

        const mailboxUrl = window.location.origin + this.baseUrl.replace(window.location.origin, "").replace(/\/+$/, "") + "/" + encodeURIComponent(email);
        const fallbackQrUrl = `https://api.qrserver.com/v1/create-qr-code/?size=220x220&margin=0&data=${encodeURIComponent(mailboxUrl)}`;

        if (window.Swal) {
            Swal.fire({
                title: "Mã QR Hộp Thư",
                html: `
                    <div style="text-align: center; padding: 4px 0;">
                        <div id="qrCodeContainer" style="display: flex; justify-content: center; align-items: center; min-height: 220px; margin: 8px auto;">
                            <img id="qrFallbackImg" src="${fallbackQrUrl}" alt="QR Code" style="width: 210px; height: 210px; border-radius: 12px; box-shadow: 0 4px 14px rgba(15, 23, 42, 0.08); display: block;" />
                        </div>
                        <div style="font-family: 'JetBrains Mono', monospace; font-size: 14.5px; font-weight: 700; color: #0f172a; margin-top: 12px; word-break: break-all;">
                            ${this.escapeHtml(email)}
                        </div>
                        <p style="font-size: 12.5px; color: #64748b; margin-top: 6px;">
                            Quét mã QR bằng camera điện thoại để mở hòm thư này ngay tức thì.
                        </p>
                    </div>
                `,
                showCloseButton: true,
                showConfirmButton: false,
                customClass: { popup: "km-qr-modal" },
                didOpen: () => {
                    const container = document.getElementById("qrCodeContainer");
                    if (container && window.QRCode && typeof window.QRCode === "function") {
                        try {
                            container.innerHTML = "";
                            new window.QRCode(container, {
                                text: mailboxUrl,
                                width: 210,
                                height: 210,
                                colorDark: "#0f172a",
                                colorLight: "#ffffff",
                                correctLevel: window.QRCode.CorrectLevel ? window.QRCode.CorrectLevel.H : 2
                            });
                        } catch (e) {
                            console.error("QRCode rendering fallback:", e);
                            container.innerHTML = `<img src="${fallbackQrUrl}" alt="QR Code" style="width: 210px; height: 210px; border-radius: 12px; box-shadow: 0 4px 14px rgba(15, 23, 42, 0.08); display: block;" />`;
                        }
                    }
                }
            });
        }
    }

    startPolling() {
        if (this.state.currentEmailId < 1 || typeof window.LongPollingManager !== "function") return;

        if (!this.poller) {
            this.poller = new window.LongPollingManager({
                basePath: this.baseUrl,
                webToken: this.webToken,
                onNewMessages: (messages, _count, payload) => this.handleNewMessages(messages, payload),
                onError: (err) => console.error("polling error:", err),
            });
        } else {
            this.poller.stop();
        }

        if (this.state.lastCheck === "") {
            this.state.lastCheck = this.time.nowSqlVN();
        }

        this.poller.start(this.state.currentEmailId, this.state.lastCheck, this.state.currentEmail);
    }

    stopPolling() {
        if (!this.poller) return;
        this.poller.stop();
    }

    handleNewMessages(messages, payload) {
        const list = Array.isArray(messages) ? messages : [];
        if (list.length === 0) return;

        this.renderMessages(list, false);

        const unreadAdded = list.reduce((sum, msg) => {
            return sum + (Number(msg?.is_read || 0) === 0 ? 1 : 0);
        }, 0);
        if (unreadAdded > 0) {
            this.state.unread += unreadAdded;
            this.setUnread(this.state.unread);
        }

        this.state.lastCheck = String(payload?.last_check || payload?.server_time || "").trim() || this.time.nowSqlVN();
        if (this.poller) {
            this.poller.updateLastCheck(this.state.lastCheck);
        }

        // Trigger clean Swal notification with sound & direct view action
        this.notifyNewMessage(list);
    }

    notifyNewMessage(messages) {
        const list = Array.isArray(messages) ? messages : [];
        if (list.length === 0) return;

        const firstMsg = list[0];
        const sender = this.getDisplayName(firstMsg) || "Email mới";

        // Update browser tab title
        if (!this.originalTitle) this.originalTitle = document.title;
        document.title = `(1) Thư mới! - ${this.originalTitle}`;

        // Play high-end crystal notification chime
        this.playNotificationSound();

        // SweetAlert2 notification:
        // Tiêu đề: Bạn có thư mới!
        // Nội dung: [Tên Người Gửi]
        if (window.Swal && typeof window.Swal.fire === "function") {
            window.Swal.fire({
                toast: true,
                position: "top-end",
                icon: "success",
                title: "Bạn có thư mới!",
                text: sender,
                showConfirmButton: false,
                timer: 4000,
                timerProgressBar: true,
                customClass: {
                    popup: "km-toast km-mail-toast"
                },
                didOpen: (toast) => {
                    toast.addEventListener("click", () => {
                        const targetId = Number(firstMsg.id || 0);
                        if (targetId > 0) {
                            const item = this.messagesList?.querySelector(`.message-item[data-id="${targetId}"]`);
                            if (item) {
                                this.toggleMessage(item, targetId);
                            }
                        }
                        window.Swal.close();
                    });
                }
            });
        }
    }

    playNotificationSound() {
        try {
            const AudioCtx = window.AudioContext || window.webkitAudioContext;
            if (!AudioCtx) return;
            const ctx = new AudioCtx();

            const playTone = (freq, start, duration, gainLevel = 0.08) => {
                const osc = ctx.createOscillator();
                const gain = ctx.createGain();

                osc.type = "sine";
                osc.frequency.setValueAtTime(freq, ctx.currentTime + start);

                gain.gain.setValueAtTime(0.0001, ctx.currentTime + start);
                gain.gain.exponentialRampToValueAtTime(gainLevel, ctx.currentTime + start + 0.015);
                gain.gain.exponentialRampToValueAtTime(0.0001, ctx.currentTime + start + duration);

                osc.connect(gain);
                gain.connect(ctx.destination);

                osc.start(ctx.currentTime + start);
                osc.stop(ctx.currentTime + start + duration + 0.02);
            };

            // Modern crystal dual chime (A5 880Hz -> E6 1318.5Hz)
            playTone(880, 0, 0.28, 0.07);
            playTone(1318.5, 0.08, 0.38, 0.09);
        } catch (e) {}
    }

    setUnread(count) {
        const n = Number(count || 0);
        if (n > 0) {
            this.unreadBadge.textContent = String(n);
            this.unreadBadge.classList.remove("hidden");
            return;
        }
        this.unreadBadge.textContent = "0";
        this.unreadBadge.classList.add("hidden");
    }

    showInbox(show) {
        // Section is now fixed, no more hiding
        if (this.inboxSection) {
            this.inboxSection.classList.remove("hidden");
        }
    }

    showMessageList(show) {
        this.messagesList.classList.toggle("hidden", !show);
    }

    showEmpty(show) {
        this.emptyState.classList.toggle("hidden", !show);
    }

    showLoading(show) {
        // Home page no longer shows a spinner overlay while fetching.
        // Keep current content visible to avoid visual flicker.
        if (this.loadingState) {
            this.loadingState.classList.add("hidden");
        }
    }

    showCopyButton(show) {
        if (!this.copyBtn) return;
        // Keep toolbar buttons visible
    }

    setGetMailLoading(loading) {
        if (!this.getMailBtn) return;
        this.getMailBtn.disabled = Boolean(loading);
        if (loading) {
            this.getMailBtn.innerHTML = "<span>Đang xem...</span>";
            return;
        }
        this.getMailBtn.innerHTML = this.defaultGetBtnHtml || `<span>Get Mail</span><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><line x1="5" y1="12" x2="19" y2="12"/><polyline points="12 5 19 12 12 19"/></svg>`;
    }

    setRefreshLoading(loading) {
        if (!this.refreshBtn) return;

        if (loading) {
            this.refreshBtn.disabled = true;
            this.refreshBtn.classList.add("spinning");
            this._refreshStartTime = Date.now();
        } else {
            const elapsed = Date.now() - (this._refreshStartTime || 0);
            const minDuration = 650;
            const remaining = Math.max(0, minDuration - elapsed);

            setTimeout(() => {
                if (this.refreshBtn) {
                    this.refreshBtn.classList.remove("spinning");
                    this.refreshBtn.classList.remove("animate");
                    this.updateRefreshState();
                }
            }, remaining);
        }
    }

    updateRefreshState() {
        const rawInput = String(this.emailInput?.value || "").trim();
        const normalizedInput = this.normalizeEmail(rawInput);
        const hasEmail = Boolean(this.state.currentEmail || (normalizedInput && this.isValidEmail(normalizedInput)));

        if (this.refreshBtn) {
            this.refreshBtn.disabled = !hasEmail;
            this.refreshBtn.classList.toggle("is-disabled", !hasEmail);
            this.refreshBtn.title = hasEmail ? "Làm mới hộp thư" : "Chưa có email để làm mới";
        }

        if (this.copyBtn) {
            this.copyBtn.disabled = !hasEmail;
            this.copyBtn.classList.toggle("is-disabled", !hasEmail);
            this.copyBtn.title = hasEmail ? "Sao chép địa chỉ email" : "Chưa có email để sao chép";
        }

        if (this.qrMailBtn) {
            this.qrMailBtn.disabled = !hasEmail;
            this.qrMailBtn.classList.toggle("is-disabled", !hasEmail);
            this.qrMailBtn.title = hasEmail ? "Xem mã QR để quét mở trên điện thoại" : "Chưa có email để tạo mã QR";
        }

        if (this.deleteMailBtn) {
            this.deleteMailBtn.disabled = !hasEmail;
            this.deleteMailBtn.classList.toggle("is-disabled", !hasEmail);
            this.deleteMailBtn.title = hasEmail ? "Xóa hộp thư hiện tại và tạo email mới" : "Chưa có email để xóa";
        }

        if (this.getMailBtn && !this.state.loading) {
            this.getMailBtn.disabled = false;
            this.getMailBtn.classList.remove("is-disabled");
        }
    }

    toggleEmailClearBtn() {
        if (!this.emailClearBtn) return;
        const val = String(this.emailInput?.value || "").trim();
        this.emailClearBtn.classList.toggle("hidden", val === "");
    }

    toast(message, type = "info") {
        const iconMap = { success: "success", error: "error", warning: "warning", info: "info" };
        const text = this.localizeError(message);

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

    localizeError(message) {
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

    getDisplayName(msg) {
        const fromName = String(msg?.from_name || "").trim();
        const fromEmail = String(msg?.from_email || "").trim();
        const subject = String(msg?.subject || "").trim();

        if (this.isValidSenderName(fromName, fromEmail)) {
            return fromName;
        }

        const detectedFromEmail = this.detectBrandFromEmail(fromEmail);
        if (detectedFromEmail !== "") return detectedFromEmail;

        const detectedFromSubject = this.detectBrandFromSubject(subject);
        if (detectedFromSubject !== "") return detectedFromSubject;

        const match = fromEmail.match(/^([^@]+)/);
        if (match) {
            const local = match[1].split("+")[0];
            if (local !== "") {
                return local.charAt(0).toUpperCase() + local.slice(1);
            }
        }
        return fromEmail || "Không rõ";
    }

    isValidSenderName(fromName, fromEmail) {
        if (fromName === "") return false;
        if (fromName.toLowerCase() === String(fromEmail || "").toLowerCase()) return false;
        if (/^em\d+$/i.test(fromName)) return false;
        if (/(?:^|\b)(?:no-?reply|noreply|bounce|mailer-daemon|notification)(?:\b|$)/i.test(fromName)) return false;
        return true;
    }

    detectBrandFromEmail(fromEmail) {
        const email = String(fromEmail || "").trim().toLowerCase();
        if (email === "" || !email.includes("@")) return "";

        const host = email.split("@")[1] || "";
        const brandMap = this.getBrandSignals();
        for (const brand of brandMap) {
            if (brand.domains.some((domain) => host === domain || host.endsWith(`.${domain}`))) {
                return brand.name;
            }
        }

        const hostParts = host.split(".").filter(Boolean);
        if (hostParts.length >= 2) {
            let root = hostParts[hostParts.length - 2];
            if (["co", "com", "net", "org", "gov", "edu"].includes(root) && hostParts.length >= 3) {
                root = hostParts[hostParts.length - 3];
            }
            return this.formatBrandName(root);
        }

        return "";
    }

    detectBrandFromSubject(subject) {
        const normalized = String(subject || "").toLowerCase();
        if (normalized === "") return "";

        const brandMap = this.getBrandSignals();
        for (const brand of brandMap) {
            if (brand.keywords.some((keyword) => normalized.includes(keyword))) {
                return brand.name;
            }
        }

        return "";
    }

    getBrandSignals() {
        return [
            { name: "OpenAI", domains: ["openai.com", "chatgpt.com"], keywords: ["openai", "chatgpt"] },
            { name: "GitHub", domains: ["github.com", "githubapp.com", "githubusercontent.com"], keywords: ["github"] },
            { name: "Canva", domains: ["canva.com"], keywords: ["canva"] },
            { name: "Google", domains: ["google.com", "gmail.com", "googlemail.com", "youtube.com"], keywords: ["google", "gmail", "youtube"] },
            { name: "Facebook", domains: ["facebook.com", "fb.com", "meta.com", "instagram.com"], keywords: ["facebook", "instagram", "meta"] },
            { name: "Microsoft", domains: ["microsoft.com", "outlook.com", "office.com", "azure.com"], keywords: ["microsoft", "outlook", "office 365", "azure"] },
            { name: "Apple", domains: ["apple.com", "icloud.com"], keywords: ["apple", "icloud"] },
            { name: "Amazon", domains: ["amazon.com", "aws.amazon.com"], keywords: ["amazon", "aws"] },
            { name: "Notion", domains: ["notion.so", "notion.com"], keywords: ["notion"] },
            { name: "Discord", domains: ["discord.com", "discordapp.com"], keywords: ["discord"] },
            { name: "LinkedIn", domains: ["linkedin.com"], keywords: ["linkedin"] },
            { name: "Telegram", domains: ["telegram.org"], keywords: ["telegram"] },
            { name: "TikTok", domains: ["tiktok.com"], keywords: ["tiktok"] },
            { name: "Slack", domains: ["slack.com"], keywords: ["slack"] },
            { name: "Figma", domains: ["figma.com"], keywords: ["figma"] },
            { name: "Atlassian", domains: ["atlassian.com"], keywords: ["atlassian", "jira", "confluence"] },
            { name: "Vercel", domains: ["vercel.com"], keywords: ["vercel"] },
            { name: "Linear", domains: ["linear.app"], keywords: ["linear"] },
            { name: "Dropbox", domains: ["dropbox.com"], keywords: ["dropbox"] },
            { name: "PayPal", domains: ["paypal.com"], keywords: ["paypal"] },
            { name: "Stripe", domains: ["stripe.com"], keywords: ["stripe"] },
        ];
    }

    formatBrandName(raw) {
        const token = String(raw || "").trim().toLowerCase();
        if (token === "") return "";

        const exact = {
            openai: "OpenAI",
            chatgpt: "ChatGPT",
            github: "GitHub",
            gitlab: "GitLab",
            linkedin: "LinkedIn",
            youtube: "YouTube",
            tiktok: "TikTok",
            paypal: "PayPal",
            iphone: "iPhone",
            icloud: "iCloud",
        };
        if (exact[token]) return exact[token];
        return token.charAt(0).toUpperCase() + token.slice(1);
    }

    resolveInitialEmail() {
        if (this.config.initialEmail && this.isValidEmail(this.config.initialEmail)) {
            return this.normalizeEmail(this.config.initialEmail);
        }

        const queryEmail = new URLSearchParams(window.location.search).get("email");
        if (this.isValidEmail(queryEmail)) return this.normalizeEmail(queryEmail);

        const path = window.location.pathname.replace(/\/+$/, "");
        const base = this.basePath.replace(/\/+$/, "");

        if (!path.includes("@")) return "";

        const raw = (base !== "" && path.startsWith(base))
            ? path.slice(base.length).replace(/^\/+/, "")
            : path.replace(/^\/+/, "");

        const email = decodeURIComponent(raw);
        if (email.includes("/") || !this.isValidEmail(email)) return "";
        return this.normalizeEmail(email);
    }

    resetToEmptyMailbox() {
        this.stopPolling();
        this.state.currentEmail = "";
        this.state.currentEmailId = 0;
        this.state.renderedIds = new Set();
        if (this.emailInput) {
            this.emailInput.value = "";
        }
        this.toggleEmailClearBtn();
        this.updateRefreshState();
        this.showEmpty(true);
        this.showMessageList(false);
        this.setUnread(0);
        this.updateUrl("");
    }

    updateUrl(email, replace = true) {
        if (this.state.currentMode === "twofa" || window.location.pathname.replace(/\/+$/, "").endsWith("/2fa")) {
            return;
        }

        const base = this.basePath.replace(/\/+$/, "");
        const cleanEmail = this.normalizeEmail(email);

        let targetPath = base ? `${base}/` : "/";
        if (cleanEmail && this.isValidEmail(cleanEmail)) {
            const encodedEmail = encodeURIComponent(cleanEmail).replace(/%40/g, "@");
            targetPath = (base ? `${base}/${encodedEmail}` : `/${encodedEmail}`);
        }

        targetPath = targetPath.replace(/\/{2,}/g, "/");
        const currentPath = (window.location.pathname + window.location.search).replace(/\/{2,}/g, "/");

        if (currentPath !== targetPath) {
            if (replace) {
                window.history.replaceState({ mode: "mail", email: cleanEmail }, "", targetPath);
            } else {
                window.history.pushState({ mode: "mail", email: cleanEmail }, "", targetPath);
            }
        }
    }

    extractBasePath(baseUrl) {
        const raw = String(baseUrl || "").trim();
        if (raw === "") return "";
        if (/^https?:\/\//i.test(raw)) {
            try {
                return new URL(raw).pathname.replace(/\/+$/, "");
            } catch (error) {
                return "";
            }
        }
        if (!raw.startsWith("/")) return `/${raw}`.replace(/\/+$/, "");
        return raw.replace(/\/+$/, "");
    }

    normalizeEmail(value) {
        return String(value || "").trim().toLowerCase();
    }

    isValidEmail(value) {
        const email = this.normalizeEmail(value);
        if (email === "") return false;
        return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email);
    }

    escapeHtml(value) {
        const div = document.createElement("div");
        div.textContent = String(value ?? "");
        return div.innerHTML;
    }

    checkSpam(key) {
        // Senior pattern: In-flight deduplication handled at event level. No punitive client locks.
        return true;
    }

    checkCooldown(key, seconds) {
        return true;
    }

    updateButtonCooldownUi(key, seconds) {
        // No-op: Do not hijack button label with countdown timers
    }

    switchMode(mode, pushRoute = true) {
        this.state.currentMode = mode;
        localStorage.setItem("kaimail_mode", mode);

        if (this.modeTabs) {
            this.modeTabs.forEach(tab => {
                const tabMode = tab.getAttribute("data-mode");
                tab.classList.toggle("active", tabMode === mode);
            });
        }

        if (mode === "mail") {
            if (this.mailModeContent) this.mailModeContent.classList.remove("hidden");
            if (this.twofaModeContent) this.twofaModeContent.classList.add("hidden");

            if (!this.state.currentEmail) {
                const initialEmail = this.resolveInitialEmail();
                if (initialEmail !== "") {
                    this.emailInput.value = initialEmail;
                    this.openInbox(initialEmail, true);
                } else {
                    this.resetToEmptyMailbox();
                }
            } else {
                this.startPolling();
                this.updateUrl(this.state.currentEmail);
            }

            if (this.twofaController) this.twofaController.stop();
            if (pushRoute && this.router) {
                this.router.navigate("mail", this.state.currentEmail || "");
            }
        } else {
            if (this.mailModeContent) this.mailModeContent.classList.add("hidden");
            if (this.twofaModeContent) this.twofaModeContent.classList.remove("hidden");
            this.stopPolling();
            if (this.twofaController) this.twofaController.start();
            if (pushRoute && this.router) {
                this.router.navigate("twofa");
            }
        }
    }

    copyTwofaOtp() {
        if (this.twofaController) {
            this.twofaController.copyOtp();
        }
    }
}

function initVietnamClock() {
    const clockEl = document.getElementById("clockTime");
    if (!clockEl) return;

    const updateClock = () => {
        try {
            const now = new Date();
            clockEl.textContent = now.toLocaleTimeString("vi-VN", {
                timeZone: "Asia/Ho_Chi_Minh",
                hour12: false,
                hour: "2-digit",
                minute: "2-digit",
                second: "2-digit"
            });
        } catch {
            const now = new Date();
            const utc = now.getTime() + (now.getTimezoneOffset() * 60000);
            const vnTime = new Date(utc + (3600000 * 7));
            const pad = (n) => String(n).padStart(2, "0");
            clockEl.textContent = `${pad(vnTime.getHours())}:${pad(vnTime.getMinutes())}:${pad(vnTime.getSeconds())}`;
        }
    };

    updateClock();
    setInterval(updateClock, 1000);
}

document.addEventListener("DOMContentLoaded", () => {
    const app = new KaiMailUserPage();
    app.init();
    window.kaimail = app;
    initVietnamClock();
});
