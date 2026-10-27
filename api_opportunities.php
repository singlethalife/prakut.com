<?php
/**
 * api_opportunities.php — Sales Pipeline & Opportunities API for Prakut.
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
    $stage = isset($_GET['stage']) ? trim((string)$_GET['stage']) : '';
    $id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

    if ($id > 0) {
        $stmt = $db->prepare(
            'SELECT o.*, c.full_name AS contact_name, c.phone AS contact_phone, f.name AS farm_name, u.name AS owner_name
             FROM opportunities o
             LEFT JOIN contacts c ON o.contact_id = c.id
             LEFT JOIN farms f ON o.farm_id = f.id
             LEFT JOIN users u ON o.owner_id = u.id
             WHERE o.id = ?'
        );
        $stmt->execute([$id]);
        $opp = $stmt->fetch();
        if (!$opp) {
            json_error('Opportunity not found.', 404);
        }
        json_success('Opportunity retrieved.', ['opportunity' => $opp]);
    }

    $sql = 'SELECT o.*, c.full_name AS contact_name, c.phone AS contact_phone, f.name AS farm_name, u.name AS owner_name
            FROM opportunities o
            LEFT JOIN contacts c ON o.contact_id = c.id
            LEFT JOIN farms f ON o.farm_id = f.id
            LEFT JOIN users u ON o.owner_id = u.id';
    $params = [];

    if ($stage !== '' && $stage !== 'All') {
        $sql .= ' WHERE o.stage = ?';
        $params[] = $stage;
    }

    $sql .= ' ORDER BY o.id DESC';
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $opps = $stmt->fetchAll();

    json_success('Opportunities retrieved.', ['opportunities' => $opps, 'total' => count($opps)]);
}

function handlePost(PDO $db, object $admin): void
{
    $data = read_json_body();
    if (!$data || !require_fields($data, ['name', 'service_type'])) {
        json_error('Opportunity name and service type are required.');
    }

    $year = date('Y');
    $count = (int)$db->query('SELECT COUNT(*) FROM opportunities')->fetchColumn() + 1;
    $oppNum = sprintf('OPP-%s-%04d', $year, $count);

    $name = trim((string)$data->name);
    $contactId = !empty($data->contact_id) ? (int)$data->contact_id : null;
    $farmId = !empty($data->farm_id) ? (int)$data->farm_id : null;
    $leadId = !empty($data->lead_id) ? (int)$data->lead_id : null;
    $service = trim((string)$data->service_type);
    $stage = isset($data->stage) ? trim((string)$data->stage) : 'discovery';
    $val = !empty($data->estimated_value) ? (float)$data->estimated_value : 0.00;
    $prob = !empty($data->probability) ? (int)$data->probability : 20;
    $close = !empty($data->expected_close_date) ? date('Y-m-d', strtotime($data->expected_close_date)) : null;
    $owner = !empty($data->owner_id) ? (int)$data->owner_id : (int)$admin->id;

    $stmt = $db->prepare(
        'INSERT INTO opportunities (opportunity_number, name, lead_id, contact_id, farm_id, service_type, stage, estimated_value, probability, expected_close_date, owner_id)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
    );
    $stmt->execute([$oppNum, $name, $leadId, $contactId, $farmId, $service, $stage, $val, $prob, $close, $owner]);
    $id = (int)$db->lastInsertId();

    logAudit('CREATE_OPPORTUNITY', "Created opportunity {$oppNum} ({$name})", (int)$admin->id);
    json_success('Opportunity created successfully.', ['id' => $id, 'opportunity_number' => $oppNum], 201);
}

function handlePut(PDO $db, object $admin): void
{
    $data = read_json_body();
    if (!$data || empty($data->id)) {
        json_error('Opportunity ID is required.');
    }

    $id = (int)$data->id;
    $validStages = ['discovery', 'assessment', 'proposal_preparation', 'proposal_sent', 'negotiation', 'won', 'lost'];

    $updates = [];
    $params = [];

    if (isset($data->stage)) {
        $st = trim((string)$data->stage);
        if (!in_array($st, $validStages, true)) {
            json_error('Invalid pipeline stage.');
        }
        $updates[] = 'stage = ?';
        $params[] = $st;
    }

    if (isset($data->estimated_value)) { $updates[] = 'estimated_value = ?'; $params[] = (float)$data->estimated_value; }
    if (isset($data->probability)) { $updates[] = 'probability = ?'; $params[] = (int)$data->probability; }
    if (isset($data->next_step)) { $updates[] = 'next_step = ?'; $params[] = trim((string)$data->next_step); }
    if (isset($data->loss_reason)) { $updates[] = 'loss_reason = ?'; $params[] = trim((string)$data->loss_reason); }

    if (empty($updates)) {
        json_error('No updates provided.');
    }

    $sql = 'UPDATE opportunities SET ' . implode(', ', $updates) . ' WHERE id = ?';
    $params[] = $id;

    $stmt = $db->prepare($sql);
    $stmt->execute($params);

    logAudit('UPDATE_OPPORTUNITY', "Updated opportunity #{$id}", (int)$admin->id);
    json_success('Opportunity updated successfully.');
}
