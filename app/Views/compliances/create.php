<?php
$flashError = $_SESSION['flash_error'] ?? null;
unset($_SESSION['flash_error']);
$checklistItems = is_array($_POST['checklist'] ?? null) ? $_POST['checklist'] : [];
$evYes = ($_POST['evidence_required'] ?? '1') === '1';
$evTypePost = $_POST['evidence_type'] ?? '';
?>
<div class="page-header">
    <div>
        <a href="<?= $basePath ?>/compliance" class="compliance-back-link">← Back</a>
        <h1 class="page-title" style="margin-top:0.5rem;">Create New Compliance</h1>
        <p class="page-subtitle">Fill in the details to create a new compliance requirement.</p>
    </div>
</div>
<?php if ($flashError): ?>
<div class="alert alert-danger"><?= htmlspecialchars($flashError) ?></div>
<?php endif; ?>

<div class="card">
    <form method="post" action="<?= $basePath ?>/compliances/create" enctype="multipart/form-data" id="form-create-compliance">
        <div class="card create-section-card">
            <h3 class="card-title">Basic Information</h3>
            <div class="create-form-grid-2">
                <div class="form-group">
                    <label class="form-label">Compliance Title *</label>
                    <input type="text" name="title" class="form-control" placeholder="Enter compliance title" value="<?= htmlspecialchars($_POST['title'] ?? '') ?>" required>
                </div>
                <div class="form-group">
                    <label class="form-label">Compliance Category *</label>
                    <select name="authority_id" class="form-control" required>
                        <option value="">Select compliance category</option>
                        <?php foreach ($authorities as $a): ?>
                        <option value="<?= $a['id'] ?>" <?= (int)($_POST['authority_id'] ?? 0) === (int)$a['id'] ? 'selected' : '' ?>><?= htmlspecialchars($a['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label">Circular / Reference Number</label>
                    <input type="text" name="circular_reference" class="form-control" placeholder="Enter reference number" value="<?= htmlspecialchars($_POST['circular_reference'] ?? '') ?>">
                </div>
                <div class="form-group">
                    <label class="form-label">Applicable Department *</label>
                    <select name="department" id="dept-input" class="form-control" required>
                        <option value="">Select Department</option>
                        <?php foreach (['Legal', 'Finance', 'Operations', 'Risk', 'IT', 'Compliance', 'Human Resources'] as $dept): ?>
                        <option value="<?= htmlspecialchars($dept) ?>" <?= ($_POST['department'] ?? '') === $dept ? 'selected' : '' ?>><?= htmlspecialchars($dept) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <p class="form-help" id="matrix-dept-hint" style="display:none;color:var(--primary);margin-top:0.35rem;"></p>
                </div>
                <div class="form-group">
                    <label class="form-label">Compliance Area *</label>
                    <select name="compliance_area" id="compliance-area-input" class="form-control" required>
                        <option value="">Select Compliance Area</option>
                        <?php foreach (($complianceAreaOptions ?? []) as $area): ?>
                        <option value="<?= htmlspecialchars($area) ?>" <?= ($_POST['compliance_area'] ?? '') === $area ? 'selected' : '' ?>><?= htmlspecialchars($area) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <p class="form-help">Options are sourced from Authority Matrix for your organization.</p>
                </div>
                <div class="form-group">
                    <label class="form-label">Risk Level *</label>
                    <select name="risk_level" class="form-control">
                        <option value="low" <?= ($_POST['risk_level'] ?? '') === 'low' ? 'selected' : '' ?>>Low</option>
                        <option value="medium" <?= ($_POST['risk_level'] ?? 'medium') === 'medium' ? 'selected' : '' ?>>Medium</option>
                        <option value="high" <?= ($_POST['risk_level'] ?? '') === 'high' ? 'selected' : '' ?>>High</option>
                        <option value="critical" <?= ($_POST['risk_level'] ?? '') === 'critical' ? 'selected' : '' ?>>Critical</option>
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label">Priority *</label>
                    <select name="priority" class="form-control">
                        <option value="low" <?= ($_POST['priority'] ?? '') === 'low' ? 'selected' : '' ?>>Low</option>
                        <option value="medium" <?= ($_POST['priority'] ?? 'medium') === 'medium' ? 'selected' : '' ?>>Medium</option>
                        <option value="high" <?= ($_POST['priority'] ?? '') === 'high' ? 'selected' : '' ?>>High</option>
                        <option value="critical" <?= ($_POST['priority'] ?? '') === 'critical' ? 'selected' : '' ?>>Critical</option>
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label">Frequency *</label>
                    <select name="frequency" class="form-control">
                        <option value="one-time" <?= ($_POST['frequency'] ?? '') === 'one-time' ? 'selected' : '' ?>>One-time</option>
                        <option value="daily" <?= ($_POST['frequency'] ?? '') === 'daily' ? 'selected' : '' ?>>Daily</option>
                        <option value="weekly" <?= ($_POST['frequency'] ?? '') === 'weekly' ? 'selected' : '' ?>>Weekly</option>
                        <option value="fortnightly" <?= ($_POST['frequency'] ?? '') === 'fortnightly' ? 'selected' : '' ?>>Fortnightly</option>
                        <option value="monthly" <?= ($_POST['frequency'] ?? 'monthly') === 'monthly' ? 'selected' : '' ?>>Monthly</option>
                        <option value="quarterly" <?= ($_POST['frequency'] ?? '') === 'quarterly' ? 'selected' : '' ?>>Quarterly</option>
                        <option value="half-yearly" <?= ($_POST['frequency'] ?? '') === 'half-yearly' ? 'selected' : '' ?>>Half-yearly</option>
                        <option value="annual" <?= ($_POST['frequency'] ?? '') === 'annual' ? 'selected' : '' ?>>Annual</option>
                        <option value="yearly" <?= ($_POST['frequency'] ?? '') === 'yearly' ? 'selected' : '' ?>>Yearly</option>
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label">Due date *</label>
                    <input type="date" name="due_date" class="form-control" value="<?= htmlspecialchars($_POST['due_date'] ?? '') ?>" required>
                </div>
            </div>
            <div class="form-group">
                <label class="form-label">Description</label>
                <textarea name="description" class="form-control" placeholder="Enter compliance description" rows="3"><?= htmlspecialchars($_POST['description'] ?? '') ?></textarea>
            </div>
            <div class="form-group">
                <label class="form-label">Objective / Description</label>
                <textarea name="objective_text" class="form-control" rows="2" placeholder="What is the objective of this compliance?"><?= htmlspecialchars($_POST['objective_text'] ?? '') ?></textarea>
            </div>
            <div class="form-group">
                <label class="form-label">Penalty / Impact</label>
                <textarea name="penalty_impact" class="form-control" placeholder="Describe penalty or impact" rows="2"><?= htmlspecialchars($_POST['penalty_impact'] ?? '') ?></textarea>
                <p class="form-help">Optional. Will be highlighted when compliance is overdue.</p>
            </div>
            <div class="form-group">
                <label class="form-label">Penalty Amount (&#8377;)</label>
                <input type="number" name="penalty_amount" class="form-control" min="0" step="0.01" placeholder="e.g. 50000" value="<?= htmlspecialchars($_POST['penalty_amount'] ?? '') ?>">
                <p class="form-help">Optional. Enter penalty in rupees.</p>
            </div>
        </div>

        <div class="card create-section-card">
            <h3 class="card-title">Ownership &amp; Workflow</h3>
            <div class="create-form-grid-2">
                <div class="form-group">
                    <label class="form-label">Owner (Maker) *</label>
                    <select name="owner_id" class="form-control" required>
                        <option value="">Select owner</option>
                        <?php foreach ($userOptions as $u): ?>
                        <option value="<?= $u['id'] ?>" <?= (int)($_POST['owner_id'] ?? 0) === (int)$u['id'] ? 'selected' : '' ?>><?= htmlspecialchars($u['full_name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <p class="form-help">Auto-assigned from Authority Matrix based on selected department.</p>
                </div>
                <div class="form-group">
                    <label class="form-label">Approval Workflow *</label>
                    <?php $wfPost = $_POST['workflow_type'] ?? 'three-level'; ?>
                    <select name="workflow_type" id="workflow_type" class="form-control">
                        <option value="two-level" <?= $wfPost === 'two-level' ? 'selected' : '' ?>>Two Level (Maker → Approver)</option>
                        <option value="three-level" <?= $wfPost !== 'two-level' ? 'selected' : '' ?>>Three Level (Maker → Reviewer → Approver)</option>
                    </select>
                </div>
                <div class="form-group" id="reviewer-field" style="<?= $wfPost === 'two-level' ? 'display:none;' : '' ?>">
                    <label class="form-label">Reviewer</label>
                    <select name="reviewer_id" id="reviewer_id" class="form-control">
                        <option value="">Select reviewer</option>
                        <?php foreach ($userOptions as $u): ?>
                        <option value="<?= $u['id'] ?>" <?= (int)($_POST['reviewer_id'] ?? 0) === (int)$u['id'] ? 'selected' : '' ?>><?= htmlspecialchars($u['full_name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label">Approver</label>
                    <select name="approver_id" class="form-control">
                        <option value="">Select approver</option>
                        <?php foreach ($userOptions as $u): ?>
                        <option value="<?= $u['id'] ?>" <?= (int)($_POST['approver_id'] ?? 0) === (int)$u['id'] ? 'selected' : '' ?>><?= htmlspecialchars($u['full_name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
        </div>

        <div class="card create-section-card">
            <h3 class="card-title">Evidence Requirements</h3>
            <div class="form-group mb-0">
                <label class="form-label">Evidence Required *</label>
                <div class="evidence-radio-row">
                    <label class="evidence-radio-label"><input type="radio" name="evidence_required" value="1" id="evidence-yes" <?= $evYes ? 'checked' : '' ?>> Yes</label>
                    <label class="evidence-radio-label"><input type="radio" name="evidence_required" value="0" id="evidence-no" <?= !$evYes ? 'checked' : '' ?>> No</label>
                </div>
                <p class="form-help">Whether proof is required for this compliance.</p>
            </div>

            <div id="evidence-extra-panel" class="evidence-extra-panel" style="<?= $evYes ? '' : 'display:none;' ?>" aria-hidden="<?= $evYes ? 'false' : 'true' ?>">
                <div class="form-group">
                    <label class="form-label" for="evidence_type">Evidence Type *</label>
                    <select name="evidence_type" id="evidence_type" class="form-control" <?= $evYes ? 'required' : '' ?>>
                        <option value="" <?= $evTypePost === '' ? 'selected' : '' ?>>Select evidence type</option>
                        <?php
                        $types = [
                            'pdf_report' => 'PDF / Report',
                            'signed_certificate' => 'Signed certificate',
                            'regulatory_filing' => 'Regulatory filing',
                            'screenshot' => 'Screenshot / Image',
                            'spreadsheet' => 'Spreadsheet',
                            'policy_document' => 'Policy document',
                            'correspondence' => 'Correspondence / Email',
                            'audit_trail' => 'Audit trail',
                            'other' => 'Other',
                        ];
                        foreach ($types as $val => $lab):
                        ?>
                        <option value="<?= htmlspecialchars($val) ?>" <?= $evTypePost === $val ? 'selected' : '' ?>><?= htmlspecialchars($lab) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group mb-0">
                    <label class="form-label">Evidence upload <span class="text-muted font-normal">(Optional)</span></label>
                    <div class="evidence-dropzone" id="evidence-dropzone">
                        <input type="file" name="evidence_upload" id="evidence_upload" class="evidence-file-input" accept=".pdf,.doc,.docx,.png,.jpg,.jpeg,.gif,.webp,.xls,.xlsx" tabindex="-1">
                        <div class="evidence-dropzone-inner">
                            <span class="evidence-dropzone-icon"><i class="fas fa-cloud-upload-alt"></i></span>
                            <p class="evidence-dropzone-text"><strong>Click to upload</strong> or drag and drop</p>
                            <p class="evidence-dropzone-hint">PDF, DOC, PNG, JPG, JPEG, GIF, WEBP, XLS, XLSX (max 10MB)</p>
                            <span id="evidence-file-name" class="ci-file-name"></span>
                        </div>
                    </div>
                    <p class="form-help">Evidence can also be uploaded later during execution on the compliance detail page.</p>
                </div>
            </div>
        </div>

        <div class="card create-section-card">
            <h3 class="card-title">Checklist Items</h3>
            <div class="form-group">
                <div class="checklist-add-row">
                    <input type="text" id="checklist-input" class="form-control" placeholder="Enter checklist item...">
                    <button type="button" class="btn btn-secondary" onclick="addChecklistItem()">+ Add Item</button>
                </div>
            </div>
            <div id="checklist-list">
                <?php foreach ($checklistItems as $ci): if (trim((string)$ci) === '') continue; ?>
                <div class="checklist-row-item">
                    <input type="hidden" name="checklist[]" value="<?= htmlspecialchars((string)$ci) ?>">
                    <span class="checklist-row-text"><?= htmlspecialchars((string)$ci) ?></span>
                    <button type="button" class="btn btn-sm btn-secondary" onclick="this.parentElement.remove()">Remove</button>
                </div>
                <?php endforeach; ?>
            </div>
        </div>

        <div class="create-form-actions">
            <a href="<?= $basePath ?>/compliance" class="btn btn-secondary">Cancel</a>
            <button type="submit" class="btn btn-primary">Create Compliance</button>
        </div>
        <p class="form-help">After creation, the compliance appears as <strong>Pending</strong>. The maker can add documents and submit from the detail page. Internal timeline dates are auto-aligned to the due date.</p>
    </form>
</div>
<script>
(function(){
    var panel = document.getElementById('evidence-extra-panel');
    var typeSel = document.getElementById('evidence_type');
    var yes = document.getElementById('evidence-yes');
    var no = document.getElementById('evidence-no');
    function toggleEvidence() {
        var show = yes && yes.checked;
        if (panel) {
            panel.style.display = show ? 'block' : 'none';
            panel.setAttribute('aria-hidden', show ? 'false' : 'true');
        }
        if (typeSel) {
            typeSel.required = !!show;
            if (!show) { typeSel.value = ''; }
        }
    }
    if (yes) yes.addEventListener('change', toggleEvidence);
    if (no) no.addEventListener('change', toggleEvidence);

    var dz = document.getElementById('evidence-dropzone');
    var input = document.getElementById('evidence_upload');
    var nameEl = document.getElementById('evidence-file-name');
    if (dz && input) {
        dz.addEventListener('click', function(e) { if (e.target !== input) input.click(); });
        ['dragenter','dragover'].forEach(function(ev){
            dz.addEventListener(ev, function(e){ e.preventDefault(); dz.classList.add('evidence-dropzone-active'); });
        });
        dz.addEventListener('dragleave', function(){ dz.classList.remove('evidence-dropzone-active'); });
        dz.addEventListener('drop', function(e){
            e.preventDefault();
            dz.classList.remove('evidence-dropzone-active');
            if (e.dataTransfer.files.length) {
                input.files = e.dataTransfer.files;
                updateFileName();
            }
        });
        input.addEventListener('change', updateFileName);
    }
    function updateFileName() {
        if (!input || !nameEl) return;
        var file = input.files && input.files[0];
        nameEl.textContent = file ? file.name : '';
    }
})();

(function(){
    var wf       = document.getElementById('workflow_type');
    var ownerSel = document.querySelector('select[name="owner_id"]');
    var revField = document.getElementById('reviewer-field');
    var revSel   = document.getElementById('reviewer_id');
    var appSel   = document.querySelector('select[name="approver_id"]');
    var deptInp  = document.getElementById('dept-input');
    var areaSel  = document.getElementById('compliance-area-input');
    var hint     = document.getElementById('matrix-dept-hint');
    var basePath = '<?= htmlspecialchars($basePath ?? '') ?>';

    function toggleWorkflow(lock) {
        var isTwoLevel = wf.value === 'two-level';
        revField.style.display = isTwoLevel ? 'none' : '';
        wf.disabled = !!lock;
        document.getElementById('wf-hidden') && document.getElementById('wf-hidden').remove();
        if (lock) {
            // disabled selects don't submit — add hidden input
            var h = document.createElement('input');
            h.type = 'hidden'; h.name = 'workflow_type'; h.id = 'wf-hidden'; h.value = wf.value;
            wf.parentNode.appendChild(h);
        }
        document.querySelector('input[name="workflow_type"]') && !lock
            && (document.querySelector('input[name="workflow_type"]').remove());
    }

    function applyMatrix(data) {
        if (!data.found) {
            // unlock everything
            wf.disabled = false;
            hint.style.display = 'none';
            toggleWorkflow(false);
            return;
        }
        if (areaSel && Array.isArray(data.compliance_areas) && data.compliance_areas.length) {
            var current = areaSel.value;
            areaSel.innerHTML = '<option value="">Select Compliance Area</option>';
            data.compliance_areas.forEach(function(area) {
                var opt = document.createElement('option');
                opt.value = area;
                opt.textContent = area;
                if (current === area) opt.selected = true;
                areaSel.appendChild(opt);
            });
        }
        // set workflow from matrix
        wf.value = data.workflow;
        toggleWorkflow(true); // lock dropdown

        // Matrix is source-of-truth: always apply mapped users for selected department.
        if (data.maker_id && ownerSel) ownerSel.value = String(data.maker_id);
        if (data.reviewer_id && revSel) revSel.value = String(data.reviewer_id);
        if (data.approver_id && appSel) appSel.value = String(data.approver_id);

        // show hint
        if (data.matched_by === 'department_area') {
            hint.textContent = '⚡ Authority Matrix applied by Department + Compliance Area: workflow and maker/reviewer/approver are auto-assigned.';
        } else {
            hint.textContent = '⚡ Authority Matrix applied by Department. Select Compliance Area to refine workflow/users for that area.';
        }
        hint.style.display = 'block';
    }

    var debounceTimer;
    function fetchMatrix(useArea) {
        var dept = deptInp ? deptInp.value.trim() : '';
        if (!dept) { applyMatrix({ found: false }); return; }
        var area = useArea && areaSel ? areaSel.value.trim() : '';
        clearTimeout(debounceTimer);
        debounceTimer = setTimeout(function() {
            var url = basePath + '/compliances/matrix-for-dept?dept=' + encodeURIComponent(dept);
            if (area) {
                url += '&area=' + encodeURIComponent(area);
            }
            fetch(url)
                .then(function(r){ return r.json(); })
                .then(applyMatrix)
                .catch(function(){ applyMatrix({ found: false }); });
        }, 400);
    }

    if (deptInp) {
        deptInp.addEventListener('input', function(){
            if (areaSel) areaSel.value = '';
            fetchMatrix(false);
        });
        deptInp.addEventListener('change', function(){
            if (areaSel) areaSel.value = '';
            fetchMatrix(false);
        });
        // trigger on load if dept pre-filled (e.g. form re-submit)
        if (deptInp.value.trim()) fetchMatrix(true);
    }
    if (areaSel) {
        areaSel.addEventListener('change', function(){
            fetchMatrix(true);
        });
    }

    if (wf) wf.addEventListener('change', function(){ toggleWorkflow(false); });
    toggleWorkflow(false);
})();

var checklistIndex = 0;
function addChecklistItem() {
    var val = document.getElementById('checklist-input').value.trim();
    if (!val) return;
    var div = document.createElement('div');
    div.className = 'checklist-row-item';
    div.innerHTML = '<input type="hidden" name="checklist[]" value="' + val.replace(/"/g, '&quot;') + '"><span class="checklist-row-text"></span><button type="button" class="btn btn-sm btn-secondary" onclick="this.parentElement.remove()">Remove</button>';
    div.querySelector('.checklist-row-text').textContent = val;
    document.getElementById('checklist-list').appendChild(div);
    document.getElementById('checklist-input').value = '';
}
</script>
