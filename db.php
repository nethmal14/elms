<?php
// Core multi-tenant database connection handler

require_once __DIR__ . '/core/DatabaseManager.php';
require_once __DIR__ . '/core/TenantResolver.php';
require_once __DIR__ . '/core/TenantContext.php';

error_reporting(E_ALL);
ini_set('display_errors', 0);          // Never show errors to browser
ini_set('log_errors', 1);              // Log them server-side instead
ini_set('error_log', __DIR__ . '/logs/php_errors.log');

// Check if platform is setup
if (!file_exists(__DIR__ . '/platform_config.php')) {
    $currentPage = basename($_SERVER['PHP_SELF']);
    if ($currentPage !== 'setup.php') {
        header("Location: setup.php");
        exit;
    }
}

// Calculate base URL path (e.g., /lms/ or /)
if (!defined('SITE_ROOT')) {
    $docRoot = str_replace('\\', '/', $_SERVER['DOCUMENT_ROOT']);
    $thisDir = str_replace('\\', '/', __DIR__);
    
    // Get the relative URL path by removing DOCUMENT_ROOT from the physical path
    $rootPath = str_ireplace($docRoot, '', $thisDir);
    
    // Ensure it starts and ends with a slash for consistent joining
    $rootPath = '/' . ltrim($rootPath, '/');
    $rootPath = rtrim($rootPath, '/') . '/';
    
    define('SITE_ROOT', $rootPath);
}

// DEBUG: If you see this, db.php is loading.
// echo "DB.PHP LOADING..."; 

// Session start if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => '/',
        'secure'   => isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on', // HTTPS only — set to false only on localhost dev
        'httponly' => true,          // JS cannot read the cookie
        'samesite' => 'Lax',        // CSRF mitigation
    ]);
    session_start();
    // Regenerate session ID on every new session to prevent fixation
    if (empty($_SESSION['_initiated'])) {
        session_regenerate_id(true);
        $_SESSION['_initiated'] = true;
    }
}

function initTenant() {
    $tenant = TenantResolver::resolve();
    
    if (!$tenant) {
        // No tenant detected or inactive
        // In a real app, show platform landing page
        die("<h1>Welcome to Elms SaaS</h1><p>No valid tenant found for this domain.</p>");
    }

    // Check Subscription
    $now = new DateTime();
    $expires = new DateTime($tenant['subscription_expires_at']);
    if ($expires < $now) {
        // Log the expired access attempt
        error_log("Tenant subscription expired: tenant_id=" . ($tenant['id'] ?? 'unknown'));
        http_response_code(402);
        // Clean, styled page without leaking internals
        include __DIR__ . '/includes/expired.html';
        exit;
    }

    TenantContext::set($tenant);
    if ($tenant) {
        // Track stats in platform_db
        try {
            $platformPdo = DatabaseManager::getPlatformDB();
            // Only track stats on ~10% of requests to reduce DB write pressure
            // Stats will be statistically representative, not exact
            if (random_int(1, 10) === 1) {
                $stmt = $platformPdo->prepare(
                    "UPDATE tenants SET request_count = request_count + 10, bandwidth_usage = bandwidth_usage + ? WHERE id = ?"
                );
                $estimate = random_int(51200, 153600); // 10x since we only run 1 in 10 times
                $stmt->execute([$estimate, $tenant['id']]);
            }
        } catch (Exception $e) {}

        return DatabaseManager::getTenantDB($tenant);
    }
}

$pdo = initTenant();

if (!$pdo) {
    die("<h1>Database Error</h1><p>Unable to connect to the tenant database.</p>");
}

function getDB() {
    global $pdo;
    return $pdo;
}
