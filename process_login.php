<?php
require_once 'config/database.php';
require_once 'classes/Auth.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Método não permitido.']);
    exit;
}

$email = trim($_POST['email'] ?? '');
$password = $_POST['password'] ?? '';

if (empty($email) || empty($password)) {
    echo json_encode(['success' => false, 'message' => 'Email e senha são obrigatórios.']);
    exit;
}

$auth = new Auth($pdo);
$result = $auth->login($email, $password);

if ($result['success']) {
    $result['redirect'] = '/CodeCraftStudio/AsOne/admin/index.php';
}

echo json_encode($result);
?>
