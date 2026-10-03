<?php
$pdo = new PDO('mysql:host=localhost;dbname=election_db;charset=utf8mb4', 'election_app', 'Veagle@12345');
$stmt = $pdo->query("SELECT elector_name, COUNT(*) as cnt FROM electors GROUP BY elector_name, part_no, sr_no HAVING cnt > 1");
$dups = $stmt->fetchAll(PDO::FETCH_ASSOC);
echo "Exact duplicates based on Name + Part + Sr No:\n";
print_r($dups);

$stmt = $pdo->query("SELECT * FROM electors WHERE elector_name = '' OR elector_name IS NULL");
$empty = $stmt->fetchAll(PDO::FETCH_ASSOC);
echo "\nRows with empty names:\n";
print_r($empty);
