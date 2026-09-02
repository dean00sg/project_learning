<?php

session_start();

$page_title = "แก้ไขประเภทการลา";
$css_path   = "../css/leave.css";

require_once "../config/db.php";

// ตารางที่ใช้ในไฟล์นี้: leave_types
// โครงสร้างตารางแบบเต็มดูได้ที่ database/leave_system.sql

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

$leave_type_id = (int)($_GET['id'] ?? 0);

if ($leave_type_id <= 0) {
    die("ไม่พบประเภทการลา");
}

function e($value)
{
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

$sql = "SELECT * FROM leave_types WHERE leave_type_id = ? LIMIT 1";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $leave_type_id);
$stmt->execute();
$leave_type = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$leave_type) {
    die("ไม่พบประเภทการลา");
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
                    <h1>แก้ไขประเภทการลา</h1>
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
                    <h3><?= e($leave_type['leave_type_name']) ?></h3>
                </div>
            </div>

            <form action="types_update.php" method="POST">
                <input type="hidden" name="leave_type_id" value="<?= e($leave_type_id) ?>">

                <div class="form-group">
                    <label>ชื่อประเภท <span class="required">*</span></label>
                    <input type="text" name="leave_type_name" value="<?= e($leave_type['leave_type_name']) ?>" required>
                </div>

                <div class="form-group">
                    <label>รายละเอียด</label>
                    <textarea name="detail" rows="3"><?= e($leave_type['detail'] ?? '') ?></textarea>
                </div>

                <div class="form-group">
                    <label class="checkbox-item">
                        <input type="checkbox" name="requires_discipline_approval" value="1" <?= $leave_type['requires_discipline_approval'] ? 'checked' : '' ?>>
                        ต้องผ่านครูฝ่ายปกครองอนุมัติต่อจากครูที่ปรึกษา
                    </label>
                </div>

                <div class="form-group">
                    <label class="checkbox-item">
                        <input type="checkbox" name="is_active" value="1" <?= $leave_type['is_active'] ? 'checked' : '' ?>>
                        เปิดใช้งาน (นักเรียนเลือกประเภทนี้ได้ตอนสร้างคำขอ)
                    </label>
                </div>

                <div class="form-actions">
                    <a href="types_main.php" class="cancel-btn">ยกเลิก</a>
                    <button type="submit" class="submit-btn">💾 บันทึกการแก้ไข</button>
                </div>

            </form>

        </div>
    </main>
</div>
</body>
</html>
