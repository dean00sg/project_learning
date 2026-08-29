<?php

session_start();

$page_title = "แจ้งคืนอุปกรณ์";
$css_path   = "../css/borrow.css";

require_once "../config/db.php";

// ตารางที่ใช้ในไฟล์นี้: equipment_item, borrow_requests
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
// ดึงรายการ (เจ้าของรายการเท่านั้น)
// =====================================================

$sql = "
    SELECT br.borrow_id, br.requester_id, br.return_requested_at, br.return_item_at,
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

if ((int)$request['requester_id'] !== $user_id) {
    http_response_code(403);
    die("คุณไม่มีสิทธิ์แจ้งคืนรายการนี้");
}

if (!empty($request['return_requested_at'])) {
    die("รายการนี้แจ้งคืนไปแล้ว รอเจ้าหน้าที่ตรวจสอบ");
}

if (!empty($request['return_item_at'])) {
    die("รายการนี้คืนอุปกรณ์เรียบร้อยแล้ว");
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
                <div class="brand-icon">📷</div>
                <div>
                    <h1>แจ้งคืนอุปกรณ์</h1>
                    <span>Equipment Borrow System</span>
                </div>
            </div>

            <a href="main.php" class="back-home">← กลับ</a>
        </div>
    </header>

    <main class="borrow-container">
        <div class="borrow-card">

            <div class="card-title">
                <div class="title-icon">📷</div>
                <div>
                    <h3>แจ้งคืนอุปกรณ์</h3>
                    <p>#<?= str_pad((int)$request['borrow_id'], 4, "0", STR_PAD_LEFT) ?> — <?= e($request['item_name']) ?> (<?= e($request['item_code']) ?>)</p>
                </div>
            </div>

            <form action="request_return.php" method="POST" enctype="multipart/form-data">
                <input type="hidden" name="borrow_id" value="<?= e($request['borrow_id']) ?>">

                <div class="form-group">
                    <label>ถ่ายรูปอุปกรณ์ตอนคืน <span class="required">*</span></label>
                    <input type="file" name="return_image" accept="image/png,image/jpeg" required>
                </div>

                <div class="form-group">
                    <label>หมายเหตุ (ถ้ามี)</label>
                    <textarea name="return_note" rows="3" placeholder="เช่น อุปกรณ์อยู่ในสภาพปกติ"></textarea>
                </div>

                <div class="notice-box">
                    <div class="notice-icon">ℹ️</div>
                    <div>
                        <strong>ขั้นตอนหลังจากแจ้งคืน</strong>
                        <p>เจ้าหน้าที่พัสดุจะตรวจสอบอุปกรณ์และยืนยันการคืนอีกครั้ง สถานะจะเปลี่ยนเป็น "คืนสำเร็จ" เมื่อเจ้าหน้าที่ยืนยันแล้ว</p>
                    </div>
                </div>

                <div class="form-actions">
                    <a href="main.php" class="cancel-btn">ยกเลิก</a>
                    <button type="submit" class="submit-btn">📷 ส่งแจ้งคืน</button>
                </div>
            </form>

        </div>
    </main>
</div>
</body>
</html>
