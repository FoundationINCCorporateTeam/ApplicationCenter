<?php
/**
 * create_admin.php
 * Usage (CLI): php scripts/create_admin.php username password "Full Name" "email@example.com"
 * Example: php scripts/create_admin.php admin "StrongP@ssw0rd" "Admin Name" "admin@example.com"
 *
 * This script:
 * - Reads data/users.json
 * - Adds or updates the provided username with a bcrypt password hash and role "admin"
 * - Writes file atomically with file locking
 *
 * IMPORTANT: Run from your project root so the relative path to data/ is correct.
 */

if (PHP_SAPI !== 'cli') {
    echo "This script should be run from the command line.\n";
    exit(1);
}

// Simple arg handling
if ($argc < 3) {
    echo "Usage: php {$argv[0]} username password [full_name] [email]\n";
    exit(1);
}

$username = trim($argv[1]);
$password = $argv[2];
$fullName = $argc > 3 ? $argv[3] : '';
$email = $argc > 4 ? $argv[4] : '';

if ($username === '' || $password === '') {
    echo "username and password must not be empty.\n";
    exit(1);
}

// Paths (adjust if your layout differs)
$baseDir = dirname(__DIR__); // if running from project root with scripts/ under root
$dataFile = $baseDir . '/data/users.json';

// Ensure data dir and file exist
if (!is_dir(dirname($dataFile))) {
    echo "Data directory not found: " . dirname($dataFile) . PHP_EOL;
    exit(1);
}
if (!file_exists($dataFile)) {
    // initialize empty JSON
    file_put_contents($dataFile, "{}");
    chmod($dataFile, 0640);
}

// Read existing users safely
$contents = @file_get_contents($dataFile);
if ($contents === false) {
    echo "Unable to read users.json\n";
    exit(1);
}
$users = json_decode($contents, true);
if (!is_array($users)) $users = [];

// Create bcrypt hash
$hash = password_hash($password, PASSWORD_BCRYPT);
if ($hash === false) {
    echo "Failed to create password hash\n";
    exit(1);
}

// Build user object
$users[$username] = [
    'username' => $username,
    'password' => $hash,
    'role' => 'admin',
    'name' => $fullName ?: $username,
    'email' => $email ?: ''
];

// Write atomically with lock: write to tmp then rename
$tmp = $dataFile;
$json = json_encode($users, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
if ($json === false) {
    echo "Failed to encode JSON\n";
    exit(1);
}

$fp = fopen($tmp, 'wb');
if (!$fp) {
    echo "Unable to open temp file for writing: $tmp\n";
    exit(1);
}
if (!flock($fp, LOCK_EX)) {
    fclose($fp);
    echo "Unable to lock temp file\n";
    exit(1);
}
fwrite($fp, $json);
fflush($fp);
flock($fp, LOCK_UN);
fclose($fp);
if (!rename($tmp, $dataFile)) {
    @unlink($tmp);
    echo "Failed to rename temp file to users.json\n";
    exit(1);
}

echo "Admin user '{$username}' created/updated successfully.\n";
echo "Login at /helpdesk/dashboard.php with username and the password you supplied.\n";
echo "IMPORTANT: Remove this script after use (rm " . __FILE__ . ").\n";
exit(0);
?>