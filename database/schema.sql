-- =====================================================================
-- project_learning : Database Schema
-- โครงสร้างตารางทั้งหมดของระบบจัดการโรงเรียน
--
-- ไฟล์นี้เป็นเอกสารอ้างอิงโครงสร้างฐานข้อมูล
-- แต่ละไฟล์ PHP ที่เชื่อมกับตารางเหล่านี้จะมีคอมเมนต์อ้างอิงกลับมาที่นี่
-- =====================================================================


-- ---------------------------------------------------------------------
-- user_accounts : บัญชีผู้ใช้งานทุกประเภท (นักเรียน / บุคลากร / แอดมิน)
-- ---------------------------------------------------------------------
CREATE TABLE `user_accounts` (
    `user_id`       BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `username`      VARCHAR(200)    DEFAULT NULL,
    `password_hash` VARCHAR(200)    DEFAULT NULL,
    `role`          VARCHAR(20)     DEFAULT NULL,   -- 'student' | 'staff' | 'admin'
    `is_active`     TINYINT(1)      DEFAULT NULL,
    PRIMARY KEY (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;


-- ---------------------------------------------------------------------
-- user_students : ข้อมูลส่วนตัวของนักเรียน (1:1 กับ user_accounts)
-- ---------------------------------------------------------------------
CREATE TABLE `user_students` (
    `student_id`    BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `user_id`       INT             DEFAULT NULL,   -- FK -> user_accounts.user_id
    `citezen_id`    VARCHAR(50)     DEFAULT NULL,
    `student_code`  CHAR(50)        NOT NULL,
    `title_name`    VARCHAR(100)    DEFAULT NULL,
    `first_name_th` VARCHAR(200)    DEFAULT NULL,
    `first_name_en` VARCHAR(200)    DEFAULT NULL,
    `last_name_th`  VARCHAR(200)    DEFAULT NULL,
    `last_name_en`  VARCHAR(200)    DEFAULT NULL,
    `birthday`      DATE            DEFAULT NULL,
    `sex`           VARCHAR(20)     DEFAULT NULL,
    `email`         VARCHAR(100)    DEFAULT NULL,
    `phone`         VARCHAR(100)    DEFAULT NULL,
    `classroom_id`  INT             DEFAULT NULL,   -- FK -> classroom.classroom_id
    PRIMARY KEY (`student_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;


-- ---------------------------------------------------------------------
-- user_staffs : ข้อมูลส่วนตัวของบุคลากร/ครู (1:1 กับ user_accounts)
-- ---------------------------------------------------------------------
CREATE TABLE `user_staffs` (
    `staff_id`        BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `user_id`         INT             DEFAULT NULL,   -- FK -> user_accounts.user_id
    `staff_type_code` VARCHAR(20)     DEFAULT NULL,
    `citezen_id`      VARCHAR(50)     DEFAULT NULL,
    `title_name`      VARCHAR(20)     DEFAULT NULL,
    `first_name_th`   VARCHAR(200)    DEFAULT NULL,
    `first_name_en`   VARCHAR(200)    DEFAULT NULL,
    `last_name_th`    VARCHAR(200)    DEFAULT NULL,
    `last_name_en`    VARCHAR(200)    DEFAULT NULL,
    `birthday`        DATE            DEFAULT NULL,
    `sex`             VARCHAR(20)     DEFAULT NULL,
    `email`           VARCHAR(50)     DEFAULT NULL,
    `phone`           VARCHAR(20)     DEFAULT NULL,
    `department_code` VARCHAR(20)     DEFAULT NULL,
    PRIMARY KEY (`staff_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;


-- ---------------------------------------------------------------------
-- classroom : ห้องเรียน
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
-- ---------------------------------------------------------------------
CREATE TABLE `repair_requests` (
    `request_id`       BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `request_type`     VARCHAR(10)     DEFAULT NULL,
    `classroom_id`     INT             NOT NULL,               -- FK -> classroom.classroom_id
    `requester_id`     INT             NOT NULL,               -- FK -> user_accounts.user_id
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
-- ใช้งานโดย: repair_system/technician.php, repair_system/update_repair_status.php
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
    `staff_repair_id`           INT             DEFAULT NULL,  -- FK -> user_staffs.staff_id
    `staff_repair_detail`       TEXT            DEFAULT NULL,
    `staff_repair_image`        VARCHAR(255)    DEFAULT NULL,
    `disbursement_order_number` VARCHAR(100)    DEFAULT NULL,
    `status_disbursement`       VARCHAR(50)     DEFAULT NULL,
    `status_repair`             VARCHAR(50)     DEFAULT NULL,  -- 'repairing' | 'done'
    `status_datetime`           DATETIME        DEFAULT NULL,
    PRIMARY KEY (`repair_id`),
    UNIQUE KEY `uniq_request_id` (`request_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
