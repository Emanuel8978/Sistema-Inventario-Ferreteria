<?php
require_once __DIR__ . '/includes/config.php';
$pdo = db();
$id = (int)(isset($_GET['id']) ? $_GET['id'] : 0);
$st = $pdo->prepare("SELECT o.*, pr.razon_social AS proveedor, a.nombre AS almacen, CONCAT(e.nombres, ' ', e.apellidos) AS comprador
                     FROM ordenes_compra o
                     JOIN proveedores pr ON pr.proveedor_id = o.proveedor_id
                     JOIN almacenes a ON a.almacen_id = o.almacen_destino_id
                     LEFT JOIN empleados e ON e.empleado_id = o.empleado_id
                     WHERE o.orden_compra_id = ?");
$st->execute([$id]);
$o = $st->fetch();
if (!$o) { flash('error', 'La orden no existe.'); header('Location: compras.php'); exit; }

$ls = $pdo->prepare('SELECT p.codigo_sku, p.nombre, d.cantidad, d.costo_unitario,
                            COALESCE((SELECT SUM(dr.cantidad_recibida) FROM detalle_recepcion dr
                                      JOIN recepciones_compra rc ON rc.recepcion_id = dr.recepcion_id
                                      WHERE rc.orden_compra_id = d.orden_compra_id AND dr.producto_id = d.producto_id), 0) AS recibida
                     FROM detalle_orden_compra d JOIN productos p ON p.producto_id = d.producto_id
                     WHERE d.orden_compra_id = ? ORDER BY d.detalle_id');
$ls->execute([$id]);
$lineas = $ls->fetchAll();
$total = 0; foreach ($lineas as $l) { $total += $l['cantidad'] * $l['costo_unitario']; }

$rs = $pdo->prepare("SELECT rc.recepcion_id, rc.fecha_recepcion, rc.guia_remision, CONCAT(e.nombres, ' ', e.apellidos) AS recibio,
                            (SELECT SUM(cantidad_recibida) FROM detalle_recepcion WHERE recepcion_id = rc.recepcion_id) AS unidades
                     FROM recepciones_compra rc LEFT JOIN empleados e ON e.empleado_id = rc.empleado_id
                     WHERE rc.orden_compra_id = ? ORDER BY rc.fecha_recepcion");
$rs->execute([$id]);
$recepciones = $rs->fetchAll();

$clase = ['Recibida' => 'verde', 'Recibida parcial' => 'amarillo', 'Pendiente' => 'amarillo', 'Cancelada' => 'rojo'];
$pagina = 'compras';
$titulo = 'Orden de compra ' . $id;
require __DIR__ . '/includes/header.php';
?>
<p><a href="compras.php">← Volver a compras</a></p>
<h1>Orden de compra N.º <?= (int)$o['orden_compra_id'] ?>
  <span class="badge badge-<?= isset($clase[$o['estado']]) ? $clase[$o['estado']] : 'verde' ?>"><?= e($o['estado']) ?></span></h1>
<section class="ficha">
  <div><b>Proveedor</b><?= e($o['proveedor']) ?></div>
  <div><b>Almacén destino</b><?= e($o['almacen']) ?></div>
  <div><b>Fecha de orden</b><?= e($o['fecha_orden']) ?></div>
  <div><b>Entrega esperada</b><?= e($o['fecha_entrega_esperada']) ?></div>
  <div><b>Condición de pago</b><?= e($o['condicion_pago']) ?></div>
  <div><b>Comprador</b><?= e($o['comprador']) ?></div>
</section>
<h3>Productos</h3>
<table class="simple tabla-detalle">
  <thead><tr><th>SKU</th><th>Producto</th><th class="der">Pedido</th><th class="der">Recibido</th><th class="der">Costo</th><th class="der">Importe</th></tr></thead>
  <tbody>
  <?php foreach ($lineas as $l): ?>
    <tr>
      <td><?= e($l['codigo_sku']) ?></td><td><?= e($l['nombre']) ?></td>
      <td class="der"><?= (int)$l['cantidad'] ?></td>
      <td class="der"><?= (int)$l['recibida'] ?></td>
      <td class="der"><?= soles($l['costo_unitario']) ?></td>
      <td class="der"><?= soles($l['cantidad'] * $l['costo_unitario']) ?></td>
    </tr>
  <?php endforeach; ?>
  </tbody>
  <tfoot><tr><td colspan="5" class="der"><b>Total</b></td><td class="der"><b><?= soles($total) ?></b></td></tr></tfoot>
</table>
<h3>Recepciones</h3>
<table class="simple tabla-detalle">
  <thead><tr><th>N.º</th><th>Fecha</th><th>Guía de remisión</th><th>Recibió</th><th class="der">Unidades</th></tr></thead>
  <tbody>
  <?php if (!$recepciones): ?><tr><td colspan="5">Aún no hay recepciones para esta orden.</td></tr><?php endif; ?>
  <?php foreach ($recepciones as $r): ?>
    <tr><td><?= (int)$r['recepcion_id'] ?></td><td><?= e($r['fecha_recepcion']) ?></td><td><?= e($r['guia_remision']) ?></td>
        <td><?= e($r['recibio']) ?></td><td class="der"><?= (int)$r['unidades'] ?></td></tr>
  <?php endforeach; ?>
  </tbody>
</table>
<?php require __DIR__ . '/includes/footer.php'; ?>
