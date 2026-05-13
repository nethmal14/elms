<?php
// core/migrate_data.php
// This script migrates data from individual tenant databases to the shared platform database.

require_once __DIR__ . '/DatabaseManager.php';

// Temporarily disable TenantAwarePDO scoping during migration if needed, 
// but actually we'll just use the Platform PDO to write data.

$platformPdo = DatabaseManager::getPlatformDB();

// 1. Get all tenants
$tenants = $platformPdo->query("SELECT * FROM tenants")->fetchAll();

$tables = [
    'users', 'settings', 'grades', 'subjects', 'units', 
    'materials', 'classes', 'recordings', 'papers', 
    'paper_submissions', 'payments', 'notifications'
];

echo "Starting migration...\n";

foreach ($tenants as $tenant) {
    echo "Migrating Tenant: {$tenant['name']} (ID: {$tenant['id']})...\n";
    
    // Connect to old tenant DB
    try {
        $dsn = "mysql:host=" . PLATFORM_DB_HOST . ";dbname=" . $tenant['db_name'] . ";charset=utf8mb4";
        $oldPdo = new PDO($dsn, $tenant['db_user'], $tenant['db_password'], [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);
    } catch (Exception $e) {
        echo "  [ERROR] Could not connect to old database {$tenant['db_name']}: " . $e->getMessage() . "\n";
        continue;
    }

    foreach ($tables as $table) {
        echo "  Table: $table... ";
        
        try {
            // Fetch all data from old table
            $data = $oldPdo->query("SELECT * FROM `$table`")->fetchAll();
            
            if (empty($data)) {
                echo "Empty. Skipped.\n";
                continue;
            }

            foreach ($data as $row) {
                // Add tenant_id
                $row['tenant_id'] = $tenant['id'];
                
                // Prepare insert
                $cols = array_keys($row);
                $placeholders = array_fill(0, count($cols), '?');
                
                $sql = "INSERT IGNORE INTO `$table` (" . implode(', ', $cols) . ") VALUES (" . implode(', ', $placeholders) . ")";
                $platformPdo->prepare($sql)->execute(array_values($row));
            }
            
            echo count($data) . " rows migrated.\n";
        } catch (Exception $e) {
            echo " [ERROR] " . $e->getMessage() . "\n";
        }
    }
}

echo "\nMigration finished.\n";
echo "IMPORTANT: Please verify data integrity before deleting old databases.\n";
