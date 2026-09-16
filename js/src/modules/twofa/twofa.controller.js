/**
 * KaiMail 2FA Controller - Quản lý chức năng Trình xác thực 2FA (TOTP)
 * Tương ứng với includes/views/twofa_view.php
 */

import { toast as defaultToast } from "../../core/toast.js";

export class KaiMailTwofaController {
    constructor({ toast } = {}) {
        this.toast = typeof toast === "function" ? toast : defaultToast;
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
        }
    }

    generateOtp(autoRun = false) {
        if (!this.twofaInput) return;
        let secret = this.twofaInput.value.trim().replace(/\s+/g, "");

        if (secret === "") {
            if (!autoRun) {
                this.toast("Vui lòng nhập khóa bí mật 2FA", "error");
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
