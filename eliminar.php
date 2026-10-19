<?php
require_once __DIR__ . '/includes/config.php';
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { header('Location: index.php'); exit; }
csrf_check();
$id = (int)(isset($_POST['id']) ? $_POST['id'] : 0);
if ($id > 0) {
    // Baja lógica: se conserva el historial de movimientos
    db()->prepare('UPDATE productos SET activo = 0 WHERE producto_id = ?')->execute([$id]);
    flash('ok', 'Producto desactivado.');
}
header('Location: index.php');
