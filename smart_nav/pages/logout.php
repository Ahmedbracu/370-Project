<?php
session_start();
session_destroy();
header("Location: /smart_nav/pages/login.php");
exit();
?>
