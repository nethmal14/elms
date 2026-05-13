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

<div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 2.5rem;">
    <div>
        <h2 style="font-size: 2.25rem; margin-bottom: 0.5rem;">Platform Configuration</h2>
        <p style="color: var(--text-3); margin: 0; font-size: 1.1rem;">Customize your institute's identity, branding, and system behavior.</p>
    </div>
</div>

<?php if ($success): ?>
    <div class="alert alert-success" style="margin-bottom: 2rem;"><?= htmlspecialchars($success) ?></div>
<?php endif; ?>

<style>
    .settings-container {
        display: grid;
        grid-template-columns: 280px 1fr;
        gap: 3rem;
        align-items: start;
    }
    .settings-nav {
        background: var(--surface);
        border-radius: 20px;
        padding: 1rem;
        border: 1px solid var(--border);
        position: sticky;
        top: 2rem;
        box-shadow: var(--shadow-sm);
    }
    .settings-tab {
        display: flex;
        align-items: center;
        gap: 1rem;
        padding: 0.85rem 1.25rem;
        color: var(--text-3);
        text-decoration: none;
        font-weight: 700;
        border-radius: 12px;
        transition: all 0.2s;
        cursor: pointer;
        border: none;
        background: none;
        width: 100%;
        text-align: left;
        font-size: 0.95rem;
        margin-bottom: 0.25rem;
    }
    .settings-tab:hover {
        background: var(--surface-2);
        color: var(--text);
    }
    .settings-tab.active {
        background: var(--blue-50);
        color: var(--blue-700);
    }
    .settings-tab svg { width: 20px; height: 20px; }
    
    .settings-content-section { display: none; }
    .settings-content-section.active { 
        display: block; 
        animation: slideUp 0.3s ease-out; 
    }

    @keyframes slideUp {
        from { opacity: 0; transform: translateY(10px); }
        to { opacity: 1; transform: translateY(0); }
    }
 
    @media (max-width: 1024px) {
        .settings-container { grid-template-columns: 1fr; gap: 1.5rem; }
        .settings-nav { position: relative; top: 0; display: flex; overflow-x: auto; gap: 0.5rem; padding: 0.5rem; }
        .settings-tab { white-space: nowrap; padding: 0.75rem 1.25rem; width: auto; margin-bottom: 0; }
    }

    .settings-card {
        background: var(--surface);
        border-radius: 24px;
        border: 1px solid var(--border);
        padding: 3rem;
        box-shadow: var(--shadow-sm);
    }

    .section-title {
        font-size: 1.5rem;
        font-weight: 800;
        margin-bottom: 0.5rem;
        letter-spacing: -0.02em;
    }

    .section-desc {
        color: var(--text-3);
        font-size: 1rem;
        margin-bottom: 2.5rem;
    }

    .form-section-label {
        display: block;
        font-weight: 800;
        color: var(--text-3);
        font-size: 0.75rem;
        text-transform: uppercase;
        letter-spacing: 0.05em;
        margin-bottom: 0.75rem;
    }
</style>

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
                <label class="form-section-label">Institute Name</label>
                <input type="text" name="site_name" class="form-control" style="border-radius: 12px; height: 50px;" value="<?= htmlspecialchars($settings['site_name'] ?? '') ?>" required>
            </div>
            
            <div class="form-group mb-6">
                <label class="form-section-label">Enrollment Instructions</label>
                <textarea name="payment_instructions" class="form-control" style="border-radius: 12px; padding: 1rem;" rows="4" required><?= htmlspecialchars($settings['payment_instructions'] ?? '') ?></textarea>
                <p style="font-size: 0.8rem; color: var(--text-3); margin-top: 0.5rem;">Shown to students during the checkout process.</p>
            </div>
            
            <div class="form-group mb-8">
                <label class="form-section-label">Payment Grace Period (Days)</label>
                <div style="position: relative; max-width: 200px;">
                    <input type="number" name="grace_period_days" class="form-control" style="border-radius: 12px; height: 50px;" value="<?= htmlspecialchars($settings['grace_period_days'] ?? '5') ?>" min="0" max="28" required>
                    <span style="position: absolute; right: 1rem; top: 50%; transform: translateY(-50%); font-weight: 700; color: var(--text-3); font-size: 0.85rem;">Days</span>
                </div>
            </div>
            
            <div style="background: var(--surface-2); padding: 1.5rem 2rem; border-radius: 16px; border: 1px solid var(--border); display: flex; align-items: center; justify-content: space-between; margin-bottom: 2.5rem;">
                <div>
                    <div style="font-weight: 800; color: var(--text); font-size: 1.1rem; margin-bottom: 0.25rem;">Academic Assessments</div>
                    <div style="font-size: 0.9rem; color: var(--text-3); font-weight: 600;">Enable past papers, hybrid exams, and student marking system.</div>
                </div>
                <label class="switch">
                    <input type="checkbox" name="enable_papers" value="1" <?= ($settings['enable_papers'] ?? '1') == '1' ? 'checked' : '' ?>>
                    <span class="slider round"></span>
                </label>
            </div>
            
            <button type="submit" class="btn btn-primary" style="height: 52px; padding: 0 2.5rem; border-radius: 12px; font-weight: 800; background: var(--blue-900); border: none;">Apply Changes</button>
        </div>

        <!-- Appearance Section -->
        <div id="section-appearance" class="settings-content-section settings-card">
            <h3 class="section-title">Visual Branding</h3>
            <p class="section-desc">Manage the landing page visuals and platform appearance.</p>
            
            <div class="form-group mb-8">
                <label class="form-section-label">Hero Background Image</label>
                <div style="position: relative; border-radius: 16px; overflow: hidden; border: 2px solid var(--border); margin-bottom: 1.5rem;">
                    <?php if (!empty($settings['hero_bg_image'])): ?>
                        <img src="<?= htmlspecialchars($settings['hero_bg_image']) ?>" style="width: 100%; height: 200px; object-fit: cover;">
                        <div style="position: absolute; inset: 0; background: linear-gradient(to top, rgba(0,0,0,0.6), transparent);"></div>
                    <?php else: ?>
                        <div style="width: 100%; height: 200px; background: var(--surface-2); display: flex; align-items: center; justify-content: center; color: var(--text-3);">
                            <svg style="width: 48px; height: 48px; opacity: 0.2;" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                        </div>
                    <?php endif; ?>
                </div>
                <input type="file" name="hero_bg_image" class="form-control" style="border-radius: 12px; height: 50px; padding: 0.6rem;" accept="image/*">
            </div>
            
            <div class="form-group mb-6">
                <label class="form-section-label">Hero Heading Text</label>
                <input type="text" name="hero_heading" class="form-control" style="border-radius: 12px; height: 50px;" value="<?= htmlspecialchars($settings['hero_heading'] ?? '') ?>" placeholder="e.g. Master Your Future with Expertise">
            </div>
            
            <div class="form-group mb-8">
                <label class="form-section-label">Hero Introduction Subtext</label>
                <textarea name="hero_subtext" class="form-control" style="border-radius: 12px; padding: 1rem;" rows="3" placeholder="e.g. High-quality educational resources tailored for academic excellence..."><?= htmlspecialchars($settings['hero_subtext'] ?? '') ?></textarea>
            </div>
            
            <button type="submit" class="btn btn-primary" style="height: 52px; padding: 0 2.5rem; border-radius: 12px; font-weight: 800; background: var(--blue-900); border: none;">Update Branding</button>
        </div>

        <!-- Tutor Section -->
        <div id="section-tutor" class="settings-content-section settings-card">
            <h3 class="section-title">Tutor Profile</h3>
            <p class="section-desc">Manage the public profile of the primary educator.</p>
            
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 2rem;" class="mb-8">
                <div class="form-group">
                    <label class="form-section-label">Full Name</label>
                    <input type="text" name="tutor_name" class="form-control" style="border-radius: 12px; height: 50px;" value="<?= htmlspecialchars($settings['tutor_name'] ?? '') ?>" placeholder="e.g. Dr. Jane Smith">
                </div>
                <div class="form-group">
                    <label class="form-section-label">Profile Portrait</label>
                    <div style="display: flex; align-items: center; gap: 1.5rem; background: var(--surface-2); padding: 0.75rem 1.25rem; border-radius: 16px; border: 1px solid var(--border);">
                        <div style="width: 56px; height: 56px; border-radius: 50%; overflow: hidden; border: 2px solid var(--blue-600); flex-shrink: 0;">
                            <?php if (!empty($settings['tutor_image'])): ?>
                                <img src="<?= htmlspecialchars($settings['tutor_image']) ?>" style="width: 100%; height: 100%; object-fit: cover;">
                            <?php else: ?>
                                <div style="width: 100%; height: 100%; background: var(--border); display: flex; align-items: center; justify-content: center; color: var(--text-3);">
                                    <svg style="width: 24px; height: 24px;" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg>
                                </div>
                            <?php endif; ?>
                        </div>
                        <input type="file" name="tutor_image" style="font-size: 0.8rem; font-weight: 700; color: var(--text-3);" accept="image/*">
                    </div>
                </div>
            </div>
            
            <div class="form-group mb-10">
                <label class="form-section-label">Professional Biography</label>
                <textarea name="tutor_bio" class="form-control" style="border-radius: 12px; padding: 1rem;" rows="5" placeholder="Highlight your academic background and teaching philosophy..."><?= htmlspecialchars($settings['tutor_bio'] ?? '') ?></textarea>
            </div>
            
            <button type="submit" class="btn btn-primary" style="height: 52px; padding: 0 2.5rem; border-radius: 12px; font-weight: 800; background: var(--blue-900); border: none;">Save Biography</button>
        </div>
    </form>

    <!-- Data Section -->
    <div id="section-data" class="settings-content-section settings-card">
        <h3 class="section-title">System & Export</h3>
        <p class="section-desc">Maintenance tools and historical data extraction.</p>
        
        <div style="background: var(--surface-2); padding: 3rem; border-radius: 20px; border: 1px solid var(--border); text-align: center;">
            <div style="font-size: 3rem; margin-bottom: 1.5rem;">📦</div>
            <h4 style="font-size: 1.25rem; font-weight: 800; margin-bottom: 0.75rem;">Archive Generator</h4>
            <p style="color: var(--text-3); font-weight: 600; margin-bottom: 2.5rem; max-width: 400px; margin-left: auto; margin-right: auto;">Compile all students' PDF submissions and payment records for the selected month into a single ZIP archive.</p>
            
            <div style="display: flex; flex-direction: column; align-items: center; gap: 1.25rem;">
                <input type="month" id="exportMonth" value="<?= date('Y-m') ?>" class="form-control" style="max-width: 300px; height: 52px; border-radius: 12px; text-align: center; font-weight: 800; font-size: 1.1rem; border: 2px solid var(--border);">
                
                <button onclick="startExport()" class="btn btn-primary" style="height: 52px; padding: 0 2.5rem; border-radius: 12px; font-weight: 800; background: var(--blue-900); border: none; display: flex; align-items: center; gap: 1rem;">
                    <svg style="width: 20px; height: 20px;" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/></svg>
                    Generate ZIP Archive
                </button>
            </div>
            
            <p id="exportStatus" style="margin-top: 2rem; font-size: 0.95rem; font-weight: 700; display: none; padding: 1rem; border-radius: 10px; background: white; border: 1px solid var(--border);"></p>
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
