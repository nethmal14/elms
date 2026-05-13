<?php
// superadmin/SuperAdminHelper.php

class SuperAdminHelper {
    public static function getTenantStats($tenant) {
        $stats = [
            'users' => 0,
            'subjects' => 0,
            'storage' => 0
        ];

        // 1. Database Stats
        try {
            $pdo = DatabaseManager::getTenantDB($tenant);
            if ($pdo) {
                $stats['users'] = $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'student'")->fetchColumn();
                $stats['subjects'] = $pdo->query("SELECT COUNT(*) FROM subjects")->fetchColumn();
            }
        } catch (Exception $e) {
            // Silently fail if DB is unreachable
        }

        // 2. Storage Stats
        $tenantDir = TENANT_UPLOAD_BASE . '/' . $tenant['id'];
        $stats['storage'] = self::getDirectorySize($tenantDir);
        
        // 3. Bandwidth from Platform record
        $stats['bandwidth'] = $tenant['bandwidth_usage'] ?? 0;
        $stats['requests'] = $tenant['request_count'] ?? 0;

        return $stats;
    }

    public static function getDirectorySize($dir) {
        if (!is_dir($dir)) return 0;
        $size = 0;
        foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir, RecursiveDirectoryIterator::SKIP_DOTS)) as $file) {
            $size += $file->getSize();
        }
        return $size;
    }

    public static function formatBytes($bytes, $precision = 2) {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $bytes = max($bytes, 0);
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min($pow, count($units) - 1);
        $bytes /= pow(1024, $pow);
        return round($bytes, $precision) . ' ' . $units[$pow];
    }
}
