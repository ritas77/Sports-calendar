<?php

$host = getenv('POSTGRES_HOST');
$db   = getenv('POSTGRES_DB');
$user = getenv('POSTGRES_USER');
$pass = getenv('POSTGRES_PASSWORD');

header('Content-Type: application/json');

try {
    $pdo = new PDO("pgsql:host=$host;dbname=$db", $user, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    $limit = 15;
    $where = [];
    $params = [];
    // Sport filter (by competition's _sport_name)
    if (!empty($_GET['filter_sport'])) {
        $where[] = 'co1._sport_name = ?';
        $params[] = $_GET['filter_sport'];
    }
    // Competition filter (by comp_id)
    if (!empty($_GET['filter_competition'])) {
        $where[] = 'co1.comp_id = ?';
        $params[] = $_GET['filter_competition'];
    }
    // Team filter (by display_name, exact match for dropdown)
    if (!empty($_GET['filter_team'])) {
        $where[] = '(t1.display_name = ? OR t2.display_name = ?)';
        $params[] = $_GET['filter_team'];
        $params[] = $_GET['filter_team'];
    }


    // If asked for initial page, return the page with events closest to today
    if (isset($_GET['get_initial_page'])) {
        $today = date('Y-m-d');
        // Find the first event >= today (with filters)
        $whereForRow = $where;
        $paramsForRow = $params;
        $whereForRow[] = 'e.start_datetime >= ?';
        $paramsForRow[] = $today . ' 00:00:00';
        $rowSql = 'SELECT e.event_id FROM event e
            LEFT JOIN team t1 ON e._first_team_id = t1.team_id
            LEFT JOIN team t2 ON e._second_team_id = t2.team_id
            LEFT JOIN venue v ON e._venue_id = v.venue_id
            LEFT JOIN city c ON v._city_id = c.city_id
            LEFT JOIN country co ON c._country_code = co.country_code
            LEFT JOIN round r ON e._round_id = r.round_id
            LEFT JOIN competition co1 ON r._comp_id = co1.comp_id
            ' . (count($whereForRow) ? ('WHERE ' . implode(' AND ', $whereForRow)) : '') .
            ' ORDER BY e.start_datetime ASC, e.event_id ASC LIMIT 1';
        $stmtRow = $pdo->prepare($rowSql);
        $stmtRow->execute($paramsForRow);
        $firstEvent = $stmtRow->fetch(PDO::FETCH_ASSOC);
        if ($firstEvent) {
            // Fetch all event_ids in order (with filters)
            $allIdsSql = 'SELECT e.event_id FROM event e
                LEFT JOIN team t1 ON e._first_team_id = t1.team_id
                LEFT JOIN team t2 ON e._second_team_id = t2.team_id
                LEFT JOIN venue v ON e._venue_id = v.venue_id
                LEFT JOIN city c ON v._city_id = c.city_id
                LEFT JOIN country co ON c._country_code = co.country_code
                LEFT JOIN round r ON e._round_id = r.round_id
                LEFT JOIN competition co1 ON r._comp_id = co1.comp_id
                ' . (count($where) ? ('WHERE ' . implode(' AND ', $where)) : '') .
                ' ORDER BY e.start_datetime ASC, e.event_id ASC';
            $stmtAll = $pdo->prepare($allIdsSql);
            $stmtAll->execute($params);
            $allIds = $stmtAll->fetchAll(PDO::FETCH_COLUMN, 0);
            $index = array_search($firstEvent['event_id'], $allIds);
            if ($index === false) {
                $initialPage = 1;
            } else {
                $initialPage = floor($index / $limit) + 1;
            }
        } else {
            $initialPage = 1;
        }
        echo json_encode(['initialPage' => $initialPage]);
        exit;
    }

    // Default: use page param or 1
    if (!isset($_GET['page'])) {
        $page = 1;
    } else {
        $page = max(1, (int)$_GET['page']);
    }
    $offset = ($page - 1) * $limit;

    $sql = 'SELECT 
                e.event_id,
                e.start_datetime, 
                e.status,
                e.description,
                e._first_team_id,
                t1.display_name as team1_name,
                t1.logo as team1_logo,
                t1.established_year as team1_established_year,
                t1.primary_color as team1_primary_color,
                t1.secondary_color as team1_secondary_color,
                e.score_first,
                e.score_second,
                t2.display_name as team2_name,
                t2.logo as team2_logo,
                t2.established_year as team2_established_year,
                t2.primary_color as team2_primary_color,
                t2.secondary_color as team2_secondary_color,
                v.name as venue_name,
                v.capacity as venue_capacity,
                v.is_indoor as venue_is_indoor,
                v._home_team_id as venue_home_team_id,
                v._city_id as venue_city_id,
                c.city_name as venue_city_name,
                c._country_code as venue_country_code,
                co.country_name as venue_country_name,
                r.round_name as round_name,
                r._season_label as season_label
            FROM event e
            LEFT JOIN team t1 ON e._first_team_id = t1.team_id
            LEFT JOIN team t2 ON e._second_team_id = t2.team_id
            LEFT JOIN venue v ON e._venue_id = v.venue_id
            LEFT JOIN city c ON v._city_id = c.city_id
            LEFT JOIN country co ON c._country_code = co.country_code
            LEFT JOIN round r ON e._round_id = r.round_id
            LEFT JOIN competition co1 ON r._comp_id = co1.comp_id
                ' . (count($where) ? ('WHERE ' . implode(' AND ', $where)) : '') .
                ' ORDER BY e.start_datetime ASC, e.event_id ASC
                LIMIT ? OFFSET ?';

            $params[] = $limit;
            $params[] = $offset;
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
    $events = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($events as &$event) {
        if ($event['team1_logo']) {
            $binaryData = stream_get_contents($event['team1_logo']);
            $event['team1_logo'] = 'data:image/png;base64,' . base64_encode($binaryData);
        } else {
            $event['team1_logo'] = null;
        }
        
        if ($event['team2_logo']) {
            $binaryData = stream_get_contents($event['team2_logo']);
            $event['team2_logo'] = 'data:image/png;base64,' . base64_encode($binaryData);
        } else {
            $event['team2_logo'] = null;
        }
    }

    echo json_encode($events);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
exit;
