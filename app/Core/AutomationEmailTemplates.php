<?php
namespace App\Core;

/**
 * Resolves org email templates from Settings → Notification Templates for automations.
 * Only enabled templates are used; no hardcoded subject/body fallbacks.
 */
final class AutomationEmailTemplates
{
    /**
     * @param list<array<string,mixed>> $templates
     * @return array<string,mixed>|null subject/body keys
     */
    public static function pickEscalationTemplate(
        array $templates,
        string $matrixTplLabel,
        string $complianceDepartment,
        int $levelIndex1Based
    ): ?array {
        $dep = trim($complianceDepartment);
        $label = trim($matrixTplLabel);

        $enabledEscalation = [];
        foreach ($templates as $t) {
            if (empty($t['enabled']) || strtolower((string) ($t['type'] ?? '')) !== 'escalation') {
                continue;
            }
            $enabledEscalation[] = $t;
        }
        if ($enabledEscalation === []) {
            return null;
        }

        // 1) Exact / fuzzy match to matrix "Email template" label (e.g. "Escalation Level 1" → "Escalation Level 1 - Overdue")
        if ($label !== '') {
            foreach ($enabledEscalation as $t) {
                if (self::nameMatchesLabel((string) ($t['name'] ?? ''), $label)) {
                    return $t;
                }
            }
        }

        // 2) Department-specific escalation (only when dept matches compliance)
        foreach ($enabledEscalation as $t) {
            if (strcasecmp((string) ($t['applicable'] ?? ''), 'Department') !== 0) {
                continue;
            }
            if ($dep !== '' && strcasecmp(trim((string) ($t['dept'] ?? '')), $dep) === 0) {
                return $t;
            }
        }

        // 3) Heuristic by level number in template name
        foreach ($enabledEscalation as $t) {
            $n = strtolower((string) ($t['name'] ?? ''));
            if ($levelIndex1Based === 1 && strpos($n, 'level 1') !== false) {
                return $t;
            }
            if ($levelIndex1Based === 2 && strpos($n, 'level 2') !== false) {
                return $t;
            }
            if ($levelIndex1Based >= 3 && strpos($n, 'high risk') !== false) {
                return $t;
            }
        }

        // 4) Any enabled escalation template (matrix label / dept may not match — still send mail)
        return $enabledEscalation[0] ?? null;
    }

    /**
     * @param list<array<string,mixed>> $templates
     * @param 'First'|'Second'|'Final' $stage
     */
    public static function pickPreDueReminderTemplate(array $templates, string $stage, string $complianceDepartment): ?array
    {
        $dep = trim($complianceDepartment);
        $enabled = [];
        foreach ($templates as $t) {
            if (empty($t['enabled']) || strtolower((string) ($t['type'] ?? '')) !== 'reminder') {
                continue;
            }
            $enabled[] = $t;
        }
        if ($enabled === []) {
            return null;
        }

        // Department-specific reminder (must match compliance department)
        if ($dep !== '') {
            foreach ($enabled as $t) {
                if (strcasecmp((string) ($t['applicable'] ?? ''), 'Department') !== 0) {
                    continue;
                }
                if (strcasecmp(trim((string) ($t['dept'] ?? '')), $dep) === 0) {
                    return $t;
                }
            }
        }

        $stage = $stage === 'Second' || $stage === 'Final' ? $stage : 'First';

        if ($stage === 'First') {
            foreach ($enabled as $t) {
                $n = strtolower((string) ($t['name'] ?? ''));
                if (strpos($n, 'first') !== false || strpos($n, 'upcoming') !== false) {
                    return $t;
                }
            }
            foreach ($enabled as $t) {
                if (strcasecmp((string) ($t['name'] ?? ''), 'Reminder - Upcoming Due Date') === 0) {
                    return $t;
                }
            }
            return $enabled[0];
        }

        if ($stage === 'Second') {
            foreach ($enabled as $t) {
                $n = strtolower((string) ($t['name'] ?? ''));
                if (strpos($n, 'second') !== false || strpos($n, '2nd') !== false) {
                    return $t;
                }
            }
            return count($enabled) > 1 ? $enabled[1] : $enabled[0];
        }

        // Final
        foreach ($enabled as $t) {
            $n = strtolower((string) ($t['name'] ?? ''));
            if (strpos($n, 'final') !== false || strpos($n, 'last') !== false) {
                return $t;
            }
        }

        $idx = count($enabled) > 2 ? 2 : 0;

        return $enabled[$idx] ?? $enabled[0];
    }

    private static function nameMatchesLabel(string $templateName, string $label): bool
    {
        $n = strtolower(trim($templateName));
        $h = strtolower(trim($label));
        if ($n === '' || $h === '') {
            return false;
        }
        if ($n === $h) {
            return true;
        }
        if (strpos($n, $h) !== false || strpos($h, $n) !== false) {
            return true;
        }

        return false;
    }
}
