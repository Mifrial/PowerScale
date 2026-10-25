<?php

declare(strict_types=1);

$root = dirname(__DIR__);
$autoload = $root . '/vendor/autoload.php';
if (is_file($autoload)) {
    require_once $autoload;
}

$configFile = $root . '/config/test.php';
if (!is_file($configFile)) {
    return;
}

$config = require $configFile;
$db = $config['db'] ?? null;
if (!is_array($db)) {
    return;
}

$portEnv = getenv('MIFRIAL_TEST_DB_PORT');
if (is_string($portEnv) && ctype_digit($portEnv)) {
    $db['port'] = (int) $portEnv;
}

$host = (string) ($db['host'] ?? '127.0.0.1');
$port = (int) ($db['port'] ?? 3306);
$database = (string) ($db['database'] ?? '');
$username = (string) ($db['username'] ?? '');
$password = (string) ($db['password'] ?? '');
$charset = (string) ($db['charset'] ?? 'utf8mb4');
if ($database === '') {
    return;
}

$dsn = sprintf('mysql:host=%s;port=%d;dbname=%s;charset=%s', $host, $port, $database, $charset);
try {
    $pdo = new PDO($dsn, $username, $password, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_TIMEOUT => 2,
    ]);
} catch (PDOException $exception) {
    // MySQL недоступен: unit-тесты должны работать и без него.
    return;
}

$tableNames = $pdo->query(
    "SELECT table_name FROM information_schema.tables "
    . "WHERE table_schema = DATABASE() AND table_type = 'BASE TABLE'",
)->fetchAll(PDO::FETCH_COLUMN);

$pdo->exec('SET FOREIGN_KEY_CHECKS=0');
try {
    foreach ($tableNames as $tableName) {
        if (!is_string($tableName) || $tableName === '') {
            continue;
        }

        $quoted = '`' . str_replace('`', '``', $tableName) . '`';
        $pdo->exec('DROP TABLE IF EXISTS ' . $quoted);
    }
} finally {
    $pdo->exec('SET FOREIGN_KEY_CHECKS=1');
}
