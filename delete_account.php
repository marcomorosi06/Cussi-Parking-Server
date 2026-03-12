<?php
// delete_account.php
// Cancella l'account dell'utente autenticato.
// - Rimuove l'utente da tutti i veicoli di cui è member
// - Se è l'unico owner di un veicolo, cancella anche il veicolo
// - Se ci sono altri owner, rimuove solo la sua permission
require 'db.php';
header('Content-Type: application/json');

$token    = $_POST['token']    ?? '';
$password = $_POST['password'] ?? ''; // richiediamo la password per conferma

if (empty($token) || empty($password)) {
    die(json_encode(["status" => "error", "message" => "Token e password obbligatori."]));
}

$hashed_token = hash('sha256', $token);
$stmt = $pdo->prepare("SELECT id, password FROM users WHERE token = :token");
$stmt->execute([':token' => $hashed_token]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$user) die(json_encode(["status" => "error", "message" => "Token non valido."]));

// Verifica password
if (!password_verify($password, $user['password'])) {
    die(json_encode(["status" => "error", "message" => "Password errata."]));
}

$user_id = (int)$user['id'];

try {
    $pdo->beginTransaction();

    // Trova tutti i veicoli di cui questo utente è OWNER
    $stmt = $pdo->prepare("SELECT vehicle_id FROM permissions WHERE user_id = :uid AND role = 'owner'");
    $stmt->execute([':uid' => $user_id]);
    $ownedVehicles = $stmt->fetchAll(PDO::FETCH_COLUMN);

    foreach ($ownedVehicles as $vid) {
        // Conta gli altri owner di questo veicolo
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM permissions WHERE vehicle_id = :vid AND role = 'owner' AND user_id != :uid");
        $stmt->execute([':vid' => $vid, ':uid' => $user_id]);
        $otherOwners = (int)$stmt->fetchColumn();

        if ($otherOwners === 0) {
            // Unico owner: cancella il veicolo e tutte le sue permissions
            $pdo->prepare("DELETE FROM permissions WHERE vehicle_id = :vid")->execute([':vid' => $vid]);
            $pdo->prepare("DELETE FROM vehicles WHERE id = :vid")->execute([':vid' => $vid]);
        }
        // Se ci sono altri owner, il veicolo sopravvive — la permission viene rimossa sotto
    }

    // Rimuovi l'utente da tutte le permissions rimanenti (member o owner con altri owner)
    $pdo->prepare("DELETE FROM permissions WHERE user_id = :uid")->execute([':uid' => $user_id]);

    // Cancella i codici invito creati da questo utente (se esiste la tabella)
    try {
        $pdo->prepare("DELETE FROM invite_codes WHERE created_by = :uid")->execute([':uid' => $user_id]);
    } catch (PDOException $e) { /* tabella potrebbe non esistere */ }

    // Cancella l'utente
    $pdo->prepare("DELETE FROM users WHERE id = :uid")->execute([':uid' => $user_id]);

    $pdo->commit();
    echo json_encode(["status" => "success", "message" => "Account eliminato con successo."]);

} catch (PDOException $e) {
    $pdo->rollBack();
    echo json_encode(["status" => "error", "message" => "Errore DB: " . $e->getMessage()]);
}
?>
