<?php
// Búsqueda de producto por código de barras (usado por movimiento.php)
require_once __DIR__ . '/includes/config.php';
header('Content-Type: application/json; charset=utf-8');

$codigo = strtoupper(trim(isset($_GET['codigo']) ? $_GET['codigo'] : ''));
$alm    = (int)(isset($_GET['almacen_id']) ? $_GET['almacen_id'] : 0);
if ($codigo === '') { echo json_encode(['ok' => false, 'error' => 'Código vacío']); exit; }

$st = db()->prepare('SELECT producto_id, codigo_sku, nombre, unidad_medida, precio_venta FROM productos WHERE codigo_sku = ? AND activo = 1');
$st->execute([$codigo]);
$p = $st->fetch();
if (!$p) { echo json_encode(['ok' => false, 'error' => 'Producto no encontrado']); exit; }

$s = db()->prepare('SELECT cantidad, stock_minimo FROM stock WHERE almacen_id = ? AND producto_id = ?');
$s->execute([$alm, $p['producto_id']]);
$stock = $s->fetch();
$p['stock'] = $stock ? (int)$stock['cantidad'] : 0;
$p['stock_minimo'] = $stock ? (int)$stock['stock_minimo'] : 0;
echo json_encode(['ok' => true, 'producto' => $p]);
