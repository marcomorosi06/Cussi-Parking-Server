<?php
// db.php
$db_file = __DIR__ . '/parcheggio.sqlite';

try {
    $pdo = new PDO("sqlite:" . $db_file);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    $pdo->exec("CREATE TABLE IF NOT EXISTS users (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        email TEXT UNIQUE NOT NULL,
        username TEXT UNIQUE NOT NULL DEFAULT '',
        password_hash TEXT NOT NULL,
        token TEXT
    )");

    $pdo->exec("CREATE TABLE IF NOT EXISTS vehicles (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        name TEXT NOT NULL,
        icon TEXT,
        lat REAL,
        lng REAL,
        updated_at INTEGER,
        last_updated_by_user_id INTEGER,
        FOREIGN KEY(last_updated_by_user_id) REFERENCES users(id)
    )");

    $pdo->exec("CREATE TABLE IF NOT EXISTS permissions (
        user_id INTEGER,
        vehicle_id INTEGER,
        role TEXT NOT NULL,
        PRIMARY KEY (user_id, vehicle_id),
        FOREIGN KEY(user_id) REFERENCES users(id),
        FOREIGN KEY(vehicle_id) REFERENCES vehicles(id)
    )");

    $pdo->exec("CREATE TABLE IF NOT EXISTS invite_codes (
        code TEXT PRIMARY KEY,
        vehicle_id INTEGER NOT NULL,
        created_by INTEGER NOT NULL,
        expires_at INTEGER NOT NULL,
        FOREIGN KEY(vehicle_id) REFERENCES vehicles(id),
        FOREIGN KEY(created_by) REFERENCES users(id)
    )");

    // Migrazioni sicure per database già esistenti
    $migrations = [
        "ALTER TABLE users ADD COLUMN username TEXT UNIQUE DEFAULT ''",
        "ALTER TABLE vehicles ADD COLUMN last_updated_by_user_id INTEGER",
    ];
    foreach ($migrations as $sql) {
        try { $pdo->exec($sql); } catch (PDOException $e) { /* colonna già presente, ignora */ }
    }

} catch (PDOException $e) {
    die(json_encode(["status" => "error", "message" => "Errore DB: " . $e->getMessage()]));
}
?>
