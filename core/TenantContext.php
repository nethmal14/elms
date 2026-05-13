<?php
// core/TenantContext.php

class TenantContext {
    private static $tenant = null;

    public static function set($tenant) {
        self::$tenant = $tenant;
    }

    public static function get() {
        return self::$tenant;
    }

    public static function getUploadDir($subdir = '') {
        if (!self::$tenant) return null;
        $dir = TENANT_UPLOAD_BASE . '/' . self::$tenant['id'] . ($subdir ? '/' . ltrim($subdir, '/') : '');
        if (!is_dir($dir)) {
            if (!@mkdir($dir, 0777, true)) {
                // Fallback check
                if (!is_dir($dir)) {
                    error_log("TenantContext: Failed to create directory: $dir");
                    return TENANT_UPLOAD_BASE; 
                }
            }
        }
        return $dir;
    }

    public static function getUploadUrl($path = '') {
        if (!self::$tenant) return null;
        return 'uploads/' . self::$tenant['id'] . ($path ? '/' . ltrim($path, '/') : '');
    }
}
