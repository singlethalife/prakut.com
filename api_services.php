<?php
/**
 * api_services.php — Agricultural Services Catalogue API for Prakut CMS.
 * Handles public catalogue browsing and administrator CRUD.
 * Modeled after Zoloverse architecture.
 */

declare(strict_types=1);
require_once __DIR__ . '/includes/init.php';

$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? $_POST['action'] ?? $_REQUEST['action'] ?? '';

if (($method === 'POST' || $method === 'GET') && $action === 'delete') {
    $method = 'DELETE';
}
if ($method === 'POST' && ($_POST['_method'] ?? '') === 'DELETE') {
    $method = 'DELETE';
}
if ($method === 'POST') {
    $dataCheck = read_json_body();
    if ($dataCheck && !empty($dataCheck->action) && $dataCheck->action === 'delete') {
        $method = 'DELETE';
    }
}

switch ($method) {
    case 'GET':
        handleGet();
        break;
    case 'POST':
        handlePost();
        break;
    case 'PUT':
        handlePut();
        break;
    case 'DELETE':
        handleDelete();
        break;
    default:
        json_error('Method Not Allowed', 405);
}

function handleGet(): void
{
    $admin = verifySessionToken(true);
    $id = isset($_GET['id']) ? (int)$_GET['id'] : null;
    $slug = isset($_GET['slug']) ? trim((string)$_GET['slug']) : null;

    $db = db();

    if ($id) {
        $sql = 'SELECT * FROM services WHERE id = ?';
        if (!$admin) {
            $sql .= " AND publication_status = 'Published'";
        }
        $stmt = $db->prepare($sql);
        $stmt->execute([$id]);
        $service = $stmt->fetch();
        if (!$service) {
            json_error('Service not found', 404);
        }
        json_success('Service retrieved', ['service' => $service]);
    }

    if ($slug) {
        $sql = 'SELECT * FROM services WHERE slug = ?';
        if (!$admin) {
            $sql .= " AND publication_status = 'Published'";
        }
        $stmt = $db->prepare($sql);
        $stmt->execute([$slug]);
        $service = $stmt->fetch();
        if (!$service) {
            json_error('Service not found', 404);
        }
        json_success('Service retrieved', ['service' => $service]);
    }

    $sql = 'SELECT * FROM services';
    if (!$admin) {
        $sql .= " WHERE publication_status = 'Published'";
    }
    $sql .= ' ORDER BY display_order ASC, id ASC';

    $stmt = $db->query($sql);
    $services = $stmt->fetchAll();

    json_success('Services retrieved', [
        'services' => $services,
        'total'    => count($services)
    ]);
}

function handlePost(): void
{
    $admin = requireAuth(true);
    $data = read_json_body();

    if (!$data || !require_fields($data, ['title', 'slug', 'short_description', 'full_description'])) {
        json_error('Title, URL slug, short description, and full description are required.');
    }

    $title = trim((string)$data->title);
    $slug = strtolower(trim((string)$data->slug));
    $slug = preg_replace('/[^a-z0-9\-]/', '-', $slug);
    $shortDesc = trim((string)$data->short_description);
    $fullDesc = trim((string)$data->full_description);
    $imagePath = isset($data->image_path) ? trim((string)$data->image_path) : null;
    $features = isset($data->features) ? trim((string)$data->features) : null;
    $benefits = isset($data->benefits) ? trim((string)$data->benefits) : null;
    $status = in_array($data->publication_status ?? '', ['Draft', 'Published', 'Unpublished'], true)
        ? $data->publication_status : 'Published';
    $featured = !empty($data->featured) ? 1 : 0;
    $displayOrder = isset($data->display_order) ? (int)$data->display_order : 0;

    $db = db();

    $chk = $db->prepare('SELECT id FROM services WHERE slug = ?');
    $chk->execute([$slug]);
    if ($chk->fetch()) {
        json_error("A service with slug '{$slug}' already exists. Please choose a unique slug.");
    }

    $stmt = $db->prepare(
        'INSERT INTO services (title, slug, short_description, full_description, image_path, features, benefits, publication_status, featured, display_order)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
    );
    $stmt->execute([$title, $slug, $shortDesc, $fullDesc, $imagePath, $features, $benefits, $status, $featured, $displayOrder]);
    $id = (int)$db->lastInsertId();

    logAudit('CREATE_SERVICE', "Created service: {$title} (#{$id})", (int)$admin->id);
    json_success('Service created successfully.', ['id' => $id, 'slug' => $slug], 201);
}

function handlePut(): void
{
    $admin = requireAuth(true);
    $data = read_json_body();

    if (!$data || empty($data->id)) {
        json_error('Service ID is required.');
    }

    $id = (int)$data->id;
    $db = db();

    $updates = [];
    $params = [];

    if (isset($data->title)) { $updates[] = 'title = ?'; $params[] = trim((string)$data->title); }
    if (isset($data->slug)) {
        $slug = strtolower(trim((string)$data->slug));
        $slug = preg_replace('/[^a-z0-9\-]/', '-', $slug);
        $chk = $db->prepare('SELECT id FROM services WHERE slug = ? AND id != ?');
        $chk->execute([$slug, $id]);
        if ($chk->fetch()) {
            json_error("Slug '{$slug}' is already taken by another service.");
        }
        $updates[] = 'slug = ?'; $params[] = $slug;
    }
    if (isset($data->short_description)) { $updates[] = 'short_description = ?'; $params[] = trim((string)$data->short_description); }
    if (isset($data->full_description)) { $updates[] = 'full_description = ?'; $params[] = trim((string)$data->full_description); }
    if (isset($data->image_path)) { $updates[] = 'image_path = ?'; $params[] = trim((string)$data->image_path); }
    if (isset($data->features)) { $updates[] = 'features = ?'; $params[] = trim((string)$data->features); }
    if (isset($data->benefits)) { $updates[] = 'benefits = ?'; $params[] = trim((string)$data->benefits); }
    if (isset($data->publication_status)) {
        $st = in_array($data->publication_status, ['Draft', 'Published', 'Unpublished'], true) ? $data->publication_status : 'Published';
        $updates[] = 'publication_status = ?'; $params[] = $st;
    }
    if (isset($data->featured)) { $updates[] = 'featured = ?'; $params[] = !empty($data->featured) ? 1 : 0; }
    if (isset($data->display_order)) { $updates[] = 'display_order = ?'; $params[] = (int)$data->display_order; }

    if (empty($updates)) {
        json_error('No fields provided to update.');
    }

    $sql = 'UPDATE services SET ' . implode(', ', $updates) . ' WHERE id = ?';
    $params[] = $id;

    $stmt = $db->prepare($sql);
    $stmt->execute($params);

    logAudit('UPDATE_SERVICE', "Updated service #{$id}", (int)$admin->id);
    json_success('Service updated successfully.');
}

function handleDelete(): void
{
    $admin = requireAuth(true);
    $id = (int)($_GET['id'] ?? $_POST['id'] ?? 0);
    if ($id <= 0) {
        $data = read_json_body();
        if ($data && !empty($data->id)) {
            $id = (int)$data->id;
        }
    }

    if ($id <= 0) {
        json_error('Valid service ID required.');
    }

    $db = db();
    $stmt = $db->prepare('DELETE FROM services WHERE id = ?');
    $stmt->execute([$id]);

    logAudit('DELETE_SERVICE', "Deleted service #{$id}", (int)$admin->id);
    json_success('Service deleted successfully.');
}
