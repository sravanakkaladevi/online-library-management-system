<?php
include('includes/config.php');

try {
    $dbh->beginTransaction();
    
    // Add OrderType column if missing
    $checkSql = "SHOW COLUMNS FROM tblorders LIKE 'OrderType'";
    $checkQuery = $dbh->prepare($checkSql);
    $checkQuery->execute();
    
    if($checkQuery->rowCount() === 0) {
        echo "Adding OrderType column...\n";
        $sql1 = "ALTER TABLE tblorders ADD COLUMN OrderType ENUM('buy','read_online') DEFAULT 'buy' AFTER OrderNumber";
        $dbh->exec($sql1);
        echo "✓ OrderType column added\n";
    } else {
        echo "✓ OrderType column already exists\n";
    }
    
    // Create tblreadonlineaccess table if missing
    $checkTable = "SHOW TABLES LIKE 'tblreadonlineaccess'";
    $checkTableQuery = $dbh->prepare($checkTable);
    $checkTableQuery->execute();
    
    if($checkTableQuery->rowCount() === 0) {
        echo "Creating tblreadonlineaccess table...\n";
        $sql2 = "CREATE TABLE tblreadonlineaccess (
            id INT AUTO_INCREMENT PRIMARY KEY,
            OrderId INT NOT NULL,
            BookId INT NOT NULL,
            PdfLink VARCHAR(500),
            ExpiryDate DATE,
            CreatedDate TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (OrderId) REFERENCES tblorders(id),
            FOREIGN KEY (BookId) REFERENCES tblbooks(id)
        )";
        $dbh->exec($sql2);
        echo "✓ tblreadonlineaccess table created\n";
    } else {
        echo "✓ tblreadonlineaccess table already exists\n";
    }
    
    $dbh->commit();
    echo "\n✓ Database updated successfully!\n";
    
} catch(Exception $e) {
    $dbh->rollBack();
    echo "✗ Error: " . $e->getMessage() . "\n";
    exit(1);
}
?>
