SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS=0;

CREATE TABLE IF NOT EXISTS tp_users (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(120) NOT NULL,
  email VARCHAR(190) NOT NULL UNIQUE,
  password_hash VARCHAR(255) NOT NULL,
  role VARCHAR(30) NOT NULL,
  phone VARCHAR(30) NULL,
  avatar_url VARCHAR(500) NULL,
  status VARCHAR(30) NOT NULL DEFAULT 'active',
  last_login_at DATETIME NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX idx_users_role (role),
  INDEX idx_users_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS tp_companies (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  legal_name VARCHAR(190) NOT NULL,
  trade_name VARCHAR(150) NOT NULL,
  cnpj VARCHAR(20) NULL,
  address VARCHAR(255) NULL,
  city VARCHAR(120) NULL,
  state CHAR(2) NULL,
  latitude DECIMAL(10,7) NULL,
  longitude DECIMAL(10,7) NULL,
  logo_url VARCHAR(500) NULL,
  rating DECIMAL(3,2) NOT NULL DEFAULT 5.00,
  reliability_score DECIMAL(5,2) NOT NULL DEFAULT 100.00,
  status VARCHAR(30) NOT NULL DEFAULT 'verified',
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS tp_company_members (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  company_id BIGINT UNSIGNED NOT NULL,
  user_id BIGINT UNSIGNED NOT NULL,
  member_role VARCHAR(40) NOT NULL DEFAULT 'manager',
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_company_user (company_id,user_id),
  CONSTRAINT fk_company_members_company FOREIGN KEY (company_id) REFERENCES tp_companies(id) ON DELETE CASCADE,
  CONSTRAINT fk_company_members_user FOREIGN KEY (user_id) REFERENCES tp_users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS tp_professionals (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id BIGINT UNSIGNED NOT NULL UNIQUE,
  cpf VARCHAR(20) NULL,
  headline VARCHAR(190) NULL,
  bio TEXT NULL,
  city VARCHAR(120) NULL,
  state CHAR(2) NULL,
  latitude DECIMAL(10,7) NULL,
  longitude DECIMAL(10,7) NULL,
  pix_key VARCHAR(190) NULL,
  reliability_score DECIMAL(5,2) NOT NULL DEFAULT 100.00,
  punctuality_score DECIMAL(5,2) NOT NULL DEFAULT 100.00,
  attendance_score DECIMAL(5,2) NOT NULL DEFAULT 100.00,
  rating DECIMAL(3,2) NOT NULL DEFAULT 5.00,
  completed_shifts INT UNSIGNED NOT NULL DEFAULT 0,
  no_show_count INT UNSIGNED NOT NULL DEFAULT 0,
  status VARCHAR(30) NOT NULL DEFAULT 'pending',
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_professional_user FOREIGN KEY (user_id) REFERENCES tp_users(id) ON DELETE CASCADE,
  INDEX idx_prof_score (reliability_score),
  INDEX idx_prof_city (city,state)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS tp_job_categories (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(120) NOT NULL UNIQUE,
  slug VARCHAR(120) NOT NULL UNIQUE,
  active TINYINT(1) NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS tp_professional_categories (
  professional_id BIGINT UNSIGNED NOT NULL,
  category_id BIGINT UNSIGNED NOT NULL,
  experience_level VARCHAR(30) NULL,
  PRIMARY KEY (professional_id,category_id),
  CONSTRAINT fk_pc_prof FOREIGN KEY (professional_id) REFERENCES tp_professionals(id) ON DELETE CASCADE,
  CONSTRAINT fk_pc_cat FOREIGN KEY (category_id) REFERENCES tp_job_categories(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS tp_shifts (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  company_id BIGINT UNSIGNED NOT NULL,
  category_id BIGINT UNSIGNED NOT NULL,
  title VARCHAR(160) NOT NULL,
  description TEXT NULL,
  starts_at DATETIME NOT NULL,
  ends_at DATETIME NOT NULL,
  shift_value DECIMAL(12,2) NOT NULL,
  required_workers INT UNSIGNED NOT NULL DEFAULT 1,
  address VARCHAR(255) NOT NULL,
  city VARCHAR(120) NOT NULL,
  state CHAR(2) NOT NULL,
  latitude DECIMAL(10,7) NULL,
  longitude DECIMAL(10,7) NULL,
  dress_code TEXT NULL,
  notes TEXT NULL,
  checkin_pin VARCHAR(8) NULL,
  acceptance_mode VARCHAR(20) NOT NULL DEFAULT 'automatic',
  status VARCHAR(30) NOT NULL DEFAULT 'draft',
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_shift_company FOREIGN KEY (company_id) REFERENCES tp_companies(id) ON DELETE CASCADE,
  CONSTRAINT fk_shift_category FOREIGN KEY (category_id) REFERENCES tp_job_categories(id),
  INDEX idx_shift_status_date (status,starts_at),
  INDEX idx_shift_company (company_id,starts_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS tp_shift_applications (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  shift_id BIGINT UNSIGNED NOT NULL,
  professional_id BIGINT UNSIGNED NOT NULL,
  status VARCHAR(30) NOT NULL DEFAULT 'applied',
  applied_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_application (shift_id,professional_id),
  CONSTRAINT fk_app_shift FOREIGN KEY (shift_id) REFERENCES tp_shifts(id) ON DELETE CASCADE,
  CONSTRAINT fk_app_prof FOREIGN KEY (professional_id) REFERENCES tp_professionals(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS tp_assignments (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  shift_id BIGINT UNSIGNED NOT NULL,
  professional_id BIGINT UNSIGNED NOT NULL,
  status VARCHAR(30) NOT NULL DEFAULT 'confirmed',
  agreed_value DECIMAL(12,2) NOT NULL,
  confirmed_at DATETIME NULL,
  checkin_at DATETIME NULL,
  checkout_at DATETIME NULL,
  checkin_method VARCHAR(30) NULL,
  cancellation_reason VARCHAR(500) NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_assignment (shift_id,professional_id),
  CONSTRAINT fk_assignment_shift FOREIGN KEY (shift_id) REFERENCES tp_shifts(id) ON DELETE CASCADE,
  CONSTRAINT fk_assignment_prof FOREIGN KEY (professional_id) REFERENCES tp_professionals(id) ON DELETE CASCADE,
  INDEX idx_assignment_prof_status (professional_id,status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS tp_reviews (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  assignment_id BIGINT UNSIGNED NOT NULL,
  reviewer_user_id BIGINT UNSIGNED NOT NULL,
  reviewee_user_id BIGINT UNSIGNED NOT NULL,
  rating TINYINT UNSIGNED NOT NULL,
  attendance_rating TINYINT UNSIGNED NULL,
  punctuality_rating TINYINT UNSIGNED NULL,
  comment TEXT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_review (assignment_id,reviewer_user_id),
  CONSTRAINT fk_review_assignment FOREIGN KEY (assignment_id) REFERENCES tp_assignments(id) ON DELETE CASCADE,
  CONSTRAINT fk_review_reviewer FOREIGN KEY (reviewer_user_id) REFERENCES tp_users(id),
  CONSTRAINT fk_review_reviewee FOREIGN KEY (reviewee_user_id) REFERENCES tp_users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS tp_reputation_events (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  professional_id BIGINT UNSIGNED NOT NULL,
  assignment_id BIGINT UNSIGNED NULL,
  event_type VARCHAR(50) NOT NULL,
  severity VARCHAR(20) NOT NULL DEFAULT 'info',
  points_delta DECIMAL(6,2) NOT NULL DEFAULT 0,
  description VARCHAR(500) NOT NULL,
  occurred_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  expires_at DATETIME NULL,
  appealed_at DATETIME NULL,
  appeal_status VARCHAR(30) NULL,
  CONSTRAINT fk_rep_prof FOREIGN KEY (professional_id) REFERENCES tp_professionals(id) ON DELETE CASCADE,
  CONSTRAINT fk_rep_assignment FOREIGN KEY (assignment_id) REFERENCES tp_assignments(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS tp_documents (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  professional_id BIGINT UNSIGNED NOT NULL,
  type VARCHAR(80) NOT NULL,
  label VARCHAR(150) NOT NULL,
  status VARCHAR(30) NOT NULL DEFAULT 'pending',
  expires_at DATE NULL,
  verified_at DATETIME NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_doc_prof FOREIGN KEY (professional_id) REFERENCES tp_professionals(id) ON DELETE CASCADE,
  INDEX idx_doc_prof_status (professional_id,status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS tp_ledger (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  company_id BIGINT UNSIGNED NULL,
  professional_id BIGINT UNSIGNED NULL,
  assignment_id BIGINT UNSIGNED NULL,
  direction VARCHAR(10) NOT NULL,
  amount DECIMAL(12,2) NOT NULL,
  kind VARCHAR(50) NOT NULL,
  status VARCHAR(30) NOT NULL,
  external_reference VARCHAR(190) NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_ledger_company FOREIGN KEY (company_id) REFERENCES tp_companies(id) ON DELETE SET NULL,
  CONSTRAINT fk_ledger_prof FOREIGN KEY (professional_id) REFERENCES tp_professionals(id) ON DELETE SET NULL,
  CONSTRAINT fk_ledger_assignment FOREIGN KEY (assignment_id) REFERENCES tp_assignments(id) ON DELETE SET NULL,
  INDEX idx_ledger_prof (professional_id,created_at),
  INDEX idx_ledger_company (company_id,created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS tp_notifications (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id BIGINT UNSIGNED NOT NULL,
  type VARCHAR(50) NOT NULL,
  title VARCHAR(190) NOT NULL,
  body TEXT NOT NULL,
  read_at DATETIME NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_notification_user FOREIGN KEY (user_id) REFERENCES tp_users(id) ON DELETE CASCADE,
  INDEX idx_notification_user (user_id,read_at,created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS tp_api_tokens (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id BIGINT UNSIGNED NOT NULL,
  token_hash CHAR(64) NOT NULL UNIQUE,
  expires_at DATETIME NOT NULL,
  revoked_at DATETIME NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_token_user FOREIGN KEY (user_id) REFERENCES tp_users(id) ON DELETE CASCADE,
  INDEX idx_token_expiry (expires_at,revoked_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS tp_audit_logs (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id BIGINT UNSIGNED NULL,
  action VARCHAR(100) NOT NULL,
  entity_type VARCHAR(80) NULL,
  entity_id BIGINT UNSIGNED NULL,
  metadata_json LONGTEXT NULL,
  ip_address VARCHAR(45) NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_audit_user FOREIGN KEY (user_id) REFERENCES tp_users(id) ON DELETE SET NULL,
  INDEX idx_audit_created (created_at),
  INDEX idx_audit_entity (entity_type,entity_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS=1;
