<?php

session_start();

$page_title = "เพิ่มประเภทการลา";
$css_path   = "../css/leave.css";

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
?>
<!DOCTYPE html>
<html lang="th">
<?php include __DIR__ . "/../includes/head.php"; ?>
<body>
<div class="leave-page">

    <header class="leave-header">
        <div class="header-inner">
            <div class="header-brand">
                <div class="brand-icon">⚙️</div>
                <div>
                    <h1>เพิ่มประเภทการลา</h1>
                    <span>Leave &amp; Permission Request System</span>
                </div>
            </div>

            <a href="types_main.php" class="back-home">← กลับ</a>
        </div>
    </header>

    <main class="leave-container">
        <div class="leave-card">

            <div class="card-title">
                <div class="title-icon">📝</div>
                <div>
                    <h3>ข้อมูลประเภทการลา</h3>
                </div>
            </div>

            <form action="types_store.php" method="POST">

                <div class="form-group">
                    <label>ชื่อประเภท <span class="required">*</span></label>
                    <input type="text" name="leave_type_name" placeholder="เช่น ลาป่วย" required>
                </div>

                <div class="form-group">
                    <label>รายละเอียด</label>
                    <textarea name="detail" rows="3" placeholder="คำอธิบายเพิ่มเติม (ถ้ามี)"></textarea>
                </div>

                <div class="form-group">
                    <label class="checkbox-item">
                        <input type="checkbox" name="requires_discipline_approval" value="1">
                        ต้องผ่านครูฝ่ายปกครองอนุมัติต่อจากครูที่ปรึกษา
                    </label>
                </div>

                <div class="notice-box">
                    <div class="notice-icon">ℹ️</div>
                    <div>
                        <strong>เมื่อไหร่ควรติ๊กตัวเลือกนี้</strong>
                        <p>ใช้กับประเภทที่มีความเสี่ยงหรือกระทบความปลอดภัยนักเรียน เช่น "ขออนุญาตออกนอกโรงเรียน" — ต้องผ่านทั้งครูที่ปรึกษาและครูฝ่ายปกครองก่อนถึงจะอนุมัติสมบูรณ์</p>
                    </div>
                </div>

                <div class="form-actions">
                    <a href="types_main.php" class="cancel-btn">ยกเลิก</a>
                    <button type="submit" class="submit-btn">✅ บันทึก</button>
                </div>

            </form>

        </div>
    </main>
</div>
</body>
</html>
