<?php
/**
 * Site Settings API
 * GET  ?action=get          -> returns all settings JSON
 * POST ?action=save         -> saves key/value settings
 * POST ?action=upload       -> uploads an image, returns its URL
 * POST ?action=delete_slide -> deletes a carousel slide image
 */

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'OPTIONS') { http_response_code(204); exit; }

define('DB_HOST', 'localhost');
define('DB_NAME', 'election_db');
define('DB_USER', 'election_app');
define('DB_PASS', 'Veagle@12345');
define('DB_PORT', 3306);
define('UPLOAD_DIR', __DIR__ . '/uploads/');
define('UPLOAD_URL', 'uploads/');

function ok($data = [])  { echo json_encode(['success' => true] + $data, JSON_UNESCAPED_UNICODE); exit; }
function err($msg)       { http_response_code(400); echo json_encode(['success' => false, 'error' => $msg], JSON_UNESCAPED_UNICODE); exit; }

function getDB(): PDO {
    static $pdo = null;
    if ($pdo) return $pdo;
    $dsn = 'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4;port=' . DB_PORT;
    $pdo = new PDO($dsn, DB_USER, DB_PASS, [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4"
    ]);
    return $pdo;
}

function ensureTable(): void {
    getDB()->exec("CREATE TABLE IF NOT EXISTS `site_settings` (
        `skey`    VARCHAR(100) PRIMARY KEY,
        `sval`    MEDIUMTEXT   NOT NULL,
        `updated` TIMESTAMP    DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
}

function getSetting(string $key, string $default = ''): string {
    $st = getDB()->prepare("SELECT sval FROM site_settings WHERE skey = ?");
    $st->execute([$key]);
    $row = $st->fetch();
    return $row ? $row['sval'] : $default;
}

function setSetting(string $key, string $val): void {
    $st = getDB()->prepare("INSERT INTO site_settings (skey, sval) VALUES (?,?)
                            ON DUPLICATE KEY UPDATE sval=VALUES(sval)");
    $st->execute([$key, $val]);
}

$DEFAULTS = [
    'candidate_name'       => 'मा. श्री. सुनीलभाऊ जगताप',
    'constituency_name'    => 'पुणे शिक्षक मतदारसंघ निवडणूक २०२६',
    'preference_number'    => 'पसंती क्र. १',
    'header_left_image'    => 'icon.png',
    'header_right_image'   => 'icon1.png',
    'carousel_slides'      => '[{"src":"images/slide1.jpg","alt":"Campaign Photo 1"},{"src":"images/slide2.jpg","alt":"Campaign Photo 2"},{"src":"images/slide3.jpg","alt":"Campaign Photo 3"}]',
    'carousel_speed_ms'    => '2000',
];

function fixUploadPermissions(): void {
    if (!is_dir(UPLOAD_DIR)) {
        @mkdir(UPLOAD_DIR, 0755, true);
    }
    @chmod(UPLOAD_DIR, 0755);
    $files = @glob(UPLOAD_DIR . '*');
    if (is_array($files)) {
        foreach ($files as $f) {
            if (is_file($f)) {
                @chmod($f, 0644);
            }
        }
    }
}

try {
    ensureTable();
    fixUploadPermissions();
    $action = $_GET['action'] ?? $_POST['action'] ?? 'get';

    if ($action === 'get') {
        $result = [];
        foreach ($DEFAULTS as $key => $default) {
            $val = getSetting($key, $default);
            if ($key === 'carousel_slides') {
                $result[$key] = json_decode($val, true) ?: json_decode($default, true);
            } else {
                $result[$key] = $val;
            }
        }
        ok(['settings' => $result]);
    }

    elseif ($action === 'save') {
        $body = json_decode(file_get_contents('php://input'), true);
        if (!$body) err('Invalid JSON body');
        $allowed = array_keys($DEFAULTS);
        foreach ($body as $key => $val) {
            if (!in_array($key, $allowed, true)) continue;
            setSetting($key, $key === 'carousel_slides' ? json_encode($val, JSON_UNESCAPED_UNICODE) : (string)$val);
        }
        ok(['message' => 'Settings saved']);
    }

    elseif ($action === 'upload') {
        if (!isset($_FILES['image'])) err('No image uploaded');
        $file = $_FILES['image'];
        if ($file['error'] !== UPLOAD_ERR_OK) err('Upload error: '.$file['error']);
        if ($file['size'] > 10*1024*1024)     err('Max file size is 10MB');

        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION) ?: 'jpg');
        $allowedExts = ['jpg','jpeg','png','webp','gif'];
        if (!in_array($ext, $allowedExts, true)) {
            err('Only JPG, PNG, WEBP, GIF allowed');
        }

        if (!is_dir(UPLOAD_DIR)) {
            @mkdir(UPLOAD_DIR, 0755, true);
        }
        @chmod(UPLOAD_DIR, 0755);

        $filename = 'img_'.time().'_'.substr(md5(rand()),0,8).'.'.$ext;
        $dest     = UPLOAD_DIR . $filename;
        if (!move_uploaded_file($file['tmp_name'], $dest)) err('Failed to save file');
        @chmod($dest, 0644);
        ok(['url' => UPLOAD_URL.$filename, 'filename' => $filename]);
    }

    elseif ($action === 'delete_slide') {
        $body = json_decode(file_get_contents('php://input'), true);
        $url  = $body['url'] ?? '';
        if (strpos($url, 'uploads/') !== 0) err('Cannot delete this file');
        $path = __DIR__.'/'.$url;
        if (file_exists($path)) unlink($path);
        ok(['message' => 'Deleted']);
    }

    else { err('Unknown action'); }

} catch (Throwable $e) {
    err('Server error: '.$e->getMessage());
}
