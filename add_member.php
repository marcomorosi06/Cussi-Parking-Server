<?php
// add_member.php
// Permette a un owner di aggiungere un membro a un veicolo tramite username.
require 'db.php';
header('Content-Type: application/json');

$token      = $_POST['token']      ?? '';
$vehicle_id = $_POST['vehicle_id'] ?? '';
$username   = $_POST['username']   ?? '';

if (empty($token) || empty($vehicle_id) || empty($username)) {
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

// Verifica che il chiamante sia owner del veicolo
$stmt = $pdo->prepare("SELECT role FROM permissions WHERE user_id = :uid AND vehicle_id = :vid");
$stmt->execute([':uid' => $caller['id'], ':vid' => $vehicle_id]);
$perm = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$perm || $perm['role'] !== 'owner') {
    die(json_encode(["status" => "error", "message" => "Solo il proprietario può aggiungere membri."]));
}

// Cerca l'utente da aggiungere per username
$stmt = $pdo->prepare("SELECT id FROM users WHERE username = :username");
$stmt->execute([':username' => $username]);
$target = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$target) {
    die(json_encode(["status" => "error", "message" => "Utente '$username' non trovato."]));
}

// Controlla che non sia già membro
$stmt = $pdo->prepare("SELECT role FROM permissions WHERE user_id = :uid AND vehicle_id = :vid");
$stmt->execute([':uid' => $target['id'], ':vid' => $vehicle_id]);
$existing = $stmt->fetch(PDO::FETCH_ASSOC);
if ($existing) {
    die(json_encode(["status" => "error", "message" => "L'utente è già " . $existing['role'] . " di questo veicolo."]));
}

try {
    $stmt = $pdo->prepare("INSERT INTO permissions (user_id, vehicle_id, role) VALUES (:uid, :vid, 'member')");
    $stmt->execute([':uid' => $target['id'], ':vid' => $vehicle_id]);
    echo json_encode(["status" => "success", "message" => "Membro aggiunto con successo."]);
} catch (PDOException $e) {
    echo json_encode(["status" => "error", "message" => "Errore DB: " . $e->getMessage()]);
}
?>
