<?php
/**
 * Application Core Functions
 * KaiMail - Temp Mail System
 */

// Load master configuration
require_once __DIR__ . '/config.php';
require_once dirname(__DIR__) . '/includes/Core/bootstrap.php';

// ======================
// DOMAIN HELPERS
// ======================

/**
 * Get default mail domain
 * Returns the first active domain from database
 * 
 * @return string|null Default domain or null if no active domains
 */
function getDefaultDomain(): ?string
{
    static $defaultDomain = null;

    // Cache the result to avoid multiple DB queries
    if ($defaultDomain !== null) {
        return $defaultDomain;
    }

    try {
        require_once __DIR__ . '/database.php';
        $db = getDB();
        $stmt = $db->query("SELECT domain FROM domains WHERE is_active = 1 ORDER BY domain ASC LIMIT 1");
        $result = $stmt->fetch();

        $defaultDomain = $result ? $result['domain'] : null;
        return $defaultDomain;
    } catch (Exception $e) {
        error_log("Error getting default domain: " . $e->getMessage());
        return null;
    }
}

/**
 * Get all active domains
 * 
 * @return array List of active domain names
 */
function getActiveDomains(): array
{
    static $domains = null;

    if ($domains !== null) {
        return $domains;
    }

    try {
        require_once __DIR__ . '/database.php';
        $db = getDB();
        $stmt = $db->query("SELECT domain FROM domains WHERE is_active = 1 ORDER BY domain ASC");
        $domains = $stmt->fetchAll(PDO::FETCH_COLUMN);
        return $domains;
    } catch (Exception $e) {
        error_log("Error getting active domains: " . $e->getMessage());
        return [];
    }
}

// ======================
// HELPER FUNCTIONS
// ======================

/**
 * JSON Response Helper
 */
function jsonResponse($data, int $code = 200): void
{
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

/**
 * Check if request is AJAX
 */
function isAjax(): bool
{
    return !empty($_SERVER['HTTP_X_REQUESTED_WITH']) &&
        strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
}

/**
 * Get request method
 */
function getMethod(): string
{
    $method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');

    // Allow override only for POST requests.
    if ($method === 'POST') {
        $headerOverride = $_SERVER['HTTP_X_HTTP_METHOD_OVERRIDE'] ?? '';
        if ($headerOverride !== '') {
            return strtoupper($headerOverride);
        }

        if (isset($_POST['_method'])) {
            return strtoupper((string) $_POST['_method']);
        }

        $input = getJsonInput();
        if (isset($input['_method'])) {
            return strtoupper($input['_method']);
        }
    }

    return $method;
}

/**
 * Get JSON input
 */
function getJsonInput(): array
{
    static $json_data = null;
    if ($json_data !== null)
        return $json_data;

    $input = (string) ($_SERVER['KAIMAIL_RAW_BODY'] ?? '');
    if ($input === '') {
        $input = (string) file_get_contents('php://input');
    }
    $json_data = json_decode($input, true) ?? [];
    return $json_data;
}

/**
 * Sanitize string
 */
function sanitize(string $str): string
{
    return htmlspecialchars(trim($str), ENT_QUOTES, 'UTF-8');
}
