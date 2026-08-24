<?php

require_once "../config/db.php";


// ==============================
// รับข้อมูลจาก Form
// ==============================

$username = $_POST["username"];
$password = $_POST["password"];
$role = $_POST["role"];

$citizen_id = $_POST["citizen_id"];
$title_name = $_POST["title_name"];

$first_name_th = $_POST["first_name_th"];
$last_name_th = $_POST["last_name_th"];

$birthday = $_POST["birthday"];
$sex = $_POST["sex"];

$email = $_POST["email"];
$phone = $_POST["phone"];

$classroom_id = $_POST["classroom_id"];

$staff_type_code = $_POST["staff_type_code"];
$department_code = $_POST["department_code"];


// ==============================
// เข้ารหัส Password
// ==============================

$password_hash = password_hash(
    $password,
    PASSWORD_DEFAULT
);


// ==============================
// เพิ่มข้อมูล user_accounts
// ==============================

$sql = "
    INSERT INTO user_accounts
    (
        username,
        password_hash,
        role,
        is_active
    )
    VALUES
    (?, ?, ?, 1)
";

$stmt = $conn->prepare($sql);

$stmt->bind_param(
    "sss",
    $username,
    $password_hash,
    $role
);

$stmt->execute();


// เอา user_id ที่เพิ่งสร้าง
$user_id = $conn->insert_id;


// ==============================
// เพิ่มข้อมูลนักเรียน
// ==============================

if ($role == "student") {

    $sql = "
        INSERT INTO user_students
        (
            user_id,
            citezen_id,
            title_name,
            first_name_th,
            last_name_th,
            birthday,
            sex,
            email,
            phone,
            classroom_id
        )
        VALUES
        (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ";

    $stmt = $conn->prepare($sql);

    $stmt->bind_param(
        "isssssssss",
        $user_id,
        $citizen_id,
        $title_name,
        $first_name_th,
        $last_name_th,
        $birthday,
        $sex,
        $email,
        $phone,
        $classroom_id
    );

    $stmt->execute();
}


// ==============================
// เพิ่มข้อมูลบุคลากร
// ==============================

if ($role == "staff") {

    $sql = "
        INSERT INTO user_staffs
        (
            user_id,
            staff_type_code,
            citezen_id,
            title_name,
            first_name_th,
            last_name_th,
            birthday,
            sex,
            email,
            phone,
            department_code
        )
        VALUES
        (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ";

    $stmt = $conn->prepare($sql);

    $stmt->bind_param(
        "issssssssss",
        $user_id,
        $staff_type_code,
        $citizen_id,
        $title_name,
        $first_name_th,
        $last_name_th,
        $birthday,
        $sex,
        $email,
        $phone,
        $department_code
    );

    $stmt->execute();
}


// ==============================
// กลับไปหน้าจัดการ User
// ==============================

header("Location: main.php");

exit;

?>