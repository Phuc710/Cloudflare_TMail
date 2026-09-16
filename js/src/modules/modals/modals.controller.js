/**
 * KaiMail Modals Controller
 * Quản lý các popup modal tương ứng với includes/views/modals_view.php:
 * 1. Custom Email Prefix Modal
 * 2. Add Custom Domain 2-Step Modal
 * 3. Mail Address QR Modal
 */

import { toast as defaultToast } from "../../core/toast.js";

export class KaiMailModalsController {
    constructor({ api, config, baseUrl, toast, onEmailCreated, onDomainActivated } = {}) {
        this.api = api;
        this.config = config || window.KAIMAIL_CONFIG || {};
        this.baseUrl = baseUrl || this.config.baseUrl || "";
        this.toast = typeof toast === "function" ? toast : defaultToast;
        this.onEmailCreated = typeof onEmailCreated === "function" ? onEmailCreated : () => {};
        this.onDomainActivated = typeof onDomainActivated === "function" ? onDomainActivated : () => {};

        this.currentCustomDomain = "";
        this.currentWorkerCode = "";

        this.bindDom();
        this.bindEvents();
    }

    bindDom() {
        // Modal 1: Custom Email
        this.customEmailModal = document.getElementById("customEmailModal");
        this.customEmailPrefixInput = document.getElementById("customEmailPrefixInput");
        this.customEmailDomainSelect = document.getElementById("customEmailDomainSelect");
        this.customEmailPreviewVal = document.getElementById("customEmailPreviewVal");
        this.customEmailAlert = document.getElementById("customEmailAlert");
        this.customEmailSubmitBtn = document.getElementById("customEmailSubmitBtn");
        this.customEmailCancelBtn = document.getElementById("customEmailCancelBtn");
        this.customEmailCloseBtn = document.getElementById("customEmailCloseBtn");

        // Modal 2: Add Custom Domain
        this.addDomainModal = document.getElementById("addDomainModal");
        this.addDomainCloseBtn = document.getElementById("addDomainCloseBtn");
        this.addDomainStep1 = document.getElementById("addDomainStep1");
        this.addDomainStep1Footer = document.getElementById("addDomainStep1Footer");
        this.addDomainInputVal = document.getElementById("addDomainInputVal");
        this.addDomainStep1Alert = document.getElementById("addDomainStep1Alert");
        this.addDomainStep1CancelBtn = document.getElementById("addDomainStep1CancelBtn");
        this.addDomainStep1NextBtn = document.getElementById("addDomainStep1NextBtn");

        this.addDomainStep2 = document.getElementById("addDomainStep2");
        this.addDomainStep2Footer = document.getElementById("addDomainStep2Footer");
        this.addDomainStep2DomainBadge = document.getElementById("addDomainStep2DomainBadge");
        this.addDomainCopyWorkerBtn = document.getElementById("addDomainCopyWorkerBtn");
        this.addDomainDownloadWorkerLink = document.getElementById("addDomainDownloadWorkerLink");
        this.addDomainStep2Alert = document.getElementById("addDomainStep2Alert");
        this.addDomainStep2BackBtn = document.getElementById("addDomainStep2BackBtn");
        this.addDomainStep2VerifyBtn = document.getElementById("addDomainStep2VerifyBtn");
    }

    bindEvents() {
        // Global Modal Close Listeners (Backdrop click + Escape key)
        document.addEventListener("keydown", (e) => {
            if (e.key === "Escape") {
                this.closeNativeModals();
            }
        });

        [this.customEmailModal, this.addDomainModal].forEach((modal) => {
            if (modal) {
                modal.addEventListener("click", (e) => {
                    if (e.target === modal) {
                        this.closeNativeModals();
                    }
                });
            }
        });

        // Bind Custom Email Modal Events
        if (this.customEmailCloseBtn) {
            this.customEmailCloseBtn.addEventListener("click", () => this.closeNativeModals());
        }
        if (this.customEmailCancelBtn) {
            this.customEmailCancelBtn.addEventListener("click", () => this.closeNativeModals());
        }

        const updateCustomPreview = () => {
            const prefix = (this.customEmailPrefixInput?.value || "").trim().toLowerCase() || "...";
            const domain = this.customEmailDomainSelect?.value || (this.config.domains?.[0] || "");
            if (this.customEmailPreviewVal) {
                this.customEmailPreviewVal.textContent = `${prefix}@${domain}`;
            }
            if (this.customEmailAlert) {
                this.customEmailAlert.style.display = "none";
            }
        };

        if (this.customEmailPrefixInput) {
            this.customEmailPrefixInput.addEventListener("input", updateCustomPreview);
            this.customEmailPrefixInput.addEventListener("keydown", (e) => {
                if (e.key === "Enter") {
                    e.preventDefault();
                    this.submitCustomEmailModal();
                }
            });
        }

        if (this.customEmailDomainSelect) {
            this.customEmailDomainSelect.addEventListener("change", () => {
                if (this.customEmailDomainSelect.value === "__add_custom_domain__") {
                    this.closeNativeModals();
                    this.openAddDomainModal();
                    return;
                }
                updateCustomPreview();
            });
        }

        if (this.customEmailSubmitBtn) {
            this.customEmailSubmitBtn.addEventListener("click", () => this.submitCustomEmailModal());
        }

        // Bind Add Domain Modal Events
        if (this.addDomainCloseBtn) {
            this.addDomainCloseBtn.addEventListener("click", () => this.closeNativeModals());
        }
        if (this.addDomainStep1CancelBtn) {
            this.addDomainStep1CancelBtn.addEventListener("click", () => this.closeNativeModals());
        }
        if (this.addDomainStep2BackBtn) {
            this.addDomainStep2BackBtn.addEventListener("click", () => this.closeNativeModals());
        }

        if (this.addDomainInputVal) {
            this.addDomainInputVal.addEventListener("input", () => {
                if (this.addDomainStep1Alert) {
                    this.addDomainStep1Alert.style.display = "none";
                }
            });
            this.addDomainInputVal.addEventListener("keydown", (e) => {
                if (e.key === "Enter") {
                    e.preventDefault();
                    this.submitAddDomainStep1();
                }
            });
        }

        if (this.addDomainStep1NextBtn) {
            this.addDomainStep1NextBtn.addEventListener("click", () => this.submitAddDomainStep1());
        }

        if (this.addDomainCopyWorkerBtn) {
            this.addDomainCopyWorkerBtn.addEventListener("click", () => this.copyWorkerCodeToClipboard());
        }

        if (this.addDomainStep2VerifyBtn) {
            this.addDomainStep2VerifyBtn.addEventListener("click", () => this.submitAddDomainStep2Verify());
        }
    }

    closeNativeModals() {
        if (this.customEmailModal) {
            this.customEmailModal.classList.remove("is-active");
            this.customEmailModal.setAttribute("aria-hidden", "true");
        }
        if (this.addDomainModal) {
            this.addDomainModal.classList.remove("is-active");
            this.addDomainModal.setAttribute("aria-hidden", "true");
        }
    }

    openCustomEmailModal() {
        this.closeNativeModals();
        if (!this.customEmailModal) return;

        if (this.customEmailPrefixInput) {
            this.customEmailPrefixInput.value = "";
        }
        if (this.customEmailAlert) {
            this.customEmailAlert.style.display = "none";
            this.customEmailAlert.textContent = "";
        }

        if (this.customEmailDomainSelect) {
            const domains = Array.isArray(this.config.domains) && this.config.domains.length > 0
                ? this.config.domains
                : ["kaishop.id.vn"];

            const optionsHtml = domains
                .map((d) => `<option value="${this.escapeHtml(d)}">@${this.escapeHtml(d)}</option>`)
                .join("") + `<option value="__add_custom_domain__" style="font-weight: 700; color: #0284c7;">➕ Thêm tên miền riêng...</option>`;

            this.customEmailDomainSelect.innerHTML = optionsHtml;
            this.customEmailDomainSelect.value = domains[0];
        }

        if (this.customEmailPreviewVal && this.customEmailDomainSelect) {
            this.customEmailPreviewVal.textContent = `...@${this.customEmailDomainSelect.value}`;
        }

        this.customEmailModal.classList.add("is-active");
        this.customEmailModal.setAttribute("aria-hidden", "false");

        setTimeout(() => {
            if (this.customEmailPrefixInput) {
                this.customEmailPrefixInput.focus();
            }
        }, 100);
    }

    async submitCustomEmailModal() {
        const prefix = (this.customEmailPrefixInput?.value || "").trim().toLowerCase();
        const domain = (this.customEmailDomainSelect?.value || "").trim().toLowerCase();

        if (!prefix) {
            this.showCustomEmailAlert("Vui lòng nhập tên hòm thư mong muốn");
            return;
        }

        if (!/^[a-z0-9\-\._]+$/.test(prefix)) {
            this.showCustomEmailAlert("Tên hòm thư chỉ được chứa chữ cái, số, dấu chấm, gạch ngang, gạch dưới");
            return;
        }

        if (!domain || domain === "__add_custom_domain__") {
            this.showCustomEmailAlert("Vui lòng chọn tên miền hợp lệ");
            return;
        }

        if (this.customEmailSubmitBtn) {
            this.customEmailSubmitBtn.disabled = true;
            this.customEmailSubmitBtn.innerHTML = `<span>Đang tạo...</span>`;
        }

        try {
            const res = await this.api.createEmail({
                name_type: "custom",
                email: prefix,
                domain: domain
            });

            if (!res.ok || !res.data?.success) {
                const errMsg = res.data?.errors?.[0] || res.data?.message || "Không thể tạo email tùy chỉnh";
                this.showCustomEmailAlert(errMsg);
                return;
            }

            const newEmail = res.data?.emails?.[0]?.email;
            if (!newEmail) {
                this.showCustomEmailAlert("Lỗi phản hồi tạo email");
                return;
            }

            this.closeNativeModals();
            await this.onEmailCreated(newEmail);
        } catch (err) {
            this.showCustomEmailAlert(err.message || "Lỗi khi tạo email tùy chỉnh");
        } finally {
            if (this.customEmailSubmitBtn) {
                this.customEmailSubmitBtn.disabled = false;
                this.customEmailSubmitBtn.innerHTML = `<span>Tạo mới</span>`;
            }
        }
    }

    showCustomEmailAlert(msg) {
        if (this.customEmailAlert) {
            this.customEmailAlert.textContent = msg;
            this.customEmailAlert.style.display = "block";
        }
    }

    openAddDomainModal(initialDomain = "") {
        this.closeNativeModals();
        if (!this.addDomainModal) return;

        if (this.addDomainStep1) this.addDomainStep1.style.display = "block";
        if (this.addDomainStep1Footer) this.addDomainStep1Footer.style.display = "flex";
        if (this.addDomainStep2) this.addDomainStep2.style.display = "none";
        if (this.addDomainStep2Footer) this.addDomainStep2Footer.style.display = "none";

        if (this.addDomainInputVal) {
            this.addDomainInputVal.value = initialDomain;
        }
        if (this.addDomainStep1Alert) {
            this.addDomainStep1Alert.style.display = "none";
            this.addDomainStep1Alert.textContent = "";
        }
        if (this.addDomainStep2Alert) {
            this.addDomainStep2Alert.style.display = "none";
            this.addDomainStep2Alert.textContent = "";
        }

        this.currentCustomDomain = "";
        this.currentWorkerCode = "";

        this.addDomainModal.classList.add("is-active");
        this.addDomainModal.setAttribute("aria-hidden", "false");

        setTimeout(() => {
            if (this.addDomainInputVal) {
                this.addDomainInputVal.focus();
            }
        }, 100);
    }

    async submitAddDomainStep1() {
        let domain = (this.addDomainInputVal?.value || "").trim().toLowerCase();
        domain = domain.replace(/^https?:\/\//i, "").replace(/\/+$/, "");

        if (!domain || !/^[a-z0-9]([a-z0-9-]{0,61}[a-z0-9])?(\.[a-z0-9]([a-z0-9-]{0,61}[a-z0-9])?)+$/i.test(domain)) {
            this.showAddDomainStep1Alert("Vui lòng nhập tên miền hợp lệ (ví dụ: devmail.vn, dewii.dpdns.org)");
            return;
        }

        const activeDomains = Array.isArray(this.config.domains)
            ? this.config.domains.map((x) => String(x).toLowerCase().trim())
            : [];
        if (activeDomains.includes(domain)) {
            this.showAddDomainStep1Alert(`Tên miền @${domain} đã có sẵn và đang hoạt động rồi! Bạn có thể chọn và sử dụng ngay.`);
            return;
        }

        if (this.addDomainStep1NextBtn) {
            this.addDomainStep1NextBtn.disabled = true;
            this.addDomainStep1NextBtn.innerHTML = `<span>Đang khởi tạo...</span>`;
        }

        try {
            const setupRes = await fetch(this.api.buildUrl("/api/custom-domains.php", { action: "setup" }), {
                method: "POST",
                headers: this.api.buildHeaders(),
                body: JSON.stringify({ domain: domain })
            });
            const setupData = await setupRes.json();

            if (!setupRes.ok || !setupData?.success) {
                const errMsg = setupData?.error || setupData?.message || "Không thể khởi tạo cấu hình domain";
                this.showAddDomainStep1Alert(errMsg);
                return;
            }

            this.currentCustomDomain = setupData.domain;
            this.currentWorkerCode = setupData.worker_code || "";

            if (this.addDomainStep1) this.addDomainStep1.style.display = "none";
            if (this.addDomainStep1Footer) this.addDomainStep1Footer.style.display = "none";
            if (this.addDomainStep2) this.addDomainStep2.style.display = "block";
            if (this.addDomainStep2Footer) this.addDomainStep2Footer.style.display = "flex";

            if (this.addDomainStep2DomainBadge) {
                this.addDomainStep2DomainBadge.textContent = `@${this.currentCustomDomain}`;
            }
            if (this.addDomainDownloadWorkerLink) {
                this.addDomainDownloadWorkerLink.href = setupData.download_url;
            }
            if (this.addDomainStep2Alert) {
                this.addDomainStep2Alert.style.display = "none";
            }
        } catch (err) {
            this.showAddDomainStep1Alert(err.message || "Lỗi kết nối máy chủ");
        } finally {
            if (this.addDomainStep1NextBtn) {
                this.addDomainStep1NextBtn.disabled = false;
                this.addDomainStep1NextBtn.innerHTML = `<span>Tiếp tục &rarr;</span>`;
            }
        }
    }

    showAddDomainStep1Alert(msg) {
        if (this.addDomainStep1Alert) {
            this.addDomainStep1Alert.textContent = msg;
            this.addDomainStep1Alert.style.display = "block";
        }
    }

    async copyWorkerCodeToClipboard() {
        if (!this.currentWorkerCode) {
            this.toast("Chưa có mã Worker", "warning");
            return;
        }

        try {
            await navigator.clipboard.writeText(this.currentWorkerCode);
            if (this.addDomainCopyWorkerBtn) {
                const originalHtml = this.addDomainCopyWorkerBtn.innerHTML;
                this.addDomainCopyWorkerBtn.style.background = "#059669";
                this.addDomainCopyWorkerBtn.style.color = "#ffffff";
                this.addDomainCopyWorkerBtn.innerHTML = `
                    <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="20 6 9 17 4 12"/></svg>
                    <span>Đã sao chép!</span>
                `;
                setTimeout(() => {
                    if (this.addDomainCopyWorkerBtn) {
                        this.addDomainCopyWorkerBtn.style.background = "";
                        this.addDomainCopyWorkerBtn.style.color = "";
                        this.addDomainCopyWorkerBtn.innerHTML = originalHtml;
                    }
                }, 2000);
            }
            this.toast("Đã sao chép mã Worker vào clipboard", "success");
        } catch {
            const input = document.createElement("textarea");
            input.value = this.currentWorkerCode;
            document.body.appendChild(input);
            input.select();
            document.execCommand("copy");
            document.body.removeChild(input);
            this.toast("Đã sao chép mã Worker", "success");
        }
    }

    async submitAddDomainStep2Verify() {
        if (!this.currentCustomDomain) {
            this.toast("Thiếu thông tin domain", "error");
            return;
        }

        if (this.addDomainStep2VerifyBtn) {
            this.addDomainStep2VerifyBtn.disabled = true;
            this.addDomainStep2VerifyBtn.innerHTML = `<span>⏳ Đang kiểm tra DNS...</span>`;
        }
        if (this.addDomainStep2Alert) {
            this.addDomainStep2Alert.style.display = "none";
        }

        try {
            const verifyRes = await fetch(this.api.buildUrl("/api/custom-domains.php", { action: "verify" }), {
                method: "POST",
                headers: this.api.buildHeaders(),
                body: JSON.stringify({ domain: this.currentCustomDomain })
            });
            const verifyData = await verifyRes.json();

            if (!verifyRes.ok || !verifyData?.success) {
                const errMsg = verifyData?.message || verifyData?.error || "Chưa phát hiện bản ghi MX Cloudflare trên tên miền của bạn.";
                this.showAddDomainStep2Alert(errMsg);
                return;
            }

            // Update active domains
            const domainName = this.currentCustomDomain;
            if (Array.isArray(this.config.domains)) {
                if (!this.config.domains.includes(domainName)) {
                    this.config.domains.unshift(domainName);
                }
            } else {
                this.config.domains = [domainName];
            }

            this.closeNativeModals();
            const newEmail = `contact@${domainName}`;
            await this.onDomainActivated(domainName, newEmail);
        } catch (err) {
            this.showAddDomainStep2Alert(err.message || "Lỗi kiểm tra DNS domain");
        } finally {
            if (this.addDomainStep2VerifyBtn) {
                this.addDomainStep2VerifyBtn.disabled = false;
                this.addDomainStep2VerifyBtn.innerHTML = `<span>🚀 Kiểm Tra & Kích Hoạt Ngay</span>`;
            }
        }
    }

    showAddDomainStep2Alert(msg) {
        if (this.addDomainStep2Alert) {
            this.addDomainStep2Alert.textContent = msg;
            this.addDomainStep2Alert.style.display = "block";
        }
    }

    showMailQrModal(email) {
        if (!email) {
            this.toast("Chưa có email nào để hiển thị QR", "warning");
            return;
        }

        const mailboxUrl = window.location.origin + this.baseUrl.replace(window.location.origin, "").replace(/\/+$/, "") + "/" + encodeURIComponent(email);
        const fallbackQrUrl = `https://api.qrserver.com/v1/create-qr-code/?size=220x220&margin=0&data=${encodeURIComponent(mailboxUrl)}`;

        if (window.Swal) {
            window.Swal.fire({
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

    escapeHtml(value) {
        const div = document.createElement("div");
        div.textContent = String(value ?? "");
        return div.innerHTML;
    }
}
