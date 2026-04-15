<div class="ci-page">
    <div class="page-header">
        <div>
            <h1 class="page-title">Add Circular (Manual)</h1>
            <p class="page-subtitle">Enter circular metadata and optional full text — AI will analyze and suggest compliance mapping</p>
        </div>
        <a href="<?= htmlspecialchars($basePath ?? '') ?>/circular-intelligence/upload" class="btn btn-primary">Upload Document Instead</a>
    </div>
    <div class="card">
        <form method="post" action="<?= htmlspecialchars($basePath ?? '') ?>/circular-intelligence/add">
            <div class="form-group">
                <label class="form-label">Title <span class="text-danger">*</span></label>
                <input type="text" name="title" class="form-control" required>
            </div>
            <div class="form-row-2">
                <div class="form-group">
                    <label class="form-label">Authority</label>
                    <select name="authority" class="form-control">
                        <?php foreach (['RBI', 'NHB', 'SEBI', 'IRDAI', 'Internal'] as $a): ?>
                        <option value="<?= $a ?>"><?= $a ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label">Reference No</label>
                    <input type="text" name="reference_no" class="form-control">
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
                <label class="form-label">Document text (helps AI)</label>
                <textarea name="document_text" class="form-control" rows="8" placeholder="Paste circular content or key obligations…"></textarea>
            </div>
            <button type="submit" class="btn btn-primary"><i class="fas fa-magic"></i> Save &amp; Run AI</button>
        </form>
    </div>
</div>
