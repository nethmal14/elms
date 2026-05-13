<?php
require_once __DIR__ . '/includes/header.php';

// Fetch today's physical or hybrid classes
$today_start = date('Y-m-d 00:00:00');
$today_end = date('Y-m-d 23:59:59');

$stmt = $pdo->prepare("
    SELECT c.*, s.name as subject_name 
    FROM classes c 
    JOIN subjects s ON c.subject_id = s.id 
    WHERE c.start_time BETWEEN ? AND ?
    AND c.class_type IN ('physical', 'hybrid')
    ORDER BY c.start_time ASC
");
$stmt->execute([$today_start, $today_end]);
$todays_classes = $stmt->fetchAll();

// Fetch all subjects for filtering
$subjects_stmt = $pdo->query("SELECT id, name FROM subjects ORDER BY name ASC");
$subjects = $subjects_stmt->fetchAll();

// Handle Filters
$filter_subject = $_GET['subject_id'] ?? '';
$filter_type = $_GET['type'] ?? '';

// Build query for All Classes
$query = "
    SELECT c.*, s.name as subject_name,
           (SELECT COUNT(*) FROM attendance a WHERE a.class_id = c.id) as attendance_count
    FROM classes c
    JOIN subjects s ON c.subject_id = s.id
    WHERE 1=1
";
$params = [];

if ($filter_subject) {
    $query .= " AND c.subject_id = ?";
    $params[] = $filter_subject;
}
if ($filter_type) {
    $query .= " AND c.class_type = ?";
    $params[] = $filter_type;
}

$query .= " ORDER BY c.start_time DESC";
$all_classes_stmt = $pdo->prepare($query);
$all_classes_stmt->execute($params);
$all_classes = $all_classes_stmt->fetchAll();
?>

<style>
    #reader {
        border: none !important;
        background: var(--surface);
        border-radius: 16px;
        overflow: hidden;
    }
    #reader __dashboard {
        background: var(--surface-2);
        padding: 1.25rem;
        border-bottom: 1px solid var(--border);
    }
    #reader button {
        background: var(--blue-600) !important;
        color: white !important;
        border: none !important;
        padding: 0.75rem 1.25rem !important;
        border-radius: 10px !important;
        font-weight: 700 !important;
        cursor: pointer !important;
        transition: all 0.2s ease !important;
        text-transform: uppercase !important;
        font-size: 0.8rem !important;
        letter-spacing: 0.02em !important;
    }
    #reader button:hover {
        background: var(--blue-700) !important;
        transform: translateY(-1px) !important;
    }
    #reader select {
        padding: 0.6rem !important;
        border-radius: 8px !important;
        border: 1px solid var(--border) !important;
        background: var(--surface) !important;
        color: var(--text) !important;
        font-size: 0.95rem !important;
    }
    #scan-result {
        transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        box-shadow: 0 4px 12px rgba(0,0,0,0.05);
    }
    .attendance-grid {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 2.5rem;
        align-items: start;
    }
    @media (max-width: 1024px) {
        .attendance-grid {
            grid-template-columns: 1fr;
            gap: 1.5rem;
        }
    }
    .log-entry {
        padding: 1rem;
        border-bottom: 1px solid var(--border);
        display: flex;
        justify-content: space-between;
        align-items: center;
        transition: background 0.2s;
    }
    .log-entry:hover { background: var(--surface-2); }
</style>

<div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 2.5rem;">
    <div>
        <h2 style="font-size: 2.25rem; margin-bottom: 0.5rem;">Attendance Registry</h2>
        <p style="color: var(--text-3); margin: 0; font-size: 1.1rem;">Monitor class participation and verify student presence in real-time.</p>
    </div>
    <button onclick="document.getElementById('unpaidReport').style.display='flex'" class="btn btn-ghost" style="color: var(--red-600); font-weight: 800; border-radius: 10px;">
        <svg class="icon icon-sm" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>
        Unpaid Attendees
    </button>
</div>

<?php if (empty($todays_classes)): ?>
    <div class="card" style="text-align: center; padding: 5rem 2rem; margin-bottom: 2.5rem;">
        <div style="font-size: 4rem; margin-bottom: 1.5rem; filter: grayscale(1); opacity: 0.2;">📅</div>
        <h3 style="color: var(--text); font-weight: 800;">No Physical Sessions Today</h3>
        <p style="color: var(--text-3); font-size: 1.1rem;">Schedule physical or hybrid classes to activate the QR scanner.</p>
    </div>
<?php else: ?>
    <div class="attendance-grid" style="margin-bottom: 3rem;">
        
        <!-- Left Column: Scanner -->
        <div class="card" style="padding: 2rem;">
            <div style="display: flex; align-items: center; gap: 0.75rem; margin-bottom: 1.5rem;">
                <div style="width: 10px; height: 10px; border-radius: 50%; background: var(--blue-600); box-shadow: 0 0 0 4px var(--blue-50);"></div>
                <h3 style="margin: 0; font-size: 1.1rem; text-transform: uppercase; letter-spacing: 0.05em; font-weight: 800;">Active Scanner</h3>
            </div>
            
            <div class="form-group mb-6">
                <label style="margin-bottom: 0.75rem; font-weight: 700; color: var(--text-3); font-size: 0.8rem; text-transform: uppercase;">Select Current Session</label>
                <div style="position: relative;">
                    <select id="classSelect" class="form-control" style="border-radius: 12px; height: 56px; font-weight: 600; padding-right: 3rem;">
                        <option value="">-- Choose target class for scanning --</option>
                        <?php foreach ($todays_classes as $c): ?>
                            <option value="<?= $c['id'] ?>">
                                <?= date('g:i A', strtotime($c['start_time'])) ?> • <?= htmlspecialchars($c['subject_name']) ?> (<?= htmlspecialchars($c['title']) ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <svg class="icon" style="position: absolute; right: 1.25rem; top: 50%; transform: translateY(-50%); pointer-events: none; color: var(--text-3); width: 20px; height: 20px;"><polyline points="6,9 12,15 18,9"/></svg>
                </div>
            </div>
            
            <div id="scanner-container" style="display: none; background: var(--surface-2); border-radius: 20px; padding: 1.5rem; border: 1px solid var(--border);">
                <div id="reader" style="width: 100%;"></div>
                <div id="scan-result" style="margin-top: 1.5rem; padding: 1.25rem; border-radius: 14px; text-align: center; font-weight: 800; display: none; font-size: 1.15rem; border: 1px solid var(--border);"></div>
            </div>
        </div>
        
        <!-- Right Column: Live Log -->
        <div class="card" style="padding: 0; overflow: hidden; display: flex; flex-direction: column; height: 100%;">
            <div style="padding: 1.5rem 2rem; background: var(--surface-2); border-bottom: 1px solid var(--border); display: flex; justify-content: space-between; align-items: center;">
                <h3 style="margin: 0; font-size: 1.1rem; text-transform: uppercase; letter-spacing: 0.05em; font-weight: 800;">Real-time Stream</h3>
                <a id="downloadBtn" href="#" class="btn btn-ghost btn-sm" style="display: none; font-weight: 700;">
                    <svg class="icon icon-sm" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
                    Download CSV
                </a>
            </div>
            
            <div id="live-log" style="height: 480px; overflow-y: auto; background: var(--bg-body);">
                <div style="color: var(--text-3); text-align: center; padding: 6rem 2rem;">
                    <div style="font-size: 3rem; margin-bottom: 1.5rem; opacity: 0.1;">📡</div>
                    <p style="font-weight: 600;">System ready. Waiting for student QR scans...</p>
                </div>
            </div>
        </div>
    </div>
<?php endif; ?>

<!-- Overview Section -->
<div style="margin-bottom: 1.25rem;">
    <h3 style="margin: 0; font-size: 1.25rem; font-weight: 800; color: var(--text);">Historical Records</h3>
</div>

<div class="card" style="padding: 0; overflow: hidden; border-radius: 16px;">
    <div style="padding: 1.5rem 2rem; background: var(--surface-2); border-bottom: 1px solid var(--border); display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 1rem;">
        <h4 style="margin: 0; font-size: 0.9rem; text-transform: uppercase; letter-spacing: 0.05em; color: var(--text-3); font-weight: 800;">Class Participation Log</h4>
        
        <form method="GET" style="display: flex; gap: 0.75rem; align-items: center;">
            <div style="position: relative;">
                <select name="subject_id" class="form-control" style="width: auto; height: 40px; padding: 0 2.5rem 0 1rem; font-size: 0.85rem; border-radius: 8px; font-weight: 600;">
                    <option value="">All Subjects</option>
                    <?php foreach ($subjects as $s): ?>
                        <option value="<?= $s['id'] ?>" <?= $filter_subject == $s['id'] ? 'selected' : '' ?>><?= htmlspecialchars($s['name']) ?></option>
                    <?php endforeach; ?>
                </select>
                <svg class="icon icon-sm" style="position: absolute; right: 0.75rem; top: 50%; transform: translateY(-50%); pointer-events: none; color: var(--text-3);"><polyline points="6,9 12,15 18,9"/></svg>
            </div>
            
            <div style="position: relative;">
                <select name="type" class="form-control" style="width: auto; height: 40px; padding: 0 2.5rem 0 1rem; font-size: 0.85rem; border-radius: 8px; font-weight: 600;">
                    <option value="">All Types</option>
                    <option value="online" <?= $filter_type == 'online' ? 'selected' : '' ?>>Online</option>
                    <option value="physical" <?= $filter_type == 'physical' ? 'selected' : '' ?>>Physical</option>
                    <option value="hybrid" <?= $filter_type == 'hybrid' ? 'selected' : '' ?>>Hybrid</option>
                </select>
                <svg class="icon icon-sm" style="position: absolute; right: 0.75rem; top: 50%; transform: translateY(-50%); pointer-events: none; color: var(--text-3);"><polyline points="6,9 12,15 18,9"/></svg>
            </div>
            
            <button type="submit" class="btn btn-primary" style="height: 40px; padding: 0 1.25rem; font-size: 0.85rem; border-radius: 8px; font-weight: 700;">Filter</button>
            <?php if ($filter_subject || $filter_type): ?>
                <a href="attendance.php" class="btn btn-ghost" style="height: 40px; display: flex; align-items: center; padding: 0 1rem; font-size: 0.85rem; border-radius: 8px; font-weight: 700;">Reset</a>
            <?php endif; ?>
        </form>
    </div>
    <div class="table-responsive">
        <table style="width: 100%; border-collapse: collapse;">
            <thead>
                <tr style="text-align: left; border-bottom: 1px solid var(--border); background: var(--surface-2);">
                    <th style="padding: 1rem 1.5rem; font-size: 0.8rem; text-transform: uppercase; letter-spacing: 0.05em; color: var(--text-3);">Session Title</th>
                    <th style="padding: 1rem 1.5rem; font-size: 0.8rem; text-transform: uppercase; letter-spacing: 0.05em; color: var(--text-3);">Delivery</th>
                    <th style="padding: 1rem 1.5rem; font-size: 0.8rem; text-transform: uppercase; letter-spacing: 0.05em; color: var(--text-3);">Timeframe</th>
                    <th style="padding: 1rem 1.5rem; font-size: 0.8rem; text-transform: uppercase; letter-spacing: 0.05em; color: var(--text-3); text-align: center;">Total Present</th>
                    <th style="padding: 1rem 1.5rem; font-size: 0.8rem; text-transform: uppercase; letter-spacing: 0.05em; color: var(--text-3); text-align: right;">Reports</th>
                </tr>
            </thead>
                <tbody>
                    <?php if (empty($all_classes)): ?>
                        <tr><td colspan="5" style="text-align: center; padding: 5rem 2rem; color: var(--text-3); font-weight: 600;">No attendance records found for the selected criteria.</td></tr>
                    <?php else: 
                        $current_subject = '';
                        foreach ($all_classes as $cls): 
                            if ($current_subject !== $cls['subject_name']):
                                $current_subject = $cls['subject_name'];
                    ?>
                        <tr style="background: var(--blue-50);">
                            <td colspan="5" style="padding: 0.75rem 1.5rem; font-weight: 800; font-size: 0.75rem; text-transform: uppercase; letter-spacing: 0.05em; color: var(--blue-600); border-bottom: 1px solid var(--blue-100);">
                                Subject: <?= htmlspecialchars($current_subject) ?>
                            </td>
                        </tr>
                    <?php endif; ?>
                        <tr style="border-bottom: 1px solid var(--border);">
                            <td style="padding: 1.25rem 1.5rem;">
                                <div style="font-weight: 700; color: var(--text);"><?= htmlspecialchars($cls['title']) ?></div>
                            </td>
                            <td style="padding: 1.25rem 1.5rem;">
                                <?php 
                                $type_class = 'badge-neutral';
                                if ($cls['class_type'] === 'online') $type_class = 'badge-blue';
                                if ($cls['class_type'] === 'physical') $type_class = 'badge-green';
                                if ($cls['class_type'] === 'hybrid') $type_class = 'badge-purple';
                                ?>
                                <span class="badge <?= $type_class ?>" style="text-transform: uppercase; font-size: 0.65rem; font-weight: 800;">
                                    <?= $cls['class_type'] ?>
                                </span>
                            </td>
                            <td style="padding: 1.25rem 1.5rem;">
                                <div style="font-weight: 700; color: var(--text);"><?= date('M j, Y', strtotime($cls['start_time'])) ?></div>
                                <div style="color: var(--text-3); font-size: 0.8rem; font-weight: 600;"><?= date('g:i A', strtotime($cls['start_time'])) ?></div>
                            </td>
                            <td style="padding: 1.25rem 1.5rem; text-align: center;">
                                <span style="font-weight: 800; color: var(--blue-600); font-size: 1.15rem;"><?= $cls['attendance_count'] ?></span>
                            </td>
                            <td style="padding: 1.25rem 1.5rem; text-align: right;">
                                <a href="api/export_attendance.php?class_id=<?= $cls['id'] ?>" class="btn btn-ghost btn-sm" style="font-weight: 700;">
                                    <svg class="icon icon-sm" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
                                    Export CSV
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

    <!-- Unpaid Attendees Modal -->
    <div id="unpaidReport" style="display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.85); z-index: 1000; align-items: flex-start; justify-content: center; backdrop-filter: blur(8px); padding: 20px; overflow-y: auto;">
        <div class="card glass-panel" style="width: 100%; max-width: 800px; max-height: 90vh; overflow-y: auto; position: relative; padding: 2rem;">
            <button onclick="document.getElementById('unpaidReport').style.display='none'" style="position: absolute; top: 1rem; right: 1rem; background: none; border: none; color: var(--text-secondary); cursor: pointer; font-size: 1.5rem;">&times;</button>
            
            <h3 style="margin-bottom: 1.5rem;">Students Who Attended Without Payment</h3>
            <p style="color: var(--text-secondary); font-size: 0.9rem; margin-bottom: 2rem;">This report shows students who were marked present in physical/hybrid classes this month but have no approved payment for the corresponding subject.</p>
            
            <?php
            $month_start = date('Y-m-01 00:00:00');
            $tenant_id_val = TenantContext::get()['id'];
            $unpaid_stmt = $pdo->prepare("
                SELECT u.username, u.student_id, s.name as subject_name, c.start_time, a.attended_at
                FROM attendance a
                JOIN users u ON a.user_id = u.id
                JOIN classes c ON a.class_id = c.id
                JOIN subjects s ON c.subject_id = s.id
                WHERE a.tenant_id = ?
                AND a.attended_at >= ?
                AND NOT EXISTS (
                    SELECT 1 FROM payments p 
                    WHERE p.user_id = a.user_id 
                    AND p.subject_id = c.subject_id 
                    AND p.status = 'approved'
                    AND p.created_at >= ?
                )
                ORDER BY a.attended_at DESC
            ");
            $unpaid_stmt->execute([$tenant_id_val, $month_start, $month_start]);
            $unpaid_list = $unpaid_stmt->fetchAll();
            ?>
            <?php if (empty($unpaid_list)): ?>
                <div style="text-align: center; padding: 5rem 2rem;">
                    <div style="font-size: 3.5rem; margin-bottom: 1.25rem;">✨</div>
                    <h4 style="color: var(--text); font-weight: 800;">All Clear!</h4>
                    <p style="color: var(--text-3); font-weight: 600;">No unauthorized attendance detected for this period.</p>
                </div>
            <?php else: ?>
                <div style="display: flex; flex-direction: column; gap: 0.75rem;">
                    <?php foreach ($unpaid_list as $up): ?>
                        <div style="background: var(--surface); border: 1px solid var(--border); border-radius: 14px; padding: 1.25rem 1.5rem; display: flex; justify-content: space-between; align-items: center; transition: transform 0.2s; cursor: default;" onmouseover="this.style.transform='translateX(4px)'" onmouseout="this.style.transform='translateX(0)'">
                            <div>
                                <div style="font-weight: 800; font-size: 1.05rem; color: var(--text);"><?= htmlspecialchars($up['username']) ?></div>
                                <div style="display: flex; align-items: center; gap: 0.5rem; margin-top: 0.25rem;">
                                    <span style="font-family: 'JetBrains Mono', monospace; font-size: 0.75rem; color: var(--blue-600); font-weight: 800; background: var(--blue-50); padding: 0.1rem 0.4rem; border-radius: 4px;"><?= htmlspecialchars($up['student_id']) ?></span>
                                    <span style="font-size: 0.85rem; color: var(--text-3); font-weight: 700;">• <?= htmlspecialchars($up['subject_name']) ?></span>
                                </div>
                            </div>
                            <div style="text-align: right;">
                                <div style="font-weight: 700; color: var(--text); font-size: 0.9rem;"><?= date('M j, Y', strtotime($up['attended_at'])) ?></div>
                                <div style="font-size: 0.8rem; color: var(--text-3); font-weight: 600;"><?= date('g:i A', strtotime($up['attended_at'])) ?></div>
                                <span class="badge badge-red" style="margin-top: 0.5rem; text-transform: uppercase; font-size: 0.65rem; font-weight: 800;">Payment Missing</span>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Manual Override Modal (Unpaid Student) -->
    <div id="overrideModal" class="modal-overlay" style="display: none; align-items: center; justify-content: center; padding: 2rem;">
        <div class="modal" style="max-width: 440px; border: 2px solid var(--red-600); padding: 2.5rem; text-align: center;">
            <div style="width: 72px; height: 72px; border-radius: 50%; background: var(--red-50); color: var(--red-600); display: flex; align-items: center; justify-content: center; margin: 0 auto 1.5rem;">
                <svg style="width: 36px; height: 36px;" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path></svg>
            </div>
            <h2 style="color: var(--text); margin-bottom: 0.75rem; font-size: 1.5rem; font-weight: 800;">Payment Alert</h2>
            <p id="overrideText" style="margin-bottom: 2.5rem; color: var(--text-3); line-height: 1.6; font-weight: 600; font-size: 1.05rem;">Student has not settled the monthly fees for this module.</p>
            
            <div style="display: flex; gap: 1rem;">
                <button onclick="denyStudent()" class="btn btn-ghost" style="flex: 1; color: var(--red-600); font-weight: 700; border-radius: 10px;">Deny Entry</button>
                <button onclick="allowStudent()" class="btn btn-primary" style="flex: 1; background: var(--red-600); border: none; font-weight: 700; border-radius: 10px;">Allow Entry</button>
            </div>
        </div>
    </div>

    <script src="https://unpkg.com/html5-qrcode"></script>
    <script>
        const classSelect = document.getElementById('classSelect');
        const scannerContainer = document.getElementById('scanner-container');
        const scanResult = document.getElementById('scan-result');
        const liveLog = document.getElementById('live-log');
        const downloadBtn = document.getElementById('downloadBtn');
        const overrideModal = document.getElementById('overrideModal');
        const overrideText = document.getElementById('overrideText');
        
        let html5QrcodeScanner = null;
        let isProcessing = false;
        let pendingStudentId = null;

        // Sound helper using Web Audio API
        const playSound = (type) => {
            try {
                const ctx = new (window.AudioContext || window.webkitAudioContext)();
                const osc = ctx.createOscillator();
                const gain = ctx.createGain();

                osc.connect(gain);
                gain.connect(ctx.destination);

                if (type === 'success') {
                    osc.type = 'sine';
                    osc.frequency.setValueAtTime(880, ctx.currentTime); 
                    gain.gain.setValueAtTime(0.1, ctx.currentTime);
                    gain.gain.exponentialRampToValueAtTime(0.01, ctx.currentTime + 0.2);
                    osc.start();
                    osc.stop(ctx.currentTime + 0.2);
                } else {
                    osc.type = 'sawtooth';
                    osc.frequency.setValueAtTime(220, ctx.currentTime); 
                    gain.gain.setValueAtTime(0.1, ctx.currentTime);
                    gain.gain.exponentialRampToValueAtTime(0.01, ctx.currentTime + 0.4);
                    osc.start();
                    osc.stop(ctx.currentTime + 0.4);
                }
            } catch (e) { console.error("Audio error", e); }
        };

        if (classSelect) {
            classSelect.addEventListener('change', function() {
                if (this.value) {
                    scannerContainer.style.display = 'block';
                    downloadBtn.style.display = 'inline-block';
                    downloadBtn.href = 'api/export_attendance.php?class_id=' + this.value;
                    initScanner();
                } else {
                    scannerContainer.style.display = 'none';
                    downloadBtn.style.display = 'none';
                    if (html5QrcodeScanner) {
                        html5QrcodeScanner.clear();
                    }
                }
            });
        }

        function initScanner() {
            if (html5QrcodeScanner) {
                html5QrcodeScanner.clear();
            }
            html5QrcodeScanner = new Html5QrcodeScanner(
                "reader",
                { fps: 10, qrbox: {width: 250, height: 250} },
                /* verbose= */ false);
            html5QrcodeScanner.render(onScanSuccess, onScanFailure);
        }

        function onScanSuccess(decodedText, decodedResult) {
            if (isProcessing) return; // Prevent double scanning
            isProcessing = true;
            
            const classId = classSelect.value;
            const studentId = decodedText;
            
            // Highlight result box
            scanResult.style.display = 'block';
            scanResult.style.background = 'var(--surface-color)';
            scanResult.style.color = 'var(--text-primary)';
            scanResult.style.border = '1px solid var(--border-color)';
            scanResult.innerHTML = `
                <div style="display: flex; align-items: center; justify-content: center; gap: 0.75rem;">
                    <div style="width: 12px; height: 12px; border-radius: 50%; background: var(--primary-color); animation: pulse 1.5s infinite;"></div>
                    Processing ${studentId}...
                </div>
                <style>@keyframes pulse { 0% { transform: scale(0.95); opacity: 1; } 50% { transform: scale(1.1); opacity: 0.5; } 100% { transform: scale(0.95); opacity: 1; } }</style>
            `;
            
            processAttendance(classId, studentId, false);
        }

        function onScanFailure(error) {
            // handle scan failure, usually better to ignore and keep scanning
        }

        function processAttendance(classId, studentId, forceAllow) {
            const formData = new FormData();
            formData.append('class_id', classId);
            formData.append('student_id', studentId);
            formData.append('force_allow', forceAllow ? '1' : '0');

            fetch('api/mark_attendance.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.status === 'success') {
                    playSound('success');
                    showResult(data.message, '#34c759');
                    addToLog(data.student_name, 'Marked Present', '#34c759');
                    overrideModal.style.display = 'none';
                    setTimeout(() => { isProcessing = false; }, 1500); // Wait 1.5s before next scan
                } 
                else if (data.status === 'unpaid') {
                    playSound('fail');
                    // Trigger override modal
                    pendingStudentId = studentId;
                    overrideText.innerText = `${data.student_name} (${studentId}) has NOT paid for ${data.subject_name} this month.`;
                    overrideModal.style.display = 'flex';
                }
                else {
                    playSound('fail');
                    showResult(data.message, 'var(--accent-color)');
                    setTimeout(() => { isProcessing = false; }, 2000);
                }
            })
            .catch(error => {
                showResult('Network Error', 'var(--accent-color)');
                setTimeout(() => { isProcessing = false; }, 2000);
            });
        }

        function denyStudent() {
            overrideModal.style.display = 'none';
            showResult('Entry Denied', 'var(--accent-color)');
            addToLog('Student ' + pendingStudentId, 'Entry Denied (Unpaid)', 'var(--accent-color)');
            pendingStudentId = null;
            setTimeout(() => { isProcessing = false; }, 1500);
        }

        function allowStudent() {
            if (pendingStudentId) {
                // Close the modal immediately for instant UX feedback;
                // processAttendance will show the result in the scan result box
                overrideModal.style.display = 'none';
                processAttendance(classSelect.value, pendingStudentId, true);
            }
        }

        function showResult(text, color) {
            scanResult.innerText = text;
            scanResult.style.background = color;
            scanResult.style.color = '#fff';
            scanResult.style.border = 'none';
        }

        function addToLog(name, status, color) {
            if (liveLog.innerHTML.includes('ready')) {
                liveLog.innerHTML = '';
            }
            const entry = document.createElement('div');
            entry.className = 'log-entry';
            
            const time = new Date().toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
            
            entry.innerHTML = `
                <div style="display: flex; flex-direction: column;">
                    <span style="font-weight: 800; color: var(--text); font-size: 1rem;">${name}</span>
                    <span style="font-size: 0.75rem; color: var(--text-3); font-weight: 600;">MARKING: ${time}</span>
                </div>
                <span style="color:${color}; font-weight:800; font-size:0.8rem; text-transform: uppercase; letter-spacing: 0.05em;">${status}</span>
            `;
            liveLog.prepend(entry);
        }
    </script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
