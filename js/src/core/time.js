/**
 * KaiMail Time Utility
 * Time formatting, parsing, relative time calculation for Asia/Ho_Chi_Minh timezone.
 */

export class KaiMailTime {
    constructor() {
        this.vnTimeZone = "Asia/Ho_Chi_Minh";
    }

    parse(value) {
        if (value instanceof Date) {
            return Number.isNaN(value.getTime()) ? null : value;
        }

        const raw = String(value || "").trim();
        if (raw === "") return null;

        const sqlMatch = raw.match(
            /^(\d{4})-(\d{2})-(\d{2})(?:[ T](\d{2}):(\d{2})(?::(\d{2}))?)?$/
        );
        const hasZone = /[zZ]$|[+-]\d{2}:\d{2}$/.test(raw);

        if (sqlMatch && !hasZone) {
            const year = Number(sqlMatch[1]);
            const month = Number(sqlMatch[2]);
            const day = Number(sqlMatch[3]);
            const hour = Number(sqlMatch[4] || "0");
            const minute = Number(sqlMatch[5] || "0");
            const second = Number(sqlMatch[6] || "0");
            const utcMs = Date.UTC(year, month - 1, day, hour - 7, minute, second);
            return new Date(utcMs);
        }

        const parsed = new Date(raw);
        return Number.isNaN(parsed.getTime()) ? null : parsed;
    }

    getVnParts(value) {
        const date = this.parse(value);
        if (!date) return null;

        const parts = new Intl.DateTimeFormat("en-GB", {
            timeZone: this.vnTimeZone,
            hour12: false,
            year: "numeric",
            month: "2-digit",
            day: "2-digit",
            hour: "2-digit",
            minute: "2-digit",
            second: "2-digit",
        }).formatToParts(date);

        const map = Object.fromEntries(parts.map((p) => [p.type, p.value]));
        return {
            year: map.year,
            month: map.month,
            day: map.day,
            hour: map.hour,
            minute: map.minute,
            second: map.second,
        };
    }

    formatRelative(value) {
        const date = this.parse(value);
        if (!date) return "";

        const diff = Date.now() - date.getTime();
        const minutes = Math.floor(diff / 60000);
        const hours = Math.floor(diff / 3600000);
        const days = Math.floor(diff / 86400000);

        if (minutes < 1) return "Vừa xong";
        if (minutes < 60) return `${minutes} phút trước`;
        if (hours < 24) return `${hours} giờ trước`;
        if (days < 7) return `${days} ngày trước`;

        const p = this.getVnParts(date);
        if (!p) return "";
        return `${p.day}/${p.month}/${p.year}`;
    }

    formatDateTime(value) {
        const p = this.getVnParts(value);
        if (!p) return "";
        return `${p.day}/${p.month}/${p.year} ${p.hour}:${p.minute}`;
    }

    nowSqlVN() {
        const p = this.getVnParts(new Date());
        if (!p) return "";
        return `${p.year}-${p.month}-${p.day} ${p.hour}:${p.minute}:${p.second}`;
    }
}
