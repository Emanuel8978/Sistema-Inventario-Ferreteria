<?php
$pagina = 'inicio';
$titulo = 'Inventario';
require __DIR__ . '/includes/header.php';

$pdo = db();
$almacenes = $pdo->query('SELECT almacen_id, nombre FROM almacenes ORDER BY almacen_id')->fetchAll();
$almacenSel = isset($_GET['almacen_id']) ? (int)$_GET['almacen_id'] : 0;

$sql = 'SELECT p.producto_id, p.codigo_sku, p.nombre, c.nombre AS categoria, p.unidad_medida,
               p.precio_venta,
               COALESCE(SUM(s.cantidad), 0) AS stock,
               COALESCE(SUM(s.stock_minimo), 0) AS minimo,
               COALESCE(SUM(CASE WHEN s.cantidad < s.stock_minimo THEN 1 ELSE 0 END), 0) AS bajo,
               COALESCE(SUM(s.cantidad * s.costo_promedio), 0) AS valor
        FROM productos p
        LEFT JOIN categorias c ON c.categoria_id = p.categoria_id
        LEFT JOIN stock s ON s.producto_id = p.producto_id';
$params = [];
if ($almacenSel > 0) {
    $sql .= ' AND s.almacen_id = :alm';
    $params[':alm'] = $almacenSel;
}
$sql .= ' WHERE p.activo = 1 GROUP BY p.producto_id, p.codigo_sku, p.nombre, c.nombre, p.unidad_medida, p.precio_venta
          ORDER BY p.codigo_sku';
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$productos = $stmt->fetchAll();

$totalProductos = count($productos);
$totalUnidades = 0; $valorTotal = 0.0; $totalBajos = 0;
foreach ($productos as $p) {
    $totalUnidades += (int)$p['stock'];
    $valorTotal    += (float)$p['valor'];
    if ((int)$p['bajo'] > 0) $totalBajos++;
}
?>
<h1>Inventario de productos</h1>

<section class="tarjetas">
  <div class="tarjeta"><span class="num"><?= number_format($totalProductos) ?></span><span>Productos</span></div>
  <div class="tarjeta"><span class="num"><?= number_format($totalUnidades) ?></span><span>Unidades en stock</span></div>
  <div class="tarjeta"><span class="num">S/ <?= number_format($valorTotal, 2) ?></span><span>Valor del inventario</span></div>
  <div class="tarjeta <?= $totalBajos ? 'tarjeta-alerta' : '' ?>"><span class="num"><?= number_format($totalBajos) ?></span><span>Con stock bajo</span></div>
</section>

<section class="barra-herramientas">
  <div>
    <label for="scan">🔎 Escanear / buscar código</label>
    <input type="text" id="scan" placeholder="Escanea el código de barras o escribe el SKU y presiona Enter" autocomplete="off" autofocus>
  </div>
  <form method="get" class="filtro">
    <label for="almacen_id">Almacén</label>
    <select name="almacen_id" id="almacen_id" onchange="this.form.submit()">
      <option value="0">Todos los almacenes</option>
      <?php foreach ($almacenes as $a): ?>
        <option value="<?= (int)$a['almacen_id'] ?>" <?= $almacenSel === (int)$a['almacen_id'] ? 'selected' : '' ?>><?= e($a['nombre']) ?></option>
      <?php endforeach; ?>
    </select>
  </form>
  <a class="btn btn-primario" href="form.php">＋ Nuevo producto</a>
</section>

<table id="tabla-productos" class="display" style="width:100%">
  <thead>
    <tr>
      <th>Código de barras</th><th>SKU</th><th>Producto</th><th>Categoría</th>
      <th>Stock</th><th>Precio</th><th>Estado</th><th>Acciones</th>
    </tr>
  </thead>
  <tbody>
  <?php foreach ($productos as $p):
        $stock = (int)$p['stock'];
        if ($stock <= 0)               { $estado = 'Agotado'; $clase = 'rojo'; }
        elseif ((int)$p['bajo'] > 0)   { $estado = 'Stock bajo'; $clase = 'amarillo'; }
        else                           { $estado = 'OK'; $clase = 'verde'; }
  ?>
    <tr data-sku="<?= e($p['codigo_sku']) ?>">
      <td><svg class="barcode" data-codigo="<?= e($p['codigo_sku']) ?>"></svg></td>
      <td><?= e($p['codigo_sku']) ?></td>
      <td><?= e($p['nombre']) ?></td>
      <td><?= e($p['categoria']) ?></td>
      <td data-order="<?= $stock ?>"><?= number_format($stock) ?> <small><?= e($p['unidad_medida']) ?></small></td>
      <td data-order="<?= e($p['precio_venta']) ?>">S/ <?= number_format((float)$p['precio_venta'], 2) ?></td>
      <td><span class="badge badge-<?= $clase ?>"><?= $estado ?></span></td>
      <td class="acciones">
        <a href="movimiento.php?codigo=<?= urlencode($p['codigo_sku']) ?>" title="Registrar movimiento">⇅</a>
        <a href="form.php?id=<?= (int)$p['producto_id'] ?>" title="Editar">✎</a>
        <form method="post" action="eliminar.php" onsubmit="return confirm('¿Desactivar este producto?');">
          <?= csrf_field() ?>
          <input type="hidden" name="id" value="<?= (int)$p['producto_id'] ?>">
          <button type="submit" class="link-peligro" title="Desactivar">🗑</button>
        </form>
      </td>
    </tr>
  <?php endforeach; ?>
  </tbody>
</table>
<?php require __DIR__ . '/includes/footer.php'; ?>
