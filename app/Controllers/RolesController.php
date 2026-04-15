<?php
namespace App\Controllers;

use App\Core\Auth;
use App\Core\BaseController;

class RolesController extends BaseController
{
    private const ASSIGNABLE_SLUGS = ['admin', 'maker', 'reviewer', 'approver'];

    private function usersSelectSql(bool $withLastLogin): string
    {
        $login = $withLastLogin ? 'u.last_login_at,' : '';
        return "SELECT u.id, u.role_id, u.full_name, u.email, u.department, u.status, u.created_at,
            {$login} r.name AS role_name, r.slug AS role_slug
            FROM users u INNER JOIN roles r ON r.id = u.role_id
            WHERE u.organization_id = ? ORDER BY u.full_name";
    }

    private function fetchUsers(int $orgId): array
    {
        try {
            $stmt = $this->db->prepare($this->usersSelectSql(true));
            $stmt->execute([$orgId]);
            return $stmt->fetchAll(\PDO::FETCH_ASSOC);
        } catch (\Throwable $e) {
            $stmt = $this->db->prepare($this->usersSelectSql(false));
            $stmt->execute([$orgId]);
            return $stmt->fetchAll(\PDO::FETCH_ASSOC);
        }
    }

    private function logActivity(int $orgId, ?int $actorId, string $action, string $description, ?int $entityId = null): void
    {
        try {
            $ip = $_SERVER['REMOTE_ADDR'] ?? null;
            $stmt = $this->db->prepare(
                'INSERT INTO activity_logs (organization_id, user_id, action, entity_type, entity_id, description, ip_address)
                 VALUES (?, ?, ?, ?, ?, ?, ?)'
            );
            $stmt->execute([$orgId, $actorId, $action, 'user', $entityId, $description, $ip]);
        } catch (\Throwable $e) {
            // table may be missing in minimal installs
        }
    }

    private function adminCount(int $orgId): int
    {
        $stmt = $this->db->prepare(
            "SELECT COUNT(*) FROM users u
             INNER JOIN roles r ON r.id = u.role_id
             WHERE u.organization_id = ? AND r.slug = 'admin' AND u.status IN ('active','pending')"
        );
        $stmt->execute([$orgId]);
        return (int) $stmt->fetchColumn();
    }

    public function index(): void
    {
        Auth::requireAuth();
        $orgId = Auth::organizationId();
        $users = $this->fetchUsers($orgId);
        $roles = $this->db->query(
            "SELECT id, name, slug FROM roles WHERE slug IN ('admin','maker','reviewer','approver')
             ORDER BY FIELD(slug,'admin','maker','reviewer','approver')"
        )->fetchAll(\PDO::FETCH_ASSOC);

        $stats = [
            'total' => count($users),
            'admin' => 0,
            'maker' => 0,
            'reviewer' => 0,
            'approver' => 0,
        ];
        foreach ($users as $u) {
            $slug = $u['role_slug'] ?? '';
            if (isset($stats[$slug])) {
                $stats[$slug]++;
            }
        }

        $this->view('roles/index', [
            'currentPage' => 'roles',
            'pageTitle' => 'Roles & Permissions',
            'user' => Auth::user(),
            'basePath' => $this->appConfig['url'] ?? '',
            'users' => $users,
            'roles' => $roles,
            'stats' => $stats,
            'isAdmin' => Auth::isAdmin(),
        ]);
    }

    public function changeRole(): void
    {
        Auth::requireRole('admin');
        $userId = (int) ($_POST['user_id'] ?? 0);
        $roleId = (int) ($_POST['role_id'] ?? 0);
        $orgId = Auth::organizationId();
        $actorId = Auth::id();

        if (!$userId || !$roleId) {
            $_SESSION['flash_error'] = 'Invalid request.';
            $this->redirect('/roles-permissions');
        }

        $roleStmt = $this->db->prepare('SELECT id, slug, name FROM roles WHERE id = ?');
        $roleStmt->execute([$roleId]);
        $newRole = $roleStmt->fetch(\PDO::FETCH_ASSOC);
        if (!$newRole || !in_array($newRole['slug'], self::ASSIGNABLE_SLUGS, true)) {
            $_SESSION['flash_error'] = 'Invalid role selected.';
            $this->redirect('/roles-permissions');
        }

        $userStmt = $this->db->prepare(
            'SELECT u.id, u.full_name, u.email, u.role_id, r.slug AS role_slug, r.name AS role_name
             FROM users u INNER JOIN roles r ON r.id = u.role_id
             WHERE u.id = ? AND u.organization_id = ?'
        );
        $userStmt->execute([$userId, $orgId]);
        $target = $userStmt->fetch(\PDO::FETCH_ASSOC);
        if (!$target) {
            $_SESSION['flash_error'] = 'User not found.';
            $this->redirect('/roles-permissions');
        }

        if ($target['role_slug'] === 'admin' && $newRole['slug'] !== 'admin') {
            if ($this->adminCount($orgId) <= 1) {
                $_SESSION['flash_error'] = 'Cannot change role: this is the only administrator in the organization.';
                $this->redirect('/roles-permissions');
            }
        }

        $this->db->prepare('UPDATE users SET role_id = ? WHERE id = ? AND organization_id = ?')
            ->execute([$roleId, $userId, $orgId]);

        $desc = sprintf(
            'Role changed for %s (%s): %s → %s',
            $target['full_name'],
            $target['email'],
            $target['role_name'],
            $newRole['name']
        );
        $this->logActivity($orgId, $actorId, 'role_changed', $desc, $userId);

        $_SESSION['flash_success'] = 'Role updated. New permissions apply on the user’s next page load.';
        $this->redirect('/roles-permissions');
    }

    public function toggleStatus(int $id): void
    {
        Auth::requireRole('admin');
        $orgId = Auth::organizationId();
        $actorId = Auth::id();

        $stmt = $this->db->prepare(
            'SELECT u.id, u.full_name, u.email, u.status, r.slug AS role_slug
             FROM users u INNER JOIN roles r ON r.id = u.role_id
             WHERE u.id = ? AND u.organization_id = ?'
        );
        $stmt->execute([$id, $orgId]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);
        if (!$row) {
            $_SESSION['flash_error'] = 'User not found.';
            $this->redirect('/roles-permissions');
        }

        if ($row['status'] === 'pending') {
            $_SESSION['flash_error'] = 'Invited users are managed from Organization.';
            $this->redirect('/roles-permissions');
        }

        if ((int) $id === (int) $actorId) {
            $_SESSION['flash_error'] = 'You cannot deactivate your own account.';
            $this->redirect('/roles-permissions');
        }

        if ($row['role_slug'] === 'admin' && $row['status'] === 'active') {
            if ($this->adminCount($orgId) <= 1) {
                $_SESSION['flash_error'] = 'Cannot deactivate the only administrator.';
                $this->redirect('/roles-permissions');
            }
        }

        $newStatus = $row['status'] === 'active' ? 'inactive' : 'active';
        $this->db->prepare('UPDATE users SET status = ? WHERE id = ? AND organization_id = ?')
            ->execute([$newStatus, $id, $orgId]);

        $verb = $newStatus === 'active' ? 'activated' : 'deactivated';
        $this->logActivity(
            $orgId,
            $actorId,
            'user_status_' . $newStatus,
            sprintf('%s %s (%s)', ucfirst($verb), $row['full_name'], $row['email']),
            $id
        );

        $_SESSION['flash_success'] = 'User ' . $verb . '.';
        $this->redirect('/roles-permissions');
    }
}
