/**
 * KaiMail Topbar Vietnam Clock
 * Keeps the topbar clock in sync with Asia/Ho_Chi_Minh time (UTC+7).
 */

export function initVietnamClock() {
    const clockEl = document.getElementById("clockTime");
    if (!clockEl) return;

    const updateClock = () => {
        try {
            const now = new Date();
            clockEl.textContent = now.toLocaleTimeString("vi-VN", {
                timeZone: "Asia/Ho_Chi_Minh",
                hour12: false,
                hour: "2-digit",
                minute: "2-digit",
                second: "2-digit"
            });
        } catch {
            const now = new Date();
            const utc = now.getTime() + (now.getTimezoneOffset() * 60000);
            const vnTime = new Date(utc + (3600000 * 7));
            const pad = (n) => String(n).padStart(2, "0");
            clockEl.textContent = `${pad(vnTime.getHours())}:${pad(vnTime.getMinutes())}:${pad(vnTime.getSeconds())}`;
        }
    };

    updateClock();
    setInterval(updateClock, 1000);
}
