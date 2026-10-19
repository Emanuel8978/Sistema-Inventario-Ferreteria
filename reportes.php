<?php
$pagina = 'reportes';
$titulo = 'Reportes';
require __DIR__ . '/includes/header.php';
$pdo = db();

$porMes = $pdo->query("SELECT DATE_FORMAT(fecha_venta, '%Y-%m') AS mes, COUNT(*) AS n, SUM(total) AS total
                       FROM ventas WHERE estado = 'Emitida' GROUP BY mes ORDER BY mes")->fetchAll();
$porAlmacen = $pdo->query("SELECT a.nombre, COUNT(*) AS n, SUM(v.total) AS total
                           FROM ventas v JOIN almacenes a ON a.almacen_id = v.almacen_id
                           WHERE v.estado = 'Emitida' GROUP BY a.almacen_id, a.nombre ORDER BY total DESC")->fetchAll();
$porPago = $pdo->query("SELECT metodo_pago AS nombre, COUNT(*) AS n, SUM(total) AS total
                        FROM ventas WHERE estado = 'Emitida' GROUP BY metodo_pago ORDER BY total DESC")->fetchAll();
$top = $pdo->query("SELECT p.codigo_sku, p.nombre, SUM(d.cantidad) AS unidades,
                           SUM(d.cantidad * d.precio_unitario * (1 - d.descuento_pct)) AS ingresos
                    FROM detalle_venta d
                    JOIN ventas v ON v.venta_id = d.venta_id AND v.estado = 'Emitida'
                    JOIN productos p ON p.producto_id = d.producto_id
                    GROUP BY p.producto_id, p.codigo_sku, p.nombre ORDER BY ingresos DESC LIMIT 10")->fetchAll();
$reposicion = $pdo->query("SELECT p.codigo_sku, p.nombre, a.nombre AS almacen, s.cantidad, s.stock_minimo, s.stock_maximo, s.cantidad_en_pedido,
                                  GREATEST(s.stock_maximo - s.cantidad - s.cantidad_en_pedido, 0) AS sugerido
                           FROM stock s JOIN productos p ON p.producto_id = s.producto_id AND p.activo = 1
                           JOIN almacenes a ON a.almacen_id = s.almacen_id
                           WHERE s.cantidad < s.stock_minimo
                           ORDER BY (s.stock_minimo - s.cantidad) DESC LIMIT 20")->fetchAll();
$totalBajos = (int)$pdo->query('SELECT COUNT(*) FROM stock s JOIN productos p ON p.producto_id = s.producto_id AND p.activo = 1 WHERE s.cantidad < s.stock_minimo')->fetchColumn();

function barras(array $filas, $campo = 'total') {
    $max = 0; foreach ($filas as $f) { $max = max($max, (float)$f[$campo]); }
    foreach ($filas as $f) {
        $pct = $max > 0 ? round($f[$campo] / $max * 100) : 0;
        echo '<div class="barra-fila"><span class="barra-etq">' . e(isset($f['mes']) ? $f['mes'] : $f['nombre']) . '</span>'
           . '<span class="barra"><i style="width:' . $pct . '%"></i></span>'
           . '<span class="barra-val">' . soles($f[$campo]) . '</span></div>';
    }
}
?>
<h1>Reportes</h1>
<div class="rejilla-reportes">
  <section class="caja"><h3>Ventas por mes <small>(emitidas)</small></h3><?php barras($porMes); ?></section>
  <section class="caja"><h3>Ventas por almacén / tienda</h3><?php barras($porAlmacen); ?></section>
  <section class="caja"><h3>Ventas por método de pago</h3><?php barras($porPago); ?></section>
  <section class="caja"><h3>Top 10 productos por ingresos</h3>
    <table class="simple">
      <thead><tr><th>SKU</th><th>Producto</th><th class="der">Unid.</th><th class="der">Ingresos</th></tr></thead>
      <tbody><?php foreach ($top as $t): ?>
        <tr><td><?= e($t['codigo_sku']) ?></td><td><?= e($t['nombre']) ?></td><td class="der"><?= number_format($t['unidades']) ?></td><td class="der"><?= soles($t['ingresos']) ?></td></tr>
      <?php endforeach; ?></tbody>
    </table>
  </section>
</div>
<section class="caja">
  <h3>Reposición sugerida <small>(<?= number_format($totalBajos) ?> combinaciones producto-almacén bajo el mínimo; se muestran las 20 más críticas)</small></h3>
  <table class="simple">
    <thead><tr><th>SKU</th><th>Producto</th><th>Almacén</th><th class="der">Stock</th><th class="der">Mínimo</th><th class="der">Máximo</th><th class="der">En pedido</th><th class="der">Pedir</th></tr></thead>
    <tbody>
    <?php if (!$reposicion): ?><tr><td colspan="8">No hay productos bajo el stock mínimo.</td></tr><?php endif; ?>
    <?php foreach ($reposicion as $r): ?>
      <tr><td><?= e($r['codigo_sku']) ?></td><td><?= e($r['nombre']) ?></td><td><?= e($r['almacen']) ?></td>
          <td class="der"><?= (int)$r['cantidad'] ?></td><td class="der"><?= (int)$r['stock_minimo'] ?></td><td class="der"><?= (int)$r['stock_maximo'] ?></td>
          <td class="der"><?= (int)$r['cantidad_en_pedido'] ?></td><td class="der"><b><?= (int)$r['sugerido'] ?></b></td></tr>
    <?php endforeach; ?>
    </tbody>
  </table>
</section>
<?php require __DIR__ . '/includes/footer.php'; ?>
