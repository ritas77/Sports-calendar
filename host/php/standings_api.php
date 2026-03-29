<?php
// API endpoint for returning league standings as JSON for different leagues
header('Content-Type: application/json');
require_once 'db.php'; // expects $pdo connection

// Get league parameter from query string
$league = $_GET['league'] ?? '';

try {
    // Choose the correct SQL view/table based on league param
    if ($league === 'nba_east') {
        $sql = "SELECT * FROM nba_conference_standings WHERE \"Conference\" = 'Eastern'";
    } elseif ($league === 'nba_west') {
        $sql = "SELECT * FROM nba_conference_standings WHERE \"Conference\" = 'Western'";
    } elseif ($league === 'premier_league') {
        $sql = "SELECT * FROM view_standings_premier_league";
    } elseif ($league === 'ekstraklasa') {
        $sql = "SELECT * FROM view_standings_ekstraklasa";
    } else {
        echo json_encode(['error' => 'Invalid league']);
        exit;
    }
    // Execute query and fetch all rows
    $stmt = $pdo->query($sql);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    if (!$rows) {
        // Return empty columns/rows if no data
        echo json_encode(['columns' => [], 'rows' => []]);
        exit;
    }
    // Return columns and rows for frontend table rendering
    $columns = array_keys($rows[0]);
    $dataRows = array_map('array_values', $rows);
    echo json_encode(['columns' => $columns, 'rows' => $dataRows]);
} catch (Exception $e) {
    // On error, return error message as JSON
    echo json_encode(['error' => $e->getMessage()]);
}
