<?php
$conn = new mysqli("localhost", "root", "", "wellness_platform");

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

?>