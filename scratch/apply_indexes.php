<?php
require_once __DIR__ . '/core/DatabaseManager.php';

try {
    $pdo = DatabaseManager::getPlatformDB();
    
    // Check if idx_user_subject exists on payments
    $pdo->exec("
        SET @exist := (SELECT COUNT(*) FROM information_schema.statistics WHERE table_schema=DATABASE() AND table_name='payments' AND index_name='idx_user_subject');
        SET @sqlstmt := if( @exist > 0, 'SELECT ''Index already exists''', 'ALTER TABLE payments ADD INDEX idx_user_subject (user_id, subject_id)');
        PREPARE stmt FROM @sqlstmt;
        EXECUTE stmt;
    ");

    $pdo->exec("
        SET @exist := (SELECT COUNT(*) FROM information_schema.statistics WHERE table_schema=DATABASE() AND table_name='classes' AND index_name='idx_subject_time');
        SET @sqlstmt := if( @exist > 0, 'SELECT ''Index already exists''', 'ALTER TABLE classes ADD INDEX idx_subject_time (subject_id, start_time)');
        PREPARE stmt FROM @sqlstmt;
        EXECUTE stmt;
    ");

    $pdo->exec("
        SET @exist := (SELECT COUNT(*) FROM information_schema.statistics WHERE table_schema=DATABASE() AND table_name='paper_submissions' AND index_name='idx_user_paper');
        SET @sqlstmt := if( @exist > 0, 'SELECT ''Index already exists''', 'ALTER TABLE paper_submissions ADD INDEX idx_user_paper (user_id, paper_id)');
        PREPARE stmt FROM @sqlstmt;
        EXECUTE stmt;
    ");

    echo "Indexes applied successfully.\n";
} catch (Exception $e) {
    // If syntax for IF exists fails (sometimes multiple statements in exec fail), we can just catch and ignore or do it in PHP
    echo "Error: " . $e->getMessage() . "\n";
}
