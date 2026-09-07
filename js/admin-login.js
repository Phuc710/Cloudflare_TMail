/**
 * KaiMail Admin Login page logic (OOP).
 */
class AdminAccessKeyStore {
    constructor() {
        // Keys are stored securely in HttpOnly cookies
    }

    normalize(rawValue) {
        const raw = String(rawValue || "").trim();
        const envPrefix = "ADMIN_ACCESS_KEY=";

        if (raw.startsWith(envPrefix)) {
            return raw.slice(envPrefix.length).trim();
        }

        return raw;
    }
}

class AdminAuthApiClient {
    constructor(baseUrl, authEndpoint) {
        this.baseUrl = String(baseUrl || "").replace(/\/+$/, "");
        this.authEndpoint = this.normalizePath(authEndpoint || "/api/admin/auth.php");
    }

    normalizePath(path) {
        const raw = String(path || "").trim();
        if (raw === "") {
            return "/api/admin/auth.php";
        }

        if (/^https?:\/\//i.test(raw)) {
            return raw;
        }

        return raw.startsWith("/") ? raw : `/${raw}`;
    }

    buildUrl(path) {
        if (/^https?:\/\//i.test(path)) {
            return path;
        }

        return `${this.baseUrl}${path}`;
    }

    async verify() {
        try {
            const response = await fetch(this.buildUrl(this.authEndpoint), {
                method: "GET"
            });

            if (response.ok) {
                return { ok: true, status: response.status, message: "Xác thực thành công" };
            }

            const data = await this.readJsonSafe(response);
            return {
                ok: false,
                status: response.status,
                message: String(data?.message || data?.error || "Khóa truy cập không đúng"),
            };
        } catch (error) {
            return { ok: false, status: 0, message: "Không thể kết nối máy chủ" };
        }
    }

    async login(password) {
        if (!password) {
            return { ok: false, status: 0, message: "Vui lòng nhập khóa truy cập" };
        }

        try {
            const response = await fetch(this.buildUrl(this.authEndpoint), {
                method: "POST",
                headers: {
                    "Content-Type": "application/json"
                },
                body: JSON.stringify({ password })
            });

            const data = await this.readJsonSafe(response);
            if (response.ok && data?.success) {
                return { ok: true, status: response.status, message: "Đăng nhập thành công" };
            }

            return {
                ok: false,
                status: response.status,
                message: String(data?.message || data?.error || "Khóa truy cập không đúng"),
            };
        } catch (error) {
            return { ok: false, status: 0, message: "Không thể kết nối máy chủ" };
        }
    }

    async loginWithStealth(token) {
        if (!token) {
            return { ok: false, status: 0, message: "Token không hợp lệ" };
        }

        try {
            // First attempt: API auth endpoint
            const response = await fetch(this.buildUrl(this.authEndpoint), {
                method: "POST",
                headers: { "Content-Type": "application/json" },
                body: JSON.stringify({ stealth_token: token })
            });

            const data = await this.readJsonSafe(response);
            if (response.ok && data?.success) {
                return { ok: true, status: response.status, message: "Đăng nhập thành công" };
            }

            // Fallback attempt: current login page endpoint
            const pageResp = await fetch(window.location.href, {
                method: "POST",
                headers: { "Content-Type": "application/json" },
                body: JSON.stringify({ stealth_token: token, action: "stealth_unlock" })
            });

            const pageData = await this.readJsonSafe(pageResp);
            return {
                ok: pageResp.ok && Boolean(pageData?.ok),
                status: pageResp.status,
                message: String(pageData?.message || "Đăng nhập thành công")
            };
        } catch (error) {
            return { ok: false, status: 0, message: "Không thể kết nối máy chủ" };
        }
    }

    async readJsonSafe(response) {
        try {
            return await response.json();
        } catch (error) {
            return null;
        }
    }
}

class AdminLoginPageController {
    constructor(options) {
        this.redirectUrl  = options.redirectUrl;
        this.authApi      = options.authApi;
        this.firstMouth   = null;   // 'left' or 'right'
        this.mouthTimer   = null;
        this.seqLocked    = false;  // prevents duplicate unlock
    }

    init() {
        this.setupMouthSecretKnock();
        this.setupDecoyInteractions();
        this.setupFakeForm();
        this.setupCompanionCard();
        this.setupCrestEasterEggs();
        this.tryAutoLogin();
    }

    // ═══ SECRET MECHANISM: Click Mouth of 2 Chibis (or Bugcats) ═══
    setupMouthSecretKnock() {
        const mouthLeft   = document.getElementById("chibiMouthLeft");
        const mouthRight  = document.getElementById("chibiMouthRight");
        const bugMouthL   = document.getElementById("bugcatMouthLeft");
        const bugMouthR   = document.getElementById("bugcatMouthRight");

        const chibiLeft   = document.getElementById("peekingLeft");
        const chibiRight  = document.getElementById("peekingRight");
        const bugcatL     = document.getElementById("bugcatLeft");
        const bugcatR     = document.getElementById("bugcatRight");

        const COMBO_WINDOW_MS = 4000;

        const resetCombo = () => {
            this.firstMouth = null;
            clearTimeout(this.mouthTimer);
        };

        const triggerMouthStep = async (side) => {
            if (this.seqLocked) return;

            if (side === "left") {
                // Bắt buộc bước 1: Click 1 cái TRÁI (Hoàn toàn ẩn, không có bất kỳ hiệu ứng nào)
                this.firstMouth = "left";
                clearTimeout(this.mouthTimer);

                // Cửa sổ 4s để click tiếp con PHẢI liên tiếp
                this.mouthTimer = setTimeout(() => {
                    resetCombo();
                }, COMBO_WINDOW_MS);

            } else if (side === "right") {
                // Bắt buộc bước 2: Click 1 cái PHẢI
                if (this.firstMouth === "left") {
                    // ĐÚNG CHUẨN: 1 TRÁI -> 1 PHẢI LIÊN TIẾP!
                    this.seqLocked = true;
                    resetCombo();
                    await this.triggerStealthUnlock();
                } else {
                    // Sai thứ tự: reset âm thầm, không phát bất kỳ tín hiệu nào
                    resetCombo();
                }
            }
        };

        // Determine if click event lands on or near mouth (calibrated to user clicks: L: X:23.2% Y:87.7%, R: X:63.7% Y:93.7%)
        const isMouthClick = (e, wrapper, side) => {
            if (e.target && (e.target.classList.contains("chibi-mouth-hotspot") || e.target.classList.contains("bugcat-mouth-hotspot"))) {
                return true;
            }
            if (!wrapper) return false;
            const rect = wrapper.getBoundingClientRect();
            const relY = (e.clientY - rect.top) / rect.height;
            const relX = (e.clientX - rect.left) / rect.width;
            if (side === "left") {
                // Calibrated mouth: center at X: 23.2%, Y: 87.7%
                return relX >= 0.05 && relX <= 0.44 && relY >= 0.70;
            }
            if (side === "right") {
                // Calibrated mouth: center at X: 63.7%, Y: 93.7%
                return relX >= 0.45 && relX <= 0.85 && relY >= 0.74;
            }
            return false;
        };

        // Explicit hotspot listeners
        if (mouthLeft) {
            mouthLeft.addEventListener("click", (e) => {
                e.stopPropagation();
                triggerMouthStep("left", chibiLeft);
            });
        }
        if (mouthRight) {
            mouthRight.addEventListener("click", (e) => {
                e.stopPropagation();
                triggerMouthStep("right", chibiRight);
            });
        }
        if (bugMouthL) {
            bugMouthL.addEventListener("click", (e) => {
                e.stopPropagation();
                triggerMouthStep("left", bugcatL);
            });
        }
        if (bugMouthR) {
            bugMouthR.addEventListener("click", (e) => {
                e.stopPropagation();
                triggerMouthStep("right", bugcatR);
            });
        }

        // Click outside chibis breaks consecutive combo
        document.addEventListener("click", (e) => {
            if (!e.target.closest(".peeking-chibi") && !e.target.closest(".bugcat-corner")) {
                resetCombo();
            }
        });

        this._isMouthClick = isMouthClick;
        this._triggerMouthStep = triggerMouthStep;
        this._resetCombo = resetCombo;
    }

    // ═══ STEALTH UNLOCK: DIRECT LOGIN INTO DASHBOARD (NO KEY PROMPT) ═══
    async triggerStealthUnlock() {
        const authOverlay = document.getElementById("authLoadingOverlay");
        const overlaySub  = document.getElementById("authOverlaySub");

        if (authOverlay) {
            authOverlay.classList.remove("hidden");
            requestAnimationFrame(() => authOverlay.classList.add("show"));
        }

        const stealthToken = String(document.body.dataset.stealthToken || "").trim();
        const result = await this.authApi.loginWithStealth(stealthToken);

        if (result.ok) {
            if (authOverlay) {
                const title = authOverlay.querySelector(".auth-loading-title");
                if (title) title.textContent = "🎉 Xác thực thành công!";
                if (overlaySub) overlaySub.textContent = "Đang chuyển hướng tới Dashboard...";
            }
            document.querySelectorAll(".peeking-chibi").forEach(c => c.classList.add("happy-jump"));
            setTimeout(() => this.redirectToDashboard(), 350);
            return;
        }

        if (authOverlay) {
            authOverlay.classList.remove("show");
            setTimeout(() => authOverlay.classList.add("hidden"), 200);
        }
        this.seqLocked = false;
        this.firstMouth = null;
    }

    // ═══ DECOY CLICKS: Clicking Mascots outside mouth tells user to enter Key ═══
    setupDecoyInteractions() {
        const chibiLeft   = document.getElementById("peekingLeft");
        const chibiRight  = document.getElementById("peekingRight");
        const bugcatL     = document.getElementById("bugcatLeft");
        const bugcatR     = document.getElementById("bugcatRight");

        const bubbleLeft  = document.getElementById("bubbleLeft");
        const bubbleRight = document.getElementById("bubbleRight");
        const bubbleBugL  = document.getElementById("bubbleBugLeft");
        const bubbleBugR  = document.getElementById("bubbleBugRight");

        const leftQuotes = [
            "Hii sếp! Nhập Khóa truy cập vào ô kia kìa~ ✨",
            "Hi đồ ngốc! Pass ở ô input kìa, bấm tui chi 😜",
            "Hí hí, chào sếp yêu! Nhập mã khóa để vào nha 💖",
            "Ủa alo? Chưa nhập Khóa truy cập kìa sếp! 🔐",
            "Hihi! Muốn vào thì nhập key bên trên nha~ 🌸"
        ];
        const rightQuotes = [
            "Hii bạn hiền! Nhập Security Key rồi bấm Xác thực nha 🚀",
            "Hi đồ ngốc, nhập pass vào ô đi chứ! 😜",
            "Hí hí, chào mừng sếp! Nhập mã khóa quản trị nè ⚡",
            "Bảo mật cấp cao: Phải có key mới vào được nha 🛡️",
            "Hi sếp! Hôm nay check bao nhiêu mail thế? 📬"
        ];
        const bugcatQuotesL = [
            "Meo meo! Hii sếp, nhập key vào ô đi! 🐾",
            "Hi đồ ngốc! Capoo cắn đấy, nhập pass đi 🐱",
            "Hí hí, gõ khóa vào ô input kìa sếp ơi! 🐟",
            "Capoo chào sếp! Nhập Security Key vào gateway nha ⚡"
        ];
        const bugcatQuotesR = [
            "Meo! Hii bạn, nhập mã khóa vào ô kìa~ 🐾",
            "Hi đồ ngốc, tui là mascot thôi, nhập pass đi! 😜",
            "Hí hí, Capoo hóng sếp vào dashboard nè! 🎉",
            "Bíp bíp! Nhập khóa truy cập quản trị vào nha 🛡️"
        ];

        const showBubble = (wrapper, bubble, quotes) => {
            if (!wrapper || !bubble) return;
            bubble.textContent = quotes[Math.floor(Math.random() * quotes.length)];
            wrapper.classList.remove("show-bubble");
            void wrapper.offsetWidth;
            wrapper.classList.add("show-bubble");
            clearTimeout(wrapper._bubbleTimer);
            wrapper._bubbleTimer = setTimeout(() => {
                wrapper.classList.remove("show-bubble");
            }, 3200);
        };

        const handleDecoyOrMouth = (e, wrapper, bubble, quotes, side) => {
            if (this._isMouthClick && this._isMouthClick(e, wrapper, side)) {
                this._triggerMouthStep(side, wrapper);
                return;
            }
            if (this._resetCombo) this._resetCombo();
            showBubble(wrapper, bubble, quotes);
        };

        // Clicking body of 4 corner mascots
        if (chibiLeft) {
            chibiLeft.addEventListener("click", (e) => handleDecoyOrMouth(e, chibiLeft, bubbleLeft, leftQuotes, "left"));
        }
        if (chibiRight) {
            chibiRight.addEventListener("click", (e) => handleDecoyOrMouth(e, chibiRight, bubbleRight, rightQuotes, "right"));
        }
        if (bugcatL) {
            bugcatL.addEventListener("click", (e) => handleDecoyOrMouth(e, bugcatL, bubbleBugL, bugcatQuotesL, "left"));
        }
        if (bugcatR) {
            bugcatR.addEventListener("click", (e) => handleDecoyOrMouth(e, bugcatR, bubbleBugR, bugcatQuotesR, "right"));
        }
    }

    // ═══ FULLY FUNCTIONAL FAKE LOGIN FORM (Kiểu bịp / lừa) ═══
    setupFakeForm() {
        const form        = document.getElementById("loginForm");
        const pwdInput    = document.getElementById("passwordInput");
        const toggleBtn   = document.getElementById("btnTogglePwd");
        const submitBtn   = document.getElementById("loginSubmitBtn");
        const submitText  = document.getElementById("loginSubmitText");
        const errorMsg    = document.getElementById("errorMsg");
        const loginBox    = document.getElementById("loginBox");
        const pwdGroup    = document.getElementById("pwdGroup");

        // Toggle password show/hide
        if (toggleBtn && pwdInput) {
            toggleBtn.addEventListener("click", (e) => {
                e.preventDefault();
                const isPwd = pwdInput.type === "password";
                pwdInput.type = isPwd ? "text" : "password";
            });
        }

        // Form submit decoy
        if (form) {
            form.addEventListener("submit", async (e) => {
                e.preventDefault();
                const val = String(pwdInput?.value || "").trim();

                if (!val) {
                    if (errorMsg) {
                        errorMsg.textContent = "Vui lòng nhập khóa truy cập!";
                        errorMsg.classList.remove("hidden");
                    }
                    if (pwdGroup) pwdGroup.classList.add("input-error");
                    if (loginBox) {
                        loginBox.classList.add("shake-form");
                        setTimeout(() => loginBox.classList.remove("shake-form"), 450);
                    }
                    pwdInput?.focus();
                    return;
                }

                // Simulate verifying password to trick the observer
                if (submitBtn) submitBtn.disabled = true;
                if (submitText) submitText.textContent = "Đang xác thực...";
                if (errorMsg) errorMsg.classList.add("hidden");
                if (pwdGroup) pwdGroup.classList.remove("input-error");

                await new Promise(r => setTimeout(r, 450));

                if (submitBtn) submitBtn.disabled = false;
                if (submitText) submitText.textContent = "Xác thực hệ thống";

                if (errorMsg) {
                    errorMsg.textContent = "Khóa truy cập không chính xác. Vui lòng kiểm tra lại!";
                    errorMsg.classList.remove("hidden");
                }
                if (pwdGroup) pwdGroup.classList.add("input-error");
                if (loginBox) {
                    loginBox.classList.add("shake-form");
                    setTimeout(() => loginBox.classList.remove("shake-form"), 450);
                }
                pwdInput?.select();
            });
        }
    }

    // ═══ Companion Card Decoy ═══
    setupCompanionCard() {
        const card = document.getElementById("companionCard");
        const sub  = document.getElementById("companionSub");
        const quotes = [
            "Vui lòng nhập Khóa truy cập vào ô input để đăng nhập 🔐",
            "Hệ thống đang chờ Security Key hợp lệ ⚡",
            "Hãy nhập mã bảo mật rồi bấm Xác thực hệ thống 🛡️",
            "Máy chủ bảo vệ an toàn: Yêu cầu khóa quản trị viên"
        ];

        if (card && sub) {
            card.addEventListener("click", () => {
                sub.textContent = quotes[Math.floor(Math.random() * quotes.length)];
                card.style.transform = "scale(1.02)";
                setTimeout(() => { card.style.transform = ""; }, 180);
            });
        }
    }

    // ═══ Crest Badges Decoy ═══
    setupCrestEasterEggs() {
        const legacy  = document.getElementById("legacyCrest");
        const partner = document.getElementById("partnerCrest");

        const pulseBadge = (el) => {
            if (!el) return;
            el.style.transform = "scale(1.15)";
            setTimeout(() => { el.style.transform = ""; }, 200);
        };

        if (legacy)  legacy.addEventListener("click", () => pulseBadge(legacy));
        if (partner) partner.addEventListener("click", () => pulseBadge(partner));
    }

    // ═══ Auto-login (if session cookie already valid) ═══
    async tryAutoLogin() {
        const result = await this.authApi.verify();
        if (result.ok) {
            this.redirectToDashboard();
        }
    }

    redirectToDashboard() {
        const raw = String(this.redirectUrl || "").trim();
        const dest = raw.endsWith("/") ? raw : `${raw}/`;
        window.location.assign(dest);
    }
}

document.addEventListener("DOMContentLoaded", () => {
    const html = document.documentElement;
    const body = document.body;

    const baseUrl       = String(html?.dataset.baseUrl || "").trim().replace(/\/+$/, "");
    const authEndpoint  = String(body?.dataset.authEndpoint || "/api/admin/auth.php").trim();
    let adminHomePath   = String(body?.dataset.adminHome || "/adminkaishop/").trim();
    if (!adminHomePath.startsWith("/")) adminHomePath = `/${adminHomePath}`;
    if (!adminHomePath.endsWith("/")) adminHomePath = `${adminHomePath}/`;

    const authApi = new AdminAuthApiClient(baseUrl, authEndpoint);

    const controller = new AdminLoginPageController({
        redirectUrl: `${baseUrl}${adminHomePath}`,
        authApi,
    });

    controller.init();
});
