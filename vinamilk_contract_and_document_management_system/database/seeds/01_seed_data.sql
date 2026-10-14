USE vinamilk_contract_db;

INSERT INTO companies (company_name, tax_code, address, contact_email)
VALUES
(
    'Demo Supplier Company',
    'DEMO-TAX-001',
    'Hanoi, Vietnam',
    'supplier@example.com'
),
(
    'Demo Partner Company',
    'DEMO-TAX-002',
    'Ho Chi Minh City, Vietnam',
    'partner@example.com'
)
ON DUPLICATE KEY UPDATE
    company_name = VALUES(company_name),
    address = VALUES(address),
    contact_email = VALUES(contact_email);