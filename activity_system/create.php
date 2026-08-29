<?php

session_start();

$page_title = "สร้างกิจกรรมใหม่";
$css_path   = "../css/activity.css";

require_once "../config/db.php";

// ตารางที่ใช้ในไฟล์นี้: user_accounts, user_staffs
// โครงสร้างตารางแบบเต็มดูได้ที่ database/activity_system.sql

// =====================================================
// ตรวจสอบสิทธิ์: เฉพาะบุคลากร (staff) และผู้ดูแลระบบ (admin)
// =====================================================

if (
    !isset($_SESSION['user_id']) ||
    !in_array($_SESSION['role'] ?? '', ['staff', 'admin'], true)
) {
    header("Location: ../login/index.php");
    exit;
}

function e($value)
{
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}
?>
<!DOCTYPE html>
<html lang="th">
<?php include __DIR__ . "/../includes/head.php"; ?>
<body>
<div class="activity-page">

    <header class="activity-header">
        <div class="header-inner">
            <div class="header-brand">
                <div class="brand-icon">🎉</div>
                <div>
                    <h1>สร้างกิจกรรมใหม่</h1>
                    <span>Student Activity System</span>
                </div>
            </div>

            <a href="main.php" class="back-home">← กลับ</a>
        </div>
    </header>

    <main class="activity-container">
        <div class="activity-card">

            <div class="card-title">
                <div class="title-icon">📝</div>
                <div>
                    <h3>รายละเอียดกิจกรรม</h3>
                    <p>กรอกข้อมูลให้ครบถ้วน นักเรียนจะเห็นกิจกรรมนี้ทันทีเมื่อบันทึก</p>
                </div>
            </div>

            <form action="store.php" method="POST">

                <div class="form-group">
                    <label>ชื่อกิจกรรม <span class="required">*</span></label>
                    <input type="text" name="title" required>
                </div>

                <div class="form-grid">
                    <div class="form-group">
                        <label>ประเภทกิจกรรม <span class="required">*</span></label>
                        <select name="category" required>
                            <option value="">-- เลือกประเภท --</option>
                            <option value="club">ชมรม/ชุมนุม</option>
                            <option value="volunteer">จิตอาสา</option>
                            <option value="trip">ทัศนศึกษา</option>
                            <option value="competition">การแข่งขัน</option>
                            <option value="other">อื่น ๆ</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label>จำนวนที่รับ (ที่นั่ง) <span class="required">*</span></label>
                        <input type="number" name="max_participants" min="1" required>
                    </div>
                </div>

                <div class="form-grid">
                    <div class="form-group">
                        <label>วันที่เริ่มกิจกรรม (ใช้อ้างอิง/เรียงลำดับเท่านั้น) <span class="required">*</span></label>
                        <input type="datetime-local" name="start_datetime" required>
                    </div>

                    <div class="form-group">
                        <label>ชั่วโมงรวมทั้งหมด (หน่วยกิต) <span class="required">*</span></label>
                        <input type="number" name="total_hours" step="0.5" min="0" required>
                    </div>
                </div>

                <div class="form-group">
                    <label>สถานที่</label>
                    <input type="text" name="location" placeholder="เช่น หอประชุมโรงเรียน">
                </div>

                <div class="form-group">
                    <label>รายละเอียดกิจกรรม</label>
                    <textarea name="detail" rows="4" placeholder="รายละเอียด/เงื่อนไขการเข้าร่วม"></textarea>
                </div>

                <div class="notice-box">
                    <div class="notice-icon">ℹ️</div>
                    <div>
                        <strong>เมื่อที่นั่งเต็ม</strong>
                        <p>นักเรียนที่สมัครหลังที่นั่งเต็มจะเข้าคิว (waitlist) อัตโนมัติ และถูกเลื่อนขึ้นมาแทนที่ทันทีถ้ามีคนยกเลิก</p>
                    </div>
                </div>

                <div class="notice-box">
                    <div class="notice-icon">🗓️</div>
                    <div>
                        <strong>ขั้นตอนถัดไป: แบ่งชั่วโมงให้แต่ละ "รอบ" ของกิจกรรม</strong>
                        <p>บันทึกแล้วจะพาไปหน้าเพิ่มรอบ (session) — กิจกรรมที่จัดหลายครั้ง เช่น ชมรมที่นัดเจอทุกสัปดาห์ เพิ่มได้หลายรอบ แต่ละรอบกำหนดวันที่และแบ่งชั่วโมงจากยอดรวมด้านบน โดยรวมกันแล้วต้องไม่เกินชั่วโมงรวมทั้งหมดที่ตั้งไว้</p>
                    </div>
                </div>

                <div class="form-actions">
                    <a href="main.php" class="cancel-btn">ยกเลิก</a>
                    <button type="submit" class="submit-btn">✅ สร้างกิจกรรม</button>
                </div>

            </form>

        </div>
    </main>
</div>
</body>
</html>
