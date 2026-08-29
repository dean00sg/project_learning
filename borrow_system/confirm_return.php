<?php

session_start();

$page_title = "ยืนยันการคืนอุปกรณ์";
$css_path   = "../css/borrow.css";

require_once "../config/db.php";

// ตารางที่ใช้ในไฟล์นี้: user_accounts, user_staffs, equipment_item, borrow_requests
// โครงสร้างตารางแบบเต็มดูได้ที่ database/schema.sql

// =====================================================
// ตรวจสอบ Login
// =====================================================

if (!isset($_SESSION['user_id'])) {
    header("Location: ../login/index.php");
    exit;
}

$user_id   = (int)$_SESSION['user_id'];
$borrow_id = (int)($_GET['id'] ?? 0);

if ($borrow_id <= 0) {
    die("ไม่พบรายการแจ้งยืม");
}

function e($value)
{
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

// =====================================================
// ตรวจสอบสิทธิ์: เฉพาะบุคลากรที่ staff_type_code = 'equipment_officer'
// =====================================================

$sql = "
    SELECT ust.staff_id, ust.staff_type_code
    FROM user_accounts ua
    INNER JOIN user_staffs ust ON ust.user_id = ua.user_id
    WHERE ua.user_id = ? AND ua.is_active = 1
    LIMIT 1
";

$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $user_id);
$stmt->execute();

$staff = $stmt->get_result()->fetch_assoc();

$stmt->close();

if (!$staff || $staff['staff_type_code'] !== 'equipment_officer') {
    http_response_code(403);
    die("หน้านี้สำหรับเจ้าหน้าที่พัสดุ (equipment_officer) เท่านั้น");
}

// =====================================================
// ดึงรายการ
// =====================================================

$sql = "
    SELECT br.borrow_id, br.return_requested_at, br.return_item_at,
           br.return_image, br.return_note,
           ei.item_code, ei.item_name
    FROM borrow_requests br
    LEFT JOIN equipment_item ei ON ei.item_id = br.item_id
    WHERE br.borrow_id = ?
    LIMIT 1
";

$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $borrow_id);
$stmt->execute();

$request = $stmt->get_result()->fetch_assoc();

$stmt->close();

if (!$request) {
    die("ไม่พบรายการแจ้งยืม");
}

if (empty($request['return_requested_at'])) {
    die("รายการนี้ยังไม่ได้แจ้งคืน");
}

if (!empty($request['return_item_at'])) {
    die("รายการนี้ยืนยันการคืนไปแล้ว");
}
?>
<!DOCTYPE html>
<html lang="th">
<?php include __DIR__ . "/../includes/head.php"; ?>
<body>
<div class="borrow-page">

    <header class="borrow-header">
        <div class="header-inner">
            <div class="header-brand">
                <div class="brand-icon">✓</div>
                <div>
                    <h1>ยืนยันการคืนอุปกรณ์</h1>
                    <span>Equipment Borrow System</span>
                </div>
            </div>

            <a href="officer.php" class="back-home">← กลับ</a>
        </div>
    </header>

    <main class="borrow-container">
        <div class="borrow-card">

            <div class="card-title">
                <div class="title-icon">✓</div>
                <div>
                    <h3>ตรวจสอบและยืนยันการคืน</h3>
                    <p>#<?= str_pad((int)$request['borrow_id'], 4, "0", STR_PAD_LEFT) ?> — <?= e($request['item_name']) ?> (<?= e($request['item_code']) ?>)</p>
                </div>
            </div>

            <?php if (!empty($request['return_image'])): ?>
                <div class="form-group">
                    <label>รูปที่ผู้ยืมแนบมา</label>
                    <img
                        src="<?= e($request['return_image']) ?>"
                        alt="รูปภาพอุปกรณ์ตอนคืน"
                        style="max-width:100%; max-height:400px; border-radius:10px;"
                    >
                </div>
            <?php endif; ?>

            <?php if (!empty($request['return_note'])): ?>
                <div class="form-group">
                    <label>หมายเหตุจากผู้ยืม</label>
                    <textarea rows="3" readonly><?= e($request['return_note']) ?></textarea>
                </div>
            <?php endif; ?>

            <form action="update_borrow_status.php" method="POST">
                <input type="hidden" name="borrow_id" value="<?= e($request['borrow_id']) ?>">

                <div class="form-group">
                    <label>สภาพอุปกรณ์ที่ตรวจพบ <span class="required">*</span></label>
                    <select name="return_condition" id="return_condition" required onchange="toggleDetail()">
                        <option value="">-- เลือกสภาพอุปกรณ์ --</option>
                        <option value="normal">ปกติ</option>
                        <option value="damaged">ชำรุด/เสียหาย</option>
                    </select>
                </div>

                <div class="form-group" id="detail-group" style="display:none;">
                    <label>รายละเอียดความเสียหาย <span class="required">*</span></label>
                    <textarea name="return_detail" rows="4" placeholder="ระบุลักษณะความเสียหาย"></textarea>
                </div>

                <div class="form-actions">
                    <a href="officer.php" class="cancel-btn">ยกเลิก</a>
                    <button type="submit" class="submit-btn">✓ ยืนยันการคืน</button>
                </div>
            </form>

        </div>
    </main>
</div>

<script>
function toggleDetail() {
    var condition = document.getElementById('return_condition').value;
    var group = document.getElementById('detail-group');
    var textarea = group.querySelector('textarea');

    if (condition === 'damaged') {
        group.style.display = 'block';
        textarea.required = true;
    } else {
        group.style.display = 'none';
        textarea.required = false;
    }
}
</script>

</body>
</html>
