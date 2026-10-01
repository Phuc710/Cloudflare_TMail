<?php
/**
 * Standalone Test: SSE (Server-Sent Events) + Redis Pub/Sub (Kèm Fallback khi chưa bật Redis)
 * Usage:
 *  - Mở trực tiếp trên trình duyệt: http://localhost/tmail/test_sse_redis.php
 *  - Bấm nút "Bắn 1 Event Test" để thấy SSE stream đẩy data xuống EventSource tức thì!
 */

declare(strict_types=1);

// Cấu hình Redis
$redisHost = '127.0.0.1';
$redisPort = 6379;
$redisPassword = null;
$channelName = 'tmail_admin_events';
$fallbackQueueFile = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'tmail_sse_fallback_events.json';

$action = $_GET['action'] ?? '';

// Helper: Check Redis connection
function checkRedis(string $host, int $port, ?string $password): array {
    if (!class_exists('Redis')) {
        return ['ok' => false, 'error' => 'PHP Redis extension (php_redis.dll) chưa được bật trong php.ini!'];
    }
    $redis = new Redis();
    try {
        $connected = @$redis->connect($host, $port, 1.0);
        if (!$connected) {
            return ['ok' => false, 'error' => "Redis Server chưa chạy (Port {$port} Connection refused)."];
        }
        if ($password) {
            $redis->auth($password);
        }
        return ['ok' => true, 'client' => $redis];
    } catch (\Throwable $e) {
        return ['ok' => false, 'error' => $e->getMessage()];
    }
}

// ==========================================
// 1. ENDPOINT: SSE STREAM (SERVER -> CLIENT)
// ==========================================
if ($action === 'stream') {
    if (function_exists('apache_setenv')) {
        @apache_setenv('no-gzip', '1');
    }
    @ini_set('zlib.output_compression', '0');
    @ini_set('implicit_flush', '1');
    while (ob_get_level() > 0) {
        ob_end_flush();
    }
    ob_implicit_flush(true);

    header('Content-Type: text/event-stream; charset=utf-8');
    header('Cache-Control: no-cache, no-transform');
    header('Connection: keep-alive');
    header('X-Accel-Buffering: no');

    $redisCheck = checkRedis($redisHost, $redisPort, $redisPassword);
    $driver = $redisCheck['ok'] ? 'redis' : 'fallback';

    // Gửi thông tin trạng thái ban đầu
    echo "event: connected\n";
    echo "data: " . json_encode([
        'status' => 'connected',
        'driver' => $driver,
        'redis_ok' => $redisCheck['ok'],
        'driver_note' => $redisCheck['ok'] ? 'Redis Pub/Sub Mode (Siêu nhanh 0ms)' : 'Fallback Memory/File Mode (' . $redisCheck['error'] . ')',
        'time' => date('H:i:s')
    ]) . "\n\n";
    flush();

    $lastHeartbeat = time();

    if ($driver === 'redis') {
        /** @var Redis $redis */
        $redis = $redisCheck['client'];
        $redis->setOption(Redis::OPT_READ_TIMEOUT, 10);

        while (!connection_aborted()) {
            try {
                $redis->subscribe([$channelName], function ($redisInstance, $chan, $message) {
                    echo "event: message\n";
                    echo "data: " . $message . "\n\n";
                    flush();
                });
            } catch (RedisException $e) {
                // Định kỳ gửi heartbeat giữ connection
                if (time() - $lastHeartbeat >= 10) {
                    echo ": heartbeat " . time() . "\n\n";
                    flush();
                    $lastHeartbeat = time();
                }
            }
        }
        $redis->close();
    } else {
        // FALLBACK MODE: Kiểm tra queue file khi chưa có Redis Server
        $lastCheckedId = '';
        if (file_exists($fallbackQueueFile)) {
            $existing = @json_decode((string)file_get_contents($fallbackQueueFile), true);
            $lastCheckedId = $existing['id'] ?? '';
        }

        while (!connection_aborted()) {
            if (file_exists($fallbackQueueFile)) {
                $raw = @file_get_contents($fallbackQueueFile);
                if ($raw) {
                    $event = @json_decode($raw, true);
                    if ($event && isset($event['id']) && $event['id'] !== $lastCheckedId) {
                        $lastCheckedId = $event['id'];
                        echo "event: message\n";
                        echo "data: " . json_encode($event) . "\n\n";
                        flush();
                    }
                }
            }

            if (time() - $lastHeartbeat >= 10) {
                echo ": heartbeat " . time() . "\n\n";
                flush();
                $lastHeartbeat = time();
            }

            usleep(150000); // 150ms sleep loop
        }
    }
    exit;
}

// ==========================================
// 2. ENDPOINT: PUBLISH EVENT (TRIGGER)
// ==========================================
if ($action === 'publish') {
    header('Content-Type: application/json; charset=utf-8');

    $payload = [
        'id' => uniqid('msg_'),
        'type' => 'new_mail',
        'from' => 'user_' . rand(100, 999) . '@kaishop.id.vn',
        'subject' => 'Mail Test Realtime lúc ' . date('H:i:s'),
        'timestamp' => date('Y-m-d H:i:s'),
        'microtime' => microtime(true),
    ];

    $redisCheck = checkRedis($redisHost, $redisPort, $redisPassword);

    if ($redisCheck['ok']) {
        /** @var Redis $redis */
        $redis = $redisCheck['client'];
        $subscribers = $redis->publish($channelName, json_encode($payload));
        $redis->close();

        echo json_encode([
            'ok' => true,
            'driver' => 'redis',
            'subscribers' => $subscribers,
            'payload' => $payload
        ]);
    } else {
        // Ghi vào fallback queue file
        @file_put_contents($fallbackQueueFile, json_encode($payload), LOCK_EX);

        echo json_encode([
            'ok' => true,
            'driver' => 'fallback',
            'note' => $redisCheck['error'],
            'payload' => $payload
        ]);
    }
    exit;
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Test SSE + Redis Realtime</title>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, monospace; }
        body { background: #0f172a; color: #f8fafc; padding: 24px; display: flex; justify-content: center; }
        .container { width: 100%; max-width: 820px; display: flex; flex-direction: column; gap: 16px; }
        .card { background: #1e293b; border: 1px solid #334155; border-radius: 12px; padding: 20px; }
        h1 { font-size: 20px; font-weight: 700; color: #38bdf8; display: flex; align-items: center; gap: 8px; }
        .badge { font-size: 12px; padding: 4px 10px; border-radius: 999px; font-weight: 600; }
        .badge-live { background: #065f46; color: #34d399; }
        .badge-warn { background: #854d0e; color: #fde047; }
        .badge-off { background: #7f1d1d; color: #f87171; }
        .btn-group { display: flex; gap: 10px; margin-top: 14px; flex-wrap: wrap; }
        button { background: #0284c7; color: white; border: none; padding: 10px 18px; border-radius: 8px; font-weight: 600; cursor: pointer; transition: 0.15s; }
        button:hover { background: #0369a1; }
        button.btn-danger { background: #dc2626; }
        button.btn-danger:hover { background: #b91c1c; }
        .log-box { background: #020617; border: 1px solid #1e293b; border-radius: 8px; padding: 14px; max-height: 360px; overflow-y: auto; font-family: 'JetBrains Mono', monospace; font-size: 13px; line-height: 1.6; display: flex; flex-direction: column; gap: 6px; }
        .log-entry { padding: 6px 10px; border-radius: 6px; background: #0f172a; border-left: 3px solid #38bdf8; word-break: break-all; }
        .log-entry.event-connected { border-left-color: #34d399; color: #34d399; }
        .log-entry.event-warn { border-left-color: #facc15; color: #fef08a; }
        .log-entry.event-error { border-left-color: #f87171; color: #f87171; }
        .stat-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 10px; margin-top: 12px; }
        .stat-card { background: #0f172a; padding: 12px; border-radius: 8px; text-align: center; border: 1px solid #334155; }
        .stat-val { font-size: 22px; font-weight: 700; color: #38bdf8; }
        .stat-label { font-size: 11px; color: #94a3b8; text-transform: uppercase; margin-top: 4px; }
        .alert-box { padding: 12px 14px; border-radius: 8px; font-size: 13px; line-height: 1.5; margin-top: 12px; }
        .alert-warning { background: #422006; border: 1px solid #854d0e; color: #fef08a; }
        .alert-info { background: #082f49; border: 1px solid #0369a1; color: #bae6fd; }
        code { background: #0f172a; padding: 2px 6px; border-radius: 4px; font-family: monospace; color: #38bdf8; }
    </style>
</head>
<body>

<div class="container">
    <div class="card">
        <div style="display: flex; justify-content: space-between; align-items: center;">
            <h1>⚡ Test SSE (Server-Sent Events)</h1>
            <span id="statusBadge" class="badge badge-off">Đang kết nối...</span>
        </div>

        <div id="driverAlert" class="alert-box alert-info" style="display: none;"></div>

        <div class="stat-grid">
            <div class="stat-card">
                <div class="stat-val" id="statReceived">0</div>
                <div class="stat-label">Events Nhận Được</div>
            </div>
            <div class="stat-card">
                <div class="stat-val" id="statLatency">0 ms</div>
                <div class="stat-label">Độ Trễ Stream (Latency)</div>
            </div>
            <div class="stat-card">
                <div class="stat-val" id="statDriver">-</div>
                <div class="stat-label">Chế độ Backend</div>
            </div>
        </div>

        <div class="btn-group">
            <button id="btnPublish" onclick="publishTestEvent()">🚀 Bắn 1 Event Test ngay</button>
            <button id="btnToggle" class="btn-danger" onclick="toggleConnection()">Ngắt SSE</button>
            <button style="background: #334155;" onclick="clearLogs()">Xóa Log</button>
        </div>
    </div>

    <div class="card">
        <h2 style="font-size: 15px; margin-bottom: 10px; color: #94a3b8;">Log Events Realtime (Stream Console):</h2>
        <div id="logBox" class="log-box">
            <div class="log-entry" style="color: #64748b;">Đang khởi tạo kết nối EventSource...</div>
        </div>
    </div>
</div>

<script>
    let evtSource = null;
    let eventCount = 0;

    function addLog(text, className = '') {
        const box = document.getElementById('logBox');
        const entry = document.createElement('div');
        entry.className = `log-entry ${className}`;
        entry.textContent = `[${new Date().toLocaleTimeString()}] ${text}`;
        box.prepend(entry);
    }

    function connectSSE() {
        if (evtSource) {
            evtSource.close();
        }

        const badge = document.getElementById('statusBadge');
        badge.className = 'badge badge-off';
        badge.textContent = 'Đang kết nối...';

        evtSource = new EventSource('?action=stream');

        evtSource.addEventListener('connected', (e) => {
            const data = JSON.parse(e.data);
            const driverElem = document.getElementById('statDriver');
            const alertBox = document.getElementById('driverAlert');

            if (data.driver === 'redis') {
                badge.className = 'badge badge-live';
                badge.textContent = 'SSE Live (Redis Pub/Sub ⚡)';
                driverElem.textContent = 'Redis Pub/Sub';
                alertBox.style.display = 'block';
                alertBox.className = 'alert-box alert-info';
                alertBox.innerHTML = '🔥 <strong>Redis Đang Hoạt Động:</strong> Kênh Pub/Sub kết nối thành công, độ trễ 0ms không qua Disk/DB.';
                addLog(`Đã kết nối luồng SSE qua Redis Pub/Sub!`, 'event-connected');
            } else {
                badge.className = 'badge badge-warn';
                badge.textContent = 'SSE Live (Fallback Mode)';
                driverElem.textContent = 'Fallback Queue';
                alertBox.style.display = 'block';
                alertBox.className = 'alert-box alert-warning';
                alertBox.innerHTML = `⚠️ <strong>Chưa bật Redis Server:</strong> ${data.driver_note}. Đang tự động chuyển sang Fallback Mode để test luồng SSE mượt mà.`;
                addLog(`Kết nối SSE Fallback: ${data.driver_note}`, 'event-warn');
            }
        });

        evtSource.addEventListener('message', (e) => {
            eventCount++;
            document.getElementById('statReceived').textContent = eventCount;

            try {
                const data = JSON.parse(e.data);
                if (data.microtime) {
                    const ms = Math.max(1, Math.round((Date.now() / 1000 - data.microtime) * 1000));
                    document.getElementById('statLatency').textContent = `${ms} ms`;
                }
                addLog(`⚡ Nhận Mail Mới: [${data.from}] "${data.subject}"`);
            } catch(err) {
                addLog(`Dữ liệu raw: ${e.data}`);
            }
        });

        evtSource.addEventListener('error', (e) => {
            if (evtSource.readyState === EventSource.CLOSED) {
                badge.className = 'badge badge-off';
                badge.textContent = 'Đã ngắt';
                addLog('Kết nối SSE đã đóng.', 'event-error');
            } else {
                badge.className = 'badge badge-off';
                badge.textContent = 'Mất kết nối, đang thử lại...';
                addLog('Lỗi kết nối SSE, browser tự reconnect...', 'event-error');
            }
        });
    }

    function toggleConnection() {
        const btn = document.getElementById('btnToggle');
        if (evtSource && evtSource.readyState !== EventSource.CLOSED) {
            evtSource.close();
            evtSource = null;
            document.getElementById('statusBadge').className = 'badge badge-off';
            document.getElementById('statusBadge').textContent = 'Đã tắt';
            btn.textContent = 'Kết nối lại SSE';
            btn.className = '';
            addLog('Đã ngắt kết nối thủ công.');
        } else {
            connectSSE();
            btn.textContent = 'Ngắt SSE';
            btn.className = 'btn-danger';
        }
    }

    async function publishTestEvent() {
        const btn = document.getElementById('btnPublish');
        btn.disabled = true;
        btn.textContent = 'Đang bắn...';
        try {
            const res = await fetch('?action=publish');
            const json = await res.json();
            if (json.ok) {
                addLog(`📤 Đã bắn event thành công qua [${json.driver}]!`, 'event-connected');
            } else {
                addLog(`Lỗi publish: ${json.error}`, 'event-error');
            }
        } catch (e) {
            addLog(`Lỗi fetch publish: ${e.message}`, 'event-error');
        } finally {
            btn.disabled = false;
            btn.textContent = '🚀 Bắn 1 Event Test ngay';
        }
    }

    function clearLogs() {
        document.getElementById('logBox').innerHTML = '';
    }

    // Tự động kết nối SSE
    connectSSE();
</script>
</body>
</html>
