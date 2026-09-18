            <div id="mailModeContent" class="<?= $initialMode === 'mail' ? '' : 'hidden' ?>">
                <section class="compose-shell tmail-panel">
                    <div class="tmail-control-card">
                        <div class="compose-row">
                            <div class="email-input-wrapper">
                                <div class="input-icon-prefix" aria-hidden="true">
                                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                        <path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1-0-2-.9-2-2V6c0-1.1.9-2 2-2z" />
                                        <polyline points="22,6 12,13 2,6" />
                                    </svg>
                                </div>
                                <input type="text" id="emailInput" class="email-input"
                                    value="<?= htmlspecialchars($initialEmail, ENT_QUOTES, 'UTF-8') ?>"
                                    placeholder="Nhập địa chỉ email, ví dụ: name@domain.com" autocomplete="off"
                                    spellcheck="false" title="Nhập hoặc chỉnh sửa địa chỉ email">
                                <button id="emailClearBtn" class="btn-input-clear <?= empty($initialEmail) ? 'hidden' : '' ?>" type="button" title="Xóa nội dung">
                                    <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                        <line x1="18" y1="6" x2="6" y2="18"></line>
                                        <line x1="6" y1="6" x2="18" y2="18"></line>
                                    </svg>
                                </button>
                                <div id="emailSpinner" class="email-spinner-indicator hidden" title="Đang xử lý..."></div>
                            </div>
                            <button id="getMailBtn" class="btn-primary-action" type="button">
                                <span>Get Mail</span>
                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2">
                                    <line x1="5" y1="12" x2="19" y2="12" />
                                    <polyline points="12 5 19 12 12 19" />
                                </svg>
                            </button>
                        </div>

                        <!-- Standard TMail Action Toolbar -->
                        <div class="tmail-toolbar" role="toolbar" aria-label="Thanh công cụ Temp Mail">
                            <button id="copyBtn" class="tmail-tool-btn btn-copy <?= empty($initialEmail) ? 'is-disabled' : '' ?>" type="button" title="<?= empty($initialEmail) ? 'Chưa có email để sao chép' : 'Sao chép địa chỉ email' ?>" <?= empty($initialEmail) ? 'disabled' : '' ?>>
                                <svg class="tool-icon copy-icon" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <rect x="9" y="9" width="13" height="13" rx="2" ry="2" />
                                    <path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1" />
                                </svg>
                                <svg class="tool-icon check-icon hidden" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="#10b981" stroke-width="2.5">
                                    <polyline points="20 6 9 17 4 12" />
                                </svg>
                                <span class="btn-label">Sao chép</span>
                            </button>

                            <button id="randomMailBtn" class="tmail-tool-btn btn-random" type="button" title="Sinh ngay một địa chỉ email ngẫu nhiên mới">
                                <svg class="tool-icon icon-random" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <polyline points="23 4 23 10 17 10" />
                                    <polyline points="1 20 1 14 7 14" />
                                    <path d="M20.49 9A9 9 0 0 0 5.64 5.64L1 10m22 4l-4.64 4.36A9 9 0 0 1 3.51 15" />
                                </svg>
                                <span class="btn-label">Tạo mới</span>
                            </button>

                            <button id="customMailBtn" class="tmail-tool-btn btn-custom" type="button" title="Tùy chỉnh tên email và tên miền theo ý muốn">
                                <svg class="tool-icon icon-custom" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <path d="M12 20h9" />
                                    <path d="M16.5 3.5a2.121 2.121 0 0 1 3 3L7 19l-4 1 1-4L16.5 3.5z" />
                                </svg>
                                <span class="btn-label">Tùy chỉnh</span>
                            </button>

                            <button id="addDomainBtn" class="tmail-tool-btn btn-add-domain" type="button" title="Thêm tên miền riêng của bạn (Cloudflare Email Routing)">
                                <svg class="tool-icon icon-add-domain" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <circle cx="12" cy="12" r="10" />
                                    <line x1="12" y1="8" x2="12" y2="16" />
                                    <line x1="8" y1="12" x2="16" y2="12" />
                                </svg>
                                <span class="btn-label">Thêm Domain</span>
                            </button>

                            <button id="qrMailBtn" class="tmail-tool-btn btn-qr <?= empty($initialEmail) ? 'is-disabled' : '' ?>" type="button" title="<?= empty($initialEmail) ? 'Chưa có email để tạo mã QR' : 'Xem mã QR để quét mở trên điện thoại' ?>" <?= empty($initialEmail) ? 'disabled' : '' ?>>
                                <svg class="tool-icon icon-qr" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <rect x="3" y="3" width="7" height="7" rx="1" />
                                    <rect x="14" y="3" width="7" height="7" rx="1" />
                                    <rect x="14" y="14" width="7" height="7" rx="1" />
                                    <rect x="3" y="14" width="7" height="7" rx="1" />
                                </svg>
                                <span class="btn-label">Mã QR</span>
                            </button>

                            <button id="deleteMailBtn" class="tmail-tool-btn btn-delete <?= empty($initialEmail) ? 'is-disabled' : '' ?>" type="button" title="<?= empty($initialEmail) ? 'Chưa có email để xóa' : 'Xóa hộp thư hiện tại và tạo email mới' ?>" <?= empty($initialEmail) ? 'disabled' : '' ?>>
                                <svg class="tool-icon icon-delete" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <polyline points="3 6 5 6 21 6" />
                                    <path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2" />
                                </svg>
                                <span class="btn-label">Xóa</span>
                            </button>
                        </div>
                    </div>
                </section>

                <section id="inboxSection" class="inbox-section">
                    <div class="inbox-header">
                        <div class="inbox-title-group">
                            <div class="inbox-title-icon">
                                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <polyline points="22 12 16 12 14 15 10 15 8 12 2 12" />
                                    <path d="M5.45 5.11L2 12v6a2 2 0 0 0 2 2h16a2 2 0 0 0 2-2v-6l-3.45-6.89A2 2 0 0 0 16.76 4H7.24a2 2 0 0 0-1.79 1.11z" />
                                </svg>
                            </div>
                            <span class="inbox-title-text">Hộp thư</span>
                            <span id="unreadBadge" class="unread-badge hidden">0</span>
                        </div>
                        <button id="refreshBtn" class="btn-header-refresh <?= empty($initialEmail) ? 'is-disabled' : '' ?>" title="Làm mới hộp thư" type="button" <?= empty($initialEmail) ? 'disabled' : '' ?>>
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <polyline points="23 4 23 10 17 10" />
                                <path d="M20.49 15a9 9 0 1 1-2.12-9.36L23 10" />
                            </svg>
                        </button>
                    </div>

                    <div id="messagesList" class="messages-list"></div>

                    <div id="emptyState" class="empty-state">
                        <svg class="empty-inbox-svg" width="90" height="90" viewBox="0 0 100 100" fill="none" xmlns="http://www.w3.org/2000/svg">
                            <!-- Perfectly Centered Rotating Dual-Arrow Ring -->
                            <g class="empty-inbox-spinner">
                                <!-- Top Arc + Arrowhead -->
                                <path d="M 14.3 37.0 A 38 38 0 0 1 85.7 37.0" stroke="#CBD5E1" stroke-width="3" stroke-linecap="round" fill="none" />
                                <polygon points="88.6,44.0 90.0,31.8 79.7,35.5" fill="#CBD5E1" />
                                
                                <!-- Bottom Arc + Arrowhead (100% 180° Symmetric) -->
                                <path d="M 85.7 63.0 A 38 38 0 0 1 14.3 63.0" stroke="#CBD5E1" stroke-width="3" stroke-linecap="round" fill="none" />
                                <polygon points="11.4,56.0 10.0,68.2 20.3,64.5" fill="#CBD5E1" />
                            </g>

                            <!-- Perfectly Centered Envelope Icon (Center at 50, 50) -->
                            <g class="empty-inbox-envelope">
                                <!-- Back Wall & Open Flap -->
                                <path d="M 30 62.5 V 46.5 C 30 44.8 30.9 43.2 32.5 42.3 L 47.5 33.5 C 49 32.6 51 32.6 52.5 33.5 L 67.5 42.3 C 69.1 43.2 70 44.8 70 46.5 V 62.5 C 70 65 68 67 65.5 67 H 34.5 C 32 67 30 65 30 62.5 Z" fill="#737A91" />
                                <!-- Front Body / Side Folds -->
                                <path d="M 50 55 L 30.7 44 C 30.2 44.8 30 45.6 30 46.5 V 62.5 C 30 65 32 67 34.5 67 H 65.5 C 68 67 70 65 70 62.5 V 46.5 C 70 45.6 69.8 44.8 69.3 44 L 50 55 Z" fill="#94A3B8" />
                                <!-- Front Bottom Highlight Flap -->
                                <path d="M 31.8 66 C 32.6 66.6 33.5 67 34.5 67 H 65.5 C 68 67 70 65 70 62.5 V 46.5 C 70 45.6 69.8 44.8 69.3 44.1 L 31.8 66 Z" fill="#CBD5E1" />
                            </g>
                        </svg>
                        <div class="empty-state-title">Chưa có thư</div>
                        <div class="empty-state-desc">Đang tự động kiểm tra và nhận thư mới auto</div>
                    </div>
                </section>
            </div>
