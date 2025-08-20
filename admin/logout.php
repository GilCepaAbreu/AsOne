<?php
require_once '../config/database.php';
require_once '../classes/Auth.php';

$auth = new Auth($pdo);
$auth->logout();

header('Location: /CodeCraftStudio/AsOne/index.php?logout=success');
exit;
?>
