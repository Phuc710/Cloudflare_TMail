/**
 * KaiMail Admin Dashboard page logic (OOP).
 */
class AdminDashboardPage {
    constructor(core) {
        this.core = core;
        this.currentPage = 1;
        this.selectedIds = new Set();
        this.emailAddressMap = new Map();
        this.currentSource = "all";
        this.lastCheckTime = "";
        this.pollingActive = false;
        this.pollTimer = null;
        this.currentMessageEmailId = 0;
        this.currentMessageEmail = "";
        this.searchDebounceTimer = null;
    }

    init() {
        if (!document.getElementById("emailsTableBody")) {
            return;
        }

        this.bindEvents();
        this.bootstrap();
    }

    async bootstrap() {
        await this.loadInitialData();
        if (!this.lastCheckTime) {
            this.lastCheckTime = this.buildCurrentTimestamp();
        }
        this.startPolling();
    }

    bindEvents() {
        const searchInput = document.getElementById("searchInput");
        const domainFilter = document.getElementById("domainFilter");
        const expiryFilter = document.getElementById("expiryFilter");
        const selectAll = document.getElementById("selectAll");
        const deleteSelectedBtn = document.getElementById("deleteSelectedBtn");
        const copySelectedBtn = document.getElementById("copySelectedBtn");
        const emailsTableBody = document.getElementById("emailsTableBody");
        const messagesModalBody = document.getElementById("messagesModalBody");
        const updateNoteForm = document.getElementById("updateNoteForm");

        // Source tabs: Tất cả / Admin Mail / Khách tạo
        document.querySelectorAll(".source-tab").forEach((tab) => {
            tab.addEventListener("click", () => {
                const source = tab.getAttribute("data-source") || "all";
                if (this.currentSource === source) return;
                this.currentSource = source;

                document.querySelectorAll(".source-tab").forEach((t) => {
                    const isActive = t === tab;
                    t.classList.toggle("active", isActive);
                    t.setAttribute("aria-selected", isActive ? "true" : "false");
                });

                this.currentPage = 1;
                this.loadEmails();
            });
        });

        // Search debounced
        searchInput?.addEventListener("input", () => {
            if (this.searchDebounceTimer) clearTimeout(this.searchDebounceTimer);
            this.searchDebounceTimer = setTimeout(() => {
                this.currentPage = 1;
                this.loadEmails();
            }, 280);
        });

        domainFilter?.addEventListener("change", () => {
            this.currentPage = 1;
            this.loadEmails();
        });

        expiryFilter?.addEventListener("change", () => {
            this.currentPage = 1;
            this.loadEmails();
        });

        // Select All Checkbox
        selectAll?.addEventListener("change", (event) => {
            const checked = Boolean(event.target.checked);
            document.querySelectorAll(".email-checkbox").forEach((checkbox) => {
                checkbox.checked = checked;
                const id = Number(checkbox.getAttribute("data-id") || "0");
                if (!id) return;
                if (checked) this.selectedIds.add(id);
                else this.selectedIds.delete(id);
            });
            this.updateSelectionUi();
        });

        // Bulk buttons
        deleteSelectedBtn?.addEventListener("click", () => this.deleteSelected());
        copySelectedBtn?.addEventListener("click", () => this.bulkCopy());

        // Fast Checker
        const checkerForm = document.getElementById("checkerForm");
        checkerForm?.addEventListener("submit", (e) => this.handleCheckerSubmit(e));

        // Note Modal Form
        updateNoteForm?.addEventListener("submit", (e) => this.handleNoteSubmit(e));

        // Table delegation
        emailsTableBody?.addEventListener("click", (event) => this.handleTableClick(event));
        emailsTableBody?.addEventListener("change", (event) => this.handleTableChange(event));
        messagesModalBody?.addEventListener("click", (event) => this.handleMessagesClick(event));
        document.getElementById("checkerResults")?.addEventListener("click", (event) => this.handleTableClick(event));

        window.addEventListener("beforeunload", () => this.stopPolling());
        document.addEventListener("visibilitychange", () => {
            if (document.hidden) return;
            this.reloadData();
            if (this.pollingActive && this.pollTimer) {
                clearTimeout(this.pollTimer);
                this.pollLoop();
            }
        });
    }

    async loadInitialData() {
        await Promise.all([this.loadStats(), this.loadEmails()]);
    }

    async reloadData() {
        await Promise.all([this.loadStats(true), this.loadEmails(true)]);
    }

    setLoading(show) {
        const loading = document.getElementById("loadingState");
        if (loading) loading.classList.toggle("hidden", !show);
    }

    async loadStats(silent = false) {
        try {
            const { ok, data, status } = await this.core.fetchJson("/api/admin/stats.php");
            if (!ok || !data) {
                if (!silent) {
                    const message = data?.error || data?.message || `Không thể tải thống kê (HTTP ${status || 0})`;
                    this.core.showToast(message, "error");
                }
                return;
            }

            this.setStatValue("statTotalEmails", data.total_emails);
            this.setStatValue("statAdminEmails", data.admin_emails);
            this.setStatValue("statApiEmails", data.api_emails);
            this.setStatValue("statUserEmails", data.user_emails);
            this.setStatValue("statTotalMessages", data.total_messages);

            // Tab badge counts
            this.setStatValue("tabCountAll", data.total_emails);
            this.setStatValue("tabCountAdmin", data.admin_emails);
            this.setStatValue("tabCountApi", data.api_emails);
            this.setStatValue("tabCountUser", data.user_emails);

            if (data.server_time) {
                this.lastCheckTime = String(data.server_time);
            }
        } catch (error) {
            if (!silent) this.core.showToast("Không thể tải thống kê", "error");
        }
    }

    setStatValue(elementId, value) {
        const element = document.getElementById(elementId);
        if (!element) return;
        element.textContent = Number(value || 0);
    }

    async loadEmails(silent = false) {
        const search = String(document.getElementById("searchInput")?.value || "").trim();
        const domain = String(document.getElementById("domainFilter")?.value || "");
        const expiry = String(document.getElementById("expiryFilter")?.value || "");

        if (!silent) this.setLoading(true);

        try {
            const params = new URLSearchParams({
                page: String(this.currentPage),
                limit: "13",
            });

            if (domain) params.set("domain", domain);
            if (search) params.set("search", search);
            if (expiry === "no_message") params.set("no_message", "1");
            if (this.currentSource && this.currentSource !== "all") {
                params.set("created_by", this.currentSource);
            }

            const { ok, data } = await this.core.fetchJson(`/api/admin/emails.php?${params.toString()}`);
            if (!ok || !data) {
                this.renderEmails([]);
                this.renderPagination({ pages: 0 });
                if (!silent) this.core.showToast("Không thể tải danh sách email", "error");
                return;
            }

            if (data.server_time) {
                this.lastCheckTime = String(data.server_time);
            }

            this.renderEmails(Array.isArray(data.emails) ? data.emails : []);
            this.renderPagination(data);
        } catch (error) {
            if (!silent) this.core.showToast("Không thể tải danh sách email", "error");
        } finally {
            if (!silent) this.setLoading(false);
        }
    }

    renderEmails(emails) {
        const tbody = document.getElementById("emailsTableBody");
        if (!tbody) return;

              if (!emails.length) {
            tbody.innerHTML = `
                <tr>
                    <td colspan="6" class="empty-row">Không có email nào phù hợp điều kiện lọc</td>
                </tr>
            `;
            this.selectedIds.clear();
            this.updateSelectionUi();
            return;
        }

        const rowsHtml = emails.map((email) => {
            const id = Number(email.id || 0);
            const emailAddress = String(email.email || "");
            const emailEncoded = encodeURIComponent(emailAddress);
            this.emailAddressMap.set(id, emailAddress);

            const checked = this.selectedIds.has(id) ? "checked" : "";
            const createdBy = String(email.created_by || "user").toLowerCase();
            const note = String(email.note || "").trim();
            const unreadCount = Number(email.unread_count || 0);
            const createdAt = email.created_at ? this.core.formatDateTimeVN(email.created_at) : "-";
            const messageCount = Number(email.message_count || 0);

            // Source Badge
            let sourceBadge = "";
            if (createdBy === "admin") {
                sourceBadge = `<span class="badge-source badge-admin" title="Admin tạo trực tiếp">Admin</span>`;
            } else if (createdBy === "api") {
                sourceBadge = `<span class="badge-source badge-api" title="Hệ thống / API tạo">API</span>`;
            } else {
                sourceBadge = `<span class="badge-source badge-user" title="Khách tạo tự động">Khách</span>`;
            }

            // Note Display
            let noteHtml = "";
            if (note) {
                noteHtml = `
                    <div class="note-cell" id="noteCell-${id}">
                        <span class="note-badge-display" data-action="edit-note" data-id="${id}" data-email="${emailEncoded}" data-note="${this.core.escapeHtml(note)}" title="Nhấp để sửa ghi chú">
                            <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M12 20h9"></path>
                                <path d="M16.5 3.5a2.121 2.121 0 0 1 3 3L7 19l-4 1 1-4L16.5 3.5z"></path>
                            </svg>
                            <span class="note-text">${this.core.escapeHtml(note)}</span>
                        </span>
                    </div>
                `;
            } else {
                noteHtml = `
                    <div class="note-cell" id="noteCell-${id}">
                        <button type="button" class="btn-add-note" data-action="edit-note" data-id="${id}" data-email="${emailEncoded}" data-note="" title="Thêm ghi chú cho email này">
                            <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <line x1="12" y1="5" x2="12" y2="19"></line>
                                <line x1="5" y1="12" x2="19" y2="12"></line>
                            </svg>
                            <span>Ghi chú</span>
                        </button>
                    </div>
                `;
            }

            return `
                <tr id="emailRow-${id}">
                    <td class="col-check">
                        <input type="checkbox" class="email-checkbox" data-id="${id}" ${checked}>
                    </td>
                    
                    <td class="col-email">
                        <div class="email-cell">
                            <span class="email-address">${this.core.escapeHtml(emailAddress)}</span>
                            ${sourceBadge}
                            <button class="btn-icon" data-action="copy-email" data-email="${emailEncoded}" title="Sao chép email">
                                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <rect x="9" y="9" width="13" height="13" rx="2" ry="2"></rect>
                                    <path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"></path>
                                </svg>
                            </button>
                        </div>
                    </td>

                    <td class="col-note">
                        ${noteHtml}
                    </td>

                    <td class="col-messages" style="text-align: center;">
                        <button class="btn-link" data-action="view-messages" data-email-id="${id}" data-email="${emailEncoded}">
                            ${messageCount}
                            ${unreadCount > 0 ? `<span class="unread-badge">${unreadCount}</span>` : ""}
                        </button>
                    </td>

                    <td class="col-date" style="text-align: center;">${this.core.escapeHtml(createdAt)}</td>

                    <td class="col-actions" style="text-align: center;">
                        <div class="row-actions-group">
                            <button class="btn-icon" data-action="view-messages" data-email-id="${id}" data-email="${emailEncoded}" title="Xem tin nhắn">
                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8z"></path>
                                    <circle cx="12" cy="12" r="3"></circle>
                                </svg>
                            </button>
                            <button class="btn-icon danger" data-action="delete-email" data-email-id="${id}" title="Xóa email">
                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <polyline points="3 6 5 6 21 6"></polyline>
                                    <path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path>
                                </svg>
                            </button>
                        </div>
                    </td>
                </tr>
            `;
        }).join("");

        tbody.innerHTML = rowsHtml;
        this.updateSelectionUi();
    }

    renderPagination(data) {
        const pagination = document.getElementById("pagination");
        if (!pagination) return;

        const pages = Number(data.pages || 0);
        if (pages <= 1) {
            pagination.innerHTML = "";
            return;
        }

        let html = "";
        
        // Prev button (<)
        if (this.currentPage > 1) {
            html += `
                <button type="button" class="btn-page-nav" data-page="${this.currentPage - 1}" title="Trang trước" aria-label="Trang trước">
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

        for (let i = 1; i <= pages; i += 1) {
            if (i === 1 || i === pages || (i >= this.currentPage - 2 && i <= this.currentPage + 2)) {
                const activeClass = i === this.currentPage ? "active" : "";
                html += `<button type="button" class="btn-page-num ${activeClass}" data-page="${i}">${i}</button>`;
            } else if (i === this.currentPage - 3 || i === this.currentPage + 3) {
                html += `<button type="button" class="btn-page-ellipsis" disabled>...</button>`;
            }
        }

        // Next button (>)
        if (this.currentPage < pages) {
            html += `
                <button type="button" class="btn-page-nav" data-page="${this.currentPage + 1}" title="Trang sau" aria-label="Trang sau">
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

        pagination.innerHTML = html;
        pagination.querySelectorAll("button[data-page]").forEach((button) => {
            button.addEventListener("click", () => {
                this.currentPage = Number(button.getAttribute("data-page") || "1");
                this.loadEmails();
            });
        });
    }

    handleTableClick(event) {
        const actionEl = event.target.closest("[data-action]");
        if (!actionEl) return;

        const action = actionEl.getAttribute("data-action");
        if (action === "copy-email") {
            const email = decodeURIComponent(actionEl.getAttribute("data-email") || "");
            this.core.copyToClipboard(email);
            return;
        }

        if (action === "edit-note") {
            const id = Number(actionEl.getAttribute("data-id") || "0");
            const email = decodeURIComponent(actionEl.getAttribute("data-email") || "");
            const note = actionEl.getAttribute("data-note") || "";
            this.openNoteModal(id, email, note);
            return;
        }

        if (action === "view-messages") {
            const emailId = Number(actionEl.getAttribute("data-email-id") || "0");
            const emailAddress = decodeURIComponent(actionEl.getAttribute("data-email") || "");
            this.viewMessages(emailId, emailAddress);
            return;
        }

        if (action === "delete-email") {
            const emailId = Number(actionEl.getAttribute("data-email-id") || "0");
            this.deleteEmail(emailId);
        }
    }

    handleTableChange(event) {
        const checkbox = event.target.closest(".email-checkbox");
        if (!checkbox) return;

        const id = Number(checkbox.getAttribute("data-id") || "0");
        if (!id) return;

        if (checkbox.checked) this.selectedIds.add(id);
        else this.selectedIds.delete(id);

        this.updateSelectionUi();
    }

    updateSelectionUi() {
        const deleteBtn = document.getElementById("deleteSelectedBtn");
        const copyBtn = document.getElementById("copySelectedBtn");
        const countSpan = document.getElementById("selectedDeleteCount");
        const selectAll = document.getElementById("selectAll");
        const checkboxes = Array.from(document.querySelectorAll(".email-checkbox"));
        const totalCheckboxes = checkboxes.length;
        const checkedCount = checkboxes.filter((box) => box.checked).length;

        const hasSelection = this.selectedIds.size > 0;

        if (deleteBtn) {
            deleteBtn.classList.toggle("hidden", !hasSelection);
        }
        if (copyBtn) {
            copyBtn.classList.toggle("hidden", !hasSelection);
        }
        if (countSpan) {
            countSpan.textContent = String(this.selectedIds.size);
        }

        if (selectAll) {
            selectAll.checked = totalCheckboxes > 0 && checkedCount === totalCheckboxes;
        }
    }

    openNoteModal(id, email, note) {
        const idInput = document.getElementById("noteEmailId");
        const targetLabel = document.getElementById("noteEmailTarget");
        const noteText = document.getElementById("noteInputText");

        if (idInput) idInput.value = String(id);
        if (targetLabel) targetLabel.textContent = email;
        if (noteText) {
            noteText.value = note || "";
            setTimeout(() => noteText.focus(), 100);
        }

        this.core.openModal("noteModal");
    }

    async handleNoteSubmit(event) {
        event.preventDefault();
        const id = Number(document.getElementById("noteEmailId")?.value || "0");
        const note = String(document.getElementById("noteInputText")?.value || "").trim();

        if (!id) return;

        try {
            const { ok, data } = await this.core.postJson("/api/admin/emails.php", {
                action: "update_note",
                id: id,
                note: note,
            });

            if (!ok) {
                this.core.showToast(data?.error || "Không thể lưu ghi chú", "error");
                return;
            }

            this.core.closeModal("noteModal");
            this.core.showToast("Đã lưu ghi chú", "success");

            // Update DOM cell directly
            const cell = document.getElementById(`noteCell-${id}`);
            const emailEncoded = encodeURIComponent(this.emailAddressMap.get(id) || "");
            if (cell) {
                if (note) {
                    cell.innerHTML = `
                        <span class="note-badge-display" data-action="edit-note" data-id="${id}" data-email="${emailEncoded}" data-note="${this.core.escapeHtml(note)}" title="Nhấp để sửa ghi chú">
                            <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M12 20h9"></path>
                                <path d="M16.5 3.5a2.121 2.121 0 0 1 3 3L7 19l-4 1 1-4L16.5 3.5z"></path>
                            </svg>
                            <span class="note-text">${this.core.escapeHtml(note)}</span>
                        </span>
                    `;
                } else {
                    cell.innerHTML = `
                        <button type="button" class="btn-add-note" data-action="edit-note" data-id="${id}" data-email="${emailEncoded}" data-note="" title="Thêm ghi chú cho email này">
                            <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <line x1="12" y1="5" x2="12" y2="19"></line>
                                <line x1="5" y1="12" x2="19" y2="12"></line>
                            </svg>
                            <span>Ghi chú</span>
                        </button>
                    `;
                }
            }
        } catch (error) {
            this.core.showToast("Lỗi kết nối máy chủ", "error");
        }
    }

    bulkCopy() {
        if (this.selectedIds.size < 1) return;
        const emails = [];
        this.selectedIds.forEach((id) => {
            const address = this.emailAddressMap.get(id);
            if (address) emails.push(address);
        });

        if (emails.length === 0) return;
        this.core.copyToClipboard(emails.join("\n"));
        this.core.showToast(`Đã sao chép ${emails.length} email vào clipboard`, "success");
    }

    async deleteEmail(emailId) {
        if (!emailId) return;
        const confirmed = await this.core.confirmAction({
            title: "Xác nhận xóa email",
            text: "Bạn có chắc muốn xóa email này?",
            confirmButtonText: "Xóa email",
            cancelButtonText: "Hủy",
            icon: "warning",
        });
        if (!confirmed) return;

        try {
            const { ok, data } = await this.core.postJson("/api/admin/emails.php", {
                _method: "DELETE",
                ids: [emailId],
            });

            if (!ok) {
                this.core.showToast(data?.error || "Không thể xóa email", "error");
                return;
            }

            this.selectedIds.delete(emailId);
            this.core.showToast("Đã xóa email", "success");
            await this.reloadData();
        } catch (error) {
            this.core.showToast("Lỗi kết nối máy chủ", "error");
        }
    }

    async deleteSelected() {
        if (this.selectedIds.size < 1) return;
        const confirmed = await this.core.confirmAction({
            title: "Xác nhận xóa email",
            text: `Bạn có chắc muốn xóa ${this.selectedIds.size} email?`,
            confirmButtonText: "Xóa đã chọn",
            cancelButtonText: "Hủy",
            icon: "warning",
        });
        if (!confirmed) return;

        try {
            const ids = Array.from(this.selectedIds);
            const { ok, data } = await this.core.postJson("/api/admin/emails.php", {
                _method: "DELETE",
                ids,
            });

            if (!ok) {
                this.core.showToast(data?.error || "Không thể xóa email", "error");
                return;
            }

            this.selectedIds.clear();
            this.core.showToast(`Đã xóa ${ids.length} email`, "success");
            await this.reloadData();
        } catch (error) {
            this.core.showToast("Lỗi kết nối máy chủ", "error");
        }
    }

    async viewMessages(emailId, emailAddress) {
        if (!emailId) return;
        this.currentMessageEmailId = emailId;
        this.currentMessageEmail = emailAddress;

        const modalTitle = document.getElementById("messagesModalTitle");
        const modalBody = document.getElementById("messagesModalBody");
        modalTitle.textContent = emailAddress;
        modalBody.innerHTML = `
            <div class="loading-state">
                <div class="spinner"></div>
                <span class="loading-text">Đang tải tin nhắn...</span>
            </div>
        `;

        this.core.openModal("messagesModal");

        try {
            const { ok, data } = await this.core.fetchJson(`/api/admin/messages.php?email_id=${emailId}`);
            if (!ok || !data) {
                const errMsg = data?.error || data?.message || "Không thể tải danh sách tin nhắn";
                modalBody.innerHTML = `<div class="error-state">${this.core.escapeHtml(errMsg)}</div>`;
                return;
            }

            const messages = Array.isArray(data.messages) ? data.messages : [];
            if (!messages.length) {
                modalBody.innerHTML = `<div class="empty-state-sm">Email này chưa có tin nhắn nào</div>`;
                return;
            }

            modalBody.innerHTML = `
                <div class="messages-list-admin">
                    ${messages.map((msg) => {
                const id = Number(msg.id || 0);
                const isUnread = Number(msg.is_read || 0) === 0;
                const sender = this.core.escapeHtml(this.core.cleanEmail(msg.from_email, msg.from_name));
                const subject = this.core.escapeHtml(msg.subject || "(Không có tiêu đề)");
                const preview = this.core.escapeHtml(this.buildPreviewText(msg.preview || msg.body_text || ""));
                const time = this.core.escapeHtml(this.core.formatTimeVN(msg.received_at));

                return `
                            <div class="message-item-admin ${isUnread ? "unread" : ""}" data-action="open-message" data-message-id="${id}">
                                <div class="message-info">
                                    <strong>${sender}</strong>
                                    <span>${subject}</span>
                                    <div class="message-preview">${preview}</div>
                                </div>
                                <div class="message-meta-right">
                                    <span>${time}</span>
                                    <button class="btn-icon danger" data-action="delete-message" data-message-id="${id}" data-email-id="${emailId}" title="Xóa tin nhắn">
                                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                            <polyline points="3 6 5 6 21 6"></polyline>
                                            <path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path>
                                        </svg>
                                    </button>
                                </div>
                            </div>
                        `;
            }).join("")}
                </div>
            `;
        } catch (error) {
            modalBody.innerHTML = `<div class="error-state">Không thể tải danh sách tin nhắn</div>`;
        }
    }

    async viewMessage(messageId) {
        if (!messageId) return;

        try {
            const { ok, data } = await this.core.fetchJson(`/api/admin/messages.php?id=${messageId}`);
            if (!ok || !data) {
                this.core.showToast("Không thể mở nội dung email", "error");
                return;
            }

            const subjectEl = document.getElementById("viewMessageSubject");
            const fromEl = document.getElementById("viewMessageFrom");
            const bodyEl = document.getElementById("viewMessageBody");

            subjectEl.textContent = data.subject || "(Không có tiêu đề)";
            fromEl.innerHTML = `Từ: <strong>${this.core.escapeHtml(
                this.core.cleanEmail(data.from_email, data.from_name)
            )}</strong> • ${this.core.escapeHtml(this.core.formatDateTimeVN(data.received_at))}`;

            this.renderMessageBody(bodyEl, data);

            this.core.openModal("viewMessageModal");
        } catch (error) {
            this.core.showToast("Không thể mở nội dung email", "error");
        }
    }

    async deleteMessage(messageId, emailId) {
        if (!messageId || !emailId) return;
        const confirmed = await this.core.confirmAction({
            title: "Xác nhận xóa tin nhắn",
            text: "Bạn có chắc muốn xóa tin nhắn này?",
            confirmButtonText: "Xóa tin nhắn",
            cancelButtonText: "Hủy",
            icon: "warning",
        });
        if (!confirmed) return;

        try {
            const { ok, data } = await this.core.postJson("/api/admin/messages.php", {
                _method: "DELETE",
                ids: [messageId],
            });

            if (!ok) {
                this.core.showToast(data?.error || "Không thể xóa tin nhắn", "error");
                return;
            }

            this.core.showToast("Đã xóa tin nhắn", "success");
            await Promise.all([this.viewMessages(emailId, this.currentMessageEmail), this.reloadData()]);
        } catch (error) {
            this.core.showToast("Lỗi kết nối máy chủ", "error");
        }
    }

    handleMessagesClick(event) {
        const actionEl = event.target.closest("[data-action]");
        if (!actionEl) return;

        const action = actionEl.getAttribute("data-action");
        const messageId = Number(actionEl.getAttribute("data-message-id") || "0");
        const emailId = Number(actionEl.getAttribute("data-email-id") || "0") || this.currentMessageEmailId;

        if (action === "open-message") {
            this.viewMessage(messageId);
        } else if (action === "delete-message") {
            event.stopPropagation();
            this.deleteMessage(messageId, emailId);
        }
    }

    buildPreviewText(value) {
        const raw = String(value || "");
        if (raw === "") return "";
        const source = this.looksLikeHtml(raw)
            ? raw
                .replace(/<style[\s\S]*?<\/style>/gi, " ")
                .replace(/<script[\s\S]*?<\/script>/gi, " ")
                .replace(/<[^>]+>/g, " ")
                .replace(/&nbsp;/gi, " ")
            : raw;
        return source.replace(/\s+/g, " ").trim();
    }

    renderMessageBody(container, data) {
        const htmlBody = this.extractHtmlBody(data);
        container.style.whiteSpace = "";

        if (htmlBody !== "") {
            this.renderHtmlBody(container, htmlBody);
            return;
        }

        container.className = "modal-body";
        container.textContent = String(data?.body_text || "(Khong co noi dung)");
        container.style.whiteSpace = "pre-wrap";
    }

    extractHtmlBody(data) {
        const bodyHtml = String(data?.body_html || "").trim();
        if (bodyHtml !== "") return bodyHtml;

        const bodyText = String(data?.body_text || "").trim();
        return this.looksLikeHtml(bodyText) ? bodyText : "";
    }

    async handleCheckerSubmit(event) {
        event.preventDefault();
        const keyword = document.getElementById("checkerKeyword").value.trim();
        const days = document.getElementById("checkerDays").value;
        const resultsContainer = document.getElementById("checkerResults");
        const submitBtn = event.target.querySelector('button[type="submit"]');

        if (!keyword) return;

        if (submitBtn) {
            submitBtn.disabled = true;
            submitBtn.innerHTML = `<div class="spinner spinner-sm" style="margin-right: 8px;"></div><span>Đang quét...</span>`;
        }

        resultsContainer.innerHTML = `<div style="text-align: center; color: #64748b; padding: 2rem;"><div class="spinner" style="margin: 0 auto 1rem;"></div>Đang quét với tốc độ ánh sáng...</div>`;

        try {
            const domain = document.getElementById("domainFilter")?.value || "";
            const query = new URLSearchParams({ keyword, days, limit: 100 });
            if (domain) query.set("domain", domain);

            const { ok, data } = await this.core.fetchJson(`/api/admin/checker.php?${query.toString()}`);

            if (!ok || !data) {
                resultsContainer.innerHTML = `<div style="text-align: center; color: #ef4444; padding: 2rem;">Lỗi kết nối hoặc API phản hồi sai.</div>`;
                return;
            }

            if (!data.results || data.results.length === 0) {
                resultsContainer.innerHTML = `<div style="text-align: center; color: #10b981; padding: 2rem;">
                    <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" style="margin: 0 auto 1rem; display: block;">
                        <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path>
                        <polyline points="22 4 12 14.01 9 11.01"></polyline>
                    </svg>
                    Tuyệt vời! Không tìm thấy email nào chứa từ khóa "${this.core.escapeHtml(keyword)}" trong ${days} ngày qua.
                    <div style="font-size: 0.8rem; margin-top: 0.5rem; color: #94a3b8;">Thời gian quét: ${data.execution_time}</div>
                </div>`;
                return;
            }

            resultsContainer.innerHTML = `
                <div class="checker-results-header">
                    <span class="results-count">Tìm thấy ${data.count} kết quả</span>
                    <span class="execution-time">⚡ ${data.execution_time}</span>
                </div>
                <div style="display: flex; flex-direction: column; gap: 0.75rem;">
                    ${data.results.map(r => `
                        <div class="checker-result-item">
                            <div class="checker-result-header-inner" onclick="this.parentElement.classList.toggle('active')">
                                <div class="checker-result-info">
                                    <div class="checker-result-email">${this.core.escapeHtml(r.email)}</div>
                                    <div class="checker-result-subject">${this.core.escapeHtml(r.subject || '(No subject)')}</div>
                                </div>
                                <div class="checker-result-meta">
                                    <div class="checker-result-time">${this.core.formatDateTimeVN(r.received_at)}</div>
                                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#94a3b8" stroke-width="2" class="chevron">
                                        <polyline points="6 9 12 15 18 9"></polyline>
                                    </svg>
                                </div>
                            </div>
                            <div class="checker-detail">
                                <div style="padding: 1rem;">
                                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.5rem;">
                                        <span style="font-size: 0.75rem; font-weight: 600; color: #64748b; text-transform: uppercase;">Message JSON</span>
                                        <button class="btn-icon" data-action="view-messages" data-email-id="${r.email_id}" data-email="${encodeURIComponent(r.email)}" title="Mở hộp thư đầy đủ" style="background: #ffffff; width: 28px; height: 28px; border: 1px solid #e2e8f0;">
                                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="#0f172a" stroke-width="2">
                                                <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path>
                                                <circle cx="12" cy="12" r="3"></circle>
                                            </svg>
                                        </button>
                                    </div>
                                    <pre style="margin: 0; padding: 0.75rem; background: #1e293b; color: #e2e8f0; border-radius: 0.375rem; font-size: 0.8rem; overflow-x: auto; font-family: 'JetBrains Mono', 'Fira Code', monospace;">${this.core.escapeHtml(JSON.stringify({
                id: r.message_id,
                subject: r.subject,
                received_at: r.received_at,
                body_snippet: (r.body_text || "").substring(0, 500) + (r.body_text?.length > 500 ? "..." : "")
            }, null, 2))}</pre>
                                </div>
                            </div>
                        </div>
                    `).join('')}
                </div>
            `;
        } catch (e) {
            resultsContainer.innerHTML = `<div style="text-align: center; color: #ef4444; padding: 2rem;">Đã xảy ra lỗi hệ thống!</div>`;
        } finally {
            if (submitBtn) {
                submitBtn.disabled = false;
                submitBtn.innerHTML = `<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <polygon points="13 2 3 14 12 14 11 22 21 10 12 10 13 2"></polygon>
                </svg><span>Quét ngay</span>`;
            }
        }
    }

    looksLikeHtml(value) {
        const text = String(value || "").trim();
        if (text === "") return false;
        if (!/<\/?[a-z][\s\S]*>/i.test(text)) return false;
        return /<!doctype\s+html|<html[\s>]|<head[\s>]|<body[\s>]|<table[\s>]|<div[\s>]|<p[\s>]|<a[\s>]|<img[\s>]|<style[\s>]/i.test(text);
    }

    renderHtmlBody(container, html) {
        container.className = "modal-body email-body";
        container.textContent = "";

        const frame = document.createElement("iframe");
        frame.className = "email-body-frame";
        frame.title = "Email HTML content";
        frame.setAttribute("sandbox", "allow-popups allow-popups-to-escape-sandbox");
        frame.setAttribute("referrerpolicy", "no-referrer");
        frame.srcdoc = this.buildEmailSrcdoc(html);
        container.appendChild(frame);
    }

    buildEmailSrcdoc(html) {
        const source = String(html || "");
        if (source === "") return "";
        if (/<\s*html[\s>]/i.test(source) || /<!doctype\s+html/i.test(source)) {
            return source;
        }
        return `<!doctype html><html><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><base target="_blank"></head><body>${source}</body></html>`;
    }

    startPolling() {
        if (this.pollingActive) return;
        this.pollingActive = true;
        this.pollLoop();
    }

    stopPolling() {
        this.pollingActive = false;
        if (this.pollTimer) {
            clearTimeout(this.pollTimer);
            this.pollTimer = null;
        }
    }

    async pollLoop() {
        if (!this.pollingActive) return;

        if (document.hidden) {
            this.pollTimer = setTimeout(() => this.pollLoop(), 3000);
            return;
        }

        const currentLastCheck = this.lastCheckTime || this.buildCurrentTimestamp();
        const controller = new AbortController();
        const timeoutId = setTimeout(() => controller.abort(), 35000);

        try {
            const { ok, data } = await this.core.fetchJson(
                `/api/admin/long-poll.php?last_check=${encodeURIComponent(currentLastCheck)}`,
                { signal: controller.signal }
            );
            clearTimeout(timeoutId);

            if (!ok || !data) {
                this.pollTimer = setTimeout(() => this.pollLoop(), 3000);
                return;
            }

            if (data.last_check) {
                this.lastCheckTime = String(data.last_check);
            }

            if (data.has_updates) {
                await this.reloadData();
            }

            this.pollTimer = setTimeout(() => this.pollLoop(), 120);
        } catch (error) {
            clearTimeout(timeoutId);
            this.pollTimer = setTimeout(() => this.pollLoop(), 5000);
        }
    }

    buildCurrentTimestamp() {
        return this.core.getCurrentSqlDateTimeVN();
    }
}

document.addEventListener("DOMContentLoaded", () => {
    const boot = () => {
        if (!window.adminCore) return;
        const dashboard = new AdminDashboardPage(window.adminCore);
        dashboard.init();
        window.adminDashboard = dashboard;
    };

    if (window.adminCore) {
        boot();
    } else {
        window.addEventListener("admin-core-ready", boot, { once: true });
    }
});
