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

<div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 2.5rem;">
    <div>
        <h2 style="font-size: 2rem; margin-bottom: 0.25rem;">Team Management</h2>
        <p style="color: var(--text-3); margin: 0;">Create manager accounts and set granular permissions for your staff.</p>
    </div>
    <button onclick="document.getElementById('addManagerModal').style.display='flex'" class="btn btn-primary" style="border-radius: 10px;">
        <svg class="icon icon-sm" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M16 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="8.5" cy="7" r="4"/><line x1="20" y1="8" x2="20" y2="14"/><line x1="17" y1="11" x2="23" y2="11"/></svg>
        Add New Manager
    </button>
</div>

<?php if ($success): ?><div class="alert alert-success"><?= $success ?></div><?php endif; ?>
<?php if ($error): ?><div class="alert alert-error"><?= $error ?></div><?php endif; ?>

<div class="card">
    <div class="table-responsive">
        <table style="width: 100%; border-collapse: collapse;">
            <thead>
                <tr style="text-align: left; border-bottom: 1px solid var(--border); background: var(--surface-2);">
                    <th style="padding: 1rem 1.5rem; font-size: 0.8rem; text-transform: uppercase; letter-spacing: 0.05em; color: var(--text-3);">Username</th>
                    <th style="padding: 1rem 1.5rem; font-size: 0.8rem; text-transform: uppercase; letter-spacing: 0.05em; color: var(--text-3); text-align: center;">Attendance</th>
                    <th style="padding: 1rem 1.5rem; font-size: 0.8rem; text-transform: uppercase; letter-spacing: 0.05em; color: var(--text-3); text-align: center;">Students</th>
                    <th style="padding: 1rem 1.5rem; font-size: 0.8rem; text-transform: uppercase; letter-spacing: 0.05em; color: var(--text-3); text-align: center;">Payments</th>
                    <th style="padding: 1rem 1.5rem; font-size: 0.8rem; text-transform: uppercase; letter-spacing: 0.05em; color: var(--text-3); text-align: center;">Scheduling</th>
                    <th style="padding: 1rem 1.5rem; font-size: 0.8rem; text-transform: uppercase; letter-spacing: 0.05em; color: var(--text-3); text-align: right;">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($managers)): ?>
                    <tr><td colspan="6" style="text-align: center; padding: 4rem; color: var(--text-3); font-weight: 500;">No managers added yet. Create accounts for your assistants or team members.</td></tr>
                <?php else: foreach ($managers as $m): ?>
                    <tr style="border-bottom: 1px solid var(--border);">
                        <td style="padding: 1.25rem 1.5rem; font-weight: 700; color: var(--text);"><?= htmlspecialchars($m['username']) ?></td>
                        <form method="POST">
                            <input type="hidden" name="action" value="update_perms">
                            <input type="hidden" name="user_id" value="<?= $m['id'] ?>">
                            <td style="padding: 1.25rem 1.5rem; text-align: center;"><input type="checkbox" name="perm_attendance" <?= $m['can_manage_attendance'] ? 'checked' : '' ?> onchange="this.form.submit()"></td>
                            <td style="padding: 1.25rem 1.5rem; text-align: center;"><input type="checkbox" name="perm_students" <?= $m['can_manage_students'] ? 'checked' : '' ?> onchange="this.form.submit()"></td>
                            <td style="padding: 1.25rem 1.5rem; text-align: center;"><input type="checkbox" name="perm_payments" <?= $m['can_manage_payments'] ? 'checked' : '' ?> onchange="this.form.submit()"></td>
                            <td style="padding: 1.25rem 1.5rem; text-align: center;"><input type="checkbox" name="perm_scheduling" <?= $m['can_manage_scheduling'] ? 'checked' : '' ?> onchange="this.form.submit()"></td>
                        </form>
                        <td style="padding: 1.25rem 1.5rem; text-align: right;">
                            <form method="POST" style="display: inline;" onsubmit="return confirm('Are you sure you want to delete this manager?')">
                                <input type="hidden" name="action" value="delete">
                                <input type="hidden" name="user_id" value="<?= $m['id'] ?>">
                                <button type="submit" class="btn btn-ghost btn-sm" style="color: var(--red-600);">Delete</button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Add Manager Modal -->
<div id="addManagerModal" class="modal-overlay" style="display: none; align-items: center; justify-content: center; padding: 2rem;">
    <div class="modal" style="max-width: 440px;">
        <button onclick="document.getElementById('addManagerModal').style.display='none'" class="close-modal" style="top: 1.5rem; right: 1.5rem;">&times;</button>
        <h3 class="mb-2">Add New Manager</h3>
        <p style="color: var(--text-3); font-size: 0.9rem; margin-bottom: 2rem;">Assign a new staff member to help manage your institute.</p>
        
        <form method="POST">
            <input type="hidden" name="action" value="add">
            <div class="form-group mb-4">
                <label>Username</label>
                <input type="text" name="username" class="form-control" style="border-radius: 10px;" placeholder="e.g. assistant_name" required>
            </div>
            <div class="form-group mb-6">
                <label>Password</label>
                <input type="password" name="password" class="form-control" style="border-radius: 10px;" placeholder="••••••••" required>
            </div>
            
            <div style="background: var(--surface-2); padding: 1.5rem; border-radius: 16px; border: 1px solid var(--border); margin-bottom: 2rem;">
                <label style="margin-bottom: 1rem; display: block; font-weight: 700; font-size: 0.75rem; text-transform: uppercase; letter-spacing: 0.05em; color: var(--text-3);">Permissions</label>
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1.25rem;">
                    <label style="display: flex; align-items: center; gap: 0.75rem; font-size: 0.9rem; font-weight: 600; cursor: pointer; color: var(--text);">
                        <input type="checkbox" name="perm_attendance" style="width: 18px; height: 18px;"> Attendance
                    </label>
                    <label style="display: flex; align-items: center; gap: 0.75rem; font-size: 0.9rem; font-weight: 600; cursor: pointer; color: var(--text);">
                        <input type="checkbox" name="perm_students" style="width: 18px; height: 18px;"> Students
                    </label>
                    <label style="display: flex; align-items: center; gap: 0.75rem; font-size: 0.9rem; font-weight: 600; cursor: pointer; color: var(--text);">
                        <input type="checkbox" name="perm_payments" style="width: 18px; height: 18px;"> Payments
                    </label>
                    <label style="display: flex; align-items: center; gap: 0.75rem; font-size: 0.9rem; font-weight: 600; cursor: pointer; color: var(--text);">
                        <input type="checkbox" name="perm_scheduling" style="width: 18px; height: 18px;"> Scheduling
                    </label>
                </div>
            </div>
            
            <div style="display: flex; gap: 1rem;">
                <button type="button" onclick="document.getElementById('addManagerModal').style.display='none'" class="btn btn-ghost" style="flex: 1; border-radius: 10px;">Cancel</button>
                <button type="submit" class="btn btn-primary" style="flex: 1; border-radius: 10px;">Create Account</button>
            </div>
        </form>
    </div>
</div>

<?php require_once __DIR__ . '/includes/header.php'; ?>
