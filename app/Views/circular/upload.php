<div class="ci-page">
    <div class="page-header">
        <div>
            <h1 class="page-title">Upload Circular</h1>
            <p class="page-subtitle">Upload PDF / DOC — AI will extract text, analyze, and suggest compliance fields</p>
        </div>
        <a href="<?= htmlspecialchars($basePath ?? '') ?>/circular-intelligence/add" class="btn btn-secondary">Add Circular (manual)</a>
    </div>
    <div class="card ci-upload-card">
        <form method="post" action="<?= htmlspecialchars($basePath ?? '') ?>/circular-intelligence/upload" enctype="multipart/form-data">
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
