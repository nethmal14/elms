<?php
// core/DatabaseManager.php

if (file_exists(__DIR__ . '/../platform_config.php')) {
    require_once __DIR__ . '/../platform_config.php';
}

require_once __DIR__ . '/TenantAwarePDO.php';

class DatabaseManager {
    private static $platformPdo = null;
    private static $tenantPdos = [];

    public static function getPlatformDB() {
        if (self::$platformPdo === null) {
            try {
                $dsn = "mysql:host=" . PLATFORM_DB_HOST . ";dbname=" . PLATFORM_DB_NAME . ";charset=utf8mb4";
                self::$platformPdo = new PDO($dsn, PLATFORM_DB_USER, PLATFORM_DB_PASS, [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                ]);
            } catch (PDOException $e) {
                die("Platform Database connection failed: " . $e->getMessage());
            }
        }
        return self::$platformPdo;
    }

    public static function getTenantDB($tenantData = null) {
        if ($tenantData === null) return null;
        
        $tid = $tenantData['id'];
        
        if (!isset(self::$tenantPdos[$tid])) {
            try {
                // Now using shared database (PLATFORM_DB_NAME) for all tenants
                $dsn = "mysql:host=" . PLATFORM_DB_HOST . ";dbname=" . PLATFORM_DB_NAME . ";charset=utf8mb4";
                
                // Wrap in TenantAwarePDO for automatic isolation
                self::$tenantPdos[$tid] = new TenantAwarePDO(
                    $dsn, 
                    PLATFORM_DB_USER, 
                    PLATFORM_DB_PASS, 
                    [
                        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    ],
                    $tid
                );
            } catch (PDOException $e) {
                return null;
            }
        }
        return self::$tenantPdos[$tid];
    }

}

