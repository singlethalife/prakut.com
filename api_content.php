<?php
/**
 * api_content.php — Dynamic Website Content API for Prakut CMS.
 * Manages Home Page, FAQs, and Site Settings editable from the Admin Panel.
 * Modeled after Zoloverse architecture.
 */

declare(strict_types=1);
require_once __DIR__ . '/includes/init.php';

$method = $_SERVER['REQUEST_METHOD'];
if ($method === 'POST' && (($_GET['action'] ?? '') === 'save' || ($_POST['_method'] ?? '') === 'PUT')) {
    $method = 'PUT';
}

switch ($method) {
    case 'GET':
        handleGet();
        break;
    case 'POST':
    case 'PUT':
        handlePut();
        break;
    default:
        json_error('Method Not Allowed', 405);
}

function handleGet(): void
{
    $section = isset($_GET['section']) ? trim((string)$_GET['section']) : 'all';
    $db = db();

    $defaultSettings = (object)[
        'site_name'        => 'Prakut',
        'tagline'          => 'Cultivating Value.',
        'contact_phone'    => '+91 98843 06922',
        'contact_email'    => 'contact@prakut.com',
        'contact_location' => 'Tambaram, Chennai, Tamil Nadu',
        'whatsapp'         => '+91 98843 06922',
        'instagram'        => 'https://www.instagram.com/prakutagritech',
        'linkedin'         => 'https://www.linkedin.com/company/prakut/',
        'copyright'        => '© 2026 Prakut. All rights reserved.'
    ];

    if ($section !== 'all') {
        $stmt = $db->prepare('SELECT section_key, content_json, updated_at FROM website_content WHERE section_key = ?');
        $stmt->execute([$section]);
        $row = $stmt->fetch();

        if (!$row) {
            if ($section === 'settings') {
                json_success('Default settings loaded', [
                    'section'    => $section,
                    'content'    => $defaultSettings,
                    'updated_at' => date('Y-m-d H:i:s')
                ]);
            }
            json_error("Content section '{$section}' not found.", 404);
        }

        $data = json_decode($row['content_json']);
        if ($section === 'settings') {
            $data = (object)array_merge((array)$defaultSettings, (array)$data);
        }

        json_success('Content loaded successfully.', [
            'section'    => $section,
            'content'    => $data,
            'updated_at' => $row['updated_at']
        ]);
    } else {
        $stmt = $db->query('SELECT section_key, content_json, updated_at FROM website_content');
        $rows = $stmt->fetchAll();
        $sections = [];

        foreach ($rows as $r) {
            $sections[$r['section_key']] = json_decode($r['content_json']);
        }

        if (!isset($sections['settings'])) {
            $sections['settings'] = $defaultSettings;
        } else {
            $sections['settings'] = (object)array_merge((array)$defaultSettings, (array)$sections['settings']);
        }

        json_success('All content sections loaded.', ['sections' => $sections]);
    }
}

function handlePut(): void
{
    $admin = requireAuth(true);
    $data = read_json_body();

    if (!$data || empty($data->section_key) || !isset($data->content)) {
        json_error('Section key and content are required.');
    }

    $sectionKey = trim((string)$data->section_key);
    $validSections = ['home', 'faqs', 'settings', 'services', 'about'];
    if (!in_array($sectionKey, $validSections, true)) {
        json_error('Invalid content section key specified.');
    }

    $contentJson = json_encode($data->content, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    if (!$contentJson) {
        json_error('Failed to encode content as valid JSON.');
    }

    $db = db();
    $stmt = $db->prepare(
        'INSERT INTO website_content (section_key, content_json)
         VALUES (?, ?)
         ON DUPLICATE KEY UPDATE content_json = VALUES(content_json), updated_at = NOW()'
    );
    $stmt->execute([$sectionKey, $contentJson]);

    logAudit('UPDATE_CMS_CONTENT', "Updated website content for section: {$sectionKey}", (int)$admin->id);
    json_success("Website {$sectionKey} content updated successfully.", [
        'section_key' => $sectionKey,
        'updated_at'  => date('Y-m-d H:i:s')
    ]);
}
