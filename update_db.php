<?php
/**
 * Master Database Update Script (Shared Architecture)
 * Handles platform-wide shared database updates.
 */

require_once __DIR__ . '/core/DatabaseManager.php';
require_once __DIR__ . '/core/TenantContext.php';

try {
    $platformPdo = DatabaseManager::getPlatformDB();
    
    echo "Starting Platform-Wide Database Update (Shared Architecture)...\n\n";

    // 0. Table Renames
    try {
        $platformPdo->exec("RENAME TABLE past_papers TO papers");
        $platformPdo->exec("UPDATE settings SET setting_key = 'enable_papers' WHERE setting_key = 'enable_past_papers'");
        echo "  Renamed setting 'enable_past_papers' to 'enable_papers'.\n";
    } catch (Exception $e) {}
    
    // 1. Apply Schema Updates to Platform DB
    echo "Updating Shared Tables Schema...\n";

    // Drop unique constraint on db_name if it exists
    try {
        $platformPdo->exec("ALTER TABLE tenants DROP INDEX db_name");
    } catch (Exception $e) { /* Ignore if doesn't exist */ }
    // 1.5 Create New Tables (if they don't exist in shared schema)
    $platformPdo->exec("
        CREATE TABLE IF NOT EXISTS attendance (
            id INT AUTO_INCREMENT PRIMARY KEY,
            tenant_id INT NOT NULL,
            class_id INT NOT NULL,
            user_id INT NOT NULL,
            attended_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            method ENUM('qr_scan', 'link_click', 'manual') NOT NULL,
            status ENUM('present', 'late', 'excused') DEFAULT 'present',
            UNIQUE(tenant_id, class_id, user_id),
            INDEX(tenant_id),
            INDEX(class_id),
            INDEX(user_id)
        );
    ");

    $platformPdo->exec("
        CREATE TABLE IF NOT EXISTS manager_permissions (
            id INT AUTO_INCREMENT PRIMARY KEY,
            tenant_id INT NOT NULL,
            user_id INT NOT NULL,
            can_manage_attendance TINYINT(1) DEFAULT 0,
            can_manage_students TINYINT(1) DEFAULT 0,
            can_manage_payments TINYINT(1) DEFAULT 0,
            can_manage_scheduling TINYINT(1) DEFAULT 0,
            UNIQUE(tenant_id, user_id),
            INDEX(tenant_id),
            INDEX(user_id)
        );
    ");

    $tablesToUpdate = [
        'classes' => [
            "class_type ENUM('physical', 'online', 'hybrid') NOT NULL DEFAULT 'online'",
            "image VARCHAR(255) NULL", 
            "notes_pdf VARCHAR(255) NULL"
        ],
        'recordings' => ["image VARCHAR(255) NULL", "notes_pdf VARCHAR(255) NULL"],
        'users' => [
            "student_id VARCHAR(10) NULL", 
            "photo VARCHAR(255) NULL", 
            "phone VARCHAR(20) NULL", 
            "school VARCHAR(255) NULL"
        ],
        'notifications' => [
            "type ENUM('notification', 'announcement') DEFAULT 'notification'",
            "is_cleared TINYINT(1) DEFAULT 0",
            "announcement_id VARCHAR(50) NULL"
        ],
        'papers' => [
            "paper_type ENUM('essay', 'mcq', 'both') DEFAULT 'essay'",
            "mcq_config JSON NULL",
            "essay_pdf_path VARCHAR(255) NULL",
            "mcq_pdf_path VARCHAR(255) NULL"
        ],
        'paper_submissions' => [
            "mcq_answers JSON NULL",
            "mcq_score INT DEFAULT NULL",
            "essay_marks INT DEFAULT NULL",
            "total_marks INT DEFAULT NULL",
            "mcq_submitted_at TIMESTAMP NULL",
            "essay_submitted_at TIMESTAMP NULL",
            "essay_status ENUM('not_submitted', 'submitted', 'marked') DEFAULT 'not_submitted'"
        ]
    ];

    // Special: Update user roles to include manager
    try {
        $platformPdo->exec("ALTER TABLE users MODIFY COLUMN role ENUM('admin', 'student', 'manager') NOT NULL DEFAULT 'student'");
    } catch (Exception $e) {}

    foreach ($tablesToUpdate as $table => $columns) {
        // Get existing columns
        $existing = $platformPdo->query("SHOW COLUMNS FROM `$table`")->fetchAll(PDO::FETCH_COLUMN);
        
        foreach ($columns as $colDef) {
            $colName = explode(' ', trim($colDef))[0];
            if (!in_array($colName, $existing)) {
                try {
                    $platformPdo->exec("ALTER TABLE `$table` ADD COLUMN $colDef");
                    echo "  Added $colName to $table.\n";
                } catch (Exception $e) {
                    echo "  Error adding $colName to $table: " . $e->getMessage() . "\n";
                }
            }
        }
    }


    // 2. Ensure default settings for all tenants
    echo "\nVerifying Default Settings for all Tenants...\n";
    $tenants = $platformPdo->query("SELECT * FROM tenants")->fetchAll();
    
    $defaultSettings = [
        ['grace_period_days', '5'],
        ['enable_papers', '1'],
        ['hero_bg_image', 'https://images.unsplash.com/photo-1522202176988-66273c2fd55f?q=80&w=2071&auto=format&fit=crop'],
        ['hero_heading', 'Unlock Your Potential with Expert Guidance'],
        ['hero_subtext', 'Experience premium education designed to elevate your skills and career. Join our community of lifelong learners today.'],
    ];

    $sStmt = $platformPdo->prepare("INSERT IGNORE INTO settings (tenant_id, setting_key, setting_value) VALUES (?, ?, ?)");
    
    foreach ($tenants as $tenant) {
        echo "  Tenant: " . $tenant['name'] . "... ";
        $count = 0;
        foreach ($defaultSettings as $s) {
            if ($sStmt->execute([$tenant['id'], $s[0], $s[1]])) {
                $count++;
            }
        }
        echo "Done.\n";

        // 3. Ensure Directories exist
        TenantContext::set($tenant);
        $dirs = [
            TenantContext::getUploadDir('images/'),
            TenantContext::getUploadDir('profiles/'),
            TenantContext::getUploadDir('papers/'),
            TenantContext::getUploadDir('submissions/'),
            TenantContext::getUploadDir('marked/'),
            TenantContext::getUploadDir('notes/'),
        ];
        foreach ($dirs as $dir) {
            if (!is_dir($dir)) mkdir($dir, 0777, true);
        }
    }
    
    // 4. Migrate Legacy Paper Data
    echo "\nMigrating Legacy Paper PDF paths...\n";
    $platformPdo->exec("UPDATE papers SET essay_pdf_path = pdf_path WHERE (paper_type = 'essay' OR paper_type = 'both') AND (essay_pdf_path IS NULL OR essay_pdf_path = '')");
    $platformPdo->exec("UPDATE papers SET mcq_pdf_path = pdf_path WHERE paper_type = 'mcq' AND (mcq_pdf_path IS NULL OR mcq_pdf_path = '')");
    echo "  Data migration complete.\n";

    echo "\nPlatform-wide update complete.\n";
    
} catch (Exception $e) {
    echo "Critical Migration Error: " . $e->getMessage() . "\n";
}
