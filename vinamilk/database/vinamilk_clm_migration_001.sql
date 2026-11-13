-- ============================================================
-- VINAMILK CLM — Migration 001
-- Run this ONCE after vinamilk_clm_schema.sql
-- Safe to run multiple times (uses IF NOT EXISTS / IF EXISTS)
-- ============================================================

USE vinamilk_clm;

-- Make StartDate and EndDate nullable so Drafts can be saved without dates
ALTER TABLE Contract
    MODIFY COLUMN StartDate DATE NULL,
    MODIFY COLUMN EndDate   DATE NULL;

-- ContractProposal: pre-contract request workflow
CREATE TABLE IF NOT EXISTS ContractProposal (
    ProposalID          INT NOT NULL AUTO_INCREMENT,
    ProposalCode        VARCHAR(20) NOT NULL,
    ProposalTitle       VARCHAR(200) NOT NULL,
    ContractTypeID      INT NOT NULL,
    DepartmentID        INT NOT NULL,
    CounterpartyID      INT NOT NULL,
    ProposedByStaffID   VARCHAR(20) NOT NULL,
    Summary             TEXT NULL,
    EstimatedValue      DECIMAL(15,2) NULL,
    Currency            CHAR(3) NOT NULL DEFAULT 'VND',
    ProposedStartDate   DATE NULL,
    ProposedEndDate     DATE NULL,
    Status              VARCHAR(20) NOT NULL DEFAULT 'Pending',
    PartnerReviewNote   TEXT NULL,
    PartnerReviewedBy   INT NULL,
    PartnerReviewedAt   DATETIME NULL,
    AdminReviewNote     TEXT NULL,
    AdminReviewedBy     INT NULL,
    AdminReviewedAt     DATETIME NULL,
    ContractID          INT NULL,
    CreatedAt           DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UpdatedAt           DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    CONSTRAINT pk_contractproposal PRIMARY KEY (ProposalID),
    CONSTRAINT uq_proposal_code UNIQUE (ProposalCode),
    CONSTRAINT fk_proposal_type        FOREIGN KEY (ContractTypeID)     REFERENCES ContractType (ContractTypeID) ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT fk_proposal_dept        FOREIGN KEY (DepartmentID)       REFERENCES Department   (DepartmentID)  ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT fk_proposal_counterparty FOREIGN KEY (CounterpartyID)    REFERENCES Counterparty (CounterpartyID) ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT fk_proposal_staff       FOREIGN KEY (ProposedByStaffID)  REFERENCES Staff        (StaffID)       ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT fk_proposal_partner_rev FOREIGN KEY (PartnerReviewedBy)  REFERENCES Account      (AccountID)     ON UPDATE CASCADE ON DELETE SET NULL,
    CONSTRAINT fk_proposal_admin_rev   FOREIGN KEY (AdminReviewedBy)    REFERENCES Account      (AccountID)     ON UPDATE CASCADE ON DELETE SET NULL,
    CONSTRAINT fk_proposal_contract    FOREIGN KEY (ContractID)         REFERENCES Contract     (ContractID)    ON UPDATE CASCADE ON DELETE SET NULL,
    CONSTRAINT ck_proposal_status CHECK (Status IN ('Pending','PartnerApproved','PartnerRejected','AdminApproved','AdminRejected','ContractCreated','Withdrawn')),
    CONSTRAINT ck_proposal_currency CHECK (Currency IN ('VND','USD')),
    CONSTRAINT ck_proposal_value CHECK (EstimatedValue IS NULL OR EstimatedValue >= 0)
) ENGINE=InnoDB;
