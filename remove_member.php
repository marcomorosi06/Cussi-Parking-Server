<?php
// remove_member.php
// Permette a un owner di rimuovere un membro (non se stesso).
require 'db.php';
header('Content-Type: application/json');

$token           = $_POST['token']           ?? '';
$vehicle_id      = $_POST['vehicle_id']      ?? '';
$target_user_id  = $_POST['target_user_id']  ?? '';

if (empty($token) || empty($vehicle_id) || empty($target_user_id)) {
    die(json_encode(["status" => "error", "message" => "Dati mancanti."]));
}

$hashed_token = hash('sha256', $token);

// Autenticazione chiamante
$stmt = $pdo->prepare("SELECT id FROM users WHERE token = :token");
$stmt->execute([':token' => $hashed_token]);
$caller = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$caller) {
    die(json_encode(["status" => "error", "message" => "Token non valido."]));
}

// Verifica che il chiamante sia owner
$stmt = $pdo->prepare("SELECT role FROM permissions WHERE user_id = :uid AND vehicle_id = :vid");
$stmt->execute([':uid' => $caller['id'], ':vid' => $vehicle_id]);
$perm = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$perm || $perm['role'] !== 'owner') {
    die(json_encode(["status" => "error", "message" => "Solo il proprietario può rimuovere membri."]));
}

// Non può rimuovere se stesso
if ($caller['id'] == $target_user_id) {
    die(json_encode(["status" => "error", "message" => "Non puoi rimuovere te stesso. Elimina il veicolo se vuoi."]));
}

// Non può rimuovere un altro owner
$stmt = $pdo->prepare("SELECT role FROM permissions WHERE user_id = :uid AND vehicle_id = :vid");
$stmt->execute([':uid' => $target_user_id, ':vid' => $vehicle_id]);
$targetPerm = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$targetPerm) {
    die(json_encode(["status" => "error", "message" => "Utente non trovato in questo veicolo."]));
}
if ($targetPerm['role'] === 'owner') {
    die(json_encode(["status" => "error", "message" => "Non puoi rimuovere un altro proprietario."]));
}

try {
    $stmt = $pdo->prepare("DELETE FROM permissions WHERE user_id = :uid AND vehicle_id = :vid");
    $stmt->execute([':uid' => $target_user_id, ':vid' => $vehicle_id]);
    echo json_encode(["status" => "success", "message" => "Membro rimosso."]);
} catch (PDOException $e) {
    echo json_encode(["status" => "error", "message" => "Errore DB: " . $e->getMessage()]);
}
?>
