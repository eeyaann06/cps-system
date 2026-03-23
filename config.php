<?php
session_start();

// ─── MySQL Connection Settings ───────────────────────────────────────────────
define('DB_HOST', 'localhost');
define('DB_PORT', '3306');
define('DB_NAME', 'cps');
define('DB_USER', 'root');          
define('DB_PASS', '123456');              
define('DB_CHARSET', 'utf8mb4');

define('APP_NAME', 'cps System');

function getDB() {
    static $pdo = null;
    if ($pdo === null) {
        $dsn = 'mysql:host=' . DB_HOST
             . ';port='    . DB_PORT
             . ';dbname='  . DB_NAME
             . ';charset=' . DB_CHARSET;

        $options = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ];

        try {
            $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
        } catch (PDOException $e) {
            die('<h3 style="color:red;font-family:sans-serif;">Database Connection Failed: '
                . htmlspecialchars($e->getMessage()) . '</h3>');
        }
    }
    return $pdo;
}

// ─── Helpers ─────────────────────────────────────────────────────────────────

function requireLogin() {
    if (!isset($_SESSION['user_id'])) {
        header('Location: index.php');
        exit;
    }
}

function redirect($url, $msg = null, $type = 'success') {
    if ($msg) $_SESSION['flash'] = ['msg' => $msg, 'type' => $type];
    header("Location: $url");
    exit;
}

function flash() {
    if (isset($_SESSION['flash'])) {
        $f = $_SESSION['flash'];
        unset($_SESSION['flash']);
        $cls = $f['type'] === 'success' ? 'flash-success' : 'flash-error';
        echo "<div class='flash $cls'>" . htmlspecialchars($f['msg']) . "</div>";
    }
}

function e($str) {
    return htmlspecialchars($str ?? '', ENT_QUOTES, 'UTF-8');
}