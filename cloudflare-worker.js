/**
 * KaiMail Universal Cloudflare Email Routing Worker
 * High-performance, low-latency, zero-overhead MIME extractor.
 * All RFC2047 / Quoted-Printable / Base64 decoding is handled by KaiMail backend.
 */
function getConfig(env) {
  return {
    webhookUrl: env.WEBHOOK_URL || "",
    webhookSecret: env.WEBHOOK_SECRET || "",
  };
}

export default {
  // Handle HTTP requests (Health check / Worker status)
  async fetch(request, env, ctx) {
    return new Response("KaiMail Worker is running.\nEmail routing is active.", {
      headers: { "content-type": "text/plain;charset=UTF-8" },
    });
  },

  // Handle incoming emails - Universal for all domains
  async email(message, env, ctx) {
    const config = getConfig(env);

    if (!config.webhookUrl || !config.webhookSecret) {
      console.error("Missing Worker env vars: WEBHOOK_URL or WEBHOOK_SECRET");
      return;
    }

    try {
      const to = message.to;
      const from = message.from;
      const subject = message.headers.get("subject") || "(No subject)";
      const messageId = message.headers.get("message-id") || `msg_${Date.now()}`;

      // Extract sender name from "From" header if present
      const fromHeader = message.headers.get("from") || from;
      let fromName = "";
      const nameMatch = fromHeader.match(/^"?([^"<]+)"?\s*<.*>$/);
      if (nameMatch) {
        fromName = nameMatch[1].trim();
      }

      // Stream raw MIME directly to text (fast & memory-efficient)
      const raw = await new Response(message.raw).text();

      // Extract raw text/plain and text/html parts
      let textBody = extractMimePart(raw, "text/plain");
      let htmlBody = extractMimePart(raw, "text/html");

      // Fallback: if no MIME boundaries found, extract after first empty line
      if (!textBody && !htmlBody) {
        const headerEnd = raw.match(/\r?\n\r?\n/);
        if (headerEnd) {
          const bodyStart = raw.indexOf(headerEnd[0]);
          textBody = raw.substring(bodyStart + headerEnd[0].length).trim();
        }
      }

      // Timestamp with Vietnam timezone (Asia/Ho_Chi_Minh, GMT+7)
      const received_at = formatVietnamDateTime();

      const payload = {
        to,
        from,
        from_name: fromName,
        subject,
        message_id: messageId,
        text: textBody,
        html: htmlBody,
        received_at,
      };

      // Forward to KaiMail Webhook with 15s timeout protection
      const response = await fetch(config.webhookUrl, {
        method: "POST",
        headers: {
          "Content-Type": "application/json",
          "X-Webhook-Secret": config.webhookSecret,
        },
        body: JSON.stringify(payload),
        signal: AbortSignal.timeout(15000),
      });

      const responseText = await response.text();

      if (!response.ok) {
        console.error(`Webhook failed [${response.status}]: ${responseText}`);
      } else {
        console.log(`Email forwarded successfully: ${to}`);
      }
    } catch (error) {
      console.error(`Error processing email: ${error.message}`);
    }
  },
};

// =====================================================
//       FAST MIME PART EXTRACTOR (RAW STREAM)
// =====================================================
function extractMimePart(raw, contentType) {
  const boundaryMatch = raw.match(/boundary\s*=\s*"?([^"\r\n;]+)"?/i);
  const boundary = boundaryMatch ? boundaryMatch[1].trim() : null;

  let parts = boundary
    ? raw.split(new RegExp(`--${boundary.replace(/[.*+?^${}()|[\]\\]/g, "\\$&")}`, "g"))
    : [raw];

  for (const part of parts) {
    if (part.includes("--") && part === parts[parts.length - 1]) continue;

    const ctMatch = part.match(/Content-Type:\s*([^;\r\n]+)/i);
    if (!ctMatch) continue;

    const partType = ctMatch[1].trim().toLowerCase();
    if (partType.includes(contentType.toLowerCase())) {
      const headerEndMatch = part.match(/\r?\n\r?\n/);
      if (!headerEndMatch) continue;

      let content = part.substring(part.indexOf(headerEndMatch[0]) + headerEndMatch[0].length);
      return content.replace(/\r?\n--[^\r\n]*$/, "").trim();
    }

    if (partType.startsWith("multipart/")) {
      const nested = extractMimePart(part, contentType);
      if (nested) return nested;
    }
  }

  return "";
}

// =====================================================
//       VIETNAM DATETIME FORMATTER (GMT+7)
// =====================================================
function formatVietnamDateTime(date = new Date()) {
  const parts = new Intl.DateTimeFormat("en-GB", {
    timeZone: "Asia/Ho_Chi_Minh",
    hour12: false,
    year: "numeric",
    month: "2-digit",
    day: "2-digit",
    hour: "2-digit",
    minute: "2-digit",
    second: "2-digit",
  }).formatToParts(date);

  const map = Object.fromEntries(parts.map((p) => [p.type, p.value]));
  return `${map.year}-${map.month}-${map.day} ${map.hour}:${map.minute}:${map.second}`;
}
