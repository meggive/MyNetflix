<?php
declare(strict_types=1);

// Dane połączeniowe do bazy MySQL
$db_host = 'localhost';
$db_user = 'srv126279_z6b'; // Zmień na nowy login bazy
$db_pass = 'bazadanych';   // Zmień na nowe hasło
$db_name = 'srv126279_z6b'; // Zmień na nową nazwę bazy

$link = @mysqli_connect($db_host, $db_user, $db_pass, $db_name);

if (!$link) {
    die("Błąd połączenia z bazą danych: " . mysqli_connect_error());
}

mysqli_query($link, "SET NAMES 'utf8mb4'");
?>