<?php
// get_locations.php
require 'db.php';
header('Content-Type: application/json');

$token = $_POST['token'] ?? $_GET['token'] ?? '';

if (empty($token)) {
    die(json_encode(["status" => "error", "message" => "Token mancante."]));
}

$hashed_token = hash('sha256', $token);

$stmt = $pdo->prepare("SELECT id FROM users WHERE token = :token");
$stmt->execute([':token' => $hashed_token]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$user) {
    die(json_encode(["status" => "error", "message" => "Token non valido o scaduto."]));
}
$user_id = $user['id'];

try {
    // LEFT JOIN con users per recuperare l'username di chi ha aggiornato per ultimo la posizione
    $query = "
        SELECT
            v.id,
            v.name,
            v.icon,
            v.lat,
            v.lng,
            v.updated_at,
            p.role,
            u.username AS last_updated_by
        FROM vehicles v
        JOIN permissions p ON v.id = p.vehicle_id
        LEFT JOIN users u ON v.last_updated_by_user_id = u.id
        WHERE p.user_id = :user_id
    ";

    $stmt = $pdo->prepare($query);
    $stmt->execute([':user_id' => $user_id]);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Cast dei tipi numerici: PDO/SQLite restituisce tutto come stringa
    $vehicles = array_map(function($v) {
        return [
            'id'              => (int)$v['id'],
            'name'            => $v['name'],
            'icon'            => $v['icon'],
            'lat'             => $v['lat'] !== null ? (float)$v['lat'] : null,
            'lng'             => $v['lng'] !== null ? (float)$v['lng'] : null,
            'updated_at'      => $v['updated_at'] !== null ? (int)$v['updated_at'] : null,
            'role'            => $v['role'],
            'last_updated_by' => $v['last_updated_by'] ?? null,
        ];
    }, $rows);

    echo json_encode([
        "status" => "success",
        "data"   => $vehicles
    ]);

} catch (PDOException $e) {
    echo json_encode(["status" => "error", "message" => "Errore DB: " . $e->getMessage()]);
}
?>
