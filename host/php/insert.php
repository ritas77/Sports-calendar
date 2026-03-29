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
        $description = $_POST['description'] ?? null;
        $score_first = isset($_POST['score_first']) && $_POST['score_first'] !== '' ? $_POST['score_first'] : null;
        $score_second = isset($_POST['score_second']) && $_POST['score_second'] !== '' ? $_POST['score_second'] : null;
        $status = $_POST['status'] ?? null;
        if ($event_id && $event_id !== 'undefined') {
            // Build dynamic update
            $fields = [];
            $params = [];
            if ($description !== null) {
                $fields[] = 'description = ?';
                $params[] = $description;
            }
            if ($score_first !== null) {
                $fields[] = 'score_first = ?';
                $params[] = $score_first;
            }
            if ($score_second !== null) {
                $fields[] = 'score_second = ?';
                $params[] = $score_second;
            }
            if ($status !== null) {
                $fields[] = 'status = ?';
                $params[] = $status;
            }
            if (count($fields) > 0) {
                $params[] = $event_id;
                $sql = 'UPDATE event SET ' . implode(', ', $fields) . ' WHERE event_id = ?';
                $stmt = $pdo->prepare($sql);
                $stmt->execute($params);
                header('Location: index.html');
                exit;
            } else {
                echo "Error: No fields to update.";
            }
        } else {
            echo "Error: Missing or invalid event_id.";
        }
    } else {
        // If not a POST request, show the current test behavior
        $stmt = $pdo->query("INSERT INTO event (description) VALUES ('Test description') RETURNING *");
        $event = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // Return as JSON
        header('Content-Type: application/json');
        echo json_encode($event);
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
