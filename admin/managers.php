<?php
require_once __DIR__ . '/includes/header.php';

// Only main admin can manage managers
if ($_SESSION['role'] !== 'admin') {
    die("Unauthorized. Only the main Tutor Admin can manage managers.");
}

$success = '';
$error = '';

// Handle manager creation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $tenant_id = TenantContext::get()['id'];
    
    if ($_POST['action'] === 'add') {
        $username = trim($_POST['username']);
        $password = password_hash($_POST['password'], PASSWORD_DEFAULT);
        
        try {
            $pdo->beginTransaction();
            
            // Create user
            $stmt = $pdo->prepare("INSERT INTO users (tenant_id, username, password, role) VALUES (?, ?, ?, 'manager')");
            $stmt->execute([$tenant_id, $username, $password]);
            $user_id = $pdo->lastInsertId();
            
            // Set permissions
            $perms = [
                'attendance' => isset($_POST['perm_attendance']) ? 1 : 0,
                'students' => isset($_POST['perm_students']) ? 1 : 0,
                'payments' => isset($_POST['perm_payments']) ? 1 : 0,
                'scheduling' => isset($_POST['perm_scheduling']) ? 1 : 0
            ];
            
            $stmt = $pdo->prepare("INSERT INTO manager_permissions (tenant_id, user_id, can_manage_attendance, can_manage_students, can_manage_payments, can_manage_scheduling) VALUES (?, ?, ?, ?, ?, ?)");
            $stmt->execute([$tenant_id, $user_id, $perms['attendance'], $perms['students'], $perms['payments'], $perms['scheduling']]);
            
            $pdo->commit();
            $success = "Manager account created successfully.";
        } catch (Exception $e) {
            $pdo->rollBack();
            $error = "Error creating manager: " . $e->getMessage();
        }
    }
    
    if ($_POST['action'] === 'update_perms') {
        $user_id = $_POST['user_id'];
        $perms = [
            'attendance' => isset($_POST['perm_attendance']) ? 1 : 0,
            'students' => isset($_POST['perm_students']) ? 1 : 0,
            'payments' => isset($_POST['perm_payments']) ? 1 : 0,
            'scheduling' => isset($_POST['perm_scheduling']) ? 1 : 0
        ];
        
        $stmt = $pdo->prepare("UPDATE manager_permissions SET can_manage_attendance = ?, can_manage_students = ?, can_manage_payments = ?, can_manage_scheduling = ? WHERE user_id = ? AND tenant_id = ?");
        $stmt->execute([$perms['attendance'], $perms['students'], $perms['payments'], $perms['scheduling'], $user_id, $tenant_id]);
        $success = "Permissions updated successfully.";
    }

    if ($_POST['action'] === 'delete') {
        $user_id = $_POST['user_id'];
        $pdo->prepare("DELETE FROM users WHERE id = ? AND tenant_id = ? AND role = 'manager'")->execute([$user_id, $tenant_id]);
        $pdo->prepare("DELETE FROM manager_permissions WHERE user_id = ? AND tenant_id = ?")->execute([$user_id, $tenant_id]);
        $success = "Manager account deleted.";
    }
}

// Fetch all managers for this tenant
$stmt = $pdo->prepare("
    SELECT u.id, u.username, p.can_manage_attendance, p.can_manage_students, p.can_manage_payments, p.can_manage_scheduling
    FROM users u
    LEFT JOIN manager_permissions p ON u.id = p.user_id
    WHERE u.tenant_id = ? AND u.role = 'manager'
");
$stmt->execute([TenantContext::get()['id']]);
$managers = $stmt->fetchAll();
?>

<div class="flex-between-center mb-10">
    <div>
        <h2 class="text-3xl mb-1">Team Management</h2>
        <p class="text-tertiary m-0">Create manager accounts and set granular permissions for your staff.</p>
    </div>
    <button onclick="document.getElementById('addManagerModal').style.display='flex'" class="btn btn-primary rounded-10">
        <svg class="icon icon-sm" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M16 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="8.5" cy="7" r="4"/><line x1="20" y1="8" x2="20" y2="14"/><line x1="17" y1="11" x2="23" y2="11"/></svg>
        Add New Manager
    </button>
</div>

<?php if ($success): ?><div class="alert alert-success"><?= $success ?></div><?php endif; ?>
<?php if ($error): ?><div class="alert alert-error"><?= $error ?></div><?php endif; ?>

<div class="card p-0 overflow-hidden">
    <div class="table-responsive">
        <table class="table-modern">
            <thead>
                <tr>
                    <th>Username</th>
                    <th class="text-center">Attendance</th>
                    <th class="text-center">Students</th>
                    <th class="text-center">Payments</th>
                    <th class="text-center">Scheduling</th>
                    <th class="text-right">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($managers)): ?>
                    <tr><td colspan="6" class="text-center p-16 text-tertiary font-medium">No managers added yet. Create accounts for your assistants or team members.</td></tr>
                <?php else: foreach ($managers as $m): ?>
                    <tr>
                        <td class="font-bold"><?= htmlspecialchars($m['username']) ?></td>
                        <form method="POST">
                            <input type="hidden" name="action" value="update_perms">
                            <input type="hidden" name="user_id" value="<?= $m['id'] ?>">
                            <td class="text-center"><input type="checkbox" name="perm_attendance" <?= $m['can_manage_attendance'] ? 'checked' : '' ?> onchange="this.form.submit()"></td>
                            <td class="text-center"><input type="checkbox" name="perm_students" <?= $m['can_manage_students'] ? 'checked' : '' ?> onchange="this.form.submit()"></td>
                            <td class="text-center"><input type="checkbox" name="perm_payments" <?= $m['can_manage_payments'] ? 'checked' : '' ?> onchange="this.form.submit()"></td>
                            <td class="text-center"><input type="checkbox" name="perm_scheduling" <?= $m['can_manage_scheduling'] ? 'checked' : '' ?> onchange="this.form.submit()"></td>
                        </form>
                        <td class="text-right">
                            <form method="POST" class="inline-block" onsubmit="return confirm('Are you sure you want to delete this manager?')">
                                <input type="hidden" name="action" value="delete">
                                <input type="hidden" name="user_id" value="<?= $m['id'] ?>">
                                <button type="submit" class="btn btn-ghost btn-sm text-danger">Delete</button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Add Manager Modal -->
<div id="addManagerModal" class="modal-overlay flex-center p-8" style="display: none;">
    <div class="modal modal-sm">
        <button onclick="document.getElementById('addManagerModal').style.display='none'" class="close-modal absolute-top-right-6">&times;</button>
        <h3 class="mb-2">Add New Manager</h3>
        <p class="text-tertiary text-sm mb-8">Assign a new staff member to help manage your institute.</p>
        
        <form method="POST">
            <input type="hidden" name="action" value="add">
            <div class="form-group mb-4">
                <label>Username</label>
                <input type="text" name="username" class="form-control rounded-10" placeholder="e.g. assistant_name" required>
            </div>
            <div class="form-group mb-6">
                <label>Password</label>
                <input type="password" name="password" class="form-control rounded-10" placeholder="••••••••" required>
            </div>
            
            <div class="p-6 rounded-16 border-default bg-surface-muted mb-8">
                <label class="label-accent-muted-sm mb-4 block">Permissions</label>
                <div class="permission-grid">
                    <label class="permission-label">
                        <input type="checkbox" name="perm_attendance" class="permission-checkbox"> Attendance
                    </label>
                    <label class="permission-label">
                        <input type="checkbox" name="perm_students" class="permission-checkbox"> Students
                    </label>
                    <label class="permission-label">
                        <input type="checkbox" name="perm_payments" class="permission-checkbox"> Payments
                    </label>
                    <label class="permission-label">
                        <input type="checkbox" name="perm_scheduling" class="permission-checkbox"> Scheduling
                    </label>
                </div>
            </div>
            
            <div class="flex gap-4">
                <button type="button" onclick="document.getElementById('addManagerModal').style.display='none'" class="btn btn-ghost flex-1 rounded-10">Cancel</button>
                <button type="submit" class="btn btn-primary flex-1 rounded-10">Create Account</button>
            </div>
        </form>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
