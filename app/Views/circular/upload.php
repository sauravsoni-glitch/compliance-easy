<?php
$bp = $basePath ?? '';
$processUrl = ($bp !== '' ? $bp : '') . '/circular-intelligence/upload-process';
?>
<div class="ci-page">
    <div class="page-header">
        <div>
            <h1 class="page-title">Upload Circular</h1>
            <p class="page-subtitle">Upload PDF / DOC — AI will extract text, analyze, and suggest compliance fields</p>
        </div>
        <a href="<?= htmlspecialchars($bp) ?>/circular-intelligence/add" class="btn btn-secondary">Add Circular (manual)</a>
    </div>
    <div class="card ci-upload-card">
        <div class="ci-ai-flow-note"><i class="fas fa-robot"></i> <strong>n8n flow:</strong> Your file is sent to n8n (binary PDF/DOC is not pre-filled with fake text). The detail page shows only fields returned as JSON from the webhook. If n8n fails, nothing is saved. A processing screen runs while the server waits for the response.</div>
        <form id="ci-upload-form" method="post" action="<?= htmlspecialchars($bp) ?>/circular-intelligence/upload" enctype="multipart/form-data" data-upload-url="<?= htmlspecialchars($processUrl) ?>" novalidate>
            <div class="form-group">
                <label class="form-label">Title <span class="text-danger">*</span></label>
                <input type="text" name="title" class="form-control" required placeholder="e.g. RBI Circular on Monthly GST Reporting">
            </div>
            <div class="form-row-2">
                <div class="form-group">
                    <label class="form-label">Authority</label>
                    <select name="authority" class="form-control">
                        <option value="RBI">RBI</option>
                        <option value="NHB">NHB</option>
                        <option value="SEBI">SEBI</option>
                        <option value="Internal">Internal</option>
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label">Reference No</label>
                    <input type="text" name="reference_no" class="form-control" placeholder="RBI/2026-27/AML/04">
                </div>
            </div>
            <div class="form-row-2">
                <div class="form-group">
                    <label class="form-label">Circular Date</label>
                    <input type="date" name="circular_date" class="form-control" value="<?= date('Y-m-d') ?>">
                </div>
                <div class="form-group">
                    <label class="form-label">Effective Date</label>
                    <input type="date" name="effective_date" class="form-control">
                </div>
            </div>
            <div class="form-group">
                <label class="form-label">Document (PDF, DOC, DOCX, TXT) — optional if you paste text below</label>
                <div class="ci-dropzone" id="ci-up-dz">
                    <input type="file" name="document" id="ci-up-file" class="ci-file-input" accept=".pdf,.doc,.docx,.txt">
                    <i class="fas fa-cloud-upload-alt ci-drop-ico"></i>
                    <p class="mb-1"><strong>Click to upload</strong> or drag and drop</p>
                    <p class="text-muted text-sm mb-0">Max 15MB · AI extracts text automatically</p>
                    <span id="ci-up-name" class="ci-file-name"></span>
                </div>
            </div>
            <div class="form-group">
                <label class="form-label">Or paste circular text (optional)</label>
                <textarea name="paste_text" class="form-control" rows="4" placeholder="Paste key paragraphs for better AI analysis…"></textarea>
            </div>
            <button type="submit" class="btn btn-primary btn-lg" id="ci-upload-submit"><i class="fas fa-brain"></i> Upload &amp; Run AI</button>
        </form>
    </div>
</div>

<div id="ci-processing-overlay" class="ci-processing-overlay" hidden aria-hidden="true" role="alertdialog" aria-labelledby="ci-processing-title" aria-busy="true">
    <div class="ci-processing-card">
        <div class="ci-processing-spinner" aria-hidden="true"></div>
        <h2 id="ci-processing-title" class="ci-processing-title">Processing circular</h2>
        <p class="ci-processing-sub">Please wait…</p>
    </div>
</div>

<script>
(function(){
    var dz = document.getElementById('ci-up-dz'), fi = document.getElementById('ci-up-file'), nm = document.getElementById('ci-up-name');
    if (dz && fi) {
        dz.addEventListener('click', function(e){ if (e.target !== fi) fi.click(); });
        fi.addEventListener('change', function(){ nm.textContent = this.files[0] ? this.files[0].name : ''; });
        dz.addEventListener('dragover', function(e){ e.preventDefault(); dz.classList.add('dragover'); });
        dz.addEventListener('dragleave', function(){ dz.classList.remove('dragover'); });
        dz.addEventListener('drop', function(e){
            e.preventDefault(); dz.classList.remove('dragover');
            if (e.dataTransfer.files.length) { fi.files = e.dataTransfer.files; nm.textContent = fi.files[0].name; }
        });
    }
    var form = document.getElementById('ci-upload-form');
    var overlay = document.getElementById('ci-processing-overlay');
    var submitBtn = document.getElementById('ci-upload-submit');
    if (!form || !overlay || !window.fetch || !window.FormData) return;
    var url = form.getAttribute('data-upload-url');
    if (!url) return;
    form.addEventListener('submit', function(ev) {
        if (!form.reportValidity()) return;
        ev.preventDefault();
        overlay.hidden = false;
        overlay.setAttribute('aria-hidden', 'false');
        if (submitBtn) { submitBtn.disabled = true; }
        var fd = new FormData(form);
        fetch(url, {
            method: 'POST',
            body: fd,
            credentials: 'same-origin',
            headers: { 'X-Requested-With': 'XMLHttpRequest' }
        })
        .then(function(res) {
            return res.text().then(function(text) {
                var j = null;
                try { j = text ? JSON.parse(text) : null; } catch (e) { j = null; }
                return { res: res, j: j, text: text };
            });
        })
        .then(function(x) {
            if (x.j && x.j.ok && x.j.redirect) {
                window.location.href = x.j.redirect;
                return;
            }
            overlay.hidden = true;
            overlay.setAttribute('aria-hidden', 'true');
            if (submitBtn) { submitBtn.disabled = false; }
            var err = (x.j && x.j.error) ? x.j.error : (x.res.ok ? 'Unexpected response from server.' : 'Request failed (HTTP ' + x.res.status + ').');
            if (window.appAlert) window.appAlert(err); else alert(err);
        })
        .catch(function() {
            overlay.hidden = true;
            overlay.setAttribute('aria-hidden', 'true');
            if (submitBtn) { submitBtn.disabled = false; }
            if (window.appAlert) window.appAlert('Network error. Check your connection and try again.'); else alert('Network error. Check your connection and try again.');
        });
    });
})();
</script>
