-- Platform Master Database Schema (Consolidated)

CREATE TABLE IF NOT EXISTS tenants (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    subdomain VARCHAR(50) NOT NULL UNIQUE,
    custom_domain VARCHAR(100) NULL UNIQUE,
    db_name VARCHAR(64) NOT NULL, -- No longer unique in shared architecture
    db_user VARCHAR(64) NOT NULL,
    db_password VARCHAR(255) NOT NULL,
    status ENUM('active', 'suspended') DEFAULT 'active',
    subscription_expires_at DATETIME NOT NULL,
    request_count INT DEFAULT 0,
    bandwidth_usage BIGINT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS super_admins (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Tenant Shared Tables

CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    tenant_id INT NOT NULL,
    username VARCHAR(50) NOT NULL,
    password VARCHAR(255) NOT NULL,
    role ENUM('admin', 'student', 'manager') NOT NULL DEFAULT 'student',
    grade_id INT NULL,
    student_id VARCHAR(10) NULL,
    photo VARCHAR(255) NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE(tenant_id, username),
    INDEX(tenant_id)
);

CREATE TABLE IF NOT EXISTS settings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    tenant_id INT NOT NULL,
    setting_key VARCHAR(50) NOT NULL,
    setting_value TEXT NULL,
    UNIQUE(tenant_id, setting_key),
    INDEX(tenant_id)
);

CREATE TABLE IF NOT EXISTS grades (
    id INT AUTO_INCREMENT PRIMARY KEY,
    tenant_id INT NOT NULL,
    name VARCHAR(100) NOT NULL,
    INDEX(tenant_id)
);

CREATE TABLE IF NOT EXISTS subjects (
    id INT AUTO_INCREMENT PRIMARY KEY,
    tenant_id INT NOT NULL,
    grade_id INT NOT NULL,
    name VARCHAR(100) NOT NULL,
    description TEXT,
    price DECIMAL(10,2) DEFAULT 0.00,
    INDEX(tenant_id),
    INDEX(grade_id)
);

CREATE TABLE IF NOT EXISTS units (
    id INT AUTO_INCREMENT PRIMARY KEY,
    tenant_id INT NOT NULL,
    subject_id INT NOT NULL,
    name VARCHAR(255) NOT NULL,
    INDEX(tenant_id),
    INDEX(subject_id)
);

CREATE TABLE IF NOT EXISTS materials (
    id INT AUTO_INCREMENT PRIMARY KEY,
    tenant_id INT NOT NULL,
    unit_id INT NOT NULL,
    title VARCHAR(255) NOT NULL,
    content_type VARCHAR(50), 
    content_url TEXT,
    INDEX(tenant_id),
    INDEX(unit_id)
);

CREATE TABLE IF NOT EXISTS classes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    tenant_id INT NOT NULL,
    subject_id INT NOT NULL,
    title VARCHAR(255) NOT NULL,
    start_time DATETIME NOT NULL,
    class_type ENUM('physical', 'online', 'hybrid') NOT NULL DEFAULT 'online',
    zoom_link TEXT NULL,
    image VARCHAR(255) NULL,
    notes_pdf VARCHAR(255) NULL,
    INDEX(tenant_id),
    INDEX(subject_id),
    INDEX idx_subject_time (subject_id, start_time)
);

CREATE TABLE IF NOT EXISTS attendance (
    id INT AUTO_INCREMENT PRIMARY KEY,
    tenant_id INT NOT NULL,
    class_id INT NOT NULL,
    user_id INT NOT NULL,
    attended_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    method ENUM('qr_scan', 'link_click', 'manual') NOT NULL,
    status ENUM('present', 'late', 'excused') DEFAULT 'present',
    UNIQUE(tenant_id, class_id, user_id),
    INDEX(tenant_id),
    INDEX(class_id),
    INDEX(user_id)
);

CREATE TABLE IF NOT EXISTS recordings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    tenant_id INT NOT NULL,
    subject_id INT NOT NULL,
    title VARCHAR(255) NOT NULL,
    youtube_id VARCHAR(50) NOT NULL,
    image VARCHAR(255) NULL,
    notes_pdf VARCHAR(255) NULL,
    INDEX(tenant_id),
    INDEX(subject_id)
);

CREATE TABLE IF NOT EXISTS past_papers (
    id INT AUTO_INCREMENT PRIMARY KEY,
    tenant_id INT NOT NULL,
    subject_id INT NOT NULL,
    title VARCHAR(255) NOT NULL,
    pdf_path VARCHAR(255) NOT NULL,
    deadline DATETIME NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX(tenant_id),
    INDEX(subject_id)
);

CREATE TABLE IF NOT EXISTS paper_submissions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    tenant_id INT NOT NULL,
    paper_id INT NOT NULL,
    user_id INT NOT NULL,
    submission_path VARCHAR(255) NOT NULL,
    submitted_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    marks INT DEFAULT NULL,
    marked_pdf_path VARCHAR(255) DEFAULT NULL,
    status ENUM('submitted', 'marked') DEFAULT 'submitted',
    INDEX(tenant_id),
    INDEX(paper_id),
    INDEX(user_id),
    INDEX idx_user_paper (user_id, paper_id)
);

CREATE TABLE IF NOT EXISTS payments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    tenant_id INT NOT NULL,
    user_id INT NOT NULL,
    subject_id INT NOT NULL,
    proof_image VARCHAR(255) NOT NULL,
    status ENUM('pending', 'approved', 'rejected') DEFAULT 'pending',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX(tenant_id),
    INDEX(user_id),
    INDEX(subject_id),
    INDEX idx_user_subject (user_id, subject_id)
);

CREATE TABLE IF NOT EXISTS notifications (
    id INT AUTO_INCREMENT PRIMARY KEY,
    tenant_id INT NOT NULL,
    user_id INT NOT NULL,
    message TEXT NOT NULL,
    is_read TINYINT(1) DEFAULT 0,
    is_cleared TINYINT(1) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX(tenant_id),
    INDEX(user_id)
);

CREATE TABLE IF NOT EXISTS manager_permissions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    tenant_id INT NOT NULL,
    user_id INT NOT NULL,
    can_manage_attendance TINYINT(1) DEFAULT 0,
    can_manage_students TINYINT(1) DEFAULT 0,
    can_manage_payments TINYINT(1) DEFAULT 0,
    can_manage_scheduling TINYINT(1) DEFAULT 0,
    UNIQUE(tenant_id, user_id),
    INDEX(tenant_id),
    INDEX(user_id)
);
