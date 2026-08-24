<?php

session_start();


// ล้าง Session

$_SESSION = array();


// ทำลาย Session

session_destroy();


// กลับหน้า Login

header("Location: index.php");

exit;

?>