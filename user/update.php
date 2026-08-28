<?php

session_start();

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

require_once "../config/db.php";

// ตารางที่ใช้ในไฟล์นี้: user_accounts, user_students, user_staffs
// โครงสร้างตารางแบบเต็มดูได้ที่ database/schema.sql


// ========================================
// รับข้อมูล
// ========================================

$user_id = intval(
    $_POST["user_id"] ?? 0
);

$username = trim(
    $_POST["username"] ?? ""
);

$password = $_POST["password"] ?? "";

$citizen_id = trim(
    $_POST["citizen_id"] ?? ""
);

$title_name = trim(
    $_POST["title_name"] ?? ""
);

$first_name_th = trim(
    $_POST["first_name_th"] ?? ""
);

$first_name_en = trim(
    $_POST["first_name_en"] ?? ""
);

$last_name_th = trim(
    $_POST["last_name_th"] ?? ""
);

$last_name_en = trim(
    $_POST["last_name_en"] ?? ""
);

$birthday = $_POST["birthday"] ?? null;

$sex = $_POST["sex"] ?? "";

$email = trim(
    $_POST["email"] ?? ""
);

$phone = trim(
    $_POST["phone"] ?? ""
);

$classroom_id = trim(
    $_POST["classroom_id"] ?? ""
);

$staff_type_code = trim(
    $_POST["staff_type_code"] ?? ""
);

$department_code = trim(
    $_POST["department_code"] ?? ""
);

$is_active =
    isset($_POST["is_active"])
    ? 1
    : 0;


// ========================================
// ตรวจสอบ
// ========================================

if ($user_id <= 0) {

    die("User ID ไม่ถูกต้อง");

}


if ($username == "") {

    die("กรุณากรอก Username");

}


// ========================================
// ตรวจสอบ User
// ========================================

$sql = "
    SELECT
        user_id,
        role
    FROM user_accounts
    WHERE user_id = ?
    LIMIT 1
";


$stmt = $conn->prepare($sql);


$stmt->bind_param(
    "i",
    $user_id
);


$stmt->execute();


$result =
    $stmt->get_result();


if ($result->num_rows == 0) {

    die("ไม่พบข้อมูลผู้ใช้งาน");

}


$user =
    $result->fetch_assoc();


$role =
    $user["role"];


// ========================================
// ตรวจสอบ Username ซ้ำ
// ========================================

$sql = "
    SELECT
        user_id
    FROM user_accounts
    WHERE username = ?
    AND user_id != ?
    LIMIT 1
";


$stmt = $conn->prepare($sql);


$stmt->bind_param(
    "si",
    $username,
    $user_id
);


$stmt->execute();


$result =
    $stmt->get_result();


if ($result->num_rows > 0) {

    die("Username นี้มีผู้ใช้งานแล้ว");

}


// ========================================
// Transaction
// ========================================

$conn->begin_transaction();


try {


    // ====================================
    // Update User Account
    // ====================================

    if ($password != "") {


        // ถ้ามี Password ใหม่

        $password_hash =
            password_hash(
                $password,
                PASSWORD_DEFAULT
            );


        $sql = "
            UPDATE user_accounts
            SET
                username = ?,
                password_hash = ?,
                is_active = ?
            WHERE user_id = ?
        ";


        $stmt =
            $conn->prepare($sql);


        $stmt->bind_param(
            "ssii",
            $username,
            $password_hash,
            $is_active,
            $user_id
        );


        $stmt->execute();

    }
    else {


        // ถ้าไม่เปลี่ยน Password

        $sql = "
            UPDATE user_accounts
            SET
                username = ?,
                is_active = ?
            WHERE user_id = ?
        ";


        $stmt =
            $conn->prepare($sql);


        $stmt->bind_param(
            "sii",
            $username,
            $is_active,
            $user_id
        );


        $stmt->execute();

    }


    // ====================================
    // STUDENT
    // ====================================

    if ($role == "student") {


        $sql = "
            UPDATE user_students
            SET
                citezen_id = ?,
                title_name = ?,
                first_name_th = ?,
                first_name_en = ?,
                last_name_th = ?,
                last_name_en = ?,
                birthday = ?,
                sex = ?,
                email = ?,
                phone = ?,
                classroom_id = ?
            WHERE user_id = ?
        ";


        $stmt =
            $conn->prepare($sql);


        $stmt->bind_param(
            "sssssssssssi",
            $citizen_id,
            $title_name,
            $first_name_th,
            $first_name_en,
            $last_name_th,
            $last_name_en,
            $birthday,
            $sex,
            $email,
            $phone,
            $classroom_id,
            $user_id
        );


        $stmt->execute();

    }


    // ====================================
    // STAFF
    // ====================================

    elseif ($role == "staff") {


        $sql = "
            UPDATE user_staffs
            SET
                staff_type_code = ?,
                citezen_id = ?,
                title_name = ?,
                first_name_th = ?,
                first_name_en = ?,
                last_name_th = ?,
                last_name_en = ?,
                birthday = ?,
                sex = ?,
                email = ?,
                phone = ?,
                department_code = ?
            WHERE user_id = ?
        ";


        $stmt =
            $conn->prepare($sql);


        $stmt->bind_param(
            "ssssssssssssi",
            $staff_type_code,
            $citizen_id,
            $title_name,
            $first_name_th,
            $first_name_en,
            $last_name_th,
            $last_name_en,
            $birthday,
            $sex,
            $email,
            $phone,
            $department_code,
            $user_id
        );


        $stmt->execute();

    }


    // ====================================
    // Commit
    // ====================================

    $conn->commit();


    header(
        "Location: main.php"
    );

    exit;


}
catch (Exception $e) {


    $conn->rollback();


    die(
        "เกิดข้อผิดพลาด: "
        . htmlspecialchars(
            $e->getMessage()
        )
    );

}

?>