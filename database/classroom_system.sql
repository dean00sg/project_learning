-- =====================================================================
-- ระบบจัดการห้องเรียน / ห้องสอบ (Classroom + Exam Room System)
-- ตารางเฉพาะของระบบนี้ที่ใช้โดยไฟล์ในโฟลเดอร์ classroom_system/
-- (ตารางผู้ใช้งาน user_accounts / user_students / user_staffs อยู่ที่
--  database/users.sql, ตาราง classroom อยู่ที่ database/repair_system.sql
--  — ทั้งสองใช้ร่วมกับระบบอื่นด้วย ไม่ต้องสร้างใหม่)
--
-- Flow: staff/admin (ฝ่ายวิชาการ) สร้าง "การสอบ" (วิชา + วันเวลา) -> เพิ่ม
-- ห้องสอบ (ห้องจริง+ความจุ+กรรมการคุมสอบ) หลายห้องได้ในขั้นตอนเดียว ->
-- เลือกห้องเรียน (homeroom) ที่ต้องเข้าสอบ แล้วกด "จัดนักเรียนเข้าห้องสอบ"
-- ระบบกระจายนักเรียนเข้าห้องสอบ+เลขที่นั่งอัตโนมัติ -> วันสอบจริงบันทึก
-- สถานะการเข้าสอบ (เข้าสอบ/ขาดสอบ) รายคน
--
-- ครูที่ปรึกษา: ดูได้อย่างเดียว (รายชื่อนักเรียนห้องตน + ตารางสอบ + ผลเข้าสอบ)
-- ไม่ใช่คนจัดห้องสอบ — ฝ่ายวิชาการ/แอดมิน (staff/admin) เป็นผู้จัดการทั้งหมด
-- =====================================================================


-- ---------------------------------------------------------------------
-- exam : การสอบแต่ละครั้ง (1 วิชา 1 วันเวลา)
-- ---------------------------------------------------------------------
CREATE TABLE `exam` (
    `exam_id`        BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `exam_name`       VARCHAR(200)    NOT NULL,       -- เช่น "สอบกลางภาค 1/2569"
    `exam_type`       VARCHAR(20)     DEFAULT NULL,   -- 'MIDTERM' | 'FINAL' | 'QUIZ' | 'OTHER'
    `subject_name`    VARCHAR(200)    NOT NULL,
    `exam_date`       DATE            NOT NULL,
    `start_time`      TIME            NOT NULL,
    `end_time`        TIME            NOT NULL,
    `detail`          TEXT            DEFAULT NULL,
    `status`          VARCHAR(20)     NOT NULL DEFAULT 'OPEN',  -- 'OPEN' | 'CANCELLED'
    `created_by`      INT             NOT NULL,       -- FK -> user_staffs.staff_id (ฝ่ายวิชาการ/แอดมินผู้สร้าง, ดู database/users.sql)
    `created_at`      DATETIME        NOT NULL DEFAULT NOW(),
    PRIMARY KEY (`exam_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;


-- ---------------------------------------------------------------------
-- exam_rooms : ห้องที่ใช้เป็นห้องสอบสำหรับการสอบครั้งนี้
--
-- ไม่ผูกกับตาราง classroom (ห้องโฮมรูม) เพราะห้องสอบอาจเป็นห้องพิเศษ
-- (เช่น หอประชุม, ห้องประชุม) ที่ไม่ใช่ห้องเรียนปกติ — เก็บชื่อ/ที่ตั้งเอง
-- ---------------------------------------------------------------------
CREATE TABLE `exam_rooms` (
    `exam_room_id`          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `exam_id`                INT             NOT NULL,   -- FK -> exam.exam_id
    `room_code`              VARCHAR(50)     NOT NULL,   -- เช่น "49301"
    `room_name`              VARCHAR(200)    DEFAULT NULL,
    `building`               VARCHAR(100)    DEFAULT NULL,
    `floor`                  INT             DEFAULT NULL,
    `capacity`               INT             NOT NULL,
    `supervisor_staff_id`    INT             DEFAULT NULL,  -- FK -> user_staffs.staff_id (กรรมการคุมสอบ, ไม่บังคับ)
    PRIMARY KEY (`exam_room_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;


-- ---------------------------------------------------------------------
-- exam_students : นักเรียนที่ถูกจัดเข้าห้องสอบ + ผลการเข้าสอบ
--
-- ไม่มี exam_id ตรง ๆ (อ้างอิงผ่าน exam_room_id -> exam_rooms.exam_id) —
-- ห้องเรียน (homeroom) ที่เข้าสอบครั้งนี้ก็ไม่ต้องมีตารางแยกเก็บไว้ ดูย้อนหลัง
-- ได้จาก exam_students -> user_students.classroom_id อยู่แล้ว
-- ---------------------------------------------------------------------
CREATE TABLE `exam_students` (
    `exam_student_id`   BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `exam_room_id`       INT             NOT NULL,       -- FK -> exam_rooms.exam_room_id
    `student_id`         INT             NOT NULL,       -- FK -> user_students.student_id (ดู database/users.sql)
    `seat_number`        VARCHAR(20)     NOT NULL,
    `attendance_status`  VARCHAR(20)     DEFAULT NULL,   -- NULL = ยังไม่เช็ค | 'present' | 'absent'
    `checkin_at`         DATETIME        DEFAULT NULL,
    `remark`             VARCHAR(255)    DEFAULT NULL,
    PRIMARY KEY (`exam_student_id`),
    UNIQUE KEY `uniq_room_seat` (`exam_room_id`, `seat_number`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
