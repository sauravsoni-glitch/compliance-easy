<?php
$flashSuccess = $_SESSION['flash_success'] ?? null;
$flashError = $_SESSION['flash_error'] ?? null;
unset($_SESSION['flash_success'], $_SESSION['flash_error']);
$activeTab = $activeTab ?? 'overview';
$bySlug = $bySlug ?? [];
$historyByRatio = $historyByRatio ?? [];
$remindersByCategory = $remindersByCategory ?? [];
$reminderFeatureEnabled = $reminderFeatureEnabled ?? false;
$basePath = $basePath ?? '';

function fr_cat_status(array $items): string {
    foreach ($items as $i) {
        if (($i['status'] ?? '') === 'non_compliant') {
            return 'non_compliant';
        }
    }
    foreach ($items as $i) {
        if (($i['status'] ?? '') === 'watch') {
            return 'watch';
        }
    }
    return 'compliant';
}
function fr_status_badge(string $s): array {
    if ($s === 'non_compliant') {
        return ['Non-Compliant', 'badge-danger'];
    }
    if ($s === 'watch') {
        return ['Watch', 'badge-warning'];
    }
    return ['Compliant', 'badge-success'];
}
function fr_row_badge(string $s): array {
    return fr_status_badge($s);
}
?>
<div class="page-header fr-page-header">
    <div>
        <h1 class="page-title">Financial Ratios</h1>
        <p class="page-subtitle">Monitor regulatory compliance ratios as per RBI/NHB guidelines</p>
    </div>
    <div class="fr-header-actions">
        <a href="<?= $basePath ?>/financial-ratios/download-template" class="btn btn-secondary"><i class="fas fa-download"></i> Download Template</a>
        <a href="<?= $basePath ?>/financial-ratios/upload" class="btn btn-primary"><i class="fas fa-upload"></i> Upload Data</a>
    </div>
</div>
<?php if ($flashSuccess): ?><div class="alert alert-success"><?= htmlspecialchars($flashSuccess) ?></div><?php endif; ?>
<?php if ($flashError): ?><div class="alert alert-danger"><?= htmlspecialchars($flashError) ?></div><?php endif; ?>

<div class="fr-kpi-row">
    <div class="fr-kpi fr-kpi-total">
        <div class="fr-kpi-icon"><i class="fas fa-chart-line"></i></div>
        <div><span class="fr-kpi-val"><?= (int)($total ?? 0) ?></span><span class="fr-kpi-lbl">Total Ratios</span></div>
    </div>
    <div class="fr-kpi fr-kpi-ok">
        <div class="fr-kpi-icon"><i class="fas fa-check-circle"></i></div>
        <div><span class="fr-kpi-val"><?= (int)($compliant ?? 0) ?></span><span class="fr-kpi-lbl">Compliant</span></div>
    </div>
    <div class="fr-kpi fr-kpi-watch">
        <div class="fr-kpi-icon"><i class="fas fa-exclamation-triangle"></i></div>
        <div><span class="fr-kpi-val"><?= (int)($watch ?? 0) ?></span><span class="fr-kpi-lbl">Watch</span></div>
    </div>
    <div class="fr-kpi fr-kpi-bad">
        <div class="fr-kpi-icon"><i class="fas fa-times-circle"></i></div>
        <div><span class="fr-kpi-val"><?= (int)($nonCompliant ?? 0) ?></span><span class="fr-kpi-lbl">Non-Compliant</span></div>
    </div>
</div>

<div class="fr-tabs">
    <a href="?tab=overview" class="fr-tab <?= $activeTab === 'overview' ? 'active' : '' ?>">Overview</a>
    <?php foreach ($categories ?? [] as $cat): ?>
    <a href="?tab=<?= htmlspecialchars($cat['slug']) ?>" class="fr-tab <?= $activeTab === $cat['slug'] ? 'active' : '' ?>"><?= htmlspecialchars($cat['name']) ?></a>
    <?php endforeach; ?>
</div>

<?php if ($activeTab === 'overview'): ?>
<div class="card">
    <h3 class="card-title">Financial Ratios Overview</h3>
    <?php if (!empty($bySlug)): ?>
    <div class="fr-overview-list">
        <?php foreach ($bySlug as $slug => $block):
            $items = $block['ratios'];
            if (empty($items)) {
                continue;
            }
            $cs = fr_cat_status($items);
            [$bl, $bc] = fr_status_badge($cs);
        ?>
        <div class="fr-overview-row">
            <div>
                <strong class="fr-overview-title"><?= htmlspecialchars($block['name']) ?></strong>
                <p class="text-muted text-sm mb-0"><?= count($items) ?> ratios monitored</p>
            </div>
            <span class="badge <?= $bc ?>"><?= htmlspecialchars($bl) ?></span>
            <a href="?tab=<?= htmlspecialchars($slug) ?>" class="fr-view-details">View Details →</a>
        </div>
        <?php endforeach; ?>
    </div>
    <?php else: ?>
    <p class="text-muted">No ratios yet. Use <strong>Upload Data</strong> with the template or wait for initial seed.</p>
    <?php endif; ?>
</div>

<?php else: ?>
<?php
$block = $bySlug[$activeTab] ?? null;
?>
<?php if (!$block): ?>
<div class="alert alert-danger">Category not found. <a href="?tab=overview">Back to overview</a></div>
<?php else:
    $cid = (int) ($block['category_id'] ?? 0);
    $rem = $remindersByCategory[$cid] ?? null;
?>
<div class="card fr-category-card">
    <div class="fr-category-head">
        <div>
            <h3 class="card-title mb-0"><?= htmlspecialchars($block['name']) ?></h3>
            <?php if ($rem && $reminderFeatureEnabled): ?>
            <p class="text-muted text-sm mb-0 mt-1"><i class="far fa-bell"></i> Reminder: <strong><?= htmlspecialchars($rem['reminder_date']) ?></strong><?php if (!empty($rem['note'])): ?> — <?= htmlspecialchars($rem['note']) ?><?php endif; ?><?php if (!empty($rem['repeat_monthly'])): ?> <span class="badge badge-secondary">Monthly</span><?php endif; ?></p>
            <?php endif; ?>
        </div>
        <div class="fr-category-actions">
            <?php if ($reminderFeatureEnabled): ?>
            <button type="button" class="btn btn-secondary btn-sm" id="fr-open-reminder"><i class="far fa-clock"></i> Set Reminder</button>
            <?php else: ?>
            <button type="button" class="btn btn-secondary btn-sm" disabled title="Run database migration 006_financial_ratio_reminders.sql"><i class="far fa-clock"></i> Set Reminder</button>
            <?php endif; ?>
            <button type="button" class="btn btn-secondary btn-sm" id="fr-open-edit" <?= empty($block['ratios']) ? 'disabled title="No ratios to edit"' : '' ?>><i class="fas fa-pencil-alt"></i> Edit</button>
        </div>
    </div>
    <?php if (!empty($block['ratios'])): ?>
    <div class="fr-ratio-list">
        <?php foreach ($block['ratios'] as $r):
            $hid = (int)$r['id'];
            $hist = $historyByRatio[$hid] ?? [];
            $histPayload = array_map(function ($h) {
                return [
                    'value' => $h['value'],
                    'status' => $h['status'],
                    'uploaded_at' => $h['uploaded_at'],
                    'uploader_name' => $h['uploader_name'] ?? '—',
                ];
            }, $hist);
            $histB64 = base64_encode(json_encode($histPayload));
            [$rl, $rb] = fr_row_badge($r['status'] ?? 'compliant');
        ?>
        <div class="fr-ratio-row">
            <div class="fr-ratio-main">
                <h4 class="fr-ratio-name"><?= htmlspecialchars($r['name']) ?></h4>
                <p class="fr-ratio-limit">Regulatory limit: <span class="text-danger"><?= htmlspecialchars($r['regulatory_limit']) ?></span></p>
                <div class="fr-ratio-current">
                    <span class="fr-current-label">Current:</span>
                    <strong class="fr-current-val"><?= htmlspecialchars($r['current_value']) ?></strong>
                    <span class="badge <?= $rb ?>"><?= htmlspecialchars($rl) ?></span>
                </div>
                <p class="text-muted text-sm mb-0">Updated: <?= $r['updated_at'] ? date('Y-m-d', strtotime($r['updated_at'])) : '—' ?></p>
            </div>
            <button type="button" class="btn btn-outline btn-sm fr-history-btn" data-title="<?= htmlspecialchars($r['name']) ?>"
                data-limit="<?= htmlspecialchars($r['regulatory_limit']) ?>"
                data-history-b64="<?= htmlspecialchars($histB64) ?>">
                <i class="fas fa-history"></i> History
            </button>
        </div>
        <?php endforeach; ?>
    </div>
    <?php else: ?>
    <p class="text-muted">No ratios in this category.</p>
    <?php endif; ?>
</div>

<?php if (!empty($block['ratios'])): ?>
<div id="fr-edit-modal" class="modal-overlay compliance-modal" style="display:none;" aria-hidden="true">
    <div class="modal fr-edit-modal-inner" role="dialog" aria-labelledby="fr-edit-title">
        <div class="modal-header">
            <h2 class="modal-title" id="fr-edit-title">Edit ratios — <?= htmlspecialchars($block['name']) ?></h2>
            <button type="button" class="modal-close fr-modal-close" data-close="fr-edit-modal" aria-label="Close">&times;</button>
        </div>
        <form method="post" action="<?= htmlspecialchars($basePath) ?>/financial-ratios/update-category">
            <input type="hidden" name="category_id" value="<?= $cid ?>">
            <input type="hidden" name="return_tab" value="<?= htmlspecialchars($activeTab) ?>">
            <div class="modal-body">
                <div class="table-wrap">
                    <table class="data-table fr-edit-table">
                        <thead>
                            <tr><th>Ratio</th><th>Regulatory limit</th><th>Current value</th><th>Status</th><th>As of</th></tr>
                        </thead>
                        <tbody>
                            <?php foreach ($block['ratios'] as $r):
                                $rid = (int) $r['id'];
                                $st = $r['status'] ?? 'compliant';
                            ?>
                            <tr>
                                <td><strong><?= htmlspecialchars($r['name']) ?></strong></td>
                                <td><input type="text" class="form-control form-control-sm" name="ratios[<?= $rid ?>][regulatory_limit]" value="<?= htmlspecialchars($r['regulatory_limit']) ?>"></td>
                                <td><input type="text" class="form-control form-control-sm" name="ratios[<?= $rid ?>][current_value]" value="<?= htmlspecialchars($r['current_value']) ?>"></td>
                                <td>
                                    <select class="form-control form-control-sm" name="ratios[<?= $rid ?>][status]">
                                        <option value="compliant" <?= $st === 'compliant' ? 'selected' : '' ?>>Compliant</option>
                                        <option value="watch" <?= $st === 'watch' ? 'selected' : '' ?>>Watch</option>
                                        <option value="non_compliant" <?= $st === 'non_compliant' ? 'selected' : '' ?>>Non-Compliant</option>
                                    </select>
                                </td>
                                <td><input type="date" class="form-control form-control-sm" name="ratios[<?= $rid ?>][updated_at]" value="<?= $r['updated_at'] ? htmlspecialchars(substr($r['updated_at'], 0, 10)) : date('Y-m-d') ?>"></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            <div class="modal-footer" style="padding:1rem 1.25rem;border-top:1px solid var(--border);display:flex;gap:0.5rem;justify-content:flex-end;">
                <button type="button" class="btn btn-secondary fr-modal-close" data-close="fr-edit-modal">Cancel</button>
                <button type="submit" class="btn btn-primary">Save changes</button>
            </div>
        </form>
    </div>
</div>
<?php endif; ?>

<?php if ($reminderFeatureEnabled): ?>
<div id="fr-reminder-modal" class="modal-overlay compliance-modal" style="display:none;" aria-hidden="true">
    <div class="modal" role="dialog" aria-labelledby="fr-reminder-title">
        <div class="modal-header">
            <h2 class="modal-title" id="fr-reminder-title">Reminder — <?= htmlspecialchars($block['name']) ?></h2>
            <button type="button" class="modal-close fr-modal-close" data-close="fr-reminder-modal" aria-label="Close">&times;</button>
        </div>
        <form id="fr-reminder-form" method="post" action="<?= htmlspecialchars($basePath) ?>/financial-ratios/save-reminder">
            <input type="hidden" name="category_id" value="<?= $cid ?>">
            <input type="hidden" name="return_tab" value="<?= htmlspecialchars($activeTab) ?>">
            <div class="modal-body">
                <div class="form-group">
                    <label for="fr-reminder-date">Next review date</label>
                    <input type="date" class="form-control" id="fr-reminder-date" name="reminder_date" required
                        value="<?= $rem ? htmlspecialchars($rem['reminder_date']) : date('Y-m-d', strtotime('+7 days')) ?>">
                </div>
                <div class="form-group">
                    <label for="fr-reminder-note">Note (optional)</label>
                    <textarea class="form-control" id="fr-reminder-note" name="note" rows="3" placeholder="e.g. Prepare board pack"><?= $rem ? htmlspecialchars($rem['note'] ?? '') : '' ?></textarea>
                </div>
                <div class="form-group mb-0">
                    <label class="checkbox-label"><input type="checkbox" name="repeat_monthly" value="1" <?= ($rem && !empty($rem['repeat_monthly'])) ? 'checked' : '' ?>> Repeat monthly (date auto-advances after it passes)</label>
                </div>
            </div>
        </form>
        <div class="modal-footer" style="padding:1rem 1.25rem;border-top:1px solid var(--border);display:flex;flex-wrap:wrap;gap:0.5rem;align-items:center;justify-content:space-between;">
            <?php if ($rem): ?>
            <form method="post" action="<?= htmlspecialchars($basePath) ?>/financial-ratios/clear-reminder" style="margin:0;" onsubmit="return confirm('Remove this reminder?');">
                <input type="hidden" name="category_id" value="<?= $cid ?>">
                <input type="hidden" name="return_tab" value="<?= htmlspecialchars($activeTab) ?>">
                <button type="submit" class="btn btn-outline btn-sm" style="color:var(--danger);border-color:var(--danger);">Remove reminder</button>
            </form>
            <?php else: ?><span></span><?php endif; ?>
            <div style="display:flex;gap:0.5rem;margin-left:auto;">
                <button type="button" class="btn btn-secondary fr-modal-close" data-close="fr-reminder-modal">Cancel</button>
                <button type="submit" class="btn btn-primary" form="fr-reminder-form">Save reminder</button>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<?php endif; /* block */ ?>
<?php endif; ?>

<div id="fr-history-modal" class="modal-overlay compliance-modal" style="display:none;" aria-hidden="true">
    <div class="modal fr-history-modal-inner" role="dialog">
        <div class="modal-header">
            <div>
                <h2 class="modal-title" id="fr-history-title">—</h2>
                <p class="text-muted text-sm mb-0" id="fr-history-limit"></p>
            </div>
            <button type="button" class="modal-close" id="fr-history-close" aria-label="Close">&times;</button>
        </div>
        <div class="modal-body">
            <div class="table-wrap">
                <table class="data-table">
                    <thead>
                        <tr><th>#</th><th>Value</th><th>Status</th><th>Uploaded Date</th><th>Uploaded By</th></tr>
                    </thead>
                    <tbody id="fr-history-tbody"></tbody>
                </table>
            </div>
            <p class="text-muted text-sm mb-0 mt-2" id="fr-history-empty" style="display:none;">No upload history yet. Data will appear after CSV uploads or initial seed.</p>
        </div>
    </div>
</div>
<script>
(function(){
    var modal = document.getElementById('fr-history-modal');
    var tbody = document.getElementById('fr-history-tbody');
    var empty = document.getElementById('fr-history-empty');
    var titleEl = document.getElementById('fr-history-title');
    var limitEl = document.getElementById('fr-history-limit');
    function openHist(title, limit, rows) {
        titleEl.textContent = title + ' — Upload History';
        limitEl.textContent = 'Regulatory limit: ' + limit;
        tbody.innerHTML = '';
        if (!rows || !rows.length) {
            empty.style.display = 'block';
        } else {
            empty.style.display = 'none';
            rows.forEach(function(row, i) {
                var st = row.status || '';
                var badge = st === 'non_compliant' ? 'badge-danger' : (st === 'watch' ? 'badge-warning' : 'badge-success');
                var sl = st === 'non_compliant' ? 'Non-Compliant' : (st === 'watch' ? 'Watch' : 'Compliant');
                var d = row.uploaded_at || '';
                var tr = document.createElement('tr');
                tr.innerHTML = '<td>' + (i+1) + '</td><td>' + escapeHtml(row.value) + '</td><td><span class="badge ' + badge + '">' + sl + '</span></td><td>' + escapeHtml(row.display_date || fmtDate(row.uploaded_at)) + '</td><td>' + escapeHtml(row.uploader_name || '—') + '</td>';
                tbody.appendChild(tr);
            });
        }
        modal.style.display = 'flex';
        modal.setAttribute('aria-hidden', 'false');
        document.body.style.overflow = 'hidden';
    }
    function closeHist() {
        modal.style.display = 'none';
        modal.setAttribute('aria-hidden', 'true');
        document.body.style.overflow = '';
    }
    function escapeHtml(s) {
        var d = document.createElement('div');
        d.textContent = s;
        return d.innerHTML;
    }
    function fmtDate(ymd) {
        if (!ymd) return '—';
        var p = String(ymd).split('-');
        if (p.length !== 3) return ymd;
        var mo = ['Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec'][parseInt(p[1],10)-1];
        return parseInt(p[2],10) + ' ' + mo + ' ' + p[0];
    }
    document.querySelectorAll('.fr-history-btn').forEach(function(btn) {
        btn.addEventListener('click', function() {
            var b64 = this.getAttribute('data-history-b64');
            var rows = [];
            try { rows = JSON.parse(atob(b64)); } catch(e) {}
            if (rows && rows.length) {
                rows = rows.map(function(r) {
                    r.display_date = fmtDate(r.uploaded_at);
                    return r;
                });
            }
            openHist(this.getAttribute('data-title'), this.getAttribute('data-limit'), rows);
        });
    });
    document.getElementById('fr-history-close').addEventListener('click', closeHist);
    modal.addEventListener('click', function(e) { if (e.target === modal) closeHist(); });
    document.addEventListener('keydown', function(e) { if (e.key === 'Escape' && modal.style.display === 'flex') closeHist(); });
})();
(function(){
    function openModal(id) {
        var el = document.getElementById(id);
        if (!el) return;
        el.style.display = 'flex';
        el.setAttribute('aria-hidden', 'false');
        document.body.style.overflow = 'hidden';
    }
    function closeModal(id) {
        var el = document.getElementById(id);
        if (!el) return;
        el.style.display = 'none';
        el.setAttribute('aria-hidden', 'true');
        document.body.style.overflow = '';
    }
    var oe = document.getElementById('fr-open-edit');
    if (oe) oe.addEventListener('click', function() { openModal('fr-edit-modal'); });
    var or = document.getElementById('fr-open-reminder');
    if (or) or.addEventListener('click', function() { openModal('fr-reminder-modal'); });
    document.querySelectorAll('.fr-modal-close').forEach(function(btn) {
        btn.addEventListener('click', function() { closeModal(this.getAttribute('data-close')); });
    });
    ['fr-edit-modal','fr-reminder-modal'].forEach(function(id) {
        var el = document.getElementById(id);
        if (el) el.addEventListener('click', function(e) { if (e.target === el) closeModal(id); });
    });
    document.addEventListener('keydown', function(e) {
        if (e.key !== 'Escape') return;
        ['fr-edit-modal','fr-reminder-modal'].forEach(function(id) {
            var el = document.getElementById(id);
            if (el && el.style.display === 'flex') closeModal(id);
        });
    });
})();
</script>
