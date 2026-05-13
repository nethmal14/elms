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
<div class="marking-workspace">
    <!-- PDF Viewer Area -->
    <div id="pdf-container" class="marking-canvas-container">
        <div id="loading-overlay" class="flex-center flex-col gap-6" style="color: #fff; font-size: 1.2rem;">
            <div class="spinner"></div>
            <div class="text-xs font-extrabold tracking-widest uppercase opacity-80">Loading Assessment...</div>
        </div>
        <div id="eraser-cursor" class="eraser-cursor"></div>
    </div>

    <!-- Floating Sidebar Tools -->
    <div class="floating-toolbar">
        <div class="tool-section border-none bg-none shadow-none p-0">
            <a href="papers.php" class="side-btn exit" title="Exit marking">
                <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"></line><line x1="6" y1="6" x2="18" y2="18"></line></svg>
            </a>
        </div>
        
        <div class="tool-section bg-glass main-tools rounded-3xl py-4 px-3">
            <button onclick="setTool('pen')" id="btn-pen" class="tool-btn active" title="Pen Tool">
                <svg id="pen-icon-el" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M12 19l7-7 3 3-7 7-3-3z"></path><path d="M18 13l-1.5-7.5L2 2l3.5 14.5L13 18l5-5z"></path><path d="M2 2l5 5"></path></svg>
            </button>
            <button onclick="setTool('eraser')" id="btn-eraser" class="tool-btn" title="Eraser Tool">
                <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M20 20H7L3 16C2 15 2 13 3 12L13 2C14 1 16 1 17 2L21 6C22 7 22 9 21 10L11 20"></path></svg>
            </button>
            
            <div class="divider-marking"></div>
            
            <div class="color-wrapper w-9 h-9">
                <input type="color" id="pen-color" value="#ff3b30" onchange="updatePenColor(this.value)">
                <div class="color-preview color-preview-circle w-9 h-9" id="color-preview-circle"></div>
            </div>
 
            <select id="pen-size" class="side-select">
                <option value="2">2</option>
                <option value="4" selected>4</option>
                <option value="8">8</option>
                <option value="12">12</option>
            </select>
        </div>

        <div class="tool-section bg-glass action-section rounded-3xl py-5 px-3">
            <div class="marks-wrapper">
                <label class="marks-label">Score</label>
                <input type="number" id="marks-input" min="0" max="100" placeholder="0" class="side-marks">
            </div>
            <button onclick="saveMarkedPDF()" id="save-btn" class="send-btn" title="Finalize and Send">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><line x1="22" y1="2" x2="11" y2="13"></line><polygon points="22 2 15 22 11 13 2 9 22 2"></polygon></svg>
                <span class="mt-1 text-2xs">Send</span>
            </button>
        </div>
    </div>
</div>

<style>


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
