<?php
namespace App\Controllers;

use App\Core\Auth;
use App\Core\BaseController;

class CalendarController extends BaseController
{
    public function index(): void
    {
        Auth::requireAuth();
        $orgId = Auth::organizationId();
        if (!$orgId) {
            Auth::logout();
            $this->redirect('/login');
        }
        $db = $this->db;
        $calMonth = isset($_GET['cal_month']) ? preg_replace('/[^0-9\-]/', '', $_GET['cal_month']) : date('Y-m');
        if (strlen($calMonth) !== 7) {
            $calMonth = date('Y-m');
        }
        $calStart = $calMonth . '-01';
        $calEnd = date('Y-m-t', strtotime($calStart));
        $calendarEvents = [];
        [$calC, $calCP] = Auth::calendarEventsScopeSql('c.');
        $stmt = $db->prepare("
            SELECT c.id, c.compliance_code, c.title, c.due_date, c.status, c.department, c.reviewer_id, c.approver_id
            FROM compliances c
            WHERE c.organization_id = ? AND ($calC) AND c.due_date IS NOT NULL AND c.due_date BETWEEN ? AND ?
            ORDER BY c.due_date
        ");
        $stmt->execute(array_merge([$orgId], $calCP, [$calStart, $calEnd]));
        $today = date('Y-m-d');
        while ($c = $stmt->fetch(\PDO::FETCH_ASSOC)) {
            $d = $c['due_date'];
            if (!isset($calendarEvents[$d])) {
                $calendarEvents[$d] = [];
            }
            $type = 'due';
            if (in_array($c['status'], ['approved', 'completed'])) {
                $type = 'completed';
            } elseif (in_array($c['status'], ['submitted', 'under_review'])) {
                $type = !empty($c['approver_id']) ? 'approval_pending' : 'review_pending';
            } elseif ($d < $today) {
                $type = 'overdue';
            }
            $calendarEvents[$d][] = [
                'type' => $type,
                'compliance_id' => (int)$c['id'],
                'title' => $c['title'],
                'compliance_code' => $c['compliance_code'],
                'department' => $c['department'] ?? '',
                'status' => $c['status'],
            ];
        }
        $stmt = $db->prepare("
            SELECT cs.compliance_id, DATE(cs.submission_date) AS sub_date, cs.escalation_level
            FROM compliance_submissions cs
            JOIN compliances c ON c.id = cs.compliance_id
            WHERE c.organization_id = ? AND ($calC) AND cs.submission_date IS NOT NULL AND DATE(cs.submission_date) BETWEEN ? AND ?
        ");
        $stmt->execute(array_merge([$orgId], $calCP, [$calStart, $calEnd]));
        while ($row = $stmt->fetch(\PDO::FETCH_ASSOC)) {
            $cStmt = $db->prepare('SELECT id, compliance_code, title, department, status FROM compliances WHERE id = ?');
            $cStmt->execute([$row['compliance_id']]);
            $c = $cStmt->fetch(\PDO::FETCH_ASSOC);
            if (!$c) {
                continue;
            }
            $d = $row['sub_date'];
            if (!isset($calendarEvents[$d])) {
                $calendarEvents[$d] = [];
            }
            $calendarEvents[$d][] = [
                'type' => !empty($row['escalation_level']) ? 'escalated' : 'submitted',
                'compliance_id' => (int)$row['compliance_id'],
                'title' => $c['title'] ?? '',
                'compliance_code' => $c['compliance_code'] ?? '',
                'department' => $c['department'] ?? '',
                'status' => $c['status'] ?? 'submitted',
            ];
        }
        $stmt = $db->prepare("
            SELECT c.id, c.compliance_code, c.title, c.due_date, c.status, c.department
            FROM compliances c
            WHERE c.organization_id = ? AND ($calC) AND c.due_date IS NOT NULL AND c.due_date < ?
            AND c.status NOT IN ('approved', 'completed', 'rejected')
        ");
        $stmt->execute(array_merge([$orgId], $calCP, [$calStart]));
        while ($c = $stmt->fetch(\PDO::FETCH_ASSOC)) {
            if (!isset($calendarEvents[$calStart])) {
                $calendarEvents[$calStart] = [];
            }
            $calendarEvents[$calStart][] = [
                'type' => 'overdue',
                'compliance_id' => (int)$c['id'],
                'title' => $c['title'],
                'compliance_code' => $c['compliance_code'],
                'department' => $c['department'] ?? '',
                'status' => $c['status'],
            ];
        }
        [$rb, $rbP] = Auth::complianceScopeSql('');
        $stmt = $db->prepare("
            SELECT id, compliance_code, title, due_date, start_date, expected_date, status, department
            FROM compliances
            WHERE organization_id = ? AND ($rb) AND due_date >= CURDATE() AND status NOT IN ('approved', 'completed', 'rejected')
            ORDER BY due_date ASC
            LIMIT 10
        ");
        $stmt->execute(array_merge([$orgId], $rbP));
        $upcomingDue = $stmt->fetchAll(\PDO::FETCH_ASSOC);
        $this->view('calendar/index', [
            'currentPage' => 'compliance-calendar',
            'pageTitle' => 'Compliance Calendar',
            'user' => Auth::user(),
            'basePath' => $this->appConfig['url'] ?? '',
            'calendarEvents' => $calendarEvents,
            'calendarMonth' => $calMonth,
            'upcomingDue' => $upcomingDue,
        ]);
    }
}
