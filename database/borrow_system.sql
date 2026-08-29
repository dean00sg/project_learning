-- =====================================================================
-- ระบบยืมคืนอุปกรณ์ (Borrow System)
-- ตารางเฉพาะของระบบนี้ที่ใช้โดยไฟล์ในโฟลเดอร์ borrow_system/
-- (ตารางผู้ใช้งาน/ห้องเรียน อยู่ที่ database/users.sql, database/repair_system.sql)
--
-- หมายเหตุ: เวอร์ชันนี้ตัดขั้นตอนอนุมัติออกทั้งหมดตามที่ผู้ใช้ระบุ
-- (ยืม = ยืมได้ทันที ไม่ต้องรอครูที่ปรึกษายืนยัน ไม่ต้องรอเจ้าหน้าที่อนุมัติ)
-- ขั้นตอนที่ยังต้องมีคนตรวจสอบคือ "คืน" เท่านั้น: ผู้ยืมถ่ายรูปแนบตอนแจ้งคืน
-- แล้วเจ้าหน้าที่พัสดุ (staff_type_code = 'equipment_officer') ตรวจสอบและ
-- ยืนยันสภาพอุปกรณ์อีกครั้งจึงจะถือว่าคืนสำเร็จ
-- =====================================================================


-- ---------------------------------------------------------------------
-- equipment_item : รายการครุภัณฑ์ที่ให้ยืม (1 แถว = อุปกรณ์ 1 ชิ้นจริง
-- ไม่ใช่สต็อกตามจำนวน เพราะ item_code คือรหัสครุภัณฑ์เฉพาะชิ้น)
-- ---------------------------------------------------------------------
CREATE TABLE `equipment_item` (
    `item_id`     BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `item_code`   VARCHAR(50)     NOT NULL,               -- รหัสครุภัณฑ์
    `item_name`   VARCHAR(200)    NOT NULL,
    `item_detail` TEXT            DEFAULT NULL,
    `item_type`   VARCHAR(50)     DEFAULT NULL,           -- หมวดอุปกรณ์ เช่น computer, projector
    `staff_id`    INT             DEFAULT NULL,           -- FK -> user_staffs.staff_id (ผู้ดูแลอุปกรณ์ชิ้นนี้)
    `status`      VARCHAR(20)     NOT NULL DEFAULT 'available',  -- 'available' | 'borrowed' | 'maintenance' | 'damaged'
    PRIMARY KEY (`item_id`),
    UNIQUE KEY `uniq_item_code` (`item_code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;


-- ---------------------------------------------------------------------
-- borrow_requests : รายการยืมอุปกรณ์
--
-- ยืมได้ทันทีเมื่อส่งคำขอ (ไม่มีขั้นอนุมัติ) สถานะคำนวณจากคอลัมน์:
--   1. กำลังยืม              : return_requested_at IS NULL
--   2. รอเจ้าหน้าที่ตรวจสอบคืน : return_requested_at ไม่เป็น NULL แต่ return_item_at ยังเป็น NULL
--   3. คืนสำเร็จ / คืนแล้ว(ชำรุด) : return_item_at ไม่เป็น NULL (ดู return_condition)
-- ---------------------------------------------------------------------
CREATE TABLE `borrow_requests` (
    `borrow_id`      BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,

    `item_id`        INT             NOT NULL,             -- FK -> equipment_item.item_id
    `requester_id`   INT             NOT NULL,             -- FK -> user_accounts.user_id
    `borrow_type`    VARCHAR(20)     DEFAULT NULL,          -- 'classroom' | 'outside' (ชั้นเรียน หรือนอกชั้นเรียน)
    `classroom_id`   INT             DEFAULT NULL,          -- FK -> classroom.classroom_id (บังคับกรอกเมื่อ borrow_type = 'classroom')
    `request_detail` TEXT            DEFAULT NULL,
    `requester_at`   DATETIME        NOT NULL DEFAULT NOW(),

    -- ผู้ยืมแจ้งคืน (ต้องแนบรูปเสมอ)
    `return_requested_at` DATETIME   DEFAULT NULL,
    `return_image`   VARCHAR(255)    DEFAULT NULL,          -- รูปที่ผู้ยืมถ่ายส่งตอนแจ้งคืน
    `return_note`    TEXT            DEFAULT NULL,          -- หมายเหตุจากผู้ยืม (ถ้ามี)

    -- เจ้าหน้าที่พัสดุตรวจสอบแล้วยืนยันการคืน
    `return_item_by` INT             DEFAULT NULL,          -- FK -> user_accounts.user_id (เจ้าหน้าที่ผู้ยืนยัน)
    `return_item_at` DATETIME        DEFAULT NULL,
    `return_condition` VARCHAR(20)   DEFAULT NULL,          -- 'normal' | 'damaged' (เจ้าหน้าที่ระบุตอนตรวจ)
    `return_detail`  TEXT            DEFAULT NULL,          -- บันทึกของเจ้าหน้าที่ เช่น รายละเอียดความเสียหาย

    PRIMARY KEY (`borrow_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
