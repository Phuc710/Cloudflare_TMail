<?php
declare(strict_types=1);

final class AdminLoginPage
{
    private const ADMIN_AUTH_API_PATH = '/api/admin/auth.php';
    private const ADMIN_DASHBOARD_PATH = '/adminkaishop/';
    private const ADMIN_STORAGE_KEY = 'kaimail_admin_access_key';

    public function __construct(private readonly string $baseUrl)
    {
    }

    public function render(): void
    {
        header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
        header('Pragma: no-cache');

        $baseUrl = $this->escape($this->baseUrl);
        $authEndpoint = $this->escape(self::ADMIN_AUTH_API_PATH);
        $dashboardPath = $this->escape(self::ADMIN_DASHBOARD_PATH);
        $storageKey = $this->escape(self::ADMIN_STORAGE_KEY);
        $adminCssHref = $this->escape($this->buildVersionedAssetUrl('/css/admin.css'));
        $adminLoginJsHref = $this->escape($this->buildVersionedAssetUrl('/js/admin-login.js'));

        $stealthToken = defined('ADMIN_ACCESS_KEY')
            ? hash_hmac('sha256', 'stealth_click_' . date('Y-m-d'), (string) ADMIN_ACCESS_KEY)
            : '';

        echo <<<HTML
<!DOCTYPE html>
<html lang="vi" data-base-url="{$baseUrl}">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Đăng nhập Admin - KaiMail</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&family=JetBrains+Mono:wght@400;500;600&family=Roboto:wght@400;500;700&display=swap" rel="stylesheet">
    <link rel="icon" type="image/png" href="{$baseUrl}/assets/kaishop_favicon.png">
    <link rel="shortcut icon" type="image/png" href="{$baseUrl}/assets/kaishop_favicon.png">
    <link rel="stylesheet" href="{$adminCssHref}">
    <style>
        /* ── Clean, Toned-down Background ── */
        .login-page {
            position: relative;
            width: 100vw;
            min-height: 100vh;
            display: flex;
            justify-content: center;
            align-items: center;
            padding: 24px;
            background: #090d16;
            background: radial-gradient(circle at 50% 30%, #111a28 0%, #070a12 100%);
            overflow: hidden;
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
        }
        .login-container {
            position: relative;
            width: 100%;
            max-width: 390px;
            z-index: 20;
            margin: auto;
        }
        .login-box {
            position: relative;
            background: rgba(15, 23, 42, 0.88);
            backdrop-filter: blur(20px);
            -webkit-backdrop-filter: blur(20px);
            border: 1px solid rgba(255, 255, 255, 0.08);
            border-radius: 20px;
            box-shadow: 0 20px 45px -10px rgba(0, 0, 0, 0.65);
            padding: 32px 28px 26px;
            transition: border-color 0.3s ease, box-shadow 0.3s ease, transform 0.2s ease;
        }

        /* ── Bugcat Corner Anchors (Top) ── */
        .bugcat-corner {
            position: fixed;
            top: 18px;
            z-index: 45;
            user-select: none;
            cursor: pointer;
            line-height: 0;
            transition: transform 0.25s cubic-bezier(0.16, 1, 0.3, 1), filter 0.25s ease;
            filter: drop-shadow(0 8px 18px rgba(0, 0, 0, 0.5));
        }
        .bugcat-corner.corner-left  {
            left: 22px;
            animation: bugcatFloatL 4.2s ease-in-out infinite alternate;
        }
        .bugcat-corner.corner-right {
            right: 22px;
            animation: bugcatFloatR 4.5s ease-in-out infinite alternate;
        }
        @keyframes bugcatFloatL {
            0%   { transform: translateY(0); }
            100% { transform: translateY(8px); }
        }
        @keyframes bugcatFloatR {
            0%   { transform: translateY(0); }
            100% { transform: translateY(8px); }
        }

        .bugcat-corner:hover {
            transform: scale(1.05);
            animation-play-state: paused;
        }
        .bugcat-corner.clicked {
            transform: scale(0.92) !important;
            transition: transform 0.12s ease !important;
        }

        .bugcat-img {
            display: block;
            width: clamp(88px, 8.5vw, 128px);
            height: auto;
            object-fit: contain;
            pointer-events: none;
        }

        /* ── Mouth Click Hotspots (Secret Knock) ── */
        .chibi-mouth-hotspot {
            position: absolute;
            cursor: pointer;
            z-index: 60 !important;
            background: transparent;
            pointer-events: auto !important;
        }
        .peeking-left .chibi-mouth-hotspot {
            bottom: 0 !important;
            left: 5% !important;
            width: 38% !important;
            height: 28% !important;
            border-radius: 16px;
        }
        .peeking-right .chibi-mouth-hotspot {
            bottom: 0 !important;
            left: 46% !important;
            right: auto !important;
            width: 38% !important;
            height: 24% !important;
            border-radius: 16px;
        }
        .bugcat-mouth-hotspot {
            position: absolute;
            cursor: pointer;
            z-index: 60 !important;
            pointer-events: auto !important;
            bottom: 0 !important;
            left: 10% !important;
            width: 80% !important;
            height: 55% !important;
            border-radius: 12px;
        }

        /* ── Speech Bubbles (ONLY on Decoy Click, Never on Hover) ── */
        .chibi-bubble {
            display: none;
            position: absolute;
            background: rgba(15, 23, 42, 0.98);
            color: #f1f5f9;
            border: 1.5px solid rgba(56, 189, 248, 0.5);
            padding: 8px 16px;
            border-radius: 14px;
            font-size: 0.82rem;
            font-weight: 600;
            white-space: nowrap;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.75), 0 0 16px rgba(56, 189, 248, 0.25);
            pointer-events: none;
            z-index: 100;
        }
        .peeking-left .chibi-bubble {
            top: -48px;
            left: 30px;
        }
        .peeking-right .chibi-bubble {
            top: -48px;
            right: 30px;
        }
        .corner-left .chibi-bubble {
            top: calc(100% + 8px);
            left: 10px;
        }
        .corner-right .chibi-bubble {
            top: calc(100% + 8px);
            right: 10px;
        }

        .chibi-bubble::after {
            content: '';
            position: absolute;
            border-style: solid;
        }
        .peeking-left .chibi-bubble::after {
            bottom: -6px;
            left: 24px;
            border-width: 6px 6px 0 6px;
            border-color: rgba(15, 23, 42, 0.98) transparent transparent transparent;
        }
        .peeking-right .chibi-bubble::after {
            bottom: -6px;
            right: 24px;
            border-width: 6px 6px 0 6px;
            border-color: rgba(15, 23, 42, 0.98) transparent transparent transparent;
        }
        .corner-left .chibi-bubble::after {
            top: -6px;
            left: 24px;
            border-width: 0 6px 6px 6px;
            border-color: transparent transparent rgba(15, 23, 42, 0.98) transparent;
        }
        .corner-right .chibi-bubble::after {
            top: -6px;
            right: 24px;
            border-width: 0 6px 6px 6px;
            border-color: transparent transparent rgba(15, 23, 42, 0.98) transparent;
        }

        /* ONLY show when .show-bubble class is added via click */
        .show-bubble > .chibi-bubble {
            display: block !important;
            opacity: 1 !important;
            transform: translateY(0) scale(1) !important;
            animation: bubblePopIn 0.22s cubic-bezier(0.16, 1, 0.3, 1) !important;
        }
        @keyframes bubblePopIn {
            0%   { opacity: 0; transform: scale(0.85) translateY(6px); }
            100% { opacity: 1; transform: scale(1) translateY(0); }
        }

        /* ── Header ── */
        .login-header {
            text-align: center;
            margin-bottom: 22px;
        }
        .login-header-showcase {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 16px;
            margin-bottom: 12px;
        }
        .crest-badge {
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 4px;
            user-select: none;
            transition: transform 0.2s ease;
        }
        .crest-badge:hover {
            transform: translateY(-2px);
        }
        .crest-gif {
            display: block;
            width: 42px;
            height: 38px;
            object-fit: contain;
            filter: drop-shadow(0 3px 8px rgba(0,0,0,0.4));
        }
        .crest-pill {
            font-family: 'JetBrains Mono', monospace;
            font-size: 0.6rem;
            font-weight: 700;
            letter-spacing: 0.05em;
            padding: 1px 6px;
            border-radius: 4px;
            text-transform: uppercase;
        }
        .crest-legacy  .crest-pill {
            color: #fef08a;
            background: rgba(234, 179, 8, 0.12);
            border: 1px solid rgba(234, 179, 8, 0.25);
        }
        .crest-partner .crest-pill {
            color: #a5f3fc;
            background: rgba(6, 182, 212, 0.12);
            border: 1px solid rgba(6, 182, 212, 0.25);
        }
        .login-logo-wrap {
            display: flex;
            justify-content: center;
            align-items: center;
        }
        .login-logo {
            width: 50px;
            height: 50px;
            object-fit: contain;
            filter: drop-shadow(0 4px 14px rgba(16, 185, 129, 0.28));
        }
        .login-header h1 {
            font-size: 1.45rem;
            font-weight: 700;
            color: #ffffff;
            letter-spacing: -0.02em;
            margin: 0;
        }
        .login-header .login-subtitle {
            color: #64748b;
            font-size: 0.8rem;
            margin-top: 5px;
            letter-spacing: 0.01em;
        }

        /* ── Fully Interactive Decoy Password Input ── */
        .form-group {
            margin-bottom: 16px;
        }
        .form-group label {
            display: flex;
            justify-content: space-between;
            align-items: center;
            color: #cbd5e1;
            font-size: 0.85rem;
            font-weight: 600;
            margin-bottom: 8px;
        }
        .label-badge {
            font-size: 0.65rem;
            font-weight: 600;
            color: #38bdf8;
            background: rgba(56, 189, 248, 0.1);
            padding: 1px 6px;
            border-radius: 4px;
            letter-spacing: 0.02em;
        }
        .password-input-group {
            position: relative;
            display: flex;
            align-items: center;
            width: 100%;
        }
        .password-input-group input {
            width: 100% !important;
            height: 46px !important;
            min-height: 46px !important;
            padding: 10px 44px 10px 14px !important;
            background: rgba(30, 41, 59, 0.5) !important;
            border: 1px solid rgba(148, 163, 184, 0.2) !important;
            border-radius: 10px !important;
            color: #f8fafc !important;
            font-size: 0.92rem !important;
            outline: none !important;
            box-sizing: border-box !important;
            transition: all 0.2s ease !important;
        }
        .password-input-group input:focus {
            border-color: #38bdf8 !important;
            box-shadow: 0 0 0 2px rgba(56, 189, 248, 0.25) !important;
        }
        .password-input-group input::placeholder {
            color: #64748b !important;
        }
        .password-input-group.input-error input {
            border-color: #ef4444 !important;
            box-shadow: 0 0 0 2px rgba(239, 68, 68, 0.25) !important;
        }

        .btn-toggle-pwd {
            position: absolute;
            right: 12px;
            top: 50%;
            transform: translateY(-50%);
            background: none;
            border: none;
            color: #64748b;
            cursor: pointer;
            padding: 4px;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: color 0.15s ease;
        }
        .btn-toggle-pwd:hover {
            color: #cbd5e1;
        }

        .btn-login {
            width: 100%;
            height: 46px;
            margin-top: 6px;
            background: #0284c7;
            background: linear-gradient(135deg, #0ea5e9 0%, #0284c7 100%);
            color: #ffffff;
            border: 1px solid rgba(56, 189, 248, 0.4);
            border-radius: 10px;
            font-size: 0.88rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s ease;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            box-shadow: 0 4px 12px rgba(2, 132, 199, 0.3);
        }
        .btn-login:hover {
            background: linear-gradient(135deg, #38bdf8 0%, #0369a1 100%);
            transform: translateY(-1px);
            box-shadow: 0 6px 16px rgba(2, 132, 199, 0.4);
        }
        .btn-login:active {
            transform: translateY(1px);
        }
        .btn-login:disabled {
            opacity: 0.65;
            cursor: not-allowed;
            transform: none;
        }

        .error-message {
            border: 1px solid rgba(239, 68, 68, 0.35);
            border-radius: 10px;
            background: rgba(239, 68, 68, 0.12);
            color: #fca5a5;
            padding: 9px 12px;
            font-size: 0.84rem;
            margin-bottom: 14px;
        }
        .status-message {
            border: 1px solid rgba(6, 182, 212, 0.35);
            border-radius: 10px;
            background: rgba(6, 182, 212, 0.12);
            color: #67e8f9;
            padding: 9px 12px;
            font-size: 0.84rem;
            margin-bottom: 14px;
        }

        /* Shake animation for fake login reject */
        .shake-form {
            animation: formShake 0.4s ease-in-out !important;
        }
        @keyframes formShake {
            0%, 100% { transform: translateX(0); }
            20% { transform: translateX(-8px); }
            40% { transform: translateX(8px); }
            60% { transform: translateX(-5px); }
            80% { transform: translateX(5px); }
        }

        /* ── Companion Card ── */
        .login-companion-card {
            display: flex;
            align-items: center;
            gap: 12px;
            margin-top: 18px;
            padding: 9px 12px;
            background: rgba(30, 41, 59, 0.35);
            border: 1px solid rgba(148, 163, 184, 0.1);
            border-radius: 12px;
            user-select: none;
            cursor: pointer;
            transition: all 0.2s ease;
        }
        .login-companion-card:hover {
            background: rgba(30, 41, 59, 0.55);
            border-color: rgba(56, 189, 248, 0.25);
        }
        .companion-avatar {
            position: relative;
            width: 40px;
            height: 40px;
            border-radius: 10px;
            overflow: hidden;
            background: #0f172a;
            border: 1px solid rgba(56, 189, 248, 0.25);
            flex-shrink: 0;
        }
        .companion-gif {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        .companion-dot {
            position: absolute;
            bottom: 2px;
            right: 2px;
            width: 7px;
            height: 7px;
            border-radius: 50%;
            background: #10b981;
            border: 1px solid #0f172a;
            box-shadow: 0 0 5px #10b981;
        }
        .companion-text {
            flex: 1;
            min-width: 0;
        }
        .companion-name {
            display: flex;
            align-items: center;
            gap: 6px;
            font-size: 0.8rem;
            color: #f1f5f9;
        }
        .companion-badge {
            font-size: 0.62rem;
            font-weight: 700;
            color: #34d399;
            background: rgba(16, 185, 129, 0.12);
            padding: 1px 5px;
            border-radius: 4px;
            text-transform: uppercase;
        }
        .companion-sub {
            font-size: 0.74rem;
            color: #94a3b8;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            margin-top: 2px;
        }

        /* ── Auth Overlay ── */
        .auth-loading-overlay {
            position: fixed;
            inset: 0;
            z-index: 100;
            background: rgba(8, 12, 22, 0.88);
            backdrop-filter: blur(12px);
            -webkit-backdrop-filter: blur(12px);
            display: flex;
            align-items: center;
            justify-content: center;
            opacity: 0;
            pointer-events: none;
            transition: opacity 0.25s ease;
        }
        .auth-loading-overlay.show {
            opacity: 1;
            pointer-events: auto;
        }
        .auth-loading-card {
            background: rgba(15, 23, 42, 0.96);
            border: 1px solid rgba(56, 189, 248, 0.3);
            border-radius: 20px;
            padding: 26px 32px;
            text-align: center;
            box-shadow: 0 20px 50px rgba(0, 0, 0, 0.8);
            display: flex;
            flex-direction: column;
            align-items: center;
            max-width: 300px;
            width: 88%;
            animation: authPopIn 0.25s cubic-bezier(0.16, 1, 0.3, 1);
        }
        @keyframes authPopIn {
            from { transform: scale(0.92); opacity: 0; }
            to   { transform: scale(1);    opacity: 1; }
        }
        .auth-loading-avatar {
            width: 76px;
            height: 76px;
            border-radius: 16px;
            overflow: hidden;
            margin-bottom: 12px;
            border: 1.5px solid rgba(56, 189, 248, 0.35);
        }
        .auth-loading-gif {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        .auth-loading-title {
            font-size: 1rem;
            font-weight: 700;
            color: #f8fafc;
            margin-bottom: 4px;
        }
        .auth-loading-sub {
            font-size: 0.8rem;
            color: #94a3b8;
            margin-bottom: 14px;
        }
        .auth-progress-track {
            width: 100%;
            height: 4px;
            background: rgba(255, 255, 255, 0.08);
            border-radius: 9999px;
            overflow: hidden;
        }
        .auth-progress-fill {
            width: 50%;
            height: 100%;
            background: linear-gradient(90deg, #10b981, #06b6d4);
            border-radius: 9999px;
            animation: authProgressAnim 1.1s ease-in-out infinite alternate;
        }
        @keyframes authProgressAnim {
            0%   { transform: translateX(-50%); }
            100% { transform: translateX(150%); }
        }

        /* ── Peeking Chibis (bottom corners) ── */
        .peeking-chibi {
            position: fixed !important;
            bottom: 0 !important;
            z-index: 30 !important;
            user-select: none !important;
            cursor: pointer !important;
            pointer-events: auto !important;
            line-height: 0;
            filter: drop-shadow(0 10px 24px rgba(0, 0, 0, 0.5));
            transition: transform 0.2s cubic-bezier(0.16, 1, 0.3, 1), filter 0.2s ease !important;
            animation: none !important; /* Disable floating during click so target doesn't drift */
        }
        .peeking-chibi img {
            width: clamp(220px, 17vw, 300px) !important;
            height: auto !important;
            display: block;
            pointer-events: none !important; /* Clicks pass cleanly to container and hotspots */
        }
        .peeking-chibi.peeking-left  { left: 0 !important; bottom: 0 !important; transform: none !important; }
        .peeking-chibi.peeking-right { right: 0 !important; bottom: 0 !important; transform: none !important; }
        .peeking-chibi.peeking-left:hover {
            transform: translateY(-4px) scale(1.02) !important;
        }
        .peeking-chibi.peeking-right:hover {
            transform: translateY(-4px) scale(1.02) !important;
        }
        .peeking-chibi.happy-jump {
            animation: chibiJump 0.55s cubic-bezier(0.16, 1, 0.3, 1) forwards !important;
        }
        @keyframes chibiJump {
            0%   { transform: translateY(0); }
            40%  { transform: translateY(-28px) scale(1.08); }
            70%  { transform: translateY(-10px) scale(1.03); }
            100% { transform: translateY(-16px) scale(1.05); }
        }

        @media (max-width: 768px) {
            .bugcat-img { width: clamp(68px, 8vw, 92px); }
            .peeking-chibi img { width: 130px !important; }
            .peeking-chibi { opacity: 0.85; }
        }
    </style>
</head>
<body class="login-page" data-auth-endpoint="{$authEndpoint}" data-admin-home="{$dashboardPath}" data-storage-key="{$storageKey}" data-stealth-token="{$stealthToken}">

    <!-- ═══ Mascot Corners (Top) ═══ -->
    <div class="bugcat-corner corner-left" id="bugcatLeft" role="button" tabindex="0" aria-label="Mascot Left">
        <div class="chibi-bubble" id="bubbleBugLeft"></div>
        <img src="{$baseUrl}/assets/bugcat.gif" alt="Mascot" class="bugcat-img">
        <div class="bugcat-mouth-hotspot" id="bugcatMouthLeft"></div>
    </div>
    <div class="bugcat-corner corner-right" id="bugcatRight" role="button" tabindex="0" aria-label="Mascot Right">
        <div class="chibi-bubble" id="bubbleBugRight"></div>
        <img src="{$baseUrl}/assets/bugcat.gif" alt="Mascot" class="bugcat-img" style="transform: scaleX(-1);">
        <div class="bugcat-mouth-hotspot" id="bugcatMouthRight"></div>
    </div>

    <!-- ═══ Peeking Chibis (Bottom) ═══ -->
    <div class="peeking-chibi peeking-left" id="peekingLeft">
        <div class="chibi-bubble" id="bubbleLeft"></div>
        <img src="{$baseUrl}/assets/left.png" alt="Chibi Left" width="260" height="260" loading="eager">
        <div class="chibi-mouth-hotspot" id="chibiMouthLeft" title=""></div>
    </div>
    <div class="peeking-chibi peeking-right" id="peekingRight">
        <div class="chibi-bubble" id="bubbleRight"></div>
        <img src="{$baseUrl}/assets/right.png" alt="Chibi Right" width="260" height="260" loading="eager">
        <div class="chibi-mouth-hotspot" id="chibiMouthRight" title=""></div>
    </div>

    <!-- ═══ Center Card ═══ -->
    <div class="login-container">
        <div class="login-box" id="loginBox">
            <div class="login-header">
                <div class="login-header-showcase">
                    <div class="crest-badge crest-legacy" id="legacyCrest">
                        <img src="{$baseUrl}/assets/legacy.gif" alt="Legacy" class="crest-gif" width="42" height="38">
                        <span class="crest-pill">LEGACY</span>
                    </div>
                    <div class="login-logo-wrap">
                        <img src="{$baseUrl}/assets/logo.png" alt="KaiMail Logo" class="login-logo" width="50" height="50">
                    </div>
                    <div class="crest-badge crest-partner" id="partnerCrest">
                        <img src="{$baseUrl}/assets/partner.gif" alt="Partner" class="crest-gif" width="42" height="38">
                        <span class="crest-pill">PARTNER</span>
                    </div>
                </div>

                <h1>KaiMail Admin</h1>
                <p class="login-subtitle">Cổng xác thực an ninh hệ thống</p>
            </div>

            <div id="loginStatus" class="status-message hidden" aria-live="polite"></div>
            <div id="errorMsg" class="error-message hidden" aria-live="assertive"></div>

            <form id="loginForm" novalidate>
                <div class="form-group">
                    <label for="passwordInput">
                        <span>Khóa truy cập</span>
                        <span class="label-badge">Security Key</span>
                    </label>
                    <div class="password-input-group" id="pwdGroup">
                        <input
                            type="password"
                            id="passwordInput"
                            name="password"
                            placeholder="Nhập khóa truy cập..."
                            autocomplete="off"
                        >
                        <button type="button" class="btn-toggle-pwd" id="btnTogglePwd" aria-label="Hiện/ẩn mật khẩu">
                            <svg class="eye-icon eye-off" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24"></path>
                                <line x1="1" y1="1" x2="23" y2="23"></line>
                            </svg>
                        </button>
                    </div>
                </div>
                <button type="submit" id="loginSubmitBtn" class="btn-login">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M15 3h4a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2h-4"></path>
                        <polyline points="10 17 15 12 10 7"></polyline>
                        <line x1="15" y1="12" x2="3" y2="12"></line>
                    </svg>
                    <span id="loginSubmitText">Xác thực hệ thống</span>
                </button>
            </form>

            <div class="login-companion-card" id="companionCard">
                <div class="companion-avatar">
                    <img src="{$baseUrl}/assets/loader-2.gif" alt="Kai Assistant" class="companion-gif">
                    <span class="companion-dot" title="Active"></span>
                </div>
                <div class="companion-text">
                    <div class="companion-name">
                        <strong>Kai Assistant</strong>
                        <span class="companion-badge">Active</span>
                    </div>
                    <div class="companion-sub" id="companionSub">Vui lòng nhập Khóa truy cập để tiếp tục 🛡️</div>
                </div>
            </div>
        </div>
    </div>

    <!-- Auth Loading Overlay (loader-2.gif) -->
    <div id="authLoadingOverlay" class="auth-loading-overlay hidden" aria-hidden="true">
        <div class="auth-loading-card">
            <div class="auth-loading-avatar">
                <img src="{$baseUrl}/assets/loader-2.gif" alt="Authenticating..." class="auth-loading-gif">
            </div>
            <div class="auth-loading-title">Đang xác thực hệ thống...</div>
            <div class="auth-loading-sub" id="authOverlaySub">Mở cổng truy cập quản trị viên</div>
            <div class="auth-progress-track">
                <div class="auth-progress-fill"></div>
            </div>
        </div>
    </div>

    <script src="{$adminLoginJsHref}" defer></script>
</body>
</html>
HTML;
    }

    private function escape(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
    }

    private function buildVersionedAssetUrl(string $assetPath): string
    {
        return \KaiMail\Core\Services\AssetService::url($assetPath);
    }
}
