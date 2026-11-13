-- ============================================================
-- VINAMILK CLM - DU LIEU MAU (SEED DATA)
-- Khop voi schema 16 bang: vinamilk_clm_schema.sql
-- MySQL 8.0.16+ / MariaDB 10.5+
--
-- CACH DUNG:
--   1. Chay vinamilk_clm_schema.sql truoc.
--   2. Chay file nay (cac bang phai dang TRONG).
--   3. Mat khau cua MOI tai khoan mau la:  password
--
-- DU LIEU DUOC DUNG THEO CAC MAN HINH DEMO:
--   - 5 nhan vien, 13 doanh nghiep doi tac, 9 hop dong, 13 tai lieu
--   - Tong gia tri hop dong = 376.100.000.000 VND (khop bao cao Admin)
--   - Moc "hom nay" cua demo la 2026-10-13
--   - Email nhan vien/doi tac lay tu demo; email doi tac tu them dung
--     duoi ".example" (ten mien du phong, khong phai email that)
-- ============================================================

USE vinamilk_clm;
SET NAMES utf8mb4;

-- ------------------------------------------------------------
-- (TUY CHON) XOA SACH DU LIEU CU DE CHAY LAI - bo comment neu can
-- ------------------------------------------------------------
-- SET FOREIGN_KEY_CHECKS = 0;
-- TRUNCATE TABLE Notification;   TRUNCATE TABLE AuditLog;
-- TRUNCATE TABLE StatusHistory;  TRUNCATE TABLE Approval;
-- TRUNCATE TABLE Review;         TRUNCATE TABLE ContractRequest;
-- TRUNCATE TABLE ContractVersion; TRUNCATE TABLE Document;
-- TRUNCATE TABLE Contract;       TRUNCATE TABLE ContractType;
-- TRUNCATE TABLE Contact;        TRUNCATE TABLE Counterparty;
-- TRUNCATE TABLE Staff;          TRUNCATE TABLE Department;
-- TRUNCATE TABLE Account;        TRUNCATE TABLE Role;
-- SET FOREIGN_KEY_CHECKS = 1;

START TRANSACTION;

-- Ma bam bcrypt cua chuoi "password" (giong mac dinh cua Laravel)
SET @pw = '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi';

-- ============================================================
-- 1. ROLE
-- ============================================================
INSERT INTO Role (RoleID, RoleName, Description, CreatedAt) VALUES
(1, 'Manager/Admin', 'Lãnh đạo / Giám đốc Pháp chế: thẩm định, ký duyệt, quản trị người dùng và cấu hình hệ thống', '2025-01-01 08:00:00'),
(2, 'Staff',         'Nhân viên Vinamilk: tạo và theo dõi hợp đồng, đề xuất doanh nghiệp đối tác',                  '2025-01-01 08:00:00'),
(3, 'External',      'Người dùng đối tác bên ngoài: chỉ xem và phản hồi hợp đồng của doanh nghiệp mình',            '2025-01-01 08:00:00');

-- ============================================================
-- 2. DEPARTMENT
-- ============================================================
INSERT INTO Department (DepartmentID, DepartmentName, Description) VALUES
(1, 'Legal',       'Ban Pháp chế & Quản trị Hợp đồng'),
(2, 'Procurement', 'Ban Thu mua & Mua sắm'),
(3, 'Logistics',   'Ban Logistics & Vận tải');

-- ============================================================
-- 3. CONTRACTTYPE
-- ============================================================
INSERT INTO ContractType (ContractTypeID, TypeName, Description, IsActive) VALUES
(1, 'HĐ Nhà cung cấp',       'Hợp đồng mua nguyên liệu, vật tư, bao bì',                 TRUE),
(2, 'HĐ Khách hàng',         'Hợp đồng phân phối, bán hàng cho nhà phân phối / đại lý',  TRUE),
(3, 'HĐ Dịch vụ',            'Hợp đồng dịch vụ kỹ thuật, kiểm nghiệm, bảo trì hệ thống', TRUE),
(4, 'HĐ Vận chuyển',         'Hợp đồng vận tải và kho bãi',                              TRUE),
(5, 'HĐ Đối tác chiến lược', 'Hợp đồng hợp tác dài hạn, nghiên cứu và phát triển',       TRUE),
(6, 'HĐ Khác',               'Các loại hợp đồng không thuộc nhóm trên',                  TRUE);

-- ============================================================
-- 4. ACCOUNT  (1-5: nhân viên | 6-18: người dùng đối tác)
-- ============================================================
INSERT INTO Account (AccountID, RoleID, Email, PasswordHash, Status, LastLoginAt, CreatedAt) VALUES
( 1, 1, 'khoavd@vinamilk.com.vn',                    @pw, 'Active',   '2026-10-05 08:50:00', '2025-01-02 09:00:00'),
( 2, 2, 'maitt@vinamilk.com.vn',                     @pw, 'Active',   '2026-10-05 09:10:00', '2025-01-02 09:00:00'),
( 3, 2, 'binh@vinamilk.com.vn',                      @pw, 'Active',   '2026-10-05 08:30:00', '2025-02-10 09:00:00'),
( 4, 2, 'anh@vinamilk.com.vn',                       @pw, 'Active',   '2026-10-04 16:45:00', '2025-02-10 09:00:00'),
( 5, 2, 'nam@vinamilk.com.vn',                       @pw, 'Inactive', '2026-09-20 17:00:00', '2025-03-05 09:00:00'),
( 6, 3, 'an.nguyen@abclogistics.vn',                 @pw, 'Active',   '2026-10-05 10:15:00', '2025-11-11 14:00:00'),
( 7, 3, 'phat.lm@minhphat-dairy.vn',                 @pw, 'Active',   '2026-10-02 14:20:00', '2025-12-02 09:00:00'),
( 8, 3, 'hong.vo@anphu-dist.com.vn',                 @pw, 'Active',   '2026-09-28 09:05:00', '2025-10-16 10:00:00'),
( 9, 3, 'kiet.dang@techsolutions.vn',                @pw, 'Active',   '2026-10-03 11:40:00', '2026-05-16 09:30:00'),
(10, 3, 'dung.dq@tancang-coldchain.vn',              @pw, 'Pending',  NULL,                  '2026-10-04 15:30:00'),
(11, 3, 'linh.pham@asiabiopack.com.vn',              @pw, 'Pending',  NULL,                  '2026-10-05 08:00:00'),
(12, 3, 'hai.vn@namhaitrans.vn',                     @pw, 'Inactive', NULL,                  '2026-09-25 11:00:00'),
(13, 3, 'hoaiann220@gmail.com',                      @pw, 'Active',   NULL,                  '2026-10-05 11:25:00'),
(14, 3, 'thanh.ha@tetrapak-indochina.example',       @pw, 'Active',   '2026-10-01 10:00:00', '2026-06-02 11:00:00'),
(15, 3, 'minh.tuan@greentech-energy.example',        @pw, 'Active',   '2026-09-30 09:30:00', '2026-04-21 09:00:00'),
(16, 3, 'lab.contract@eurofins-hdlab.example',       @pw, 'Active',   '2026-08-01 15:00:00', '2025-06-21 10:00:00'),
(17, 3, 'quan.le@mocchau-agri.example',              @pw, 'Active',   '2026-10-04 17:00:00', '2026-03-16 14:00:00'),
(18, 3, 'phuong.tran@binhduong-packaging.example',   @pw, 'Active',   '2026-01-15 10:00:00', '2024-01-11 10:00:00');

-- ============================================================
-- 5. STAFF  (StaffID chính là mã nhân viên)
-- ============================================================
INSERT INTO Staff (StaffID, AccountID, DepartmentID, FullName, Position) VALUES
('VNM-001', 1, 1, 'TS. Vũ Đình Khoa',  'Giám đốc Pháp chế & Quản trị Hợp đồng'),
('VNM-104', 2, 2, 'Trần Thị Mai',      'Chuyên viên Quản trị Hợp đồng'),
('VNM-208', 3, 3, 'Nguyễn Văn Bình',   'Chuyên viên Điều phối Vận tải'),
('VNM-315', 4, 1, 'Trần Minh Anh',     'Chuyên viên Pháp chế'),
('VNM-402', 5, 2, 'Lê Hoàng Nam',      'Chuyên viên Thu mua Nguyên liệu');

-- ============================================================
-- 6. COUNTERPARTY  (ID 1-8 theo VNM-EXT-001..008, ID 9-13 là đối tác của các hợp đồng)
-- ============================================================
INSERT INTO Counterparty
(CounterpartyID, CounterpartyCode, CompanyName, CounterpartyType, TaxCode, Address, Email, Phone,
 LegalRepresentative, BusinessLicense, ApprovalStatus, RejectionReason,
 ProposedBy, ProposedAt, DecidedBy, DecidedAt) VALUES
( 1, 'VNM-EXT-001', 'ABC Logistics Co., Ltd.', 'ServiceProvider', '0314892019',
  'KCN Sóng Thần 2, Dĩ An, Tỉnh Bình Dương', 'an.nguyen@abclogistics.vn', '(028) 3892 1199',
  'Nguyễn Văn An', '0314892019 do Sở KH&ĐT Tỉnh Bình Dương cấp', 'Active', NULL,
  'VNM-104', '2025-11-10 09:30:00', 'VNM-001', '2025-11-11 14:00:00'),
( 2, 'VNM-EXT-002', 'Minh Phát Ingredients JSC', 'Supplier', '3700891234',
  'KCN VSIP 1, Thuận An, Tỉnh Bình Dương', 'phat.lm@minhphat-dairy.vn', '(0274) 3756 1234',
  'Lê Minh Phát', '3700891234 do Sở KH&ĐT Tỉnh Bình Dương cấp', 'Active', NULL,
  'VNM-104', '2025-12-01 10:15:00', 'VNM-001', '2025-12-02 09:00:00'),
( 3, 'VNM-EXT-003', 'An Phú Distribution Co., Ltd.', 'Customer', '1801239871',
  'Đại lộ Hòa Bình, Ninh Kiều, Cần Thơ', 'hong.vo@anphu-dist.com.vn', '(0292) 3812 4560',
  'Võ Thị Hồng', '1801239871 do Sở KH&ĐT TP. Cần Thơ cấp', 'Active', NULL,
  'VNM-104', '2025-10-15 08:30:00', 'VNM-001', '2025-10-16 10:00:00'),
( 4, 'VNM-EXT-004', 'Tech Solutions Vietnam', 'ServiceProvider', '0312984512',
  'Tòa nhà Landmark 81, Bình Thạnh, TP. Hồ Chí Minh', 'kiet.dang@techsolutions.vn', '(028) 3822 8100',
  'Đặng Tuấn Kiệt', '0312984512 do Sở KH&ĐT TP. Hồ Chí Minh cấp', 'Active', NULL,
  'VNM-104', '2026-05-15 14:00:00', 'VNM-001', '2026-05-16 09:30:00'),
( 5, 'VNM-EXT-005', 'Vận tải & Kho lạnh Tân Cảng Logistics', 'ServiceProvider', '0317829101',
  'Cảng Cát Lái, Phường Cát Lái, TP. Thủ Đức, TP. Hồ Chí Minh', 'dung.dq@tancang-coldchain.vn', '(028) 3742 5500',
  'Đoàn Quốc Dũng', '0317829101 do Sở KH&ĐT TP. Hồ Chí Minh cấp', 'Pending', NULL,
  'VNM-104', '2026-10-04 15:30:00', NULL, NULL),
( 6, 'VNM-EXT-006', 'Công ty TNHH Bao bì Sinh học Á Châu', 'Supplier', '0319882314',
  'KCN Lê Minh Xuân 3, Huyện Bình Chánh, TP. Hồ Chí Minh', 'linh.pham@asiabiopack.com.vn', '(028) 3766 2200',
  'Phạm Thùy Linh', '0319882314 do Sở KH&ĐT TP. Hồ Chí Minh cấp', 'Pending', NULL,
  'VNM-104', '2026-10-05 08:00:00', NULL, NULL),
( 7, 'VNM-EXT-007', 'Công ty CP Vận tải Quốc tế Nam Hải', 'ServiceProvider', '0311223344',
  'Số 45 Nguyễn Tất Thành, Phường 13, Quận 4, TP. Hồ Chí Minh', 'hai.vn@namhaitrans.vn', '(028) 3940 7788',
  'Vũ Nam Hải', '0311223344 do Sở KH&ĐT TP. Hồ Chí Minh cấp', 'Rejected',
  'Hồ sơ phương tiện không đạt chứng nhận kiểm định lạnh',
  'VNM-104', '2026-09-25 11:00:00', 'VNM-001', '2026-09-27 10:00:00'),
( 8, 'VNM-EXT-008', 'Doanh nghiệp Điệp An', 'Customer', '23070591',
  '21 ngõ 62 Phùng Khoang, Nam Từ Liêm, Hà Nội', 'hoaiann220@gmail.com', '(024) 3556 9021',
  'Vũ Khắc Điệp', '23070591 do Sở KH&ĐT TP. Hà Nội cấp', 'Active', NULL,
  'VNM-001', '2026-10-05 11:23:00', 'VNM-001', '2026-10-05 11:25:00'),
( 9, 'VNM-EXT-009', 'Tetra Pak Indochina Ltd', 'Supplier', '0301450009',
  'Tầng 12, Tòa nhà Bitexco, Quận 1, TP. Hồ Chí Minh', 'thanh.ha@tetrapak-indochina.example', '(028) 3915 0009',
  'Nguyễn Thanh Hà', '0301450009 do Sở KH&ĐT TP. Hồ Chí Minh cấp', 'Active', NULL,
  'VNM-104', '2026-06-01 09:00:00', 'VNM-001', '2026-06-02 11:00:00'),
(10, 'VNM-EXT-010', 'GreenTech Energy Solutions JSC', 'ServiceProvider', '0312650010',
  'Khu Công nghệ cao, TP. Thủ Đức, TP. Hồ Chí Minh', 'minh.tuan@greentech-energy.example', '(028) 3736 0010',
  'Lê Minh Tuấn', '0312650010 do Sở KH&ĐT TP. Hồ Chí Minh cấp', 'Active', NULL,
  'VNM-104', '2026-04-20 10:00:00', 'VNM-001', '2026-04-21 09:00:00'),
(11, 'VNM-EXT-011', 'Eurofins Sắc Ký Hải Đăng', 'ServiceProvider', '0302780011',
  'Lô C, KCN Tân Bình, Quận Tân Phú, TP. Hồ Chí Minh', 'lab.contract@eurofins-hdlab.example', '(028) 3816 0011',
  'Hoàng Hải Đăng', '0302780011 do Sở KH&ĐT TP. Hồ Chí Minh cấp', 'Active', NULL,
  'VNM-104', '2025-06-20 09:00:00', 'VNM-001', '2025-06-21 10:00:00'),
(12, 'VNM-EXT-012', 'Nông nghiệp Mộc Châu JSC', 'Supplier', '5500230012',
  'Tiểu khu 26, Thị trấn Mộc Châu, Tỉnh Sơn La', 'quan.le@mocchau-agri.example', '(0212) 386 0012',
  'Lê Văn Quân', '5500230012 do Sở KH&ĐT Tỉnh Sơn La cấp', 'Active', NULL,
  'VNM-104', '2026-03-15 08:30:00', 'VNM-001', '2026-03-16 14:00:00'),
(13, 'VNM-EXT-013', 'Công ty TNHH Bao bì Bình Dương', 'Supplier', '3700640013',
  'KCN Mỹ Phước 2, Thị xã Bến Cát, Tỉnh Bình Dương', 'phuong.tran@binhduong-packaging.example', '(0274) 355 0013',
  'Trần Thị Phương', '3700640013 do Sở KH&ĐT Tỉnh Bình Dương cấp', 'Active', NULL,
  'VNM-104', '2024-01-10 09:00:00', 'VNM-001', '2024-01-11 10:00:00');

-- ============================================================
-- 7. CONTACT  (AccountID = CounterpartyID + 5)
-- ============================================================
INSERT INTO Contact (ContactID, AccountID, CounterpartyID, FullName, JobTitle, Phone, IsPrimary) VALUES
( 1,  6,  1, 'Nguyễn Văn An',     'Giám đốc Vận hành Đối tác',                   '0901234001', TRUE),
( 2,  7,  2, 'Lê Minh Phát',      'Chủ tịch HĐQT & Đại diện Pháp luật',          '0901234002', TRUE),
( 3,  8,  3, 'Võ Thị Hồng',       'Giám đốc Bán hàng & Phân phối',               '0901234003', TRUE),
( 4,  9,  4, 'Đặng Tuấn Kiệt',    'Giám đốc Kỹ thuật Hệ thống',                  '0901234004', TRUE),
( 5, 10,  5, 'Đoàn Quốc Dũng',    'Giám đốc Vận hành Đội xe Lạnh',               '0901234005', TRUE),
( 6, 11,  6, 'Phạm Thùy Linh',    'Giám đốc Dự án Xanh & Phát triển Bền vững',   '0901234006', TRUE),
( 7, 12,  7, 'Vũ Nam Hải',        'Giám đốc Kinh doanh Vận tải',                 '0901234007', TRUE),
( 8, 13,  8, 'Vũ Khắc Điệp',      'Giám đốc điều hành',                          '0901234008', TRUE),
( 9, 14,  9, 'Nguyễn Thanh Hà',   'Giám đốc Quan hệ Khách hàng',                 '0901234009', TRUE),
(10, 15, 10, 'Lê Minh Tuấn',      'Giám đốc Dự án',                              '0901234010', TRUE),
(11, 16, 11, 'Hoàng Hải Đăng',    'Trưởng phòng Hợp đồng',                       '0901234011', TRUE),
(12, 17, 12, 'Lê Văn Quân',       'Giám đốc Kinh doanh',                         '0901234012', TRUE),
(13, 18, 13, 'Trần Thị Phương',   'Phó Giám đốc',                                '0901234013', TRUE);

-- ============================================================
-- 8. CONTRACT  (tổng giá trị = 376.100.000.000 VND)
--    ID: 1=001 2=002 3=003 4=004 5=005 6=006 7=008 8=2025-019 9=2025-045
-- ============================================================
INSERT INTO Contract
(ContractID, ContractCode, ContractTitle, ContractTypeID, DepartmentID, CounterpartyID, OwnerStaffID,
 Summary, ContractValue, Currency, StartDate, EndDate, RenewalReviewDate, AutoRenewalAlert,
 Status, CreatedAt, UpdatedAt) VALUES
(1, 'VM-2026-001',
 'Transportation Service Agreement (Hợp đồng Dịch vụ Vận tải Hàng hóa Nội địa)',
 4, 3, 1, 'VNM-104',
 'Dịch vụ vận chuyển hàng hóa nội địa bằng xe tải và xe lạnh cho các tuyến phân phối miền Nam; có cam kết SLA giao hàng đúng hạn.',
 15000000000.00, 'VND', '2026-01-01', '2026-12-31', '2026-11-01', TRUE,
 'Active', '2025-12-15 10:00:00', '2026-04-01 09:00:00'),
(2, 'VM-2026-002',
 'Raw Material Supply Agreement (Hợp đồng Cung ứng Nguyên liệu Sữa tươi & Phụ gia)',
 1, 2, 2, 'VNM-104',
 'Cung ứng sữa tươi nguyên liệu và phụ gia theo sản lượng cam kết hằng quý; có điều khoản chất lượng ISO 22000 và phạt chậm giao.',
 78500000000.00, 'VND', '2026-05-01', '2027-04-30', '2027-03-01', TRUE,
 'PendingApproval', '2026-10-01 09:00:00', '2026-10-04 14:00:00'),
(3, 'VM-2026-003',
 'Distributor Agreement (Hợp đồng Phân phối Khu vực Đồng bằng sông Cửu Long)',
 2, 3, 3, 'VNM-104',
 'Phân phối sản phẩm Vinamilk tại khu vực Đồng bằng sông Cửu Long; có bảo lãnh ngân hàng và chỉ tiêu doanh số hằng quý.',
 62000000000.00, 'VND', '2025-11-01', '2026-11-01', '2026-10-01', TRUE,
 'Active', '2025-10-20 09:00:00', '2026-10-02 08:30:00'),
(4, 'VM-2026-004',
 'IT Maintenance Service Agreement (Hợp đồng Bảo trì Hệ thống ERP SAP)',
 3, 2, 4, 'VNM-104',
 'Bảo trì, hỗ trợ kỹ thuật và nâng cấp hệ thống ERP SAP trong 24 tháng; cam kết SLA xử lý sự cố theo mức độ ưu tiên.',
 24300000000.00, 'VND', '2026-06-01', '2028-05-31', '2028-04-01', TRUE,
 'PendingReview', '2026-10-02 09:00:00', '2026-10-03 14:00:00'),
(5, 'VM-2026-005',
 'Partnership Agreement on Bio-Packaging (Hợp đồng Hợp tác Phát triển Bao bì Sinh học)',
 5, 2, 9, 'VNM-104',
 'Hợp tác nghiên cứu và phát triển bao bì sinh học, tái chế tuần hoàn cho dòng sản phẩm sữa nước.',
 40000000000.00, 'VND', '2026-07-01', '2029-06-30', '2029-05-01', TRUE,
 'PendingReview', '2026-10-03 09:00:00', '2026-10-06 08:05:00'),
(6, 'VM-2026-006',
 'Solar Rooftop PPA Lease (Hợp đồng Thuê Hệ thống Điện mặt trời Áp mái)',
 6, 2, 10, 'VNM-104',
 'Thuê hệ thống điện mặt trời áp mái tại nhà máy theo mô hình PPA 10 năm; đang chờ bổ sung điều khoản bồi thường và chứng nhận PCCC.',
 85000000000.00, 'VND', '2026-05-01', '2036-04-30', '2036-03-01', TRUE,
 'RevisionRequested', '2026-09-28 09:00:00', '2026-10-04 10:15:00'),
(7, 'VM-2026-008',
 'Alfalfa Hay Supply (Hợp đồng Mua Cỏ Alfalfa & Cỏ khô Chăn nuôi)',
 1, 2, 12, 'VNM-104',
 'Mua cỏ Alfalfa và cỏ khô chăn nuôi cho trang trại bò sữa; giao hàng theo lô hằng tháng.',
 21000000000.00, 'VND', '2026-04-15', '2027-04-14', '2027-03-15', TRUE,
 'Approved', '2026-10-01 14:00:00', '2026-10-04 10:00:00'),
(8, 'VM-2025-019',
 'Microbiological Laboratory Testing (Hợp đồng Kiểm nghiệm Vi sinh Phòng thí nghiệm)',
 3, 2, 11, 'VNM-104',
 'Kiểm nghiệm vi sinh định kỳ mẫu sữa và nguyên liệu tại phòng thí nghiệm được công nhận.',
 4800000000.00, 'VND', '2025-08-01', '2026-08-01', '2026-07-01', TRUE,
 'Active', '2025-07-20 09:00:00', '2026-08-01 17:00:00'),
(9, 'VM-2025-045',
 'Packaging Supply Termination (Thanh lý Hợp đồng Cung ứng Bao bì Bình Dương)',
 1, 2, 13, 'VNM-104',
 'Thanh lý hợp đồng cung ứng bao bì đã hết thời hạn, quyết toán công nợ và bàn giao tồn kho.',
 45500000000.00, 'VND', '2024-02-01', '2026-01-31', NULL, FALSE,
 'Closed', '2024-01-15 09:00:00', '2026-01-15 16:00:00');

-- ============================================================
-- 9. DOCUMENT  (13 tài liệu - khớp màn hình Quản lý tài liệu)
-- ============================================================
INSERT INTO Document (DocumentID, ContractID, DocumentType, DocumentName, IsVisibleToPartner, CreatedAt) VALUES
( 1, 1, 'MainContract',   'VM_2026_001_Transportation_Master_Signed.pdf',  TRUE,  '2026-01-02 10:00:00'),
( 2, 1, 'Appendix',       'Phu_Luc_1_Don_Gia_Va_Chi_Tieu_SLA.pdf',         TRUE,  '2026-01-02 10:30:00'),
( 3, 1, 'CargoInsurance', 'Bao_Hiem_Trach_Nhiem_Dan_Su_Hang_Hoa.pdf',      TRUE,  '2026-01-05 09:20:00'),
( 4, 2, 'MainContract',   'VM_2026_002_RawMaterial_MinhPhat.pdf',          FALSE, '2026-10-01 16:00:00'),
( 5, 2, 'FoodSafetyCert', 'Chung_Nhan_ISO_22000_MinhPhat.pdf',             TRUE,  '2026-10-02 15:00:00'),
( 6, 3, 'MainContract',   'VM_2026_003_Distributor_Agreement_AnPhu.pdf',   TRUE,  '2025-11-01 09:00:00'),
( 7, 3, 'BankGuarantee',  'Thu_Bao_Lanh_Ngan_Hang_BIDV_CanTho.pdf',        TRUE,  '2025-10-25 14:30:00'),
( 8, 4, 'MainContract',   'VM_2026_004_SAP_Maintenance_SLA_Draft.pdf',     FALSE, '2026-10-03 13:30:00'),
( 9, 5, 'MainContract',   'MOU_TetraPak_Vinamilk_CircularPackaging.pdf',   FALSE, '2026-10-04 11:00:00'),
(10, 6, 'MainContract',   'VM_2026_006_Solar_PPA_GreenTech.pdf',           FALSE, '2026-09-30 10:00:00'),
(11, 8, 'MainContract',   'Eurofins_Testing_Master_2025.pdf',              TRUE,  '2025-08-01 09:00:00'),
(12, 7, 'MainContract',   'VM_2026_008_Alfalfa_Hay_Approved.pdf',          TRUE,  '2026-10-04 09:30:00'),
(13, 9, 'MainContract',   'VM_2025_045_ThanhLyHopDong_BinhDuong.pdf',      TRUE,  '2026-01-15 15:00:00');

-- ============================================================
-- 10. CONTRACTVERSION  (file thật nằm ngoài DB; ở đây chỉ lưu đường dẫn)
--     Mỗi tài liệu có >= 1 phiên bản; chỉ 1 phiên bản IsCurrent = TRUE
-- ============================================================
INSERT INTO ContractVersion
(DocumentID, VersionNumber, FilePath, FileSize, FileHash, UploadedBy, ChangeSummary, IsCurrent, UploadedAt) VALUES
( 1, 1, '/storage/contracts/2025/VM-2026-001/VM_2026_001_Transportation_Master_v1.pdf',        4718592, NULL, 2,  'Bản dự thảo gửi thẩm định pháp lý',                        FALSE, '2025-12-20 11:00:00'),
( 1, 2, '/storage/contracts/2026/VM-2026-001/VM_2026_001_Transportation_Master_Signed_v2.pdf', 5033165, NULL, 2,  'Bản chính thức đã ký số (Final)',                           TRUE,  '2026-01-02 10:00:00'),
( 2, 1, '/storage/contracts/2026/VM-2026-001/Phu_Luc_1_Don_Gia_Va_Chi_Tieu_SLA_v1.pdf',       1468006, NULL, 2,  'Phụ lục 1 - Đơn giá và chỉ tiêu SLA (v1.0)',               TRUE,  '2026-01-02 10:30:00'),
( 3, 1, '/storage/contracts/2026/VM-2026-001/Bao_Hiem_Trach_Nhiem_Dan_Su_Hang_Hoa_v1.pdf',    3250586, NULL, 6,  'Bản gốc bảo hiểm do đối tác nộp (v1.0)',                    TRUE,  '2026-01-05 09:20:00'),
( 4, 1, '/storage/contracts/2026/VM-2026-002/VM_2026_002_RawMaterial_MinhPhat_v1.pdf',        5138022, NULL, 2,  'Dự thảo ban đầu (v1.0)',                                    FALSE, '2026-10-01 16:00:00'),
( 4, 2, '/storage/contracts/2026/VM-2026-002/VM_2026_002_RawMaterial_MinhPhat_v2.pdf',        5242880, NULL, 2,  'Cập nhật điều khoản giá và phạt chậm giao (v1.1)',          FALSE, '2026-10-02 11:00:00'),
( 4, 3, '/storage/contracts/2026/VM-2026-002/VM_2026_002_RawMaterial_MinhPhat_v3.pdf',        5452595, NULL, 2,  'Hiệu chỉnh theo góp ý của Pháp chế (v1.2)',                 TRUE,  '2026-10-03 09:00:00'),
( 5, 1, '/storage/contracts/2026/VM-2026-002/Chung_Nhan_ISO_22000_MinhPhat_v1.pdf',           2621440, NULL, 7,  'Chứng nhận ISO 22000 do đối tác nộp (v1.0)',                TRUE,  '2026-10-02 15:00:00'),
( 6, 1, '/storage/contracts/2025/VM-2026-003/VM_2026_003_Distributor_Agreement_AnPhu_v1.pdf', 6396314, NULL, 2,  'Bản chính thức đã ký (Final)',                              TRUE,  '2025-11-01 09:00:00'),
( 7, 1, '/storage/contracts/2025/VM-2026-003/Thu_Bao_Lanh_Ngan_Hang_BIDV_CanTho_v1.pdf',     1887437, NULL, 8,  'Thư bảo lãnh ngân hàng do đối tác nộp (v1.0)',              TRUE,  '2025-10-25 14:30:00'),
( 8, 1, '/storage/contracts/2026/VM-2026-004/VM_2026_004_SAP_Maintenance_SLA_Draft_v1.pdf',   3565158, NULL, 2,  'Dự thảo hợp đồng và SLA (v1.0)',                            TRUE,  '2026-10-03 13:30:00'),
( 9, 1, '/storage/contracts/2026/VM-2026-005/MOU_TetraPak_Vinamilk_CircularPackaging_v1.pdf',  2202009, NULL, 2,  'Bản MOU dự thảo (v0.9)',                                    TRUE,  '2026-10-04 11:00:00'),
(10, 1, '/storage/contracts/2026/VM-2026-006/VM_2026_006_Solar_PPA_GreenTech_v1.pdf',         4928307, NULL, 2,  'Bản gốc nhận từ đối tác (v1.0)',                           TRUE,  '2026-09-30 10:00:00'),
(11, 1, '/storage/contracts/2025/VM-2025-019/Eurofins_Testing_Master_2025_v1.pdf',            3355443, NULL, 2,  'Bản chính thức đã ký (Final)',                              TRUE,  '2025-08-01 09:00:00'),
(12, 1, '/storage/contracts/2026/VM-2026-008/VM_2026_008_Alfalfa_Hay_Approved_v1.pdf',        4718592, NULL, 2,  'Bản đã được Lãnh đạo phê duyệt (Final)',                    TRUE,  '2026-10-04 09:30:00'),
(13, 1, '/storage/contracts/2026/VM-2025-045/VM_2025_045_ThanhLyHopDong_BinhDuong_v1.pdf',    2516582, NULL, 2,  'Biên bản thanh lý hợp đồng (Final)',                        TRUE,  '2026-01-15 15:00:00');

-- Giả lập mã băm SHA-256 từ đường dẫn file + số phiên bản
UPDATE ContractVersion
SET FileHash = SHA2(CONCAT(FilePath, '#', VersionNumber), 256)
WHERE FileHash IS NULL;

-- ============================================================
-- 11. CONTRACTREQUEST
-- ============================================================
INSERT INTO ContractRequest
(RequestID, ContractID, RequestedBy, RequestType, AssignedSide, Status, Description, RequestedAt,
 ResponseNote, RespondedBy, RespondedAt) VALUES
(1, 1, 2, 'Supplement', 'Counterparty', 'Completed',
 'Đề nghị bổ sung Bảo hiểm trách nhiệm dân sự hàng hóa trước khi ký chính thức.',
 '2026-01-03 09:00:00',
 'Đã tải lên bản gốc Bảo hiểm trách nhiệm dân sự hàng hóa.', 6, '2026-01-05 09:30:00'),
(2, 6, 1, 'Revision', 'Internal', 'Pending',
 'Cần bổ sung điều khoản cam kết bồi thường thiệt hại đột mái xưởng khi giông lốc và giấy chứng nhận kiểm định an toàn PCCC mới nhất.',
 '2026-10-04 10:15:00', NULL, NULL, NULL),
(3, 7, 2, 'Supplement', 'Counterparty', 'Pending',
 'Đề nghị đối tác nộp bản gốc thư bảo lãnh thực hiện hợp đồng.',
 '2026-10-04 16:00:00', NULL, NULL, NULL),
(4, 2, 2, 'InternalApproval', 'Internal', 'InProgress',
 'Trình Lãnh đạo ký duyệt điện tử hợp đồng cung ứng nguyên liệu sữa tươi và phụ gia.',
 '2026-10-04 14:00:00', NULL, NULL, NULL),
(5, 4, 2, 'ExternalReview', 'Counterparty', 'Pending',
 'Gửi bản dự thảo SLA cho Tech Solutions Vietnam xem xét các chỉ tiêu hỗ trợ kỹ thuật.',
 '2026-10-05 09:00:00', NULL, NULL, NULL);

-- ============================================================
-- 12. REVIEW  (soát xét pháp chế; SLA trung bình ~ 1,8 ngày)
-- ============================================================
INSERT INTO Review
(ContractID, RequestID, ReviewerAccountID, ReviewStatus, Comments, SubmittedAt, ReviewedAt) VALUES
(1, NULL, 4, 'Approved', 'Điều khoản bồi thường và bảo hiểm phù hợp quy định hiện hành.',                 '2025-12-20 11:00:00', '2025-12-22 10:00:00'),
(3, NULL, 4, 'Approved', 'Bảo lãnh ngân hàng đầy đủ; chỉ tiêu doanh số rõ ràng.',                          '2025-10-28 10:00:00', '2025-10-30 09:30:00'),
(8, NULL, 4, 'Approved', 'Phạm vi kiểm nghiệm và trách nhiệm bảo mật mẫu phù hợp.',                        '2025-07-25 09:00:00', '2025-07-27 11:00:00'),
(9, NULL, 4, 'Approved', 'Biên bản thanh lý hợp lệ, công nợ đã đối chiếu.',                                '2024-01-20 09:00:00', '2024-01-22 10:00:00'),
(7, NULL, 4, 'Approved', 'Điều khoản chất lượng và giao nhận hợp lệ.',                                     '2026-10-02 10:00:00', '2026-10-03 11:00:00'),
(2, NULL, 4, 'Approved', 'Đã rà soát điều khoản giá, bảo mật và chế tài; đề nghị trình Lãnh đạo ký duyệt.', '2026-10-03 09:00:00', '2026-10-04 14:00:00'),
(6, 2,    4, 'RevisionRequired',
 'Cần bổ sung điều khoản bồi thường thiệt hại đột mái xưởng khi giông lốc và giấy chứng nhận kiểm định an toàn PCCC.',
 '2026-09-30 15:00:00', '2026-10-04 10:15:00'),
(4, NULL, 4, 'Pending', NULL, '2026-10-03 14:00:00', NULL),
(5, NULL, 1, 'Pending', NULL, '2026-10-06 08:05:00', NULL);

-- ============================================================
-- 13. APPROVAL  (ký duyệt điện tử của Lãnh đạo)
-- ============================================================
INSERT INTO Approval
(ContractID, RequestID, ApproverAccountID, ApprovalLevel, DocumentID, ApprovalStatus, Comments,
 SubmittedAt, ApprovedAt, SignatureStatus, SignatureHash) VALUES
(1, NULL, 1, 1,  1, 'Approved', 'Đồng ý ký duyệt.',                              '2025-12-22 10:00:00', '2025-12-23 09:00:00', 'Success', NULL),
(3, NULL, 1, 1,  6, 'Approved', 'Đồng ý ký duyệt.',                              '2025-10-30 14:00:00', '2025-10-31 16:00:00', 'Success', NULL),
(8, NULL, 1, 1, 11, 'Approved', 'Đồng ý ký duyệt.',                              '2025-07-27 14:00:00', '2025-07-30 10:00:00', 'Success', NULL),
(9, NULL, 1, 1, 13, 'Approved', 'Đồng ý thanh lý hợp đồng.',                     '2026-01-12 09:00:00', '2026-01-15 14:30:00', 'Success', NULL),
-- Hợp đồng 008: lần ký đầu thất bại (chứng thư số hết hạn), ký lại thành công
(7, NULL, 1, 1, 12, 'Pending',  'Ký số thất bại do chứng thư số hết hạn; thực hiện ký lại.',
                                                                                  '2026-10-03 14:00:00', NULL,                  'Failed',  NULL),
(7, NULL, 1, 1, 12, 'Approved', 'Đồng ý ký duyệt sau khi gia hạn chứng thư số.', '2026-10-04 09:40:00', '2026-10-04 10:00:00', 'Success', NULL),
-- Hợp đồng 002: đang chờ Lãnh đạo ký (liên kết yêu cầu InternalApproval)
(2, 4,    1, 1,  4, 'Pending',  NULL,                                            '2026-10-04 14:00:00', NULL,                  NULL,      NULL);

UPDATE Approval
SET SignatureHash = SHA2(CONCAT('sig-', ApprovalID, '-', ContractID, '-', DocumentID), 256)
WHERE SignatureStatus = 'Success';

-- ============================================================
-- 14. STATUSHISTORY  (lịch sử đổi trạng thái; 1 = Lãnh đạo, 2 = Mai, 4 = Pháp chế)
-- ============================================================
INSERT INTO StatusHistory (ContractID, ChangedBy, OldStatus, NewStatus, ChangeReason, ChangedAt) VALUES
-- VM-2026-001
(1, 2, NULL,              'Draft',             'Tạo hồ sơ hợp đồng mới',                                  '2025-12-15 10:00:00'),
(1, 2, 'Draft',           'PendingReview',     'Gửi thẩm định pháp lý',                                   '2025-12-20 11:00:00'),
(1, 4, 'PendingReview',   'PendingApproval',   'Pháp chế đã soát xét xong',                               '2025-12-22 10:00:00'),
(1, 1, 'PendingApproval', 'Approved',          'Lãnh đạo ký duyệt điện tử',                               '2025-12-23 09:00:00'),
(1, 1, 'Approved',        'Active',            'Tự động kích hoạt theo ngày bắt đầu hiệu lực',            '2026-01-01 00:05:00'),
-- VM-2026-002
(2, 2, NULL,              'Draft',             'Tạo hồ sơ hợp đồng mới',                                  '2026-10-01 09:00:00'),
(2, 2, 'Draft',           'PendingReview',     'Gửi thẩm định pháp lý',                                   '2026-10-03 09:00:00'),
(2, 4, 'PendingReview',   'PendingApproval',   'Pháp chế đã soát xét xong, trình Lãnh đạo',               '2026-10-04 14:00:00'),
-- VM-2026-003
(3, 2, NULL,              'Draft',             'Tạo hồ sơ hợp đồng mới',                                  '2025-10-20 09:00:00'),
(3, 2, 'Draft',           'PendingReview',     'Gửi thẩm định pháp lý',                                   '2025-10-28 10:00:00'),
(3, 4, 'PendingReview',   'PendingApproval',   'Pháp chế đã soát xét xong',                               '2025-10-30 09:30:00'),
(3, 1, 'PendingApproval', 'Approved',          'Lãnh đạo ký duyệt điện tử',                               '2025-10-31 16:00:00'),
(3, 1, 'Approved',        'Active',            'Tự động kích hoạt theo ngày bắt đầu hiệu lực',            '2025-11-01 00:05:00'),
-- VM-2026-004
(4, 2, NULL,              'Draft',             'Tạo hồ sơ hợp đồng mới',                                  '2026-10-02 09:00:00'),
(4, 2, 'Draft',           'PendingReview',     'Gửi thẩm định pháp lý',                                   '2026-10-03 14:00:00'),
-- VM-2026-005
(5, 2, NULL,              'Draft',             'Tạo hồ sơ hợp đồng mới',                                  '2026-10-03 09:00:00'),
(5, 2, 'Draft',           'PendingReview',     'Gửi thẩm định pháp lý',                                   '2026-10-06 08:05:00'),
-- VM-2026-006
(6, 2, NULL,              'Draft',             'Tạo hồ sơ hợp đồng mới',                                  '2026-09-28 09:00:00'),
(6, 2, 'Draft',           'PendingReview',     'Gửi thẩm định pháp lý',                                   '2026-09-30 15:00:00'),
(6, 1, 'PendingReview',   'RevisionRequested', 'Yêu cầu bổ sung điều khoản bồi thường và chứng nhận PCCC', '2026-10-04 10:15:00'),
-- VM-2026-008
(7, 2, NULL,              'Draft',             'Tạo hồ sơ hợp đồng mới',                                  '2026-10-01 14:00:00'),
(7, 2, 'Draft',           'PendingReview',     'Gửi thẩm định pháp lý',                                   '2026-10-02 10:00:00'),
(7, 4, 'PendingReview',   'PendingApproval',   'Pháp chế đã soát xét xong',                               '2026-10-03 11:00:00'),
(7, 1, 'PendingApproval', 'Approved',          'Lãnh đạo ký duyệt điện tử',                               '2026-10-04 10:00:00'),
-- VM-2025-019
(8, 2, NULL,              'Draft',             'Tạo hồ sơ hợp đồng mới',                                  '2025-07-20 09:00:00'),
(8, 2, 'Draft',           'PendingReview',     'Gửi thẩm định pháp lý',                                   '2025-07-25 09:00:00'),
(8, 4, 'PendingReview',   'PendingApproval',   'Pháp chế đã soát xét xong',                               '2025-07-27 11:00:00'),
(8, 1, 'PendingApproval', 'Approved',          'Lãnh đạo ký duyệt điện tử',                               '2025-07-30 10:00:00'),
(8, 1, 'Approved',        'Active',            'Tự động kích hoạt theo ngày bắt đầu hiệu lực',            '2025-08-01 00:05:00'),
-- VM-2025-045
(9, 2, NULL,              'Draft',             'Tạo hồ sơ hợp đồng mới',                                  '2024-01-15 09:00:00'),
(9, 2, 'Draft',           'PendingReview',     'Gửi thẩm định pháp lý',                                   '2024-01-20 09:00:00'),
(9, 4, 'PendingReview',   'PendingApproval',   'Pháp chế đã soát xét xong',                               '2024-01-22 10:00:00'),
(9, 1, 'PendingApproval', 'Approved',          'Lãnh đạo ký duyệt điện tử',                               '2024-01-25 10:00:00'),
(9, 1, 'Approved',        'Active',            'Tự động kích hoạt theo ngày bắt đầu hiệu lực',            '2024-02-01 00:05:00'),
(9, 1, 'Active',          'Closed',            'Thanh lý hợp đồng theo biên bản thỏa thuận',              '2026-01-15 15:00:00');

-- ============================================================
-- 15. AUDITLOG
-- ============================================================
INSERT INTO AuditLog (AccountID, Action, EntityName, EntityID, Details, IPAddress, CreatedAt) VALUES
(2,    'LOGIN',                'Account',      2,  '{"role":"Staff"}',                                                         '10.10.20.15',    '2026-10-05 09:10:00'),
(1,    'LOGIN',                'Account',      1,  '{"role":"Manager/Admin"}',                                                 '10.10.20.11',    '2026-10-05 08:50:00'),
(6,    'LOGIN',                'Account',      6,  '{"role":"External","company":"ABC Logistics Co., Ltd."}',                  '203.113.152.20', '2026-10-05 10:15:00'),
(2,    'CREATE',               'Contract',     2,  '{"contractCode":"VM-2026-002"}',                                           '10.10.20.15',    '2026-10-01 09:00:00'),
(2,    'UPLOAD_VERSION',       'Document',     4,  '{"versionNumber":3,"fileName":"VM_2026_002_RawMaterial_MinhPhat.pdf"}',    '10.10.20.15',    '2026-10-03 09:00:00'),
(2,    'SUBMIT_REVIEW',        'Contract',     4,  '{"contractCode":"VM-2026-004"}',                                           '10.10.20.15',    '2026-10-03 14:00:00'),
(4,    'COMPLETE_REVIEW',      'Contract',     2,  '{"result":"Approved"}',                                                    '10.10.20.18',    '2026-10-04 14:00:00'),
(1,    'REQUEST_REVISION',     'Contract',     6,  '{"requestId":2}',                                                          '10.10.20.11',    '2026-10-04 10:15:00'),
(1,    'SIGN_CONTRACT',        'Contract',     7,  '{"result":"Failed","reason":"Certificate expired"}',                       '10.10.20.11',    '2026-10-03 14:20:00'),
(1,    'SIGN_CONTRACT',        'Contract',     7,  '{"result":"Success"}',                                                     '10.10.20.11',    '2026-10-04 10:00:00'),
(1,    'APPROVE_COUNTERPARTY', 'Counterparty', 8,  '{"code":"VNM-EXT-008"}',                                                   '10.10.20.11',    '2026-10-05 11:25:00'),
(1,    'REJECT_COUNTERPARTY',  'Counterparty', 7,  '{"code":"VNM-EXT-007","reason":"Hồ sơ phương tiện không đạt"}',            '10.10.20.11',    '2026-09-27 10:00:00'),
(6,    'UPLOAD_VERSION',       'Document',     3,  '{"versionNumber":1,"fileName":"Bao_Hiem_Trach_Nhiem_Dan_Su_Hang_Hoa.pdf"}', '203.113.152.20', '2026-01-05 09:20:00'),
(NULL, 'EXPIRY_ALERT',         'Contract',     3,  '{"daysLeft":30,"channel":"system"}',                                       NULL,             '2026-10-02 08:30:00');

-- ============================================================
-- 16. NOTIFICATION
-- ============================================================
INSERT INTO Notification
(AccountID, ContractID, RequestID, CounterpartyID, Title, Message, NotificationType, IsRead, CreatedAt, ReadAt) VALUES
-- Trần Thị Mai (nhân viên)
(2, NULL, NULL, 8, 'Doanh nghiệp đối tác đã được phê duyệt',
 'Lãnh đạo đã phê duyệt doanh nghiệp "Doanh nghiệp Điệp An". Tài khoản người dùng đại diện đã được kích hoạt.',
 'Success', FALSE, '2026-10-05 11:25:00', NULL),
(2, 6, 2, NULL, 'Yêu cầu bổ sung tài liệu PCCC',
 'Lãnh đạo yêu cầu bổ sung giấy nghiệm thu PCCC cho hợp đồng VM-2026-006 (Điện mặt trời áp mái).',
 'Warning', FALSE, '2026-10-04 10:15:00', NULL),
(2, 3, NULL, NULL, 'Hợp đồng sắp đến hạn gia hạn',
 'Hợp đồng VM-2026-003 (Phân phối An Phú) còn 30 ngày hiệu lực. Vui lòng tiến hành quy trình gia hạn.',
 'Warning', TRUE, '2026-10-02 08:30:00', '2026-10-02 09:00:00'),
-- TS. Vũ Đình Khoa (Lãnh đạo)
(1, 5, NULL, NULL, 'Hợp đồng cần thẩm định pháp lý',
 'Hợp đồng VM-2026-005 vừa được gửi thẩm định bởi Trần Thị Mai.',
 'Warning', FALSE, '2026-10-06 08:05:00', NULL),
(1, NULL, NULL, 5, 'Hồ sơ doanh nghiệp đối tác mới chờ duyệt',
 'Chuyên viên Trần Thị Mai vừa trình duyệt hồ sơ "Vận tải & Kho lạnh Tân Cảng Logistics" và tài khoản người dùng đại diện.',
 'Warning', FALSE, '2026-10-04 15:30:00', NULL),
(1, 2, 4, NULL, 'Hợp đồng cần phê duyệt',
 'Hợp đồng VM-2026-002 (Cung ứng Nguyên liệu Sữa tươi) đã qua bước thẩm định pháp lý và đang chờ TS. Vũ Đình Khoa phê duyệt.',
 'Warning', FALSE, '2026-10-04 14:00:00', NULL),
(1, 3, NULL, NULL, 'Hợp đồng sắp đến hạn gia hạn',
 'Hợp đồng VM-2026-003 (Phân phối An Phú) còn 30 ngày hiệu lực. Vui lòng tiến hành quy trình gia hạn.',
 'Warning', TRUE, '2026-10-02 08:30:00', '2026-10-02 10:00:00'),
(1, 4, NULL, NULL, 'Hồ sơ thẩm định mới gửi đến',
 'Chuyên viên Trần Thị Mai vừa gửi hồ sơ hợp đồng VM-2026-004 (Bảo trì hệ thống ERP SAP) để Ban Pháp chế soát xét.',
 'Info', FALSE, '2026-10-03 14:00:00', NULL),
-- Trần Minh Anh (Pháp chế)
(4, 4, NULL, NULL, 'Hợp đồng mới cần soát xét',
 'Hợp đồng VM-2026-004 đã được gửi sang Ban Pháp chế để soát xét.',
 'Info', FALSE, '2026-10-03 14:00:00', NULL),
-- Người dùng đối tác
(6, NULL, NULL, 1, 'Tài khoản đối tác đã được kích hoạt',
 'Doanh nghiệp ABC Logistics Co., Ltd. đã được Lãnh đạo Vinamilk phê duyệt. Bạn có thể đăng nhập Cổng Đối tác.',
 'Success', TRUE, '2025-11-11 14:00:00', '2025-11-12 08:30:00'),
(13, NULL, NULL, 8, 'Tài khoản đối tác đã được kích hoạt',
 'Doanh nghiệp Điệp An đã được Lãnh đạo Vinamilk phê duyệt. Bạn có thể đăng nhập Cổng Đối tác.',
 'Success', FALSE, '2026-10-05 11:25:00', NULL),
(17, 7, 3, NULL, 'Yêu cầu bổ sung hồ sơ hợp đồng',
 'Vinamilk đề nghị Nông nghiệp Mộc Châu JSC nộp bản gốc thư bảo lãnh thực hiện hợp đồng VM-2026-008.',
 'Warning', FALSE, '2026-10-04 16:00:00', NULL),
(9, 4, 5, NULL, 'Đề nghị xem xét dự thảo SLA',
 'Vinamilk gửi bản dự thảo hợp đồng VM-2026-004 để Tech Solutions Vietnam xem xét các chỉ tiêu hỗ trợ kỹ thuật.',
 'Info', FALSE, '2026-10-05 09:00:00', NULL);

COMMIT;

-- ============================================================
-- KIỂM TRA SAU KHI CHÈN (chạy từng câu để đối chiếu với demo)
-- ============================================================
-- 1) Số dòng từng bảng (kỳ vọng: 3,18,3,5,13,13,6,9,13,16,5,9,7,35,14,13)
-- SELECT 'Role', COUNT(*) FROM Role UNION ALL SELECT 'Account', COUNT(*) FROM Account
-- UNION ALL SELECT 'Department', COUNT(*) FROM Department UNION ALL SELECT 'Staff', COUNT(*) FROM Staff
-- UNION ALL SELECT 'Counterparty', COUNT(*) FROM Counterparty UNION ALL SELECT 'Contact', COUNT(*) FROM Contact
-- UNION ALL SELECT 'ContractType', COUNT(*) FROM ContractType UNION ALL SELECT 'Contract', COUNT(*) FROM Contract
-- UNION ALL SELECT 'Document', COUNT(*) FROM Document UNION ALL SELECT 'ContractVersion', COUNT(*) FROM ContractVersion
-- UNION ALL SELECT 'ContractRequest', COUNT(*) FROM ContractRequest UNION ALL SELECT 'Review', COUNT(*) FROM Review
-- UNION ALL SELECT 'Approval', COUNT(*) FROM Approval UNION ALL SELECT 'StatusHistory', COUNT(*) FROM StatusHistory
-- UNION ALL SELECT 'AuditLog', COUNT(*) FROM AuditLog UNION ALL SELECT 'Notification', COUNT(*) FROM Notification;

-- 2) Tổng giá trị danh mục hợp đồng (kỳ vọng 376.100.000.000 VND)
-- SELECT Currency, SUM(ContractValue) AS TongGiaTri FROM Contract GROUP BY Currency;

-- 3) Trạng thái hiển thị (Sắp hết hạn / Đã hết hạn được TÍNH từ EndDate, không lưu cứng)
-- SET @today = '2026-10-13';
-- SELECT ContractCode, Status, EndDate,
--        CASE WHEN Status = 'Active' AND EndDate < @today THEN 'Đã hết hạn'
--             WHEN Status = 'Active' AND EndDate <= DATE_ADD(@today, INTERVAL 30 DAY) THEN 'Sắp hết hạn'
--             ELSE Status END AS TrangThaiHienThi
-- FROM Contract ORDER BY ContractCode;

-- 4) Thời gian thẩm định trung bình (ngày) - báo cáo SLA của Admin
-- SELECT ROUND(AVG(TIMESTAMPDIFF(HOUR, SubmittedAt, ReviewedAt)) / 24, 1) AS SLA_ngay
-- FROM Review WHERE ReviewedAt IS NOT NULL;

-- 5) Cô lập dữ liệu: hợp đồng mà ABC Logistics (AccountID = 6) được phép xem
-- SELECT c.ContractCode, c.ContractTitle
-- FROM Contract c
-- JOIN Contact ct ON ct.CounterpartyID = c.CounterpartyID
-- WHERE ct.AccountID = 6;

-- 6) Phiên bản hiện hành của từng tài liệu
-- SELECT d.DocumentName, v.VersionNumber, v.FileSize, v.UploadedAt
-- FROM Document d JOIN ContractVersion v ON v.DocumentID = d.DocumentID AND v.IsCurrent = TRUE
-- ORDER BY d.DocumentID;
