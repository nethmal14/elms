<?php
require_once __DIR__ . '/includes/header.php';

$success = '';

// Define the keys we want to manage
$keys = [
    'site_name', 'payment_instructions', 'grace_period_days', 
    'hero_bg_image', 'hero_heading', 'hero_subtext', 
    'enable_papers', 'tutor_name', 'tutor_bio', 'tutor_image'
];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_settings'])) {
    $data = [];
    foreach ($keys as $key) {
        if ($key === 'enable_papers') {
            $data[$key] = isset($_POST[$key]) ? '1' : '0';
        } else {
            $data[$key] = $_POST[$key] ?? '';
        }
    }

    // Handle File Uploads
    $uploadDir = TenantContext::getUploadDir('settings');
    foreach (['hero_bg_image', 'tutor_image'] as $fileKey) {
        if (isset($_FILES[$fileKey]) && $_FILES[$fileKey]['error'] === UPLOAD_ERR_OK) {
            $ext = pathinfo($_FILES[$fileKey]['name'], PATHINFO_EXTENSION);
            $fileName = $fileKey . '_' . time() . '.' . $ext;
            $targetPath = $uploadDir . '/' . $fileName;
            if (move_uploaded_file($_FILES[$fileKey]['tmp_name'], $targetPath)) {
                $data[$fileKey] = TenantContext::getUploadUrl('settings/' . $fileName);
            }
        }
    }

    // Save to database
    foreach ($data as $key => $val) {
        $check = $pdo->prepare("SELECT 1 FROM settings WHERE setting_key = ?");
        $check->execute([$key]);
        if ($check->fetch()) {
            $pdo->prepare("UPDATE settings SET setting_value = ? WHERE setting_key = ?")->execute([$val, $key]);
        } else {
            $pdo->prepare("INSERT INTO settings (setting_key, setting_value) VALUES (?, ?)")->execute([$key, $val]);
        }
    }

    $success = "Settings updated successfully.";
}

// Get current settings
$stmt = $pdo->query("SELECT * FROM settings");
$settings = [];
while ($row = $stmt->fetch()) {
    $settings[$row['setting_key']] = $row['setting_value'];
}
?>

<div class="flex-between-center mb-10">
    <div>
        <h2 class="text-4xl mb-2">Platform Configuration</h2>
        <p class="text-tertiary m-0 text-lg">Customize your institute's identity, branding, and system behavior.</p>
    </div>
</div>

<?php if ($success): ?>
    <div class="alert alert-success mb-8"><?= htmlspecialchars($success) ?></div>
<?php endif; ?>




<div class="settings-container">
    <div class="settings-nav">
        <button class="settings-tab active" onclick="showTab('general', this)">
            <svg fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"></path></svg>
            General Settings
        </button>
        <button class="settings-tab" onclick="showTab('appearance', this)">
            <svg fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path d="M4 5a1 1 0 011-1h14a1 1 0 011 1v2a1 1 0 01-1 1H5a1 1 0 01-1-1V5zM4 13a1 1 0 011-1h6a1 1 0 011 1v6a1 1 0 01-1 1H5a1 1 0 01-1-1v-6zM16 13a1 1 0 011-1h2a1 1 0 011 1v6a1 1 0 01-1 1h-2a1 1 0 01-1-1v-6z"></path></svg>
            Visual Branding
        </button>
        <button class="settings-tab" onclick="showTab('tutor', this)">
            <svg fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path></svg>
            Tutor Profile
        </button>
        <button class="settings-tab" onclick="showTab('data', this)">
            <svg fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"></path></svg>
            System & Export
        </button>
    </div>

    <form method="POST" enctype="multipart/form-data" id="settingsForm">
        <input type="hidden" name="save_settings" value="1">
        
        <!-- General Section -->
        <div id="section-general" class="settings-content-section active settings-card">
            <h3 class="section-title">General Settings</h3>
            <p class="section-desc">Core parameters for your platform identity and rules.</p>
            
            <div class="form-group mb-6">
                <label class="label-accent-muted-sm mb-3 block">Institute Name</label>
                <input type="text" name="site_name" class="form-control rounded-12 h-12.5" value="<?= htmlspecialchars($settings['site_name'] ?? '') ?>" required>
            </div>
            
            <div class="form-group mb-6">
                <label class="label-accent-muted-sm mb-3 block">Enrollment Instructions</label>
                <textarea name="payment_instructions" class="form-control rounded-12 p-4" rows="4" required><?= htmlspecialchars($settings['payment_instructions'] ?? '') ?></textarea>
                <p class="text-xs text-tertiary mt-2">Shown to students during the checkout process.</p>
            </div>
            
            <div class="form-group mb-8">
                <label class="label-accent-muted-sm mb-3 block">Payment Grace Period (Days)</label>
                <div class="relative w-50">
                    <input type="number" name="grace_period_days" class="form-control rounded-12 h-12.5" value="<?= htmlspecialchars($settings['grace_period_days'] ?? '5') ?>" min="0" max="28" required>
                    <span class="absolute-right-center font-bold text-tertiary text-sm">Days</span>
                </div>
            </div>
            
            <div class="switch-container mb-10">
                <div>
                    <div class="font-extrabold text-primary text-lg mb-1">Academic Assessments</div>
                    <div class="text-sm text-tertiary font-semibold">Enable past papers, hybrid exams, and student marking system.</div>
                </div>
                <label class="switch">
                    <input type="checkbox" name="enable_papers" value="1" <?= ($settings['enable_papers'] ?? '1') == '1' ? 'checked' : '' ?>>
                    <span class="slider round"></span>
                </label>
            </div>
            
            <button type="submit" class="btn btn-primary h-13 px-10 rounded-12 font-extrabold bg-blue-900 border-none">Apply Changes</button>
        </div>

        <!-- Appearance Section -->
        <div id="section-appearance" class="settings-content-section settings-card">
            <h3 class="section-title">Visual Branding</h3>
            <p class="section-desc">Manage the landing page visuals and platform appearance.</p>
            
            <div class="form-group mb-8">
                <label class="label-accent-muted-sm mb-3 block">Hero Background Image</label>
                <div class="relative rounded-16 overflow-hidden border-default mb-6">
                    <?php if (!empty($settings['hero_bg_image'])): ?>
                        <img src="<?= htmlspecialchars($settings['hero_bg_image']) ?>" class="w-full h-50 object-cover">
                        <div class="absolute inset-0 bg-gradient-to-t-black"></div>
                    <?php else: ?>
                        <div class="w-full h-50 bg-surface-muted flex-center text-tertiary">
                            <svg class="w-12 h-12 opacity-20" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                        </div>
                    <?php endif; ?>
                </div>
                <input type="file" name="hero_bg_image" class="form-control rounded-12 h-12.5 p-2.5" accept="image/*">
            </div>
            
            <div class="form-group mb-6">
                <label class="label-accent-muted-sm mb-3 block">Hero Heading Text</label>
                <input type="text" name="hero_heading" class="form-control rounded-12 h-12.5" value="<?= htmlspecialchars($settings['hero_heading'] ?? '') ?>" placeholder="e.g. Master Your Future with Expertise">
            </div>
            
            <div class="form-group mb-8">
                <label class="label-accent-muted-sm mb-3 block">Hero Introduction Subtext</label>
                <textarea name="hero_subtext" class="form-control rounded-12 p-4" rows="3" placeholder="e.g. High-quality educational resources tailored for academic excellence..."><?= htmlspecialchars($settings['hero_subtext'] ?? '') ?></textarea>
            </div>
            
            <button type="submit" class="btn btn-primary h-13 px-10 rounded-12 font-extrabold bg-blue-900 border-none">Update Branding</button>
        </div>

        <!-- Tutor Section -->
        <div id="section-tutor" class="settings-content-section settings-card">
            <h3 class="section-title">Tutor Profile</h3>
            <p class="section-desc">Manage the public profile of the primary educator.</p>
            
            <div class="grid grid-cols-2 gap-8 mb-8">
                <div class="form-group">
                    <label class="label-accent-muted-sm mb-3 block">Full Name</label>
                    <input type="text" name="tutor_name" class="form-control rounded-12 h-12.5" value="<?= htmlspecialchars($settings['tutor_name'] ?? '') ?>" placeholder="e.g. Dr. Jane Smith">
                </div>
                <div class="form-group">
                    <label class="label-accent-muted-sm mb-3 block">Profile Portrait</label>
                    <div class="flex items-center gap-6 bg-surface-muted p-3-5 rounded-16 border-default">
                        <div class="w-14 h-14 rounded-full overflow-hidden border-2 border-blue-600 flex-shrink-0">
                            <?php if (!empty($settings['tutor_image'])): ?>
                                <img src="<?= htmlspecialchars($settings['tutor_image']) ?>" class="w-full h-full object-cover">
                            <?php else: ?>
                                <div class="w-full h-full bg-border flex-center text-tertiary">
                                    <svg class="w-6 h-6" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg>
                                </div>
                            <?php endif; ?>
                        </div>
                        <input type="file" name="tutor_image" class="text-xs font-bold text-tertiary" accept="image/*">
                    </div>
                </div>
            </div>
            
            <div class="form-group mb-10">
                <label class="label-accent-muted-sm mb-3 block">Professional Biography</label>
                <textarea name="tutor_bio" class="form-control rounded-12 p-4" rows="5" placeholder="Highlight your academic background and teaching philosophy..."><?= htmlspecialchars($settings['tutor_bio'] ?? '') ?></textarea>
            </div>
            
            <button type="submit" class="btn btn-primary h-13 px-10 rounded-12 font-extrabold bg-blue-900 border-none">Save Biography</button>
        </div>
    </form>

    <!-- Data Section -->
    <div id="section-data" class="settings-content-section settings-card">
        <h3 class="section-title">System & Export</h3>
        <p class="section-desc">Maintenance tools and historical data extraction.</p>
        
        <div class="bg-surface-muted p-12 rounded-20 border-default text-center">
            <div class="text-5xl mb-6">📦</div>
            <h4 class="text-xl font-extrabold mb-3">Archive Generator</h4>
            <p class="text-tertiary font-semibold mb-10 max-w-sm mx-auto">Compile all students' PDF submissions and payment records for the selected month into a single ZIP archive.</p>
            
            <div class="flex flex-col items-center gap-5">
                <input type="month" id="exportMonth" value="<?= date('Y-m') ?>" class="form-control max-w-xs h-13 rounded-12 text-center font-extrabold text-xl border-2 border-default">
                
                <button onclick="startExport()" class="btn btn-primary h-13 px-10 rounded-12 font-extrabold bg-blue-900 border-none flex items-center gap-4">
                    <svg class="icon icon-sm" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/></svg>
                    Generate ZIP Archive
                </button>
            </div>
            
            <p id="exportStatus" class="mt-8 text-sm font-bold hidden p-4 rounded-10 bg-surface border-default"></p>
        </div>
    </div>
</div>

<script>
    function showTab(id, btn) {
        document.querySelectorAll('.settings-tab').forEach(t => t.classList.remove('active'));
        document.querySelectorAll('.settings-content-section').forEach(s => s.classList.remove('active'));
        
        btn.classList.add('active');
        document.getElementById('section-' + id).classList.add('active');
    }

    function startExport() {
        const month = document.getElementById('exportMonth').value;
        const status = document.getElementById('exportStatus');
        status.style.display = 'block';
        status.innerText = 'Initializing compilation... This may take several minutes for large datasets.';
        status.style.color = 'var(--blue-600)';
        
        window.location.href = 'api/export_monthly_data.php?month=' + month;
        
        setTimeout(() => {
            status.innerText = 'Compilation finished. If the download did not start, please refresh and try again.';
            status.style.color = 'var(--text-3)';
        }, 5000);
    }
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
