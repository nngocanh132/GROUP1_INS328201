-- ============================================================
-- VINAMILK CLM
-- Contract & Document Lifecycle Management System
-- MySQL 8.0.16+
-- 16 tables: Role, Account, Department, Staff, Counterparty,
-- Contact, ContractType, Contract, Document, ContractVersion,
-- ContractRequest, Review, Approval, StatusHistory, AuditLog,
-- Notification
--
-- IMPORTANT:
-- 1. This script does NOT drop an existing database.
-- 2. Run this in a development database after reviewing the schema.
-- 3. Store uploaded files outside the database; save their paths here.
-- ============================================================

CREATE DATABASE IF NOT EXISTS vinamilk_clm
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

USE vinamilk_clm;




-- If you need to rebuild from scratch, back up your data first.
-- Then drop tables manually in reverse dependency order.

-- ============================================================
-- GROUP A: USERS AND ORGANIZATION
-- ============================================================

CREATE TABLE IF NOT EXISTS Role (
    RoleID       INT NOT NULL AUTO_INCREMENT,
    RoleName     VARCHAR(50) NOT NULL,
    Description  VARCHAR(255) NULL,
    CreatedAt    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT pk_role PRIMARY KEY (RoleID),
    CONSTRAINT uq_role_name UNIQUE (RoleName)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS Account (
    AccountID     INT NOT NULL AUTO_INCREMENT,
    RoleID        INT NOT NULL,
    Email         VARCHAR(150) NOT NULL,
    PasswordHash VARCHAR(255) NOT NULL,
    Status        VARCHAR(20) NOT NULL DEFAULT 'Pending',
    LastLoginAt   DATETIME NULL,
    CreatedAt     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT pk_account PRIMARY KEY (AccountID),
    CONSTRAINT uq_account_email UNIQUE (Email),
    CONSTRAINT fk_account_role FOREIGN KEY (RoleID)
        REFERENCES Role (RoleID)
        ON UPDATE CASCADE
        ON DELETE RESTRICT,
    CONSTRAINT ck_account_status
        CHECK (Status IN ('Pending', 'Active', 'Inactive'))
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS Department (
    DepartmentID    INT NOT NULL AUTO_INCREMENT,
    DepartmentName  VARCHAR(100) NOT NULL,
    Description     VARCHAR(255) NULL,
    CONSTRAINT pk_department PRIMARY KEY (DepartmentID),
    CONSTRAINT uq_department_name UNIQUE (DepartmentName)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS Staff (
    StaffID       VARCHAR(20) NOT NULL,
    AccountID     INT NOT NULL,
    DepartmentID  INT NOT NULL,
    FullName      VARCHAR(100) NOT NULL,
    Position      VARCHAR(100) NULL,
    CONSTRAINT pk_staff PRIMARY KEY (StaffID),
    CONSTRAINT uq_staff_account UNIQUE (AccountID),
    CONSTRAINT fk_staff_account FOREIGN KEY (AccountID)
        REFERENCES Account (AccountID)
        ON UPDATE CASCADE
        ON DELETE RESTRICT,
    CONSTRAINT fk_staff_department FOREIGN KEY (DepartmentID)
        REFERENCES Department (DepartmentID)
        ON UPDATE CASCADE
        ON DELETE RESTRICT
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS Counterparty (
    CounterpartyID       INT NOT NULL AUTO_INCREMENT,
    CounterpartyCode     VARCHAR(20) NOT NULL,
    CompanyName          VARCHAR(200) NOT NULL,
    CounterpartyType     VARCHAR(30) NOT NULL,
    TaxCode              VARCHAR(20) NOT NULL,
    Address              VARCHAR(255) NULL,
    Email                VARCHAR(150) NULL,
    Phone                VARCHAR(20) NULL,
    LegalRepresentative  VARCHAR(100) NULL,
    BusinessLicense      VARCHAR(255) NULL,
    ApprovalStatus       VARCHAR(20) NOT NULL DEFAULT 'Pending',
    RejectionReason      VARCHAR(500) NULL,
    ProposedBy           VARCHAR(20) NOT NULL,
    ProposedAt           DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    DecidedBy            VARCHAR(20) NULL,
    DecidedAt            DATETIME NULL,
    CONSTRAINT pk_counterparty PRIMARY KEY (CounterpartyID),
    CONSTRAINT uq_counterparty_code UNIQUE (CounterpartyCode),
    CONSTRAINT uq_counterparty_tax UNIQUE (TaxCode),
    CONSTRAINT fk_counterparty_proposedby FOREIGN KEY (ProposedBy)
        REFERENCES Staff (StaffID)
        ON UPDATE CASCADE
        ON DELETE RESTRICT,
    CONSTRAINT fk_counterparty_decidedby FOREIGN KEY (DecidedBy)
        REFERENCES Staff (StaffID)
        ON UPDATE CASCADE
        ON DELETE SET NULL,
    CONSTRAINT ck_counterparty_type
        CHECK (CounterpartyType IN ('Supplier', 'Customer', 'ServiceProvider')),
    CONSTRAINT ck_counterparty_status
        CHECK (ApprovalStatus IN ('Pending', 'Active', 'Rejected', 'Suspended'))
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS Contact (
    ContactID       INT NOT NULL AUTO_INCREMENT,
    AccountID       INT NOT NULL,
    CounterpartyID  INT NOT NULL,
    FullName        VARCHAR(100) NOT NULL,
    JobTitle        VARCHAR(100) NULL,
    Phone           VARCHAR(20) NULL,
    IsPrimary       BOOLEAN NOT NULL DEFAULT FALSE,
    CONSTRAINT pk_contact PRIMARY KEY (ContactID),
    CONSTRAINT uq_contact_account UNIQUE (AccountID),
    CONSTRAINT fk_contact_account FOREIGN KEY (AccountID)
        REFERENCES Account (AccountID)
        ON UPDATE CASCADE
        ON DELETE RESTRICT,
    CONSTRAINT fk_contact_counterparty FOREIGN KEY (CounterpartyID)
        REFERENCES Counterparty (CounterpartyID)
        ON UPDATE CASCADE
        ON DELETE RESTRICT
) ENGINE=InnoDB;

-- ============================================================
-- GROUP B: CONTRACTS AND DOCUMENTS
-- ============================================================

CREATE TABLE IF NOT EXISTS ContractType (
    ContractTypeID  INT NOT NULL AUTO_INCREMENT,
    TypeName        VARCHAR(100) NOT NULL,
    Description     VARCHAR(255) NULL,
    IsActive        BOOLEAN NOT NULL DEFAULT TRUE,
    CONSTRAINT pk_contracttype PRIMARY KEY (ContractTypeID),
    CONSTRAINT uq_contracttype_name UNIQUE (TypeName)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS Contract (
    ContractID         INT NOT NULL AUTO_INCREMENT,
    ContractCode       VARCHAR(20) NOT NULL,
    ContractTitle      VARCHAR(200) NOT NULL,
    ContractTypeID     INT NOT NULL,
    DepartmentID       INT NOT NULL,
    CounterpartyID     INT NOT NULL,
    OwnerStaffID       VARCHAR(20) NOT NULL,
    Summary            TEXT NULL,
    ContractValue      DECIMAL(15,2) NULL,
    Currency           CHAR(3) NULL,
    StartDate          DATE NOT NULL,
    EndDate            DATE NOT NULL,
    RenewalReviewDate  DATE NULL,
    AutoRenewalAlert   BOOLEAN NOT NULL DEFAULT TRUE,
    Status             VARCHAR(30) NOT NULL DEFAULT 'Draft',
    CreatedAt          DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UpdatedAt          DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
                                    ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT pk_contract PRIMARY KEY (ContractID),
    CONSTRAINT uq_contract_code UNIQUE (ContractCode),
    CONSTRAINT fk_contract_type FOREIGN KEY (ContractTypeID)
        REFERENCES ContractType (ContractTypeID)
        ON UPDATE CASCADE
        ON DELETE RESTRICT,
    CONSTRAINT fk_contract_department FOREIGN KEY (DepartmentID)
        REFERENCES Department (DepartmentID)
        ON UPDATE CASCADE
        ON DELETE RESTRICT,
    CONSTRAINT fk_contract_counterparty FOREIGN KEY (CounterpartyID)
        REFERENCES Counterparty (CounterpartyID)
        ON UPDATE CASCADE
        ON DELETE RESTRICT,
    CONSTRAINT fk_contract_owner FOREIGN KEY (OwnerStaffID)
        REFERENCES Staff (StaffID)
        ON UPDATE CASCADE
        ON DELETE RESTRICT,
    CONSTRAINT ck_contract_dates CHECK (EndDate >= StartDate),
    CONSTRAINT ck_contract_value CHECK (ContractValue IS NULL OR ContractValue >= 0),
    CONSTRAINT ck_contract_currency
        CHECK (Currency IS NULL OR Currency IN ('VND', 'USD')),
    CONSTRAINT ck_contract_status CHECK (Status IN (
        'Draft', 'PendingReview', 'PendingApproval',
        'RevisionRequested', 'Approved', 'Active', 'Closed'
    ))
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS Document (
    DocumentID          INT NOT NULL AUTO_INCREMENT,
    ContractID          INT NOT NULL,
    DocumentType        VARCHAR(30) NOT NULL DEFAULT 'MainContract',
    DocumentName        VARCHAR(200) NOT NULL,
    IsVisibleToPartner  BOOLEAN NOT NULL DEFAULT FALSE,
    CreatedAt           DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT pk_document PRIMARY KEY (DocumentID),
    CONSTRAINT fk_document_contract FOREIGN KEY (ContractID)
        REFERENCES Contract (ContractID)
        ON UPDATE CASCADE
        ON DELETE RESTRICT,
    CONSTRAINT ck_document_type CHECK (DocumentType IN (
        'MainContract', 'Appendix', 'CargoInsurance',
        'FoodSafetyCert', 'BankGuarantee', 'Other'
    ))
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS ContractVersion (
    VersionID      INT NOT NULL AUTO_INCREMENT,
    DocumentID     INT NOT NULL,
    VersionNumber  INT NOT NULL,
    FilePath       VARCHAR(500) NOT NULL,
    FileSize       BIGINT NULL,
    FileHash       CHAR(64) NULL,
    UploadedBy     INT NOT NULL,
    ChangeSummary  TEXT NULL,
    IsCurrent      BOOLEAN NOT NULL DEFAULT TRUE,
    UploadedAt     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT pk_contractversion PRIMARY KEY (VersionID),
    CONSTRAINT uq_document_version UNIQUE (DocumentID, VersionNumber),
    CONSTRAINT fk_version_document FOREIGN KEY (DocumentID)
        REFERENCES Document (DocumentID)
        ON UPDATE CASCADE
        ON DELETE RESTRICT,
    CONSTRAINT fk_version_uploadedby FOREIGN KEY (UploadedBy)
        REFERENCES Account (AccountID)
        ON UPDATE CASCADE
        ON DELETE RESTRICT,
    CONSTRAINT ck_version_number CHECK (VersionNumber >= 1),
    CONSTRAINT ck_version_filesize CHECK (FileSize IS NULL OR FileSize >= 0)
) ENGINE=InnoDB;

-- ============================================================
-- GROUP C: CONTRACT WORKFLOW
-- ============================================================

CREATE TABLE IF NOT EXISTS ContractRequest (
    RequestID     INT NOT NULL AUTO_INCREMENT,
    ContractID    INT NOT NULL,
    RequestedBy   INT NOT NULL,
    RequestType   VARCHAR(30) NOT NULL,
    AssignedSide  VARCHAR(20) NOT NULL,
    Status        VARCHAR(20) NOT NULL DEFAULT 'Pending',
    Description   TEXT NULL,
    RequestedAt   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    ResponseNote  TEXT NULL,
    RespondedBy   INT NULL,
    RespondedAt   DATETIME NULL,
    CONSTRAINT pk_contractrequest PRIMARY KEY (RequestID),
    CONSTRAINT fk_request_contract FOREIGN KEY (ContractID)
        REFERENCES Contract (ContractID)
        ON UPDATE CASCADE
        ON DELETE RESTRICT,
    CONSTRAINT fk_request_requestedby FOREIGN KEY (RequestedBy)
        REFERENCES Account (AccountID)
        ON UPDATE CASCADE
        ON DELETE RESTRICT,
    CONSTRAINT fk_request_respondedby FOREIGN KEY (RespondedBy)
        REFERENCES Account (AccountID)
        ON UPDATE CASCADE
        ON DELETE SET NULL,
    CONSTRAINT ck_request_type
        CHECK (RequestType IN ('Revision', 'Supplement', 'ExternalReview', 'InternalApproval')),
    CONSTRAINT ck_request_side
        CHECK (AssignedSide IN ('Internal', 'Counterparty')),
    CONSTRAINT ck_request_status
        CHECK (Status IN ('Pending', 'InProgress', 'Completed', 'Cancelled'))
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS Review (
    ReviewID           INT NOT NULL AUTO_INCREMENT,
    ContractID         INT NOT NULL,
    RequestID          INT NULL,
    ReviewerAccountID  INT NOT NULL,
    ReviewStatus       VARCHAR(30) NOT NULL DEFAULT 'Pending',
    Comments           TEXT NULL,
    SubmittedAt        DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    ReviewedAt         DATETIME NULL,
    CONSTRAINT pk_review PRIMARY KEY (ReviewID),
    CONSTRAINT fk_review_contract FOREIGN KEY (ContractID)
        REFERENCES Contract (ContractID)
        ON UPDATE CASCADE
        ON DELETE RESTRICT,
    CONSTRAINT fk_review_request FOREIGN KEY (RequestID)
        REFERENCES ContractRequest (RequestID)
        ON UPDATE CASCADE
        ON DELETE SET NULL,
    CONSTRAINT fk_review_reviewer FOREIGN KEY (ReviewerAccountID)
        REFERENCES Account (AccountID)
        ON UPDATE CASCADE
        ON DELETE RESTRICT,
    CONSTRAINT ck_review_status CHECK (ReviewStatus IN (
        'Pending', 'Approved', 'Rejected', 'RevisionRequired'
    ))
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS Approval (
    ApprovalID         INT NOT NULL AUTO_INCREMENT,
    ContractID         INT NOT NULL,
    RequestID          INT NULL,
    ApproverAccountID  INT NOT NULL,
    ApprovalLevel      INT NOT NULL DEFAULT 1,
    DocumentID         INT NULL,
    ApprovalStatus     VARCHAR(20) NOT NULL DEFAULT 'Pending',
    Comments           TEXT NULL,
    SubmittedAt        DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    ApprovedAt         DATETIME NULL,
    SignatureStatus    VARCHAR(10) NULL,
    SignatureHash      CHAR(64) NULL,
    CONSTRAINT pk_approval PRIMARY KEY (ApprovalID),
    CONSTRAINT fk_approval_contract FOREIGN KEY (ContractID)
        REFERENCES Contract (ContractID)
        ON UPDATE CASCADE
        ON DELETE RESTRICT,
    CONSTRAINT fk_approval_request FOREIGN KEY (RequestID)
        REFERENCES ContractRequest (RequestID)
        ON UPDATE CASCADE
        ON DELETE SET NULL,
    CONSTRAINT fk_approval_approver FOREIGN KEY (ApproverAccountID)
        REFERENCES Account (AccountID)
        ON UPDATE CASCADE
        ON DELETE RESTRICT,
    CONSTRAINT fk_approval_document FOREIGN KEY (DocumentID)
        REFERENCES Document (DocumentID)
        ON UPDATE CASCADE
        ON DELETE SET NULL,
    CONSTRAINT ck_approval_level CHECK (ApprovalLevel >= 1),
    CONSTRAINT ck_approval_status
        CHECK (ApprovalStatus IN ('Pending', 'Approved', 'Rejected')),
    CONSTRAINT ck_approval_signature
        CHECK (SignatureStatus IS NULL OR SignatureStatus IN ('Success', 'Failed'))
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS StatusHistory (
    HistoryID     INT NOT NULL AUTO_INCREMENT,
    ContractID    INT NOT NULL,
    ChangedBy     INT NOT NULL,
    OldStatus     VARCHAR(30) NULL,
    NewStatus     VARCHAR(30) NOT NULL,
    ChangeReason  TEXT NULL,
    ChangedAt     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT pk_statushistory PRIMARY KEY (HistoryID),
    CONSTRAINT fk_history_contract FOREIGN KEY (ContractID)
        REFERENCES Contract (ContractID)
        ON UPDATE CASCADE
        ON DELETE RESTRICT,
    CONSTRAINT fk_history_changedby FOREIGN KEY (ChangedBy)
        REFERENCES Account (AccountID)
        ON UPDATE CASCADE
        ON DELETE RESTRICT
) ENGINE=InnoDB;

-- ============================================================
-- GROUP D: AUDIT AND NOTIFICATIONS
-- ============================================================

CREATE TABLE IF NOT EXISTS AuditLog (
    AuditLogID  BIGINT NOT NULL AUTO_INCREMENT,
    AccountID   INT NULL,
    Action      VARCHAR(100) NOT NULL,
    EntityName  VARCHAR(50) NOT NULL,
    EntityID    INT NULL,
    Details     JSON NULL,
    IPAddress   VARCHAR(45) NULL,
    CreatedAt   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT pk_auditlog PRIMARY KEY (AuditLogID),
    CONSTRAINT fk_auditlog_account FOREIGN KEY (AccountID)
        REFERENCES Account (AccountID)
        ON UPDATE CASCADE
        ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS Notification (
    NotificationID   INT NOT NULL AUTO_INCREMENT,
    AccountID        INT NOT NULL,
    ContractID       INT NULL,
    RequestID        INT NULL,
    CounterpartyID   INT NULL,
    Title            VARCHAR(200) NOT NULL,
    Message          TEXT NOT NULL,
    NotificationType VARCHAR(20) NOT NULL DEFAULT 'Info',
    IsRead           BOOLEAN NOT NULL DEFAULT FALSE,
    CreatedAt        DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    ReadAt           DATETIME NULL,

    CONSTRAINT pk_notification
        PRIMARY KEY (NotificationID),

    CONSTRAINT fk_notification_account
        FOREIGN KEY (AccountID)
        REFERENCES Account (AccountID)
        ON UPDATE CASCADE
        ON DELETE CASCADE,

    CONSTRAINT fk_notification_contract
        FOREIGN KEY (ContractID)
        REFERENCES Contract (ContractID)
        ON UPDATE CASCADE
        ON DELETE RESTRICT,

    CONSTRAINT fk_notification_request
        FOREIGN KEY (RequestID)
        REFERENCES ContractRequest (RequestID)
        ON UPDATE CASCADE
        ON DELETE RESTRICT,

    CONSTRAINT fk_notification_counterparty
        FOREIGN KEY (CounterpartyID)
        REFERENCES Counterparty (CounterpartyID)
        ON UPDATE CASCADE
        ON DELETE RESTRICT,

    CONSTRAINT ck_notification_type
        CHECK (NotificationType IN ('Info', 'Success', 'Warning'))
) ENGINE=InnoDB;

-- ============================================================
-- INDEXES FOR COMMON QUERIES
-- ============================================================

CREATE INDEX idx_counterparty_status
    ON Counterparty (ApprovalStatus);

CREATE INDEX idx_contract_status
    ON Contract (Status);

CREATE INDEX idx_contract_enddate
    ON Contract (EndDate);

CREATE INDEX idx_contract_counterparty
    ON Contract (CounterpartyID);

CREATE INDEX idx_contract_owner
    ON Contract (OwnerStaffID);

CREATE INDEX idx_document_contract
    ON Document (ContractID);

CREATE INDEX idx_version_document_current
    ON ContractVersion (DocumentID, IsCurrent);

CREATE INDEX idx_request_contract_status
    ON ContractRequest (ContractID, Status);

CREATE INDEX idx_review_contract_status
    ON Review (ContractID, ReviewStatus);

CREATE INDEX idx_approval_contract_status
    ON Approval (ContractID, ApprovalStatus);

CREATE INDEX idx_statushistory_contract_date
    ON StatusHistory (ContractID, ChangedAt);

CREATE INDEX idx_auditlog_account_date
    ON AuditLog (AccountID, CreatedAt);

CREATE INDEX idx_notification_account_read
    ON Notification (AccountID, IsRead);

-- ============================================================
-- END OF SCHEMA
-- Verify with: SHOW TABLES;
-- Expected: 16 tables
-- ============================================================
