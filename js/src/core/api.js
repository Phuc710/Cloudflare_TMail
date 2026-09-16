/**
 * KaiMail API Client
 * Manages HTTP requests (GET, POST, DELETE), AbortController timeouts, and UI auth headers.
 */

export class KaiMailApi {
    constructor(baseUrl, webToken) {
        this.baseUrl = String(baseUrl || "").trim().replace(/\/+$/, "");
        this.webToken = String(webToken || "").trim();
        this.requestTimeoutMs = 12000;
    }

    buildUrl(path, query = {}) {
        const cleanPath = path.startsWith("/") ? path : `/${path}`;
        const url = `${this.baseUrl}${cleanPath}`;
        const params = new URLSearchParams();

        Object.entries(query).forEach(([k, v]) => {
            if (v === null || v === undefined || v === "") return;
            params.set(k, String(v));
        });

        const queryString = params.toString();
        return queryString === "" ? url : `${url}?${queryString}`;
    }

    buildHeaders() {
        const headers = { Accept: "application/json" };
        if (this.webToken !== "") {
            headers["X-WEB-UI-TOKEN"] = this.webToken;
        }
        return headers;
    }

    async getJson(path, query = {}) {
        const controller = new AbortController();
        const timeoutId = setTimeout(() => controller.abort(), this.requestTimeoutMs);
        try {
            const response = await fetch(this.buildUrl(path, query), {
                method: "GET",
                headers: this.buildHeaders(),
                credentials: "same-origin",
                cache: "no-store",
                signal: controller.signal,
            });

            let data = null;
            try {
                data = await response.json();
            } catch (error) {
                data = null;
            }

            return { ok: response.ok, status: response.status, data };
        } catch (error) {
            if (error?.name === "AbortError") {
                throw new Error("Kết nối chậm, vui lòng thử lại");
            }
            throw error;
        } finally {
            clearTimeout(timeoutId);
        }
    }

    fetchMessages(email, limit = 25) {
        return this.getJson("/api/messages.php", { email, limit });
    }

    fetchMessageById(id, email = "") {
        return this.getJson("/api/messages.php", { id, email });
    }

    async postJson(path, body = {}) {
        const controller = new AbortController();
        const timeoutId = setTimeout(() => controller.abort(), this.requestTimeoutMs);
        try {
            const headers = this.buildHeaders();
            headers["Content-Type"] = "application/json";

            const response = await fetch(this.buildUrl(path), {
                method: "POST",
                headers,
                credentials: "same-origin",
                cache: "no-store",
                body: JSON.stringify(body),
                signal: controller.signal,
            });

            let data = null;
            try {
                data = await response.json();
            } catch {
                data = null;
            }

            return { ok: response.ok, status: response.status, data };
        } catch (error) {
            if (error?.name === "AbortError") {
                throw new Error("Kết nối chậm, vui lòng thử lại");
            }
            throw error;
        } finally {
            clearTimeout(timeoutId);
        }
    }

    async deleteJson(path, query = {}, body = null) {
        const controller = new AbortController();
        const timeoutId = setTimeout(() => controller.abort(), this.requestTimeoutMs);
        try {
            const headers = this.buildHeaders();
            const options = {
                method: "DELETE",
                headers,
                credentials: "same-origin",
                cache: "no-store",
                signal: controller.signal,
            };

            if (body !== null) {
                headers["Content-Type"] = "application/json";
                options.body = JSON.stringify(body);
            }

            const response = await fetch(this.buildUrl(path, query), options);

            let data = null;
            try {
                data = await response.json();
            } catch {
                data = null;
            }

            return { ok: response.ok, status: response.status, data };
        } catch (error) {
            if (error?.name === "AbortError") {
                throw new Error("Kết nối chậm, vui lòng thử lại");
            }
            throw error;
        } finally {
            clearTimeout(timeoutId);
        }
    }

    createEmail(payload = {}) {
        return this.postJson("/api/emails.php", payload);
    }

    deleteEmail(email) {
        return this.deleteJson("/api/emails.php", { email });
    }

    fetchDomains() {
        return this.getJson("/api/domains.php");
    }
}
