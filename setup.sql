-- Prakut Farm Transformation Platform: CRM & CMS Database Schema
-- Architecture modeled for PHP/MySQL on Apache/XAMPP

CREATE DATABASE IF NOT EXISTS prakut_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE prakut_db;

-- 1. Users / Staff & Administrators
CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    email VARCHAR(150) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    phone VARCHAR(30) DEFAULT NULL,
    role ENUM('admin', 'manager', 'sales', 'auditor', 'editor') DEFAULT 'admin',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 2. Landowner & Client Contacts
CREATE TABLE IF NOT EXISTS contacts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    full_name VARCHAR(150) NOT NULL,
    phone VARCHAR(50) NOT NULL,
    email VARCHAR(150) NOT NULL,
    organization_name VARCHAR(150) DEFAULT NULL,
    job_title VARCHAR(100) DEFAULT NULL,
    preferred_contact_method VARCHAR(50) DEFAULT 'Phone',
    notes TEXT DEFAULT NULL,
    status ENUM('active', 'inactive', 'archived') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    KEY idx_contact_phone (phone),
    KEY idx_contact_email (email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 3. Farms & Agricultural Assets
CREATE TABLE IF NOT EXISTS farms (
    id INT AUTO_INCREMENT PRIMARY KEY,
    contact_id INT DEFAULT NULL,
    name VARCHAR(150) DEFAULT NULL,
    area_value DECIMAL(10,2) DEFAULT NULL,
    area_unit VARCHAR(50) DEFAULT 'acre',
    address TEXT DEFAULT NULL,
    district VARCHAR(100) DEFAULT NULL,
    state VARCHAR(100) DEFAULT NULL,
    current_crops TEXT DEFAULT NULL,
    infrastructure_summary TEXT DEFAULT NULL,
    notes TEXT DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (contact_id) REFERENCES contacts(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 4. CRM Leads (Captured from website index.html and other public forms)
CREATE TABLE IF NOT EXISTS leads (
    id INT AUTO_INCREMENT PRIMARY KEY,
    lead_number VARCHAR(50) NOT NULL UNIQUE,
    full_name VARCHAR(150) NOT NULL,
    phone VARCHAR(50) NOT NULL,
    email VARCHAR(150) NOT NULL,
    contact_id INT DEFAULT NULL,
    farm_id INT DEFAULT NULL,
    source VARCHAR(100) DEFAULT 'Website',
    source_page VARCHAR(255) DEFAULT '/index.html',
    form_type VARCHAR(100) DEFAULT 'HOME_ASSESSMENT',
    service_interest VARCHAR(150) DEFAULT NULL,
    farm_location VARCHAR(200) DEFAULT NULL,
    farm_size VARCHAR(100) DEFAULT NULL,
    current_crop VARCHAR(200) DEFAULT NULL,
    message TEXT DEFAULT NULL,
    status ENUM('new', 'attempting_contact', 'contacted', 'qualified', 'nurture', 'converted', 'disqualified', 'closed_lost') DEFAULT 'new',
    priority ENUM('Low', 'Medium', 'High') DEFAULT 'Medium',
    assigned_to INT DEFAULT NULL,
    admin_notes TEXT DEFAULT NULL,
    closed_reason VARCHAR(255) DEFAULT NULL,
    duplicate_of INT DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    KEY idx_lead_status (status),
    KEY idx_lead_created (created_at),
    FOREIGN KEY (contact_id) REFERENCES contacts(id) ON DELETE SET NULL,
    FOREIGN KEY (farm_id) REFERENCES farms(id) ON DELETE SET NULL,
    FOREIGN KEY (assigned_to) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 5. Farm Health Audit Requests & Schedules
CREATE TABLE IF NOT EXISTS audit_requests (
    id INT AUTO_INCREMENT PRIMARY KEY,
    audit_number VARCHAR(50) NOT NULL UNIQUE,
    lead_id INT DEFAULT NULL,
    contact_id INT DEFAULT NULL,
    farm_id INT DEFAULT NULL,
    requested_service VARCHAR(150) DEFAULT 'Farm Health Audit',
    farm_location VARCHAR(200) DEFAULT NULL,
    farm_size VARCHAR(100) DEFAULT NULL,
    current_crop VARCHAR(200) DEFAULT NULL,
    appointment_at DATETIME DEFAULT NULL,
    status ENUM('requested', 'contact_pending', 'scheduled', 'in_progress', 'report_pending', 'completed', 'cancelled') DEFAULT 'requested',
    assigned_auditor VARCHAR(100) DEFAULT NULL,
    report_status ENUM('Not Started', 'In Progress', 'Ready', 'Delivered') DEFAULT 'Not Started',
    location_notes TEXT DEFAULT NULL,
    internal_notes TEXT DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    KEY idx_audit_status (status),
    FOREIGN KEY (lead_id) REFERENCES leads(id) ON DELETE CASCADE,
    FOREIGN KEY (contact_id) REFERENCES contacts(id) ON DELETE SET NULL,
    FOREIGN KEY (farm_id) REFERENCES farms(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 6. Sales Opportunities & Pipeline
CREATE TABLE IF NOT EXISTS opportunities (
    id INT AUTO_INCREMENT PRIMARY KEY,
    opportunity_number VARCHAR(50) NOT NULL UNIQUE,
    name VARCHAR(200) NOT NULL,
    lead_id INT DEFAULT NULL,
    contact_id INT DEFAULT NULL,
    farm_id INT DEFAULT NULL,
    service_type VARCHAR(150) NOT NULL,
    stage ENUM('discovery', 'assessment', 'proposal_preparation', 'proposal_sent', 'negotiation', 'won', 'lost') DEFAULT 'discovery',
    estimated_value DECIMAL(12,2) DEFAULT 0.00,
    currency VARCHAR(10) DEFAULT 'INR',
    probability INT DEFAULT 20,
    expected_close_date DATE DEFAULT NULL,
    owner_id INT DEFAULT NULL,
    next_step VARCHAR(255) DEFAULT NULL,
    loss_reason VARCHAR(255) DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (contact_id) REFERENCES contacts(id) ON DELETE SET NULL,
    FOREIGN KEY (farm_id) REFERENCES farms(id) ON DELETE SET NULL,
    FOREIGN KEY (owner_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 7. Activities & Tasks
CREATE TABLE IF NOT EXISTS activities (
    id INT AUTO_INCREMENT PRIMARY KEY,
    type ENUM('call', 'email', 'meeting', 'site_visit', 'note', 'task') DEFAULT 'task',
    subject VARCHAR(200) NOT NULL,
    description TEXT DEFAULT NULL,
    lead_id INT DEFAULT NULL,
    contact_id INT DEFAULT NULL,
    farm_id INT DEFAULT NULL,
    opportunity_id INT DEFAULT NULL,
    audit_request_id INT DEFAULT NULL,
    assigned_to INT DEFAULT NULL,
    due_at DATETIME DEFAULT NULL,
    completed_at DATETIME DEFAULT NULL,
    outcome VARCHAR(255) DEFAULT NULL,
    created_by INT DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (lead_id) REFERENCES leads(id) ON DELETE CASCADE,
    FOREIGN KEY (assigned_to) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 8. CMS Website Dynamic Content (Editable by Admin)
CREATE TABLE IF NOT EXISTS website_content (
    section_key VARCHAR(100) NOT NULL PRIMARY KEY,
    content_json LONGTEXT NOT NULL,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 9. Audit Logs
CREATE TABLE IF NOT EXISTS audit_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT DEFAULT NULL,
    action VARCHAR(100) NOT NULL,
    details TEXT DEFAULT NULL,
    ip_address VARCHAR(45) DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------- Seed Default Admin User ----------------
-- Password is 'admin123'
INSERT INTO users (name, email, password, role, phone)
VALUES ('Prakut Administrator', 'admin@prakut.com', '$2y$10$FHx4nB.6zqM.8RcGnQSEh.eqF9QOBchZx.uMd./p2G3oUBwVPcW92', 'admin', '+91 98843 06922')
ON DUPLICATE KEY UPDATE name = VALUES(name);

-- ---------------- Seed Initial CMS Content for index.html ----------------

-- Homepage Content Section
INSERT INTO website_content (section_key, content_json)
VALUES ('home', '{
    "hero_eyebrow": "Farm Transformation Partner",
    "hero_title": "Your Farm Is An Asset. We Turn It Into A Business.",
    "hero_sub": "Prakut is a farm transformation company helping landowners, farm businesses, and agricultural enterprises improve farm operations.",
    "hero_tagline": "Cultivating Value.",
    "cta_primary_label": "Get a Farm Health Audit",
    "cta_primary_link": "farm-health-audit.html",
    "cta_secondary_label": "Talk to Prakut",
    "cta_secondary_link": "contact.html",
    "stats": [
        { "counter": 500, "label": "Acres Assessed" },
        { "counter": 50, "label": "Farms Supported" },
        { "counter": 20, "label": "Transformation Projects" },
        { "counter": 6, "label": "Transformation Areas" }
    ],
    "problem_eyebrow": "The Problem",
    "problem_title": "Owning farmland is easy. Running it well isn\'t.",
    "problem_pull": "The result? A valuable agricultural asset can remain an underperforming business. That\'s where Prakut comes in.",
    "story_eyebrow": "From Land To Business",
    "story_title": "A clearer path from potential to performance.",
    "story_desc": "Prakut brings land, operations, people, data, crops and revenue into one connected view. The result is a farm that can be understood, managed and improved with intention.",
    "audit_banner_eyebrow": "Start Here",
    "audit_banner_title": "Know the health of your farm before you invest another rupee.",
    "audit_banner_desc": "An independent assessment of your farm\'s land, water, soil, crops, infrastructure, operations, economics, risks, and opportunities — before you commit further capital or time.",
    "framework_eyebrow": "The Prakut Farm Transformation Framework",
    "framework_title": "The goal isn\'t a more complicated farm. It\'s a more predictable one.",
    "final_cta_eyebrow": "Start With Clarity",
    "final_cta_title": "Ready to understand what your farm can become?",
    "final_cta_desc": "Start with a Farm Health Audit and identify the opportunities hiding in your farm."
}')
ON DUPLICATE KEY UPDATE content_json = VALUES(content_json);

-- FAQ Content Section
INSERT INTO website_content (section_key, content_json)
VALUES ('faqs', '[
    {
        "id": 1,
        "question": "What exactly does Prakut do?",
        "answer": "Prakut transforms farms into income-generating businesses. We diagnose the farm\'s condition, design a plan around it, execute the operations on the ground, and work to grow revenue and profitability from there."
    },
    {
        "id": 2,
        "question": "How is Prakut different from consultants or agronomists?",
        "answer": "Consultants typically hand over recommendations and step away. Prakut stays for execution — we run the operation and are accountable for the outcome, not just the advice."
    },
    {
        "id": 3,
        "question": "What happens after the Farm Health Audit?",
        "answer": "You receive a written assessment covering land, infrastructure, crops, water, operations, economics, and risk, along with a prioritised action plan. Whether you move into Farm Transformation or Farm Operations from there depends on your goals and the farm\'s fit — there\'s no pressure to continue."
    },
    {
        "id": 4,
        "question": "Why is the audit paid?",
        "answer": "A serious asset decision deserves a serious assessment. A paid audit requires structured fieldwork and economic analysis, keeps both sides aligned, and filters out casual enquiries."
    },
    {
        "id": 5,
        "question": "Do you guarantee returns or income?",
        "answer": "No. Prakut does not offer guaranteed returns. We focus on building structurally sound farm economics, which improves predictability and long-term performance — not short-term assurances."
    },
    {
        "id": 6,
        "question": "Do I need to travel to India to get started?",
        "answer": "No. The audit and ongoing engagement are designed to run without requiring your physical presence, though you\'re welcome to visit anytime."
    }
]')
ON DUPLICATE KEY UPDATE content_json = VALUES(content_json);

-- General Site Settings Section
INSERT INTO website_content (section_key, content_json)
VALUES ('settings', '{
    "site_name": "Prakut",
    "tagline": "Cultivating Value.",
    "contact_phone": "+91 98843 06922",
    "contact_email": "contact@prakut.com",
    "contact_location": "Tambaram, Chennai, Tamil Nadu",
    "whatsapp": "+91 98843 06922",
    "instagram": "https://www.instagram.com/prakutagritech",
    "linkedin": "https://www.linkedin.com/company/prakut/",
    "copyright": "© 2026 Prakut. All rights reserved."
}')
ON DUPLICATE KEY UPDATE content_json = VALUES(content_json);
