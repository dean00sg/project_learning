-- =====================================================================
-- ระบบลา/ขออนุญาตนักเรียน (Leave & Permission Request System)
-- ตารางเฉพาะของระบบนี้ที่ใช้โดยไฟล์ในโฟลเดอร์ leave_system/
-- (ตารางผู้ใช้งาน/ห้องเรียน อยู่ที่ database/users.sql, database/repair_system.sql
--  — ใช้ร่วมกับระบบอื่นด้วย)
--
-- Flow: นักเรียนยื่นคำขอ -> ครูที่ปรึกษาของห้องนักเรียนคนนั้นอนุมัติ/ไม่อนุมัติ
-- -> ถ้าประเภทการลานั้นต้องผ่านฝ่ายปกครองด้วย (requires_discipline_approval)
-- จะไปรออนุมัติซ้อนอีกชั้นกับครูฝ่ายปกครอง (staff_type_code = 'discipline')
-- ก่อนถึงจะอนุมัติสมบูรณ์ — ประเภทที่ไม่ต้องผ่านฝ่ายปกครอง (เช่น ลาป่วย/ลากิจ)
-- จะอนุมัติสมบูรณ์ทันทีที่ครูที่ปรึกษาอนุมัติ
--
-- status: PENDING_ADVISOR -> PENDING_DISCIPLINE (เฉพาะประเภทที่ต้องผ่าน 2 ชั้น)
--         -> APPROVED | REJECTED | CANCELLED (นักเรียนยกเลิกเองได้ถ้ายังไม่อนุมัติ)
-- =====================================================================


-- ---------------------------------------------------------------------
-- leave_types : ประเภทการลา/ขออนุญาต (ฝ่ายวิชาการ/แอดมินตั้งค่าไว้ล่วงหน้า)
--
-- requires_discipline_approval = 1 เท่านั้นที่ต้องผ่านครูฝ่ายปกครองอนุมัติซ้อน
-- อีกชั้นหลังครูที่ปรึกษาอนุมัติ (ตัวอย่างเช่น "ขออนุญาตออกนอกโรงเรียน")
-- ---------------------------------------------------------------------
CREATE TABLE `leave_types` (
    `leave_type_id`                  BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `leave_type_name`                VARCHAR(200)    NOT NULL,
    `detail`                         TEXT            DEFAULT NULL,
    `requires_discipline_approval`   TINYINT(1)      NOT NULL DEFAULT 0,
    `is_active`                      TINYINT(1)      NOT NULL DEFAULT 1,
    PRIMARY KEY (`leave_type_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;


-- ---------------------------------------------------------------------
-- leave_requests : คำขอลา/ขออนุญาตของนักเรียนแต่ละครั้ง
--
-- advisor_approved_by / discipline_approved_by แยกกันเก็บ เพราะมีผู้อนุมัติ
-- ได้ 2 ระดับไม่เท่ากัน — ต้องตอบได้ว่าใครอนุมัติขั้นไหน (ทั้งสองฟิลด์
-- บันทึกไว้ไม่ว่าผลจะอนุมัติหรือไม่อนุมัติ ผลจริงดูจาก status/reject_reason)
-- ---------------------------------------------------------------------
CREATE TABLE `leave_requests` (
    `request_id`               BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `leave_type_id`             INT             NOT NULL,       -- FK -> leave_types.leave_type_id
    `student_id`                INT             NOT NULL,       -- FK -> user_students.student_id (ดู database/users.sql)
    `classroom_id`              INT             NOT NULL,       -- FK -> classroom.classroom_id (บันทึกไว้ตอนยื่นคำขอ)
    `start_date`                DATE            NOT NULL,
    `end_date`                  DATE            NOT NULL,
    `reason`                    TEXT            NOT NULL,
    `evidence_image`            VARCHAR(255)    DEFAULT NULL,
    `request_at`                DATETIME        NOT NULL DEFAULT NOW(),

    `advisor_approved_by`       INT             DEFAULT NULL,   -- FK -> user_accounts.user_id (ครูที่ปรึกษาที่ดำเนินการ)
    `advisor_approved_at`       DATETIME        DEFAULT NULL,

    `discipline_approved_by`    INT             DEFAULT NULL,   -- FK -> user_accounts.user_id (ครูฝ่ายปกครองที่ดำเนินการ)
    `discipline_approved_at`    DATETIME        DEFAULT NULL,

    `reject_reason`             TEXT            DEFAULT NULL,
    `status`                    VARCHAR(30)     NOT NULL DEFAULT 'PENDING_ADVISOR',
    -- 'PENDING_ADVISOR' | 'PENDING_DISCIPLINE' | 'APPROVED' | 'REJECTED' | 'CANCELLED'

    PRIMARY KEY (`request_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;


-- ---------------------------------------------------------------------
-- ข้อมูลตัวอย่างประเภทการลา
-- ---------------------------------------------------------------------
INSERT INTO `leave_types` (`leave_type_name`, `detail`, `requires_discipline_approval`, `is_active`) VALUES
('ลาป่วย', 'ลาเนื่องจากเจ็บป่วย แนบใบรับรองแพทย์ถ้ามี', 0, 1),
('ลากิจ', 'ลาเนื่องจากมีธุระส่วนตัว', 0, 1),
('ขออนุญาตออกนอกโรงเรียน', 'ขออนุญาตออกนอกบริเวณโรงเรียนระหว่างเวลาเรียน ต้องผ่านครูฝ่ายปกครองอนุมัติซ้อน', 1, 1),
('ขออนุญาตเข้าร่วมกิจกรรม', 'ขออนุญาตเข้าร่วมกิจกรรมภายนอกที่ไม่ใช่กิจกรรมของโรงเรียน', 0, 1);
