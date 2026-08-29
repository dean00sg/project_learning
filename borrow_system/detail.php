<?php

session_start();

$page_title = "รายละเอียดการยืม-คืน";
$css_path   = "../css/borrow.css";

require_once "../config/db.php";

// ตารางที่ใช้ในไฟล์นี้: user_accounts, user_students, user_staffs,
//                     equipment_item, borrow_requests
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

function getBorrowTypeName($type)
{
    $types = [
        "classroom" => "ใช้ในห้องเรียน",
        "outside"   => "ใช้นอกห้องเรียน",
    ];

    return $types[$type] ?? $type;
}

// =====================================================
// ดึงรายการ
// =====================================================

$sql = "
    SELECT
        br.borrow_id, br.item_id, br.requester_id, br.borrow_type, br.classroom_id,
        br.request_detail, br.requester_at,
        br.return_requested_at, br.return_image, br.return_note,
        br.return_item_by, br.return_item_at, br.return_condition, br.return_detail,

        ei.item_code, ei.item_name,

        c.classroom_number_code,

        ua.username,

        us.title_name AS student_title,
        us.first_name_th AS student_first_name,
        us.last_name_th AS student_last_name,

        officer_ua.username AS officer_username

    FROM borrow_requests br
    LEFT JOIN equipment_item ei ON ei.item_id = br.item_id
    LEFT JOIN classroom c ON c.classroom_id = br.classroom_id
    LEFT JOIN user_accounts ua ON ua.user_id = br.requester_id
    LEFT JOIN user_students us ON us.user_id = br.requester_id
    LEFT JOIN user_accounts officer_ua ON officer_ua.user_id = br.return_item_by
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

// =====================================================
// ตรวจสิทธิ์: เจ้าของ หรือเจ้าหน้าที่พัสดุ
// =====================================================

$is_owner = (int)$request['requester_id'] === $user_id;

$sql = "SELECT staff_type_code FROM user_staffs WHERE user_id = ? LIMIT 1";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$staff_row = $stmt->get_result()->fetch_assoc();
$stmt->close();

$is_officer = $staff_row && $staff_row['staff_type_code'] === 'equipment_officer';

if (!$is_owner && !$is_officer) {
    http_response_code(403);
    die("คุณไม่มีสิทธิ์ดูรายการแจ้งยืมนี้");
}

// =====================================================
// ชื่อผู้ยืม
// =====================================================

if (!empty($request['student_first_name'])) {
    $requester_name = trim(
        ($request['student_title'] ?? "") . " " .
        ($request['student_first_name'] ?? "") . " " .
        ($request['student_last_name'] ?? "")
    );
} else {
    $requester_name = $request['username'] ?? "-";
}

// =====================================================
// สถานะ
// =====================================================

if (!empty($request['return_item_at'])) {
    if ($request['return_condition'] === 'damaged') {
        $status       = "คืนแล้ว (ชำรุด)";
        $status_class = "damaged";
    } else {
        $status       = "คืนสำเร็จ";
        $status_class = "done";
    }
} elseif (!empty($request['return_requested_at'])) {
    $status       = "รอเจ้าหน้าที่ตรวจสอบการคืน";
    $status_class = "waiting";
} else {
    $status       = "กำลังยืม";
    $status_class = "borrowed";
}

$can_notify_return = $is_owner && empty($request['return_requested_at']) && empty($request['return_item_at']);
?>
<!DOCTYPE html>
<html lang="th">
<?php include __DIR__ . "/../includes/head.php"; ?>
<body>
<div class="borrow-page">

    <header class="borrow-header">
        <div class="header-inner">
            <div class="header-brand">
                <div class="brand-icon">📦</div>
                <div>
                    <h1>รายละเอียดการยืม-คืน</h1>
                    <span>Equipment Borrow System</span>
                </div>
            </div>

            <a href="main.php" class="back-home">← กลับ</a>
        </div>
    </header>

    <main class="borrow-container">
        <div class="borrow-card">

            <div class="card-title">
                <div class="title-icon">📋</div>
                <div>
                    <h3>รายละเอียดการยืม-คืน</h3>
                    <p>เลขที่ #<?= str_pad((int)$request['borrow_id'], 4, "0", STR_PAD_LEFT) ?></p>
                </div>
            </div>

            <div class="form-group">
                <label>ผู้ยืม</label>
                <input type="text" value="<?= e($requester_name) ?>" readonly>
            </div>

            <div class="form-group">
                <label>อุปกรณ์</label>
                <input type="text" value="<?= e($request['item_name'] ?? '-') ?> (<?= e($request['item_code'] ?? '-') ?>)" readonly>
            </div>

            <div class="form-group">
                <label>วันที่ยืม</label>
                <input
                    type="text"
                    value="<?= !empty($request['requester_at'])
                        ? date("d/m/Y H:i", strtotime($request['requester_at']))
                        : "-" ?>"
                    readonly
                >
            </div>

            <div class="form-group">
                <label>ลักษณะการใช้งาน</label>
                <input
                    type="text"
                    value="<?= e(getBorrowTypeName($request['borrow_type'])) ?><?= !empty($request['classroom_number_code']) ? ' (ห้อง ' . e($request['classroom_number_code']) . ')' : '' ?>"
                    readonly
                >
            </div>

            <div class="form-group">
                <label>เหตุผล/รายละเอียดการยืม</label>
                <textarea rows="4" readonly><?= e($request['request_detail']) ?></textarea>
            </div>

            <div class="form-group">
                <label>สถานะ</label>
                <div>
                    <span class="status <?= e($status_class) ?>"><?= e($status) ?></span>
                </div>
            </div>

            <?php if (!empty($request['return_requested_at'])): ?>
                <div class="notice-box">
                    <div class="notice-icon">📷</div>
                    <div>
                        <strong>แจ้งคืนแล้ว</strong>
                        <p>
                            วันที่แจ้งคืน: <?= date("d/m/Y H:i", strtotime($request['return_requested_at'])) ?>
                            <?php if (!empty($request['return_note'])): ?>
                                <br>หมายเหตุ: <?= e($request['return_note']) ?>
                            <?php endif; ?>
                        </p>
                        <?php if (!empty($request['return_image'])): ?>
                            <img
                                src="<?= e($request['return_image']) ?>"
                                alt="รูปภาพอุปกรณ์ตอนคืน"
                                style="max-width:100%; max-height:400px; border-radius:10px; margin-top:10px;"
                            >
                        <?php endif; ?>
                    </div>
                </div>
            <?php endif; ?>

            <?php if (!empty($request['return_item_at'])): ?>
                <div class="notice-box">
                    <div class="notice-icon"><?= $request['return_condition'] === 'damaged' ? '⚠️' : '✅' ?></div>
                    <div>
                        <strong>เจ้าหน้าที่ยืนยันการคืนแล้ว (<?= $request['return_condition'] === 'damaged' ? 'ชำรุด/เสียหาย' : 'ปกติ' ?>)</strong>
                        <p>
                            ผู้ยืนยัน: <?= e($request['officer_username'] ?? "-") ?><br>
                            วันที่ยืนยัน: <?= date("d/m/Y H:i", strtotime($request['return_item_at'])) ?>
                            <?php if (!empty($request['return_detail'])): ?>
                                <br>รายละเอียด: <?= e($request['return_detail']) ?>
                            <?php endif; ?>
                        </p>
                    </div>
                </div>
            <?php endif; ?>

            <!-- ปุ่มแจ้งคืนสำหรับเจ้าของรายการ -->
            <?php if ($can_notify_return): ?>
                <div style="margin-top:20px; padding:20px; border-radius:12px; background:#f0f6ff; border:1px solid #bfdbfe;">
                    <strong>ใช้อุปกรณ์เสร็จแล้ว?</strong>
                    <p style="margin:8px 0 15px;">แจ้งคืนพร้อมแนบรูปภาพ เพื่อให้เจ้าหน้าที่ตรวจสอบและยืนยัน</p>
                    <a href="return_notify.php?id=<?= e($request['borrow_id']) ?>" class="approve-btn" style="text-decoration:none; padding:10px 20px;">📷 แจ้งคืน</a>
                </div>
            <?php endif; ?>

            <div class="form-actions">
                <a href="main.php" class="cancel-btn">← กลับหน้าหลัก</a>
            </div>

        </div>
    </main>
</div>
</body>
</html>
