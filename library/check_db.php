<?php
include('includes/config.php');
try {
    $sql = 'DESCRIBE tblorders';
    $query = $dbh->prepare($sql);
    $query->execute();
    $columns = $query->fetchAll(PDO::FETCH_ASSOC);
    echo "Current tblorders columns:\n";
    foreach($columns as $col) {
        echo '  - ' . $col['Field'] . ' (' . $col['Type'] . ")\n";
    }
    $hasOrderType = false;
    foreach($columns as $col) {
        if($col['Field'] === 'OrderType') {
            $hasOrderType = true;
            break;
        }
    }
    echo "\n" . ($hasOrderType ? '✓ OrderType column EXISTS' : '✗ OrderType column MISSING - Need to add it') . "\n";
} catch(Exception $e) {
    echo 'Error: ' . $e->getMessage();
}
?>
