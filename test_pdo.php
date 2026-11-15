<?php
// Test PDO connection
$pdo = new PDO("mysql:host=127.0.0.1;dbname=lcct_guidance_v2_test", "root", "");
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$pdo->exec("SET time_zone = '" . date('P') . "'");
$stmt = $pdo->query("SELECT 1");
$result = $stmt->fetch();
echo "PDO works: " . ($result[0] == 1 ? "YES" : "NO") . "\n";

// Also test with the app's DB config
define('DB_HOST', '127.0.0.1');
define('DB_PORT', 3306);
define('DB_NAME', 'lcct_guidance_v2_test');
define('DB_USER', 'root');
define('DB_PASS', '');

try {
    $pdo2 = new PDO("mysql:host=" . DB_HOST . ";port=" . DB_PORT . ";dbname=" . DB_NAME, DB_USER, DB_PASS, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);
    $pdo2->exec("SET time_zone = '" . date('P') . "'");
    $stmt2 = $pdo2->query("SELECT 1");
    $result2 = $stmt2->fetch();
    echo "PDO2 works: " . ($result2[0] == 1 ? "YES" : "NO") . "\n";
    echo "Connection OK!\n";
} catch (PDOException $e) {
    echo "PDO2 failed: " . $e->getMessage() . "\n";
}