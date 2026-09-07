<?php
declare(strict_types=1);

/**
 * Shared layout renderer for public user-facing pages (Home, 2FA, Docs, etc.)
 */
final class UserLayout
{
    /**
     * Render the unified user topbar.
     *
     * @param string $activeTab 'mail' | 'twofa' | 'docs'
     * @param bool   $isSinglePageApp True if on index.php (uses buttons for mail/twofa with data-mode), False if multi-page (uses <a> links)
     */
    public static function renderTopbar(string $activeTab = 'mail', bool $isSinglePageApp = false): void
    {
        $siteUrl = rtrim((string) (defined('BASE_URL') ? BASE_URL : ''), '/');
        if ($siteUrl === '') {
            $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https://' : 'http://';
            $siteUrl = $protocol . ($_SERVER['HTTP_HOST'] ?? 'localhost');
        }

        $logoVersion = @filemtime(__DIR__ . '/../assets/logo.json') ?: time();
        $safeSiteUrl = htmlspecialchars($siteUrl, ENT_QUOTES, 'UTF-8');

        $isMailActive = $activeTab === 'mail';
        $isTwofaActive = $activeTab === 'twofa';
        $isDocsActive = $activeTab === 'docs';

        echo <<<HTML
        <header class="user-topbar">
            <a href="{$safeSiteUrl}/" class="brand-block" aria-label="KaiMail - Temp Mail">
                <div class="brand-logo-anim" aria-hidden="true">
                    <lottie-player
                        id="brandLottieLogo"
                        src="{$safeSiteUrl}/assets/logo.json?v={$logoVersion}"
                        background="transparent"
                        speed="0.75"
                        loop
                        autoplay
                    ></lottie-player>
                </div>
            </a>

            <div class="app-mode-selector" role="tablist">
HTML;

        if ($isSinglePageApp) {
            $mailClass = $isMailActive ? 'mode-tab active' : 'mode-tab';
            $twofaClass = $isTwofaActive ? 'mode-tab active' : 'mode-tab';
            $mailSelected = $isMailActive ? 'true' : 'false';
            $twofaSelected = $isTwofaActive ? 'true' : 'false';

            echo <<<HTML
                <button class="{$mailClass}" data-mode="mail" role="tab" aria-selected="{$mailSelected}">
                    <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1-0-2-.9-2-2V6c0-1.1.9-2 2-2z" />
                        <polyline points="22,6 12,13 2,6" />
                    </svg>
                    <span>Temp Mail</span>
                </button>
                <button class="{$twofaClass}" data-mode="twofa" role="tab" aria-selected="{$twofaSelected}">
                    <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <rect x="3" y="11" width="18" height="11" rx="2" ry="2" />
                        <path d="M7 11V7a5 5 0 0 1 10 0v4" />
                    </svg>
                    <span>GET 2FA</span>
                </button>
HTML;
        } else {
            $mailClass = $isMailActive ? 'mode-tab active' : 'mode-tab';
            $twofaClass = $isTwofaActive ? 'mode-tab active' : 'mode-tab';

            echo <<<HTML
                <a href="{$safeSiteUrl}/" class="{$mailClass}" role="tab" style="text-decoration: none;">
                    <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1-0-2-.9-2-2V6c0-1.1.9-2 2-2z" />
                        <polyline points="22,6 12,13 2,6" />
                    </svg>
                    <span>Temp Mail</span>
                </a>
                <a href="{$safeSiteUrl}/2fa" class="{$twofaClass}" role="tab" style="text-decoration: none;">
                    <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <rect x="3" y="11" width="18" height="11" rx="2" ry="2" />
                        <path d="M7 11V7a5 5 0 0 1 10 0v4" />
                    </svg>
                    <span>GET 2FA</span>
                </a>
HTML;
        }

        $docsClass = $isDocsActive ? 'mode-tab active' : 'mode-tab';

        echo <<<HTML
                <a href="{$safeSiteUrl}/docs" class="{$docsClass}" style="text-decoration: none;" title="Tài liệu tích hợp API cho lập trình viên">
                    <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <polyline points="16 18 22 12 16 6"></polyline>
                        <polyline points="8 6 2 12 8 18"></polyline>
                    </svg>
                    <span>Docs API</span>
                </a>
            </div>

            <div class="topbar-clock" id="topbarClock" title="Giờ chuẩn Việt Nam (GMT+7)">
                <span class="clock-time" id="clockTime">--:--:--</span>
            </div>
        </header>
        <script>
            (function() {
                function updateClock() {
                    var el = document.getElementById('clockTime');
                    if (!el) return;
                    var now = new Date();
                    el.textContent = now.toLocaleTimeString('vi-VN', { timeZone: 'Asia/Ho_Chi_Minh', hour12: false });
                }
                updateClock();
                setInterval(updateClock, 1000);
            })();
        </script>
HTML;
    }

    /**
     * Render the unified user footer.
     */
    public static function renderFooter(): void
    {
        echo <<<HTML
        <footer class="user-page-footer">
            <div class="footer-container">
                <div class="footer-left">
                    <span class="footer-brand-title">KaiMail</span>
                    <span class="footer-sep" aria-hidden="true">•</span>
                    <span class="footer-desc">Dịch vụ Temp Mail &amp; Trình xác thực 2FA miễn phí</span>
                </div>
                <div class="footer-right">
                    <span class="footer-eco-label">Hệ sinh thái:</span>
                    <a href="https://kaishop.id.vn/" target="_blank" rel="noopener noreferrer" class="footer-link footer-link-kai">
                        <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                            <circle cx="12" cy="12" r="10"></circle>
                            <line x1="2" y1="12" x2="22" y2="12"></line>
                            <path d="M12 2a15.3 15.3 0 0 1 4 10 15.3 15.3 0 0 1-4 10 15.3 15.3 0 0 1-4-10z"></path>
                        </svg>
                        <span>kaishop.id.vn</span>
                    </a>
                    <a href="https://t.me/KaiHub_bot" target="_blank" rel="noopener noreferrer" class="footer-link footer-link-tg">
                        <svg width="13" height="13" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true">
                            <path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm4.64 6.8c-.15 1.58-.8 5.42-1.13 7.19-.14.75-.42 1-.68 1.03-.58.05-1.02-.38-1.58-.75-.88-.58-1.38-.94-2.23-1.5-.99-.65-.35-1.01.22-1.59.15-.15 2.71-2.48 2.76-2.69.01-.03.01-.14-.07-.19-.08-.05-.19-.02-.27 0-.12.03-1.99 1.27-5.62 3.72-.53.36-1.01.54-1.44.53-.47-.01-1.38-.27-2.06-.49-.83-.27-1.49-.42-1.43-.88.03-.24.37-.49 1.02-.75 3.98-1.73 6.64-2.88 7.97-3.44 3.8-1.58 4.59-1.86 5.1-1.87.11 0 .37.03.53.17.14.12.18.28.2.45-.01.07.01.23 0 .32z" />
                        </svg>
                        <span>@KaiHub_bot</span>
                    </a>
                </div>
            </div>
        </footer>
HTML;
    }
}
