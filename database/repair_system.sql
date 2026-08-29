-- =====================================================================
-- ระบบแจ้งซ่อมอุปกรณ์ (Repair System)
-- ตารางเฉพาะของระบบนี้ที่ใช้โดยไฟล์ในโฟลเดอร์ repair_system/
-- (ตารางผู้ใช้งาน user_accounts / user_students / user_staffs
--  แยกไว้ที่ database/users.sql เพราะใช้ร่วมกับระบบอื่นด้วย)
-- =====================================================================


-- ---------------------------------------------------------------------
-- classroom : ห้องเรียน
--
-- advisor_staff_id เก็บเป็น JSON array ของ user_staffs.user_id เช่น "[1]"
-- หรือ "[1,4]" ถ้ามีครูที่ปรึกษาหลายคน — ต้องเป็น array เสมอ (ห้ามเก็บ
-- แค่ตัวเลขเปล่า ๆ เช่น "1" เพราะ JSON_CONTAINS / isAdvisorOf() จะตรวจไม่เจอ
-- ---------------------------------------------------------------------
CREATE TABLE `classroom` (
    `classroom_id`          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `classroom_type`        VARCHAR(20)     DEFAULT NULL,
    `classroom_number_code` VARCHAR(20)     DEFAULT NULL,
    `classroom_level`       INT             DEFAULT NULL,
    `advisor_staff_id`      VARCHAR(20)     DEFAULT NULL,  -- JSON array ของ staff user_id เช่น "[1]"
    `building`              VARCHAR(20)     DEFAULT NULL,
    PRIMARY KEY (`classroom_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;


-- ---------------------------------------------------------------------
-- repair_requests : รายการแจ้งซ่อมอุปกรณ์
--
-- approved_by IS NULL = รอครูที่ปรึกษาอนุมัติ
-- approved_by ตั้งค่าแล้ว = อนุมัติแล้ว (ดูสถานะซ่อมต่อได้จาก repair_process)
-- ---------------------------------------------------------------------
CREATE TABLE `repair_requests` (
    `request_id`       BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `request_type`     VARCHAR(10)     DEFAULT NULL,
    `classroom_id`     INT             NOT NULL,               -- FK -> classroom.classroom_id
    `requester_id`     INT             NOT NULL,               -- FK -> user_accounts.user_id (ดู database/users.sql)
    `request_datetime` DATETIME        NOT NULL DEFAULT NOW(),
    `approved_by`      INT             DEFAULT NULL,           -- FK -> user_accounts.user_id (ครูที่ปรึกษา)
    `approved_at`      DATETIME        DEFAULT NULL,
    `repair_detail`    TEXT            NOT NULL,
    `request_image`    VARCHAR(255)    DEFAULT NULL,
    PRIMARY KEY (`request_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;


-- ---------------------------------------------------------------------
-- repair_process : ติดตามการดำเนินการซ่อมของเจ้าหน้าที่ (เฉพาะบุคลากรที่
-- staff_type_code = 'technician') หลังจากรายการแจ้งซ่อมได้รับอนุมัติแล้ว
--
-- 1 request_id มีได้แค่ 1 แถว (UNIQUE) — สร้างแถวตอนกด "เริ่มซ่อม"
-- แล้ว UPDATE เป็น 'done' ตอนกด "เสร็จสิ้น"
--
-- status_repair: 'repairing' (กำลังซ่อม) | 'done' (เสร็จสิ้น)
-- disbursement_order_number / status_disbursement: เตรียมไว้สำหรับกรณี
-- ต้องเบิกจ่ายอะไหล่ ยังไม่มีไฟล์ในระบบที่ใช้งาน 2 คอลัมน์นี้
-- ---------------------------------------------------------------------
CREATE TABLE `repair_process` (
    `repair_id`                 BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `request_id`                INT             NOT NULL,      -- FK -> repair_requests.request_id (UNIQUE)
    `repair_datetime`           DATETIME        NOT NULL DEFAULT NOW(),
    `staff_repair_id`           INT             DEFAULT NULL,  -- FK -> user_staffs.staff_id (ดู database/users.sql)
    `staff_repair_detail`       TEXT            DEFAULT NULL,
    `staff_repair_image`        VARCHAR(255)    DEFAULT NULL,
    `disbursement_order_number` VARCHAR(100)    DEFAULT NULL,
    `status_disbursement`       VARCHAR(50)     DEFAULT NULL,
    `status_repair`             VARCHAR(50)     DEFAULT NULL,  -- 'repairing' | 'done'
    `status_datetime`           DATETIME        DEFAULT NULL,
    PRIMARY KEY (`repair_id`),
    UNIQUE KEY `uniq_request_id` (`request_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
