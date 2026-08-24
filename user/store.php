<?php

require_once "../config/db.php";


// ========================================
// รับข้อมูล
// ========================================

$username = trim($_POST["username"] ?? "");

$password = $_POST["password"] ?? "";

$role = $_POST["role"] ?? "";

$citizen_id = trim($_POST["citizen_id"] ?? "");

$title_name = trim($_POST["title_name"] ?? "");

$first_name_th = trim($_POST["first_name_th"] ?? "");

$first_name_en = trim($_POST["first_name_en"] ?? "");

$last_name_th = trim($_POST["last_name_th"] ?? "");

$last_name_en = trim($_POST["last_name_en"] ?? "");

$birthday = $_POST["birthday"] ?? null;

$sex = $_POST["sex"] ?? "";

$email = trim($_POST["email"] ?? "");

$phone = trim($_POST["phone"] ?? "");

$classroom_id = trim($_POST["classroom_id"] ?? "");

$staff_type_code = trim($_POST["staff_type_code"] ?? "");

$department_code = trim($_POST["department_code"] ?? "");


// ========================================
// ตรวจสอบข้อมูลพื้นฐาน
// ========================================

if (
    $username == "" ||
    $password == "" ||
    $role == ""
) {

    die("กรุณากรอกข้อมูล Login ให้ครบ");

}


if (
    $role != "student" &&
    $role != "staff"
) {

    die("ประเภทผู้ใช้งานไม่ถูกต้อง");

}


// ========================================
// ตรวจสอบ Username ซ้ำ
// ========================================

$sql = "
    SELECT user_id
    FROM user_accounts
    WHERE username = ?
    LIMIT 1
";

$stmt = $conn->prepare($sql);

$stmt->bind_param(
    "s",
    $username
);

$stmt->execute();

$result = $stmt->get_result();


if ($result->num_rows > 0) {

    die("Username นี้มีอยู่แล้ว");

}


// ========================================
// Password Hash
// ========================================

$password_hash = password_hash(
    $password,
    PASSWORD_DEFAULT
);


// ========================================
// เริ่ม Transaction
// ========================================

$conn->begin_transaction();


try {


    // ====================================
    // เพิ่ม user_accounts
    // ====================================

    $sql = "
        INSERT INTO user_accounts
        (
            username,
            password_hash,
            role,
            is_active
        )
        VALUES (?, ?, ?, 1)
    ";


    $stmt = $conn->prepare($sql);


    $stmt->bind_param(
        "sss",
        $username,
        $password_hash,
        $role
    );


    $stmt->execute();


    $user_id = $conn->insert_id;


    // ====================================
    // STUDENT
    // ====================================

    if ($role == "student") {


        // ตรวจสอบบัตรประชาชน
        if ($citizen_id == "") {

            throw new Exception(
                "นักเรียนต้องกรอกเลขบัตรประชาชน"
            );

        }


        if (strlen($citizen_id) != 13) {

            throw new Exception(
                "เลขบัตรประชาชนต้องมี 13 หลัก"
            );

        }


        // =================================
        // ปี พ.ศ.
        // =================================

        $thai_year =
            date("Y") + 543;


        $year_code =
            substr($thai_year, -2);


        // =================================
        // คำนำหน้า
        // =================================

        if ($title_name == "นาย") {

            $title_code = "01";

        }
        elseif ($title_name == "นางสาว") {

            $title_code = "02";

        }
        elseif ($title_name == "นาง") {

            $title_code = "03";

        }
        else {

            throw new Exception(
                "กรุณาเลือกคำนำหน้า"
            );

        }


        // =================================
        // กลุ่มตัวอักษรชื่อ
        // =================================

        $letter_code =
            getThaiLetterCode(
                $first_name_th
            );


        // =================================
        // 3 ตัวท้ายบัตรประชาชน
        // =================================

        $citizen_last3 =
            substr(
                $citizen_id,
                -3
            );


        // =================================
        // สร้าง Student Code
        // =================================

        $student_code =
            $year_code .
            $title_code .
            $letter_code .
            $citizen_last3;


        // ตรวจสอบว่าครบ 9 หลัก
        if (strlen($student_code) != 9) {

            throw new Exception(
                "ไม่สามารถสร้างรหัสนักเรียน 9 หลักได้"
            );

        }


        // =================================
        // ตรวจสอบ student_code ซ้ำ
        // =================================

        $sql = "
            SELECT student_id
            FROM user_students
            WHERE student_code = ?
            LIMIT 1
        ";


        $stmt = $conn->prepare($sql);


        $stmt->bind_param(
            "s",
            $student_code
        );


        $stmt->execute();


        $check =
            $stmt->get_result();


        if ($check->num_rows > 0) {

            throw new Exception(
                "รหัสนักเรียน $student_code มีอยู่แล้ว"
            );

        }


        // =================================
        // เพิ่ม user_students
        // =================================

        $sql = "
            INSERT INTO user_students
            (
                user_id,
                student_code,
                citezen_id,
                title_name,
                first_name_th,
                first_name_en,
                last_name_th,
                last_name_en,
                birthday,
                sex,
                email,
                phone,
                classroom_id
            )
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ";


        $stmt = $conn->prepare($sql);


        $stmt->bind_param(
            "issssssssssss",
            $user_id,
            $student_code,
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
            $classroom_id
        );


        $stmt->execute();

    }


    // ====================================
    // STAFF
    // ====================================

    elseif ($role == "staff") {


        $sql = "
            INSERT INTO user_staffs
            (
                user_id,
                staff_type_code,
                citezen_id,
                title_name,
                first_name_th,
                first_name_en,
                last_name_th,
                last_name_en,
                birthday,
                sex,
                email,
                phone,
                department_code
            )
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ";


        $stmt = $conn->prepare($sql);


        $stmt->bind_param(
            "issssssssssss",
            $user_id,
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
            $department_code
        );


        $stmt->execute();

    }


    // ====================================
    // บันทึกทั้งหมด
    // ====================================

    $conn->commit();


    header(
        "Location: main.php"
    );

    exit;


}
catch (Exception $e) {


    // ยกเลิกข้อมูลทั้งหมด
    $conn->rollback();


    die(
        "เกิดข้อผิดพลาด: "
        . htmlspecialchars(
            $e->getMessage()
        )
    );

}


// ========================================
// Function
// แบ่งกลุ่มตัวอักษรไทย
// ========================================

function getThaiLetterCode($name)
{

    $first =
        mb_substr(
            trim($name),
            0,
            1,
            "UTF-8"
        );


    // 01 = ก - ง

    if (
        in_array(
            $first,
            [
                "ก",
                "ข",
                "ฃ",
                "ค",
                "ฅ",
                "ฆ",
                "ง"
            ]
        )
    ) {

        return "01";

    }


    // 02 = จ - ญ

    if (
        in_array(
            $first,
            [
                "จ",
                "ฉ",
                "ช",
                "ซ",
                "ฌ",
                "ญ"
            ]
        )
    ) {

        return "02";

    }


    // 03 = ฎ - ณ

    if (
        in_array(
            $first,
            [
                "ฎ",
                "ฏ",
                "ฐ",
                "ฑ",
                "ฒ",
                "ณ"
            ]
        )
    ) {

        return "03";

    }


    // 04 = ด - น

    if (
        in_array(
            $first,
            [
                "ด",
                "ต",
                "ถ",
                "ท",
                "ธ",
                "น"
            ]
        )
    ) {

        return "04";

    }


    // 05 = บ - ม

    if (
        in_array(
            $first,
            [
                "บ",
                "ป",
                "ผ",
                "ฝ",
                "พ",
                "ฟ",
                "ภ",
                "ม"
            ]
        )
    ) {

        return "05";

    }


    // 06 = ย - ฮ

    if (
        in_array(
            $first,
            [
                "ย",
                "ร",
                "ล",
                "ว",
                "ศ",
                "ษ",
                "ส",
                "ห",
                "ฬ",
                "อ",
                "ฮ"
            ]
        )
    ) {

        return "06";

    }


    return "00";
}

?>