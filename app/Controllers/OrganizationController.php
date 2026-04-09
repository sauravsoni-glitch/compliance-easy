<?php
namespace App\Controllers;

use App\Core\Auth;
use App\Core\BaseController;
use App\Core\Mailer;

class OrganizationController extends BaseController
{
    /** @return string[] */
    private function departmentOptions(): array
    {
        $defaults = ['Compliance', 'Finance', 'Legal', 'Operations', 'Risk', 'IT', 'HR'];
        $opts = $defaults;
        try {
            $stmt = $this->db->prepare('SELECT DISTINCT department FROM users WHERE organization_id = ? AND department IS NOT NULL AND TRIM(department) <> "" ORDER BY department');
            $stmt->execute([Auth::organizationId()]);
            foreach ($stmt->fetchAll(\PDO::FETCH_COLUMN) as $d) {
                $d = trim((string) $d);
                if ($d !== '' && !in_array($d, $opts, true)) {
                    $opts[] = $d;
                }
            }
        } catch (\Throwable $e) {
            // keep defaults only
        }
        sort($opts, SORT_NATURAL | SORT_FLAG_CASE);
        return $opts;
    }

    private function normalizedDepartmentOrEmpty(string $dept): string
    {
        $dept = trim($dept);
        if ($dept === '') {
            return '';
        }
        foreach ($this->departmentOptions() as $opt) {
            if (strcasecmp($opt, $dept) === 0) {
                return $opt;
            }
        }
        return '';
    }

    private function orgExtended(): bool
    {
        static $x;
        if ($x === null) {
            try {
                $this->db->query('SELECT company_size FROM organizations LIMIT 1');
                $x = true;
            } catch (\Throwable $e) {
                $x = false;
            }
        }

        return $x;
    }

    private function mailConfig(): array
    {
        $path = dirname(__DIR__, 2) . '/config/mail.php';

        return is_file($path) ? (require $path) : ['enabled' => false];
    }

    private function finalizeInviteNotification(
        string $toEmail,
        string $toName,
        string $department,
        string $roleLabel,
        string $inviteLink
    ): void {
        $cfg = $this->mailConfig();
        if (!empty($cfg['enabled'])) {
            [$sent, $err] = Mailer::sendWorkspaceInvite(
                $this->appConfig,
                $toEmail,
                $toName,
                $inviteLink,
                $roleLabel,
                $department
            );
            if ($sent) {
                $_SESSION['flash_success'] = 'Invitation email sent to ' . $toEmail . '.';

                return;
            }
            $_SESSION['flash_error'] = 'Invitation saved, but email could not be sent'
                . ($err ? ': ' . $err : '')
                . '. Share this link manually: ' . $inviteLink;

            return;
        }
        $_SESSION['flash_success'] = 'Invitation saved. Join link: ' . $inviteLink;
    }

    private function roleDisplayName(string $roleSlug): string
    {
        $stmt = $this->db->prepare('SELECT name FROM roles WHERE slug = ? LIMIT 1');
        $stmt->execute([$roleSlug]);
        $n = $stmt->fetchColumn();

        return $n ? (string) $n : ucfirst($roleSlug);
    }

    private function loadPendingInviteForEmail(int $inviteId, int $orgId): ?array
    {
        if ($this->inviteHasExtraColumns()) {
            $sql = 'SELECT i.email, i.full_name, i.department, r.slug AS role_slug, r.name AS role_name
                FROM organization_invites i INNER JOIN roles r ON r.id = i.role_id
                WHERE i.id = ? AND i.organization_id = ? AND i.accepted_at IS NULL LIMIT 1';
        } else {
            $sql = 'SELECT i.email, NULL AS full_name, NULL AS department, r.slug AS role_slug, r.name AS role_name
                FROM organization_invites i INNER JOIN roles r ON r.id = i.role_id
                WHERE i.id = ? AND i.organization_id = ? AND i.accepted_at IS NULL LIMIT 1';
        }
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$inviteId, $orgId]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);

        return $row ?: null;
    }

    private function inviteHasExtraColumns(): bool
    {
        static $x;
        if ($x === null) {
            try {
                $this->db->query('SELECT full_name FROM organization_invites LIMIT 1');
                $x = true;
            } catch (\Throwable $e) {
                $x = false;
            }
        }

        return $x;
    }

    private function roleIdForSlug(string $slug): int
    {
        $s = $this->db->prepare('SELECT id FROM roles WHERE slug = ? LIMIT 1');
        $s->execute([strtolower($slug)]);

        return (int) $s->fetchColumn() ?: 2;
    }

    private function stepperState(int $onboardingStep): array
    {
        if ($onboardingStep >= 3) {
            return ['s1' => 'done', 's2' => 'done', 's3' => 'done'];
        }
        if ($onboardingStep === 2) {
            return ['s1' => 'done', 's2' => 'current', 's3' => 'pending'];
        }

        return ['s1' => 'current', 's2' => 'pending', 's3' => 'pending'];
    }

    private function loadOrg(int $orgId): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM organizations WHERE id = ?');
        $stmt->execute([$orgId]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);

        return $row ?: null;
    }

    private function teamUsers(int $orgId): array
    {
        $sql = 'SELECT u.id, u.full_name, u.email, u.department, u.status, r.name AS role_name, r.slug AS role_slug
            FROM users u INNER JOIN roles r ON r.id = u.role_id
            WHERE u.organization_id = ? ORDER BY u.full_name';
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$orgId]);

        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    private function pendingInvites(int $orgId): array
    {
        if ($this->inviteHasExtraColumns()) {
            $sql = 'SELECT i.id, i.email, i.expires_at, i.created_at, i.role_id, r.name AS role_name, i.full_name, i.department
                FROM organization_invites i INNER JOIN roles r ON r.id = i.role_id
                WHERE i.organization_id = ? AND i.accepted_at IS NULL ORDER BY i.created_at DESC';
        } else {
            $sql = 'SELECT i.id, i.email, i.expires_at, i.created_at, i.role_id, r.name AS role_name, NULL AS full_name, NULL AS department
                FROM organization_invites i INNER JOIN roles r ON r.id = i.role_id
                WHERE i.organization_id = ? AND i.accepted_at IS NULL ORDER BY i.created_at DESC';
        }
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$orgId]);

        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    private function pendingInviteById(int $inviteId, int $orgId): ?array
    {
        if ($inviteId <= 0) {
            return null;
        }
        if ($this->inviteHasExtraColumns()) {
            $sql = 'SELECT i.id, i.email, i.expires_at, i.created_at, i.role_id, r.slug AS role_slug, r.name AS role_name, i.full_name, i.department
                FROM organization_invites i INNER JOIN roles r ON r.id = i.role_id
                WHERE i.id = ? AND i.organization_id = ? AND i.accepted_at IS NULL LIMIT 1';
        } else {
            $sql = 'SELECT i.id, i.email, i.expires_at, i.created_at, i.role_id, r.slug AS role_slug, r.name AS role_name, NULL AS full_name, NULL AS department
                FROM organization_invites i INNER JOIN roles r ON r.id = i.role_id
                WHERE i.id = ? AND i.organization_id = ? AND i.accepted_at IS NULL LIMIT 1';
        }
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$inviteId, $orgId]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);

        return $row ?: null;
    }

    private function saveLogo(int $orgId): ?string
    {
        if (empty($_FILES['logo']['tmp_name']) || !is_uploaded_file($_FILES['logo']['tmp_name'])) {
            return null;
        }
        if ((int) ($_FILES['logo']['error'] ?? 0) !== UPLOAD_ERR_OK) {
            return null;
        }
        if ((int) ($_FILES['logo']['size'] ?? 0) > 2 * 1024 * 1024) {
            $_SESSION['flash_error'] = 'Logo must be 2MB or smaller.';

            return '__error__';
        }
        $finfo = new \finfo(FILEINFO_MIME_TYPE);
        $mime = $finfo->file($_FILES['logo']['tmp_name']);
        $ext = null;
        if (in_array($mime, ['image/jpeg', 'image/jpg'], true)) {
            $ext = 'jpg';
        } elseif ($mime === 'image/png') {
            $ext = 'png';
        }
        if (!$ext) {
            $_SESSION['flash_error'] = 'Logo must be PNG or JPG.';

            return '__error__';
        }
        $dir = $this->uploadHistorySubdir('orgs');
        $name = 'org_' . $orgId . '_' . time() . '.' . $ext;
        $path = $dir . DIRECTORY_SEPARATOR . $name;
        if (!move_uploaded_file($_FILES['logo']['tmp_name'], $path)) {
            return null;
        }
        chmod($path, 0644);
        $this->forwardUploadedFileToWebhook($path, $_FILES['logo']['name'] ?? basename($path));

        return $this->uploadHistoryDbPath('orgs', $name);
    }

    public function index(): void
    {
        Auth::requireAuth();
        $orgId = Auth::organizationId();
        $org = $this->loadOrg($orgId);
        if (!$org) {
            $this->redirect('/dashboard');
        }
        $isAdmin = Auth::isAdmin();
        $step = (int) ($org['onboarding_step'] ?? 1);
        if ($isAdmin && $step === 2) {
            $this->redirect('/organization/invite');
        }
        $stepper = $this->stepperState($step);

        $teamUsers = [];
        $pendingInvites = [];
        if ($step >= 3) {
            $teamUsers = $this->teamUsers($orgId);
            $pendingInvites = $this->pendingInvites($orgId);
        }

        $this->view('organization/index', [
            'currentPage' => 'organization',
            'pageTitle' => 'Organization',
            'user' => Auth::user(),
            'basePath' => $this->appConfig['url'] ?? '',
            'organization' => $org,
            'onboardingStep' => $step,
            'stepper' => $stepper,
            'isAdmin' => $isAdmin,
            'teamUsers' => $teamUsers,
            'pendingInvites' => $pendingInvites,
            'orgExtended' => $this->orgExtended(),
        ]);
    }

    public function setup(): void
    {
        Auth::requireRole('admin');
        $this->redirect('/organization');
    }

    public function invite(): void
    {
        Auth::requireRole('admin');
        $orgId = Auth::organizationId();
        $org = $this->loadOrg($orgId);
        $step = (int) ($org['onboarding_step'] ?? 1);
        if ($step < 2) {
            $_SESSION['flash_error'] = 'Complete organization profile first.';
            $this->redirect('/organization');
        }
        $stepper = $this->stepperState($step >= 3 ? 3 : 2);
        if ($step >= 3) {
            $stepper = ['s1' => 'done', 's2' => 'done', 's3' => 'done'];
        }

        $roles = $this->db->query('SELECT id, name, slug FROM roles WHERE slug IN (\'admin\',\'maker\',\'reviewer\',\'approver\') ORDER BY FIELD(slug,\'admin\',\'maker\',\'reviewer\',\'approver\')')->fetchAll(\PDO::FETCH_ASSOC);
        $editInviteId = (int) ($_GET['edit'] ?? 0);
        $editInvite = $this->pendingInviteById($editInviteId, $orgId);

        $this->view('organization/invite', [
            'currentPage' => 'organization',
            'pageTitle' => 'Invite Users',
            'user' => Auth::user(),
            'basePath' => $this->appConfig['url'] ?? '',
            'organization' => $org,
            'stepper' => $stepper,
            'onboardingStep' => $step,
            'rolesForInvite' => $roles,
            'departmentOptions' => $this->departmentOptions(),
            'editInvite' => $editInvite,
        ]);
    }

    public function complete(): void
    {
        Auth::requireAuth();
        $org = $this->loadOrg(Auth::organizationId());
        $step = (int) ($org['onboarding_step'] ?? 1);
        if ($step >= 3) {
            $this->redirect('/organization');
        }
        $this->view('organization/complete', [
            'currentPage' => 'organization',
            'pageTitle' => 'Setup Complete',
            'user' => Auth::user(),
            'basePath' => $this->appConfig['url'] ?? '',
            'stepper' => ['s1' => 'done', 's2' => 'done', 's3' => 'done'],
        ]);
    }

    public function saveStep1(): void
    {
        Auth::requireRole('admin');
        $orgId = Auth::organizationId();
        $name = trim($_POST['organization_name'] ?? '');
        $industry = trim($_POST['industry'] ?? '');
        $size = trim($_POST['company_size'] ?? '');
        $tz = trim($_POST['timezone'] ?? '');
        $city = trim($_POST['city'] ?? '');
        $country = trim($_POST['country'] ?? '');

        if ($name === '' || $industry === '' || $size === '' || $tz === '' || $city === '' || $country === '') {
            $_SESSION['flash_error'] = 'Please fill all required fields (marked with *).';
            $this->redirect('/organization');
        }

        $logoRel = null;
        if ($this->orgExtended() && !empty($_FILES['logo']['name'])) {
            $logoRel = $this->saveLogo($orgId);
            if ($logoRel === '__error__') {
                $this->redirect('/organization');
            }
        }

        if ($this->orgExtended()) {
            if ($logoRel) {
                $this->db->prepare('UPDATE organizations SET name=?, industry=?, company_size=?, timezone=?, city=?, country=?, logo_path=?, onboarding_step=2 WHERE id=?')
                    ->execute([$name, $industry, $size, $tz, $city, $country, $logoRel, $orgId]);
            } else {
                $this->db->prepare('UPDATE organizations SET name=?, industry=?, company_size=?, timezone=?, city=?, country=?, onboarding_step=2 WHERE id=?')
                    ->execute([$name, $industry, $size, $tz, $city, $country, $orgId]);
            }
        } else {
            $detail = "City: {$city}\nCountry: {$country}\nCompany size: {$size}\nTimezone: {$tz}";
            $this->db->prepare('UPDATE organizations SET name=?, industry=?, address=?, onboarding_step=2 WHERE id=?')
                ->execute([$name, $industry, $detail, $orgId]);
        }

        $_SESSION['flash_success'] = 'Organization profile saved.';
        $this->redirect('/organization/invite');
    }

    public function updateOrg(): void
    {
        Auth::requireRole('admin');
        $orgId = Auth::organizationId();
        $name = trim($_POST['organization_name'] ?? '');
        $email = trim($_POST['company_email'] ?? '');
        if ($name === '') {
            $_SESSION['flash_error'] = 'Organization name is required.';
            $this->redirect('/organization');
        }
        if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $_SESSION['flash_error'] = 'Invalid company email.';
            $this->redirect('/organization');
        }
        $phone = trim($_POST['phone'] ?? '');
        $address = trim($_POST['address'] ?? '');
        $industry = trim($_POST['industry'] ?? '');
        $cin = trim($_POST['registration_number'] ?? '');
        $size = trim($_POST['company_size'] ?? '');
        $tz = trim($_POST['timezone'] ?? '');
        $city = trim($_POST['city'] ?? '');
        $country = trim($_POST['country'] ?? '');

        $logoRel = null;
        if (!empty($_FILES['logo']['name'])) {
            $logoRel = $this->saveLogo($orgId);
            if ($logoRel === '__error__') {
                $this->redirect('/organization');
            }
        }

        if ($this->orgExtended()) {
            if ($logoRel) {
                $this->db->prepare('UPDATE organizations SET name=?, contact_email=?, phone=?, address=?, industry=?, registration_number=?, company_size=?, timezone=?, city=?, country=?, logo_path=? WHERE id=?')
                    ->execute([$name, $email ?: null, $phone ?: null, $address ?: null, $industry ?: null, $cin ?: null, $size ?: null, $tz ?: null, $city ?: null, $country ?: null, $logoRel, $orgId]);
            } else {
                $this->db->prepare('UPDATE organizations SET name=?, contact_email=?, phone=?, address=?, industry=?, registration_number=?, company_size=?, timezone=?, city=?, country=? WHERE id=?')
                    ->execute([$name, $email ?: null, $phone ?: null, $address ?: null, $industry ?: null, $cin ?: null, $size ?: null, $tz ?: null, $city ?: null, $country ?: null, $orgId]);
            }
        } else {
            $this->db->prepare('UPDATE organizations SET name=?, contact_email=?, phone=?, address=?, industry=?, registration_number=? WHERE id=?')
                ->execute([$name, $email ?: null, $phone ?: null, $address ?: null, $industry ?: null, $cin ?: null, $orgId]);
        }
        $_SESSION['flash_success'] = 'Organization updated.';
        $this->redirect('/organization');
    }

    public function saveSetup(): void
    {
        $this->saveStep1();
    }

    public function sendInvite(): void
    {
        Auth::requireRole('admin');
        $orgId = Auth::organizationId();
        $fname = trim($_POST['invite_full_name'] ?? '');
        $mail = trim($_POST['invite_email'] ?? '');
        $dept = $this->normalizedDepartmentOrEmpty((string) ($_POST['invite_department'] ?? ''));
        $roleSlug = strtolower(trim($_POST['invite_role'] ?? ''));

        if ($fname === '') {
            $_SESSION['flash_error'] = 'Full name is required.';
            $this->redirect('/organization/invite');
        }
        if ($mail === '' || !filter_var($mail, FILTER_VALIDATE_EMAIL)) {
            $_SESSION['flash_error'] = 'Valid email address is required.';
            $this->redirect('/organization/invite');
        }
        if (!in_array($roleSlug, ['admin', 'maker', 'reviewer', 'approver'], true)) {
            $_SESSION['flash_error'] = 'Please select a role.';
            $this->redirect('/organization/invite');
        }

        $exists = $this->db->prepare('SELECT id FROM users WHERE organization_id = ? AND LOWER(email) = LOWER(?)');
        $exists->execute([$orgId, $mail]);
        if ($exists->fetchColumn()) {
            $_SESSION['flash_error'] = 'A user with this email already exists in your organization.';
            $this->redirect('/organization/invite');
        }

        $this->db->prepare('DELETE FROM organization_invites WHERE organization_id = ? AND LOWER(email) = LOWER(?) AND accepted_at IS NULL')->execute([$orgId, $mail]);
        $token = bin2hex(random_bytes(16));
        $exp = date('Y-m-d H:i:s', strtotime('+24 hours'));
        $roleId = $this->roleIdForSlug($roleSlug);
        $hasExtra = $this->inviteHasExtraColumns();
        try {
            if ($hasExtra) {
                $this->db->prepare('INSERT INTO organization_invites (organization_id, full_name, department, email, token, role_id, expires_at, created_by) VALUES (?,?,?,?,?,?,?,?)')
                    ->execute([$orgId, $fname, $dept ?: null, $mail, $token, $roleId, $exp, Auth::id()]);
            } else {
                $this->db->prepare('INSERT INTO organization_invites (organization_id, email, token, role_id, expires_at, created_by) VALUES (?,?,?,?,?,?)')
                    ->execute([$orgId, $mail, $token, $roleId, $exp, Auth::id()]);
            }
            $base = $this->publicAbsoluteBaseUrl();
            $link = $base . '/invite/accept?token=' . urlencode($token);
            $this->finalizeInviteNotification($mail, $fname, $dept, $this->roleDisplayName($roleSlug), $link);
        } catch (\Throwable $e) {
            $_SESSION['flash_error'] = 'Could not send invitation. Try again.';
        }
        $this->redirect('/organization/invite');
    }

    public function updateInvite(): void
    {
        Auth::requireRole('admin');
        $orgId = Auth::organizationId();
        $inviteId = (int) ($_POST['invite_id'] ?? 0);
        $fname = trim($_POST['invite_full_name'] ?? '');
        $mail = trim($_POST['invite_email'] ?? '');
        $dept = $this->normalizedDepartmentOrEmpty((string) ($_POST['invite_department'] ?? ''));
        $roleSlug = strtolower(trim($_POST['invite_role'] ?? ''));

        $existing = $this->pendingInviteById($inviteId, $orgId);
        if (!$existing) {
            $_SESSION['flash_error'] = 'Pending invite not found.';
            $this->redirect('/organization');
        }
        if ($fname === '') {
            $_SESSION['flash_error'] = 'Full name is required.';
            $this->redirect('/organization/invite?edit=' . $inviteId);
        }
        if ($mail === '' || !filter_var($mail, FILTER_VALIDATE_EMAIL)) {
            $_SESSION['flash_error'] = 'Valid email address is required.';
            $this->redirect('/organization/invite?edit=' . $inviteId);
        }
        if (!in_array($roleSlug, ['admin', 'maker', 'reviewer', 'approver'], true)) {
            $_SESSION['flash_error'] = 'Please select a role.';
            $this->redirect('/organization/invite?edit=' . $inviteId);
        }
        $exists = $this->db->prepare('SELECT id FROM users WHERE organization_id = ? AND LOWER(email) = LOWER(?)');
        $exists->execute([$orgId, $mail]);
        if ($exists->fetchColumn()) {
            $_SESSION['flash_error'] = 'A user with this email already exists in your organization.';
            $this->redirect('/organization/invite?edit=' . $inviteId);
        }

        $newToken = bin2hex(random_bytes(16));
        $newExp = date('Y-m-d H:i:s', strtotime('+24 hours'));
        $roleId = $this->roleIdForSlug($roleSlug);

        try {
            if ($this->inviteHasExtraColumns()) {
                $this->db->prepare('UPDATE organization_invites SET full_name=?, department=?, email=?, token=?, role_id=?, expires_at=? WHERE id=? AND organization_id=? AND accepted_at IS NULL')
                    ->execute([$fname, $dept ?: null, $mail, $newToken, $roleId, $newExp, $inviteId, $orgId]);
            } else {
                $this->db->prepare('UPDATE organization_invites SET email=?, token=?, role_id=?, expires_at=? WHERE id=? AND organization_id=? AND accepted_at IS NULL')
                    ->execute([$mail, $newToken, $roleId, $newExp, $inviteId, $orgId]);
            }
            $base = $this->publicAbsoluteBaseUrl();
            $link = $base . '/invite/accept?token=' . urlencode($newToken);
            $this->finalizeInviteNotification($mail, $fname, $dept, $this->roleDisplayName($roleSlug), $link);
        } catch (\Throwable $e) {
            $_SESSION['flash_error'] = 'Could not update invitation. Try again.';
        }
        $this->redirect('/organization');
    }

    public function skipInvite(): void
    {
        Auth::requireRole('admin');
        $orgId = Auth::organizationId();
        $this->db->prepare('UPDATE organizations SET onboarding_step = 3 WHERE id = ?')->execute([$orgId]);
        $_SESSION['flash_success'] = 'You can invite team members anytime from Organization.';
        $this->redirect('/organization');
    }

    public function finishSetup(): void
    {
        Auth::requireRole('admin');
        $orgId = Auth::organizationId();
        $this->db->prepare('UPDATE organizations SET onboarding_step = 3 WHERE id = ?')->execute([$orgId]);
        $_SESSION['org_just_completed'] = true;
        $this->redirect('/organization');
    }

    public function resendInvite(): void
    {
        Auth::requireRole('admin');
        $id = (int) ($_POST['invite_id'] ?? 0);
        $orgId = Auth::organizationId();
        $stmt = $this->db->prepare('SELECT id FROM organization_invites WHERE id = ? AND organization_id = ? AND accepted_at IS NULL');
        $stmt->execute([$id, $orgId]);
        if (!$stmt->fetchColumn()) {
            $_SESSION['flash_error'] = 'Invite not found.';
            $this->redirect('/organization');
        }
        $token = bin2hex(random_bytes(16));
        $this->db->prepare('UPDATE organization_invites SET token = ?, expires_at = ? WHERE id = ? AND organization_id = ?')
            ->execute([$token, date('Y-m-d H:i:s', strtotime('+24 hours')), $id, $orgId]);
        $base = $this->publicAbsoluteBaseUrl();
        $link = $base . '/invite/accept?token=' . urlencode($token);
        $row = $this->loadPendingInviteForEmail($id, $orgId);
        if ($row) {
            $slug = strtolower((string) ($row['role_slug'] ?? 'maker'));
            $label = !empty($row['role_name']) ? (string) $row['role_name'] : $this->roleDisplayName($slug);
            $this->finalizeInviteNotification(
                (string) $row['email'],
                (string) ($row['full_name'] ?? ''),
                (string) ($row['department'] ?? ''),
                $label,
                $link
            );
        } else {
            $_SESSION['flash_success'] = 'Invitation updated. Join link: ' . $link;
        }
        $this->redirect('/organization');
    }

    public function viewTeamMember(int $id): void
    {
        Auth::requireAuth();
        $orgId = Auth::organizationId();
        $stmt = $this->db->prepare('SELECT u.*, r.name AS role_name FROM users u INNER JOIN roles r ON r.id = u.role_id WHERE u.id = ? AND u.organization_id = ?');
        $stmt->execute([$id, $orgId]);
        $member = $stmt->fetch(\PDO::FETCH_ASSOC);
        if (!$member) {
            $_SESSION['flash_error'] = 'User not found.';
            $this->redirect('/organization');
        }
        if (!Auth::isAdmin() && (int) $member['id'] !== Auth::id()) {
            $_SESSION['flash_error'] = 'Access denied.';
            $this->redirect('/organization');
        }
        $this->view('organization/team-member', [
            'currentPage' => 'organization',
            'pageTitle' => $member['full_name'],
            'user' => Auth::user(),
            'basePath' => $this->appConfig['url'] ?? '',
            'member' => $member,
            'isAdmin' => Auth::isAdmin(),
        ]);
    }
}
