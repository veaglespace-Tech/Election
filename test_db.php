<?php
try {
    $pdo = new PDO('mysql:host=127.0.0.1;port=3306', 'root', 'Veagle@123');
    echo "Root works!\n";
    $stmt = $pdo->query("SHOW DATABASES");
    $dbs = $stmt->fetchAll(PDO::FETCH_COLUMN);
    print_r($dbs);
} catch (Exception $e) {
    echo "Root failed: " . $e->getMessage() . "\n";
}
