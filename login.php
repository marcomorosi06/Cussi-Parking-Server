<?php
// login.php
require 'db.php';

header('Content-Type: application/json');

$email = $_POST['email'] ?? '';
$password = $_POST['password'] ?? '';

if (empty($email) || empty($password)) {
    die(json_encode(["status" => "error", "message" => "Email e password obbligatorie."]));
}

// Cerca l'utente nel database
$stmt = $pdo->prepare("SELECT id, password_hash FROM users WHERE email = :email");
$stmt->execute([':email' => $email]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

// Verifica che l'utente esista e che la password coincida con l'hash
if ($user && password_verify($password, $user['password_hash'])) {
    
    // 1. Genera il token IN CHIARO (da dare all'app)
    $plain_token = bin2hex(random_bytes(32));
    
    // 2. Calcola l'HASH del token (da salvare nel database)
    $hashed_token = hash('sha256', $plain_token);
    
    // 3. Salva SOLO L'HASH nel database
    $updateStmt = $pdo->prepare("UPDATE users SET token = :token WHERE id = :id");
    $updateStmt->execute([
        ':token' => $hashed_token,
        ':id' => $user['id']
    ]);
    
    // 4. Restituisci il token IN CHIARO all'app Android
    echo json_encode([
        "status" => "success", 
        "message" => "Login effettuato.",
        "token" => $plain_token,
        "user_id" => $user['id']
    ]);

} else {
    echo json_encode(["status" => "error", "message" => "Email o password errati."]);
}
?>
