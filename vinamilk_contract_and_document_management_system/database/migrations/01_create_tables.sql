CREATE DATABASE IF NOT EXISTS vinamilk_contract_db
CHARACTER SET utf8mb4
COLLATE utf8mb4_unicode_ci;

USE vinamilk_contract_db;

CREATE TABLE IF NOT EXISTS companies (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    company_name VARCHAR(255) NOT NULL,
    tax_code VARCHAR(50) UNIQUE,
    address VARCHAR(500),
    contact_email VARCHAR(255),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS users (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    company_id INT UNSIGNED NULL,
    full_name VARCHAR(150) NOT NULL,
    email VARCHAR(255) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    role ENUM('external_user', 'staff', 'manager') NOT NULL,
    account_status ENUM('pending', 'active', 'rejected')
        NOT NULL DEFAULT 'pending',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_users_company
        FOREIGN KEY (company_id) REFERENCES companies(id)
        ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS contracts (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    contract_number VARCHAR(100) UNIQUE,
    title VARCHAR(255) NOT NULL,
    company_id INT UNSIGNED NOT NULL,
    created_by INT UNSIGNED NOT NULL,
    start_date DATE NULL,
    end_date DATE NULL,
    status ENUM(
        'draft',
        'pending_approval',
        'approved',
        'rejected',
        'active',
        'expired'
    ) NOT NULL DEFAULT 'draft',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_contracts_company
        FOREIGN KEY (company_id) REFERENCES companies(id),
    CONSTRAINT fk_contracts_creator
        FOREIGN KEY (created_by) REFERENCES users(id)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS contract_documents (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    contract_id INT UNSIGNED NOT NULL,
    uploaded_by INT UNSIGNED NOT NULL,
    file_name VARCHAR(255) NOT NULL,
    file_path VARCHAR(500) NOT NULL,
    uploaded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_documents_contract
        FOREIGN KEY (contract_id) REFERENCES contracts(id)
        ON DELETE CASCADE,
    CONSTRAINT fk_documents_uploader
        FOREIGN KEY (uploaded_by) REFERENCES users(id)
) ENGINE=InnoDB;