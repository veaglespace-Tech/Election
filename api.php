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

    // Check if 'status' and 'review' columns exist (schema migration safe)
    $hasStatus = false;
    $hasReview = false;
    try {
        $cols = $db->query("SELECT COLUMN_NAME FROM information_schema.COLUMNS
            WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'electors' AND COLUMN_NAME IN ('status', 'review')")
            ->fetchAll(PDO::FETCH_COLUMN);
        $hasStatus = in_array('status', $cols, true);
        $hasReview = in_array('review', $cols, true);
    } catch (Exception $e) {
        $hasStatus = false;
        $hasReview = false;
    }

    // ── Build WHERE ───────────────────────────────────────────────────────────
    $where = [];
    $params = [];

    if ($hasStatus && $status !== '' && $status !== 'ALL') {
        $where[] = 'status = :status';
        $params[':status'] = $status;
    }

    if ($name !== '') {
        $where[] = 'elector_name LIKE :name';
        $params[':name'] = "%$name%";
    }
    if ($address !== '') {
        $where[] = 'address LIKE :address';
        $params[':address'] = "%$address%";
    }
    if ($institute !== '') {
        $where[] = 'institute LIKE :inst';
        $params[':inst'] = "%$institute%";
    }
    if ($part_no !== '') {
        $where[] = 'part_no = :part_no';
        $params[':part_no'] = $part_no;
    }
    if ($gender !== '') {
        $where[] = 'gender = :gender';
        $params[':gender'] = $gender;
    }

    $whereSQL = count($where) ? 'WHERE ' . implode(' AND ', $where) : '';

    // Count
    $countStmt = $db->prepare("SELECT COUNT(*) FROM electors $whereSQL");
    $countStmt->execute($params);
    $total = (int) $countStmt->fetchColumn();

    $statusSel = $hasStatus ? ', status' : ", 'ACTIVE' AS status";
    $reviewSel = $hasReview ? ', review' : ", '' AS review";

    // Data — ordered by part_no, sr_no
    $dataStmt = $db->prepare(
        "SELECT id, part_no, sr_no, elector_name, relative_name, address, institute, age, gender, epic_no{$statusSel}{$reviewSel}
         FROM electors
         $whereSQL
         ORDER BY part_no ASC, sr_no ASC
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
