<?php
require_once __DIR__ . '/../Auth.php';
require_once __DIR__ . '/../Core/App.php';

use KaiMail\Core\App;
use KaiMail\Core\Services\QrPresetService;

$isAdmin = Auth::isLoggedIn();
$qrPresetText = '';
try {
    /** @var QrPresetService $qrService */
    $qrService = App::getService(QrPresetService::class);
    $qrPresetText = $qrService->getQrText();
} catch (Throwable $e) {
    // Ignore load error gracefully
}

$isPresetUrl = false;
$presetHref = '';
if ($qrPresetText !== '') {
    $trimmedPreset = trim($qrPresetText);
    if (preg_match('#^https?://#i', $trimmedPreset)) {
        $isPresetUrl = true;
        $presetHref = $trimmedPreset;
    } elseif (preg_match('#^www\.[a-z0-9\-]+(\.[a-z0-9\-]+)+#i', $trimmedPreset)) {
        $isPresetUrl = true;
        $presetHref = 'https://' . $trimmedPreset;
    }
}
?>
            <!-- QR Mode Content -->
            <div id="qrModeContent" class="<?= $initialMode === 'qr' ? '' : 'hidden' ?>">
                <!-- Main Input Shell for creating QR -->
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
                        <!-- Empty State (Chưa nhập nội dung) -->
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

                <!-- ADMIN CONTROL BOX (Only rendered if Admin is logged in) -->
                <?php if ($isAdmin): ?>
                <div class="qr-admin-preset-shell">
                    <div class="qr-admin-preset-header">
                        <div class="qr-admin-title">
                            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2">
                                <path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/>
                            </svg>
                            <span>Cấu hình văn bản mẫu</span>
                        </div>
                    </div>
                    <div class="qr-admin-desc">Nội dung này sẽ hiển thị làm mẫu gợi ý sao chép hoặc mở link nhanh cho toàn bộ người dùng.</div>
                    <div class="qr-admin-preset-controls">
                        <div class="qr-admin-textarea-wrapper">
                            <textarea id="qrPresetTextInput" class="qr-admin-textarea"
                                rows="3"
                                placeholder="Admin: Nhập văn bản mẫu cho user (hỗ trợ nhiều dòng, link hoặc mã)..."><?= htmlspecialchars($qrPresetText, ENT_QUOTES, 'UTF-8') ?></textarea>
                        </div>
                        <div class="qr-admin-preset-actions">
                            <button id="qrPresetSaveAdminBtn" class="btn-qr-admin-save" type="button">
                                <svg class="save-icon" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2">
                                    <path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"></path>
                                    <polyline points="17 21 17 13 7 13 7 21"></polyline>
                                    <polyline points="7 3 7 8 15 8"></polyline>
                                </svg>
                                <span>Lưu cấu hình</span>
                            </button>
                        </div>
                    </div>
                </div>
                <?php endif; ?>

                <!-- USER VIEW DISPLAY (Visible to EVERYONE when text is present) -->
                <div id="qrUserPresetShell" class="qr-user-preset-shell" style="margin-top: 16px; background: #ffffff; border: 1px solid #e2e8f0; border-radius: 12px; padding: 14px 16px; display: <?= $qrPresetText !== '' ? 'flex' : 'none' ?>; align-items: center; justify-content: space-between; gap: 12px; flex-wrap: wrap; box-shadow: 0 1px 3px rgba(15,23,42,0.03);">
                    <div style="flex: 1; min-width: 180px;">
                        <div style="font-size: 11px; font-weight: 600; color: #64748b; text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 4px;">Văn bản mẫu / Mã sẵn có:</div>
                        <div id="qrPresetUserTextDisplay" style="font-family: var(--font-mono, monospace); font-size: 13.5px; color: #0f172a; font-weight: 500; word-break: break-all; white-space: pre-wrap;">
                            <?php if ($isPresetUrl): ?>
                                <a href="<?= htmlspecialchars($presetHref, ENT_QUOTES, 'UTF-8') ?>" target="_blank" rel="noopener noreferrer" style="color: #2563eb; text-decoration: underline; display: inline-flex; align-items: center; gap: 4px; font-weight: 500;">
                                    <span><?= htmlspecialchars($qrPresetText, ENT_QUOTES, 'UTF-8') ?></span>
                                    <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                        <path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"></path>
                                        <polyline points="15 3 21 3 21 9"></polyline>
                                        <line x1="10" y1="14" x2="21" y2="3"></line>
                                    </svg>
                                </a>
                            <?php else: ?>
                                <?= htmlspecialchars($qrPresetText, ENT_QUOTES, 'UTF-8') ?>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div style="display: flex; gap: 8px;">
                        <button id="qrPresetCopyBtn" class="btn-secondary-action" type="button"
                            data-url="<?= $isPresetUrl ? htmlspecialchars($presetHref, ENT_QUOTES, 'UTF-8') : '' ?>"
                            title="<?= $isPresetUrl ? 'Mở liên kết trong tab mới' : 'Sao chép nội dung' ?>"
                            style="padding: 0 16px; height: 38px; font-size: 13px; font-weight: 500; border-radius: 6px; border: 1px solid <?= $isPresetUrl ? '#2563eb' : '#cbd5e1' ?>; background: <?= $isPresetUrl ? '#eff6ff' : '#fff' ?>; color: <?= $isPresetUrl ? '#1d4ed8' : '#0f172a' ?>; cursor: pointer; display: inline-flex; align-items: center; gap: 6px; transition: all 0.2s;">
                            
                            <span class="btn-icon-container" style="display: inline-flex; align-items: center;">
                                <?php if ($isPresetUrl): ?>
                                    <svg class="open-icon" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                        <path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"></path>
                                        <polyline points="15 3 21 3 21 9"></polyline>
                                        <line x1="10" y1="14" x2="21" y2="3"></line>
                                    </svg>
                                <?php else: ?>
                                    <svg class="copy-icon" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                        <rect x="9" y="9" width="13" height="13" rx="2" ry="2"/>
                                        <path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"/>
                                    </svg>
                                    <svg class="check-icon hidden" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="#10b981" stroke-width="2.5">
                                        <polyline points="20 6 9 17 4 12" />
                                    </svg>
                                <?php endif; ?>
                            </span>
                            <span class="btn-copy-label"><?= $isPresetUrl ? 'Mở link' : 'Copy' ?></span>
                        </button>
                    </div>
                </div>
            </div>
