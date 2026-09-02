# project_learning — ระบบจัดการโรงเรียน

ระบบจัดการโรงเรียนแบบรวมศูนย์ (PHP + MySQL/MariaDB, XAMPP) ประกอบด้วยหลายโมดูลย่อยที่ใช้บัญชีผู้ใช้งานชุดเดียวกัน (นักเรียน / บุคลากร / แอดมิน) ล็อกอินครั้งเดียวแล้วเข้าถึงได้ทุกระบบผ่านเมนูหน้าแรก

## เทคโนโลยีที่ใช้

- PHP (mysqli, prepared statements)
- MySQL / MariaDB (ผ่าน XAMPP)
- HTML/CSS ธรรมดา ไม่มี framework/build step — เปิดผ่าน `http://localhost/.../project_learning/` ได้ทันที

## เริ่มต้นใช้งาน

1. สร้างฐานข้อมูลชื่อ `project_learning`
2. Import ไฟล์ในโฟลเดอร์ `database/` ทีละไฟล์ผ่าน phpMyAdmin (ดูรายละเอียดลำดับ/ความหมายแต่ละไฟล์ในหัวข้อ [database/](#database) ด้านล่าง)
3. ตรวจการตั้งค่าเชื่อมต่อ DB ที่ `config/db.php` (ค่าเริ่มต้น: host `localhost`, user `root`, ไม่มีรหัสผ่าน)
4. เข้าที่ `login/index.php` เพื่อเข้าสู่ระบบ

> ไม่มีสคริปต์ auto-migrate — ทุกครั้งที่ตาราง `database/*.sql` มีการแก้ไข ต้อง copy ไปรันเองใน phpMyAdmin

## โครงสร้างโฟลเดอร์

```
project_learning/
├── index.php              หน้าแรก/เมนูรวมหลัง login
├── .htaccess               rewrite /api/students -> api_demo/mock_students_api.php
├── config/                 การเชื่อมต่อฐานข้อมูล
├── includes/                ชิ้นส่วน HTML ที่ใช้ร่วมกัน (head.php)
├── css/                     สไตล์ชีตแยกตามระบบ
├── database/                ไฟล์ SQL สร้างตาราง (คัดลอกไปรันเองใน phpMyAdmin)
├── login/                   เข้าสู่ระบบ / ออกจากระบบ
├── user/                    จัดการบัญชีผู้ใช้งาน (staff/admin)
├── repair_system/           ระบบแจ้งซ่อมอุปกรณ์
├── borrow_system/           ระบบยืม-คืนอุปกรณ์
├── activity_system/         ระบบจัดการกิจกรรมนักเรียน
├── classroom_system/         ระบบจัดห้องสอบ/ที่นั่งสอบ
├── leave_system/              ระบบลา/ขออนุญาตนักเรียน
└── api_demo/                ตัวอย่าง mock API + โค้ดดึงข้อมูลเข้าฐานข้อมูล
```

## บัญชีผู้ใช้งาน (`user_accounts`)

ทุกโมดูลใช้ตารางผู้ใช้งานชุดเดียวกัน แบ่ง role เป็น 3 แบบ:

| role | คำอธิบาย | ตารางข้อมูลส่วนตัว |
|---|---|---|
| `student` | นักเรียน | `user_students` (มี `classroom_id`, `student_code`) |
| `staff` | บุคลากร/ครู | `user_staffs` (มี `staff_type_code` เช่น `teacher`, `technician`, `equipment_officer`) |
| `admin` | ผู้ดูแลระบบ | ไม่มีตารางแยก ใช้ username แสดงชื่อแทน |

**การแสดงชื่อห้องเรียน:** ทุกจุดที่แสดงห้องเรียน (homeroom) ใช้รูปแบบเดียวกันทั้งโปรเจกต์ผ่านฟังก์ชัน `getClassroomLabel($type, $code, $level)` (ก็อปไว้ในแต่ละไฟล์ที่ใช้ ตาม pattern เดิมของโปรเจกต์ที่ไม่มี include ฟังก์ชันร่วม) ผลลัพธ์คือ `{classroom_type} {classroom_number_code} / {classroom_level}` เช่น `มัธยม ม.1/1 / 1`

---

## login/

เข้าสู่ระบบด้วย username + password (`password_hash`/`password_verify`) แล้วเก็บ session (`user_id`, `role`, ชื่อ-สกุล) ทุกไฟล์ใน module อื่นเช็ค `$_SESSION['user_id']` ก่อนทุกครั้ง ถ้าไม่ล็อกอินจะ redirect กลับมาที่นี่

- `index.php` — ฟอร์ม login
- `login.php` — ตรวจสอบ + สร้าง session
- `logout.php` — ล้าง session

## user/

จัดการบัญชีผู้ใช้งาน (staff/admin เท่านั้น) — เพิ่ม/แก้ไขบัญชีนักเรียนและบุคลากร รหัสนักเรียน (`student_code`) สร้างอัตโนมัติจากปี พ.ศ. + คำนำหน้า + กลุ่มตัวอักษรชื่อ + เลขท้ายบัตรประชาชน 3 หลัก

- `main.php` — รายการผู้ใช้งานทั้งหมด
- `create.php` / `store.php` — ฟอร์มเพิ่มผู้ใช้งานใหม่ + บันทึก
- `edit.php` / `update.php` — แก้ไขข้อมูลผู้ใช้งาน

## repair_system/ — ระบบแจ้งซ่อมอุปกรณ์

**Flow:** นักเรียน/บุคลากรแจ้งซ่อม → ครูที่ปรึกษาของห้องนั้นอนุมัติ → เจ้าหน้าที่ซ่อมบำรุง (`staff_type_code = 'technician'`) รับงานและอัปเดตสถานะจนเสร็จ

| ไฟล์ | หน้าที่ |
|---|---|
| `main.php` | ฟอร์มแจ้งซ่อม + รายการล่าสุดของตัวเอง |
| `store.php` | บันทึกคำขอแจ้งซ่อม |
| `approve.php` | ครูที่ปรึกษาอนุมัติคำขอ (เช็คสิทธิ์จาก `classroom.advisor_staff_id`) |
| `technician.php` | คิวงานของช่างเทคนิค |
| `update_repair_status.php` | อัปเดตสถานะการซ่อม (`repairing` → `done`) |
| `detail.php` / `history.php` | ดูรายละเอียด/ประวัติ |

ตาราง: `repair_requests`, `repair_process`, `classroom` (ดู `database/repair_system.sql`)

## borrow_system/ — ระบบยืม-คืนอุปกรณ์

**Flow:** ยืมได้ทันทีไม่ต้องรออนุมัติ → ใช้เสร็จนักเรียน/บุคลากรแจ้งคืนพร้อมแนบรูป → เจ้าหน้าที่พัสดุ (`staff_type_code = 'equipment_officer'`) ตรวจสอบสภาพและยืนยันการคืนอีกครั้ง

| ไฟล์ | หน้าที่ |
|---|---|
| `main.php` | Dashboard คลังอุปกรณ์ + ฟอร์มยืม |
| `store.php` | บันทึกการยืม (ล็อกแถวอุปกรณ์กันยืมชิ้นเดียวกันซ้ำ) |
| `return_notify.php` / `request_return.php` | นักเรียน/บุคลากรแจ้งคืน + แนบรูป |
| `officer.php` / `confirm_return.php` / `update_borrow_status.php` | เจ้าหน้าที่พัสดุตรวจสอบและยืนยันการคืน |
| `detail.php` / `history.php` | ดูรายละเอียด/ประวัติ |

ตาราง: `equipment_item`, `borrow_requests` (ดู `database/borrow_system.sql`)

## activity_system/ — ระบบจัดการกิจกรรมนักเรียน

**Flow:** staff/admin สร้างกิจกรรม พร้อมกำหนด "ชั่วโมงรวมทั้งหมด" → นักเรียนสมัคร (ที่นั่งจำกัด เต็มแล้วเข้า waitlist อัตโนมัติ และเลื่อนคิวให้เองเมื่อมีคนยกเลิก) → ผู้จัดกิจกรรมเพิ่ม "รอบ" (session) ได้หลายรอบ แบ่งชั่วโมงจากยอดรวมให้แต่ละรอบ (รวมกันห้ามเกินที่ตั้งไว้) → เช็คชื่อแยกทีละรอบ → ชั่วโมงสะสมของนักเรียนคำนวณจากผลรวมชั่วโมงของทุกรอบที่เช็คชื่อว่า "มาร่วม"

| ไฟล์ | หน้าที่ |
|---|---|
| `main.php` | นักเรียน: สมัครกิจกรรมที่เปิดรับ / staff-admin: ภาพรวมกิจกรรมที่สร้าง |
| `create.php` / `store.php` | สร้างกิจกรรมใหม่ (กำหนดชั่วโมงรวม) |
| `sessions.php` / `store_session.php` | เพิ่ม/ดูรอบของกิจกรรม แบ่งชั่วโมงจากยอดรวม |
| `register.php` | นักเรียนสมัคร/ยกเลิก (มี logic เลื่อน waitlist) |
| `attendance.php` / `update_attendance.php` | เช็คชื่อรายรอบ |
| `update_activity_status.php` | ปิดรับสมัคร/ยกเลิก/ปิดกิจกรรม |
| `detail.php` / `history.php` | รายละเอียดกิจกรรม / ประวัติ + สรุปชั่วโมงสะสม |

ตาราง: `activities`, `activity_signups`, `activity_sessions`, `activity_attendance` (ดู `database/activity_system.sql`)

## classroom_system/ — ระบบจัดการห้องเรียน/ห้องสอบ

ครอบคลุม 2 ส่วน: **จัดการข้อมูลห้องเรียน** (CRUD ตาราง `classroom` ที่มีอยู่แล้ว) และ **จัดห้องสอบ/ที่นั่งสอบ**

**Flow การสอบ:** staff/admin (ต้องมีข้อมูลบุคลากรในตาราง `user_staffs` ก่อน) สร้าง "การสอบ" (ชื่อ+ประเภท+วิชา+วันเวลา) พร้อมเพิ่มห้องสอบได้หลายห้องในฟอร์มเดียว (room_code/ความจุ/กรรมการคุมสอบ — ไม่ผูกกับตาราง `classroom` เพราะห้องสอบอาจเป็นห้องพิเศษ) → ไปหน้ารายละเอียด เลือกห้องเรียน (homeroom) ที่ต้องเข้าสอบแล้วกด "จัดที่นั่งอัตโนมัติ" (กระจายแบบ round-robin กันคนห้องเดียวกันนั่งติดกัน, กดซ้ำ = จัดใหม่ทับของเดิม) → วันสอบจริงบันทึกสถานะเข้าสอบ/ขาดสอบ+หมายเหตุรายห้อง

**สิทธิ์:** ครูที่ปรึกษาดูได้อย่างเดียว (รายชื่อนักเรียนห้องตน + ผลสอบ) — ผู้จัดการทั้งหมด (สร้าง/จัดห้อง/จัดที่นั่ง/บันทึกผล) คือผู้สร้างการสอบเองหรือแอดมิน

| ไฟล์ | หน้าที่ |
|---|---|
| `classroom_main.php` | รายการห้องเรียนทั้งหมด + ครูที่ปรึกษา |
| `classroom_create.php` / `classroom_store.php` | เพิ่มห้องเรียนใหม่ |
| `classroom_edit.php` / `classroom_update.php` | แก้ไขห้องเรียน (รวมครูที่ปรึกษา) |
| `classroom_students.php` | รายชื่อนักเรียนในห้อง (read-only) |
| `main.php` | Dashboard แยกตาม role: แอดมิน (ภาพรวมทั้งระบบ), staff (การสอบที่สร้าง), ครูที่ปรึกษา (ห้องที่ดูแล), นักเรียน (ตารางสอบตัวเอง) |
| `create.php` / `store.php` | สร้างการสอบ + เพิ่มห้องสอบหลายห้องในฟอร์มเดียว (JS เพิ่ม/ลบแถว, เลือกห้องจาก dropdown ที่มีอยู่แล้วหรือพิมพ์เองก็ได้) |
| `detail.php` | รายละเอียดการสอบ — การ์ด "เช็คชื่อเข้าสอบ" อยู่บนสุดของหน้า (กดตรงเข้าห้องได้ทันที) + สรุปห้องสอบ + จัดนักเรียนเข้าห้องสอบ + แก้ไข/ยกเลิกการสอบ |
| `edit.php` / `update.php` | แก้ไขข้อมูลการสอบ (ชื่อ/ประเภท/วิชา/วันเวลา/รายละเอียด) |
| `edit_room.php` / `update_room.php` | แก้ไขห้องสอบทีละห้อง (ลดความจุต่ำกว่าจำนวนที่จัดที่นั่งไปแล้วไม่ได้) |
| `assign_students.php` | จัดที่นั่งอัตโนมัติ (round-robin, ตรวจความจุ, ล็อกด้วย transaction, กดซ้ำแทนที่ของเดิม) |
| `room_roster.php` / `update_attendance.php` | รายชื่อ+บันทึกสถานะเข้าสอบ/ขาดสอบรายห้อง |
| `update_exam_status.php` | ยกเลิกการสอบ |

ตาราง: `exam`, `exam_rooms`, `exam_students` (ดู `database/classroom_system.sql`) — `exam.created_by`/`exam_rooms.supervisor_staff_id` ผูกกับ `user_staffs.staff_id`, `exam_students.student_id` ผูกกับ `user_students.student_id` โดยตรง (ไม่ผ่าน `user_accounts`)

## leave_system/ — ระบบลา/ขออนุญาตนักเรียน

**Flow:** นักเรียนยื่นคำขอ (เลือกประเภทการลา + วันที่ + เหตุผล + เอกสารประกอบถ้ามี) → ครูที่ปรึกษาของห้องนักเรียนคนนั้นอนุมัติ/ไม่อนุมัติก่อนเสมอ → ถ้าประเภทการลานั้นตั้งค่า `requires_discipline_approval = 1` (เช่น "ขออนุญาตออกนอกโรงเรียน") จะต้องผ่าน**ครูฝ่ายปกครอง** (`staff_type_code = 'discipline'`) อนุมัติซ้อนอีกชั้นก่อนถึงจะอนุมัติสมบูรณ์ — ประเภทอื่น (ลาป่วย/ลากิจ) อนุมัติสมบูรณ์ทันทีที่ครูที่ปรึกษาอนุมัติ

**สถานะ:** `PENDING_ADVISOR` → (`PENDING_DISCIPLINE` เฉพาะประเภทที่ต้องผ่าน 2 ชั้น) → `APPROVED` | `REJECTED` | `CANCELLED` (นักเรียนยกเลิกเองได้ก่อนอนุมัติ)

| ไฟล์ | หน้าที่ |
|---|---|
| `main.php` | Dashboard แยกตาม role: นักเรียน (คำขอตัวเอง), ครูที่ปรึกษา (คิวรออนุมัติห้องตน), ครูฝ่ายปกครอง (คิวรออนุมัติขั้น 2), staff/admin (สถิติรวม + กราฟตามประเภท) |
| `create.php` / `store.php` | นักเรียนสร้างคำขอ (แนบเอกสารได้) |
| `detail.php` | รายละเอียดคำขอ + timeline ขั้นตอนอนุมัติ + ปุ่มอนุมัติ/ไม่อนุมัติ/ยกเลิกตามสิทธิ์ |
| `approve_advisor.php` | ครูที่ปรึกษาอนุมัติ/ไม่อนุมัติ (เช็คสิทธิ์จาก `classroom.advisor_staff_id`) |
| `approve_discipline.php` | ครูฝ่ายปกครองอนุมัติ/ไม่อนุมัติ (เฉพาะคำขอที่ผ่านครูที่ปรึกษาแล้ว) |
| `cancel.php` | นักเรียนยกเลิกคำขอของตัวเอง (เฉพาะที่ยังไม่อนุมัติ) |
| `history.php` | ประวัติคำขอทั้งหมด (นักเรียนเห็นเฉพาะของตัวเอง, staff/admin เห็นทั้งหมด) |
| `types_main.php` ฯลฯ | CRUD ประเภทการลา (staff/admin) — ตั้งค่าว่าประเภทไหนต้องผ่านฝ่ายปกครองด้วย |

ตาราง: `leave_types`, `leave_requests` (ดู `database/leave_system.sql`) — `leave_requests.student_id` ผูกกับ `user_students.student_id`, `advisor_approved_by`/`discipline_approved_by` ผูกกับ `user_accounts.user_id`

## api_demo/ — ตัวอย่างดึงข้อมูลจาก API ภายนอก

ตัวอย่างสำหรับฝึก/ใช้อ้างอิงเวลาข้อสอบมี API ข้อมูลนักเรียนมาให้จริง — จำลอง API + เขียนโค้ดดึงข้อมูลเข้าฐานข้อมูล แยกกันคนละไฟล์ตามหน้าที่

| ไฟล์ | หน้าที่ |
|---|---|
| `mock_students_api.php` | API จำลอง คืนค่าเป็น JSON รายชื่อนักเรียนตัวอย่าง (เข้าถึงผ่าน path `/api/students` ตาม `.htaccess`) |
| `fetch_students.php` | เรียก API (cURL) → decode JSON → insert/update ลง `user_accounts` + `user_students` — มีคอมเมนต์บอกจุดที่ต้องแก้เมื่อเปลี่ยนไปใช้ API จริง |
| `import_students.php` | หน้าเว็บสำหรับกดปุ่มสั่งดึงข้อมูล + แสดงสรุปผล (staff/admin เท่านั้น) |

บัญชีนักเรียนที่สร้างผ่านการ import: username = เลขบัตรประชาชน, password เริ่มต้น = `123456`

## config/, includes/, css/

- `config/db.php` — จุดเชื่อมต่อฐานข้อมูลกลาง (`$conn` แบบ mysqli) ทุกไฟล์ `require_once` จากตรงนี้
- `includes/head.php` — `<head>` ที่ใช้ร่วมกันทุกหน้า (รับ `$page_title`, `$css_path` จากไฟล์ที่ include)
- `css/` — 1 ไฟล์ต่อ 1 ระบบ (`style.css` ใช้กับหน้าแรก, `login.css`, `user.css`, `repair.css`, `borrow.css`, `activity.css`, `classroom.css`, `leave.css`)

## database/

| ไฟล์ | เนื้อหา |
|---|---|
| `schema.sql` | **สารบัญเท่านั้น** ไม่มี `CREATE TABLE` — บอกว่าตารางจริงอยู่ไฟล์ไหน |
| `users.sql` | `user_accounts`, `user_students`, `user_staffs` — ใช้ร่วมทุกระบบ |
| `repair_system.sql` | `classroom`, `repair_requests`, `repair_process` |
| `borrow_system.sql` | `equipment_item`, `borrow_requests` |
| `activity_system.sql` | `activities`, `activity_signups`, `activity_sessions`, `activity_attendance` |
| `classroom_system.sql` | `exam`, `exam_rooms`, `exam_students` |
| `leave_system.sql` | `leave_types`, `leave_requests` (มีข้อมูลตัวอย่าง 4 ประเภทการลาติดมาด้วย) |

ลำดับการ import: `users.sql` → `repair_system.sql` (มี `classroom` ที่ระบบอื่นอ้างอิงถึง) → `borrow_system.sql` → `activity_system.sql` → `classroom_system.sql` → `leave_system.sql`
