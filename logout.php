<?php
// C:\xampp\htdocs\UMBC447-DOORDASH\logout.php
session_start();
$_SESSION = [];
session_destroy();
header("Location: /UMBC447-DOORDASH/index.php?success=Logged+out");
exit();
