<?php

session_start();

$page_title = "รายละเอียดการแจ้งซ่อม";
$css_path   = "../css/repair.css";

require_once "../config/db.php";

// ตารางที่ใช้ในไฟล์นี้: repair_requests, classroom, user_accounts, user_students
// โครงสร้างตารางแบบเต็มดูได้ที่ database/schema.sql

// =====================================================
// ตรวจสอบ Login
// =====================================================

if (!isset($_SESSION['user_id'])) {
    header("Location: ../login/index.php");
    exit;
}

$user_id    = (int)$_SESSION['user_id'];
$request_id = (int)($_GET['id'] ?? 0);

if ($request_id <= 0) {
    die("ไม่พบรายการแจ้งซ่อม");
}

// =====================================================
// ฟังก์ชัน
// =====================================================

function e($value)
{
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function getTypeName($type)
{
    $types = [
        "computer"        => "คอมพิวเตอร์ / Notebook",
        "projector"       => "โปรเจกเตอร์",
        "printer"         => "เครื่องพิมพ์",
        "network"         => "ระบบเครือข่าย / Internet",
        "electric"        => "ระบบไฟฟ้า",
        "air_conditioner" => "เครื่องปรับอากาศ",
        "other"           => "อื่น ๆ",
    ];

    return $types[$type] ?? $type;
}

/**
 * ตรวจว่า $user_id เป็นครูที่ปรึกษาของห้อง โดยอ่านจากคอลัมน์
 * classroom.advisor_staff_id ซึ่งเก็บเป็น JSON array ของ staff user_id
 * เช่น "[1]" หรือ "[1,4]"
 */
function isAdvisorOf($advisor_staff_id, $user_id)
{
    if (empty($advisor_staff_id) || !is_string($advisor_staff_id)) {
        return false;
    }

    json_decode($advisor_staff_id);

    if (json_last_error() !== JSON_ERROR_NONE) {
        return false;
    }

    $advisor_ids = json_decode($advisor_staff_id, true);

    if (!is_array($advisor_ids)) {
        return false;
    }

    foreach ($advisor_ids as $advisor_id) {
        if ((int)$advisor_id === (int)$user_id) {
            return true;
        }
    }

    return false;
}

// =====================================================
// ดึงรายการ
// =====================================================

$sql = "
    SELECT
        r.request_id, r.request_type, r.requester_id, r.request_datetime,
        r.repair_detail, r.request_image, r.approved_by, r.approved_at,

        c.classroom_id, c.classroom_number_code, c.classroom_level,
        c.building, c.advisor_staff_id,

        ua.username,

        us.title_name AS student_title,
        us.first_name_th AS student_first_name,
        us.last_name_th AS student_last_name,

        approver_ua.username AS approver_username

    FROM repair_requests r
    INNER JOIN classroom c ON c.classroom_id = r.classroom_id
    LEFT JOIN user_accounts ua ON ua.user_id = r.requester_id
    LEFT JOIN user_students us ON us.user_id = r.requester_id
    LEFT JOIN user_accounts approver_ua ON approver_ua.user_id = r.approved_by
    WHERE r.request_id = ?
    LIMIT 1
";

$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $request_id);
$stmt->execute();

$request = $stmt->get_result()->fetch_assoc();

$stmt->close();

if (!$request) {
    die("ไม่พบรายการแจ้งซ่อม");
}

// =====================================================
// ตรวจสิทธิ์: เจ้าของ หรือครูที่ปรึกษาของห้องนั้น
// =====================================================

$is_owner   = (int)$request['requester_id'] === $user_id;
$is_advisor = isAdvisorOf($request['advisor_staff_id'] ?? null, $user_id);

if (!$is_owner && !$is_advisor) {
    http_response_code(403);
    die("คุณไม่มีสิทธิ์ดูรายการแจ้งซ่อมนี้");
}

// =====================================================
// ชื่อผู้แจ้ง
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

if (!empty($request['approved_by'])) {
    $status       = "อนุมัติแล้ว";
    $status_class = "approved";
} else {
    $status       = "รอครูอนุมัติ";
    $status_class = "waiting";
}

// อนุมัติได้เฉพาะครูที่ปรึกษาของห้องนั้น ที่ไม่ใช่เจ้าของรายการเอง
// และรายการยังไม่ถูกอนุมัติ
$can_approve = $is_advisor && !$is_owner && empty($request['approved_by']);
?>
<!DOCTYPE html>
<html lang="th">
<?php include __DIR__ . "/../includes/head.php"; ?>
<body>
<div class="repair-page">

    <header class="repair-header">
        <div class="header-inner">
            <div class="header-brand">
                <div class="brand-icon">🔧</div>
                <div>
                    <h1>รายละเอียดการแจ้งซ่อม</h1>
                    <span>Equipment Repair Request System</span>
                </div>
            </div>

            <a href="main.php" class="back-home">← กลับ</a>
        </div>
    </header>

    <main class="repair-container">
        <div class="repair-card">

            <div class="card-title">
                <div class="title-icon">📋</div>
                <div>
                    <h3>รายละเอียดการแจ้งซ่อม</h3>
                    <p>เลขที่ #<?= str_pad((int)$request['request_id'], 4, "0", STR_PAD_LEFT) ?></p>
                </div>
            </div>

            <div class="form-group">
                <label>ผู้แจ้ง</label>
                <input type="text" value="<?= e($requester_name) ?>" readonly>
            </div>

            <div class="form-group">
                <label>ห้องเรียน</label>
                <input type="text" value="<?= e($request['classroom_number_code'] ?? "-") ?>" readonly>
            </div>

            <div class="form-group">
                <label>วันที่แจ้ง</label>
                <input
                    type="text"
                    value="<?= !empty($request['request_datetime'])
                        ? date("d/m/Y H:i", strtotime($request['request_datetime']))
                        : "-" ?>"
                    readonly
                >
            </div>

            <div class="form-group">
                <label>ประเภทการแจ้งซ่อม</label>
                <input type="text" value="<?= e(getTypeName($request['request_type'])) ?>" readonly>
            </div>

            <div class="form-group">
                <label>รายละเอียดปัญหา</label>
                <textarea rows="7" readonly><?= e($request['repair_detail']) ?></textarea>
            </div>

            <?php if (!empty($request['request_image'])): ?>
                <div class="form-group">
                    <label>รูปภาพปัญหา</label>
                    <img
                        src="<?= e($request['request_image']) ?>"
                        alt="รูปภาพแจ้งซ่อม"
                        style="max-width:100%; max-height:500px; border-radius:10px;"
                    >
                </div>
            <?php endif; ?>

            <div class="form-group">
                <label>สถานะ</label>
                <div>
                    <span class="status <?= e($status_class) ?>"><?= e($status) ?></span>
                </div>
            </div>

            <?php if (!empty($request['approved_by'])): ?>
                <div class="notice-box">
                    <div class="notice-icon">✓</div>
                    <div>
                        <strong>อนุมัติแล้ว</strong>
                        <p>
                            ผู้อนุมัติ: <?= e($request['approver_username'] ?? "-") ?><br>
                            วันที่อนุมัติ:
                            <?= !empty($request['approved_at'])
                                ? date("d/m/Y H:i", strtotime($request['approved_at']))
                                : "-" ?>
                        </p>
                    </div>
                </div>
            <?php else: ?>
                <div class="notice-box">
                    <div class="notice-icon">ℹ️</div>
                    <div>
                        <strong>รอครูที่ปรึกษาอนุมัติ</strong>
                        <p>รายการนี้ยังไม่ได้รับการอนุมัติจากครูที่ปรึกษา</p>
                    </div>
                </div>
            <?php endif; ?>

            <!-- ปุ่มสำหรับครูที่ปรึกษา -->
            <?php if ($can_approve): ?>
                <div style="margin-top:20px; padding:20px; border-radius:12px; background:#f0fdf4; border:1px solid #bbf7d0;">
                    <strong>คุณเป็นครูที่ปรึกษาของห้องนี้</strong>
                    <p style="margin:8px 0 15px;">สามารถอนุมัติรายการแจ้งซ่อมนี้ได้</p>

                    <form action="approve.php" method="POST" onsubmit="return confirm('ยืนยันการอนุมัติรายการแจ้งซ่อมนี้หรือไม่?');">
                        <input type="hidden" name="request_id" value="<?= e($request['request_id']) ?>">
                        <button type="submit" class="approve-btn" style="padding:10px 20px; cursor:pointer;">✓ อนุมัติรายการนี้</button>
                    </form>
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
