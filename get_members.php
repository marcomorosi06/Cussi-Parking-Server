<?php
// get_members.php
// Restituisce la lista dei membri di un veicolo (solo se si ha accesso).
require 'db.php';
header('Content-Type: application/json');

$token      = $_POST['token']      ?? '';
$vehicle_id = $_POST['vehicle_id'] ?? '';

if (empty($token) || empty($vehicle_id)) {
    die(json_encode(["status" => "error", "message" => "Dati mancanti."]));
}

$hashed_token = hash('sha256', $token);

// Autenticazione
$stmt = $pdo->prepare("SELECT id FROM users WHERE token = :token");
$stmt->execute([':token' => $hashed_token]);
$caller = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$caller) {
    die(json_encode(["status" => "error", "message" => "Token non valido."]));
}

// Verifica che il chiamante abbia accesso al veicolo (owner O member)
$stmt = $pdo->prepare("SELECT role FROM permissions WHERE user_id = :uid AND vehicle_id = :vid");
$stmt->execute([':uid' => $caller['id'], ':vid' => $vehicle_id]);
$perm = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$perm) {
    die(json_encode(["status" => "error", "message" => "Accesso negato."]));
}

try {
    $stmt = $pdo->prepare("
        SELECT u.id, u.username, p.role
        FROM users u
        JOIN permissions p ON u.id = p.user_id
        WHERE p.vehicle_id = :vid
        ORDER BY p.role ASC, u.username ASC
    ");
    $stmt->execute([':vid' => $vehicle_id]);
    $members = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Cast id a intero, aggiungi is_me
    $caller_id = (int)$caller['id'];
    $members = array_map(function($m) use ($caller_id) {
        return [
            'id'       => (int)$m['id'],
            'username' => $m['username'],
            'role'     => $m['role'],
            'is_me'    => ((int)$m['id'] === $caller_id)
        ];
    }, $members);

    echo json_encode(["status" => "success", "data" => $members]);
} catch (PDOException $e) {
    echo json_encode(["status" => "error", "message" => "Errore DB: " . $e->getMessage()]);
}
?>
