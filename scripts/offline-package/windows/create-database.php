<?php
// ينشئ قاعدة بيانات MySQL لو مش موجودة — يستخدمه INSTALL.bat.
// php create-database.php <host> <port> <database> <user> [password]
[$_, $host, $port, $db, $user] = array_pad($argv, 5, '');
$pass = $argv[5] ?? '';
if (! preg_match('/^[A-Za-z0-9_]+$/', $db)) {
    fwrite(STDERR, "Invalid database name: {$db}\n");
    exit(1);
}
try {
    $pdo = new PDO("mysql:host={$host};port={$port}", $user, $pass, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
    $pdo->exec("CREATE DATABASE IF NOT EXISTS `{$db}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    echo "Database ready: {$db}\n";
} catch (Throwable $e) {
    fwrite(STDERR, 'Could not create database: '.$e->getMessage()."\nIs MySQL running in Laragon?\n");
    exit(1);
}
