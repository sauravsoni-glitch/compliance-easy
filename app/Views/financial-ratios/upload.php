<div class="page-header">
    <div>
        <a href="<?= $basePath ?>/financial-ratios" class="text-primary">← Back to Financial Ratios</a>
        <h1 class="page-title" style="margin-top: 0.5rem;">Upload Data</h1>
        <p class="page-subtitle">Upload ratio data via template (CSV/Excel)</p>
    </div>
</div>
<div class="card">
    <p class="text-muted">Download the template from the Financial Ratios page. CSV columns: <code>category_slug</code>, <code>ratio_name</code>, <code>regulatory_limit</code>, <code>current_value</code>, optional <code>status</code> (compliant/watch/non_compliant), <code>as_of_date</code> (Y-m-d). Each upload appends to <strong>History</strong> for that ratio.</p>
    <form method="post" action="<?= $basePath ?>/financial-ratios/upload" enctype="multipart/form-data">
        <div class="form-group">
            <label class="form-label">Select file</label>
            <input type="file" name="file" accept=".csv,.xlsx" class="form-control">
        </div>
        <button type="submit" class="btn btn-primary">Upload Data</button>
    </form>
</div>
