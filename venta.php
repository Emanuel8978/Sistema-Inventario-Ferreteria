<?php
require_once __DIR__ . '/includes/config.php';
$pdo = db();
$id = (int)(isset($_GET['id']) ? $_GET['id'] : 0);
$st = $pdo->prepare("SELECT v.*, c.nombre_o_razon_social AS cliente, c.tipo_documento, c.numero_documento, a.nombre AS almacen,
                            CONCAT(e.nombres, ' ', e.apellidos) AS vendedor
                     FROM ventas v
                     JOIN clientes c ON c.cliente_id = v.cliente_id
                     JOIN almacenes a ON a.almacen_id = v.almacen_id
                     LEFT JOIN empleados e ON e.empleado_id = v.empleado_id
                     WHERE v.venta_id = ?");
$st->execute([$id]);
$v = $st->fetch();
if (!$v) { flash('error', 'La venta no existe.'); header('Location: ventas.php'); exit; }

$ls = $pdo->prepare('SELECT p.codigo_sku, p.nombre, p.unidad_medida, d.cantidad, d.precio_unitario, d.descuento_pct,
                            ROUND(d.cantidad * d.precio_unitario * (1 - d.descuento_pct), 2) AS importe
                     FROM detalle_venta d JOIN productos p ON p.producto_id = d.producto_id
                     WHERE d.venta_id = ? ORDER BY d.detalle_id');
$ls->execute([$id]);
$lineas = $ls->fetchAll();

$pagina = 'ventas';
$titulo = 'Venta ' . $v['numero_comprobante'];
require __DIR__ . '/includes/header.php';
?>
<p><a href="ventas.php">← Volver a ventas</a></p>
<h1><?= e($v['tipo_comprobante']) ?> <?= e($v['numero_comprobante']) ?>
  <span class="badge badge-<?= $v['estado'] === 'Anulada' ? 'rojo' : 'verde' ?>"><?= e($v['estado']) ?></span></h1>
<section class="ficha">
  <div><b>Fecha</b><?= e($v['fecha_venta']) ?></div>
  <div><b>Cliente</b><?= e($v['cliente']) ?> (<?= e($v['tipo_documento']) ?> <?= e($v['numero_documento']) ?>)</div>
  <div><b>Almacén / tienda</b><?= e($v['almacen']) ?></div>
  <div><b>Vendedor</b><?= e($v['vendedor']) ?></div>
  <div><b>Método de pago</b><?= e($v['metodo_pago']) ?></div>
</section>
<table class="simple tabla-detalle">
  <thead><tr><th>SKU</th><th>Producto</th><th class="der">Cant.</th><th class="der">Precio</th><th class="der">Desc.</th><th class="der">Importe</th></tr></thead>
  <tbody>
  <?php foreach ($lineas as $l): ?>
    <tr>
      <td><?= e($l['codigo_sku']) ?></td><td><?= e($l['nombre']) ?></td>
      <td class="der"><?= (int)$l['cantidad'] ?> <small><?= e($l['unidad_medida']) ?></small></td>
      <td class="der"><?= soles($l['precio_unitario']) ?></td>
      <td class="der"><?= $l['descuento_pct'] > 0 ? round($l['descuento_pct'] * 100) . ' %' : '—' ?></td>
      <td class="der"><?= soles($l['importe']) ?></td>
    </tr>
  <?php endforeach; ?>
  </tbody>
  <tfoot>
    <tr><td colspan="5" class="der">Subtotal</td><td class="der"><?= soles($v['subtotal']) ?></td></tr>
    <tr><td colspan="5" class="der">IGV (18 %)</td><td class="der"><?= soles($v['igv']) ?></td></tr>
    <tr><td colspan="5" class="der"><b>Total</b></td><td class="der"><b><?= soles($v['total']) ?></b></td></tr>
  </tfoot>
</table>
<?php require __DIR__ . '/includes/footer.php'; ?>
