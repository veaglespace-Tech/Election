<?php
/**
 * Server-Side Slip & ZIP Generator API
 * Handles:
 *  - action=part_zip&part_no=X (Generates a clean printable multi-slip HTML / ZIP directly on server)
 *  - action=start_batch (Starts a background job to build ZIP archives on server)
 *  - action=batch_status (Checks status of background ZIP generation)
 *  - action=download_file&file=XYZ.zip (Downloads ready ZIP)
 */

declare(strict_types=1);
error_reporting(E_ALL);
ini_set('display_errors', '0');
ini_set('memory_limit', '512M');
set_time_limit(600);

header('Access-Control-Allow-Origin: *');

// DB config matching api.php
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

$action = $_GET['action'] ?? $_POST['action'] ?? '';
$outputDir = __DIR__ . '/uploads/slips_export';
if (!is_dir($outputDir)) {
    @mkdir($outputDir, 0777, true);
}

// ─────────────────────────────────────────────────────────────────────────────
// 1. ACTION: get_parts - Get parts list with count of electors
// ─────────────────────────────────────────────────────────────────────────────
if ($action === 'get_parts') {
    header('Content-Type: application/json; charset=utf-8');
    try {
        $db = getDB();
        $stmt = $db->query("
            SELECT part_no, COUNT(*) as total_voters
            FROM electors
            GROUP BY part_no
            ORDER BY CAST(part_no AS UNSIGNED) ASC, part_no ASC
        ");
        $parts = $stmt->fetchAll();
        echo json_encode(['success' => true, 'parts' => $parts], JSON_UNESCAPED_UNICODE);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
    exit;
}

// ─────────────────────────────────────────────────────────────────────────────
// Function: Generate Printable A4 Slips HTML
// ─────────────────────────────────────────────────────────────────────────────
function generatePartSlipsHtmlContent(string $part_no, array $rows): string
{
    ob_start();
    ?>
    <!DOCTYPE html>
    <html lang="mr">
    <head>
      <meta charset="utf-8" />
      <title>Voter Slips - Part No <?= htmlspecialchars($part_no) ?> (<?= count($rows) ?> Electors)</title>
      <style>
        @import url('https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700;800&family=Noto+Sans+Devanagari:wght@400;600;700;800&display=swap');
        * { box-sizing: border-box; -webkit-print-color-adjust: exact; print-color-adjust: exact; }
        body { margin: 0; padding: 12px; font-family: 'Noto Sans Devanagari', 'Inter', sans-serif; background: #525659; }
        .no-print-bar {
          position: sticky; top: 0; background: #1e293b; color: #fff; padding: 12px 20px;
          display: flex; justify-content: space-between; align-items: center; border-radius: 8px;
          margin-bottom: 20px; box-shadow: 0 4px 12px rgba(0,0,0,0.25); z-index: 1000;
        }
        .no-print-bar button {
          background: #ea580c; color: #fff; border: none; padding: 8px 18px; border-radius: 6px;
          font-size: 14px; font-weight: 700; cursor: pointer; display: flex; align-items: center; gap: 6px;
        }
        .no-print-bar button:hover { background: #c2410c; }
        .slip-grid {
          display: flex; flex-direction: column; gap: 14px; max-width: 820px; margin: 0 auto;
        }
        .slip-card {
          background: #fff; border: 1.5px solid #0f172a; border-radius: 10px; overflow: hidden;
          page-break-inside: avoid; break-inside: avoid; margin-bottom: 12px;
        }
        .slip-banner { width: 100%; height: auto; display: block; border-bottom: 1px solid #e2e8f0; }
        .slip-header {
          background: #0f172a; color: #fff; padding: 8px 14px; display: flex; justify-content: space-between; align-items: center;
          font-weight: 800; font-size: 13px;
        }
        .slip-body { padding: 12px 16px; }
        .elector-name { font-size: 18px; font-weight: 800; color: #0f172a; text-transform: uppercase; margin-bottom: 8px; }
        .meta-box {
          display: flex; gap: 10px; background: #f8fafc; border: 1px solid #e2e8f0; padding: 8px 12px; border-radius: 6px;
          font-size: 12px; margin-bottom: 8px; flex-wrap: wrap;
        }
        .meta-item { flex: 1; min-width: 120px; }
        .meta-label { color: #64748b; font-size: 10px; font-weight: 700; text-transform: uppercase; }
        .meta-val { font-weight: 700; color: #1e293b; margin-top: 2px; }
        .info-line { font-size: 12px; color: #334155; margin-bottom: 6px; line-height: 1.4; }
        .station-box {
          background: #f0fdf4; border: 1px solid #86efac; border-radius: 6px; padding: 8px 12px; font-size: 12px;
          color: #166534; font-weight: 700; line-height: 1.4; margin-top: 6px;
        }
        .slip-footer {
          background: #fef3c7; border-top: 1px solid #fcd34d; padding: 6px; text-align: center; font-size: 11px;
          font-weight: 800; color: #92400e;
        }
        @media print {
          body { background: #fff; padding: 0; }
          .no-print-bar { display: none !important; }
          .slip-grid { max-width: 100%; margin: 0; }
          .slip-card { border: 1px solid #333; margin-bottom: 16px; box-shadow: none; }
        }
      </style>
    </head>
    <body>
      <div class="no-print-bar">
        <div>
          <strong>📦 Part No <?= htmlspecialchars($part_no) ?> Voter Slips</strong>
          <span style="font-size:12px;opacity:0.85;margin-left:10px;">Total <?= count($rows) ?> Electors Ready to Print</span>
        </div>
        <div style="display:flex;gap:10px;">
          <button onclick="window.print()">🖨️ Print / Save as PDF</button>
        </div>
      </div>

      <div class="slip-grid">
        <?php foreach ($rows as $r): 
            $bldg = $r['station_building'] ?? $r['building'] ?? '';
            $genderTxt = ($r['gender'] === 'M') ? 'Male (पुरुष)' : (($r['gender'] === 'F') ? 'Female (स्त्री)' : $r['gender']);
        ?>
          <div class="slip-card">
            <img src="images/img1_banner.jpg" class="slip-banner" onerror="this.src='images/img1.png'" alt="Campaign Banner" />
            <div class="slip-header">
              <span>भाग क्र. <?= htmlspecialchars((string)$r['part_no']) ?> (PT <?= htmlspecialchars((string)$r['part_no']) ?>) &nbsp;|&nbsp; अनु. क्र. <?= htmlspecialchars((string)$r['sr_no']) ?> (SR <?= htmlspecialchars((string)$r['sr_no']) ?>)</span>
              <span style="background:rgba(255,255,255,0.2);padding:2px 8px;border-radius:12px;font-size:11px;">मतदार यादी २०२६</span>
            </div>
            <div class="slip-body">
              <div class="elector-name"><?= htmlspecialchars((string)$r['elector_name']) ?></div>
              <div class="meta-box">
                <div class="meta-item" style="flex:2;">
                  <div class="meta-label">नातेवाईक / Relative Name</div>
                  <div class="meta-val"><?= htmlspecialchars((string)($r['relative_name'] ?: '—')) ?></div>
                </div>
                <div class="meta-item" style="max-width:70px;text-align:center;">
                  <div class="meta-label">वय / Age</div>
                  <div class="meta-val" style="color:#ea580c;font-size:14px;"><?= htmlspecialchars((string)($r['age'] ?: '—')) ?></div>
                </div>
                <div class="meta-item" style="max-width:100px;">
                  <div class="meta-label">लिंग / Gender</div>
                  <div class="meta-val"><?= htmlspecialchars((string)$genderTxt) ?></div>
                </div>
                <div class="meta-item" style="max-width:130px;">
                  <div class="meta-label">EPIC NO</div>
                  <div class="meta-val" style="font-family:monospace;"><?= htmlspecialchars((string)($r['epic_no'] ?: '—')) ?></div>
                </div>
              </div>
              <?php if (!empty($r['address'])): ?>
                <div class="info-line"><strong>📍 पत्ता:</strong> <?= htmlspecialchars((string)$r['address']) ?></div>
              <?php endif; ?>
              <?php if (!empty($r['institute'])): ?>
                <div class="info-line"><strong>🎓 संस्था:</strong> <?= htmlspecialchars((string)$r['institute']) ?></div>
              <?php endif; ?>
              <?php if (!empty($bldg)): ?>
                <div class="station-box">
                  🏛️ <strong>मतदान केंद्र / POLLING STATION:</strong> <?= htmlspecialchars((string)$bldg) ?>
                </div>
              <?php endif; ?>
            </div>
            <div class="slip-footer">
              मतदान: शुक्रवार दि. २३ ऑक्टोबर २०२६ &nbsp;|&nbsp; वेळ: सकाळी ०८:०० ते दुपारी ०४:०० वा.
            </div>
          </div>
        <?php endforeach; ?>
      </div>
    </body>
    </html>
    <?php
    return ob_get_clean();
}

// ─────────────────────────────────────────────────────────────────────────────
// 2. ACTION: print_part_slips - Generate Printable A4 Slips (3 slips per page)
// ─────────────────────────────────────────────────────────────────────────────
if ($action === 'print_part_slips') {
    $part_no = trim($_GET['part_no'] ?? '');
    if ($part_no === '') {
        die('Part No is required.');
    }

    try {
        $db = getDB();
        // Check building column & polling_stations table safely
        $hasBuilding = false;
        $hasPsTable = false;
        try {
            $cols = $db->query("SELECT COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'electors' AND COLUMN_NAME = 'building'")->fetchAll();
            $hasBuilding = count($cols) > 0;
            $checkPs = $db->query("SHOW TABLES LIKE 'polling_stations'")->fetchAll();
            $hasPsTable = count($checkPs) > 0;
        } catch (Exception $e) {}

        if ($hasBuilding && $hasPsTable) {
            $sql = "SELECT e.*, COALESCE(NULLIF(e.building, ''), ps.building, '') AS station_building
                    FROM electors e
                    LEFT JOIN polling_stations ps ON ps.station_no = e.part_no
                    WHERE e.part_no = :part
                    ORDER BY CAST(e.sr_no AS UNSIGNED) ASC, e.sr_no ASC";
        } elseif ($hasPsTable) {
            $sql = "SELECT e.*, COALESCE(ps.building, '') AS station_building
                    FROM electors e
                    LEFT JOIN polling_stations ps ON ps.station_no = e.part_no
                    WHERE e.part_no = :part
                    ORDER BY CAST(e.sr_no AS UNSIGNED) ASC, e.sr_no ASC";
        } else {
            $sql = "SELECT e.*, '' AS station_building
                    FROM electors e
                    WHERE e.part_no = :part
                    ORDER BY CAST(e.sr_no AS UNSIGNED) ASC, e.sr_no ASC";
        }

        $stmt = $db->prepare($sql);
        $stmt->execute([':part' => $part_no]);
        $rows = $stmt->fetchAll();

        if (empty($rows)) {
            die("No electors found for Part No {$part_no}.");
        }

        header('Content-Type: text/html; charset=utf-8');
        echo generatePartSlipsHtmlContent($part_no, $rows);
        exit;
    } catch (Exception $e) {
        die('Error generating slips: ' . $e->getMessage());
    }
}

// ─────────────────────────────────────────────────────────────────────────────
// 3. ACTION: start_server_zip - Builds full or part ZIP archives of ACTUAL SLIPS
// ─────────────────────────────────────────────────────────────────────────────
if ($action === 'start_server_zip') {
    header('Content-Type: application/json; charset=utf-8');
    $scope = $_POST['scope'] ?? 'part'; // 'part' or 'all'
    $part_no = trim($_POST['part_no'] ?? '');

    $jobId = 'job_' . time() . '_' . rand(1000, 9999);
    $statusFile = $outputDir . '/' . $jobId . '.json';

    $initData = [
        'job_id' => $jobId,
        'scope' => $scope,
        'part_no' => $part_no,
        'status' => 'processing',
        'progress' => 0,
        'total' => 0,
        'processed' => 0,
        'file_url' => '',
        'file_name' => '',
        'message' => 'Initializing voter slips export...',
        'created_at' => date('Y-m-d H:i:s')
    ];
    file_put_contents($statusFile, json_encode($initData, JSON_PRETTY_PRINT));

    try {
        $db = getDB();
        if ($scope === 'part' && $part_no !== '') {
            $stmtCount = $db->prepare("SELECT COUNT(*) FROM electors WHERE part_no = :part");
            $stmtCount->execute([':part' => $part_no]);
            $totalCount = (int)$stmtCount->fetchColumn();
            
            $downloadUrl = "slips_api.php?action=print_part_slips&part_no=" . urlencode($part_no);
            
            $initData['status'] = 'completed';
            $initData['progress'] = 100;
            $initData['total'] = $totalCount;
            $initData['processed'] = $totalCount;
            $initData['file_url'] = $downloadUrl;
            $initData['file_name'] = "Part_{$part_no}_Voter_Slips.html";
            $initData['message'] = "Part {$part_no} ({$totalCount} voters) ready for instant download / print!";
            file_put_contents($statusFile, json_encode($initData, JSON_PRETTY_PRINT));

            echo json_encode(['success' => true, 'job' => $initData]);
            exit;
        } else {
            // All database
            $stmtCount = $db->query("SELECT COUNT(*) FROM electors");
            $totalCount = (int)$stmtCount->fetchColumn();

            if (!class_exists('ZipArchive')) {
                throw new Exception('PHP ZipArchive extension is not enabled on server.');
            }

            $zipFileName = "All_62099_Voter_Slips_Archive_" . date('Ymd_His') . ".zip";
            $zipFilePath = $outputDir . '/' . $zipFileName;

            $zip = new ZipArchive();
            if ($zip->open($zipFilePath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== TRUE) {
                throw new Exception("Cannot open file <$zipFilePath>\n");
            }

            // Fetch distinct parts and package ACTUAL formatted printable voter slips inside ZIP
            $partsStmt = $db->query("SELECT DISTINCT part_no FROM electors ORDER BY CAST(part_no AS UNSIGNED) ASC");
            $allParts = $partsStmt->fetchAll(PDO::FETCH_COLUMN);

            $processed = 0;
            foreach ($allParts as $p) {
                $pDataStmt = $db->prepare("
                    SELECT e.*, COALESCE(ps.building, '') AS station_building
                    FROM electors e
                    LEFT JOIN polling_stations ps ON ps.station_no = e.part_no
                    WHERE e.part_no = :part 
                    ORDER BY CAST(e.sr_no AS UNSIGNED) ASC
                ");
                $pDataStmt->execute([':part' => $p]);
                $pRows = $pDataStmt->fetchAll();

                // 1. Generate the actual formatted printable HTML slips for this Part
                $partSlipsHtml = generatePartSlipsHtmlContent((string)$p, $pRows);
                $zip->addFromString("Part_{$p}_Voter_Slips.html", $partSlipsHtml);

                $processed += count($pRows);

                // Update status
                $pct = min(99, (int)(($processed / max(1, $totalCount)) * 100));
                $initData['progress'] = $pct;
                $initData['processed'] = $processed;
                $initData['total'] = $totalCount;
                $initData['message'] = "Packaging Part {$p} slips ({$processed} of {$totalCount} processed)...";
                file_put_contents($statusFile, json_encode($initData, JSON_PRETTY_PRINT));
            }

            // Also add full campaign banner image into root of ZIP
            $bannerPath = __DIR__ . '/images/img1_banner.jpg';
            if (file_exists($bannerPath)) {
                $zip->addFile($bannerPath, 'campaign_banner.jpg');
            }

            $zip->close();

            $initData['status'] = 'completed';
            $initData['progress'] = 100;
            $initData['processed'] = $totalCount;
            $initData['total'] = $totalCount;
            $initData['file_url'] = "slips_api.php?action=download_zip&file=" . urlencode($zipFileName);
            $initData['file_name'] = $zipFileName;
            $initData['message'] = "All {$totalCount} voter slips generated and packaged into ZIP!";
            file_put_contents($statusFile, json_encode($initData, JSON_PRETTY_PRINT));

            echo json_encode(['success' => true, 'job' => $initData]);
            exit;
        }
    } catch (Exception $e) {
        $initData['status'] = 'failed';
        $initData['message'] = $e->getMessage();
        file_put_contents($statusFile, json_encode($initData, JSON_PRETTY_PRINT));
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        exit;
    }
}

// ─────────────────────────────────────────────────────────────────────────────
// 3.1 ACTION: download_zip - Stream ZIP file directly via PHP (Bypasses Nginx 404)
// ─────────────────────────────────────────────────────────────────────────────
if ($action === 'download_zip') {
    $file = basename($_GET['file'] ?? '');
    if (!$file || !preg_match('/^[a-zA-Z0-9_\-]+\.zip$/', $file)) {
        http_response_code(400);
        die('Invalid ZIP file request.');
    }

    $filePath = $outputDir . '/' . $file;
    if (!file_exists($filePath)) {
        // Also check if file exists directly in uploads
        $altPath = __DIR__ . '/uploads/' . $file;
        if (file_exists($altPath)) {
            $filePath = $altPath;
        } else {
            http_response_code(404);
            die("File not found on server ($file). Please generate again.");
        }
    }

    // Clear output buffering
    if (ob_get_level()) {
        ob_end_clean();
    }

    header('Content-Description: File Transfer');
    header('Content-Type: application/zip');
    header('Content-Disposition: attachment; filename="' . $file . '"');
    header('Expires: 0');
    header('Cache-Control: must-revalidate');
    header('Pragma: public');
    header('Content-Length: ' . filesize($filePath));
    readfile($filePath);
    exit;
}

// ─────────────────────────────────────────────────────────────────────────────
// 4. ACTION: check_job_status
// ─────────────────────────────────────────────────────────────────────────────
if ($action === 'check_job_status') {
    header('Content-Type: application/json; charset=utf-8');
    $jobId = preg_replace('/[^a-zA-Z0-9_]/', '', $_GET['job_id'] ?? '');
    $statusFile = $outputDir . '/' . $jobId . '.json';
    if (!file_exists($statusFile)) {
        echo json_encode(['success' => false, 'error' => 'Job not found']);
        exit;
    }
    $data = json_decode(file_get_contents($statusFile), true);
    echo json_encode(['success' => true, 'job' => $data]);
    exit;
}

echo json_encode(['success' => false, 'error' => 'Invalid action']);
exit;
