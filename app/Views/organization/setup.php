<div class="page-header">
    <div>
        <h1 class="page-title">Organization Setup</h1>
        <p class="page-subtitle">Step 1: Organization Profile</p>
    </div>
</div>
<div class="card">
    <form method="post" action="<?= $basePath ?>/organization/setup">
        <div class="form-group">
            <label class="form-label">Organization Name *</label>
            <input type="text" name="organization_name" class="form-control" required>
        </div>
        <div class="form-group">
            <label class="form-label">Industry</label>
            <input type="text" name="industry" class="form-control" placeholder="e.g. NBFC">
        </div>
        <div class="form-group">
            <label class="form-label">Registration Number</label>
            <input type="text" name="registration_number" class="form-control">
        </div>
        <div class="form-group">
            <label class="form-label">Address</label>
            <textarea name="address" class="form-control" rows="3"></textarea>
        </div>
        <div class="form-group">
            <label class="form-label">Contact Email</label>
            <input type="email" name="contact_email" class="form-control">
        </div>
        <button type="submit" class="btn btn-primary">Save & Continue</button>
    </form>
</div>
