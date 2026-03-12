<?php
// delete_vehicle.php
require 'db.php';
header('Content-Type: application/json');

// 1. Lettura dati
$token = $_POST['token'] ?? $_GET['token'] ?? '';
$vehicle_id = $_POST['vehicle_id'] ?? '';

if (empty($token) || empty($vehicle_id)) {
    die(json_encode(["status" => "error", "message" => "Dati mancanti."]));
}

// 2. Calcolo Hash del token
$hashed_token = hash('sha256', $token);

// 3. Autenticazione
$stmt = $pdo->prepare("SELECT id FROM users WHERE token = :token");
$stmt->execute([':token' => $hashed_token]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$user) {
    die(json_encode(["status" => "error", "message" => "Token non valido."]));
}
$user_id = $user['id'];

// 4. Controllo Permessi
$stmt = $pdo->prepare("SELECT role FROM permissions WHERE user_id = :user_id AND vehicle_id = :vehicle_id");
$stmt->execute([':user_id' => $user_id, ':vehicle_id' => $vehicle_id]);
$permission = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$permission) {
    die(json_encode(["status" => "error", "message" => "Permesso negato o veicolo inesistente."]));
}

try {
    // 5. Inizio transazione di eliminazione sicura
    $pdo->beginTransaction();

    // Rimuove i permessi collegati all'auto
    $stmt = $pdo->prepare("DELETE FROM permissions WHERE vehicle_id = :vehicle_id");
    $stmt->execute([':vehicle_id' => $vehicle_id]);

    // Rimuove l'auto dal database
    $stmt = $pdo->prepare("DELETE FROM vehicles WHERE id = :vehicle_id");
    $stmt->execute([':vehicle_id' => $vehicle_id]);

    $pdo->commit();

    echo json_encode(["status" => "success", "message" => "Veicolo eliminato."]);

} catch (PDOException $e) {
    $pdo->rollBack();
    echo json_encode(["status" => "error", "message" => "Errore DB: " . $e->getMessage()]);
}
?>
