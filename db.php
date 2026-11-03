
<?php

$host = "localhost";
$port = "5432";
$dbname = "postgres";
$username = "app_user";
$password = "loki";

try {
    $pdo = new PDO(
        "pgsql:host=$host;port=$port;dbname=$dbname",
        $username,
        $password,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
        ]
    );

} catch (PDOException $e) {
    error_log($e->getMessage());
    die("Unable to connect to the database. Please check your connection settings.");
}
