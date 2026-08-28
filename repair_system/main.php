<?php

session_start();

$page_title = "แจ้งซ่อมอุปกรณ์";
$css_path   = "../css/repair.css";

require_once "../config/db.php";

// ตารางที่ใช้ในไฟล์นี้: user_accounts, user_students, user_staffs,
//                     classroom, repair_requests
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

function getRequestTypeName($type)
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

function getRepairStatus($approved_by)
{
    if (!empty($approved_by)) {
        return ["text" => "อนุมัติแล้ว", "class" => "approved"];
    }

    return ["text" => "รอครูอนุมัติ", "class" => "waiting"];
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
// ดึงข้อมูลผู้ใช้งาน
// =====================================================

$sql = "
    SELECT
        ua.user_id, ua.username, ua.role, ua.is_active,

        us.student_id, us.student_code, us.title_name AS student_title,
        us.first_name_th AS student_first_name,
        us.last_name_th AS student_last_name,
        us.classroom_id AS student_classroom_id,

        ust.staff_id, ust.title_name AS staff_title,
        ust.first_name_th AS staff_first_name,
        ust.last_name_th AS staff_last_name

    FROM user_accounts ua
    LEFT JOIN user_students us ON us.user_id = ua.user_id
    LEFT JOIN user_staffs ust ON ust.user_id = ua.user_id
    WHERE ua.user_id = ?
    LIMIT 1
";

$stmt = $conn->prepare($sql);

if (!$stmt) {
    die("เกิดข้อผิดพลาด SQL: " . $conn->error);
}

$stmt->bind_param("i", $user_id);
$stmt->execute();

$user = $stmt->get_result()->fetch_assoc();

$stmt->close();

if (!$user) {
    die("ไม่พบข้อมูลผู้ใช้งาน");
}

// =====================================================
// ตรวจสอบ Account
// =====================================================

if ((int)$user['is_active'] !== 1) {
    die("บัญชีผู้ใช้งานนี้ถูกปิดการใช้งาน");
}

// =====================================================
// ตรวจสอบประเภทผู้ใช้
// =====================================================

$is_student = !empty($user['student_id']);
$is_staff   = !empty($user['staff_id']);

// =====================================================
// ชื่อผู้ใช้งาน
// =====================================================

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
// ห้องของนักเรียน
// =====================================================

$student_classroom = null;

if ($is_student && !empty($user['student_classroom_id'])) {
    $classroom_id = (int)$user['student_classroom_id'];

    $sql = "
        SELECT classroom_id, classroom_number_code, classroom_level, building
        FROM classroom
        WHERE classroom_id = ?
        LIMIT 1
    ";

    $stmt = $conn->prepare($sql);

    if ($stmt) {
        $stmt->bind_param("i", $classroom_id);
        $stmt->execute();

        $student_classroom = $stmt->get_result()->fetch_assoc();

        $stmt->close();
    }
}

// =====================================================
// ห้องสำหรับบุคลากร
//
// ตอนนี้ไม่ใช้ advisor_staff_id ให้เลือกห้องทั้งหมด
// (เป็นห้องที่จะแจ้งซ่อมให้ ไม่ใช่สิทธิ์อนุมัติ)
// =====================================================

$all_classrooms = [];

if ($is_staff) {
    $sql = "
        SELECT classroom_id, classroom_number_code, classroom_level, building
        FROM classroom
        ORDER BY classroom_level, classroom_number_code
    ";

    $result = $conn->query($sql);

    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $all_classrooms[] = $row;
        }
    }
}

// =====================================================
// ดึงรายการแจ้งซ่อม
//
// นักเรียน: เฉพาะรายการของตัวเอง
// อาจารย์ : เฉพาะรายการของตัวเอง หรือรายการของห้องที่ตนเป็นครูที่ปรึกษา
//
// สำคัญ: รายการที่ approved_by IS NULL จะสามารถกดอนุมัติได้
// =====================================================

$recent_requests = [];

$select_columns = "
    r.request_id, r.request_type, r.classroom_id, r.requester_id,
    r.request_datetime, r.repair_detail, r.request_image,
    r.approved_by, r.approved_at,

    c.classroom_number_code, c.classroom_level, c.building, c.advisor_staff_id,

    ua.username AS requester_username,

    us.student_id, us.student_code, us.title_name AS student_title,
    us.first_name_th AS student_first_name, us.last_name_th AS student_last_name,

    ust.staff_id AS requester_staff_id, ust.title_name AS requester_staff_title,
    ust.first_name_th AS requester_staff_first_name,
    ust.last_name_th AS requester_staff_last_name
";

$joins = "
    FROM repair_requests r
    LEFT JOIN classroom c ON c.classroom_id = r.classroom_id
    LEFT JOIN user_accounts ua ON ua.user_id = r.requester_id
    LEFT JOIN user_students us ON us.user_id = r.requester_id
    LEFT JOIN user_staffs ust ON ust.user_id = r.requester_id
";

if ($is_staff) {
    // อาจารย์: รายการของตัวเอง หรือห้องที่ตนเป็นครูที่ปรึกษา
    $sql = "
        SELECT $select_columns
        $joins
        WHERE
            r.requester_id = ?
            OR (
                c.advisor_staff_id IS NOT NULL
                AND JSON_VALID(c.advisor_staff_id)
                AND JSON_CONTAINS(c.advisor_staff_id, JSON_ARRAY(?))
            )
        ORDER BY
            CASE WHEN r.approved_by IS NULL THEN 0 ELSE 1 END,
            r.request_datetime DESC
    ";
} else {
    // นักเรียน: เฉพาะรายการของตัวเอง
    $sql = "
        SELECT $select_columns
        $joins
        WHERE r.requester_id = ?
        ORDER BY r.request_datetime DESC
        LIMIT 5
    ";
}

$stmt = $conn->prepare($sql);

if (!$stmt) {
    die("เกิดข้อผิดพลาด SQL รายการแจ้งซ่อม: " . $conn->error);
}

if ($is_staff) {
    $stmt->bind_param("ii", $user_id, $user_id);
} else {
    $stmt->bind_param("i", $user_id);
}

$stmt->execute();

$result = $stmt->get_result();

while ($row = $result->fetch_assoc()) {
    $recent_requests[] = $row;
}

$stmt->close();

// =====================================================
// นับรายการรออนุมัติ (เฉพาะรายการที่ตนอนุมัติได้จริง)
// =====================================================

$waiting_count = 0;

if ($is_staff) {
    foreach ($recent_requests as $request) {
        $row_is_advisor = isAdvisorOf($request['advisor_staff_id'] ?? null, $user_id);
        $row_is_owner   = (int)$request['requester_id'] === $user_id;

        if ($row_is_advisor && !$row_is_owner && empty($request['approved_by'])) {
            $waiting_count++;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="th">
<?php include __DIR__ . "/../includes/head.php"; ?>
<body>
<div class="repair-page">

    <!-- ================================================= -->
    <!-- HEADER -->
    <!-- ================================================= -->
    <header class="repair-header">
        <div class="header-inner">
            <div class="header-brand">
                <div class="brand-icon">🔧</div>
                <div>
                    <h1>ระบบแจ้งซ่อมอุปกรณ์</h1>
                    <span>Equipment Repair Request System</span>
                </div>
            </div>

            <a href="../index.php" class="back-home">🏠 หน้าหลัก</a>
        </div>
    </header>

    <!-- ================================================= -->
    <!-- MAIN -->
    <!-- ================================================= -->
    <main class="repair-container">

        <div class="page-heading">
            <div>
                <h2>แจ้งซ่อมอุปกรณ์</h2>
                <p>กรุณากรอกข้อมูลอุปกรณ์หรือสถานที่ที่ต้องการแจ้งซ่อม</p>
            </div>

            <a href="history.php" class="history-btn">📋 ประวัติการแจ้งซ่อม</a>
        </div>

        <!-- ================================================= -->
        <!-- FORM -->
        <!-- ================================================= -->
        <div class="repair-card">

            <div class="card-title">
                <div class="title-icon">🔧</div>
                <div>
                    <h3>รายละเอียดการแจ้งซ่อม</h3>
                    <p>กรอกข้อมูลให้ครบถ้วนเพื่อส่งคำขอแจ้งซ่อม</p>
                </div>
            </div>

            <form action="store.php" method="POST" enctype="multipart/form-data">

                <!-- ผู้แจ้ง -->
                <div class="section-title"><span>01</span> ข้อมูลผู้แจ้ง</div>

                <div class="form-grid">
                    <div class="form-group">
                        <label>ชื่อผู้แจ้ง <span class="required">*</span></label>
                        <input type="text" value="<?= e($requester_name) ?>" readonly>
                        <input type="hidden" name="requester_id" value="<?= e($user_id) ?>">
                    </div>

                    <!-- ห้อง -->
                    <div class="form-group">
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

                        <?php elseif ($is_staff): ?>

                            <select name="classroom_id" required>
                                <option value="">-- เลือกห้องเรียน --</option>
                                <?php foreach ($all_classrooms as $classroom): ?>
                                    <option value="<?= e($classroom['classroom_id']) ?>">
                                        <?= e($classroom['classroom_number_code']) ?>
                                        <?php if (!empty($classroom['building'])): ?>
                                            - <?= e($classroom['building']) ?>
                                        <?php endif; ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>

                        <?php else: ?>

                            <input type="text" value="ไม่พบข้อมูลห้องเรียน" readonly>

                        <?php endif; ?>
                    </div>
                </div>

                <!-- รายละเอียด -->
                <div class="section-title"><span>02</span> รายละเอียดการแจ้งซ่อม</div>

                <div class="form-group">
                    <label>ประเภทการแจ้งซ่อม <span class="required">*</span></label>
                    <select name="request_type" required>
                        <option value="">-- เลือกประเภทการแจ้งซ่อม --</option>
                        <option value="computer">คอมพิวเตอร์ / Notebook</option>
                        <option value="projector">โปรเจกเตอร์</option>
                        <option value="printer">เครื่องพิมพ์</option>
                        <option value="network">ระบบเครือข่าย / Internet</option>
                        <option value="electric">ระบบไฟฟ้า</option>
                        <option value="air_conditioner">เครื่องปรับอากาศ</option>
                        <option value="other">อื่น ๆ</option>
                    </select>
                </div>

                <div class="form-group">
                    <label>รายละเอียดปัญหา <span class="required">*</span></label>
                    <textarea
                        name="repair_detail"
                        rows="6"
                        placeholder="กรุณาระบุอาการหรือปัญหาที่พบ"
                        required
                    ></textarea>
                </div>

                <!-- รูป -->
                <div class="section-title"><span>03</span> รูปภาพประกอบ</div>

                <div class="upload-box">
                    <div class="upload-icon">📷</div>
                    <div class="upload-text">
                        <strong>แนบรูปภาพปัญหา</strong>
                        <p>สามารถแนบรูปภาพอุปกรณ์หรือปัญหาที่พบ</p>
                        <span>รองรับ JPG, JPEG, PNG ขนาดไม่เกิน 5 MB</span>
                    </div>
                    <label class="upload-btn">
                        เลือกรูปภาพ
                        <input type="file" name="request_image" accept="image/png,image/jpeg" hidden>
                    </label>
                </div>

                <!-- NOTICE -->
                <div class="notice-box">
                    <div class="notice-icon">ℹ️</div>
                    <div>
                        <strong>ขั้นตอนหลังจากส่งแจ้งซ่อม</strong>
                        <p>รายการแจ้งซ่อมจะอยู่ในสถานะ "รอครูอนุมัติ" จนกว่าจะมีอาจารย์อนุมัติ</p>
                    </div>
                </div>

                <!-- BUTTON -->
                <div class="form-actions">
                    <a href="../index.php" class="cancel-btn">ยกเลิก</a>
                    <button type="submit" class="submit-btn">🔧 ส่งแจ้งซ่อม</button>
                </div>

            </form>
        </div>

        <!-- ================================================= -->
        <!-- รายการล่าสุด -->
        <!-- ================================================= -->
        <div class="recent-card">

            <div class="recent-header">
                <div>
                    <h3>📋 รายการแจ้งซ่อม</h3>
                    <p><?= $is_staff ? "รายการแจ้งซ่อมทั้งหมด" : "รายการแจ้งซ่อมของคุณ" ?></p>
                </div>

                <a href="history.php">ดูทั้งหมด →</a>
            </div>

            <?php if ($is_staff && $waiting_count > 0): ?>
                <div style="margin:15px 0; padding:15px 18px; border-radius:10px; background:#fff7ed; border:1px solid #fed7aa; color:#9a3412;">
                    <strong>🔔 มีรายการรออนุมัติ <?= e($waiting_count) ?> รายการ</strong>
                    <div style="margin-top:5px;">
                        รายการที่มีสถานะ <strong>รอครูอนุมัติ</strong> สามารถกดปุ่มอนุมัติได้จากตารางด้านล่าง
                    </div>
                </div>
            <?php endif; ?>

            <div class="table-wrapper">
                <table>
                    <thead>
                        <tr>
                            <th>เลขที่</th>
                            <th>วันที่แจ้ง</th>
                            <th>ผู้แจ้ง</th>
                            <th>ห้อง</th>
                            <th>ประเภท</th>
                            <th>รายละเอียด</th>
                            <th>สถานะ</th>
                            <?php if ($is_staff): ?>
                                <th>การดำเนินการ</th>
                            <?php endif; ?>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!empty($recent_requests)): ?>
                            <?php foreach ($recent_requests as $request): ?>
                                <?php
                                $status = getRepairStatus($request['approved_by']);

                                // ชื่อผู้แจ้ง
                                if (!empty($request['student_first_name'])) {
                                    $display_requester = trim(
                                        ($request['student_title'] ?? "") . " " .
                                        ($request['student_first_name'] ?? "") . " " .
                                        ($request['student_last_name'] ?? "")
                                    );
                                } elseif (!empty($request['requester_staff_first_name'])) {
                                    $display_requester = trim(
                                        ($request['requester_staff_title'] ?? "") . " " .
                                        ($request['requester_staff_first_name'] ?? "") . " " .
                                        ($request['requester_staff_last_name'] ?? "")
                                    );
                                } else {
                                    $display_requester = $request['requester_username'] ?? "-";
                                }

                                // สิทธิ์อนุมัติ: ต้องเป็นครูที่ปรึกษาของห้องนั้น ไม่ใช่เจ้าของรายการเอง
                                // และ approved_by ยังเป็น NULL
                                $row_is_advisor = isAdvisorOf($request['advisor_staff_id'] ?? null, $user_id);
                                $row_is_owner   = (int)$request['requester_id'] === $user_id;

                                $can_approve = $row_is_advisor && !$row_is_owner && empty($request['approved_by']);
                                ?>
                                <tr>
                                    <td><strong>#<?= str_pad((int)$request['request_id'], 4, "0", STR_PAD_LEFT) ?></strong></td>
                                    <td>
                                        <?= !empty($request['request_datetime'])
                                            ? date("d/m/Y H:i", strtotime($request['request_datetime']))
                                            : "-" ?>
                                    </td>
                                    <td><?= e($display_requester) ?></td>
                                    <td><?= e($request['classroom_number_code'] ?? "-") ?></td>
                                    <td><?= e(getRequestTypeName($request['request_type'])) ?></td>
                                    <td><?= e(mb_strimwidth($request['repair_detail'] ?? "", 0, 50, "...")) ?></td>
                                    <td><span class="status <?= e($status['class']) ?>"><?= e($status['text']) ?></span></td>
                                    <?php if ($is_staff): ?>
                                        <td>
                                            <?php if ($can_approve): ?>
                                                <form
                                                    action="approve.php"
                                                    method="POST"
                                                    style="display:inline;"
                                                    onsubmit="return confirm('ยืนยันการอนุมัติรายการแจ้งซ่อมนี้หรือไม่?');"
                                                >
                                                    <input type="hidden" name="request_id" value="<?= e($request['request_id']) ?>">
                                                    <button type="submit" class="approve-btn">✓ อนุมัติ</button>
                                                </form>
                                            <?php elseif (!empty($request['approved_by'])): ?>
                                                <span>อนุมัติแล้ว</span>
                                            <?php else: ?>
                                                <span>-</span>
                                            <?php endif; ?>
                                        </td>
                                    <?php endif; ?>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="<?= $is_staff ? 8 : 7 ?>" class="empty-data">ยังไม่มีรายการแจ้งซ่อม</td>
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
