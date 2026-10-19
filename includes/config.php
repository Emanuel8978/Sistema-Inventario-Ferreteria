<?php
// ---------- Configuración de base de datos ----------
// Si necesitas otras credenciales sin tocar este archivo, crea includes/config.local.php
// (está en .gitignore) y define allí las constantes DB_*.
if (file_exists(__DIR__ . '/config.local.php')) {
    require __DIR__ . '/config.local.php';
}
defined('DB_HOST') || define('DB_HOST', 'localhost');
defined('DB_NAME') || define('DB_NAME', 'inventario_ferreteria');
defined('DB_USER') || define('DB_USER', 'root');
defined('DB_PASS') || define('DB_PASS', '');

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

function db() {
    static $pdo = null;
    if ($pdo === null) {
        $dsn = 'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4';
        $pdo = new PDO($dsn, DB_USER, DB_PASS, [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ]);
    }
    return $pdo;
}

// Escapar texto para HTML
function e($v) {
    return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
}

// ---------- Protección CSRF ----------
function csrf_token() {
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf'];
}
function csrf_field() {
    return '<input type="hidden" name="csrf" value="' . e(csrf_token()) . '">';
}
function csrf_check() {
    $ok = isset($_POST['csrf'], $_SESSION['csrf']) && hash_equals($_SESSION['csrf'], $_POST['csrf']);
    if (!$ok) {
        http_response_code(400);
        exit('Token de seguridad inválido. Recarga la página e inténtalo de nuevo.');
    }
}

// ---------- Mensajes flash ----------
function flash($tipo, $texto) {
    $_SESSION['flash'] = ['tipo' => $tipo, 'texto' => $texto];
}
function flash_get() {
    $f = isset($_SESSION['flash']) ? $_SESSION['flash'] : null;
    unset($_SESSION['flash']);
    return $f;
}

// ---------- Utilidades ----------
function soles($n) {
    return 'S/ ' . number_format((float)$n, 2);
}
function opciones_select(array $filas, $campoId, $campoTxt, $sel) {
    $html = '';
    foreach ($filas as $f) {
        $s = ((string)$sel !== '' && (string)$sel === (string)$f[$campoId]) ? ' selected' : '';
        $html .= '<option value="' . e($f[$campoId]) . '"' . $s . '>' . e($f[$campoTxt]) . '</option>';
    }
    return $html;
}

// Longitud y recorte de texto UTF-8 (funciona aunque la extensión mbstring no esté activa)
function texto_len($s) {
    return function_exists('mb_strlen') ? mb_strlen($s, 'UTF-8') : preg_match_all('/./us', (string)$s, $m);
}
function texto_cortar($s, $max) {
    if (function_exists('mb_substr')) return mb_substr($s, 0, $max, 'UTF-8');
    preg_match_all('/./us', (string)$s, $m);
    return implode('', array_slice($m[0], 0, $max));
}
