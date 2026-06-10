<?php

$servername = "localhost";
$username = "root";
$password = "";
$dbname = "psr_erea";

try {
    $conn = new PDO("mysql:host=$servername;dbname=$dbname", $username, $password);
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch(PDOException $e) {
    die("La connexion a échoué : " . $e->getMessage());
}

?>
