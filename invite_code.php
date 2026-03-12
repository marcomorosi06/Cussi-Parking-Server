<?php
// invite_code.php
// Genera un codice di invito (owner) oppure lo usa per unirsi (chiunque).
// Azione "generate": crea un codice valido per 24 ore
// Azione "join":     usa un codice per diventare member
require 'db.php';
header('Content-Type: application/json');

// Assicuriamo che la tabella degli inviti esista
$pdo->exec("CREATE TABLE IF NOT EXISTS invite_codes (
    code TEXT PRIMARY KEY,
    vehicle_id INTEGER NOT NULL,
    created_by INTEGER NOT NULL,
    expires_at INTEGER NOT NULL,
    FOREIGN KEY(vehicle_id) REFERENCES vehicles(id),
    FOREIGN KEY(created_by) REFERENCES users(id)
)");

$token      = $_POST['token']  ?? '';
$action     = $_POST['action'] ?? ''; // 'generate' oppure 'join'
$vehicle_id = $_POST['vehicle_id'] ?? ''; // necessario per 'generate'
$code       = $_POST['code']       ?? ''; // necessario per 'join'

if (empty($token) || empty($action)) {
    die(json_encode(["status" => "error", "message" => "Dati mancanti."]));
}

$hashed_token = hash('sha256', $token);
$stmt = $pdo->prepare("SELECT id FROM users WHERE token = :token");
$stmt->execute([':token' => $hashed_token]);
$caller = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$caller) {
    die(json_encode(["status" => "error", "message" => "Token non valido."]));
}

// ----------------------------------------
if ($action === 'generate') {
// ----------------------------------------
    if (empty($vehicle_id)) {
        die(json_encode(["status" => "error", "message" => "vehicle_id mancante."]));
    }

    // Solo owner può generare inviti
    $stmt = $pdo->prepare("SELECT role FROM permissions WHERE user_id = :uid AND vehicle_id = :vid");
    $stmt->execute([':uid' => $caller['id'], ':vid' => $vehicle_id]);
    $perm = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$perm || $perm['role'] !== 'owner') {
        die(json_encode(["status" => "error", "message" => "Solo il proprietario può generare inviti."]));
    }

    // Genera un codice alfanumerico di 8 caratteri maiuscoli
    $new_code  = strtoupper(bin2hex(random_bytes(4))); // es. "A3F9C12B"
    $expires   = time() + 86400; // 24 ore

    try {
        // Cancella vecchi codici scaduti per questo veicolo (pulizia)
        $pdo->prepare("DELETE FROM invite_codes WHERE vehicle_id = :vid AND expires_at < :now")
            ->execute([':vid' => $vehicle_id, ':now' => time()]);

        $stmt = $pdo->prepare("INSERT INTO invite_codes (code, vehicle_id, created_by, expires_at) VALUES (:code, :vid, :uid, :exp)");
        $stmt->execute([':code' => $new_code, ':vid' => $vehicle_id, ':uid' => $caller['id'], ':exp' => $expires]);

        echo json_encode([
            "status"     => "success",
            "code"       => $new_code,
            "expires_at" => $expires
        ]);
    } catch (PDOException $e) {
        echo json_encode(["status" => "error", "message" => "Errore DB: " . $e->getMessage()]);
    }

// ----------------------------------------
} elseif ($action === 'join') {
// ----------------------------------------
    if (empty($code)) {
        die(json_encode(["status" => "error", "message" => "Codice mancante."]));
    }

    $stmt = $pdo->prepare("SELECT * FROM invite_codes WHERE code = :code");
    $stmt->execute([':code' => strtoupper(trim($code))]);
    $invite = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$invite) {
        die(json_encode(["status" => "error", "message" => "Codice non valido."]));
    }
    if ($invite['expires_at'] < time()) {
        die(json_encode(["status" => "error", "message" => "Codice scaduto."]));
    }

    $vid = $invite['vehicle_id'];

    // Controlla se è già membro/owner
    $stmt = $pdo->prepare("SELECT role FROM permissions WHERE user_id = :uid AND vehicle_id = :vid");
    $stmt->execute([':uid' => $caller['id'], ':vid' => $vid]);
    $existing = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($existing) {
        die(json_encode(["status" => "error", "message" => "Sei già " . $existing['role'] . " di questo veicolo."]));
    }

    try {
        $pdo->beginTransaction();

        $stmt = $pdo->prepare("INSERT INTO permissions (user_id, vehicle_id, role) VALUES (:uid, :vid, 'member')");
        $stmt->execute([':uid' => $caller['id'], ':vid' => $vid]);

        // Il codice è monouso: lo eliminiamo
        $pdo->prepare("DELETE FROM invite_codes WHERE code = :code")->execute([':code' => $invite['code']]);

        // Recupera il nome del veicolo per la risposta
        $v = $pdo->prepare("SELECT name FROM vehicles WHERE id = :vid");
        $v->execute([':vid' => $vid]);
        $vehicle_name = $v->fetchColumn();

        $pdo->commit();

        echo json_encode([
            "status"       => "success",
            "message"      => "Sei stato aggiunto al veicolo!",
            "vehicle_id"   => (int)$vid,
            "vehicle_name" => $vehicle_name
        ]);
    } catch (PDOException $e) {
        $pdo->rollBack();
        echo json_encode(["status" => "error", "message" => "Errore DB: " . $e->getMessage()]);
    }

// ----------------------------------------
} else {
    echo json_encode(["status" => "error", "message" => "Azione non riconosciuta. Usa 'generate' o 'join'."]);
}
?>
