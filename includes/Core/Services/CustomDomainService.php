<?php
declare(strict_types=1);

namespace KaiMail\Core\Services;

use PDO;
use Throwable;
use KaiMail\Core\Http\ApiException;

/**
 * Enterprise Custom Domain Management Service.
 * Manages user custom domain onboarding, per-domain Webhook secrets,
 * Cloudflare Worker code generation and live DNS MX verification.
 */
final class CustomDomainService
{
    private PDO $db;

    public function __construct(PDO $db)
    {
        $this->db = $db;
    }

    /**
     * Register or retrieve an existing custom domain for setup.
     *
     * @return array{id: int, domain: string, webhook_secret: string, verify_token: string, is_active: int, is_new: bool}
     */
    public function setupCustomDomain(string $domain): array
    {
        $domain = strtolower(trim($domain));
        $domain = preg_replace('#^https?://#i', '', $domain);
        $domain = rtrim((string) $domain, '/');

        if ($domain === '') {
            throw ApiException::badRequest('Vui lòng nhập tên miền hợp lệ');
        }

        if (!preg_match('/^[a-z0-9]([a-z0-9-]{0,61}[a-z0-9])?(\.[a-z0-9]([a-z0-9-]{0,61}[a-z0-9])?)+$/i', $domain)) {
            throw ApiException::badRequest('Định dạng tên miền không hợp lệ (ví dụ: devmail.vn, mail.mydomain.org)');
        }

        // Check if domain already exists
        $stmt = $this->db->prepare("SELECT id, domain, is_active, webhook_secret, verify_token, type FROM domains WHERE domain = ? LIMIT 1");
        $stmt->execute([$domain]);
        $existing = $stmt->fetch();

        if ($existing) {
            $isSystem = ($existing['type'] ?? '') === 'system';
            $isActive = (int) ($existing['is_active'] ?? 0) === 1;

            if ($isSystem) {
                throw ApiException::badRequest("Tên miền '{$domain}' là tên miền mặc định của hệ thống! Không thể thêm lại.");
            }

            if ($isActive) {
                throw ApiException::badRequest("Tên miền '{$domain}' đã tồn tại và đang hoạt động trên hệ thống! Bạn có thể chọn và sử dụng ngay.");
            }

            $secret = (string) ($existing['webhook_secret'] ?? '');
            $token = (string) ($existing['verify_token'] ?? '');

            // Ensure secrets exist for pending domain
            if ($secret === '' || $token === '') {
                $secret = $secret !== '' ? $secret : 'km_whsec_' . bin2hex(random_bytes(20));
                $token = $token !== '' ? $token : bin2hex(random_bytes(16));

                $upStmt = $this->db->prepare("UPDATE domains SET webhook_secret = ?, verify_token = ?, type = 'custom' WHERE id = ?");
                $upStmt->execute([$secret, $token, $existing['id']]);
            }

            return [
                'id' => (int) $existing['id'],
                'domain' => (string) $existing['domain'],
                'webhook_secret' => $secret,
                'verify_token' => $token,
                'is_active' => 0,
                'is_new' => false,
            ];
        }

        // Generate secrets for new custom domain
        $secret = 'km_whsec_' . bin2hex(random_bytes(20));
        $token = bin2hex(random_bytes(16));

        $insertStmt = $this->db->prepare("INSERT INTO domains (domain, is_active, webhook_secret, verify_token, type) VALUES (?, 0, ?, ?, 'custom')");
        $insertStmt->execute([$domain, $secret, $token]);
        $domainId = (int) $this->db->lastInsertId();

        return [
            'id' => $domainId,
            'domain' => $domain,
            'webhook_secret' => $secret,
            'verify_token' => $token,
            'is_active' => 0,
            'is_new' => true,
        ];
    }

    /**
     * Find domain by ID or domain string.
     */
    public function find(int|string $identifier): ?array
    {
        if (is_int($identifier) || ctype_digit((string) $identifier)) {
            $stmt = $this->db->prepare("SELECT id, domain, is_active, webhook_secret, verify_token, type, created_at FROM domains WHERE id = ?");
            $stmt->execute([(int) $identifier]);
        } else {
            $stmt = $this->db->prepare("SELECT id, domain, is_active, webhook_secret, verify_token, type, created_at FROM domains WHERE domain = ?");
            $stmt->execute([strtolower(trim((string) $identifier))]);
        }

        $row = $stmt->fetch();
        return is_array($row) ? $row : null;
    }

    /**
     * Verify DNS MX records for domain.
     * Checks if MX records are pointed to Cloudflare Email Routing.
     *
     * @return array{valid: bool, mx_records: array, message: string}
     */
    public function verifyDns(string $domain): array
    {
        $domain = strtolower(trim($domain));
        $mxRecords = [];

        // 1. Native PHP DNS Lookup
        if (function_exists('dns_get_record')) {
            try {
                $rawRecords = @dns_get_record($domain, DNS_MX);
                if (is_array($rawRecords)) {
                    foreach ($rawRecords as $rec) {
                        if (!empty($rec['target'])) {
                            $mxRecords[] = strtolower((string) $rec['target']);
                        }
                    }
                }
            } catch (Throwable $e) {
                // Fallback
            }
        }

        // 2. Cloudflare DoH (DNS-over-HTTPS) fallback if local DNS resolution fails
        if (empty($mxRecords)) {
            try {
                $dohUrl = 'https://cloudflare-dns.com/dns-query?name=' . urlencode($domain) . '&type=MX';
                $ctx = stream_context_create([
                    'http' => [
                        'method' => 'GET',
                        'header' => "Accept: application/dns-json\r\nUser-Agent: KaiMail-DnsChecker/1.0\r\n",
                        'timeout' => 4,
                    ],
                ]);
                $response = @file_get_contents($dohUrl, false, $ctx);
                if ($response !== false) {
                    $json = json_decode($response, true);
                    if (!empty($json['Answer']) && is_array($json['Answer'])) {
                        foreach ($json['Answer'] as $ans) {
                            if (($ans['type'] ?? 0) === 15 && !empty($ans['data'])) {
                                $parts = preg_split('/\s+/', trim((string) $ans['data']));
                                $target = end($parts);
                                if ($target !== '') {
                                    $mxRecords[] = strtolower(rtrim($target, '.'));
                                }
                            }
                        }
                    }
                }
            } catch (Throwable $e) {
                // Ignore DoH error
            }
        }

        // Check if MX target is Cloudflare Email Routing
        $hasCloudflareMx = false;
        foreach ($mxRecords as $mx) {
            if (str_contains($mx, 'mx.cloudflare.net') || str_contains($mx, 'cloudflare.com')) {
                $hasCloudflareMx = true;
                break;
            }
        }

        if ($hasCloudflareMx) {
            return [
                'valid' => true,
                'mx_records' => $mxRecords,
                'message' => 'Bản ghi MX Cloudflare Email Routing đã sẵn sàng.',
            ];
        }

        if (!empty($mxRecords)) {
            $previewMx = implode(', ', array_slice($mxRecords, 0, 2));
            return [
                'valid' => false,
                'mx_records' => $mxRecords,
                'message' => "Tên miền đang trỏ MX về máy chủ khác ({$previewMx}), chưa trỏ về Cloudflare Email Routing (*.mx.cloudflare.net). Vui lòng nhấn Enable Email Routing trên Cloudflare.",
            ];
        }

        return [
            'valid' => false,
            'mx_records' => [],
            'message' => 'Chưa phát hiện bản ghi MX trên domain. Vui lòng bật Enable Email Routing trên Cloudflare.',
        ];
    }

    /**
     * Activate a custom domain.
     */
    public function activate(int $domainId): bool
    {
        $stmt = $this->db->prepare("UPDATE domains SET is_active = 1 WHERE id = ?");
        return $stmt->execute([$domainId]);
    }

    /**
     * Generate personalized Cloudflare Worker script content.
     */
    public function generateWorkerScript(string $webhookUrl, string $webhookSecret): string
    {
        $safeUrl = addslashes($webhookUrl);
        $safeSecret = addslashes($webhookSecret);

        return <<<JS
/**
 * KaiMail Universal Cloudflare Email Routing Worker
 * Automatically configured for your domain.
 * High-performance, low-latency, zero-overhead MIME extractor.
 */
function getConfig(env) {
  return {
    webhookUrl: env.WEBHOOK_URL || "{$safeUrl}",
    webhookSecret: env.WEBHOOK_SECRET || "{$safeSecret}",
  };
}

export default {
  // Handle HTTP requests (Health check / Worker status)
  async fetch(request, env, ctx) {
    return new Response("KaiMail Universal Worker is running.\\nEmail Routing is Active.", {
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
      const messageId = message.headers.get("message-id") || `msg_\${Date.now()}`;

      // Extract sender name from "From" header if present
      const fromHeader = message.headers.get("from") || from;
      let fromName = "";
      const nameMatch = fromHeader.match(/^"?([^"<]+)"?\\s*<.*>$/);
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
        const headerEnd = raw.match(/\\r?\\n\\r?\\n/);
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
        console.error(`Webhook failed [\${response.status}]: \${responseText}`);
      } else {
        console.log(`Email forwarded successfully: \${to}`);
      }
    } catch (error) {
      console.error(`Error processing email: \${error.message}`);
    }
  },
};

// =====================================================
//       FAST MIME PART EXTRACTOR (RAW STREAM)
// =====================================================
function extractMimePart(raw, contentType) {
  const boundaryMatch = raw.match(/boundary\\s*=\\s*"?([^"\\r\\n;]+)"?/i);
  const boundary = boundaryMatch ? boundaryMatch[1].trim() : null;

  let parts = boundary
    ? raw.split(new RegExp(`--\${boundary.replace(/[.*+?^\${}()|[\\]\\\\]/g, "\\\\$&")}`, "g"))
    : [raw];

  for (const part of parts) {
    if (part.includes("--") && part === parts[parts.length - 1]) continue;

    const ctMatch = part.match(/Content-Type:\\s*([^;\\r\\n]+)/i);
    if (!ctMatch) continue;

    const partType = ctMatch[1].trim().toLowerCase();
    if (partType.includes(contentType.toLowerCase())) {
      const headerEndMatch = part.match(/\\r?\\n\\r?\\n/);
      if (!headerEndMatch) continue;

      let content = part.substring(part.indexOf(headerEndMatch[0]) + headerEndMatch[0].length);
      return content.replace(/\\r?\\n--[^\\r\\n]*$/, "").trim();
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
  return `\${map.year}-\${map.month}-\${map.day} \${map.hour}:\${map.minute}:\${map.second}`;
}
JS;
    }
}
