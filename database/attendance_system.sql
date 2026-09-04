-- =====================================================================
-- ระบบตารางสอน + เช็คชื่อเข้าเรียนรายคาบ (Attendance System)
-- ตารางเฉพาะของระบบนี้ที่ใช้โดยไฟล์ในโฟลเดอร์ attendance_system/
-- (ตารางผู้ใช้งาน/ห้องเรียน อยู่ที่ database/users.sql, database/repair_system.sql
--  ตารางใบลา อยู่ที่ database/leave_system.sql — ใช้ร่วมกับระบบอื่นด้วย)
--
-- Flow: ฝ่ายวิชาการ/แอดมินสร้าง "ตารางสอน" ผูกครูผู้สอน+ห้องเรียน+วัน/เวลา
-- ไว้ล่วงหน้า -> ครูผู้สอนเช็คชื่อนักเรียนตามคาบของตัวเองได้ทุกวัน
-- (เลือกวันที่ย้อนหลัง/ล่วงหน้าได้) -> ถ้านักเรียนมีใบลาที่ "APPROVED" ครอบคลุม
-- วันนั้น ระบบจะบังคับสถานะเป็น "ลา" ให้อัตโนมัติ ครูแก้ไม่ได้ (ป้องกันขัดแย้ง
-- กับผลอนุมัติใบลา)
--
-- status: PRESENT (มา) | LATE (สาย) | ABSENT (ขาด) | LEAVE (ลา — มาจากใบลาอนุมัติเท่านั้น)
-- =====================================================================


-- ---------------------------------------------------------------------
-- class_schedule : ตารางสอน (1 แถว = 1 คาบเรียนที่นัดหมายไว้ล่วงหน้า)
-- ---------------------------------------------------------------------
CREATE TABLE `class_schedule` (
    `schedule_id`   BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `classroom_id`  BIGINT UNSIGNED NOT NULL,           -- FK -> classroom.classroom_id (ดู database/repair_system.sql)
    `subject_code`  VARCHAR(50)     DEFAULT NULL,
    `subject_name`  VARCHAR(200)    NOT NULL,
    `staff_id`      BIGINT UNSIGNED NOT NULL,           -- FK -> user_accounts.user_id (ครูผู้สอน, ดู database/users.sql)
    `day_of_week`   TINYINT         NOT NULL,           -- 1=จันทร์ ... 7=อาทิตย์
    `start_time`    TIME            NOT NULL,
    `end_time`      TIME            NOT NULL,
    `room`          VARCHAR(100)    DEFAULT NULL,
    `is_active`     TINYINT(1)      NOT NULL DEFAULT 1,
    PRIMARY KEY (`schedule_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;


-- ---------------------------------------------------------------------
-- attendance : ผลเช็คชื่อรายคน ต่อคาบ ต่อวัน
--
-- 1 นักเรียน เช็คได้ 1 แถวต่อ 1 คาบต่อ 1 วัน (UNIQUE) — เช็คซ้ำวันเดิมคือ
-- UPDATE แถวเดิม (ON DUPLICATE KEY UPDATE)
-- ---------------------------------------------------------------------
CREATE TABLE `attendance` (
    `attendance_id`     BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `schedule_id`       BIGINT UNSIGNED NOT NULL,       -- FK -> class_schedule.schedule_id
    `student_id`        BIGINT UNSIGNED NOT NULL,       -- FK -> user_students.student_id (ดู database/users.sql)
    `attendance_date`   DATE            NOT NULL,
    `checkin_at`        DATETIME        DEFAULT NULL,
    `status`            VARCHAR(20)     NOT NULL,       -- 'PRESENT' | 'LATE' | 'ABSENT' | 'LEAVE'
    `leave_request_id`  BIGINT UNSIGNED DEFAULT NULL,   -- FK -> leave_requests.request_id (ดู database/leave_system.sql, เฉพาะตอน status = 'LEAVE')
    `remark`            TEXT            DEFAULT NULL,
    `checked_by`        BIGINT UNSIGNED DEFAULT NULL,   -- FK -> user_accounts.user_id (ครูผู้เช็คชื่อ)
    PRIMARY KEY (`attendance_id`),
    UNIQUE KEY `uniq_schedule_student_date` (`schedule_id`, `student_id`, `attendance_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
