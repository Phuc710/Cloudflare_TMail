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

$presetLines = [];
if ($qrPresetText !== '') {
    $rawLines = preg_split("/\r\n|\n|\r/", $qrPresetText);
    foreach ($rawLines as $rawLine) {
        $trimmed = trim($rawLine);
        if ($trimmed !== '') {
            $isUrl = false;
            $href = '';
            if (preg_match('#^https?://#i', $trimmed)) {
                $isUrl = true;
                $href = $trimmed;
            } elseif (preg_match('#^www\.[a-z0-9\-]+(\.[a-z0-9\-]+)+#i', $trimmed)) {
                $isUrl = true;
                $href = 'https://' . $trimmed;
            }
            $presetLines[] = [
                'text' => $trimmed,
                'isUrl' => $isUrl,
                'href' => $href,
            ];
        }
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
                            <span>Cấu hình Mã</span>
                        </div>
                    </div>
                    <div class="qr-admin-desc">Nhập danh sách mã hoặc liên kết (mỗi dòng 1 mã). Hệ thống sẽ hiển thị thành từng dòng riêng cho người dùng.</div>
                    <div class="qr-admin-preset-controls">
                        <div class="qr-admin-textarea-wrapper">
                            <textarea id="qrPresetTextInput" class="qr-admin-textarea"
                                rows="3"
                                placeholder="Admin: Nhập danh sách mã (mỗi dòng 1 mã hoặc liên kết)..."><?= htmlspecialchars($qrPresetText, ENT_QUOTES, 'UTF-8') ?></textarea>
                        </div>
                        <div class="qr-admin-preset-actions">
                            <button id="qrPresetSaveAdminBtn" class="btn-qr-admin-save" type="button">
                                <svg class="save-icon" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2">
                                    <path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"></path>
                                    <polyline points="17 21 17 13 7 13 7 21"></polyline>
                                    <polyline points="7 3 7 8 15 8"></polyline>
                                </svg>
                                <span>Lưu mã</span>
                            </button>
                        </div>
                    </div>
                </div>
                <?php endif; ?>

                <!-- USER VIEW DISPLAY (Visible to EVERYONE when lines are present) -->
                <div id="qrUserPresetShell" class="qr-user-preset-shell" style="<?= count($presetLines) > 0 ? '' : 'display: none;' ?>">
                    <div class="qr-user-preset-header">
                        <div class="qr-user-preset-title">
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2">
                                <rect x="3" y="3" width="7" height="7" rx="1"/>
                                <rect x="14" y="3" width="7" height="7" rx="1"/>
                                <rect x="14" y="14" width="7" height="7" rx="1"/>
                                <rect x="3" y="14" width="7" height="7" rx="1"/>
                            </svg>
                            <span>Mã có sẵn</span>
                        </div>
                        <span id="qrPresetCountBadge" class="qr-user-preset-count"><?= count($presetLines) ?></span>
                    </div>

                    <div id="qrPresetLinesList" class="qr-preset-items-list">
                        <?php foreach ($presetLines as $idx => $lineItem): ?>
                        <div class="qr-preset-item-card" data-index="<?= $idx ?>">
                            <div class="qr-preset-item-info">
                                <span class="qr-preset-item-badge"><?= $idx + 1 ?></span>
                                <div class="qr-preset-item-text">
                                    <?php if ($lineItem['isUrl']): ?>
                                        <a href="<?= htmlspecialchars($lineItem['href'], ENT_QUOTES, 'UTF-8') ?>" target="_blank" rel="noopener noreferrer" class="qr-preset-item-link">
                                            <span><?= htmlspecialchars($lineItem['text'], ENT_QUOTES, 'UTF-8') ?></span>
                                            <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                                <path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"></path>
                                                <polyline points="15 3 21 3 21 9"></polyline>
                                                <line x1="10" y1="14" x2="21" y2="3"></line>
                                            </svg>
                                        </a>
                                    <?php else: ?>
                                        <?= htmlspecialchars($lineItem['text'], ENT_QUOTES, 'UTF-8') ?>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <div class="qr-preset-item-actions">
                                <?php if ($lineItem['isUrl']): ?>
                                <a href="<?= htmlspecialchars($lineItem['href'], ENT_QUOTES, 'UTF-8') ?>" target="_blank" rel="noopener noreferrer" class="btn-preset-action btn-preset-open" title="Mở liên kết">
                                    <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                        <path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"></path>
                                        <polyline points="15 3 21 3 21 9"></polyline>
                                        <line x1="10" y1="14" x2="21" y2="3"></line>
                                    </svg>
                                    <span>Mở link</span>
                                </a>
                                <?php endif; ?>
                                <button type="button" class="btn-preset-action btn-preset-copy-item" data-text="<?= htmlspecialchars($lineItem['text'], ENT_QUOTES, 'UTF-8') ?>" title="Sao chép">
                                    <svg class="copy-icon" width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                        <rect x="9" y="9" width="13" height="13" rx="2" ry="2"/>
                                        <path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"/>
                                    </svg>
                                    <svg class="check-icon hidden" width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="#10b981" stroke-width="2.5">
                                        <polyline points="20 6 9 17 4 12" />
                                    </svg>
                                    <span class="btn-copy-label">Copy</span>
                                </button>
                                <button type="button" class="btn-preset-action btn-preset-qr btn-preset-use-item" data-text="<?= htmlspecialchars($lineItem['text'], ENT_QUOTES, 'UTF-8') ?>" title="Tạo mã QR cho mã này">
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
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
