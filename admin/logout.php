<?php
session_start();
session_destroy();
header("Location: /matrimony/admin/login.php");
exit();
?>
