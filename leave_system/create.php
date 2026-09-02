<?php

session_start();

$page_title = "สร้างคำขอลา/ขออนุญาต";
$css_path   = "../css/leave.css";

require_once "../config/db.php";

// ตารางที่ใช้ในไฟล์นี้: user_students, leave_types
// โครงสร้างตารางแบบเต็มดูได้ที่ database/leave_system.sql

// =====================================================
// ตรวจสอบสิทธิ์: เฉพาะนักเรียนเท่านั้น
// =====================================================

if (!isset($_SESSION['user_id'])) {
    header("Location: ../login/index.php");
    exit;
}

$user_id = (int)$_SESSION['user_id'];

function e($value)
{
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

$sql = "SELECT student_id, classroom_id FROM user_students WHERE user_id = ? LIMIT 1";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$student = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$student) {
    http_response_code(403);
    die("หน้านี้สำหรับนักเรียนเท่านั้น");
}

if (empty($student['classroom_id'])) {
    die("บัญชีนี้ยังไม่มีห้องเรียน กรุณาติดต่อเจ้าหน้าที่ก่อนยื่นคำขอ");
}

// =====================================================
// รายการประเภทการลาที่เปิดใช้งาน
// =====================================================

$leave_types = [];
$result = $conn->query("SELECT leave_type_id, leave_type_name, detail FROM leave_types WHERE is_active = 1 ORDER BY leave_type_name");

if ($result) {
    while ($row = $result->fetch_assoc()) {
        $leave_types[] = $row;
    }
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
                <div class="brand-icon">📝</div>
                <div>
                    <h1>สร้างคำขอลา/ขออนุญาต</h1>
                    <span>Leave &amp; Permission Request System</span>
                </div>
            </div>

            <a href="main.php" class="back-home">← กลับ</a>
        </div>
    </header>

    <main class="leave-container">
        <div class="leave-card">

            <div class="card-title">
                <div class="title-icon">📝</div>
                <div>
                    <h3>รายละเอียดคำขอ</h3>
                    <p>ยื่นแล้วต้องรอครูที่ปรึกษาตรวจสอบและอนุมัติก่อนจึงจะมีผล</p>
                </div>
            </div>

            <?php if (empty($leave_types)): ?>
                <div class="notice-box">
                    <div class="notice-icon">ℹ️</div>
                    <div><strong>ยังไม่มีประเภทการลาที่เปิดใช้งาน</strong></div>
                </div>
            <?php else: ?>

                <form action="store.php" method="POST" enctype="multipart/form-data">

                    <div class="form-group">
                        <label>ประเภทการลา/ขออนุญาต <span class="required">*</span></label>
                        <select name="leave_type_id" required>
                            <option value="">-- เลือกประเภท --</option>
                            <?php foreach ($leave_types as $t): ?>
                                <option value="<?= e($t['leave_type_id']) ?>"><?= e($t['leave_type_name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-grid">
                        <div class="form-group">
                            <label>วันที่เริ่ม <span class="required">*</span></label>
                            <input type="date" name="start_date" required>
                        </div>
                        <div class="form-group">
                            <label>วันที่สิ้นสุด <span class="required">*</span></label>
                            <input type="date" name="end_date" required>
                        </div>
                    </div>

                    <div class="form-group">
                        <label>เหตุผล <span class="required">*</span></label>
                        <textarea name="reason" rows="4" placeholder="ระบุเหตุผลการลา/ขออนุญาต" required></textarea>
                    </div>

                    <div class="form-group">
                        <label>เอกสารประกอบ (ถ้ามี)</label>
                        <input type="file" name="evidence_image" accept="image/png,image/jpeg">
                    </div>

                    <div class="form-actions">
                        <a href="main.php" class="cancel-btn">ยกเลิก</a>
                        <button type="submit" class="submit-btn">📨 ส่งคำขอ</button>
                    </div>

                </form>

            <?php endif; ?>
        </div>
    </main>
</div>
</body>
</html>
