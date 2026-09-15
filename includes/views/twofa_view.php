            <div id="twofaModeContent" class="<?= $initialMode === 'twofa' ? '' : 'hidden' ?>">
                <section class="compose-shell">
                    <div class="compose-row">
                        <div class="email-input-wrapper">
                            <div class="input-icon-prefix" aria-hidden="true">
                                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <rect width="18" height="11" x="3" y="11" rx="2" ry="2"/>
                                    <path d="M7 11V7a5 5 0 0 1 10 0v4"/>
                                </svg>
                            </div>
                            <input type="text" id="twofaInput" class="email-input"
                                placeholder="Nhập khóa bí mật" autocomplete="off"
                                spellcheck="false">
                            <button id="twofaClearBtn" class="btn-input-clear" type="button" title="Xóa khóa bí mật" style="display: none;">
                                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <line x1="18" y1="6" x2="6" y2="18"></line>
                                    <line x1="6" y1="6" x2="18" y2="18"></line>
                                </svg>
                            </button>
                        </div>
                        <button id="getOtpBtn" class="btn-primary-action" type="button">
                            <span>Get 2FA</span>
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2">
                                <line x1="5" y1="12" x2="19" y2="12" />
                                <polyline points="12 5 19 12 12 19" />
                            </svg>
                        </button>
                    </div>
                </section>

                <!-- 2FA Main Card -->
                <section id="twofaResultSection" class="inbox-section">
                    <div class="inbox-header">
                        <div class="inbox-title-group">
                            <div class="inbox-title-icon">
                                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <rect width="18" height="11" x="3" y="11" rx="2" ry="2" />
                                    <path d="M7 11V7a5 5 0 0 1 10 0v4" />
                                </svg>
                            </div>
                            <span class="inbox-title-text">Mã xác thực 2FA</span>
                        </div>
                        <button id="copyOtpBtn" class="btn-header-copy is-disabled" type="button" title="Sao chép mã xác thực" disabled>
                            <svg class="copy-icon" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <rect x="9" y="9" width="13" height="13" rx="2" ry="2" />
                                <path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1" />
                            </svg>
                            <svg class="check-icon hidden" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="#10b981" stroke-width="2.5">
                                <polyline points="20 6 9 17 4 12" />
                            </svg>
                        </button>
                    </div>
                    
                    <div class="twofa-card-body">
                        <!-- Senior 6-Digit Segmented OTP Display -->
                        <div class="twofa-otp-stage is-empty" id="otpCodeWrapper" role="button" tabindex="0" title="Nhấp để sao chép mã">
                            <div class="otp-boxes-grid">
                                <!-- 3 số đầu -->
                                <div class="otp-box-group" aria-label="3 số đầu">
                                    <div class="otp-digit-card is-empty"><span class="digit-text" data-digit="0">—</span></div>
                                    <div class="otp-digit-card is-empty"><span class="digit-text" data-digit="1">—</span></div>
                                    <div class="otp-digit-card is-empty"><span class="digit-text" data-digit="2">—</span></div>
                                </div>

                                <!-- Divider -->
                                <div class="otp-box-divider" aria-hidden="true">
                                    <span class="divider-pill"></span>
                                </div>

                                <!-- 3 số sau -->
                                <div class="otp-box-group" aria-label="3 số sau">
                                    <div class="otp-digit-card is-empty"><span class="digit-text" data-digit="3">—</span></div>
                                    <div class="otp-digit-card is-empty"><span class="digit-text" data-digit="4">—</span></div>
                                    <div class="otp-digit-card is-empty"><span class="digit-text" data-digit="5">—</span></div>
                                </div>
                            </div>

                            <!-- Fallback hidden spans for backwards compatibility -->
                            <span id="otpPart1" class="sr-only">···</span>
                            <span id="otpPart2" class="sr-only">···</span>
                        </div>

                        <!-- Progress Bar & Countdown Timer -->
                        <div class="twofa-timer-wrapper">
                            <div class="twofa-progress-bar-bg">
                                <div class="twofa-progress-bar" id="twofaProgress" style="width: 0%;"></div>
                            </div>
                            <span class="twofa-timer-text" id="twofaTimerText">Nhập khóa bí mật để sinh mã</span>
                        </div>
                    </div>

                    <!-- Recent Keys History (Chỉ hiện khi có lịch sử đã lưu) -->
                    <div id="twofaRecentWrapper" class="twofa-recent-section hidden">
                        <div class="twofa-recent-header">
                            <span>Khóa đã dùng gần đây</span>
                            <button id="twofaClearHistoryBtn" class="btn-clear-history" type="button">Xóa lịch sử</button>
                        </div>
                        <div id="twofaRecentList" class="twofa-recent-list"></div>
                    </div>
                </section>
            </div>