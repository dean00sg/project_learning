<?php

session_start();

require_once "../config/db.php";


// =====================================================
// ตรวจสอบ Login
// =====================================================

if (!isset($_SESSION['user_id'])) {

    header("Location: ../login.php");
    exit;
}

$user_id = (int)$_SESSION['user_id'];


// =====================================================
// รับ request_id
// =====================================================

$request_id =
    (int)($_POST['request_id'] ?? 0);


if ($request_id <= 0) {

    echo "<script>
        alert('ไม่พบรายการแจ้งซ่อม');
        window.location.href = 'main.php';
    </script>";

    exit;
}


// =====================================================
// ตรวจสอบว่า User เป็นบุคลากร/อาจารย์หรือไม่
//
// ใช้ user_id เป็นตัวหลัก
// =====================================================

$sql = "
    SELECT
        ua.user_id,
        ua.role,
        ua.is_active,

        ust.staff_id

    FROM user_accounts ua

    LEFT JOIN user_staffs ust
        ON ust.user_id = ua.user_id

    WHERE ua.user_id = ?

    LIMIT 1
";

$stmt = $conn->prepare($sql);

if (!$stmt) {

    die(
        "เกิดข้อผิดพลาด SQL: "
        . $conn->error
    );
}

$stmt->bind_param(
    "i",
    $user_id
);

$stmt->execute();

$result = $stmt->get_result();

$user = $result->fetch_assoc();

$stmt->close();


if (!$user) {

    echo "<script>
        alert('ไม่พบข้อมูลผู้ใช้งาน');
        window.location.href = 'main.php';
    </script>";

    exit;
}


// =====================================================
// ต้องมีข้อมูล user_staffs
// =====================================================

if (empty($user['staff_id'])) {

    echo "<script>
        alert('บัญชีนี้ไม่มีสิทธิ์เป็นอาจารย์หรือบุคลากร');
        window.location.href = 'main.php';
    </script>";

    exit;
}


// =====================================================
// ตรวจสอบรายการ
//
// สำคัญ:
//
// 1. request_id ต้องมีจริง
// 2. approved_by ต้องยังเป็น NULL
//
// ไม่ตรวจ classroom
// ไม่ตรวจ advisor_staff_id
// ไม่ตรวจ staff_id
// =====================================================

$sql = "
    SELECT
        request_id,
        requester_id,
        classroom_id,
        approved_by

    FROM repair_requests

    WHERE
        request_id = ?

        AND approved_by IS NULL

    LIMIT 1
";

$stmt = $conn->prepare($sql);

if (!$stmt) {

    die(
        "เกิดข้อผิดพลาด SQL: "
        . $conn->error
    );
}

$stmt->bind_param(
    "i",
    $request_id
);

$stmt->execute();

$result = $stmt->get_result();

$request = $result->fetch_assoc();

$stmt->close();


if (!$request) {

    echo "<script>
        alert('รายการนี้ไม่มีอยู่ หรือได้รับการอนุมัติแล้ว');
        window.location.href = 'main.php';
    </script>";

    exit;
}


// =====================================================
// อนุมัติ
//
// approved_by = user_id ของอาจารย์
// =====================================================

$sql = "
    UPDATE repair_requests

    SET
        approved_by = ?,
        approved_at = NOW()

    WHERE
        request_id = ?

        AND approved_by IS NULL
";

$stmt = $conn->prepare($sql);

if (!$stmt) {

    die(
        "เกิดข้อผิดพลาด SQL: "
        . $conn->error
    );
}

$stmt->bind_param(
    "ii",
    $user_id,
    $request_id
);


if ($stmt->execute()) {

    $affected_rows =
        $stmt->affected_rows;

    $stmt->close();


    if ($affected_rows === 1) {

        echo "<script>

            alert(
                'อนุมัติรายการแจ้งซ่อมเรียบร้อยแล้ว'
            );

            window.location.href =
                'main.php';

        </script>";

        exit;

    }

}


// =====================================================
// Error
// =====================================================

$error =
    $stmt->error;

$stmt->close();

echo "<script>

    alert(
        'เกิดข้อผิดพลาด: "
        . htmlspecialchars(
            $error,
            ENT_QUOTES,
            'UTF-8'
        )
        . "'
    );

    window.location.href =
        'main.php';

</script>";

exit;