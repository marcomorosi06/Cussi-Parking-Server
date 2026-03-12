<?php
// update_location.php
require 'db.php';
header('Content-Type: application/json');

$token      = $_POST['token']      ?? $_GET['token'] ?? '';
$vehicle_id = $_POST['vehicle_id'] ?? '';
$lat        = $_POST['lat']        ?? '';
$lng        = $_POST['lng']        ?? '';
// Il timestamp del client viene usato solo per la logica anti-conflitto,
// ma il server salva sempre il proprio time() per evitare disallineamenti di clock.
$client_updated_at = isset($_POST['updated_at']) ? (int)$_POST['updated_at'] : time();

if (empty($token) || empty($vehicle_id) || empty($lat) || empty($lng)) {
    die(json_encode(["status" => "error", "message" => "Dati mancanti."]));
}

$hashed_token = hash('sha256', $token);

$stmt = $pdo->prepare("SELECT id FROM users WHERE token = :token");
$stmt->execute([':token' => $hashed_token]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$user) {
    die(json_encode(["status" => "error", "message" => "Token non valido."]));
}
$user_id = $user['id'];

try {
    $stmt = $pdo->prepare("
        SELECT v.updated_at
        FROM vehicles v
        JOIN permissions p ON v.id = p.vehicle_id
        WHERE p.user_id = :user_id AND v.id = :vehicle_id
    ");
    $stmt->execute([':user_id' => $user_id, ':vehicle_id' => $vehicle_id]);
    $vehicle = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$vehicle) {
        die(json_encode(["status" => "error", "message" => "Veicolo non trovato o permessi negati."]));
    }

    $db_updated_at = $vehicle['updated_at'] ?? 0;

    // Anti-conflitto: se il client manda un timestamp molto vecchio
    // (più di 60 secondi prima di quello sul server), ignoriamo.
    // Usiamo una tolleranza di 60s per compensare piccoli disallineamenti di clock.
    if ($client_updated_at < ($db_updated_at - 60)) {
        die(json_encode([
            "status"  => "success",
            "message" => "Ignorato: esiste già una posizione più recente sul server."
        ]));
    }

    // Salviamo con il timestamp del SERVER (affidabile) anziché del client
    $server_now = time();

    $stmt = $pdo->prepare("
        UPDATE vehicles
        SET lat = :lat, lng = :lng, updated_at = :updated_at, last_updated_by_user_id = :user_id
        WHERE id = :vehicle_id
    ");
    $stmt->execute([
        ':lat'        => $lat,
        ':lng'        => $lng,
        ':updated_at' => $server_now,
        ':user_id'    => $user_id,
        ':vehicle_id' => $vehicle_id
    ]);

    echo json_encode(["status" => "success", "message" => "Posizione aggiornata con successo."]);

} catch (PDOException $e) {
    echo json_encode(["status" => "error", "message" => "Errore DB: " . $e->getMessage()]);
}
?>
