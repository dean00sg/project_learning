<?php

session_start();

$page_title = "ยืม-คืนอุปกรณ์";
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

$user_id = (int)$_SESSION['user_id'];

// =====================================================
// ฟังก์ชัน
// =====================================================

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

/**
 * ตรวจว่า $user_id เป็นครูที่ปรึกษาของห้อง โดยอ่านจากคอลัมน์
 * classroom.advisor_staff_id ซึ่งเก็บเป็น JSON array ของ staff user_id
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

/**
 * คำนวณสถานะของรายการยืม-คืนจากคอลัมน์ต่าง ๆ (ไม่มีคอลัมน์ status รวม)
 * ยืมได้ทันทีโดยไม่ต้องอนุมัติ ขั้นตอนที่ต้องตรวจสอบมีแค่ "คืน"
 */
function getBorrowStatus($request)
{
    if (!empty($request['return_item_at'])) {
        if ($request['return_condition'] === 'damaged') {
            return ["text" => "คืนแล้ว (ชำรุด)", "class" => "damaged"];
        }
        return ["text" => "คืนสำเร็จ", "class" => "done"];
    }

    if (!empty($request['return_requested_at'])) {
        return ["text" => "รอเจ้าหน้าที่ตรวจสอบการคืน", "class" => "waiting"];
    }

    return ["text" => "กำลังยืม", "class" => "borrowed"];
}

// =====================================================
// ดึงข้อมูลผู้ใช้งาน
// =====================================================

$sql = "
    SELECT
        ua.user_id, ua.username, ua.role, ua.is_active,

        us.student_id, us.title_name AS student_title,
        us.first_name_th AS student_first_name,
        us.last_name_th AS student_last_name,
        us.classroom_id AS student_classroom_id,

        ust.staff_id, ust.staff_type_code, ust.title_name AS staff_title,
        ust.first_name_th AS staff_first_name,
        ust.last_name_th AS staff_last_name

    FROM user_accounts ua
    LEFT JOIN user_students us ON us.user_id = ua.user_id
    LEFT JOIN user_staffs ust ON ust.user_id = ua.user_id
    WHERE ua.user_id = ?
    LIMIT 1
";

$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $user_id);
$stmt->execute();

$user = $stmt->get_result()->fetch_assoc();

$stmt->close();

if (!$user) {
    die("ไม่พบข้อมูลผู้ใช้งาน");
}

if ((int)$user['is_active'] !== 1) {
    die("บัญชีผู้ใช้งานนี้ถูกปิดการใช้งาน");
}

$is_student = !empty($user['student_id']);
$is_staff   = !empty($user['staff_id']);
$is_officer = $is_staff && ($user['staff_type_code'] ?? '') === 'equipment_officer';

$requester_name = "";

if ($is_staff) {
    $requester_name = trim(
        ($user['staff_title'] ?? "") . " " .
        ($user['staff_first_name'] ?? "") . " " .
        ($user['staff_last_name'] ?? "")
    );
}

if ($requester_name === "" && $is_student) {
    $requester_name = trim(
        ($user['student_title'] ?? "") . " " .
        ($user['student_first_name'] ?? "") . " " .
        ($user['student_last_name'] ?? "")
    );
}

if ($requester_name === "") {
    $requester_name = $user['username'];
}

// =====================================================
// ห้องเรียน (ใช้เมื่อเลือก "ใช้ในห้องเรียน")
//
// นักเรียน: ห้องของตัวเอง (readonly)
// บุคลากร : เฉพาะห้องที่ตนเป็นครูที่ปรึกษา
// =====================================================

$student_classroom = null;

if ($is_student && !empty($user['student_classroom_id'])) {
    $classroom_id = (int)$user['student_classroom_id'];

    $sql = "SELECT classroom_id, classroom_number_code FROM classroom WHERE classroom_id = ? LIMIT 1";

    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $classroom_id);
    $stmt->execute();

    $student_classroom = $stmt->get_result()->fetch_assoc();

    $stmt->close();
}

$advised_classrooms = [];

if ($is_staff) {
    $sql = "
        SELECT classroom_id, classroom_number_code
        FROM classroom
        WHERE
            advisor_staff_id IS NOT NULL
            AND JSON_VALID(advisor_staff_id)
            AND JSON_CONTAINS(advisor_staff_id, JSON_ARRAY(?))
        ORDER BY classroom_number_code
    ";

    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $user_id);
    $stmt->execute();

    $result = $stmt->get_result();

    while ($row = $result->fetch_assoc()) {
        $advised_classrooms[] = $row;
    }

    $stmt->close();
}

// =====================================================
// สรุปคลังอุปกรณ์ (Dashboard)
// =====================================================

$stats = [
    "total"     => 0,
    "available" => 0,
    "borrowed"  => 0,
    "damaged"   => 0,
];

$result = $conn->query("SELECT status, COUNT(*) AS total FROM equipment_item GROUP BY status");

if ($result) {
    while ($row = $result->fetch_assoc()) {
        $stats["total"] += (int)$row['total'];

        if (isset($stats[$row['status']])) {
            $stats[$row['status']] = (int)$row['total'];
        }
    }
}

$pending_return_count = (int)($conn->query("
    SELECT COUNT(*) AS total FROM borrow_requests
    WHERE return_requested_at IS NOT NULL AND return_item_at IS NULL
")->fetch_assoc()['total'] ?? 0);

// สรุปแยกตามชื่ออุปกรณ์ (กรณีมีอุปกรณ์รุ่นเดียวกันหลายชิ้น)
$item_breakdown = [];

$result = $conn->query("
    SELECT
        item_name, item_type,
        COUNT(*) AS total,
        SUM(status = 'available') AS available,
        SUM(status = 'borrowed') AS borrowed,
        SUM(status = 'damaged') AS damaged
    FROM equipment_item
    GROUP BY item_name, item_type
    ORDER BY item_type, item_name
");

if ($result) {
    while ($row = $result->fetch_assoc()) {
        $item_breakdown[] = $row;
    }
}

// =====================================================
// ดึงรายการอุปกรณ์ที่ว่างให้ยืม
// =====================================================

$available_items = [];

$result = $conn->query("
    SELECT item_id, item_code, item_name, item_detail, item_type
    FROM equipment_item
    WHERE status = 'available'
    ORDER BY item_type, item_name
");

if ($result) {
    while ($row = $result->fetch_assoc()) {
        $available_items[] = $row;
    }
}

// =====================================================
// ดึงรายการแจ้งยืมล่าสุดของตัวเอง
// =====================================================

$recent_requests = [];

$sql = "
    SELECT
        br.borrow_id, br.item_id, br.requester_id, br.borrow_type, br.classroom_id,
        br.request_detail, br.requester_at,
        br.return_requested_at, br.return_item_at, br.return_condition,

        ei.item_code, ei.item_name,

        c.classroom_number_code

    FROM borrow_requests br
    LEFT JOIN equipment_item ei ON ei.item_id = br.item_id
    LEFT JOIN classroom c ON c.classroom_id = br.classroom_id
    WHERE br.requester_id = ?
    ORDER BY br.requester_at DESC
    LIMIT 5
";

$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $user_id);
$stmt->execute();

$result = $stmt->get_result();

while ($row = $result->fetch_assoc()) {
    $recent_requests[] = $row;
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
                    <h1>ระบบยืม-คืนอุปกรณ์</h1>
                    <span>Equipment Borrow System</span>
                </div>
            </div>

            <a href="../index.php" class="back-home">🏠 หน้าหลัก</a>
        </div>
    </header>

    <main class="borrow-container">

        <div class="page-heading">
            <div>
                <h2>ยืมอุปกรณ์</h2>
                <p>เลือกอุปกรณ์ที่ต้องการยืม และกรอกรายละเอียด</p>
            </div>

            <div style="display:flex; gap:10px;">
                <?php if ($is_officer): ?>
                    <a href="officer.php" class="history-btn">🗂️ ตรวจสอบการคืน<?= $pending_return_count > 0 ? " (" . $pending_return_count . ")" : "" ?></a>
                <?php endif; ?>
                <a href="history.php" class="history-btn">📋 ประวัติการยืม-คืน</a>
            </div>
        </div>

        <!-- ================================================= -->
        <!-- DASHBOARD -->
        <!-- ================================================= -->
        <div class="stat-grid">
            <div class="stat-card">
                <div class="stat-value"><?= e($stats['total']) ?></div>
                <div class="stat-label">อุปกรณ์ทั้งหมด</div>
            </div>
            <div class="stat-card stat-available">
                <div class="stat-value"><?= e($stats['available']) ?></div>
                <div class="stat-label">เหลือให้ยืม</div>
            </div>
            <div class="stat-card stat-borrowed">
                <div class="stat-value"><?= e($stats['borrowed']) ?></div>
                <div class="stat-label">กำลังถูกยืม</div>
            </div>
            <div class="stat-card stat-damaged">
                <div class="stat-value"><?= e($stats['damaged']) ?></div>
                <div class="stat-label">ชำรุด/ซ่อมบำรุง</div>
            </div>
        </div>

        <div class="recent-card" style="margin-bottom:25px;">
            <div class="recent-header">
                <div>
                    <h3>📊 สรุปตามรายการอุปกรณ์</h3>
                    <p>จำนวนคงเหลือแยกตามชื่ออุปกรณ์</p>
                </div>
            </div>

            <div class="table-wrapper">
                <table>
                    <thead>
                        <tr>
                            <th>อุปกรณ์</th>
                            <th>ประเภท</th>
                            <th>ทั้งหมด</th>
                            <th>เหลือให้ยืม</th>
                            <th>กำลังถูกยืม</th>
                            <th>ชำรุด</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!empty($item_breakdown)): ?>
                            <?php foreach ($item_breakdown as $row): ?>
                                <tr>
                                    <td><?= e($row['item_name']) ?></td>
                                    <td><?= e($row['item_type'] ?? '-') ?></td>
                                    <td><?= e($row['total']) ?></td>
                                    <td><?= e($row['available']) ?></td>
                                    <td><?= e($row['borrowed']) ?></td>
                                    <td><?= e($row['damaged']) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="6" class="empty-data">ยังไม่มีอุปกรณ์ในระบบ</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- ================================================= -->
        <!-- FORM -->
        <!-- ================================================= -->
        <div class="borrow-card">

            <div class="card-title">
                <div class="title-icon">📦</div>
                <div>
                    <h3>แจ้งยืมอุปกรณ์</h3>
                    <p>เลือกอุปกรณ์และกรอกรายละเอียดให้ครบถ้วน — ยืมได้ทันทีไม่ต้องรออนุมัติ</p>
                </div>
            </div>

            <?php if (empty($available_items)): ?>

                <div class="notice-box">
                    <div class="notice-icon">ℹ️</div>
                    <div>
                        <strong>ไม่มีอุปกรณ์ว่างให้ยืมในขณะนี้</strong>
                        <p>กรุณาตรวจสอบใหม่ภายหลัง</p>
                    </div>
                </div>

            <?php else: ?>

                <form action="store.php" method="POST">

                    <div class="section-title"><span>01</span> เลือกอุปกรณ์</div>

                    <div class="equipment-grid">
                        <?php foreach ($available_items as $item): ?>
                            <label class="equipment-card">
                                <input type="radio" name="item_id" value="<?= e($item['item_id']) ?>" required>
                                <div class="item-code"><?= e($item['item_code']) ?></div>
                                <div class="item-name"><?= e($item['item_name']) ?></div>
                                <?php if (!empty($item['item_detail'])): ?>
                                    <div class="item-detail"><?= e($item['item_detail']) ?></div>
                                <?php endif; ?>
                            </label>
                        <?php endforeach; ?>
                    </div>

                    <div class="section-title"><span>02</span> รายละเอียดการยืม</div>

                    <div class="form-grid">
                        <div class="form-group">
                            <label>ผู้ยืม <span class="required">*</span></label>
                            <input type="text" value="<?= e($requester_name) ?>" readonly>
                        </div>

                        <div class="form-group">
                            <label>ลักษณะการใช้งาน <span class="required">*</span></label>
                            <select name="borrow_type" id="borrow_type" required onchange="toggleClassroom()">
                                <option value="">-- เลือกลักษณะการใช้งาน --</option>
                                <option value="classroom">ใช้ในห้องเรียน</option>
                                <option value="outside">ใช้นอกห้องเรียน</option>
                            </select>
                        </div>
                    </div>

                    <div class="form-group" id="classroom-group" style="display:none;">
                        <label>ห้องเรียน <span class="required">*</span></label>

                        <?php if ($is_student): ?>

                            <input
                                type="text"
                                value="<?= e($student_classroom['classroom_number_code'] ?? 'ไม่พบห้องเรียน') ?>"
                                readonly
                            >
                            <input
                                type="hidden"
                                name="classroom_id"
                                value="<?= e($student_classroom['classroom_id'] ?? '') ?>"
                            >

                        <?php elseif ($is_staff && !empty($advised_classrooms)): ?>

                            <select name="classroom_id" id="classroom_id">
                                <?php if (count($advised_classrooms) > 1): ?>
                                    <option value="">-- เลือกห้องเรียน --</option>
                                <?php endif; ?>
                                <?php foreach ($advised_classrooms as $classroom): ?>
                                    <option value="<?= e($classroom['classroom_id']) ?>">
                                        <?= e($classroom['classroom_number_code']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>

                        <?php elseif ($is_staff): ?>

                            <input type="text" value="คุณไม่ได้เป็นครูที่ปรึกษาของห้องเรียนใด" readonly>

                        <?php else: ?>

                            <input type="text" value="ไม่พบข้อมูลห้องเรียน" readonly>

                        <?php endif; ?>
                    </div>

                    <div class="form-group">
                        <label>เหตุผล/รายละเอียดการยืม <span class="required">*</span></label>
                        <textarea
                            name="request_detail"
                            rows="4"
                            placeholder="ระบุเหตุผลหรือรายละเอียดการใช้งาน"
                            required
                        ></textarea>
                    </div>

                    <div class="notice-box">
                        <div class="notice-icon">ℹ️</div>
                        <div>
                            <strong>ขั้นตอนหลังจากส่งคำขอ</strong>
                            <p>รับอุปกรณ์ได้ทันที เมื่อใช้เสร็จให้กด "แจ้งคืน" พร้อมถ่ายรูปแนบ เจ้าหน้าที่จะตรวจสอบและยืนยันการคืนอีกครั้ง</p>
                        </div>
                    </div>

                    <div class="form-actions">
                        <a href="../index.php" class="cancel-btn">ยกเลิก</a>
                        <button type="submit" class="submit-btn">📦 ยืมอุปกรณ์</button>
                    </div>

                </form>

            <?php endif; ?>
        </div>

        <!-- ================================================= -->
        <!-- รายการล่าสุดของตัวเอง -->
        <!-- ================================================= -->
        <div class="recent-card">

            <div class="recent-header">
                <div>
                    <h3>📋 รายการยืมของคุณ</h3>
                    <p>5 รายการล่าสุด</p>
                </div>

                <a href="history.php">ดูทั้งหมด →</a>
            </div>

            <div class="table-wrapper">
                <table>
                    <thead>
                        <tr>
                            <th>เลขที่</th>
                            <th>วันที่ยืม</th>
                            <th>อุปกรณ์</th>
                            <th>ลักษณะการใช้งาน</th>
                            <th>สถานะ</th>
                            <th>การดำเนินการ</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!empty($recent_requests)): ?>
                            <?php foreach ($recent_requests as $request): ?>
                                <?php
                                $status = getBorrowStatus($request);
                                $can_notify_return = empty($request['return_requested_at']) && empty($request['return_item_at']);
                                ?>
                                <tr>
                                    <td><strong>#<?= str_pad((int)$request['borrow_id'], 4, "0", STR_PAD_LEFT) ?></strong></td>
                                    <td>
                                        <?= !empty($request['requester_at'])
                                            ? date("d/m/Y H:i", strtotime($request['requester_at']))
                                            : "-" ?>
                                    </td>
                                    <td><?= e($request['item_name'] ?? "-") ?></td>
                                    <td>
                                        <?= e(getBorrowTypeName($request['borrow_type'])) ?>
                                        <?php if (!empty($request['classroom_number_code'])): ?>
                                            (<?= e($request['classroom_number_code']) ?>)
                                        <?php endif; ?>
                                    </td>
                                    <td><span class="status <?= e($status['class']) ?>"><?= e($status['text']) ?></span></td>
                                    <td>
                                        <?php if ($can_notify_return): ?>
                                            <a href="return_notify.php?id=<?= e($request['borrow_id']) ?>" class="approve-btn" style="text-decoration:none;">📷 แจ้งคืน</a>
                                        <?php else: ?>
                                            <a href="detail.php?id=<?= e($request['borrow_id']) ?>">ดูรายละเอียด</a>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="6" class="empty-data">ยังไม่มีรายการแจ้งยืม</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

    </main>
</div>

<script>
document.querySelectorAll('.equipment-card input[type="radio"]').forEach(function (radio) {
    radio.addEventListener('change', function () {
        document.querySelectorAll('.equipment-card').forEach(function (card) {
            card.classList.remove('selected');
        });
        radio.closest('.equipment-card').classList.add('selected');
    });
});

function toggleClassroom() {
    var borrowType = document.getElementById('borrow_type').value;
    var group = document.getElementById('classroom-group');
    var select = document.getElementById('classroom_id');

    if (borrowType === 'classroom') {
        group.style.display = 'block';
        if (select) { select.required = true; }
    } else {
        group.style.display = 'none';
        if (select) { select.required = false; }
    }
}
</script>

</body>
</html>
