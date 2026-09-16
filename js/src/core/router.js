/**
 * KaiMail Router
 * URL routing and SPA history management for modes: mail, 2fa, qr, docs.
 */

export class KaiMailRouter {
    constructor(baseUrl = "") {
        this.baseUrl = String(baseUrl || "").trim().replace(/\/+$/, "");
        this.basePath = this.extractBasePath(this.baseUrl);
        this.listeners = [];
        this.bindEvents();
    }

    extractBasePath(url) {
        if (!url) return "";
        try {
            const parsed = new URL(url, window.location.origin);
            const path = parsed.pathname.replace(/\/+$/, "");
            return path === "/" ? "" : path;
        } catch {
            return "";
        }
    }

    bindEvents() {
        window.addEventListener("popstate", () => {
            const route = this.getCurrentRoute();
            this.notify(route);
        });
    }

    getCurrentRoute() {
        const path = window.location.pathname.replace(/\/+$/, "");
        const twofaPath = (this.basePath + "/2fa").replace(/\/+$/, "");
        const docsPath = (this.basePath + "/docs").replace(/\/+$/, "");
        const userDocsPath = (this.basePath + "/user_docs").replace(/\/+$/, "");
        const qrPath = (this.basePath + "/qr").replace(/\/+$/, "");
        const search = new URLSearchParams(window.location.search);

        if (path === docsPath || path === userDocsPath || search.get("mode") === "docs") {
            return { mode: "docs", email: "" };
        }

        if (path === twofaPath || search.get("mode") === "twofa") {
            return { mode: "twofa", email: "" };
        }

        if (path === qrPath || search.get("mode") === "qr") {
            return { mode: "qr", email: "" };
        }

        let email = String(search.get("email") || "").trim().toLowerCase();
        if (!email && path.includes("@")) {
            const base = this.basePath.replace(/\/+$/, "");
            const raw = (base !== "" && path.startsWith(base))
                ? path.slice(base.length).replace(/^\/+/, "")
                : path.replace(/^\/+/, "");
            const decoded = decodeURIComponent(raw);
            if (!decoded.includes("/") && decoded.includes("@")) {
                email = decoded.trim().toLowerCase();
            }
        }

        return { mode: "mail", email };
    }

    navigate(mode, email = "", replace = false) {
        let targetPath = this.basePath || "";

        if (mode === "docs") {
            targetPath = (this.basePath || "") + "/docs";
        } else if (mode === "twofa") {
            targetPath = (this.basePath || "") + "/2fa";
        } else if (mode === "qr") {
            targetPath = (this.basePath || "") + "/qr";
        } else {
            const cleanEmail = String(email || "").trim().toLowerCase();
            if (cleanEmail && cleanEmail.includes("@")) {
                const encodedEmail = encodeURIComponent(cleanEmail).replace(/%40/g, "@");
                targetPath = (this.basePath ? `${this.basePath}/${encodedEmail}` : `/${encodedEmail}`);
            } else {
                targetPath = (this.basePath ? `${this.basePath}/` : "/");
            }
        }

        const fullUrl = targetPath.replace(/\/{2,}/g, "/");
        const currentUrl = (window.location.pathname + window.location.search).replace(/\/{2,}/g, "/");

        if (currentUrl !== fullUrl) {
            if (replace) {
                window.history.replaceState({ mode, email }, "", fullUrl);
            } else {
                window.history.pushState({ mode, email }, "", fullUrl);
            }
        }
    }

    onRoute(callback) {
        if (typeof callback === "function") {
            this.listeners.push(callback);
        }
    }

    notify(route) {
        this.listeners.forEach((cb) => {
            try {
                cb(route);
            } catch (err) {
                console.error("Router listener error:", err);
            }
        });
    }
}
