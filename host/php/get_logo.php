<?php
$host = getenv('POSTGRES_HOST');
$db   = getenv('POSTGRES_DB');
$user = getenv('POSTGRES_USER');
$pass = getenv('POSTGRES_PASSWORD');

try {
    $pdo = new PDO("pgsql:host=$host;dbname=$db", $user, $pass);
    
    $stmt = $pdo->query("SELECT logo FROM team LIMIT 1");
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($row && $row['logo']) {
        header("Content-Type: image/png");
        echo stream_get_contents($row['logo']);
    }
} catch (PDOException $e) {
    exit;
}
