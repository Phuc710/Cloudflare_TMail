<?php
/**
 * KaiMail Custom Domains Health Checker (Background CLI and Cron)
 *
 * Usage:
 *   CLI:  php scripts/check_domains_health.php
 *   Cron: 0 0,6,12,18 * * * php /path/to/scripts/check_domains_health.php
 */

declare(strict_types=1);

if (php_sapi_name() !== 'cli') {
    header('Content-Type: text/plain; charset=utf-8');
}

use KaiMail\Core\Services\CustomDomainService;

$baseDir = dirname(__DIR__);
require_once $baseDir . '/config/database.php';
require_once $baseDir . '/includes/Core/Http/ApiException.php';
require_once $baseDir . '/includes/Core/Services/CustomDomainService.php';

echo "========================================================\n";
echo " KaiMail - Custom Domains Live Health Checker\n";
echo " Time: " . date('Y-m-d H:i:s') . "\n";
echo "========================================================\n\n";

try {
    $db = getDB();
    $service = new CustomDomainService($db);
} catch (Throwable $e) {
    echo "[CRITICAL] Database or service initialization failed: " . $e->getMessage() . "\n";
    exit(1);
}

// Fetch all custom domains
$stmt = $db->query("SELECT id, domain, is_active, webhook_secret, type, created_at FROM domains WHERE type = 'custom' ORDER BY id ASC");
$customDomains = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (empty($customDomains)) {
    echo "[INFO] No custom domains found in the database.\n";
    exit(0);
}

echo "[INFO] Checking health for " . count($customDomains) . " custom domain(s)...\n\n";

$liveCount = 0;
$degradedCount = 0;
$revivedCount = 0;

foreach ($customDomains as $d) {
    $domainName = (string) $d['domain'];
    $currentActive = (int) $d['is_active'];

    echo ">>> Domain: @{$domainName} (Current Status: " . ($currentActive === 1 ? "ACTIVE" : "INACTIVE") . ")\n";

    $dnsResult = $service->verifyDns($domainName);
    $isDnsValid = (bool) ($dnsResult['valid'] ?? false);
    $mxList = implode(', ', $dnsResult['mx_records'] ?? []);

    if ($isDnsValid) {
        $liveCount++;
        echo "    ✓ Status: [LIVE]\n";
        echo "    ✓ Cloudflare MX Detected: [{$mxList}]\n";

        // If it was previously marked inactive, restore it
        if ($currentActive === 0) {
            $upd = $db->prepare("UPDATE domains SET is_active = 1 WHERE id = ?");
            $upd->execute([(int) $d['id']]);
            $revivedCount++;
            echo "    ⚡ Action: Re-activated domain (Restored from inactive)\n";
        }
    } else {
        $degradedCount++;
        echo "    ✗ Status: [DEGRADED / MISSING MX]\n";
        echo "    ✗ Diagnostic: " . ($dnsResult['message'] ?? 'No Cloudflare MX records detected') . "\n";

        // If it was previously marked active, degrade it to prevent broken emails
        if ($currentActive === 1) {
            $upd = $db->prepare("UPDATE domains SET is_active = 0 WHERE id = ?");
            $upd->execute([(int) $d['id']]);
            echo "    ⚠️ Action: De-activated domain (Protected against routing failures)\n";
        }
    }
    echo "--------------------------------------------------------\n";
}

echo "\n========================================================\n";
echo " Summary Report:\n";
echo " - Total Checked: " . count($customDomains) . "\n";
echo " - 🟢 Live & Healthy: {$liveCount}\n";
echo " - 🔴 Degraded (Inactive): {$degradedCount}\n";
if ($revivedCount > 0) {
    echo " - ⚡ Revived to Active: {$revivedCount}\n";
}
echo "========================================================\n";
