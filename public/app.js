document.addEventListener('DOMContentLoaded', () => {
    const dropZone = document.getElementById('drop-zone');
    const fileInput = document.getElementById('file-input');
    const appArea = document.getElementById('app-area');
    const pagesContainer = document.getElementById('pages-container');
    const alertBox = document.getElementById('alert-box');
    const spinner = document.getElementById('spinner');
    const btnProcess = document.getElementById('btn-process');
    const btnDelete = document.getElementById('btn-delete');
    const btnClearAll = document.getElementById('btn-clear-all');
    const btnReset = document.getElementById('btn-reset');
    const btnZoomIn = document.getElementById('btn-zoom-in');
    const btnZoomOut = document.getElementById('btn-zoom-out');
    const toolHand = document.getElementById('tool-hand');
    const toolPen = document.getElementById('tool-pen');

    let pdfDoc = null;
    let currentFile = null;
    let scale = 1.5;
    const MIN_SCALE = 0.5;
    const MAX_SCALE = 4.0;
    let redactions = [];
    let selectedRedactionId = null;
    let isDrawing = false;
    let drawStartX, drawStartY;
    let activePageNum = null;
    let currentTool = 'pen'; 

    updateDeleteButtonState();

    btnReset.addEventListener('click', resetApp);
    function resetApp() {
        pdfDoc = null;
        currentFile = null;
        redactions = [];
        selectedRedactionId = null;
        isDrawing = false;
        activePageNum = null;
        scale = 1.5;
        fileInput.value = '';
        pagesContainer.innerHTML = '';
        hideError();
        updateDeleteButtonState();
        appArea.classList.add('d-none');
        dropZone.classList.remove('d-none');
    }

    btnClearAll.addEventListener('click', () => {
        if (redactions.length === 0) return;
        if (confirm('Möchtest du wirklich alle Markierungen auf allen Seiten entfernen?')) {
            redactions = [];
            selectedRedactionId = null;
            updateDeleteButtonState();
            const wrappers = document.querySelectorAll('.pdf-page-wrapper');
            wrappers.forEach(wrapper => {
                redrawOverlay(parseInt(wrapper.dataset.pageNum));
            });
        }
    });

    btnZoomIn.addEventListener('click', () => changeZoom(0.25));
    btnZoomOut.addEventListener('click', () => changeZoom(-0.25));
    async function changeZoom(delta) {
        const newScale = scale + delta;
        if (newScale < MIN_SCALE || newScale > MAX_SCALE) return;
        scale = newScale;
        await renderAllPages();
    }

    toolHand.addEventListener('click', () => setTool('hand'));
    toolPen.addEventListener('click', () => setTool('pen'));
    function setTool(mode) {
        currentTool = mode;
        if (mode === 'hand') {
            toolHand.classList.add('btn-primary', 'active');
            toolHand.classList.remove('btn-outline-primary');
            toolPen.classList.add('btn-outline-primary');
            toolPen.classList.remove('btn-primary', 'active');
        } else {
            toolPen.classList.add('btn-primary', 'active');
            toolPen.classList.remove('btn-outline-primary');
            toolHand.classList.add('btn-outline-primary');
            toolHand.classList.remove('btn-primary', 'active');
        }
    }

    ['dragenter', 'dragover', 'dragleave', 'drop'].forEach(evt => {
        dropZone.addEventListener(evt, e => { e.preventDefault(); e.stopPropagation(); });
    });
    dropZone.addEventListener('dragover', () => dropZone.classList.add('dragover'));
    dropZone.addEventListener('dragleave', () => dropZone.classList.remove('dragover'));
    dropZone.addEventListener('drop', e => {
        dropZone.classList.remove('dragover');
        handleFiles(e.dataTransfer.files);
    });
    fileInput.addEventListener('change', e => handleFiles(e.target.files));

    async function handleFiles(files) {
        if (files.length === 0) return;
        const file = files[0];
        if (file.type !== 'application/pdf') { showError("Nur PDF-Dateien erlaubt."); return; }
        currentFile = file;
        hideError();
        dropZone.classList.add('d-none');
        appArea.classList.remove('d-none');
        pagesContainer.innerHTML = '<div class="p-5 text-muted">Lade Dokument...</div>';
        try {
            const arrayBuffer = await file.arrayBuffer();
            pdfDoc = await pdfjsLib.getDocument(arrayBuffer).promise;
            await renderAllPages();
        } catch (err) {
            showError("Fehler beim Laden: " + err.message);
            resetApp();
        }
    }

    async function renderAllPages() {
        pagesContainer.innerHTML = '';
        for (let num = 1; num <= pdfDoc.numPages; num++) {
            await renderSinglePage(num);
            redrawOverlay(num);
        }
    }

    async function renderSinglePage(pageNum) {
        const page = await pdfDoc.getPage(pageNum);
        const viewport = page.getViewport({ scale: scale });
        const wrapper = document.createElement('div');
        wrapper.className = 'pdf-page-wrapper';
        wrapper.style.width = `${viewport.width}px`;
        wrapper.style.height = `${viewport.height}px`;
        wrapper.dataset.pageNum = pageNum;
        const canvasPdf = document.createElement('canvas');
        canvasPdf.className = 'pdf-canvas';
        canvasPdf.width = viewport.width;
        canvasPdf.height = viewport.height;
        const ctxPdf = canvasPdf.getContext('2d');
        const canvasSel = document.createElement('canvas');
        canvasSel.className = 'selection-canvas';
        canvasSel.width = viewport.width;
        canvasSel.height = viewport.height;
        canvasSel.dataset.pageNum = pageNum;
        wrapper.appendChild(canvasPdf);
        wrapper.appendChild(canvasSel);
        pagesContainer.appendChild(wrapper);
        await page.render({ canvasContext: ctxPdf, viewport: viewport }).promise;
        attachCanvasEvents(canvasSel, pageNum);
    }

    function getPointerPos(e, canvas) {
        const rect = canvas.getBoundingClientRect();
        let clientX, clientY;
        if (e.touches && e.touches.length > 0) { clientX = e.touches[0].clientX; clientY = e.touches[0].clientY; }
        else if (e.changedTouches && e.changedTouches.length > 0) { clientX = e.changedTouches[0].clientX; clientY = e.changedTouches[0].clientY; }
        else { clientX = e.clientX; clientY = e.clientY; }
        return { x: clientX - rect.left, y: clientY - rect.top };
    }

    function attachCanvasEvents(canvas, pageNum) {
        const handleStart = (e) => {
            const isTouch = (e.type === 'touchstart');
            if (isTouch && currentTool === 'hand') return;
            if (isTouch && e.touches && e.touches.length > 1) { isDrawing = false; return; }
            if (isTouch && currentTool === 'pen') { if (e.touches.length === 1) e.preventDefault(); }
            const pos = getPointerPos(e, canvas);
            const clickedRedaction = findRedactionAt(pageNum, pos.x, pos.y);
            if (clickedRedaction) {
                selectedRedactionId = clickedRedaction.id;
                isDrawing = false;
                updateDeleteButtonState();
                redrawOverlay(pageNum);
                return;
            }
            if (selectedRedactionId !== null) { selectedRedactionId = null; updateDeleteButtonState(); redrawOverlay(pageNum); }
            isDrawing = true;
            activePageNum = pageNum;
            drawStartX = pos.x;
            drawStartY = pos.y;
        };
        const handleMove = (e) => {
            const isTouch = (e.type === 'touchmove');
            if (isTouch && currentTool === 'hand') return;
            if (!isDrawing || activePageNum !== pageNum) return;
            if (isTouch) e.preventDefault();
            const pos = getPointerPos(e, canvas);
            redrawOverlay(pageNum);
            const ctx = canvas.getContext('2d');
            ctx.fillStyle = 'rgba(208, 16, 28, 0.3)';
            ctx.strokeStyle = '#d0101c';
            ctx.lineWidth = 2;
            const w = pos.x - drawStartX;
            const h = pos.y - drawStartY;
            ctx.fillRect(drawStartX, drawStartY, w, h);
            ctx.strokeRect(drawStartX, drawStartY, w, h);
        };
        const handleEnd = (e) => {
            if (!isDrawing || activePageNum !== pageNum) return;
            const isTouch = (e.type === 'touchend');
            if (isTouch) e.preventDefault();
            isDrawing = false;
            const pos = getPointerPos(e, canvas);
            let w = pos.x - drawStartX;
            let h = pos.y - drawStartY;
            let x = drawStartX;
            let y = drawStartY;
            if (w < 0) { x = pos.x; w = -w; }
            if (h < 0) { y = pos.y; h = -h; }
            if (w < 5 || h < 5) { redrawOverlay(pageNum); return; }
            redactions.push({ id: Date.now() + Math.random(), page: pageNum, rect: [x / scale, y / scale, w / scale, h / scale] });
            updateDeleteButtonState();
            redrawOverlay(pageNum);
        };
        canvas.addEventListener('mousedown', handleStart);
        canvas.addEventListener('mousemove', handleMove);
        canvas.addEventListener('mouseup', handleEnd);
        canvas.addEventListener('touchstart', handleStart, { passive: false });
        canvas.addEventListener('touchmove', handleMove, { passive: false });
        canvas.addEventListener('touchend', handleEnd, { passive: false });
    }

    function findRedactionAt(pageNum, mx, my) {
        const tolerance = ('ontouchstart' in window) ? 10 : 0; 
        for (let i = redactions.length - 1; i >= 0; i--) {
            const r = redactions[i];
            if (r.page !== pageNum) continue;
            const rx = r.rect[0] * scale;
            const ry = r.rect[1] * scale;
            const rw = r.rect[2] * scale;
            const rh = r.rect[3] * scale;
            if (mx >= rx - tolerance && mx <= rx + rw + tolerance && my >= ry - tolerance && my <= ry + rh + tolerance) { return r; }
        }
        return null;
    }

    function redrawOverlay(pageNum) {
        const wrapper = document.querySelector(`.pdf-page-wrapper[data-page-num="${pageNum}"]`);
        if (!wrapper) return;
        const canvas = wrapper.querySelector('.selection-canvas');
        const ctx = canvas.getContext('2d');
        ctx.clearRect(0, 0, canvas.width, canvas.height);
        redactions.forEach(r => {
            if (r.page !== pageNum) return;
            const x = r.rect[0] * scale;
            const y = r.rect[1] * scale;
            const w = r.rect[2] * scale;
            const h = r.rect[3] * scale;
            if (r.id === selectedRedactionId) {
                ctx.fillStyle = 'rgba(0, 123, 255, 0.3)'; ctx.strokeStyle = '#007bff'; ctx.lineWidth = 2;
                ctx.fillRect(x, y, w, h); ctx.strokeRect(x, y, w, h);
            } else {
                ctx.fillStyle = 'rgba(0, 0, 0, 0.7)'; ctx.fillRect(x, y, w, h);
            }
        });
    }

    btnDelete.addEventListener('click', deleteSelected);
    window.addEventListener('keydown', e => { if (selectedRedactionId && (e.key === 'Delete' || e.key === 'Backspace')) deleteSelected(); });
    function deleteSelected() {
        if (!selectedRedactionId) return;
        const item = redactions.find(r => r.id === selectedRedactionId);
        if (item) {
            redactions = redactions.filter(r => r.id !== selectedRedactionId);
            selectedRedactionId = null;
            updateDeleteButtonState();
            redrawOverlay(item.page);
        }
    }

    function updateDeleteButtonState() {
        btnDelete.disabled = (selectedRedactionId === null);
        btnClearAll.disabled = (redactions.length === 0);
    }

    btnProcess.addEventListener('click', async () => {
        if (!currentFile || redactions.length === 0) { showError("Bitte markiere mindestens einen Bereich."); return; }
        setLoading(true); hideError();
        const formData = new FormData();
        formData.append('pdf', currentFile);
        const dataToSend = redactions.map(r => ({ page: r.page, rect: r.rect }));
        formData.append('redactions', JSON.stringify(dataToSend));
        const endpoint = (window.APP_BASE_PATH || '') + '/upload';
        try {
            const response = await fetch(endpoint, { method: 'POST', body: formData, headers: {'X-Requested-With': 'XMLHttpRequest'} });
            if (response.status === 401) { window.location.reload(); return; }
            if (!response.ok) { let msg = "Server Fehler"; try { msg = (await response.json()).error; } catch(e){} throw new Error(msg); }
            const blob = await response.blob();
            const url = window.URL.createObjectURL(blob);
            const a = document.createElement('a');
            a.href = url;
            a.download = `SAFE_${currentFile.name}`;
            document.body.appendChild(a);
            a.click();
            setTimeout(() => { window.URL.revokeObjectURL(url); a.remove(); }, 100);
        } catch (e) { showError(e.message); } finally { setLoading(false); }
    });

    function showError(msg) { alertBox.textContent = msg; alertBox.classList.remove('d-none'); }
    function hideError() { alertBox.classList.add('d-none'); }
    function setLoading(isLoading) { btnProcess.disabled = isLoading; isLoading ? spinner.classList.remove('d-none') : spinner.classList.add('d-none'); }
});
