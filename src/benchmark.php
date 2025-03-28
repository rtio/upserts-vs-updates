<?php
$pdo = new PDO('mysql:host=mysql;dbname=benchmark;charset=utf8mb4', 'root', 'secret');

function analyzeResults($results) {
    echo "\n=== Benchmark Analysis ===\n\n";
    
    // Calculate statistics
    $updateTimes = array_column($results, 'update');
    $upsertTimes = array_column($results, 'upsert');
    
    // Calculate averages
    $avgUpdate = array_sum($updateTimes) / count($updateTimes);
    $avgUpsert = array_sum($upsertTimes) / count($upsertTimes);
    
    // Calculate speedup
    $speedup = $avgUpdate / $avgUpsert;
    
    echo "Performance Summary:\n";
    echo "-------------------\n";
    echo "Average UPDATE time: " . number_format($avgUpdate, 6) . "s\n";
    echo "Average UPSERT time: " . number_format($avgUpsert, 6) . "s\n";
    echo "UPSERT is " . number_format($speedup, 2) . "x faster than UPDATE\n\n";
    
    echo "Detailed Results:\n";
    echo "----------------\n";
    foreach ($results as $result) {
        echo "Batch size: {$result['batch_size']}\n";
        echo "  UPDATE: " . number_format($result['update'], 6) . "s\n";
        echo "  UPSERT: " . number_format($result['upsert'], 6) . "s\n";
        echo "  Speedup: " . number_format($result['update'] / $result['upsert'], 2) . "x\n\n";
    }
    
    // Calculate scaling analysis
    echo "Scaling Analysis:\n";
    echo "----------------\n";
    for ($i = 1; $i < count($results); $i++) {
        $batchSizeRatio = $results[$i]['batch_size'] / $results[$i-1]['batch_size'];
        $updateTimeRatio = $results[$i]['update'] / $results[$i-1]['update'];
        $upsertTimeRatio = $results[$i]['upsert'] / $results[$i-1]['upsert'];
        
        echo "Batch size {$results[$i-1]['batch_size']} → {$results[$i]['batch_size']}:\n";
        echo "  UPDATE scaling: " . number_format($updateTimeRatio, 2) . "x (ideal: " . number_format($batchSizeRatio, 2) . "x)\n";
        echo "  UPSERT scaling: " . number_format($upsertTimeRatio, 2) . "x (ideal: " . number_format($batchSizeRatio, 2) . "x)\n\n";
    }
}

$sizes = [100, 1000, 5000, 10000, 20000];
$results = [];

foreach ($sizes as $size) {
    echo "Running benchmark for batch size: $size\n";

    // Prepare batch data (80% existing, 20% new)
    $existingCount = (int)($size * 0.8);
    $newCount = $size - $existingCount;

    $data = [];
    for ($i = 1; $i <= $existingCount; $i++) {
        $id = rand(1, 100000);
        $data[] = [$id, "Updated User $id", "updated{$id}@example.com", rand(1001, 2000)];
    }
    for ($i = 100001; $i < 100001 + $newCount; $i++) {
        $data[] = [$i, "New User $i", "new{$i}@example.com", rand(0, 1000)];
    }

    // Bulk UPDATE (existing only)
    $ids = implode(',', array_map(fn($row) => $row[0], array_slice($data, 0, $existingCount)));
    $caseName = '';
    $caseScore = '';

    foreach (array_slice($data, 0, $existingCount) as $row) {
        $caseName .= "WHEN id={$row[0]} THEN '{$row[1]}' ";
        $caseScore .= "WHEN id={$row[0]} THEN {$row[3]} ";
    }

    $updateSQL = "UPDATE users SET
                    name = CASE $caseName ELSE name END,
                    score = CASE $caseScore ELSE score END
                  WHERE id IN ($ids)";

    $start = microtime(true);
    $pdo->exec($updateSQL);
    $updateTime = microtime(true) - $start;
    echo "UPDATE time: {$updateTime}s\n";

    // UPSERT (INSERT ON DUPLICATE KEY UPDATE)
    $chunks = array_chunk($data, 500);
    $start = microtime(true);
    foreach ($chunks as $chunk) {
        $values = implode(',', array_map(function ($row) {
            return "({$row[0]}, '{$row[1]}', '{$row[2]}', {$row[3]})";
        }, $chunk));

        $upsertSQL = "INSERT INTO users (id, name, email, score) VALUES $values
                      ON DUPLICATE KEY UPDATE name=VALUES(name), score=VALUES(score)";
        $pdo->exec($upsertSQL);
    }
    $upsertTime = microtime(true) - $start;
    echo "UPSERT time: {$upsertTime}s\n\n";

    $results[] = ['batch_size' => $size, 'update' => $updateTime, 'upsert' => $upsertTime];
}

// Save results
file_put_contents('results/results.json', json_encode($results));
echo "Benchmarks completed. Results saved to results/results.json\n";

// Analyze and display detailed results
analyzeResults($results);
