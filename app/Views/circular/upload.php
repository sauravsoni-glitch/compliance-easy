<div class="ci-page">
    <div class="page-header">
        <div>
            <h1 class="page-title">Upload Circular</h1>
            <p class="page-subtitle">Upload PDF / DOC — AI will extract text, analyze, and suggest compliance fields</p>
        </div>
        <a href="<?= htmlspecialchars($basePath ?? '') ?>/circular-intelligence/add" class="btn btn-secondary">Add Circular (manual)</a>
    </div>
    <div class="card ci-upload-card">
        <div class="ci-ai-flow-note"><i class="fas fa-robot"></i> <strong>AI flow:</strong> Upload → Text extraction (simulated for PDF/DOC) → Analysis → Auto department / owner / risk → Review on detail page.</div>
        <form method="post" action="<?= htmlspecialchars($basePath ?? '') ?>/circular-intelligence/upload" enctype="multipart/form-data">
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
            <button type="submit" class="btn btn-primary btn-lg"><i class="fas fa-brain"></i> Upload &amp; Run AI</button>
        </form>
    </div>
</div>
<script>
(function(){
    var dz = document.getElementById('ci-up-dz'), fi = document.getElementById('ci-up-file'), nm = document.getElementById('ci-up-name');
    if (!dz || !fi) return;
    dz.addEventListener('click', function(e){ if (e.target !== fi) fi.click(); });
    fi.addEventListener('change', function(){ nm.textContent = this.files[0] ? this.files[0].name : ''; });
    dz.addEventListener('dragover', function(e){ e.preventDefault(); dz.classList.add('dragover'); });
    dz.addEventListener('dragleave', function(){ dz.classList.remove('dragover'); });
    dz.addEventListener('drop', function(e){
        e.preventDefault(); dz.classList.remove('dragover');
        if (e.dataTransfer.files.length) { fi.files = e.dataTransfer.files; nm.textContent = fi.files[0].name; }
    });
})();
</script>
