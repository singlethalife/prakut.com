<?php
/**
 * api_enquiry.php — Public CRM Enquiry Intake API for Prakut.
 * Handles submissions from index.html (homeAuditForm, footerNewsletterForm)
 * and other website forms (contactForm, auditForm, prakutOneForm).
 * Modeled after Zoloverse architecture.
 */

declare(strict_types=1);
require_once __DIR__ . '/includes/init.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_error('Method Not Allowed. POST is required.', 405);
}

handleEnquiry();

function handleEnquiry(): void
{
    $data = read_json_body();
    if (!$data) {
        // Fallback to standard POST form data if JSON wasn't sent
        $data = (object)$_POST;
    }

    // 1. Anti-spam honeypot check
    if (!empty($data->website_hp)) {
        // Silently accept bots without saving
        json_success("Thank you. We've received your details and will get back to you shortly.", [
            'data' => [
                'reference' => 'PRK-' . date('Y') . '-SPAM',
                'status'    => 'received'
            ]
        ], 200);
        return;
    }

    $formType = trim((string)($data->form_type ?? 'HOME_ASSESSMENT'));
    $sourcePage = trim((string)($data->source_page ?? '/index.html'));

    // Handle newsletter form specifically
    if ($formType === 'NEWSLETTER_SIGNUP' || (!empty($data->action) && $data->action === 'newsletter')) {
        handleNewsletterSignup($data, $sourcePage);
        return;
    }

    // 2. Validate essential fields for assessment & enquiry forms (supports direct or nested fields)
    $fields = [];
    if (!empty($data->fields)) {
        $fields = is_array($data->fields) ? $data->fields : (array)$data->fields;
    }
    $getVal = function(string $key, string $default = '') use ($data, $fields): string {
        if (isset($data->$key) && (string)$data->$key !== '') {
            return trim((string)$data->$key);
        }
        if (isset($fields[$key]) && (string)$fields[$key] !== '') {
            return trim((string)$fields[$key]);
        }
        return $default;
    };

    $name     = $getVal('name', $getVal('full_name'));
    $email    = strtolower($getVal('email'));
    $phone    = $getVal('phone');
    $location = $getVal('location', $getVal('farm_location'));
    $size     = $getVal('size', $getVal('farm_size'));
    $crop     = $getVal('crop', $getVal('current_crop'));
    $service  = $getVal('help', $getVal('service_interest', 'Farm Health Audit'));
    $message  = $getVal('message');

    if ($name === '' || $email === '' || $phone === '') {
        json_error('Please provide your name, email address, and phone number.');
    }

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        json_error('Please provide a valid email address.');
    }

    $db = db();

    // 3. Rate limiting check: maximum 5 submissions per email/IP in 1 hour
    $rateStmt = $db->prepare('SELECT COUNT(*) FROM leads WHERE email = ? AND created_at > DATE_SUB(NOW(), INTERVAL 1 HOUR)');
    $rateStmt->execute([$email]);
    if ((int)$rateStmt->fetchColumn() >= 5) {
        json_error('You have reached the submission limit for this hour. Please try again later or contact us directly at +91 98843 06922.', 429);
    }

    // 4. Generate unique Lead and Audit reference numbers
    $year = date('Y');
    $leadCount = (int)$db->query('SELECT COUNT(*) FROM leads')->fetchColumn() + 1;
    $leadNumber = sprintf('PRK-%s-%04d', $year, $leadCount);

    $db->beginTransaction();

    try {
        // Step A: Find existing contact or create new
        $contactStmt = $db->prepare('SELECT id FROM contacts WHERE email = ? OR phone = ? LIMIT 1');
        $contactStmt->execute([$email, $phone]);
        $contactId = $contactStmt->fetchColumn();

        if (!$contactId) {
            $insContact = $db->prepare('INSERT INTO contacts (full_name, phone, email, notes) VALUES (?, ?, ?, ?)');
            $insContact->execute([$name, $phone, $email, "Created via website {$sourcePage} ({$formType})"]);
            $contactId = (int)$db->lastInsertId();
        } else {
            $contactId = (int)$contactId;
        }

        // Step B: Create Farm record if location or size provided
        $farmId = null;
        if ($location !== '' || $size !== '' || $crop !== '') {
            // Extract numeric area if possible
            preg_match('/([\d\.]+)/', $size, $matches);
            $areaVal = !empty($matches[1]) ? (float)$matches[1] : null;

            $insFarm = $db->prepare(
                'INSERT INTO farms (contact_id, name, area_value, address, current_crops, notes)
                 VALUES (?, ?, ?, ?, ?, ?)'
            );
            $farmName = "{$name}'s Farm" . ($location ? " ({$location})" : '');
            $insFarm->execute([$contactId, $farmName, $areaVal, $location, $crop, "Size note: {$size}"]);
            $farmId = (int)$db->lastInsertId();
        }

        // Step C: Insert Lead record
        $insLead = $db->prepare(
            'INSERT INTO leads (lead_number, full_name, phone, email, contact_id, farm_id, source, source_page, form_type, service_interest, farm_location, farm_size, current_crop, message, status)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
        );
        $insLead->execute([
            $leadNumber,
            $name,
            $phone,
            $email,
            $contactId,
            $farmId,
            'Website',
            $sourcePage,
            $formType,
            $service,
            $location,
            $size,
            $crop,
            $message,
            'new'
        ]);
        $leadId = (int)$db->lastInsertId();

        // Step D: If this is an audit request, create an audit_requests record
        $auditNumber = null;
        if (stripos($service, 'Audit') !== false || $formType === 'FARM_HEALTH_AUDIT') {
            $auditCount = (int)$db->query('SELECT COUNT(*) FROM audit_requests')->fetchColumn() + 1;
            $auditNumber = sprintf('AUD-%s-%04d', $year, $auditCount);

            $insAudit = $db->prepare(
                'INSERT INTO audit_requests (audit_number, lead_id, contact_id, farm_id, requested_service, farm_location, farm_size, current_crop, status)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)'
            );
            $insAudit->execute([
                $auditNumber,
                $leadId,
                $contactId,
                $farmId,
                $service,
                $location,
                $size,
                $crop,
                'requested'
            ]);
        }

        // Step E: Create initial activity/task
        $insAct = $db->prepare(
            'INSERT INTO activities (type, subject, description, lead_id, contact_id, farm_id, due_at)
             VALUES (?, ?, ?, ?, ?, ?, DATE_ADD(NOW(), INTERVAL 24 HOUR))'
        );
        $insAct->execute([
            'task',
            "Initial contact follow-up for {$name}",
            "Website submission received for '{$service}'. Review farm details ({$location}, {$size}) and call lead.",
            $leadId,
            $contactId,
            $farmId
        ]);

        $db->commit();

        logAudit('NEW_LEAD', "Captured new enquiry {$leadNumber} from {$email} for {$service}");

        $responseRef = $auditNumber ?: $leadNumber;
        json_success("Thank you. We've received your details and will get back to you shortly.", [
            'data' => [
                'reference'    => $responseRef,
                'lead_number'  => $leadNumber,
                'audit_number' => $auditNumber,
                'status'       => 'received'
            ]
        ], 201);
    } catch (\Throwable $e) {
        $db->rollBack();
        error_log('[prakut] Lead intake failed: ' . $e->getMessage());
        json_error('We could not save your submission due to a temporary issue. Please call +91 98843 06922 directly.', 500);
    }
}

function handleNewsletterSignup(object $data, string $sourcePage): void
{
    $email = strtolower(trim((string)($data->email ?? '')));

    if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        json_error('Please provide a valid email address for notifications.');
    }

    $db = db();

    // Check if lead already exists for newsletter
    $chkStmt = $db->prepare("SELECT lead_number FROM leads WHERE email = ? AND form_type = 'NEWSLETTER_SIGNUP' LIMIT 1");
    $chkStmt->execute([$email]);
    $existing = $chkStmt->fetchColumn();

    if ($existing) {
        json_success("Thank you! You are already subscribed to updates from Prakut.", [
            'data' => ['reference' => $existing, 'status' => 'subscribed']
        ]);
        return;
    }

    $year = date('Y');
    $leadCount = (int)$db->query('SELECT COUNT(*) FROM leads')->fetchColumn() + 1;
    $leadNumber = sprintf('PRK-%s-%04d', $year, $leadCount);

    $stmt = $db->prepare(
        'INSERT INTO leads (lead_number, full_name, phone, email, source, source_page, form_type, service_interest, status)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)'
    );
    $stmt->execute([
        $leadNumber,
        'Newsletter Subscriber',
        '',
        $email,
        'Website',
        $sourcePage,
        'NEWSLETTER_SIGNUP',
        'Email Updates & Newsletter',
        'nurture'
    ]);

    logAudit('NEWSLETTER_SIGNUP', "New newsletter subscription: {$email}");

    json_success("Thank you! You've been subscribed to Prakut updates.", [
        'data' => [
            'reference' => $leadNumber,
            'status'    => 'subscribed'
        ]
    ], 201);
}
