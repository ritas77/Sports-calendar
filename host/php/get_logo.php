<?php
$host = 'db';
$db   = 'sports_calendar_db';
$user = 'postgres';
$pass = 'mypassword';

try {
    $pdo = new PDO("pgsql:host=$host;dbname=$db", $user, $pass);
    
    $stmt = $pdo->query("SELECT logo FROM Team LIMIT 1");
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($row && $row['logo']) {
        // Tell the browser this is a PNG image
        header("Content-Type: image/png");
        // Output the binary data
        echo stream_get_contents($row['logo']);
    }
} catch (PDOException $e) {
    // If it fails, don't output HTML, just exit
    exit;
}
