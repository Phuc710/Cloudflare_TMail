<?php
/**
 * Standalone Test: SSE (Server-Sent Events) + Redis Pub/Sub
 * Usage:
 *  - Mở trực tiếp trên trình duyệt: http://localhost/tmail/scratch/test_sse_redis.php
 *  - Bấm nút "Bắn Event Test" để trigger event qua Redis Pub/Sub và thấy EventSource nhận ngay tức thì!
 */

declare(strict_types=1);

// Cấu hình Redis
$redisHost = '127.0.0.1';
$redisPort = 6379;
$redisPassword = null; // hoặc pass nếu có
$channelName = 'tmail_admin_events';

$action = $_GET['action'] ?? '';

// ==========================================
// 1. ENDPOINT: SSE STREAM (SERVER -> CLIENT)
// ==========================================
if ($action === 'stream') {
    // Tắt hoàn toàn output buffering để stream mượt
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
    header('X-Accel-Buffering: no'); // Tránh Nginx buffer

    // Gửi ping chào mừng
    echo "event: connected\n";
    echo "data: " . json_encode(['status' => 'connected', 'time' => date('H:i:s')]) . "\n\n";
    flush();

    // Kiểm tra Extension Redis
    if (!class_exists('Redis')) {
        echo "event: error\n";
        echo "data: " . json_encode(['error' => 'PHP Redis extension chưa được cài đặt/bật!']) . "\n\n";
        flush();
        exit;
    }

    $redis = new Redis();
    try {
        $connected = @$redis->connect($redisHost, $redisPort, 3.0);
        if (!$connected) {
            echo "event: error\n";
            echo "data: " . json_encode(['error' => "Không kết nối được Redis tại {$redisHost}:{$redisPort}"]) . "\n\n";
            flush();
            exit;
        }
        if ($redisPassword) {
            $redis->auth($redisPassword);
        }
        // Set timeout cho subscribe để định kỳ gửi heartbeat (tránh timeout connection)
        $redis->setOption(Redis::OPT_READ_TIMEOUT, 15);
    } catch (Throwable $e) {
        echo "event: error\n";
        echo "data: " . json_encode(['error' => $e->getMessage()]) . "\n\n";
        flush();
        exit;
    }

    $lastHeartbeat = time();

    // Lắng nghe Redis Channel bằng Subscribe loop
    while (!connection_aborted()) {
        try {
            $redis->subscribe([$channelName], function ($redisInstance, $chan, $message) {
                echo "event: message\n";
                echo "data: " . $message . "\n\n";
                flush();
            });
        } catch (RedisException $e) {
            // Read timeout định kỳ -> gửi heartbeat giữ connection
            if (time() - $lastHeartbeat >= 10) {
                echo ": heartbeat " . time() . "\n\n";
                flush();
                $lastHeartbeat = time();
            }
        }
    }

    $redis->close();
    exit;
}

// ==========================================
// 2. ENDPOINT: PUBLISH EVENT (TRIGGER)
// ==========================================
if ($action === 'publish') {
    header('Content-Type: application/json; charset=utf-8');

    if (!class_exists('Redis')) {
        echo json_encode(['ok' => false, 'error' => 'PHP Redis extension not found']);
        exit;
    }

    $payload = [
        'id' => uniqid('msg_'),
        'type' => 'new_mail',
        'from' => 'test_' . rand(100, 999) . '@example.com',
        'subject' => 'Mail Test Realtime lúc ' . date('H:i:s'),
        'timestamp' => date('Y-m-d H:i:s'),
        'microtime' => microtime(true),
    ];

    try {
        $redis = new Redis();
        $redis->connect($redisHost, $redisPort, 2.0);
        if ($redisPassword) {
            $redis->auth($redisPassword);
        }
        $subscribers = $redis->publish($channelName, json_encode($payload));
        $redis->close();

        echo json_encode([
            'ok' => true,
            'subscribers' => $subscribers,
            'payload' => $payload
        ]);
    } catch (Throwable $e) {
        echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
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
        .container { width: 100%; max-width: 800px; display: flex; flex-direction: column; gap: 16px; }
        .card { background: #1e293b; border: 1px solid #334155; border-radius: 12px; padding: 20px; }
        h1 { font-size: 20px; font-weight: 700; color: #38bdf8; display: flex; align-items: center; gap: 8px; }
        .badge { font-size: 12px; padding: 4px 10px; border-radius: 999px; font-weight: 600; }
        .badge-live { background: #065f46; color: #34d399; }
        .badge-off { background: #7f1d1d; color: #f87171; }
        .btn-group { display: flex; gap: 10px; margin-top: 14px; }
        button { background: #0284c7; color: white; border: none; padding: 10px 18px; border-radius: 8px; font-weight: 600; cursor: pointer; transition: 0.15s; }
        button:hover { background: #0369a1; }
        button.btn-danger { background: #dc2626; }
        button.btn-danger:hover { background: #b91c1c; }
        .log-box { background: #020617; border: 1px solid #1e293b; border-radius: 8px; padding: 14px; max-height: 400px; overflow-y: auto; font-family: 'JetBrains Mono', monospace; font-size: 13px; line-height: 1.6; display: flex; flex-direction: column; gap: 6px; }
        .log-entry { padding: 6px 10px; border-radius: 6px; background: #0f172a; border-left: 3px solid #38bdf8; word-break: break-all; }
        .log-entry.event-connected { border-left-color: #34d399; color: #34d399; }
        .log-entry.event-error { border-left-color: #f87171; color: #f87171; }
        .stat-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 10px; margin-top: 12px; }
        .stat-card { background: #0f172a; padding: 12px; border-radius: 8px; text-align: center; border: 1px solid #334155; }
        .stat-val { font-size: 22px; font-weight: 700; color: #38bdf8; }
        .stat-label { font-size: 11px; color: #94a3b8; text-transform: uppercase; margin-top: 4px; }
    </style>
</head>
<body>

<div class="container">
    <div class="card">
        <div style="display: flex; justify-content: space-between; align-items: center;">
            <h1>⚡ Test SSE + Redis Pub/Sub</h1>
            <span id="statusBadge" class="badge badge-off">Chưa kết nối</span>
        </div>
        <p style="color: #94a3b8; font-size: 13px; margin-top: 8px;">
            Trang test 1 file duy nhất tích hợp cả <strong>EventSource client</strong>, <strong>SSE Stream backend</strong>, và <strong>Redis Publisher</strong>.
        </p>

        <div class="stat-grid">
            <div class="stat-card">
                <div class="stat-val" id="statReceived">0</div>
                <div class="stat-label">Events Nhận</div>
            </div>
            <div class="stat-card">
                <div class="stat-val" id="statLatency">0 ms</div>
                <div class="stat-label">Độ Trễ (Latency)</div>
            </div>
            <div class="stat-card">
                <div class="stat-val" id="statSubscribers">-</div>
                <div class="stat-label">Subscribers Online</div>
            </div>
        </div>

        <div class="btn-group">
            <button id="btnPublish" onclick="publishTestEvent()">🚀 Bắn 1 Event Test qua Redis</button>
            <button id="btnToggle" class="btn-danger" onclick="toggleConnection()">Ngắt SSE</button>
            <button style="background: #334155;" onclick="clearLogs()">Xóa Log</button>
        </div>
    </div>

    <div class="card">
        <h2 style="font-size: 15px; margin-bottom: 10px; color: #94a3b8;">Log Events Nhận Được:</h2>
        <div id="logBox" class="log-box">
            <div class="log-entry" style="color: #64748b;">Đang khởi tạo kết nối SSE...</div>
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
            badge.className = 'badge badge-live';
            badge.textContent = 'SSE Live (Redis Sub)';
            addLog(`Đã kết nối luồng SSE: ${e.data}`, 'event-connected');
        });

        evtSource.addEventListener('message', (e) => {
            eventCount++;
            document.getElementById('statReceived').textContent = eventCount;

            try {
                const data = JSON.parse(e.data);
                if (data.microtime) {
                    const diff = Math.round((performance.now() - (performance.timing.navigationStart + data.microtime * 1000 - performance.timing.fetchStart)));
                    const ms = Math.max(1, Math.round((Date.now() / 1000 - data.microtime) * 1000));
                    document.getElementById('statLatency').textContent = `${ms} ms`;
                }
                addLog(`⚡ Nhận Mail mới: [${data.from}] ${data.subject}`);
            } catch(err) {
                addLog(`Dữ liệu raw: ${e.data}`);
            }
        });

        evtSource.addEventListener('error', (e) => {
            if (evtSource.readyState === EventSource.CLOSED) {
                badge.className = 'badge badge-off';
                badge.textContent = 'Đã ngắt';
                addLog('Kết nối SSE bị đóng.', 'event-error');
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
                document.getElementById('statSubscribers').textContent = json.subscribers;
            } else {
                addLog(`Lỗi publish: ${json.error}`, 'event-error');
            }
        } catch (e) {
            addLog(`Lỗi fetch publish: ${e.message}`, 'event-error');
        } finally {
            btn.disabled = false;
            btn.textContent = '🚀 Bắn 1 Event Test qua Redis';
        }
    }

    function clearLogs() {
        document.getElementById('logBox').innerHTML = '';
    }

    // Auto connect
    connectSSE();
</script>
</body>
</html>
