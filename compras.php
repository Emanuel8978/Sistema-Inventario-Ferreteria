<?php
$pagina = 'compras';
$titulo = 'Compras';
require __DIR__ . '/includes/header.php';
$pdo = db();
$almacenes   = $pdo->query('SELECT almacen_id, nombre FROM almacenes ORDER BY almacen_id')->fetchAll();
$proveedores = $pdo->query('SELECT proveedor_id, razon_social FROM proveedores ORDER BY razon_social')->fetchAll();
$estados     = $pdo->query('SELECT DISTINCT estado FROM ordenes_compra ORDER BY 1')->fetchAll(PDO::FETCH_COLUMN);
$f = [
    'almacen_id'   => isset($_GET['almacen_id']) ? $_GET['almacen_id'] : '',
    'proveedor_id' => isset($_GET['proveedor_id']) ? $_GET['proveedor_id'] : '',
    'estado'       => isset($_GET['estado']) ? $_GET['estado'] : '',
];
$res = $pdo->query("SELECT COUNT(*) n, SUM(estado='Pendiente') pend, SUM(estado='Recibida parcial') parc FROM ordenes_compra")->fetch();
?>
<h1>Órdenes de compra</h1>
<section class="tarjetas">
  <div class="tarjeta"><span class="num"><?= number_format($res['n']) ?></span><span>Órdenes</span></div>
  <div class="tarjeta"><span class="num"><?= number_format($res['pend']) ?></span><span>Pendientes</span></div>
  <div class="tarjeta"><span class="num"><?= number_format($res['parc']) ?></span><span>Recibidas parcialmente</span></div>
</section>
<form method="get" class="filtros-linea">
  <div><label for="proveedor_id">Proveedor</label>
    <select id="proveedor_id" name="proveedor_id"><option value="">Todos</option><?= opciones_select($proveedores, 'proveedor_id', 'razon_social', $f['proveedor_id']) ?></select></div>
  <div><label for="almacen_id">Almacén destino</label>
    <select id="almacen_id" name="almacen_id"><option value="">Todos</option><?= opciones_select($almacenes, 'almacen_id', 'nombre', $f['almacen_id']) ?></select></div>
  <div><label for="estado">Estado</label>
    <select id="estado" name="estado"><option value="">Todos</option>
      <?php foreach ($estados as $s): ?><option value="<?= e($s) ?>" <?= $f['estado'] === $s ? 'selected' : '' ?>><?= e($s) ?></option><?php endforeach; ?></select></div>
  <div class="botones-filtro"><button class="btn btn-primario" type="submit">Filtrar</button> <a class="btn" href="compras.php">Limpiar</a></div>
</form>
<table id="tabla-compras" class="display ss" data-tabla="compras" style="width:100%">
  <thead><tr><th>N.º orden</th><th>Fecha</th><th>Proveedor</th><th>Almacén destino</th><th>Entrega esperada</th><th>Pago</th><th>Estado</th><th>Total</th></tr></thead>
</table>
<?php require __DIR__ . '/includes/footer.php'; ?>
