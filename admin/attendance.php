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



<div class="flex-between-center mb-10">
    <div>
        <h2 class="text-4xl mb-2">Attendance Registry</h2>
        <p class="text-tertiary m-0 text-lg">Monitor class participation and verify student presence in real-time.</p>
    </div>
    <button onclick="document.getElementById('unpaidReport').style.display='flex'" class="btn btn-ghost btn-unpaid font-extrabold rounded-10">
        <svg class="icon icon-sm" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>
        Unpaid Attendees
    </button>
</div>

<?php if (empty($todays_classes)): ?>
    <div class="card p-20 text-center mb-10">
        <div class="text-6xl mb-6 grayscale opacity-20">📅</div>
        <h3 class="font-extrabold">No Physical Sessions Today</h3>
        <p class="text-tertiary text-lg">Schedule physical or hybrid classes to activate the QR scanner.</p>
    </div>
<?php else: ?>
    <div class="attendance-grid mb-12">
        
        <!-- Left Column: Scanner -->
        <div class="card p-8">
            <div class="flex-center-gap-3 mb-6">
                <div class="active-dot"></div>
                <h3 class="card-section-title">Active Scanner</h3>
            </div>
            
            <div class="form-group mb-6">
                <label class="label-accent-muted">Select Current Session</label>
                <div class="relative">
                    <select id="classSelect" class="form-control select-large">
                        <option value="">-- Choose target class for scanning --</option>
                        <?php foreach ($todays_classes as $c): ?>
                            <option value="<?= $c['id'] ?>">
                                <?= date('g:i A', strtotime($c['start_time'])) ?> • <?= htmlspecialchars($c['subject_name']) ?> (<?= htmlspecialchars($c['title']) ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <svg class="icon absolute-right-center pointer-events-none text-tertiary w-5 h-5"><polyline points="6,9 12,15 18,9"/></svg>
                </div>
            </div>
            
            <div id="scanner-container" class="scanner-wrapper" style="display: none;">
                <div id="reader" class="w-full"></div>
                <div id="scan-result" class="scan-result-box" style="display: none;"></div>
            </div>
        </div>
        
        <!-- Right Column: Live Log -->
        <div class="card p-0 overflow-hidden flex flex-col h-full">
            <div class="card-header-muted">
                <h3 class="card-section-title">Real-time Stream</h3>
                <a id="downloadBtn" href="#" class="btn btn-ghost btn-sm font-bold" style="display: none;">
                    <svg class="icon icon-sm" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
                    Download CSV
                </a>
            </div>
            
            <div id="live-log" class="live-log-container">
                <div class="p-24 text-center text-tertiary">
                    <div class="text-5xl mb-6 opacity-10">📡</div>
                    <p class="font-semibold">System ready. Waiting for student QR scans...</p>
                </div>
            </div>
        </div>
    </div>
<?php endif; ?>

<!-- Overview Section -->
<div class="mb-5">
    <h3 class="text-xl font-extrabold m-0">Historical Records</h3>
</div>

<div class="card card-table">
    <div class="card-header-muted flex-wrap gap-4">
        <h4 class="card-subsection-title">Class Participation Log</h4>
        
        <form method="GET" class="flex gap-3 items-center">
            <div class="relative">
                <select name="subject_id" class="form-control select-sm">
                    <option value="">All Subjects</option>
                    <?php foreach ($subjects as $s): ?>
                        <option value="<?= $s['id'] ?>" <?= $filter_subject == $s['id'] ? 'selected' : '' ?>><?= htmlspecialchars($s['name']) ?></option>
                    <?php endforeach; ?>
                </select>
                <svg class="icon icon-sm select-arrow"><polyline points="6,9 12,15 18,9"/></svg>
            </div>
            
            <div class="relative">
                <select name="type" class="form-control select-sm">
                    <option value="">All Types</option>
                    <option value="online" <?= $filter_type == 'online' ? 'selected' : '' ?>>Online</option>
                    <option value="physical" <?= $filter_type == 'physical' ? 'selected' : '' ?>>Physical</option>
                    <option value="hybrid" <?= $filter_type == 'hybrid' ? 'selected' : '' ?>>Hybrid</option>
                </select>
                <svg class="icon icon-sm select-arrow"><polyline points="6,9 12,15 18,9"/></svg>
            </div>
            
            <button type="submit" class="btn btn-primary font-bold h-10 px-5 text-sm rounded-8">Filter</button>
            <?php if ($filter_subject || $filter_type): ?>
                <a href="attendance.php" class="btn btn-ghost flex items-center font-bold h-10 px-4 text-sm rounded-8">Reset</a>
            <?php endif; ?>
        </form>
    </div>
    <div class="table-responsive">
        <table class="admin-table">
            <thead>
                <tr>
                    <th>Session Title</th>
                    <th>Delivery</th>
                    <th>Timeframe</th>
                    <th class="text-center">Total Present</th>
                    <th class="text-right">Reports</th>
                </tr>
            </thead>
                <tbody>
                    <?php if (empty($all_classes)): ?>
                        <tr><td colspan="5" class="p-20 text-center text-tertiary font-semibold">No attendance records found for the selected criteria.</td></tr>
                    <?php else: 
                        $current_subject = '';
                        foreach ($all_classes as $cls): 
                            if ($current_subject !== $cls['subject_name']):
                                $current_subject = $cls['subject_name'];
                    ?>
                        <tr class="bg-blue-50">
                            <td colspan="5" class="px-6 py-3 font-extrabold text-xs text-uppercase tracking-wider text-blue-600 border-b-blue-100">
                                Subject: <?= htmlspecialchars($current_subject) ?>
                            </td>
                        </tr>
                    <?php endif; ?>
                        <tr>
                            <td>
                                <div class="font-bold"><?= htmlspecialchars($cls['title']) ?></div>
                            </td>
                            <td>
                                <?php 
                                $type_class = 'badge-neutral';
                                if ($cls['class_type'] === 'online') $type_class = 'badge-blue';
                                if ($cls['class_type'] === 'physical') $type_class = 'badge-green';
                                if ($cls['class_type'] === 'hybrid') $type_class = 'badge-purple';
                                ?>
                                <span class="badge <?= $type_class ?> badge-uppercase">
                                    <?= $cls['class_type'] ?>
                                </span>
                            </td>
                            <td>
                                <div class="font-bold"><?= date('M j, Y', strtotime($cls['start_time'])) ?></div>
                                <div class="text-tertiary text-xs font-semibold"><?= date('g:i A', strtotime($cls['start_time'])) ?></div>
                            </td>
                            <td class="text-center">
                                <span class="stat-number text-xl"><?= $cls['attendance_count'] ?></span>
                            </td>
                            <td class="text-right">
                                <a href="api/export_attendance.php?class_id=<?= $cls['id'] ?>" class="btn btn-ghost btn-sm font-bold">
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
    <div id="unpaidReport" class="modal-overlay flex-center-top p-5" style="display: none;">
        <div class="modal modal-unpaid">
            <button onclick="document.getElementById('unpaidReport').style.display='none'" class="btn-close">&times;</button>
            
            <h3 class="mb-6">Students Who Attended Without Payment</h3>
            <p class="text-secondary text-sm mb-8">This report shows students who were marked present in physical/hybrid classes this month but have no approved payment for the corresponding subject.</p>
            
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
                <div class="text-center p-20">
                    <div class="text-5xl-plus mb-5">✨</div>
                    <h4 class="font-extrabold">All Clear!</h4>
                    <p class="text-tertiary font-semibold">No unauthorized attendance detected for this period.</p>
                </div>
            <?php else: ?>
                <div class="flex flex-col gap-3">
                    <?php foreach ($unpaid_list as $up): ?>
                        <div class="curriculum-item-card p-5-6">
                            <div>
                                <div class="font-extrabold text-lg"><?= htmlspecialchars($up['username']) ?></div>
                                <div class="flex items-center gap-2 mt-1">
                                    <span class="student-id-mono text-xs bg-blue-50 px-1.5 py-0.5 rounded-4"><?= htmlspecialchars($up['student_id']) ?></span>
                                    <span class="text-sm text-tertiary font-bold">• <?= htmlspecialchars($up['subject_name']) ?></span>
                                </div>
                            </div>
                            <div class="text-right">
                                <div class="font-bold text-sm"><?= date('M j, Y', strtotime($up['attended_at'])) ?></div>
                                <div class="text-xs text-tertiary font-semibold"><?= date('g:i A', strtotime($up['attended_at'])) ?></div>
                                <span class="badge badge-red badge-uppercase mt-2">Payment Missing</span>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Manual Override Modal (Unpaid Student) -->
    <div id="overrideModal" class="modal-overlay flex-center p-8" style="display: none;">
        <div class="modal modal-override">
            <div class="alert-icon-wrapper">
                <svg class="w-9 h-9" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path></svg>
            </div>
            <h2 class="mb-4 text-2xl font-extrabold">Payment Alert</h2>
            <p id="overrideText" class="mb-10 text-tertiary font-semibold text-lg leading-relaxed">Student has not settled the monthly fees for this module.</p>
            
            <div class="flex gap-4">
                <button onclick="denyStudent()" class="btn btn-ghost flex-1 font-bold rounded-10 text-danger">Deny Entry</button>
                <button onclick="allowStudent()" class="btn btn-primary flex-1 font-bold rounded-10 bg-danger border-none">Allow Entry</button>
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
