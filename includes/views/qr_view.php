            <!-- QR Mode Content -->
            <div id="qrModeContent" class="<?= $initialMode === 'qr' ? '' : 'hidden' ?>">
                <section class="compose-shell">
                    <div class="compose-row">
                        <div class="email-input-wrapper">
                            <div class="input-icon-prefix" aria-hidden="true">
                                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <rect x="3" y="3" width="7" height="7" rx="1"/>
                                    <rect x="14" y="3" width="7" height="7" rx="1"/>
                                    <rect x="14" y="14" width="7" height="7" rx="1"/>
                                    <rect x="3" y="14" width="7" height="7" rx="1"/>
                                </svg>
                            </div>
                            <input type="text" id="qrInput" class="email-input"
                                placeholder="Nhập nội dung hoặc mã voucher..." autocomplete="off"
                                spellcheck="false">
                            <button id="qrClearBtn" class="btn-input-clear" type="button" title="Xóa nội dung" style="display: none;">
                                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <line x1="18" y1="6" x2="6" y2="18"></line>
                                    <line x1="6" y1="6" x2="18" y2="18"></line>
                                </svg>
                            </button>
                        </div>
                        <button id="generateQrBtn" class="btn-primary-action" type="button">
                            <span>Tạo QR</span>
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2">
                                <line x1="5" y1="12" x2="19" y2="12" />
                                <polyline points="12 5 19 12 12 19" />
                            </svg>
                        </button>
                    </div>
                </section>

                <!-- QR Main Card -->
                <section id="qrResultSection" class="inbox-section">
                    <div class="inbox-header">
                        <div class="inbox-title-group">
                            <div class="inbox-title-icon">
                                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <rect x="3" y="3" width="7" height="7" rx="1"/>
                                    <rect x="14" y="3" width="7" height="7" rx="1"/>
                                    <rect x="14" y="14" width="7" height="7" rx="1"/>
                                    <rect x="3" y="14" width="7" height="7" rx="1"/>
                                </svg>
                            </div>
                            <span class="inbox-title-text">Mã QR Code</span>
                        </div>
                        <div class="qr-header-actions">
                            <button id="qrDownloadBtn" class="btn-header-copy is-disabled" type="button" title="Tải ảnh QR" disabled>
                                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path>
                                    <polyline points="7 10 12 15 17 10"></polyline>
                                    <line x1="12" y1="15" x2="12" y2="3"></line>
                                </svg>
                            </button>
                            <button id="qrCopyBtn" class="btn-header-copy is-disabled" type="button" title="Sao chép nội dung" disabled>
                                <svg class="copy-icon" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <rect x="9" y="9" width="13" height="13" rx="2" ry="2" />
                                    <path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1" />
                                </svg>
                                <svg class="check-icon hidden" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="#10b981" stroke-width="2.5">
                                    <polyline points="20 6 9 17 4 12" />
                                </svg>
                            </button>
                        </div>
                    </div>

                    <div class="qr-card-body">
                        <!-- Empty State (Chưa nhập nội dung) - Đồng bộ 100% style empty-state từ mail_view -->
                        <div id="qrPlaceholder" class="empty-state">
                            <svg class="empty-qr-svg" width="70" height="70" viewBox="0 0 24 24" fill="none" stroke="#cbd5e1" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round">
                                <rect x="3" y="3" width="7" height="7" rx="1.5"></rect>
                                <rect x="14" y="3" width="7" height="7" rx="1.5"></rect>
                                <rect x="14" y="14" width="7" height="7" rx="1.5"></rect>
                                <rect x="3" y="14" width="7" height="7" rx="1.5"></rect>
                                <line x1="7" y1="7" x2="7.01" y2="7"></line>
                                <line x1="17" y1="7" x2="17.01" y2="7"></line>
                                <line x1="7" y1="17" x2="7.01" y2="17"></line>
                                <line x1="17" y1="17" x2="17.01" y2="17"></line>
                            </svg>
                            <div class="empty-state-title">Chưa có mã QR</div>
                            <div class="empty-state-desc">Nhập nội dung hoặc dán mã vào ô bên trên để tạo mã QR</div>
                        </div>

                        <!-- Active QR Result Stage (Chỉ hiện khi đã có mã) -->
                        <div id="qrResultContainer" class="qr-active-stage" style="display: none;">
                            <div class="qr-frame-box" id="qrStageWrapper" role="button" tabindex="0" title="Nhấp để tải ảnh QR">
                                <div id="qrCanvasContainer"></div>
                            </div>
                            <span class="qr-hint-text">Nhấp vào mã QR để tải ảnh PNG về máy</span>
                        </div>
                    </div>
                </section>
            </div>
