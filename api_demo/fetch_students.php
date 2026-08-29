<?php

session_start();

require_once "../config/db.php";

// ตารางที่ใช้ในไฟล์นี้: user_accounts, user_students, classroom
// โครงสร้างตารางแบบเต็มดูได้ที่ database/schema.sql

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

// =====================================================
// เรียก API ภายนอก
//
// path /api/students คือ endpoint จำลอง (ดู .htaccess ที่ rewrite
// ไปหา api_demo/mock_students_api.php จริง ๆ เบื้องหลัง)
//
// *** ในข้อสอบจริง: ลบ 3 บรรทัดด้านล่าง ($project_root_path, $base_url, $api_url)
// ทิ้งทั้งหมด แล้วแทนที่ด้วยบรรทัดเดียว ชี้ไปที่ URL ของ API จริงที่โจทย์ให้มา เช่น:
//
//     $api_url = "https://exam-server.com/api/v1/students";
//
// โค้ดส่วน callApi() และ loop บันทึกข้อมูลด้านล่างทั้งหมด ไม่ต้องแก้อะไรเลย
// ใช้ได้ทันที ตราบใดที่ API จริงคืนค่าเป็น JSON array ของนักเรียน
//
// ถ้า field ของ API จริงชื่อไม่ตรงกับที่โค้ดอ่าน (เช่นเขาใช้ id_card แทน
// citizen_id) ต้องไปแก้ในส่วน foreach ($students as $row) { ... } ด้านล่าง
// ให้ map ชื่อ field ให้ตรงด้วย
// =====================================================

$project_root_path = dirname(dirname($_SERVER['SCRIPT_NAME']));

$base_url = (!empty($_SERVER['HTTPS']) ? "https" : "http")
    . "://" . $_SERVER['HTTP_HOST']
    . $project_root_path;

$api_url = $base_url . "/api/students";

function callApi($url)
{
    $ch = curl_init($url);

    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);

    $response   = curl_exec($ch);
    $curl_error = curl_error($ch);
    $http_code  = curl_getinfo($ch, CURLINFO_HTTP_CODE);

    curl_close($ch);

    if ($response === false) {
        throw new Exception("เรียก API ไม่สำเร็จ: " . $curl_error);
    }

    if ($http_code !== 200) {
        throw new Exception("API ตอบกลับผิดพลาด (HTTP $http_code)");
    }

    $data = json_decode($response, true);

    if (json_last_error() !== JSON_ERROR_NONE) {
        throw new Exception("รูปแบบข้อมูลจาก API ไม่ถูกต้อง (ไม่ใช่ JSON)");
    }

    return $data;
}

/**
 * แบ่งกลุ่มตัวอักษรไทยสำหรับสร้างรหัสนักเรียน (เหมือน user/store.php)
 */
function getThaiLetterCode($name)
{
    $first = mb_substr(trim($name), 0, 1, "UTF-8");

    $groups = [
        "01" => ["ก", "ข", "ฃ", "ค", "ฅ", "ฆ", "ง"],
        "02" => ["จ", "ฉ", "ช", "ซ", "ฌ", "ญ"],
        "03" => ["ฎ", "ฏ", "ฐ", "ฑ", "ฒ", "ณ"],
        "04" => ["ด", "ต", "ถ", "ท", "ธ", "น"],
        "05" => ["บ", "ป", "ผ", "ฝ", "พ", "ฟ", "ภ", "ม"],
        "06" => ["ย", "ร", "ล", "ว", "ศ", "ษ", "ส", "ห", "ฬ", "อ", "ฮ"],
    ];

    foreach ($groups as $code => $letters) {
        if (in_array($first, $letters, true)) {
            return $code;
        }
    }

    return "00";
}

$title_codes = ["นาย" => "01", "นางสาว" => "02", "นาง" => "03"];

// =====================================================
// ดึงข้อมูล แล้วบันทึกลงฐานข้อมูลทีละคน
// (แยก transaction ต่อคน กันคนเดียวข้อมูลผิดแล้วทำให้ทั้งชุด rollback)
// =====================================================

$summary = ["inserted" => 0, "updated" => 0, "failed" => 0, "errors" => []];

try {
    $students = callApi($api_url);
} catch (Exception $e) {
    $_SESSION['import_result'] = [
        "ok"      => false,
        "message" => $e->getMessage(),
        "summary" => $summary,
    ];

    header("Location: import_students.php");
    exit;
}

// *** ถ้า API จริงตั้งชื่อ field ไม่ตรงกับ mock (เช่น "id_card" แทน
// "citizen_id", "fname" แทน "first_name_th") ให้แก้ชื่อ key ใน $row['...']
// ด้านล่างนี้ให้ตรงกับ field จริงที่ API ส่งมา ส่วนชื่อตัวแปร ($citizen_id,
// $first_name_th, ...) ไม่ต้องเปลี่ยน เพราะโค้ดข้างล่างอ้างอิงจากตัวแปรเหล่านี้

foreach ($students as $row) {
    $citizen_id    = trim($row['citizen_id'] ?? "");
    $title_name    = trim($row['title_name'] ?? "");
    $first_name_th = trim($row['first_name_th'] ?? "");
    $last_name_th  = trim($row['last_name_th'] ?? "");
    $first_name_en = trim($row['first_name_en'] ?? "");
    $last_name_en  = trim($row['last_name_en'] ?? "");
    $birthday      = $row['birthday'] ?? null;
    $sex           = $row['sex'] ?? "";
    $email         = trim($row['email'] ?? "");
    $phone         = trim($row['phone'] ?? "");
    $classroom_code = trim($row['classroom_number_code'] ?? "");

    $label = $first_name_th !== "" ? "$first_name_th $last_name_th" : ($citizen_id !== "" ? $citizen_id : "(ไม่ทราบชื่อ)");

    if ($citizen_id === "" || strlen($citizen_id) !== 13 || $first_name_th === "" || $last_name_th === "") {
        $summary['failed']++;
        $summary['errors'][] = "$label: ข้อมูลจาก API ไม่ครบ (ต้องมีเลขบัตร 13 หลัก, ชื่อ, นามสกุล)";
        continue;
    }

    // หาห้องเรียนจากรหัสห้อง (ถ้าไม่พบ จะปล่อย classroom_id เป็น NULL)
    $classroom_id = null;

    if ($classroom_code !== "") {
        $stmt = $conn->prepare("SELECT classroom_id FROM classroom WHERE classroom_number_code = ? LIMIT 1");
        $stmt->bind_param("s", $classroom_code);
        $stmt->execute();
        $classroom = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if ($classroom) {
            $classroom_id = (int)$classroom['classroom_id'];
        }
    }

    $conn->begin_transaction();

    try {
        // ตรวจว่ามีนักเรียนคนนี้อยู่แล้วหรือไม่ (อ้างอิงจากเลขบัตรประชาชน)
        $stmt = $conn->prepare("
            SELECT student_id, user_id
            FROM user_students
            WHERE citezen_id = ?
            LIMIT 1
        ");
        $stmt->bind_param("s", $citizen_id);
        $stmt->execute();
        $existing = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if ($existing) {
            // มีอยู่แล้ว: อัปเดตข้อมูลส่วนตัวให้ตรงกับ API
            $stmt = $conn->prepare("
                UPDATE user_students
                SET title_name = ?, first_name_th = ?, last_name_th = ?,
                    first_name_en = ?, last_name_en = ?, birthday = ?,
                    sex = ?, email = ?, phone = ?, classroom_id = ?
                WHERE student_id = ?
            ");
            $stmt->bind_param(
                "sssssssssii",
                $title_name,
                $first_name_th,
                $last_name_th,
                $first_name_en,
                $last_name_en,
                $birthday,
                $sex,
                $email,
                $phone,
                $classroom_id,
                $existing['student_id']
            );
            $stmt->execute();
            $stmt->close();

            $conn->commit();
            $summary['updated']++;
            continue;
        }

        // ยังไม่มี: สร้างบัญชีผู้ใช้งาน + ข้อมูลนักเรียนใหม่

        if (!isset($title_codes[$title_name])) {
            throw new Exception("คำนำหน้าไม่ถูกต้อง ($title_name)");
        }

        $username      = $citizen_id;
        $default_password = "123456";
        $password_hash = password_hash($default_password, PASSWORD_DEFAULT);
        $role          = "student";

        $stmt = $conn->prepare("
            INSERT INTO user_accounts (username, password_hash, role, is_active)
            VALUES (?, ?, ?, 1)
        ");
        $stmt->bind_param("sss", $username, $password_hash, $role);
        $stmt->execute();
        $user_id = $conn->insert_id;
        $stmt->close();

        $thai_year   = date("Y") + 543;
        $year_code   = substr($thai_year, -2);
        $title_code  = $title_codes[$title_name];
        $letter_code = getThaiLetterCode($first_name_th);
        $citizen_last3 = substr($citizen_id, -3);

        $student_code = $year_code . $title_code . $letter_code . $citizen_last3;

        $stmt = $conn->prepare("SELECT student_id FROM user_students WHERE student_code = ? LIMIT 1");
        $stmt->bind_param("s", $student_code);
        $stmt->execute();
        $dup = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if ($dup) {
            throw new Exception("รหัสนักเรียน $student_code ซ้ำกับที่มีอยู่แล้ว");
        }

        $stmt = $conn->prepare("
            INSERT INTO user_students
            (user_id, student_code, citezen_id, title_name, first_name_th, first_name_en,
             last_name_th, last_name_en, birthday, sex, email, phone, classroom_id)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->bind_param(
            "isssssssssssi",
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
        $stmt->close();

        $conn->commit();
        $summary['inserted']++;

    } catch (Exception $e) {
        $conn->rollback();
        $summary['failed']++;
        $summary['errors'][] = "$label: " . $e->getMessage();
    }
}

$_SESSION['import_result'] = [
    "ok"      => true,
    "message" => "ดึงข้อมูลจาก API เรียบร้อยแล้ว",
    "summary" => $summary,
];

header("Location: import_students.php");
exit;
