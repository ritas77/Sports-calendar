<?php
// insert_round.php: Insert a new round and return its ID as JSON
header('Content-Type: application/json');

$envFile = __DIR__ . '/../.env';
if (file_exists($envFile)) {
    $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (strpos($line, '=') !== false && strpos($line, '#') !== 0) {
            list($key, $value) = explode('=', $line, 2);
            putenv(trim($key) . '=' . trim($value));
        }
    }
}

$host = getenv('POSTGRES_HOST') ?: 'db';
$db = getenv('POSTGRES_DB');
$user = getenv('POSTGRES_USER');
$pass = getenv('POSTGRES_PASSWORD');
$port = getenv('POSTGRES_PORT') ?: '5432';

try {
    $pdo = new PDO("pgsql:host=$host;port=$port;dbname=$db", $user, $pass, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
    $data = json_decode(file_get_contents('php://input'), true);
    $round_name = trim($data['round_name'] ?? '');
    $_season_label = $data['_season_label'] ?? null;
    $_comp_id = $data['_comp_id'] ?? null;
    if (!$round_name || !$_season_label || !$_comp_id) {
        echo json_encode(['success' => false, 'error' => 'Missing required fields.']);
        exit;
    }
    // Insert round
    $stmt = $pdo->prepare('INSERT INTO round (round_name, _season_label, _comp_id) VALUES (?, ?, ?) RETURNING round_id');
    $stmt->execute([$round_name, $_season_label, $_comp_id]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($row && isset($row['round_id'])) {
        echo json_encode(['success' => true, 'round_id' => $row['round_id']]);
    } else {
        echo json_encode(['success' => false, 'error' => 'Insert failed.']);
    }
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
