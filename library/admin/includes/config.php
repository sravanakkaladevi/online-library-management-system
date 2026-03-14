<?php 
// DB credentials.
define('DB_HOST','localhost');
define('DB_USER','root');
define('DB_PASS','8309484956');
define('DB_NAME','library');
// Establish database connection.
if (!extension_loaded('pdo_mysql')) {
exit('Database driver error: The pdo_mysql extension is not enabled. Enable it in php.ini and restart PHP.');
}

$dsn = "mysql:host=".DB_HOST.";dbname=".DB_NAME.";charset=utf8mb4";
$options = array(
PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
PDO::ATTR_EMULATE_PREPARES => false,
);

try
{
$dbh = new PDO($dsn, DB_USER, DB_PASS, $options);
}
catch (PDOException $e)
{
exit("Database connection failed: " . $e->getMessage());
}
?>
