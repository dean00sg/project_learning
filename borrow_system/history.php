<?php

session_start();

$page_title = "ประวัติการยืม-คืน";
$css_path   = "../css/borrow.css";

require_once "../config/db.php";

// ตารางที่ใช้ในไฟล์นี้: user_accounts, user_staffs, equipment_item, borrow_requests
// โครงสร้างตารางแบบเต็มดูได้ที่ database/schema.sql

if (!isset($_SESSION['user_id'])) {
    header("Location: ../login/index.php");
    exit;
}

$user_id = (int)$_SESSION['user_id'];

function e($value)
{
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function getClassroomLabel($type, $code, $level)
{
    $parts = [];

    if (!empty($type)) {
        $parts[] = $type;
    }

    $parts[] = $code;

    if ($level !== null && $level !== '') {
        $parts[] = "/ " . $level;
    }

    return implode(' ', $parts);
}

function getBorrowTypeName($type)
{
    $types = [
        "classroom" => "ใช้ในห้องเรียน",
        "outside"   => "ใช้นอกห้องเรียน",
    ];

    return $types[$type] ?? $type;
}

/*
|--------------------------------------------------------------------------
| ตรวจสอบ User
|--------------------------------------------------------------------------
*/

$sql = "
    SELECT ua.user_id, ust.staff_id, ust.staff_type_code
    FROM user_accounts ua
    LEFT JOIN user_staffs ust ON ust.user_id = ua.user_id
    WHERE ua.user_id = ?
    LIMIT 1
";

$stmt = $conn->prepare($sql);

if (!$stmt) {
    die("SQL Error: " . $conn->error);
}

$stmt->bind_param("i", $user_id);
$stmt->execute();

$user = $stmt->get_result()->fetch_assoc();

$stmt->close();

if (!$user) {
    die("ไม่พบข้อมูลผู้ใช้งาน");
}

$is_officer = !empty($user['staff_id']) && ($user['staff_type_code'] ?? '') === 'equipment_officer';

$requests = [];

$select_columns = "
    br.borrow_id, br.borrow_type, br.classroom_id, br.request_detail, br.requester_at,
    br.return_requested_at, br.return_item_at, br.return_condition,

    ei.item_code, ei.item_name,

    c.classroom_type, c.classroom_number_code, c.classroom_level,

    ua.username,

    us.title_name AS req_student_title,
    us.first_name_th AS req_student_first_name,
    us.last_name_th AS req_student_last_name,

    ust.title_name AS req_staff_title,
    ust.first_name_th AS req_staff_first_name,
    ust.last_name_th AS req_staff_last_name
";

$joins = "
    FROM borrow_requests br
    LEFT JOIN equipment_item ei ON ei.item_id = br.item_id
    LEFT JOIN classroom c ON c.classroom_id = br.classroom_id
    LEFT JOIN user_accounts ua ON ua.user_id = br.requester_id
    LEFT JOIN user_students us ON us.user_id = br.requester_id
    LEFT JOIN user_staffs ust ON ust.user_id = br.requester_id
";

if ($is_officer) {
    // เจ้าหน้าที่พัสดุ: เห็นทุกรายการ
    $sql = "SELECT $select_columns $joins ORDER BY br.requester_at DESC";
    $stmt = $conn->prepare($sql);
} else {
    // นักเรียน/บุคลากรทั่วไป: เฉพาะรายการของตัวเอง
    $sql = "SELECT $select_columns $joins WHERE br.requester_id = ? ORDER BY br.requester_at DESC";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $user_id);
}

$stmt->execute();

$result = $stmt->get_result();

while ($row = $result->fetch_assoc()) {
    $requests[] = $row;
}

$stmt->close();
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
                    <h1>ประวัติการยืม-คืน</h1>
                    <span>Equipment Borrow System</span>
                </div>
            </div>

            <a href="main.php" class="back-home">← ยืม-คืน</a>
        </div>
    </header>

    <main class="borrow-container">

        <div class="page-heading">
            <div>
                <h2>ประวัติการยืม-คืน</h2>
                <p><?= $is_officer ? "รายการยืม-คืนทั้งหมด" : "รายการยืมของคุณ" ?></p>
            </div>
        </div>

        <div class="recent-card">
            <div class="table-wrapper">
                <table>
                    <thead>
                        <tr>
                            <th>เลขที่</th>
                            <th>วันที่ยืม</th>
                            <?php if ($is_officer): ?>
                                <th>ผู้ยืม</th>
                            <?php endif; ?>
                            <th>อุปกรณ์</th>
                            <th>ลักษณะการใช้งาน</th>
                            <th>สถานะ</th>
                            <th>ดู</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!empty($requests)): ?>
                            <?php foreach ($requests as $request): ?>
                                <?php
                                if (!empty($request['return_item_at'])) {
                                    if ($request['return_condition'] === 'damaged') {
                                        $status_text  = "คืนแล้ว (ชำรุด)";
                                        $status_class = "damaged";
                                    } else {
                                        $status_text  = "คืนสำเร็จ";
                                        $status_class = "done";
                                    }
                                } elseif (!empty($request['return_requested_at'])) {
                                    $status_text  = "รอเจ้าหน้าที่ตรวจสอบการคืน";
                                    $status_class = "waiting";
                                } else {
                                    $status_text  = "กำลังยืม";
                                    $status_class = "borrowed";
                                }

                                if (!empty($request['req_student_first_name'])) {
                                    $requester = trim(
                                        ($request['req_student_title'] ?? "") . " " .
                                        $request['req_student_first_name'] . " " .
                                        $request['req_student_last_name']
                                    );
                                } elseif (!empty($request['req_staff_first_name'])) {
                                    $requester = trim(
                                        ($request['req_staff_title'] ?? "") . " " .
                                        $request['req_staff_first_name'] . " " .
                                        $request['req_staff_last_name']
                                    );
                                } else {
                                    $requester = $request['username'] ?? "-";
                                }
                                ?>
                                <tr>
                                    <td><strong>#<?= str_pad((int)$request['borrow_id'], 4, "0", STR_PAD_LEFT) ?></strong></td>
                                    <td>
                                        <?= !empty($request['requester_at'])
                                            ? date("d/m/Y H:i", strtotime($request['requester_at']))
                                            : "-" ?>
                                    </td>
                                    <?php if ($is_officer): ?>
                                        <td><?= e($requester) ?></td>
                                    <?php endif; ?>
                                    <td><?= e($request['item_name'] ?? "-") ?></td>
                                    <td>
                                        <?= e(getBorrowTypeName($request['borrow_type'])) ?>
                                        <?php if (!empty($request['classroom_number_code'])): ?>
                                            (<?= e(getClassroomLabel($request['classroom_type'], $request['classroom_number_code'], $request['classroom_level'])) ?>)
                                        <?php endif; ?>
                                    </td>
                                    <td><span class="status <?= e($status_class) ?>"><?= e($status_text) ?></span></td>
                                    <td><a href="detail.php?id=<?= e($request['borrow_id']) ?>">ดูรายละเอียด</a></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="<?= $is_officer ? 6 : 5 ?>" class="empty-data">ยังไม่มีรายการแจ้งยืม</td>
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
