<?php
require_once __DIR__ . '/../core/DatabaseManager.php';
$pdo = DatabaseManager::getPlatformDB();
$tenants = $pdo->query("SELECT name, subdomain, custom_domain FROM tenants")->fetchAll();
echo "Current Tenants:\n";
foreach ($tenants as $t) {
    echo "- Name: {$t['name']}, Subdomain: {$t['subdomain']}, Custom: {$t['custom_domain']}\n";
}
