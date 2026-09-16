<?php
// Database Configuration
$servername = "localhost:3306";
$dbname = "hospital_db";
$dbusername = "root";
$dbpassword = "";


$conn = new mysqli($servername, $dbusername, $dbpassword, $dbname);


if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}
$conn->set_charset("utf8mb4");


?>