<?php

session_start();

$page_title = "ตรวจสอบการคืนอุปกรณ์";
$css_path   = "../css/borrow.css";

require_once "../config/db.php";

// ตารางที่ใช้ในไฟล์นี้: user_accounts, user_staffs, user_students,
//                     equipment_item, borrow_requests
// โครงสร้างตารางแบบเต็มดูได้ที่ database/schema.sql

// =====================================================
// ตรวจสอบ Login
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

function getBorrowTypeName($type)
{
    $types = [
        "classroom" => "ใช้ในห้องเรียน",
        "outside"   => "ใช้นอกห้องเรียน",
    ];

    return $types[$type] ?? $type;
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
// ดึงรายการที่แจ้งคืนแล้ว รอเจ้าหน้าที่ตรวจสอบยืนยัน
// =====================================================

$sql = "
    SELECT
        br.borrow_id, br.borrow_type, br.requester_at,
        br.return_requested_at, br.return_image, br.return_note,

        ei.item_code, ei.item_name,

        ua.username AS requester_username,

        req_us.title_name AS req_student_title,
        req_us.first_name_th AS req_student_first_name,
        req_us.last_name_th AS req_student_last_name,

        req_ust.title_name AS req_staff_title,
        req_ust.first_name_th AS req_staff_first_name,
        req_ust.last_name_th AS req_staff_last_name

    FROM borrow_requests br
    LEFT JOIN equipment_item ei ON ei.item_id = br.item_id
    LEFT JOIN user_accounts ua ON ua.user_id = br.requester_id
    LEFT JOIN user_students req_us ON req_us.user_id = br.requester_id
    LEFT JOIN user_staffs req_ust ON req_ust.user_id = br.requester_id
    WHERE br.return_requested_at IS NOT NULL AND br.return_item_at IS NULL
    ORDER BY br.return_requested_at ASC
";

$queue = [];
$result = $conn->query($sql);

if ($result) {
    while ($row = $result->fetch_assoc()) {
        $queue[] = $row;
    }
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
                <div class="brand-icon">🗂️</div>
                <div>
                    <h1>ตรวจสอบการคืนอุปกรณ์</h1>
                    <span>Equipment Borrow System</span>
                </div>
            </div>

            <a href="main.php" class="back-home">← กลับ</a>
        </div>
    </header>

    <main class="borrow-container">

        <div class="page-heading">
            <div>
                <h2>รอตรวจสอบการคืน</h2>
                <p>ตรวจสอบรูปที่ผู้ยืมแนบมา แล้วยืนยันสภาพอุปกรณ์</p>
            </div>
        </div>

        <div class="recent-card">
            <div class="table-wrapper">
                <table>
                    <thead>
                        <tr>
                            <th>เลขที่</th>
                            <th>วันที่แจ้งคืน</th>
                            <th>ผู้ยืม</th>
                            <th>อุปกรณ์</th>
                            <th>รูปที่แนบ</th>
                            <th>หมายเหตุผู้ยืม</th>
                            <th>การดำเนินการ</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!empty($queue)): ?>
                            <?php foreach ($queue as $item): ?>
                                <?php
                                if (!empty($item['req_student_first_name'])) {
                                    $requester_name = trim(
                                        ($item['req_student_title'] ?? "") . " " .
                                        $item['req_student_first_name'] . " " .
                                        $item['req_student_last_name']
                                    );
                                } elseif (!empty($item['req_staff_first_name'])) {
                                    $requester_name = trim(
                                        ($item['req_staff_title'] ?? "") . " " .
                                        $item['req_staff_first_name'] . " " .
                                        $item['req_staff_last_name']
                                    );
                                } else {
                                    $requester_name = $item['requester_username'] ?? "-";
                                }
                                ?>
                                <tr>
                                    <td><strong>#<?= str_pad((int)$item['borrow_id'], 4, "0", STR_PAD_LEFT) ?></strong></td>
                                    <td>
                                        <?= !empty($item['return_requested_at'])
                                            ? date("d/m/Y H:i", strtotime($item['return_requested_at']))
                                            : "-" ?>
                                    </td>
                                    <td><?= e($requester_name) ?></td>
                                    <td><?= e($item['item_name'] ?? "-") ?> (<?= e($item['item_code'] ?? "-") ?>)</td>
                                    <td>
                                        <?php if (!empty($item['return_image'])): ?>
                                            <a href="<?= e($item['return_image']) ?>" target="_blank">ดูรูป</a>
                                        <?php else: ?>
                                            -
                                        <?php endif; ?>
                                    </td>
                                    <td><?= e(mb_strimwidth($item['return_note'] ?? "", 0, 40, "...")) ?></td>
                                    <td>
                                        <a href="confirm_return.php?id=<?= e($item['borrow_id']) ?>" class="approve-btn" style="text-decoration:none;">✓ ตรวจสอบและยืนยัน</a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="7" class="empty-data">ไม่มีรายการรอตรวจสอบการคืน</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

    </main>
</div>
</body>
</html>
