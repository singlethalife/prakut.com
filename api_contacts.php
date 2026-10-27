<?php
/**
 * api_contacts.php — Landowner & Client Contacts Management API for Prakut.
 * Modeled after Zoloverse architecture.
 */

declare(strict_types=1);
require_once __DIR__ . '/includes/init.php';

$admin = requireAuth(true);
$db = db();

$method = $_SERVER['REQUEST_METHOD'];

switch ($method) {
    case 'GET':
        handleGet($db);
        break;
    case 'POST':
        handlePost($db, $admin);
        break;
    case 'PUT':
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
    $search = isset($_GET['search']) ? trim((string)$_GET['search']) : '';
    $id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

    if ($id > 0) {
        $stmt = $db->prepare('SELECT * FROM contacts WHERE id = ?');
        $stmt->execute([$id]);
        $contact = $stmt->fetch();
        if (!$contact) {
            json_error('Contact not found.', 404);
        }

        // Fetch linked farms and leads
        $farmStmt = $db->prepare('SELECT * FROM farms WHERE contact_id = ?');
        $farmStmt->execute([$id]);
        $contact['farms'] = $farmStmt->fetchAll();

        $leadStmt = $db->prepare('SELECT * FROM leads WHERE contact_id = ?');
        $leadStmt->execute([$id]);
        $contact['leads'] = $leadStmt->fetchAll();

        json_success('Contact retrieved successfully.', ['contact' => $contact]);
    }

    $sql = 'SELECT c.*,
                   (SELECT COUNT(*) FROM farms f WHERE f.contact_id = c.id) AS farm_count,
                   (SELECT COUNT(*) FROM leads l WHERE l.contact_id = c.id) AS lead_count
            FROM contacts c';
    $params = [];

    if ($search !== '') {
        $sql .= ' WHERE c.full_name LIKE ? OR c.phone LIKE ? OR c.email LIKE ? OR c.organization_name LIKE ?';
        $like = "%{$search}%";
        $params = [$like, $like, $like, $like];
    }

    $sql .= ' ORDER BY c.id DESC';
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $contacts = $stmt->fetchAll();

    json_success('Contacts retrieved successfully.', ['contacts' => $contacts, 'total' => count($contacts)]);
}

function handlePost(PDO $db, object $admin): void
{
    $data = read_json_body();
    if (!$data || !require_fields($data, ['full_name', 'phone', 'email'])) {
        json_error('Full name, phone, and email are required.');
    }

    $name = trim((string)$data->full_name);
    $phone = trim((string)$data->phone);
    $email = strtolower(trim((string)$data->email));
    $org = isset($data->organization_name) ? trim((string)$data->organization_name) : null;
    $title = isset($data->job_title) ? trim((string)$data->job_title) : null;
    $pref = isset($data->preferred_contact_method) ? trim((string)$data->preferred_contact_method) : 'Phone';
    $notes = isset($data->notes) ? trim((string)$data->notes) : null;

    $stmt = $db->prepare('INSERT INTO contacts (full_name, phone, email, organization_name, job_title, preferred_contact_method, notes) VALUES (?, ?, ?, ?, ?, ?, ?)');
    $stmt->execute([$name, $phone, $email, $org, $title, $pref, $notes]);
    $id = (int)$db->lastInsertId();

    logAudit('CREATE_CONTACT', "Created contact #{$id} ({$name})", (int)$admin->id);
    json_success('Contact created successfully.', ['id' => $id], 201);
}

function handlePut(PDO $db, object $admin): void
{
    $data = read_json_body();
    if (!$data || empty($data->id)) {
        json_error('Contact ID is required.');
    }

    $id = (int)$data->id;
    $updates = [];
    $params = [];

    if (isset($data->full_name)) { $updates[] = 'full_name = ?'; $params[] = trim((string)$data->full_name); }
    if (isset($data->phone)) { $updates[] = 'phone = ?'; $params[] = trim((string)$data->phone); }
    if (isset($data->email)) { $updates[] = 'email = ?'; $params[] = strtolower(trim((string)$data->email)); }
    if (isset($data->organization_name)) { $updates[] = 'organization_name = ?'; $params[] = trim((string)$data->organization_name); }
    if (isset($data->notes)) { $updates[] = 'notes = ?'; $params[] = trim((string)$data->notes); }
    if (isset($data->status)) { $updates[] = 'status = ?'; $params[] = trim((string)$data->status); }

    if (empty($updates)) {
        json_error('No updates provided.');
    }

    $sql = 'UPDATE contacts SET ' . implode(', ', $updates) . ' WHERE id = ?';
    $params[] = $id;

    $stmt = $db->prepare($sql);
    $stmt->execute($params);

    logAudit('UPDATE_CONTACT', "Updated contact #{$id}", (int)$admin->id);
    json_success('Contact updated successfully.');
}

function handleDelete(PDO $db, object $admin): void
{
    $id = (int)($_GET['id'] ?? $_POST['id'] ?? 0);
    if ($id <= 0) {
        json_error('Valid contact ID required.');
    }

    $stmt = $db->prepare('DELETE FROM contacts WHERE id = ?');
    $stmt->execute([$id]);

    logAudit('DELETE_CONTACT', "Deleted contact #{$id}", (int)$admin->id);
    json_success('Contact deleted successfully.');
}
