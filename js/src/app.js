/**
 * KaiMail Production Entrypoint
 * Native ES Module Bootstrap
 */

import { KaiMailUserPage } from "./modules/mail/mail.controller.js";

function boot() {
    if (window.__kaimail_initialized) return;
    window.__kaimail_initialized = true;

    const app = new KaiMailUserPage();
    app.init();
    window.kaimail = app;
}

if (document.readyState === "loading") {
    document.addEventListener("DOMContentLoaded", boot);
} else {
    boot();
}
