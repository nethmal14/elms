<?php
require_once __DIR__ . '/includes/header.php';

$pdo = getDB();

if (!isset($_GET['id'])) {
    header("Location: papers.php");
    exit;
}

$submission_id = $_GET['id'];

// Get submission details
$stmt = $pdo->prepare("
    SELECT ps.*, u.username as student_name, p.title as paper_title, p.pdf_path as original_path, ps.submission_path
    FROM paper_submissions ps
    JOIN users u ON ps.user_id = u.id
    JOIN papers p ON ps.paper_id = p.id
    WHERE ps.id = ?
");
$stmt->execute([$submission_id]);
$sub = $stmt->fetch();

if (!$sub) {
    header("Location: papers.php");
    exit;
}

// Construct PDF URL
$pdf_url = '../' . TenantContext::getUploadUrl('submissions/' . $sub['submission_path']);
?>

<div class="container-fluid" style="padding: 0; height: 100vh; background: #0b0e14; display: flex; flex-direction: column; overflow: hidden;">
    <!-- PDF Viewer Area -->
    <div id="pdf-container" style="flex: 1; overflow-y: auto; padding: 4rem 0; display: flex; flex-direction: column; align-items: center; gap: 3rem; position: relative;">
        <div id="loading-overlay" class="flex-center" style="color: #fff; font-size: 1.2rem; flex-direction: column; gap: 1.5rem;">
            <div class="spinner"></div>
            <div style="font-weight: 800; letter-spacing: 0.05em; text-transform: uppercase; font-size: 0.8rem; opacity: 0.8;">Loading Assessment...</div>
        </div>
        <div id="eraser-cursor" style="position: fixed; pointer-events: none; width: 40px; height: 40px; border: 2px solid rgba(255,255,255,0.5); border-radius: 50%; box-shadow: 0 0 20px rgba(0,0,0,0.5); display: none; z-index: 10000; background: rgba(255,255,255,0.1); backdrop-filter: blur(2px);"></div>
    </div>

    <!-- Floating Sidebar Tools -->
    <div class="floating-toolbar">
        <div class="tool-section" style="padding: 0; background: none; box-shadow: none;">
            <a href="papers.php" class="side-btn exit" title="Exit marking" style="background: rgba(239, 68, 68, 0.2); color: #f87171; border: 1px solid rgba(239, 68, 68, 0.2);">
                <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"></line><line x1="6" y1="6" x2="18" y2="18"></line></svg>
            </a>
        </div>
        
        <div class="tool-section bg-glass main-tools" style="border-radius: 24px; padding: 1rem 0.75rem;">
            <button onclick="setTool('pen')" id="btn-pen" class="tool-btn active" title="Pen Tool">
                <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M12 19l7-7 3 3-7 7-3-3z"></path><path d="M18 13l-1.5-7.5L2 2l3.5 14.5L13 18l5-5z"></path><path d="M2 2l5 5"></path></svg>
            </button>
            <button onclick="setTool('eraser')" id="btn-eraser" class="tool-btn" title="Eraser Tool">
                <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M20 20H7L3 16C2 15 2 13 3 12L13 2C14 1 16 1 17 2L21 6C22 7 22 9 21 10L11 20"></path></svg>
            </button>
            
            <div class="divider" style="background: rgba(255,255,255,0.1); margin: 0.75rem 0;"></div>
            
            <div class="color-wrapper" style="width: 36px; height: 36px;">
                <input type="color" id="pen-color" value="#ff3b30" onchange="updatePenColor(this.value)">
                <div class="color-preview" id="color-preview-circle" style="width: 36px; height: 36px; border: 3px solid rgba(255,255,255,0.2);"></div>
            </div>

            <select id="pen-size" class="side-select" style="width: 52px; height: 32px; font-weight: 800; background: rgba(255,255,255,0.05); border-radius: 10px;">
                <option value="2">2</option>
                <option value="4" selected>4</option>
                <option value="8">8</option>
                <option value="12">12</option>
            </select>
        </div>

        <div class="tool-section bg-glass action-section" style="border-radius: 24px; padding: 1.25rem 0.75rem;">
            <div class="marks-wrapper">
                <label style="font-weight: 900; opacity: 0.6; font-size: 0.65rem;">Score</label>
                <input type="number" id="marks-input" min="0" max="100" placeholder="0" class="side-marks" style="width: 52px; border-radius: 12px; background: rgba(255,255,255,0.05); border: 1px solid rgba(255,255,255,0.1); font-size: 1.25rem; padding: 0.75rem 0;">
            </div>
            <button onclick="saveMarkedPDF()" id="save-btn" class="send-btn" title="Finalize and Send" style="width: 52px; height: 70px; background: var(--blue-600); box-shadow: 0 10px 20px rgba(37, 99, 235, 0.3);">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><line x1="22" y1="2" x2="11" y2="13"></line><polygon points="22 2 15 22 11 13 2 9 22 2"></polygon></svg>
                <span style="margin-top: 0.25rem; font-size: 0.6rem;">Send</span>
            </button>
        </div>
    </div>
</div>

<style>
    .floating-toolbar {
        position: fixed; top: 2rem; right: 2rem; width: 72px;
        display: flex; flex-direction: column; gap: 1rem; z-index: 2000;
    }
    .tool-section {
        display: flex; flex-direction: column; gap: 0.75rem; align-items: center;
        box-shadow: 0 20px 50px rgba(0,0,0,0.5);
    }
    .bg-glass {
        background: rgba(23, 23, 26, 0.85);
        backdrop-filter: blur(20px) saturate(180%);
        border: 1px solid rgba(255, 255, 255, 0.1);
    }
    .tool-btn, .side-btn {
        width: 52px; height: 52px; border-radius: 16px; border: none;
        background: rgba(255, 255, 255, 0.05); color: #94a3b8; cursor: pointer; transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
        display: flex; align-items: center; justify-content: center;
    }
    .tool-btn:hover { background: rgba(255, 255, 255, 0.1); color: #fff; transform: translateY(-2px); }
    .tool-btn.active { background: var(--blue-600); color: #fff; box-shadow: 0 8px 20px rgba(37, 99, 235, 0.4); transform: scale(1.05); }
    
    .side-marks:focus { outline: none; border-color: var(--blue-600) !important; background: rgba(255,255,255,0.1) !important; }
    
    .pdf-page-wrapper {
        position: relative;
        box-shadow: 0 30px 100px rgba(0,0,0,0.8);
        background: #fff;
        margin-bottom: 2rem;
        border-radius: 4px;
        overflow: hidden;
    }
    .pdf-canvas { display: block; }
    .annotation-canvas {
        position: absolute; top: 0; left: 0;
        cursor: crosshair;
        touch-action: none;
    }

    .spinner {
        width: 50px; height: 50px; border: 4px solid rgba(255,255,255,0.05);
        border-top-color: var(--blue-600); border-radius: 50%;
        animation: spin 0.8s cubic-bezier(0.4, 0, 0.2, 1) infinite;
    }
    @keyframes spin { to { transform: rotate(360deg); } }
</style>

<script src="https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.4.120/pdf.min.js"></script>
<script src="https://unpkg.com/pdf-lib/dist/pdf-lib.min.js"></script>

<script>
    pdfjsLib.GlobalWorkerOptions.workerSrc = 'https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.4.120/pdf.worker.min.js';

    const pdfUrl = '<?= $pdf_url ?>';
    const submissionId = '<?= $submission_id ?>';
    let pdfDoc = null;
    let pages = [];
    let currentTool = 'pen';
    let isDrawing = false;

    // Load PDF
    async function init() {
        try {
            const loadingTask = pdfjsLib.getDocument(pdfUrl);
            pdfDoc = await loadingTask.promise;
            document.getElementById('loading-overlay').remove();

            for (let i = 1; i <= pdfDoc.numPages; i++) {
                await renderPage(i);
            }
        } catch (err) {
            console.error(err);
            alert('Error loading PDF: ' + err.message);
        }
    }

    async function renderPage(num) {
        const page = await pdfDoc.getPage(num);
        const viewport = page.getViewport({ scale: 1.5 });

        const wrapper = document.createElement('div');
        wrapper.className = 'pdf-page-wrapper';
        wrapper.style.width = viewport.width + 'px';
        wrapper.style.height = viewport.height + 'px';

        const canvas = document.createElement('canvas');
        canvas.className = 'pdf-canvas';
        const ctx = canvas.getContext('2d');
        canvas.width = viewport.width;
        canvas.height = viewport.height;

        const annCanvas = document.createElement('canvas');
        annCanvas.className = 'annotation-canvas';
        annCanvas.width = viewport.width;
        annCanvas.height = viewport.height;
        const annCtx = annCanvas.getContext('2d');

        wrapper.appendChild(canvas);
        wrapper.appendChild(annCanvas);
        document.getElementById('pdf-container').appendChild(wrapper);

        await page.render({ canvasContext: ctx, viewport: viewport }).promise;

        const pageObj = {
            pageNum: num,
            canvas: annCanvas,
            ctx: annCtx,
            viewport: viewport
        };
        pages.push(pageObj);
        setupDrawing(pageObj);
    }

    function setupDrawing(page) {
        const canvas = page.canvas;
        const ctx = page.ctx;

        const getPos = (e) => {
            const rect = canvas.getBoundingClientRect();
            const clientX = e.touches ? e.touches[0].clientX : e.clientX;
            const clientY = e.touches ? e.touches[0].clientY : e.clientY;
            return {
                x: clientX - rect.left,
                y: clientY - rect.top
            };
        };

        const start = (e) => {
            isDrawing = true;
            const pos = getPos(e);
            ctx.beginPath();
            ctx.moveTo(pos.x, pos.y);
            ctx.strokeStyle = document.getElementById('pen-color').value;
            
            ctx.lineCap = 'round';
            ctx.lineJoin = 'round';

            if (currentTool === 'eraser') {
                ctx.globalCompositeOperation = 'destination-out';
                ctx.lineWidth = 30; // Big eraser
            } else {
                ctx.globalCompositeOperation = 'source-over';
                ctx.lineWidth = document.getElementById('pen-size').value;
            }
        };

        const move = (e) => {
            if (!isDrawing) return;
            const pos = getPos(e);
            ctx.lineTo(pos.x, pos.y);
            ctx.stroke();
        };

        const stop = () => {
            isDrawing = false;
        };

        canvas.addEventListener('mousedown', start);
        canvas.addEventListener('mousemove', move);
        canvas.addEventListener('mouseup', stop);
        canvas.addEventListener('touchstart', start);
        canvas.addEventListener('touchmove', move);
        canvas.addEventListener('touchend', stop);

        // Eraser Cursor Feedback
        canvas.addEventListener('mouseenter', () => {
            if (currentTool === 'eraser') document.getElementById('eraser-cursor').style.display = 'block';
        });
        canvas.addEventListener('mouseleave', () => {
            document.getElementById('eraser-cursor').style.display = 'none';
        });
    }

    // Global cursor movement for eraser
    document.addEventListener('mousemove', (e) => {
        if (currentTool === 'eraser') {
            const cursor = document.getElementById('eraser-cursor');
            cursor.style.left = (e.clientX - 15) + 'px';
            cursor.style.top = (e.clientY - 15) + 'px';
        }
    });

    function updatePenColor(color) {
        document.getElementById('color-preview-circle').style.background = color;
        document.getElementById('pen-icon-el').style.color = color;
    }

    function setTool(tool) {
        currentTool = tool;
        document.getElementById('btn-pen').classList.toggle('active', tool === 'pen');
        document.getElementById('btn-eraser').classList.toggle('active', tool === 'eraser');
        document.getElementById('eraser-cursor').style.display = 'none';
        
        // If pen, reset icon color in case active state background hides it
        if (tool === 'pen') {
            updatePenColor(document.getElementById('pen-color').value);
        } else {
            document.getElementById('pen-icon-el').style.color = '#fff';
        }
    }

    async function saveMarkedPDF() {
        const marks = document.getElementById('marks-input').value;
        if (!marks) {
            alert('Please enter marks before saving.');
            return;
        }

        const btn = document.getElementById('save-btn');
        btn.disabled = true;
        btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Processing...';

        try {
            // Load original PDF using PDF-Lib
            const existingPdfBytes = await fetch(pdfUrl).then(res => res.arrayBuffer());
            const pdfDocLib = await PDFLib.PDFDocument.load(existingPdfBytes);

            for (let i = 0; i < pages.length; i++) {
                const pageData = pages[i];
                const libPage = pdfDocLib.getPages()[i];
                const { width, height } = libPage.getSize();

                // Convert annotation canvas to image
                const imgData = pageData.canvas.toDataURL('image/png');
                const img = await pdfDocLib.embedPng(imgData);

                libPage.drawImage(img, {
                    x: 0,
                    y: 0,
                    width: width,
                    height: height,
                });
            }

            const pdfBytes = await pdfDocLib.save();
            const blob = new Blob([pdfBytes], { type: 'application/pdf' });

            // Upload to server
            const formData = new FormData();
            formData.append('submission_id', submissionId);
            formData.append('marks', marks);
            formData.append('marked_pdf', blob, 'marked_paper.pdf');

            const response = await fetch('api/save_marked_paper.php', {
                method: 'POST',
                body: formData
            });

            const result = await response.json();
            if (result.success) {
                alert('Paper marked and sent to student!');
                window.location.href = 'papers.php';
            } else {
                throw new Exception(result.message || 'Error saving PDF');
            }

        } catch (err) {
            console.error(err);
            alert('Error: ' + err.message);
            btn.disabled = false;
            btn.innerHTML = '<i class="fas fa-save"></i> Save & Send';
        }
    }

    init();
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
