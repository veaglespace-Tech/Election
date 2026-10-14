<?php
/**
 * Polling Stations API
 * Handles: pagination, filtering by district, voter_type, station_no, location, building, area
 */

// ── CORS & headers ────────────────────────────────────────────────────────
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');

// ── DB Config ─────────────────────────────────────────────────────────────
define('DB_HOST', 'localhost');
define('DB_NAME', 'election_db');
define('DB_USER', 'election_app');
define('DB_PASS', 'Veagle@12345');
define('DB_PORT', 3306);

function getDB(): PDO
{
    static $pdo = null;
    if ($pdo) return $pdo;
    $dsn = 'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4;port=' . DB_PORT;
    $options = [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ];
    $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
    return $pdo;
}

function inp(string $key, $default = '') {
    return isset($_GET[$key]) ? trim($_GET[$key]) : $default;
}

// ── SUGGEST MODE (live autocomplete dropdown) ─────────────────────────────
$mode = inp('mode');
if ($mode === 'suggest') {
    $q = inp('q');
    if (strlen($q) < 1) { echo json_encode(['suggestions'=>[]]); exit; }
    try {
        $db   = getDB();
        $like = "%$q%";
        $stmt = $db->prepare(
            "SELECT ps.station_no, ps.location, ps.building, ps.area, ps.voter_type,
                    d.name AS district_name
             FROM polling_stations ps
             LEFT JOIN districts d ON d.id = ps.district_id
             WHERE ps.location LIKE :q1
                OR ps.building LIKE :q2
                OR ps.area     LIKE :q3
                OR CAST(ps.station_no AS CHAR) = :q4
             ORDER BY
               (ps.location LIKE :q5) DESC,
               (ps.area     LIKE :q6) DESC,
               ps.station_no ASC
             LIMIT 12"
        );
        $stmt->execute([':q1'=>$like,':q2'=>$like,':q3'=>$like,':q4'=>$q,':q5'=>"$q%",':q6'=>"$q%"]);
        echo json_encode(['suggestions'=>$stmt->fetchAll()], JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
    } catch (PDOException $e) {
        echo json_encode(['suggestions'=>[], 'error'=>$e->getMessage()]);
    }
    exit;
}

// ── Params ────────────────────────────────────────────────────────────────
$page       = max(1, (int) inp('page', 1));
$limit      = min(200, max(10, (int) inp('limit', 50)));
$offset     = ($page - 1) * $limit;

$district   = inp('district');      // district_id
$voter_type = inp('voter_type');    // TEACHERS_ONLY, GRADUATES_ONLY, ALL_ELECTORS
$station_no = inp('station_no');    // exact or partial
$location   = inp('location');      // location text search
$search     = inp('search');        // global search across location, building, area

// ── Query ─────────────────────────────────────────────────────────────────
try {
    $db = getDB();

    // Build WHERE
    $where  = [];
    $params = [];

    if ($district !== '') {
        $where[]              = 'ps.district_id = :district';
        $params[':district']  = (int) $district;
    }
    if ($voter_type !== '') {
        $where[]               = 'ps.voter_type = :voter_type';
        $params[':voter_type'] = $voter_type;
    }
    if ($station_no !== '') {
        if (ctype_digit($station_no)) {
            $where[]               = 'ps.station_no = :station_no';
            $params[':station_no'] = (int) $station_no;
        } else {
            $where[]               = 'CAST(ps.station_no AS CHAR) LIKE :station_no';
            $params[':station_no'] = "%$station_no%";
        }
    }
    if ($location !== '') {
        $where[]             = 'ps.location LIKE :location';
        $params[':location'] = "%$location%";
    }
    if ($search !== '') {
        $where[]           = '(ps.location LIKE :search OR ps.building LIKE :search2 OR ps.area LIKE :search3)';
        $params[':search']  = "%$search%";
        $params[':search2'] = "%$search%";
        $params[':search3'] = "%$search%";
    }

    $whereSQL = count($where) ? 'WHERE ' . implode(' AND ', $where) : '';

    $joinSQL = 'FROM polling_stations ps LEFT JOIN districts d ON d.id = ps.district_id';

    // Count
    $countStmt = $db->prepare("SELECT COUNT(*) $joinSQL $whereSQL");
    $countStmt->execute($params);
    $total = (int) $countStmt->fetchColumn();

    // Data
    $dataStmt = $db->prepare(
        "SELECT ps.station_no, ps.location, ps.building, ps.area, ps.voter_type,
                ps.constituency, ps.district_id, d.name AS district_name
         $joinSQL $whereSQL
         ORDER BY ps.station_no ASC
         LIMIT :limit OFFSET :offset"
    );
    foreach ($params as $k => $v) $dataStmt->bindValue($k, $v);
    $dataStmt->bindValue(':limit',  $limit,  PDO::PARAM_INT);
    $dataStmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $dataStmt->execute();
    $rows = $dataStmt->fetchAll();

    // Districts list for filter dropdown
    $districts = $db->query("SELECT id, name FROM districts ORDER BY id ASC")->fetchAll();

    echo json_encode([
        'success'     => true,
        'total'       => $total,
        'page'        => $page,
        'limit'       => $limit,
        'total_pages' => (int) ceil($total / $limit),
        'districts'   => $districts,
        'data'        => $rows,
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
