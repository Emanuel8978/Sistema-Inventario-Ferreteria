<?php
$pagina = 'etiquetas';
$titulo = 'Etiquetas';
require __DIR__ . '/includes/header.php';

$pdo = db();
$cats = $pdo->query('SELECT categoria_id, nombre FROM categorias ORDER BY nombre')->fetchAll();
$cat  = isset($_GET['categoria_id']) ? (int)$_GET['categoria_id'] : 0;

if ($cat > 0) {
    $st = $pdo->prepare('SELECT codigo_sku, nombre, precio_venta FROM productos WHERE activo = 1 AND categoria_id = ? ORDER BY codigo_sku');
    $st->execute([$cat]);
} else {
    $st = $pdo->query('SELECT codigo_sku, nombre, precio_venta FROM productos WHERE activo = 1 ORDER BY codigo_sku');
}
$items = $st->fetchAll();
?>
<div class="no-imprimir">
  <h1>Etiquetas de código de barras</h1>
  <form method="get" class="filtro">
    <label for="categoria_id">Categoría</label>
    <select name="categoria_id" id="categoria_id" onchange="this.form.submit()">
      <option value="0">Todas</option>
      <?php foreach ($cats as $c): ?>
        <option value="<?= (int)$c['categoria_id'] ?>" <?= $cat === (int)$c['categoria_id'] ? 'selected' : '' ?>><?= e($c['nombre']) ?></option>
      <?php endforeach; ?>
    </select>
    <button type="button" class="btn btn-primario" onclick="window.print()">🖨 Imprimir (<?= count($items) ?>)</button>
  </form>
</div>

<div class="hoja-etiquetas">
  <?php foreach ($items as $it): ?>
    <div class="etiqueta">
      <div class="etiqueta-nombre"><?= e($it['nombre']) ?></div>
      <svg class="barcode" data-codigo="<?= e($it['codigo_sku']) ?>"></svg>
      <div class="etiqueta-precio">S/ <?= number_format((float)$it['precio_venta'], 2) ?></div>
    </div>
  <?php endforeach; ?>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
