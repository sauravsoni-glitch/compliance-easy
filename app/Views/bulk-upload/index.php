<?php
$basePath = $basePath ?? '';
$tab = $activeTab ?? 'upload';
if (!in_array($tab, ['upload', 'status', 'history'], true)) {
    $tab = 'upload';
}
$flashSuccess = $_SESSION['flash_success'] ?? null;
$flashError = $_SESSION['flash_error'] ?? null;
unset($_SESSION['flash_success'], $_SESSION['flash_error']);
$history = $uploadHistory ?? [];
$tabUrl = function (string $t) use ($basePath) {
    return htmlspecialchars($basePath . '/bulk-upload?tab=' . $t);
};
$fileIcon = function (string $name) {
    $e = strtolower(pathinfo($name, PATHINFO_EXTENSION));
    if ($e === 'xlsx' || $e === 'xls') {
        return 'fa-file-excel text-success';
    }
    if ($e === 'zip') {
        return 'fa-file-archive text-warning';
    }

    return 'fa-file-csv text-primary';
};
?>
<div class="bu-page">
    <div class="bu-head">
        <h1 class="page-title">Bulk Upload</h1>
        <p class="page-subtitle">Upload compliance documents in bulk <span class="text-muted">(Admin only)</span></p>
    </div>
    <?php if ($flashSuccess): ?><div class="alert alert-success"><?= htmlspecialchars($flashSuccess) ?></div><?php endif; ?>
    <?php if ($flashError): ?><div class="alert alert-danger"><?= htmlspecialchars($flashError) ?></div><?php endif; ?>

    <div class="bu-tabs">
        <a href="<?= $tabUrl('upload') ?>" class="bu-tab <?= $tab === 'upload' ? 'active' : '' ?>">Bulk Upload</a>
        <a href="<?= $tabUrl('status') ?>" class="bu-tab <?= $tab === 'status' ? 'active' : '' ?>">Bulk Status Update</a>
        <a href="<?= $tabUrl('history') ?>" class="bu-tab <?= $tab === 'history' ? 'active' : '' ?>">Upload History</a>
    </div>

    <?php if ($tab === 'upload'): ?>
    <div class="card bu-card">
        <form method="post" enctype="multipart/form-data" id="bu-main-form" class="bu-form" action="<?= htmlspecialchars($basePath) ?>/bulk-upload/process">
            <div class="form-group">
                <label class="form-label font-weight-600">Select upload type</label>
                <select name="upload_type" id="bu-upload-type" class="form-control bu-type-select">
                    <option value="compliance">Compliance Upload</option>
                    <option value="financial_ratios">Financial Ratios Upload</option>
                    <option value="doa">DOA Upload</option>
                    <option value="authority_matrix">Authority Matrix Upload</option>
                </select>
            </div>
            <div class="bu-dropzone" id="bu-dropzone">
                <div class="bu-drop-ico"><i class="fas fa-cloud-upload-alt"></i></div>
                <p class="bu-drop-title"><strong>Drag & drop files here, or click to browse</strong></p>
                <p class="bu-drop-hint">Any standard CSV or Excel .xlsx / .xlsm (Max 50MB, up to 100 data rows)</p>
                <input type="file" name="file" id="bu-file" class="bu-file-input" required>
                <button type="button" class="btn btn-secondary btn-sm" onclick="document.getElementById('bu-file').click()">Choose Files</button>
                <span class="bu-file-name text-muted text-sm" id="bu-file-name"></span>
            </div>
            <div class="bu-guidelines">
                <h4 class="bu-gl-title">Upload Guidelines</h4>
                <ul class="bu-gl-list">
                    <li><i class="fas fa-exclamation-circle"></i> Use the provided template for each upload type</li>
                    <li><i class="fas fa-exclamation-circle"></i> Maximum 100 records per upload</li>
                    <li><i class="fas fa-exclamation-circle"></i> File size limit: 50MB</li>
                    <li><i class="fas fa-exclamation-circle"></i> <strong>.csv</strong>, <strong>.txt</strong> (comma-separated), <strong>.xlsx</strong>, <strong>.xlsm</strong>; Excel without the right extension is detected when possible</li>
                    <li><i class="fas fa-exclamation-circle"></i> Column names are flexible; rows that cannot be imported are skipped (see Upload History)</li>
                    <li><i class="fas fa-exclamation-circle"></i> Uploaded files are archived under <code>public/uploads/upload_history/</code> (e.g. <code>bulk_compliance/</code>)</li>
                </ul>
            </div>
            <div class="bu-actions">
                <a href="<?= htmlspecialchars($basePath) ?>/bulk-upload/template/compliance" class="btn btn-secondary bu-tpl-btn" id="bu-tpl-btn"><i class="fas fa-download"></i> Download Template</a>
                <button type="submit" class="btn btn-primary" id="bu-submit-btn"><i class="fas fa-upload"></i> Submit</button>
            </div>
        </form>
    </div>
    <script>
(function(){
  var base = <?= json_encode($basePath) ?>;
  var type = document.getElementById('bu-upload-type');
  var form = document.getElementById('bu-main-form');
  var tpl = document.getElementById('bu-tpl-btn');
  var templates = {
    compliance: 'compliance',
    financial_ratios: 'ratios',
    doa: 'doa',
    authority_matrix: 'matrix'
  };
  function sync(){
    var v = type.value;
    form.action = base + '/bulk-upload/process';
    tpl.href = base + '/bulk-upload/template/' + (templates[v] || 'compliance');
  }
  type.addEventListener('change', sync);
  sync();
  var fi = document.getElementById('bu-file');
  var fn = document.getElementById('bu-file-name');
  fi.addEventListener('change', function(){ fn.textContent = fi.files[0] ? fi.files[0].name : ''; });
  document.getElementById('bu-dropzone').addEventListener('click', function(e){ if(e.target.tagName !== 'BUTTON' && e.target.tagName !== 'INPUT') fi.click(); });
  form.addEventListener('submit', function(e){
    var f = fi.files[0];
    if (f && f.size > 50*1024*1024) { e.preventDefault(); (window.appAlert||alert)('File exceeds 50MB'); return false; }
    var sb = document.getElementById('bu-submit-btn');
    if (sb) { sb.disabled = true; sb.textContent = 'Uploading...'; }
  });
})();
    </script>
    <?php elseif ($tab === 'status'): ?>
    <div class="card bu-card bu-status-card">
        <h3 class="card-title">Bulk Status Update</h3>
        <form method="post" action="<?= htmlspecialchars($basePath) ?>/bulk-upload/status" enctype="multipart/form-data">
            <div class="form-group">
                <label class="form-label">Upload status file (CSV or Excel)</label>
                <input type="file" name="status_file" class="form-control" required>
            </div>
            <div class="form-group">
                <label class="form-label">Target Status</label>
                <select name="target_status" class="form-control" required>
                    <option value="">Select status</option>
                    <?php foreach (['draft', 'pending', 'submitted', 'under_review', 'rework', 'approved', 'rejected', 'completed', 'overdue'] as $st): ?>
                    <option value="<?= $st ?>"><?= htmlspecialchars(ucfirst(str_replace('_', ' ', $st))) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <p class="text-sm text-muted"><a href="<?= htmlspecialchars($basePath) ?>/bulk-upload/template/status">Download sample CSV</a> (one compliance code per line)</p>
            <button type="submit" class="btn btn-primary btn-block bu-status-submit">Update Statuses</button>
        </form>
    </div>
    <?php else: ?>
    <div class="card bu-card">
        <h3 class="card-title">Upload History</h3>
        <div class="table-wrap">
            <table class="data-table bu-history-table">
                <thead>
                    <tr>
                        <th>File name</th>
                        <th>Uploaded by</th>
                        <th>Date</th>
                        <th>Records</th>
                        <th>Status</th>
                        <th>Errors</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($history)): ?>
                    <tr><td colspan="6" class="text-muted">No uploads yet. Run an import to see history here.</td></tr>
                    <?php else: foreach ($history as $h): ?>
                    <tr>
                        <td><i class="fas <?= $fileIcon($h['file_name'] ?? '') ?> bu-file-ico"></i> <?= htmlspecialchars($h['file_name'] ?? '') ?></td>
                        <td><?= htmlspecialchars($h['uploader_name'] ?? '—') ?></td>
                        <td><?= htmlspecialchars(date('Y-m-d H:i', strtotime($h['created_at'] ?? 'now'))) ?></td>
                        <td><?= (int)($h['records_total'] ?? 0) ?></td>
                        <td><?php
                            $st = strtolower((string) ($h['status'] ?? 'completed'));
                            if ($st === 'failed') {
                                echo '<span class="bu-pill-fail"><i class="fas fa-times-circle"></i> Failed</span>';
                            } elseif ($st === 'partial') {
                                echo '<span class="bu-pill-partial"><i class="fas fa-exclamation-circle"></i> Partial</span>';
                            } else {
                                echo '<span class="bu-pill-ok"><i class="fas fa-check"></i> ' . htmlspecialchars(ucfirst($st)) . '</span>';
                            }
                        ?></td>
                        <td class="<?= ((int)($h['records_fail'] ?? 0) > 0) ? 'bu-err-red' : 'text-muted' ?>"><?= (int)($h['records_fail'] ?? 0) ?></td>
                    </tr>
                    <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>
    </div>
    <?php endif; ?>
</div>
