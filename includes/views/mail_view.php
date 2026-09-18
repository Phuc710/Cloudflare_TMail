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
                        <svg class="empty-inbox-svg" width="92" height="92" viewBox="0 0 92 92" fill="none" xmlns="http://www.w3.org/2000/svg">
                            <!-- Envelope (Dead center at X=46, Y=46) -->
                            <g class="empty-inbox-envelope">
                                <path d="M26 57.77V42.3C26 40.52 26.95 38.88 28.48 37.99L43.48 29.24C45.03 28.35 46.93 28.35 48.48 29.24L63.48 37.99C65.03 38.87 66 40.52 66 42.3V57.77C66 60.53 63.76 62.77 61 62.77H31C28.24 62.77 26 60.53 26 57.77Z" fill="#8C92A5"></path>
                                <path d="M46 51.1L26.68 39.79C26.23 40.56 26 41.43 26 42.32V57.77C26 60.53 28.24 62.77 31 62.77H61C63.76 62.77 66 60.53 66 57.77V42.3C66 41.41 65.77 40.54 65.32 39.77L46 51.1Z" fill="#CDCDD8"></path>
                                <path d="M27.9 61.67C28.78 62.38 29.87 62.77 31 62.77H61C63.76 62.77 66 60.53 66 57.77V42.3C66 41.43 65.77 40.57 65.33 39.82L27.9 61.67Z" fill="#E5E5F0"></path>
                            </g>

                            <!-- Perfectly Symmetrical Rotating Circular Ring (Dead center at X=46, Y=46, R=39) -->
                            <g class="emptyInboxRotation">
                                <!-- Top Arc + Arrow -->
                                <path d="M9.35 32.66 A39 39 0 0 1 82.65 32.66" stroke="#E5E5F0" stroke-width="3" stroke-linecap="round" fill="none"></path>
                                <polygon points="85.4,40.2 87.6,28.7 76.3,32.8" fill="#E5E5F0"></polygon>

                                <!-- Bottom Arc + Arrow (180° Point-Reflection Symmetrical) -->
                                <path d="M82.65 59.34 A39 39 0 0 1 9.35 59.34" stroke="#E5E5F0" stroke-width="3" stroke-linecap="round" fill="none"></path>
                                <polygon points="6.6,51.8 4.4,63.3 15.7,59.2" fill="#E5E5F0"></polygon>
                            </g>
                        </svg>
                        <div class="empty-state-title">Chưa có thư</div>
                        <div class="empty-state-desc">Đang tự động kiểm tra và nhận thư mới auto</div>
                    </div>
                </section>
            </div>
