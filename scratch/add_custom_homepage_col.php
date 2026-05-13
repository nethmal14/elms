<?php
require_once __DIR__ . '/core/DatabaseManager.php';

try {
    $pdo = DatabaseManager::getPlatformDB();
    $pdo->exec("ALTER TABLE tenants ADD COLUMN custom_homepage_html TEXT NULL AFTER subscription_expires_at");
    echo "Column custom_homepage_html added to tenants table.\n";
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
