<?php
// Simulate what app.php does
putenv('LCCT_SYNTHETIC_TEST');
putenv('LCCT_DB_HOST');
putenv('LCCT_DB_PORT');
putenv('LCCT_DB_NAME');
putenv('LCCT_DB_USER');
putenv('LCCT_DB_PASSWORD');

$local = is_file(__DIR__.'/config/local.php') ? require __DIR__.'/config/local.php' : [];
if (!is_array($local)) { die('Local configuration must return an array.'); }
if (!empty($local['SYNTHETIC_TEST'])) putenv('LCCT_SYNTHETIC_TEST=1');
define('DB_PORT', (int)($local['DB_PORT'] ?? getenv('LCCT_DB_PORT') ?: 3306));
define('DB_HOST', $local['DB_HOST'] ?? getenv('LCCT_DB_HOST') ?: '127.0.0.1');
define('DB_NAME', $local['DB_NAME'] ?? getenv('LCCT_DB_NAME') ?: 'lcct_guidance_v2_test');
define('DB_USER', $local['DB_USER'] ?? getenv('LCCT_DB_USER') ?: 'lcct_guidance_dev');
define('DB_PASS', $local['DB_PASS'] ?? getenv('LCCT_DB_PASSWORD') ?: '');

// Check synthetic test condition
if (getenv('LCCT_SYNTHETIC_TEST')==='1' && (!in_array(DB_HOST,['127.0.0.1','localhost','::1'],true)||!str_ends_with(DB_NAME,'_test'))) {
    exit('Synthetic mode requires a local _test database.');
}

// Try PDO connection
try {
    $pdo = new PDO('mysql:host=' . DB_HOST . ';port=' . DB_PORT . ';dbname=' . DB_NAME . ';charset=utf8mb4', DB_USER, DB_PASS, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);
    $pdo->exec("SET time_zone = '" . date('P') . "'");
    echo "PDO Connection SUCCESS\n";
} catch (PDOException $ex) {
    echo "PDO Connection FAILED: " . $ex->getMessage() . "\n";
    echo "errno: " . $ex->getCode() . "\n";
}