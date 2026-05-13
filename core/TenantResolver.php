<?php
// core/TenantResolver.php

class TenantResolver {
    public static function resolve() {
        $host = $_SERVER['HTTP_HOST'];
        $platformDomain = PLATFORM_MAIN_DOMAIN;

        // 1. Check for custom domain
        try {
            $pdo = DatabaseManager::getPlatformDB();
            $stmt = $pdo->prepare("SELECT * FROM tenants WHERE custom_domain = ? AND status = 'active'");
            $stmt->execute([$host]);
            $tenant = $stmt->fetch();

            if ($tenant) {
                return $tenant;
            }

            // 2. Check for subdomain
            if (str_ends_with($host, $platformDomain)) {
                $subdomain = str_replace("." . $platformDomain, "", $host);
                if ($subdomain !== $host && $subdomain !== 'www') {
                    $stmt = $pdo->prepare("SELECT * FROM tenants WHERE subdomain = ? AND status = 'active'");
                    $stmt->execute([$subdomain]);
                    return $stmt->fetch();
                }
            }
        } catch (PDOException $e) {
            // Table or DB might not exist yet
            return null;
        }

        return null;
    }
}
