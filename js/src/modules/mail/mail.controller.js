/**
 * KaiMail Mail Controller & User Page Orchestrator
 * Quản lý tính năng Hòm thư (inbox, polling, message rendering) tương ứng mail_view.php
 * và điều phối các mode chuyển đổi (mail, 2fa, qr, docs).
 */

import { KaiMailTime } from "../../core/time.js";
import { KaiMailApi } from "../../core/api.js";
import { KaiMailRouter } from "../../core/router.js";
import { toast, localizeError } from "../../core/toast.js";
import { KaiMailTwofaController } from "../twofa/twofa.controller.js";
import { KaiMailQrController } from "../qr/qr.controller.js";
import { KaiMailModalsController } from "../modals/modals.controller.js";

export class KaiMailUserPage {
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

        this.qrController = new KaiMailQrController({
            toast: (msg, type) => this.toast(msg, type),
            baseUrl: this.baseUrl
        });

        this.modals = new KaiMailModalsController({
            api: this.api,
            config: this.config,
            baseUrl: this.baseUrl,
            toast: (msg, type) => this.toast(msg, type),
            onEmailCreated: async (newEmail) => {
                this.emailInput.value = newEmail;
                this.state.currentEmail = newEmail;
                localStorage.setItem(this.storageKey, newEmail);
                this.updateUrl(newEmail);
                await this.openInbox(newEmail, true);
                this.toast("Tạo mới email thành công", "success");
            },
            onDomainActivated: async (domainName, newEmail) => {
                this.emailInput.value = newEmail;
                this.state.currentEmail = newEmail;
                localStorage.setItem(this.storageKey, newEmail);
                this.updateUrl(newEmail);
                await this.openInbox(newEmail, false);
                this.toast(`Tên miền @${domainName} đã được kích hoạt thành công!`, "success");
            }
        });

        this.state = {
            currentEmail: "",
            currentEmailId: 0,
            loading: false,
            unread: 0,
            renderedIds: new Set(),
            lastCheck: "",
            cooldowns: {},
            clickStats: {},
            currentMode: "mail"
        };

        this.poller = null;

        this.bindDom();
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
        this.addDomainBtn = document.getElementById("addDomainBtn");
        this.qrMailBtn = document.getElementById("qrMailBtn");
        this.deleteMailBtn = document.getElementById("deleteMailBtn");
        this.emailSpinner = document.getElementById("emailSpinner");
        this.refreshBtn = document.getElementById("refreshBtn");
        this.inboxSection = document.getElementById("inboxSection");
        this.messagesList = document.getElementById("messagesList");
        this.emptyState = document.getElementById("emptyState");
        this.loadingState = document.getElementById("loadingState");
        this.unreadBadge = document.getElementById("unreadBadge");

        this.defaultGetBtnHtml = this.getMailBtn ? this.getMailBtn.innerHTML : "";

        this.userMain = document.getElementById("userMain");
        this.mailModeContent = document.getElementById("mailModeContent");
        this.twofaModeContent = document.getElementById("twofaModeContent");
        this.qrModeContent = document.getElementById("qrModeContent");
        this.docsModeContent = document.getElementById("docsModeContent");
        this.docsInitialized = false;
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
            this.customMailBtn.addEventListener("click", () => this.modals.openCustomEmailModal());
        }

        if (this.addDomainBtn) {
            this.addDomainBtn.addEventListener("click", () => this.modals.openAddDomainModal());
        }

        if (this.qrMailBtn) {
            this.qrMailBtn.addEventListener("click", () => this.modals.showMailQrModal(this.state.currentEmail));
        }

        if (this.deleteMailBtn) {
            this.deleteMailBtn.addEventListener("click", () => this.onDeleteEmail());
        }

        this.copyBtn.addEventListener("click", () => this.copyEmail());

        let emailInputDebounce = null;
        this.emailInput.addEventListener("keydown", (event) => {
            if (event.key === "Enter") {
                event.preventDefault();
                this.openInboxFromInput();
            }
        });

        this.emailInput.addEventListener("input", () => {
            this.toggleEmailClearBtn();
            this.updateRefreshState();

            const inputVal = this.normalizeEmail(this.emailInput.value);
            if (this.isValidEmail(inputVal)) {
                if (emailInputDebounce) clearTimeout(emailInputDebounce);
                emailInputDebounce = setTimeout(() => {
                    if (this.normalizeEmail(this.emailInput.value) === inputVal) {
                        this.openInbox(inputVal, false);
                    }
                }, 600);
            }
        });

        if (this.emailClearBtn) {
            this.emailClearBtn.addEventListener("click", () => {
                if (emailInputDebounce) clearTimeout(emailInputDebounce);
                this.resetToEmptyMailbox();
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

        // 1. Initialize 2FA & QR Controllers
        this.twofaController.init();
        this.qrController.init();

        // 2. Determine Initial Mode (URL > Router > Config > Default 'mail')
        const path = window.location.pathname.replace(/\/+$/, "");
        const search = new URLSearchParams(window.location.search);
        const isDocsRoute = path.endsWith("/docs") || path.endsWith("/user_docs") || search.get("mode") === "docs" || this.config.isDocsRoute || this.config.initialMode === "docs";
        const isTwoFaRoute = !isDocsRoute && (path.endsWith("/2fa") || search.get("mode") === "twofa" || this.config.isTwoFaRoute || this.config.initialMode === "twofa");
        const isQrRoute = !isDocsRoute && !isTwoFaRoute && (path.endsWith("/qr") || search.get("mode") === "qr" || this.config.isQrRoute || this.config.initialMode === "qr");
        const initialMode = isDocsRoute ? "docs" : (isTwoFaRoute ? "twofa" : (isQrRoute ? "qr" : "mail"));
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
            if (route.mode === "qr" && route.code) {
                if (this.qrController && this.qrController.qrInput) {
                    this.qrController.qrInput.value = route.code;
                    this.qrController.renderQr(route.code);
                }
            }
            if (route.mode === "mail") {
                const targetEmail = this.normalizeEmail(route.email);
                if (targetEmail !== this.state.currentEmail) {
                    if (targetEmail !== "") {
                        this.emailInput.value = targetEmail;
                        this.openInbox(targetEmail, false);
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
        } finally {
            this.state.loading = false;
            this.setRefreshLoading(false);
            this.showLoading(false);
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
            const extractedOtp = this.extractOTP(subject, bodyText);

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
                            ${extractedOtp ? `
                                <button type="button" class="btn-copy-otp" data-otp="${this.escapeHtml(extractedOtp)}" title="Sao chép nhanh mã xác nhận">
                                    <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                        <rect width="18" height="11" x="3" y="11" rx="2" ry="2"/>
                                        <path d="M7 11V7a5 5 0 0 1 10 0v4"/>
                                    </svg>
                                    <span>Mã: ${this.escapeHtml(extractedOtp)}</span>
                                </button>
                            ` : ""}
                        </div>
                        <div class="detail-body-content" id="body-content-${id}"></div>
                    </div>
                </div>
            `;

            if (extractedOtp) {
                const otpBtn = detailContainer.querySelector(".btn-copy-otp");
                if (otpBtn) {
                    otpBtn.addEventListener("click", (e) => {
                        e.stopPropagation();
                        this.copyOtp(extractedOtp);
                    });
                }
            }

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
        const otpRegex = /\b\d{4,8}\b/g;

        if (subject) {
            const subjectMatches = [...subject.matchAll(otpRegex)];
            if (subjectMatches.length > 0) {
                return subjectMatches[subjectMatches.length - 1][0];
            }
        }

        if (body) {
            const bodyMatches = [...body.matchAll(otpRegex)];
            if (bodyMatches.length > 0) {
                const uniqueOtps = [...new Set(bodyMatches.map(m => m[0]))];
                if (uniqueOtps.length === 1) {
                    return uniqueOtps[0];
                }

                const lines = body.split("\n");
                for (const line of lines) {
                    if (line.toLowerCase().includes("code") || line.toLowerCase().includes("otp") || line.toLowerCase().includes("mã")) {
                        const lineMatches = line.match(otpRegex);
                        if (lineMatches && lineMatches.length === 1) {
                            return lineMatches[0];
                        }
                    }
                }

                for (const m of bodyMatches) {
                    if (!body.includes(m[0] + "-") && !body.includes("-" + m[0]) && !body.includes(m[0] + "/")) {
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

    async onDeleteEmail() {
        if (this.state.loading || this.state.generatingEmail || this.deleteMailBtn?.disabled) return;

        const inputVal = this.normalizeEmail(this.emailInput?.value);
        const email = inputVal || this.state.currentEmail;

        if (!email) {
            this.toast("Vui lòng nhập địa chỉ email cần xóa", "warning");
            return;
        }

        if (!this.isValidEmail(email)) {
            this.toast("Định dạng email không hợp lệ", "error");
            return;
        }

        if (window.Swal && typeof window.Swal.fire === "function") {
            const result = await window.Swal.fire({
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
                customClass: { popup: "km-swal-modal" }
            });

            if (!result.isConfirmed) return;
        } else {
            if (!confirm(`Bạn có chắc chắn muốn xóa vĩnh viễn hộp thư ${email}?`)) {
                return;
            }
        }

        this.setAddressLoading(true);

        try {
            const { ok, data } = await this.api.deleteEmail(email);
            if (!ok || !data?.success) {
                throw new Error(data?.message || data?.error || "Không thể xóa email");
            }

            if (this.state.currentEmail === email) {
                localStorage.removeItem(this.storageKey);
                this.resetToEmptyMailbox();
            }

            this.toast("Đã xóa email thành công", "success");
        } catch (err) {
            this.toast(err?.message || "Lỗi khi xóa email", "error");
        } finally {
            this.setAddressLoading(false);
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

        this.notifyNewMessage(list);
    }

    notifyNewMessage(messages) {
        const list = Array.isArray(messages) ? messages : [];
        if (list.length === 0) return;

        const firstMsg = list[0];
        const sender = this.getDisplayName(firstMsg) || "Email mới";

        if (!this.originalTitle) this.originalTitle = document.title;
        document.title = `(1) Thư mới! - ${this.originalTitle}`;

        this.playNotificationSound();

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
                customClass: { popup: "km-toast km-mail-toast" },
                didOpen: (toastEl) => {
                    toastEl.addEventListener("click", () => {
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
        if (this.loadingState) {
            this.loadingState.classList.add("hidden");
        }
    }

    showCopyButton(show) {
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
        toast(message, type);
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

    switchMode(mode, pushRoute = true) {
        this.state.currentMode = mode;
        localStorage.setItem("kaimail_mode", mode);

        if (this.modeTabs) {
            this.modeTabs.forEach(tab => {
                const tabMode = tab.getAttribute("data-mode");
                const isActive = tabMode === mode;
                tab.classList.toggle("active", isActive);
                tab.setAttribute("aria-selected", isActive ? "true" : "false");
            });
        }

        if (mode === "mail") {
            document.title = "Dịch vụ Temp Mail Free | KaiHub";
            this.originalTitle = document.title;
            if (this.userMain) this.userMain.classList.remove("hidden");
            if (this.mailModeContent) this.mailModeContent.classList.remove("hidden");
            if (this.twofaModeContent) this.twofaModeContent.classList.add("hidden");
            if (this.qrModeContent) this.qrModeContent.classList.add("hidden");
            if (this.docsModeContent) this.docsModeContent.classList.add("hidden");

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
            if (this.qrController) this.qrController.stop();
            if (pushRoute && this.router) {
                this.router.navigate("mail", this.state.currentEmail || "");
            }
        } else if (mode === "twofa") {
            document.title = "Trình Xác Thực 2FA (TOTP) Online Miễn Phí | KaiMail";
            this.originalTitle = document.title;
            if (this.userMain) this.userMain.classList.remove("hidden");
            if (this.mailModeContent) this.mailModeContent.classList.add("hidden");
            if (this.twofaModeContent) this.twofaModeContent.classList.remove("hidden");
            if (this.qrModeContent) this.qrModeContent.classList.add("hidden");
            if (this.docsModeContent) this.docsModeContent.classList.add("hidden");
            this.stopPolling();
            if (this.twofaController) this.twofaController.start();
            if (this.qrController) this.qrController.stop();
            if (pushRoute && this.router) {
                this.router.navigate("twofa");
            }
        } else if (mode === "qr") {
            document.title = "Tạo Mã QR Code Online Miễn Phí | KaiMail";
            this.originalTitle = document.title;
            if (this.userMain) this.userMain.classList.remove("hidden");
            if (this.mailModeContent) this.mailModeContent.classList.add("hidden");
            if (this.twofaModeContent) this.twofaModeContent.classList.add("hidden");
            if (this.qrModeContent) this.qrModeContent.classList.remove("hidden");
            if (this.docsModeContent) this.docsModeContent.classList.add("hidden");
            this.stopPolling();
            if (this.twofaController) this.twofaController.stop();
            if (this.qrController) this.qrController.start();
            if (pushRoute && this.router) {
                this.router.navigate("qr");
            }
        } else if (mode === "docs") {
            document.title = "Tài Liệu Tích Hợp API - KaiMail | Temp Mail Service";
            this.originalTitle = document.title;
            if (this.userMain) this.userMain.classList.add("hidden");
            if (this.mailModeContent) this.mailModeContent.classList.add("hidden");
            if (this.twofaModeContent) this.twofaModeContent.classList.add("hidden");
            if (this.qrModeContent) this.qrModeContent.classList.add("hidden");
            if (this.docsModeContent) this.docsModeContent.classList.remove("hidden");
            this.stopPolling();
            if (this.twofaController) this.twofaController.stop();
            if (this.qrController) this.qrController.stop();
            this.initDocsInteractivity();
            if (pushRoute && this.router) {
                this.router.navigate("docs");
            }
        }
    }

    initDocsInteractivity() {
        if (this.docsInitialized || !this.docsModeContent) return;
        this.docsInitialized = true;

        const tabButtons = this.docsModeContent.querySelectorAll(".lang-tab-btn");
        const tabPanels = this.docsModeContent.querySelectorAll(".lang-panel");

        tabButtons.forEach(btn => {
            btn.addEventListener("click", () => {
                const lang = btn.getAttribute("data-lang");
                tabButtons.forEach(b => b.classList.remove("active"));
                tabPanels.forEach(p => p.classList.remove("active"));

                btn.classList.add("active");
                const targetPanel = document.getElementById(`lang-${lang}`);
                if (targetPanel) {
                    targetPanel.classList.add("active");
                }
            });
        });

        this.docsModeContent.querySelectorAll(".btn-copy-code").forEach(btn => {
            btn.addEventListener("click", async () => {
                const targetId = btn.getAttribute("data-copy-target");
                const targetEl = document.getElementById(targetId);
                if (!targetEl) return;

                try {
                    await navigator.clipboard.writeText(targetEl.textContent.trim());
                    const originalText = btn.textContent;
                    btn.textContent = "Đã chép!";
                    btn.style.background = "#059669";
                    setTimeout(() => {
                        btn.textContent = originalText;
                        btn.style.background = "";
                    }, 2000);
                } catch (err) {
                    console.error("Copy failed:", err);
                }
            });
        });

        const mobileBtn = document.getElementById("mobileTocBtn");
        const sidebar = document.getElementById("docsSidebar");
        const overlay = document.getElementById("sidebarOverlay");

        const toggleSidebar = (show) => {
            if (sidebar) sidebar.classList.toggle("show", show);
            if (overlay) overlay.classList.toggle("show", show);
        };

        if (mobileBtn && sidebar && overlay) {
            mobileBtn.addEventListener("click", () => toggleSidebar(true));
            overlay.addEventListener("click", () => toggleSidebar(false));

            sidebar.querySelectorAll(".sidebar-link").forEach(link => {
                link.addEventListener("click", () => {
                    if (window.innerWidth <= 992) {
                        toggleSidebar(false);
                    }
                });
            });
        }

        const sections = this.docsModeContent.querySelectorAll(".docs-section, .docs-hero");
        const navLinks = this.docsModeContent.querySelectorAll(".sidebar-link");

        if (sections.length > 0 && navLinks.length > 0 && "IntersectionObserver" in window) {
            const observer = new IntersectionObserver((entries) => {
                entries.forEach(entry => {
                    if (entry.isIntersecting) {
                        const id = entry.target.getAttribute("id");
                        navLinks.forEach(link => {
                            const href = link.getAttribute("href");
                            if (href === `#${id}`) {
                                link.classList.add("active");
                            } else {
                                link.classList.remove("active");
                            }
                        });
                    }
                });
            }, { rootMargin: "-20% 0px -70% 0px" });

            sections.forEach(sec => observer.observe(sec));
        }
    }

    checkSpam(key) {
        return true;
    }

    checkCooldown(key, seconds) {
        return true;
    }

    updateButtonCooldownUi(key, seconds) {
        // No-op
    }

    copyTwofaOtp() {
        if (this.twofaController) {
            this.twofaController.copyOtp();
        }
    }

    openCustomEmailModal() {
        if (this.modals) this.modals.openCustomEmailModal();
    }

    openAddDomainModal(initialDomain = "") {
        if (this.modals) this.modals.openAddDomainModal(initialDomain);
    }

    showMailQrModal(email) {
        if (this.modals) this.modals.showMailQrModal(email || this.state.currentEmail);
    }
}
