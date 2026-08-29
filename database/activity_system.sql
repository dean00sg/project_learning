-- =====================================================================
-- ระบบจัดการกิจกรรมนักเรียน (Activity System)
-- ตารางเฉพาะของระบบนี้ที่ใช้โดยไฟล์ในโฟลเดอร์ activity_system/
-- (ตารางผู้ใช้งาน user_accounts / user_students / user_staffs
--  แยกไว้ที่ database/users.sql เพราะใช้ร่วมกับระบบอื่นด้วย)
--
-- Flow: staff/admin สร้างกิจกรรม -> นักเรียนสมัคร (เต็มแล้วเข้า waitlist)
-- -> staff เปิด "รอบ" (session) ของกิจกรรมนั้นได้หลายรอบ (เช่น ชมรมที่นัดเจอ
-- ทุกสัปดาห์) -> เช็คชื่อแยกทีละรอบ -> ชั่วโมงสะสมของนักเรียนคำนวณจาก
-- SUM(activity_sessions.hours_awarded) เฉพาะรอบที่เช็คชื่อว่า 'present'
-- (ไม่มีคอลัมน์ชั่วโมงสะสมแยกเก็บไว้ คำนวณจาก SUM ตอน query เพื่อไม่ให้
-- ข้อมูลเพี้ยนถ้าย้อนกลับไปแก้การเช็คชื่อทีหลัง)
-- =====================================================================


-- ---------------------------------------------------------------------
-- activities : กิจกรรมที่เปิดให้นักเรียนสมัคร (ยังไม่มีวันที่จัดผูกอยู่
-- ตรงนี้ — ของแบบนั้นอยู่ที่ activity_sessions เพราะ 1 กิจกรรมจัดได้หลายรอบ)
--
-- total_hours = ชั่วโมง/หน่วยกิตรวมทั้งหมดของกิจกรรม กำหนดตอนสร้าง แล้วค่อย
-- แบ่งจัดสรรให้แต่ละรอบใน activity_sessions ทีหลัง (ดู activity_system/store_session.php
-- ที่ตรวจไม่ให้ผลรวม hours_awarded ของทุกรอบเกิน total_hours)
-- ---------------------------------------------------------------------
CREATE TABLE `activities` (
    `activity_id`        BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `title`               VARCHAR(200)    NOT NULL,
    `category`            VARCHAR(50)     DEFAULT NULL,   -- 'club' | 'volunteer' | 'trip' | 'competition' | 'other'
    `detail`              TEXT            DEFAULT NULL,
    `organizer_id`        INT             NOT NULL,       -- FK -> user_accounts.user_id (staff หรือ admin ผู้สร้างกิจกรรม, ดู database/users.sql)
    `start_datetime`      DATETIME        NOT NULL,       -- วันที่เริ่ม/อ้างอิง ใช้แสดงในรายการ+เรียงลำดับเท่านั้น (ไม่ใช่วันเช็คชื่อ)
    `location`            VARCHAR(200)    DEFAULT NULL,
    `max_participants`    INT             NOT NULL,
    `total_hours`         DECIMAL(4,1)    NOT NULL DEFAULT 0,  -- ชั่วโมงรวมทั้งหมด แบ่งจัดสรรให้แต่ละรอบใน activity_sessions
    `status`              VARCHAR(20)     NOT NULL DEFAULT 'open',  -- 'open' | 'closed' | 'cancelled' | 'finished'
    `created_at`          DATETIME        NOT NULL DEFAULT NOW(),
    PRIMARY KEY (`activity_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;


-- ---------------------------------------------------------------------
-- activity_signups : การสมัครเข้าร่วมกิจกรรมของนักเรียน
--
-- สมัครระดับ "กิจกรรม" ครั้งเดียว ไม่ต้องสมัครแยกทีละรอบ — สมัครผ่านแล้ว
-- เข้าเช็คชื่อได้ทุกรอบของกิจกรรมนั้น
--
-- ที่นั่งจำกัดตาม activities.max_participants (นับเฉพาะแถวที่ status = 'registered')
-- เต็มแล้วสมัครใหม่จะได้ status = 'waitlisted' ต่อคิวไว้
-- ถ้ามีคน 'registered' ยกเลิก ระบบเลื่อนคนที่ waitlist ลำดับแรกขึ้นมาแทนที่อัตโนมัติ
-- (ดู logic ที่ activity_system/register.php)
--
-- 1 คนสมัครได้ 1 แถวต่อ 1 กิจกรรม (UNIQUE) — ยกเลิกแล้วสมัครใหม่คือ UPDATE
-- แถวเดิมกลับมา ไม่สร้างแถวใหม่ซ้ำ
-- ---------------------------------------------------------------------
CREATE TABLE `activity_signups` (
    `registration_id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `activity_id`      INT             NOT NULL,          -- FK -> activities.activity_id
    `requester_id`     INT             NOT NULL,          -- FK -> user_accounts.user_id (นักเรียนผู้สมัคร)
    `registered_at`    DATETIME        NOT NULL DEFAULT NOW(),
    `status`           VARCHAR(20)     NOT NULL DEFAULT 'registered',  -- 'registered' | 'waitlisted' | 'cancelled'
    `cancelled_at`     DATETIME        DEFAULT NULL,
    PRIMARY KEY (`registration_id`),
    UNIQUE KEY `uniq_activity_requester` (`activity_id`, `requester_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;


-- ---------------------------------------------------------------------
-- activity_sessions : "รอบ" ของกิจกรรม (1 กิจกรรมมีได้หลายรอบ)
--
-- ตัวอย่าง: ชมรมดนตรีนัดซ้อมทุกวันศุกร์ -> สร้าง session ใหม่ทุกสัปดาห์
-- กิจกรรมทัศนศึกษาวันเดียวจบ -> สร้าง session เดียวพอ
--
-- hours_awarded เป็นของแต่ละรอบ (ไม่ใช่ของทั้งกิจกรรม) เพราะแต่ละรอบอาจให้
-- ชั่วโมงไม่เท่ากันได้ — ผลรวม hours_awarded ของทุกรอบในกิจกรรมเดียวกัน
-- ต้องไม่เกิน activities.total_hours (ตรวจตอนเพิ่มรอบใหม่)
-- ---------------------------------------------------------------------
CREATE TABLE `activity_sessions` (
    `session_id`        BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `activity_id`        INT             NOT NULL,        -- FK -> activities.activity_id
    `session_datetime`   DATETIME        NOT NULL,
    `hours_awarded`      DECIMAL(4,1)    NOT NULL DEFAULT 0,
    `note`               VARCHAR(200)    DEFAULT NULL,     -- เช่น "ครั้งที่ 1: ปฐมนิเทศ"
    `created_at`         DATETIME        NOT NULL DEFAULT NOW(),
    PRIMARY KEY (`session_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;


-- ---------------------------------------------------------------------
-- activity_attendance : เช็คชื่อแยกทีละรอบ (เฉพาะคนที่ signup.status = 'registered')
--
-- 1 คน เช็คชื่อได้ 1 แถวต่อ 1 รอบ (UNIQUE session_id+registration_id)
-- staff เช็คชื่อซ้ำในรอบเดิมคือ UPDATE แถวเดิม
--
-- ชั่วโมงสะสมของนักเรียนคำนวณจาก:
-- SUM(activity_sessions.hours_awarded)
-- JOIN ผ่าน activity_attendance WHERE attend_status = 'present'
-- ---------------------------------------------------------------------
CREATE TABLE `activity_attendance` (
    `attendance_id`   BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `session_id`      INT             NOT NULL,           -- FK -> activity_sessions.session_id
    `registration_id` INT             NOT NULL,           -- FK -> activity_signups.registration_id
    `attend_status`   VARCHAR(20)     NOT NULL,            -- 'present' | 'absent'
    `checked_by`      INT             DEFAULT NULL,       -- FK -> user_accounts.user_id (staff ผู้เช็คชื่อ)
    `checked_at`      DATETIME        DEFAULT NULL,
    PRIMARY KEY (`attendance_id`),
    UNIQUE KEY `uniq_session_registration` (`session_id`, `registration_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
