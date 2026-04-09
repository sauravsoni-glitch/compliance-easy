<?php
namespace App\Core;

use PDO;

/**
 * Central helpers for multi-tenant row access (defense in depth with org-scoped queries).
 */
final class TenantGuard
{
    public static function requireOrganizationId(): int
    {
        Auth::requireAuth();
        $oid = Auth::organizationId();
        if (!$oid) {
            $_SESSION['flash_error'] = 'Your account is not linked to an organization.';
            $p = Auth::webPathPrefix();
            header('Location: ' . $p . '/login', true, 302);
            exit;
        }

        return (int) $oid;
    }

    /**
     * @param array<string, mixed> $row
     */
    public static function rowBelongsToOrganization(array $row, int $organizationId, string $column = 'organization_id'): bool
    {
        return isset($row[$column]) && (int) $row[$column] === $organizationId;
    }

    /**
     * @param array<string, mixed> $row
     */
    public static function matchesSessionOrganization(array $row, string $column = 'organization_id'): bool
    {
        $oid = Auth::organizationId();
        if (!$oid) {
            return false;
        }

        return self::rowBelongsToOrganization($row, (int) $oid, $column);
    }

    /**
     * Fetch a single row by primary key `id` scoped to organization_id.
     *
     * @return array<string, mixed>|null
     */
    public static function fetchRowForOrganization(
        PDO $db,
        string $table,
        int $id,
        int $organizationId,
        string $orgColumn = 'organization_id'
    ): ?array {
        $table = preg_replace('/[^a-z0-9_]/i', '', $table) ?: '';
        $orgColumn = preg_replace('/[^a-z0-9_]/i', '', $orgColumn) ?: 'organization_id';
        if ($table === '') {
            return null;
        }
        $sql = 'SELECT * FROM `' . $table . '` WHERE id = ? AND `' . $orgColumn . '` = ? LIMIT 1';
        $stmt = $db->prepare($sql);
        $stmt->execute([$id, $organizationId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ?: null;
    }
}
