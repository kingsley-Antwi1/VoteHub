-- VoteHub Database Schema
CREATE DATABASE IF NOT EXISTS votehub DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE votehub;

-- Super Admin
CREATE TABLE super_admin (
    id INT PRIMARY KEY AUTO_INCREMENT,
    username VARCHAR(50) NOT NULL UNIQUE,
    email VARCHAR(100) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Subscription Plans
CREATE TABLE subscription_plans (
    id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(50) NOT NULL,
    max_elections INT NOT NULL DEFAULT 1,
    max_voters INT NOT NULL DEFAULT 100,
    max_candidates INT NOT NULL DEFAULT 50,
    custom_branding TINYINT(1) DEFAULT 0,
    priority_support TINYINT(1) DEFAULT 0,
    price DECIMAL(10,2) NOT NULL,
    duration_days INT NOT NULL DEFAULT 365,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Institutions
CREATE TABLE institutions (
    id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(200) NOT NULL,
    slug VARCHAR(100) NOT NULL UNIQUE,
    type ENUM('shs','university','other') NOT NULL DEFAULT 'university',
    location VARCHAR(255),
    contact_email VARCHAR(100),
    contact_phone VARCHAR(20),
    logo VARCHAR(255),
    banner VARCHAR(255),
    primary_color VARCHAR(7) DEFAULT '#1a1a2e',
    about TEXT,
    status ENUM('pending','active','suspended','deactivated') DEFAULT 'pending',
    subscription_id INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (subscription_id) REFERENCES subscription_plans(id) ON DELETE SET NULL
);

-- Institution Admins
CREATE TABLE institution_admins (
    id INT PRIMARY KEY AUTO_INCREMENT,
    institution_id INT NOT NULL,
    full_name VARCHAR(100) NOT NULL,
    email VARCHAR(100) NOT NULL,
    phone VARCHAR(20),
    password VARCHAR(255) NOT NULL,
    role ENUM('admin','officer') DEFAULT 'admin',
    status TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (institution_id) REFERENCES institutions(id) ON DELETE CASCADE
);

-- Subscriptions
CREATE TABLE subscriptions (
    id INT PRIMARY KEY AUTO_INCREMENT,
    institution_id INT NOT NULL,
    plan_id INT NOT NULL,
    start_date DATE NOT NULL,
    end_date DATE NOT NULL,
    status ENUM('active','expired','cancelled') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (institution_id) REFERENCES institutions(id) ON DELETE CASCADE,
    FOREIGN KEY (plan_id) REFERENCES subscription_plans(id)
);

-- Payments
CREATE TABLE payments (
    id INT PRIMARY KEY AUTO_INCREMENT,
    institution_id INT NOT NULL,
    subscription_id INT,
    amount DECIMAL(10,2) NOT NULL,
    payment_method ENUM('mobile_money','bank_transfer','online') NOT NULL,
    reference VARCHAR(100),
    status ENUM('pending','approved','declined') DEFAULT 'pending',
    receipt_url VARCHAR(255),
    recorded_by INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (institution_id) REFERENCES institutions(id) ON DELETE CASCADE
);

-- Elections
CREATE TABLE elections (
    id INT PRIMARY KEY AUTO_INCREMENT,
    institution_id INT NOT NULL,
    title VARCHAR(200) NOT NULL,
    description TEXT,
    start_date DATETIME NOT NULL,
    end_date DATETIME NOT NULL,
    status ENUM('pending','active','closed','cancelled') DEFAULT 'pending',
    show_results TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (institution_id) REFERENCES institutions(id) ON DELETE CASCADE
);

-- Positions
CREATE TABLE positions (
    id INT PRIMARY KEY AUTO_INCREMENT,
    election_id INT NOT NULL,
    title VARCHAR(100) NOT NULL,
    description TEXT,
    max_vote INT DEFAULT 1,
    display_order INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (election_id) REFERENCES elections(id) ON DELETE CASCADE
);

-- Candidates
CREATE TABLE candidates (
    id INT PRIMARY KEY AUTO_INCREMENT,
    position_id INT NOT NULL,
    full_name VARCHAR(100) NOT NULL,
    photo VARCHAR(255),
    manifesto TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (position_id) REFERENCES positions(id) ON DELETE CASCADE
);

-- Voters
CREATE TABLE voters (
    id INT PRIMARY KEY AUTO_INCREMENT,
    institution_id INT NOT NULL,
    student_id VARCHAR(50) NOT NULL,
    full_name VARCHAR(100) NOT NULL,
    email VARCHAR(100),
    phone VARCHAR(20),
    password VARCHAR(255) NOT NULL,
    level VARCHAR(50),
    status TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (institution_id) REFERENCES institutions(id) ON DELETE CASCADE,
    UNIQUE KEY unique_voter (institution_id, student_id)
);

-- OTP Codes
CREATE TABLE otp_codes (
    id INT PRIMARY KEY AUTO_INCREMENT,
    voter_id INT NOT NULL,
    code VARCHAR(6) NOT NULL,
    purpose ENUM('login','verify') DEFAULT 'login',
    expires_at DATETIME NOT NULL,
    used TINYINT(1) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (voter_id) REFERENCES voters(id) ON DELETE CASCADE
);

-- Votes
CREATE TABLE votes (
    id INT PRIMARY KEY AUTO_INCREMENT,
    election_id INT NOT NULL,
    voter_id INT NOT NULL,
    position_id INT NOT NULL,
    candidate_id INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (election_id) REFERENCES elections(id) ON DELETE CASCADE,
    FOREIGN KEY (voter_id) REFERENCES voters(id) ON DELETE CASCADE,
    FOREIGN KEY (position_id) REFERENCES positions(id) ON DELETE CASCADE,
    FOREIGN KEY (candidate_id) REFERENCES candidates(id) ON DELETE CASCADE,
    UNIQUE KEY one_vote_per_position (election_id, voter_id, position_id)
);

-- Audit Logs
CREATE TABLE audit_logs (
    id INT PRIMARY KEY AUTO_INCREMENT,
    institution_id INT,
    user_type ENUM('super_admin','inst_admin','voter') NOT NULL,
    user_id INT,
    action VARCHAR(100) NOT NULL,
    description TEXT,
    ip_address VARCHAR(45),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Indexes
CREATE INDEX idx_institution_slug ON institutions(slug);
CREATE INDEX idx_voter_institution ON voters(institution_id);
CREATE INDEX idx_votes_election ON votes(election_id);
CREATE INDEX idx_otp_voter ON otp_codes(voter_id);
