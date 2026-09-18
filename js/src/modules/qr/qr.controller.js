/**
 * KaiMail QR Controller - Quản lý chức năng Tạo Mã QR Code
 * Tương ứng với includes/views/qr_view.php
 */

import { toast as defaultToast } from "../../core/toast.js";

export class KaiMailQrController {
    constructor({ toast, baseUrl } = {}) {
        this.toast = typeof toast === "function" ? toast : defaultToast;
        this.baseUrl = baseUrl || "";
        this.qrCodeInstance = null;
        this.debounceTimer = null;
        this.bindDom();
    }

    bindDom() {
        this.qrInput = document.getElementById("qrInput");
        this.qrClearBtn = document.getElementById("qrClearBtn");
        this.generateQrBtn = document.getElementById("generateQrBtn");
        this.qrCanvasContainer = document.getElementById("qrCanvasContainer");
        this.qrPlaceholder = document.getElementById("qrPlaceholder");
        this.qrResultContainer = document.getElementById("qrResultContainer");
        this.qrDownloadBtn = document.getElementById("qrDownloadBtn");
        this.qrCopyBtn = document.getElementById("qrCopyBtn");
        this.qrStageWrapper = document.getElementById("qrStageWrapper");

        this.qrPresetTextInput = document.getElementById("qrPresetTextInput");
        this.qrPresetCopyBtn = document.getElementById("qrPresetCopyBtn");
        this.qrPresetSaveAdminBtn = document.getElementById("qrPresetSaveAdminBtn");
    }

    init() {
        this.bindEvents();
        this.renderQr("");
        this.loadPresets();
    }

    bindEvents() {
        if (this.qrInput) {
            // Typing controls clear button visibility
            this.qrInput.addEventListener("input", () => {
                const val = this.qrInput.value.trim();
                if (this.qrClearBtn) {
                    this.qrClearBtn.style.display = val !== "" ? "inline-flex" : "none";
                }
                if (val === "") {
                    this.renderQr("");
                }
            });

            // Enter key triggers generation
            this.qrInput.addEventListener("keydown", (e) => {
                if (e.key === "Enter") {
                    e.preventDefault();
                    this.handleGenerate();
                }
            });
        }

        if (this.qrClearBtn) {
            this.qrClearBtn.addEventListener("click", () => {
                if (this.qrInput) {
                    this.qrInput.value = "";
                    this.qrClearBtn.style.display = "none";
                    this.renderQr("");
                }
            });
        }

        // Clicking "Tạo QR" button triggers generation
        if (this.generateQrBtn) {
            this.generateQrBtn.addEventListener("click", () => {
                this.handleGenerate();
            });
        }

        if (this.qrDownloadBtn) {
            this.qrDownloadBtn.addEventListener("click", () => this.downloadPng());
        }

        if (this.qrCopyBtn) {
            this.qrCopyBtn.addEventListener("click", () => {
                const val = this.qrInput ? this.qrInput.value.trim() : "";
                if (!val) return;
                this.copyTextToClipboard(val);
                this.showCopiedState(this.qrCopyBtn);
                this.toast("Đã sao chép nội dung!", "success");
            });
        }

        if (this.qrStageWrapper) {
            this.qrStageWrapper.addEventListener("click", () => {
                const val = this.qrInput ? this.qrInput.value.trim() : "";
                if (val) {
                    this.downloadPng();
                }
            });
        }

        const bindPresetItemActions = (container) => {
            const root = container || document.getElementById("qrPresetLinesList");
            if (!root) return;

            root.querySelectorAll(".btn-preset-copy-item").forEach((btn) => {
                btn.addEventListener("click", async (e) => {
                    e.preventDefault();
                    e.stopPropagation();
                    const val = btn.getAttribute("data-text") || "";
                    if (!val) return;
                    await this.copyTextToClipboard(val);
                    this.showCopiedState(btn);
                    const label = btn.querySelector(".btn-copy-label");
                    if (label) {
                        const oldText = label.textContent;
                        label.textContent = "Đã copy!";
                        setTimeout(() => { label.textContent = oldText; }, 1800);
                    }
                    this.toast("Đã sao chép mã!", "success");
                });
            });

            root.querySelectorAll(".btn-preset-use-item").forEach((btn) => {
                btn.addEventListener("click", (e) => {
                    e.preventDefault();
                    e.stopPropagation();
                    const val = btn.getAttribute("data-text") || "";
                    if (!val) return;
                    if (this.qrInput) {
                        this.qrInput.value = val;
                        if (this.qrClearBtn) this.qrClearBtn.style.display = "inline-flex";
                        this.handleGenerate();
                        this.toast("Đã tạo mã QR cho mã này!", "success");
                        if (this.qrResultContainer) {
                            this.qrResultContainer.scrollIntoView({ behavior: "smooth", block: "nearest" });
                        }
                    }
                });
            });
        };

        const updatePresetUi = (text) => {
            const raw = String(text || "");
            const lines = raw.split(/\r\n|\n|\r/).map((l) => l.trim()).filter((l) => l.length > 0);

            const userShell = document.getElementById("qrUserPresetShell");
            const countBadge = document.getElementById("qrPresetCountBadge");
            const listContainer = document.getElementById("qrPresetLinesList");

            if (userShell) {
                userShell.style.display = lines.length > 0 ? "block" : "none";
            }
            if (countBadge) {
                countBadge.textContent = String(lines.length);
            }

            if (listContainer) {
                if (lines.length === 0) {
                    listContainer.innerHTML = "";
                    return;
                }

                listContainer.innerHTML = lines.map((line, idx) => {
                    const isLink = this.isUrl(line);
                    const linkHref = isLink ? this.toHref(line) : "";
                    const textEsc = this.escapeHtml(line);
                    const hrefEsc = this.escapeHtml(linkHref);

                    const openBtnHtml = isLink ? `
                        <a href="${hrefEsc}" target="_blank" rel="noopener noreferrer" class="btn-preset-action btn-preset-open" title="Mở liên kết">
                            <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"></path>
                                <polyline points="15 3 21 3 21 9"></polyline>
                                <line x1="10" y1="14" x2="21" y2="3"></line>
                            </svg>
                            <span>Mở link</span>
                        </a>
                    ` : "";

                    const contentHtml = isLink ? `
                        <a href="${hrefEsc}" target="_blank" rel="noopener noreferrer" class="qr-preset-item-link">
                            <span>${textEsc}</span>
                            <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"></path>
                                <polyline points="15 3 21 3 21 9"></polyline>
                                <line x1="10" y1="14" x2="21" y2="3"></line>
                            </svg>
                        </a>
                    ` : textEsc;

                    return `
                        <div class="qr-preset-item-card" data-index="${idx}">
                            <div class="qr-preset-item-info">
                                <span class="qr-preset-item-badge">${idx + 1}</span>
                                <div class="qr-preset-item-text">${contentHtml}</div>
                            </div>
                            <div class="qr-preset-item-actions">
                                ${openBtnHtml}
                                <button type="button" class="btn-preset-action btn-preset-copy-item" data-text="${textEsc}" title="Sao chép">
                                    <svg class="copy-icon" width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                        <rect x="9" y="9" width="13" height="13" rx="2" ry="2"/>
                                        <path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"/>
                                    </svg>
                                    <svg class="check-icon hidden" width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="#10b981" stroke-width="2.5">
                                        <polyline points="20 6 9 17 4 12" />
                                    </svg>
                                    <span class="btn-copy-label">Copy</span>
                                </button>
                                <button type="button" class="btn-preset-action btn-preset-qr btn-preset-use-item" data-text="${textEsc}" title="Tạo mã QR cho mã này">
                                    <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                        <rect x="3" y="3" width="7" height="7" rx="1"/>
                                        <rect x="14" y="3" width="7" height="7" rx="1"/>
                                        <rect x="14" y="14" width="7" height="7" rx="1"/>
                                        <rect x="3" y="14" width="7" height="7" rx="1"/>
                                    </svg>
                                    <span>Tạo QR</span>
                                </button>
                            </div>
                        </div>
                    `;
                }).join("");

                bindPresetItemActions(listContainer);
            }
        };

        // Initialize actions for already-rendered lines from PHP
        bindPresetItemActions();

        const saveBtn = this.qrPresetSaveAdminBtn || document.getElementById("qrPresetSaveAdminBtn");
        if (saveBtn) {
            saveBtn.addEventListener("click", async (e) => {
                e.preventDefault();
                const input = this.qrPresetTextInput || document.getElementById("qrPresetTextInput");
                const val = input ? input.value.trim() : "";
                const origHtml = saveBtn.innerHTML;

                saveBtn.disabled = true;
                saveBtn.innerHTML = `<span>Đang lưu...</span>`;

                try {
                    const res = await fetch((this.baseUrl || "") + "/api/admin/qr-presets.php", {
                        method: "POST",
                        headers: { "Content-Type": "application/json" },
                        body: JSON.stringify({ action: "save_qr_text", qr_text: val })
                    }).then((r) => r.json());

                    if (res && res.success) {
                        this.toast("Admin: Đã lưu danh sách mã thành công!", "success");
                        updatePresetUi(val);
                        saveBtn.innerHTML = `<span>Đã lưu!</span>`;
                        setTimeout(() => {
                            saveBtn.innerHTML = origHtml;
                        }, 1500);
                    } else {
                        this.toast(res.message || "Không thể lưu mã", "error");
                        saveBtn.innerHTML = origHtml;
                    }
                } catch (e) {
                    this.toast("Lỗi kết nối máy chủ", "error");
                    saveBtn.innerHTML = origHtml;
                } finally {
                    saveBtn.disabled = false;
                }
            });
        }
    }

    handleGenerate() {
        const val = this.qrInput ? this.qrInput.value.trim() : "";
        if (!val) {
            this.toast("Vui lòng nhập nội dung để tạo mã QR", "error");
            return;
        }

        // Render QR with pure input string
        this.renderQr(val);
    }

    showCopiedState(btn) {
        if (!btn) return;
        btn.classList.add("copied");
        const copyIcon = btn.querySelector(".copy-icon");
        const checkIcon = btn.querySelector(".check-icon");
        if (copyIcon && checkIcon) {
            copyIcon.classList.add("hidden");
            checkIcon.classList.remove("hidden");
            setTimeout(() => {
                btn.classList.remove("copied");
                copyIcon.classList.remove("hidden");
                checkIcon.classList.add("hidden");
            }, 1800);
        }
    }

    renderQr(text) {
        const clean = String(text || "").trim();

        if (!clean) {
            if (this.qrPlaceholder) this.qrPlaceholder.style.display = "flex";
            if (this.qrResultContainer) this.qrResultContainer.style.display = "none";
            if (this.qrDownloadBtn) {
                this.qrDownloadBtn.classList.add("is-disabled");
                this.qrDownloadBtn.disabled = true;
            }
            if (this.qrCopyBtn) {
                this.qrCopyBtn.classList.add("is-disabled");
                this.qrCopyBtn.disabled = true;
            }
            if (this.qrCanvasContainer) this.qrCanvasContainer.innerHTML = "";
            return;
        }

        if (this.qrPlaceholder) this.qrPlaceholder.style.display = "none";
        if (this.qrResultContainer) this.qrResultContainer.style.display = "flex";
        if (this.qrDownloadBtn) {
            this.qrDownloadBtn.classList.remove("is-disabled");
            this.qrDownloadBtn.disabled = false;
        }
        if (this.qrCopyBtn) {
            this.qrCopyBtn.classList.remove("is-disabled");
            this.qrCopyBtn.disabled = false;
        }

        if (this.qrCanvasContainer) {
            this.qrCanvasContainer.innerHTML = "";
            try {
                if (typeof QRCode !== "undefined") {
                    this.qrCodeInstance = new QRCode(this.qrCanvasContainer, {
                        text: clean,
                        width: 220,
                        height: 220,
                        colorDark: "#0f172a",
                        colorLight: "#ffffff",
                        correctLevel: QRCode.CorrectLevel.L
                    });
                }
            } catch (err) {
                console.error("QRCode generation error:", err);
                if (String(err).includes("overflow")) {
                    this.toast("Nội dung quá dài (vượt quá dung lượng mã QR). Vui lòng rút ngắn văn bản.", "error");
                } else {
                    this.toast("Lỗi tạo mã QR", "error");
                }
            }
        }
    }

    copyTextToClipboard(text) {
        if (!text) return;
        if (navigator.clipboard && navigator.clipboard.writeText) {
            navigator.clipboard.writeText(text).catch(() => {
                this.fallbackCopyText(text);
            });
        } else {
            this.fallbackCopyText(text);
        }
    }

    fallbackCopyText(text) {
        const ta = document.createElement("textarea");
        ta.value = text;
        ta.style.position = "fixed";
        ta.style.left = "-9999px";
        document.body.appendChild(ta);
        ta.focus();
        ta.select();
        try { document.execCommand("copy"); } catch {}
        document.body.removeChild(ta);
    }

    downloadPng() {
        if (!this.qrCanvasContainer) return;
        const canvas = this.qrCanvasContainer.querySelector("canvas");
        const img = this.qrCanvasContainer.querySelector("img");
        let dataUrl = "";
        if (canvas) {
            dataUrl = canvas.toDataURL("image/png");
        } else if (img && img.src) {
            dataUrl = img.src;
        }

        if (!dataUrl) {
            this.toast("Chưa có mã QR để tải về", "error");
            return;
        }

        const a = document.createElement("a");
        a.href = dataUrl;
        a.download = `qrcode-${Date.now()}.png`;
        document.body.appendChild(a);
        a.click();
        document.body.removeChild(a);
        this.toast("Đã tải ảnh mã QR thành công!", "success");
    }

    escapeHtml(str) {
        return String(str || "")
            .replace(/&/g, "&amp;")
            .replace(/</g, "&lt;")
            .replace(/>/g, "&gt;")
            .replace(/"/g, "&quot;")
            .replace(/'/g, "&#039;");
    }

    isUrl(str) {
        if (!str) return false;
        const trimmed = String(str).trim();
        if (/^https?:\/\//i.test(trimmed)) return true;
        if (/^www\.[a-z0-9\-]+(\.[a-z0-9\-]+)+/i.test(trimmed)) return true;
        return false;
    }

    toHref(str) {
        const trimmed = String(str || "").trim();
        if (/^https?:\/\//i.test(trimmed)) return trimmed;
        if (/^www\./i.test(trimmed)) return "https://" + trimmed;
        return trimmed;
    }

    async loadPresets() {
        const section = document.getElementById("qrPresetsSection");
        const listContainer = document.getElementById("qrPresetsList");
        if (!section || !listContainer) return;

        try {
            const apiPath = (this.baseUrl || "") + "/api/qr-presets";
            const res = await fetch(apiPath).then((r) => r.json());
            if (res && res.success && Array.isArray(res.presets) && res.presets.length > 0) {
                section.style.display = "block";
                listContainer.innerHTML = res.presets.map((preset) => {
                    const titleEsc = this.escapeHtml(preset.title || "");
                    const catEsc = this.escapeHtml(preset.category || "Gợi ý");
                    const contentEsc = this.escapeHtml(preset.content || "");

                    return `
                        <div class="qr-preset-card">
                            <div class="qr-preset-top">
                                <span class="qr-preset-badge">${catEsc}</span>
                            </div>
                            <div class="qr-preset-title-text">${titleEsc}</div>
                            <div class="qr-preset-content-text" title="${contentEsc}">${contentEsc}</div>
                            <div class="qr-preset-actions">
                                <button type="button" class="btn-preset-copy" data-content="${contentEsc}">
                                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                        <rect x="9" y="9" width="13" height="13" rx="2" ry="2"/>
                                        <path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"/>
                                    </svg>
                                    <span>Copy</span>
                                </button>
                                <button type="button" class="btn-preset-use" data-content="${contentEsc}">
                                    <span>Tạo QR</span>
                                </button>
                            </div>
                        </div>
                    `;
                }).join("");

                listContainer.querySelectorAll(".btn-preset-copy").forEach((btn) => {
                    btn.addEventListener("click", (e) => {
                        e.stopPropagation();
                        const val = btn.getAttribute("data-content") || "";
                        if (val) {
                            this.copyTextToClipboard(val);
                            const span = btn.querySelector("span");
                            if (span) {
                                const oldText = span.textContent;
                                span.textContent = "Đã copy!";
                                setTimeout(() => { span.textContent = oldText; }, 1500);
                            }
                            this.toast("Đã sao chép nội dung!", "success");
                        }
                    });
                });

                listContainer.querySelectorAll(".btn-preset-use").forEach((btn) => {
                    btn.addEventListener("click", () => {
                        const val = btn.getAttribute("data-content") || "";
                        if (val && this.qrInput) {
                            this.qrInput.value = val;
                            if (this.qrClearBtn) this.qrClearBtn.style.display = "inline-flex";
                            this.handleGenerate();
                        }
                    });
                });
            } else {
                section.style.display = "none";
            }
        } catch (e) {
            console.error("Failed to load QR presets:", e);
            section.style.display = "none";
        }
    }

    start() {
    }

    stop() {
    }
}
