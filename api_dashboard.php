<?php
/**
 * api_dashboard.php — Administrative Dashboard Statistics & Summaries for Prakut.
 * Modeled after Zoloverse architecture. Computes live metrics from database tables.
 */

declare(strict_types=1);
require_once __DIR__ . '/includes/init.php';

$admin = requireAuth(true);
$db = db();

// 1. KPI Counts
$stats = [
    'leads_total'         => (int)$db->query('SELECT COUNT(*) FROM leads')->fetchColumn(),
    'leads_today'         => (int)$db->query('SELECT COUNT(*) FROM leads WHERE DATE(created_at) = CURDATE()')->fetchColumn(),
    'leads_new'           => (int)$db->query("SELECT COUNT(*) FROM leads WHERE status = 'new'")->fetchColumn(),
    'leads_contacted'     => (int)$db->query("SELECT COUNT(*) FROM leads WHERE status IN ('contacted', 'attempting_contact')")->fetchColumn(),
    'leads_qualified'     => (int)$db->query("SELECT COUNT(*) FROM leads WHERE status = 'qualified'")->fetchColumn(),
    'leads_converted'     => (int)$db->query("SELECT COUNT(*) FROM leads WHERE status = 'converted'")->fetchColumn(),
    'audits_total'        => (int)$db->query('SELECT COUNT(*) FROM audit_requests')->fetchColumn(),
    'audits_requested'    => (int)$db->query("SELECT COUNT(*) FROM audit_requests WHERE status = 'requested'")->fetchColumn(),
    'audits_scheduled'    => (int)$db->query("SELECT COUNT(*) FROM audit_requests WHERE status = 'scheduled'")->fetchColumn(),
    'audits_completed'    => (int)$db->query("SELECT COUNT(*) FROM audit_requests WHERE status = 'completed'")->fetchColumn(),
    'contacts_total'      => (int)$db->query('SELECT COUNT(*) FROM contacts')->fetchColumn(),
    'farms_total'         => (int)$db->query('SELECT COUNT(*) FROM farms')->fetchColumn(),
    'opportunities_open'  => (int)$db->query("SELECT COUNT(*) FROM opportunities WHERE stage NOT IN ('won', 'lost')")->fetchColumn(),
    'tasks_due'           => (int)$db->query('SELECT COUNT(*) FROM activities WHERE completed_at IS NULL AND due_at <= NOW()')->fetchColumn(),
];

// 2. Recent Leads
$stmt = $db->query(
    'SELECT l.id, l.lead_number, l.full_name, l.phone, l.email, l.service_interest, l.farm_location, l.farm_size, l.status, l.priority, l.created_at, u.name AS assigned_user
     FROM leads l
     LEFT JOIN users u ON l.assigned_to = u.id
     ORDER BY l.id DESC LIMIT 6'
);
$recentLeads = $stmt->fetchAll();

// 3. Recent Farm Audit Requests
$stmt = $db->query(
    'SELECT a.id, a.audit_number, a.requested_service, a.farm_location, a.farm_size, a.status, a.appointment_at, a.assigned_auditor, a.created_at, c.full_name AS contact_name, c.phone AS contact_phone
     FROM audit_requests a
     LEFT JOIN contacts c ON a.contact_id = c.id
     ORDER BY a.id DESC LIMIT 6'
);
$recentAudits = $stmt->fetchAll();

// 4. Recent Activities / Follow-up Tasks
$stmt = $db->query(
    'SELECT act.id, act.type, act.subject, act.due_at, act.completed_at, l.lead_number, l.full_name AS lead_name
     FROM activities act
     LEFT JOIN leads l ON act.lead_id = l.id
     WHERE act.completed_at IS NULL
     ORDER BY act.due_at ASC LIMIT 6'
);
$pendingTasks = $stmt->fetchAll();

// 5. Recent System Audit Logs
$stmt = $db->query(
    'SELECT id, action, details, ip_address, created_at
     FROM audit_logs
     ORDER BY id DESC LIMIT 6'
);
$recentLogs = $stmt->fetchAll();

json_success('Dashboard statistics retrieved successfully.', [
    'stats'         => $stats,
    'recent_leads'  => $recentLeads,
    'recent_audits' => $recentAudits,
    'pending_tasks' => $pendingTasks,
    'recent_logs'   => $recentLogs,
]);
