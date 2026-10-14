<?php
/**
 * Election Data API
 * Handles: pagination, filtering by name, age, address, part_no, gender
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
    if ($pdo)
        return $pdo;
    $dsn = 'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4;port=' . DB_PORT;
    $options = [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ];
    try {
        $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
    } catch (PDOException $e) {
        $pdo = new PDO($dsn, 'election_app', 'Veagle@12345', $options);
    }
    return $pdo;
}

// ── Input sanitization ────────────────────────────────────────────────────
function inp(string $key, $default = '')
{
    return isset($_GET[$key]) ? trim($_GET[$key]) : $default;
}

// ── Params ────────────────────────────────────────────────────────────────
$page = max(1, (int) inp('page', 1));
$limit = min(500, max(10, (int) inp('limit', 500)));
$offset = ($page - 1) * $limit;


$name = inp('name');
$address = inp('address');
$institute = inp('institute');
$part_no = inp('part_no');
$gender = inp('gender');
$status = inp('status', 'ACTIVE');

// ── Query ─────────────────────────────────────────────────────────────────
try {
    $db = getDB();

    // Check which optional columns exist (schema migration safe)
    $hasId = false;
    $hasStatus = false;
    $hasReview = false;
    $hasBuilding = false;
    try {
        $cols = $db->query("SELECT COLUMN_NAME FROM information_schema.COLUMNS
            WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'electors'
            AND COLUMN_NAME IN ('id', 'status', 'review', 'building')")
            ->fetchAll(PDO::FETCH_COLUMN);
        $hasId       = in_array('id', $cols, true);
        $hasStatus   = in_array('status', $cols, true);
        $hasReview   = in_array('review', $cols, true);
        $hasBuilding = in_array('building', $cols, true);
    } catch (Exception $e) {
        // defaults are all false
    }

    // Check if polling_stations table exists
    $hasPsTable = false;
    try {
        $checkPs = $db->query("SHOW TABLES LIKE 'polling_stations'")->fetchAll();
        $hasPsTable = count($checkPs) > 0;
    } catch (Exception $e) {
        $hasPsTable = false;
    }

    // ── Build WHERE ───────────────────────────────────────────────────────────
    $where = [];
    $params = [];

    if ($hasStatus && $status !== '' && $status !== 'ALL') {
        $where[] = 'e.status = :status';
        $params[':status'] = $status;
    }

    if ($name !== '') {
        $where[] = 'e.elector_name LIKE :name';
        $params[':name'] = "%$name%";
    }
    if ($address !== '') {
        $where[] = 'e.address LIKE :address';
        $params[':address'] = "%$address%";
    }
    if ($institute !== '') {
        $where[] = 'e.institute LIKE :inst';
        $params[':inst'] = "%$institute%";
    }
    if ($part_no !== '') {
        $where[] = 'e.part_no = :part_no';
        $params[':part_no'] = $part_no;
    }
    if ($gender !== '') {
        $where[] = 'e.gender = :gender';
        $params[':gender'] = $gender;
    }

    $whereSQL = count($where) ? 'WHERE ' . implode(' AND ', $where) : '';

    // Count
    $countStmt = $db->prepare("SELECT COUNT(*) FROM electors e $whereSQL");
    $countStmt->execute($params);
    $total = (int) $countStmt->fetchColumn();

    // Dynamic column selection & table join
    $idSel     = $hasId     ? 'e.id, '       : '';
    $statusSel = $hasStatus ? ', e.status'   : ", 'ACTIVE' AS status";
    $reviewSel = $hasReview ? ', e.review'   : ", '' AS review";

    if ($hasBuilding && $hasPsTable) {
        $buildingSel = ", COALESCE(NULLIF(e.building, ''), ps.building, '') AS building";
        $fromSQL = "FROM electors e LEFT JOIN polling_stations ps ON ps.station_no = e.part_no";
    } elseif ($hasPsTable) {
        $buildingSel = ", COALESCE(ps.building, '') AS building";
        $fromSQL = "FROM electors e LEFT JOIN polling_stations ps ON ps.station_no = e.part_no";
    } elseif ($hasBuilding) {
        $buildingSel = ", e.building";
        $fromSQL = "FROM electors e";
    } else {
        $buildingSel = ", '' AS building";
        $fromSQL = "FROM electors e";
    }

    // Data — ordered by part_no, sr_no
    $dataStmt = $db->prepare(
        "SELECT {$idSel}e.part_no, e.sr_no, e.elector_name, e.relative_name, e.address, e.institute, e.age, e.gender, e.epic_no{$buildingSel}{$statusSel}{$reviewSel}
         $fromSQL
         $whereSQL
         ORDER BY e.part_no ASC, e.sr_no ASC
         LIMIT :limit OFFSET :offset"
    );
    foreach ($params as $k => $v)
        $dataStmt->bindValue($k, $v);
    $dataStmt->bindValue(':limit', $limit, PDO::PARAM_INT);
    $dataStmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $dataStmt->execute();
    $rows = $dataStmt->fetchAll();

    // Get distinct part numbers for UI
    $partsWhere = $hasStatus ? "WHERE status = 'ACTIVE'" : "";
    $partsStmt = $db->query("SELECT DISTINCT part_no FROM electors $partsWhere ORDER BY part_no ASC");
    $parts = $partsStmt->fetchAll(PDO::FETCH_COLUMN);

    echo json_encode([
        'success' => true,
        'total' => $total,
        'page' => $page,
        'limit' => $limit,
        'total_pages' => (int) ceil($total / $limit),
        'parts' => $parts,
        'data' => $rows,
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
