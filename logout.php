<?php
session_start();
session_unset();
session_destroy();

// Po wylogowaniu wracamy na stronę logowania (lub np. polecenie1_1.php)
header('Location: logowanie.php'); 
exit();
?>