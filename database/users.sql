-- =====================================================================
-- ข้อมูลผู้ใช้งาน (User Management)
-- ใช้ร่วมกันทุกระบบ: login/, user/, repair_system/
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
    `classroom_id`  INT             DEFAULT NULL,   -- FK -> classroom.classroom_id (ดู database/repair_system.sql)
    PRIMARY KEY (`student_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;


-- ---------------------------------------------------------------------
-- user_staffs : ข้อมูลส่วนตัวของบุคลากร/ครู (1:1 กับ user_accounts)
--
-- staff_type_code ที่ใช้จริงในระบบตอนนี้:
--   'teacher'    = ครู (สามารถถูกตั้งเป็นครูที่ปรึกษาห้องผ่าน classroom.advisor_staff_id)
--   'technician' = เจ้าหน้าที่ซ่อมบำรุง (เห็นคิวงานที่ repair_system/technician.php)
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
