<?php
$host = getenv('POSTGRES_HOST');
$db   = getenv('POSTGRES_DB');
$user = getenv('POSTGRES_USER');
$pass = getenv('POSTGRES_PASSWORD');
$port = getenv('POSTGRES_PORT');

$dsn = "pgsql:host=$host;port=$port;dbname=$db;";

try {
    // 1. Connect to the database
    $pdo = new PDO($dsn, $user, $pass, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        // Handle form submission
        $event_id = $_POST['event_id'] ?? null;
        if ($event_id) {
            // Clear the description for the event
            $stmt = $pdo->prepare("UPDATE event SET description = NULL WHERE event_id = ?");
            $stmt->execute([$event_id]);
            // Redirect back to the main page
            header('Location: index.html');
            exit;
        } else {
            echo "Error: Missing event_id";
        }
    }

} catch (PDOException $e) {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        echo "Database error: " . $e->getMessage();
    } else {
        header('Content-Type: application/json');
        echo json_encode(['error' => $e->getMessage()]);
    }
}
?>
