<?php
/**
 * api_leads.php — CRM Lead Management API for Prakut.
 * Modeled after Zoloverse architecture. Handles listing, searching, filtering,
 * status transitions, staff assignments, notes, and CSV export.
 */

declare(strict_types=1);
require_once __DIR__ . '/includes/init.php';

$admin = requireAuth(true);
$db = db();

$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? $_POST['action'] ?? '';

if ($method === 'POST' && $action === 'export') {
    handleExport($db, $admin);
    exit;
}

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
    $status   = isset($_GET['status']) ? trim((string)$_GET['status']) : '';
    $search   = isset($_GET['search']) ? trim((string)$_GET['search']) : '';
    $service  = isset($_GET['service']) ? trim((string)$_GET['service']) : '';
    $priority = isset($_GET['priority']) ? trim((string)$_GET['priority']) : '';
    $id       = isset($_GET['id']) ? (int)$_GET['id'] : 0;

    if ($id > 0) {
        $stmt = $db->prepare(
            'SELECT l.*, c.phone AS contact_phone, c.email AS contact_email, f.name AS farm_name, f.area_value AS farm_area, u.name AS assigned_user_name
             FROM leads l
             LEFT JOIN contacts c ON l.contact_id = c.id
             LEFT JOIN farms f ON l.farm_id = f.id
             LEFT JOIN users u ON l.assigned_to = u.id
             WHERE l.id = ?'
        );
        $stmt->execute([$id]);
        $lead = $stmt->fetch();
        if (!$lead) {
            json_error('Lead not found.', 404);
        }

        // Fetch related activities
        $actStmt = $db->prepare('SELECT * FROM activities WHERE lead_id = ? ORDER BY id DESC');
        $actStmt->execute([$id]);
        $lead['activities'] = $actStmt->fetchAll();

        json_success('Lead retrieved successfully.', ['lead' => $lead]);
    }

    $sql = 'SELECT l.*, u.name AS assigned_user_name
            FROM leads l
            LEFT JOIN users u ON l.assigned_to = u.id';
    $where = [];
    $params = [];

    if ($status !== '' && $status !== 'All') {
        $where[] = 'l.status = ?';
        $params[] = $status;
    }

    if ($service !== '' && $service !== 'All') {
        $where[] = 'l.service_interest = ?';
        $params[] = $service;
    }

    if ($priority !== '' && $priority !== 'All') {
        $where[] = 'l.priority = ?';
        $params[] = $priority;
    }

    if ($search !== '') {
        $where[] = '(l.lead_number LIKE ? OR l.full_name LIKE ? OR l.email LIKE ? OR l.phone LIKE ? OR l.farm_location LIKE ?)';
        $like = "%{$search}%";
        $params = array_merge($params, [$like, $like, $like, $like, $like]);
    }

    if (!empty($where)) {
        $sql .= ' WHERE ' . implode(' AND ', $where);
    }

    $sql .= ' ORDER BY l.id DESC';
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $leads = $stmt->fetchAll();

    json_success('Leads retrieved successfully.', ['leads' => $leads, 'total' => count($leads)]);
}

function handlePut(PDO $db, object $admin): void
{
    $data = read_json_body();
    if (!$data || empty($data->id)) {
        json_error('Lead ID is required.');
    }

    $id = (int)$data->id;
    $validStatuses = ['new', 'attempting_contact', 'contacted', 'qualified', 'nurture', 'converted', 'disqualified', 'closed_lost'];
    $validPriorities = ['Low', 'Medium', 'High'];

    $updates = [];
    $params  = [];

    if (isset($data->status)) {
        $st = trim((string)$data->status);
        if (!in_array($st, $validStatuses, true)) {
            json_error('Invalid status transition.');
        }
        $updates[] = 'status = ?';
        $params[] = $st;
    }

    if (isset($data->priority)) {
        $pr = trim((string)$data->priority);
        if (!in_array($pr, $validPriorities, true)) {
            json_error('Invalid priority.');
        }
        $updates[] = 'priority = ?';
        $params[] = $pr;
    }

    if (isset($data->assigned_to)) {
        $assignee = (int)$data->assigned_to > 0 ? (int)$data->assigned_to : null;
        $updates[] = 'assigned_to = ?';
        $params[] = $assignee;
    }

    if (isset($data->admin_notes)) {
        $updates[] = 'admin_notes = ?';
        $params[] = trim((string)$data->admin_notes);
    }

    if (isset($data->closed_reason)) {
        $updates[] = 'closed_reason = ?';
        $params[] = trim((string)$data->closed_reason);
    }

    if (empty($updates)) {
        json_error('No valid fields provided to update.');
    }

    $sql = 'UPDATE leads SET ' . implode(', ', $updates) . ' WHERE id = ?';
    $params[] = $id;

    $stmt = $db->prepare($sql);
    $stmt->execute($params);

    logAudit('UPDATE_LEAD', "Updated lead #{$id} fields: " . implode(', ', $updates), (int)$admin->id);
    json_success('Lead updated successfully.');
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
        json_error('Valid lead ID is required.');
    }

    $stmt = $db->prepare('DELETE FROM leads WHERE id = ?');
    $stmt->execute([$id]);

    logAudit('DELETE_LEAD', "Deleted lead #{$id}", (int)$admin->id);
    json_success('Lead record deleted successfully.');
}

function handleExport(PDO $db, object $admin): void
{
    $stmt = $db->query('SELECT lead_number, full_name, phone, email, source_page, service_interest, farm_location, farm_size, current_crop, status, priority, created_at FROM leads ORDER BY id DESC');
    $rows = $stmt->fetchAll();

    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="prakut_leads_' . date('Y-m-d') . '.csv"');

    $out = fopen('php://output', 'w');
    fputcsv($out, ['Lead #', 'Name', 'Phone', 'Email', 'Source Page', 'Service', 'Location', 'Farm Size', 'Crops', 'Status', 'Priority', 'Date Created']);

    foreach ($rows as $row) {
        fputcsv($out, [
            $row['lead_number'],
            $row['full_name'],
            $row['phone'],
            $row['email'],
            $row['source_page'],
            $row['service_interest'],
            $row['farm_location'],
            $row['farm_size'],
            $row['current_crop'],
            $row['status'],
            $row['priority'],
            $row['created_at']
        ]);
    }
    fclose($out);
    logAudit('EXPORT_LEADS', 'Exported leads to CSV', (int)$admin->id);
    exit;
}
