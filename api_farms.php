<?php
/**
 * api_farms.php — Farm & Agricultural Assets Management API for Prakut.
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
    default:
        json_error('Method Not Allowed', 405);
}

function handleGet(PDO $db): void
{
    $search = isset($_GET['search']) ? trim((string)$_GET['search']) : '';
    $id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

    if ($id > 0) {
        $stmt = $db->prepare('SELECT f.*, c.full_name AS contact_name, c.phone AS contact_phone FROM farms f LEFT JOIN contacts c ON f.contact_id = c.id WHERE f.id = ?');
        $stmt->execute([$id]);
        $farm = $stmt->fetch();
        if (!$farm) {
            json_error('Farm not found.', 404);
        }
        json_success('Farm retrieved successfully.', ['farm' => $farm]);
    }

    $sql = 'SELECT f.*, c.full_name AS contact_name, c.phone AS contact_phone, c.email AS contact_email
            FROM farms f
            LEFT JOIN contacts c ON f.contact_id = c.id';
    $params = [];

    if ($search !== '') {
        $sql .= ' WHERE f.name LIKE ? OR f.address LIKE ? OR f.district LIKE ? OR f.state LIKE ? OR c.full_name LIKE ?';
        $like = "%{$search}%";
        $params = [$like, $like, $like, $like, $like];
    }

    $sql .= ' ORDER BY f.id DESC';
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $farms = $stmt->fetchAll();

    json_success('Farms retrieved successfully.', ['farms' => $farms, 'total' => count($farms)]);
}

function handlePost(PDO $db, object $admin): void
{
    $data = read_json_body();
    if (!$data || !require_fields($data, ['name'])) {
        json_error('Farm name is required.');
    }

    $contactId = !empty($data->contact_id) ? (int)$data->contact_id : null;
    $name = trim((string)$data->name);
    $areaVal = !empty($data->area_value) ? (float)$data->area_value : null;
    $areaUnit = isset($data->area_unit) ? trim((string)$data->area_unit) : 'acre';
    $address = isset($data->address) ? trim((string)$data->address) : null;
    $district = isset($data->district) ? trim((string)$data->district) : null;
    $state = isset($data->state) ? trim((string)$data->state) : null;
    $crops = isset($data->current_crops) ? trim((string)$data->current_crops) : null;
    $notes = isset($data->notes) ? trim((string)$data->notes) : null;

    $stmt = $db->prepare('INSERT INTO farms (contact_id, name, area_value, area_unit, address, district, state, current_crops, notes) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)');
    $stmt->execute([$contactId, $name, $areaVal, $areaUnit, $address, $district, $state, $crops, $notes]);
    $id = (int)$db->lastInsertId();

    logAudit('CREATE_FARM', "Created farm record #{$id} ({$name})", (int)$admin->id);
    json_success('Farm created successfully.', ['id' => $id], 201);
}

function handlePut(PDO $db, object $admin): void
{
    $data = read_json_body();
    if (!$data || empty($data->id)) {
        json_error('Farm ID is required.');
    }

    $id = (int)$data->id;
    $updates = [];
    $params = [];

    if (isset($data->name)) { $updates[] = 'name = ?'; $params[] = trim((string)$data->name); }
    if (isset($data->area_value)) { $updates[] = 'area_value = ?'; $params[] = (float)$data->area_value; }
    if (isset($data->address)) { $updates[] = 'address = ?'; $params[] = trim((string)$data->address); }
    if (isset($data->current_crops)) { $updates[] = 'current_crops = ?'; $params[] = trim((string)$data->current_crops); }
    if (isset($data->notes)) { $updates[] = 'notes = ?'; $params[] = trim((string)$data->notes); }

    if (empty($updates)) {
        json_error('No updates provided.');
    }

    $sql = 'UPDATE farms SET ' . implode(', ', $updates) . ' WHERE id = ?';
    $params[] = $id;

    $stmt = $db->prepare($sql);
    $stmt->execute($params);

    logAudit('UPDATE_FARM', "Updated farm #{$id}", (int)$admin->id);
    json_success('Farm updated successfully.');
}
