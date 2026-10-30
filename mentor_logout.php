<?php
session_start();
session_destroy();
header("Location: mentor_login.php");
exit();
?>