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
                        <svg class="empty-inbox-svg" width="92" height="94" viewBox="0 0 92 87" fill="none" xmlns="http://www.w3.org/2000/svg">
                            <path d="M26 54.37V38.9C26.003 37.125 26.9469 35.4846 28.48 34.59L43.48 25.84C45.027 24.9468 46.933 24.9468 48.48 25.84L63.48 34.59C65.0285 35.4745 65.9887 37.1167 66 38.9V54.37C66 57.1314 63.7614 59.37 61 59.37H31C28.2386 59.37 26 57.1314 26 54.37Z" fill="#8C92A5"></path>
                            <path d="M46 47.7L26.68 36.39C26.2325 37.1579 25.9978 38.0312 26 38.92V54.37C26 57.1314 28.2386 59.37 31 59.37H61C63.7614 59.37 66 57.1314 66 54.37V38.9C66.0022 38.0112 65.7675 37.1379 65.32 36.37L46 47.7Z" fill="#CDCDD8"></path>
                            <path d="M27.8999 58.27C28.7796 58.9758 29.8721 59.3634 30.9999 59.37H60.9999C63.7613 59.37 65.9999 57.1314 65.9999 54.37V38.9C65.9992 38.0287 65.768 37.1731 65.3299 36.42L27.8999 58.27Z" fill="#E5E5F0"></path>
                            <g class="emptyInboxRotation">
                                <path class="emptyInboxRotation" d="M77.8202 29.21L89.5402 25.21C89.9645 25.0678 90.4327 25.1942 90.7277 25.5307C91.0227 25.8673 91.0868 26.348 90.8902 26.75L87.0002 34.62C86.8709 34.8874 86.6407 35.0924 86.3602 35.19C86.0798 35.2806 85.7751 35.2591 85.5102 35.13L77.6502 31.26C77.2436 31.0643 76.9978 30.6401 77.0302 30.19C77.0677 29.7323 77.3808 29.3438 77.8202 29.21Z" fill="#E5E5F0"></path>
                                <path class="emptyInboxRotation" d="M5.12012 40.75C6.36707 20.9791 21.5719 4.92744 41.2463 2.61179C60.9207 0.296147 79.4368 12.3789 85.2401 31.32" stroke="#E5E5F0" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"></path>
                                <path class="emptyInboxRotation" d="M14.18 57.79L2.46001 61.79C2.03313 61.9358 1.56046 61.8088 1.2642 61.4686C0.967927 61.1284 0.906981 60.6428 1.11001 60.24L5.00001 52.38C5.12933 52.1127 5.35954 51.9076 5.64001 51.81C5.92044 51.7194 6.22508 51.7409 6.49001 51.87L14.35 55.74C14.7224 55.9522 14.9394 56.36 14.9073 56.7874C14.8753 57.2149 14.5999 57.5857 14.2 57.74L14.18 57.79Z" fill="#E5E5F0"></path>
                                <path class="emptyInboxRotation" d="M86.9998 45.8C85.9593 65.5282 70.9982 81.709 51.4118 84.2894C31.8254 86.8697 13.1841 75.1156 7.06982 56.33" stroke="#E5E5F0" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"></path>
                            </g>
                        </svg>
                        <div class="empty-state-title">Chưa có thư</div>
                        <div class="empty-state-desc">Đang tự động kiểm tra và nhận thư mới auto</div>
                    </div>
                </section>
            </div>
