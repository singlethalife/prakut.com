<?php
/**
 * api_audits.php — Farm Health Audit Requests Management API for Prakut.
 * Modeled after Zoloverse architecture. Handles audit tracking, scheduling,
 * auditor assignment, status progression, and location notes.
 */

declare(strict_types=1);
require_once __DIR__ . '/includes/init.php';

$admin = requireAuth(true);
$db = db();

$method = $_SERVER['REQUEST_METHOD'];
if ($method === 'POST' && (($_GET['action'] ?? '') === 'delete' || ($_POST['_method'] ?? '') === 'DELETE')) {
    $method = 'DELETE';
}

switch ($method) {
    case 'GET':
        handleGet($db);
        break;
    case 'PUT':
    case 'POST':
        handlePut($db, $admin);
        break;
    case 'DELETE':
        handleDelete($db, $admin);
        break;
    default:
        json_error('Method Not Allowed', 405);
}

function handleGet(PDO $db): void
{
    $status = isset($_GET['status']) ? trim((string)$_GET['status']) : '';
    $search = isset($_GET['search']) ? trim((string)$_GET['search']) : '';
    $id     = isset($_GET['id']) ? (int)$_GET['id'] : 0;

    if ($id > 0) {
        $stmt = $db->prepare(
            'SELECT a.*, c.full_name AS contact_name, c.phone AS contact_phone, c.email AS contact_email,
                    l.lead_number, l.message AS lead_message
             FROM audit_requests a
             LEFT JOIN contacts c ON a.contact_id = c.id
             LEFT JOIN leads l ON a.lead_id = l.id
             WHERE a.id = ?'
        );
        $stmt->execute([$id]);
        $audit = $stmt->fetch();
        if (!$audit) {
            json_error('Audit request not found.', 404);
        }
        json_success('Audit details retrieved.', ['audit' => $audit]);
    }

    $sql = 'SELECT a.*, c.full_name AS contact_name, c.phone AS contact_phone, c.email AS contact_email,
                   l.lead_number
            FROM audit_requests a
            LEFT JOIN contacts c ON a.contact_id = c.id
            LEFT JOIN leads l ON a.lead_id = l.id';
    $where = [];
    $params = [];

    if ($status !== '' && $status !== 'All') {
        $where[] = 'a.status = ?';
        $params[] = $status;
    }

    if ($search !== '') {
        $where[] = '(a.audit_number LIKE ? OR a.farm_location LIKE ? OR c.full_name LIKE ? OR c.phone LIKE ? OR a.assigned_auditor LIKE ?)';
        $like = "%{$search}%";
        $params = array_merge($params, [$like, $like, $like, $like, $like]);
    }

    if (!empty($where)) {
        $sql .= ' WHERE ' . implode(' AND ', $where);
    }

    $sql .= ' ORDER BY a.id DESC';
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $audits = $stmt->fetchAll();

    json_success('Audit requests retrieved successfully.', ['audits' => $audits, 'total' => count($audits)]);
}

function handlePut(PDO $db, object $admin): void
{
    $data = read_json_body();
    if (!$data || empty($data->id)) {
        json_error('Audit Request ID is required.');
    }

    $id = (int)$data->id;
    $validStatuses = ['requested', 'contact_pending', 'scheduled', 'in_progress', 'report_pending', 'completed', 'cancelled'];
    $validReports = ['Not Started', 'In Progress', 'Ready', 'Delivered'];

    $updates = [];
    $params  = [];

    if (isset($data->status)) {
        $st = trim((string)$data->status);
        if (!in_array($st, $validStatuses, true)) {
            json_error('Invalid audit status.');
        }
        $updates[] = 'status = ?';
        $params[] = $st;
    }

    if (isset($data->appointment_at)) {
        $app = !empty($data->appointment_at) ? date('Y-m-d H:i:s', strtotime($data->appointment_at)) : null;
        $updates[] = 'appointment_at = ?';
        $params[] = $app;
    }

    if (isset($data->assigned_auditor)) {
        $updates[] = 'assigned_auditor = ?';
        $params[] = trim((string)$data->assigned_auditor);
    }

    if (isset($data->report_status)) {
        $rep = trim((string)$data->report_status);
        if (!in_array($rep, $validReports, true)) {
            json_error('Invalid report status.');
        }
        $updates[] = 'report_status = ?';
        $params[] = $rep;
    }

    if (isset($data->location_notes)) {
        $updates[] = 'location_notes = ?';
        $params[] = trim((string)$data->location_notes);
    }

    if (isset($data->internal_notes)) {
        $updates[] = 'internal_notes = ?';
        $params[] = trim((string)$data->internal_notes);
    }

    if (empty($updates)) {
        json_error('No valid fields provided to update.');
    }

    $sql = 'UPDATE audit_requests SET ' . implode(', ', $updates) . ' WHERE id = ?';
    $params[] = $id;

    $stmt = $db->prepare($sql);
    $stmt->execute($params);

    logAudit('UPDATE_AUDIT', "Updated farm audit request #{$id}", (int)$admin->id);
    json_success('Audit request updated successfully.');
}

function handleDelete(PDO $db, object $admin): void
{
    $id = (int)($_GET['id'] ?? $_POST['id'] ?? 0);
    if ($id <= 0) {
        $data = read_json_body();
        if ($data && !empty($data->id)) {
            $id = (int)$data->id;
        }
    }

    if ($id <= 0) {
        json_error('Valid audit request ID is required.');
    }

    $stmt = $db->prepare('DELETE FROM audit_requests WHERE id = ?');
    $stmt->execute([$id]);

    logAudit('DELETE_AUDIT', "Deleted farm audit request #{$id}", (int)$admin->id);
    json_success('Audit request deleted successfully.');
}
