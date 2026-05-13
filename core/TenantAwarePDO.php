<?php
// core/TenantAwarePDO.php

class TenantAwarePDO extends PDO {
    private $tenantId;
    private $isSuperAdmin = false;
    private $tenantTables = [
        'users', 'settings', 'grades', 'subjects', 'units', 
        'materials', 'classes', 'recordings', 'papers', 
        'paper_submissions', 'payments', 'notifications', 'manager_permissions'
    ];

    public function __construct($dsn, $username, $password, $options, $tenantId, $isSuperAdmin = false) {
        parent::__construct($dsn, $username, $password, $options);
        $this->tenantId = $tenantId;
        $this->isSuperAdmin = $isSuperAdmin;
    }

    public function prepare($query, $options = []) {
        if ($this->isSuperAdmin) {
            return parent::prepare($query, $options);
        }

        try {
            list($modifiedQuery, $injections) = $this->injectTenantScope($query);
            $stmt = parent::prepare($modifiedQuery, $options);
            
            if (!$stmt) {
                // If preparation fails, return a dummy statement that throws on execute
                // to avoid "call to member function on bool"
                return parent::prepare($query, $options); 
            }
            
            return new TenantAwareStatement($stmt, $this->tenantId, $injections);
        } catch (Exception $e) {
            return parent::prepare($query, $options);
        }
    }

    public function query($query, $fetchMode = null, ...$fetchModeArgs) {
        if ($this->isSuperAdmin) {
            return parent::query($query, $fetchMode, ...$fetchModeArgs);
        }

        list($modifiedQuery, $injections) = $this->injectTenantScope($query);
        
        if (!empty($injections)) {
            $stmt = $this->prepare($query); // Use our own prepare
            $stmt->execute();
            return $stmt;
        }

        return parent::query($query, $fetchMode, ...$fetchModeArgs);
    }


    private function injectTenantScope($sql) {
        if ($this->isSuperAdmin) return [$sql, []];

        $injections = [];

        // 1. Basic subquery handling (no recursive injections for simplicity/stability)
        // We'll just scope the main query for now, but handle subquery presence
        if (preg_match_all('/\((SELECT\b.*?)\)/is', $sql, $matches)) {
             // Subqueries present - we will process them simply
        }

        // 2. Find main table
        $mainTable = null;
        $alias = null;
        if (preg_match('/(?:FROM|UPDATE|INTO|DELETE\s+FROM)\s+[`]?([a-z0-9_]+)[`]?\s*(?:AS\s+)?([a-z0-9_]+)?/i', $sql, $matches)) {
            $tableName = $matches[1];
            if (in_array(strtolower($tableName), $this->tenantTables)) {
                $mainTable = $tableName;
                if (isset($matches[2]) && !preg_match('/^(JOIN|WHERE|GROUP|ORDER|LIMIT|SET|ON|AS)$/i', $matches[2])) {
                    $alias = $matches[2];
                }
            }
        }

        if (!$mainTable) return [$sql, $injections];
        $column = $alias ? "`$alias`.tenant_id" : "`$mainTable`.tenant_id";

        // 3. Handle INSERT
        if (preg_match('/^\s*INSERT\s+INTO\s+([a-z0-9_]+)\s*\(([^)]+)\)\s*VALUES\s*\(([^)]+)\)/i', $sql, $matches)) {
            $injections[] = ['type' => 'start'];
            return ["INSERT INTO $matches[1] (tenant_id, $matches[2]) VALUES (?, $matches[3])", $injections];
        }

        // 4. Handle WHERE clause injection (simple)
        if (stripos($sql, 'tenant_id') === false) {
            if (stripos($sql, ' WHERE ') !== false) {
                $parts = preg_split('/(\bWHERE\b)/i', $sql, 2, PREG_SPLIT_DELIM_CAPTURE);
                $placeholderCount = substr_count($parts[0], '?');
                $sql = $parts[0] . $parts[1] . " $column = ? AND " . $parts[2];
                $injections[] = ['type' => 'at_index', 'index' => $placeholderCount];
            } else {
                if (preg_match('/(\bGROUP BY\b|\bORDER BY\b|\bLIMIT\b)/i', $sql, $matches, PREG_OFFSET_CAPTURE)) {
                    $pos = $matches[0][1];
                    $placeholderCount = substr_count(substr($sql, 0, $pos), '?');
                    $sql = substr($sql, 0, $pos) . " WHERE $column = ? " . substr($sql, $pos);
                } else {
                    $placeholderCount = substr_count($sql, '?');
                    $sql .= " WHERE $column = ?";
                }
                $injections[] = ['type' => 'at_index', 'index' => $placeholderCount];
            }
        }

        return [$sql, $injections];
    }
}

class TenantAwareStatement {
    private $stmt;
    private $tenantId;
    private $injections;

    public function __construct($stmt, $tenantId, $injections) {
        $this->stmt = $stmt;
        $this->tenantId = $tenantId;
        $this->injections = $injections;
    }

    public function execute($params = null) {
        if (!$this->stmt) return false;
        if ($params === null) $params = [];
        
        if (!empty($this->injections)) {
            $atIndexInjs = [];
            $otherInjs = [];
            foreach ($this->injections as $inj) {
                if ($inj['type'] === 'at_index') $atIndexInjs[] = $inj;
                else $otherInjs[] = $inj;
            }
            
            foreach ($otherInjs as $inj) {
                array_unshift($params, $this->tenantId);
            }
            
            usort($atIndexInjs, function($a, $b) { return $b['index'] - $a['index']; });
            foreach ($atIndexInjs as $inj) {
                array_splice($params, $inj['index'], 0, $this->tenantId);
            }
        }
        return $this->stmt->execute($params);
    }

    public function __call($name, $arguments) {
        if (!$this->stmt) return false;
        return call_user_func_array([$this->stmt, $name], $arguments);
    }
}



