<?php

namespace App\Core;

/**
 * Replaces {{placeholders}} in notification templates with support for
 * underscore/space/case variants (e.g. {{Compliance_ID}}, {{Compliance ID}}, {{compliance_id}}).
 */
final class EmailTemplateVars
{
    /**
     * @param array<string, string> $canonical Keys use underscore style: Compliance_ID, Due_Date, …
     */
    public static function replace(string $text, array $canonical): string
    {
        $lookup = self::buildLookup($canonical);

        return (string) preg_replace_callback('/\{\{\s*([^}]+)\s*\}\}/u', static function (array $m) use ($lookup): string {
            $norm = self::normalizeToken($m[1]);
            if ($norm !== '' && isset($lookup[$norm])) {
                return $lookup[$norm];
            }

            return $m[0];
        }, $text);
    }

    private static function normalizeToken(string $s): string
    {
        $s = trim($s);
        $s = preg_replace('/[\s_\-]+/u', '', $s) ?? $s;

        return strtolower($s);
    }

    /**
     * @param array<string, string> $canonical
     * @return array<string, string> normalized token => value
     */
    private static function buildLookup(array $canonical): array
    {
        $lookup = [];

        /** @var array<string, list<string>> */
        $aliases = [
            'Compliance_ID' => ['Compliance ID', 'ComplianceID', 'compliance_id', 'CMP_ID', 'Compliance_Code', 'Compliance Code'],
            'Compliance_Title' => ['Compliance Title', 'ComplianceTitle', 'Title', 'compliance_title', 'Compliance Name', 'ComplianceName'],
            'Department' => ['Dept', 'department'],
            'Due_Date' => ['Due Date', 'DueDate', 'due_date', 'Due date'],
            'Expected_Date' => ['Expected Date', 'ExpectedDate', 'expected_date'],
            'Creation_Date' => ['Creation Date', 'CreationDate', 'creation_date', 'Created At'],
            'Days_Remaining' => ['Days Remaining', 'DaysRemaining', 'days_remaining', 'Days Remaining'],
            'Days_Overdue' => ['Days Overdue', 'DaysOverdue', 'days_overdue'],
            'Risk_Level' => ['Risk Level', 'RiskLevel', 'risk_level'],
            'Escalation_Level' => ['Escalation Level', 'EscalationLevel', 'escalation_level', 'Level'],
            'Owner_Name' => ['Owner Name', 'OwnerName', 'owner_name', 'Assigned To', 'AssignedTo', 'Maker'],
            'Reviewer_Name' => ['Reviewer Name', 'ReviewerName', 'reviewer_name', 'Checker'],
            'Approver_Name' => ['Approver Name', 'ApproverName', 'approver_name'],
            'Sent_At' => ['Sent At', 'sent_at', 'Current_Time', 'Current Time', 'Notification_Time', 'Notification Time'],
        ];

        foreach ($canonical as $key => $value) {
            $lookup[self::normalizeToken((string) $key)] = (string) $value;
        }

        foreach ($aliases as $canonicalKey => $aliasList) {
            if (!isset($canonical[$canonicalKey])) {
                continue;
            }
            $v = (string) $canonical[$canonicalKey];
            foreach ($aliasList as $alias) {
                $lookup[self::normalizeToken($alias)] = $v;
            }
        }

        return $lookup;
    }
}
