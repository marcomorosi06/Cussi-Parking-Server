<?php
// add_vehicle.php
require 'db.php';
header('Content-Type: application/json');

// 1. Lettura dati (flessibile)
$token = $_POST['token'] ?? $_GET['token'] ?? '';
$name = $_POST['name'] ?? $_GET['name'] ?? '';
$icon = $_POST['icon'] ?? $_GET['icon'] ?? 'car';

if (empty($token) || empty($name)) {
    die(json_encode(["status" => "error", "message" => "Token e nome auto obbligatori."]));
}

// 2. Calcolo Hash per la sicurezza
$hashed_token = hash('sha256', $token);

// 3. Autenticazione
$stmt = $pdo->prepare("SELECT id FROM users WHERE token = :token");
$stmt->execute([':token' => $hashed_token]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$user) {
    die(json_encode(["status" => "error", "message" => "Token non valido."]));
}
$user_id = $user['id'];

try {
    // 4. Inizio transazione: Creiamo l'auto E diamo i permessi al proprietario
    $pdo->beginTransaction();

    // Creazione del veicolo
    $stmt = $pdo->prepare("INSERT INTO vehicles (name, icon, updated_at) VALUES (:name, :icon, :updated_at)");
    $stmt->execute([
        ':name' => $name,
        ':icon' => $icon,
        ':updated_at' => time()
    ]);
    
    // Recuperiamo l'ID appena generato dal database
    $vehicle_id = $pdo->lastInsertId();

    // Diamo all'utente che l'ha creata il ruolo di "owner" (proprietario)
    $stmt = $pdo->prepare("INSERT INTO permissions (user_id, vehicle_id, role) VALUES (:user_id, :vehicle_id, 'owner')");
    $stmt->execute([
        ':user_id' => $user_id,
        ':vehicle_id' => $vehicle_id
    ]);

    $pdo->commit();

    echo json_encode(["status" => "success", "message" => "Veicolo aggiunto con successo.", "vehicle_id" => $vehicle_id]);

} catch (PDOException $e) {
    $pdo->rollBack();
    echo json_encode(["status" => "error", "message" => "Errore DB: " . $e->getMessage()]);
}
?>
