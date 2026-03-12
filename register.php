<?php
// register.php
require 'db.php';
header('Content-Type: application/json');

$email    = $_POST['email']    ?? '';
$password = $_POST['password'] ?? '';
$username = trim($_POST['username'] ?? '');

if (empty($email) || empty($password) || empty($username)) {
    die(json_encode(["status" => "error", "message" => "Email, username e password obbligatori."]));
}

// Validazione username: solo lettere, numeri, underscore, 3-20 caratteri
if (!preg_match('/^[a-zA-Z0-9_]{3,20}$/', $username)) {
    die(json_encode(["status" => "error", "message" => "Username non valido. Usa 3-20 caratteri: lettere, numeri o _."]));
}

$password_hash = password_hash($password, PASSWORD_DEFAULT);

try {
    $stmt = $pdo->prepare("INSERT INTO users (email, username, password_hash) VALUES (:email, :username, :password_hash)");
    $stmt->execute([
        ':email'         => $email,
        ':username'      => $username,
        ':password_hash' => $password_hash
    ]);
    echo json_encode(["status" => "success", "message" => "Utente registrato con successo."]);

} catch (PDOException $e) {
    if (str_contains($e->getMessage(), 'UNIQUE constraint failed: users.email')) {
        echo json_encode(["status" => "error", "message" => "Email già registrata."]);
    } elseif (str_contains($e->getMessage(), 'UNIQUE constraint failed: users.username')) {
        echo json_encode(["status" => "error", "message" => "Username già in uso. Scegline un altro."]);
    } else {
        echo json_encode(["status" => "error", "message" => "Errore DB: " . $e->getMessage()]);
    }
}
?>
