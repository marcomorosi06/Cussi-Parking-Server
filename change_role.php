<?php
// change_role.php
// Permette a un owner di promuovere un member a owner, o retrocedere un owner a member.
require 'db.php';
header('Content-Type: application/json');

$token          = $_POST['token']          ?? '';
$vehicle_id     = $_POST['vehicle_id']     ?? '';
$target_user_id = $_POST['target_user_id'] ?? '';
$new_role       = $_POST['new_role']       ?? ''; // 'owner' oppure 'member'

if (empty($token) || empty($vehicle_id) || empty($target_user_id) || empty($new_role)) {
    die(json_encode(["status" => "error", "message" => "Dati mancanti."]));
}
if (!in_array($new_role, ['owner', 'member'])) {
    die(json_encode(["status" => "error", "message" => "Ruolo non valido. Usa 'owner' o 'member'."]));
}

$hashed_token = hash('sha256', $token);
$stmt = $pdo->prepare("SELECT id FROM users WHERE token = :token");
$stmt->execute([':token' => $hashed_token]);
$caller = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$caller) die(json_encode(["status" => "error", "message" => "Token non valido."]));

// Solo un owner può cambiare ruoli
$stmt = $pdo->prepare("SELECT role FROM permissions WHERE user_id = :uid AND vehicle_id = :vid");
$stmt->execute([':uid' => $caller['id'], ':vid' => $vehicle_id]);
$callerPerm = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$callerPerm || $callerPerm['role'] !== 'owner') {
    die(json_encode(["status" => "error", "message" => "Solo il proprietario può cambiare i ruoli."]));
}

// Non può cambiare il proprio ruolo
if ($caller['id'] == $target_user_id) {
    die(json_encode(["status" => "error", "message" => "Non puoi cambiare il tuo stesso ruolo."]));
}

// Verifica che il target sia un membro del veicolo
$stmt = $pdo->prepare("SELECT role FROM permissions WHERE user_id = :uid AND vehicle_id = :vid");
$stmt->execute([':uid' => $target_user_id, ':vid' => $vehicle_id]);
$targetPerm = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$targetPerm) {
    die(json_encode(["status" => "error", "message" => "Utente non trovato in questo veicolo."]));
}
if ($targetPerm['role'] === $new_role) {
    die(json_encode(["status" => "error", "message" => "L'utente ha gia il ruolo '$new_role'."]));
}

try {
    $stmt = $pdo->prepare("UPDATE permissions SET role = :role WHERE user_id = :uid AND vehicle_id = :vid");
    $stmt->execute([':role' => $new_role, ':uid' => $target_user_id, ':vid' => $vehicle_id]);
    $label = $new_role === 'owner' ? 'Proprietario' : 'Membro';
    echo json_encode(["status" => "success", "message" => "Ruolo aggiornato a $label."]);
} catch (PDOException $e) {
    echo json_encode(["status" => "error", "message" => "Errore DB: " . $e->getMessage()]);
}
?>
