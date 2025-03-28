<?php
$pdo = new PDO('mysql:host=mysql;dbname=benchmark;charset=utf8mb4', 'root', 'secret');
$pdo->exec('TRUNCATE TABLE users');

$pdo->beginTransaction();
$stmt = $pdo->prepare("INSERT INTO users (id, name, email, score) VALUES (?, ?, ?, ?)");

for ($i = 1; $i <= 100000; $i++) {
    $stmt->execute([
        $i,
        "User $i",
        "user{$i}@example.com",
        rand(0, 1000)
    ]);

    if ($i % 5000 === 0) {
        $pdo->commit();
        $pdo->beginTransaction();
    }
}
$pdo->commit();

echo "Initial data generated (100,000 records)\n";
