<?php
$pagina = 'kardex';
$titulo = 'Kardex';
require __DIR__ . '/includes/header.php';
$pdo = db();
$almacenes = $pdo->query('SELECT almacen_id, nombre FROM almacenes ORDER BY almacen_id')->fetchAll();
$tipos = $pdo->query('SELECT DISTINCT tipo_movimiento FROM movimientos_inventario ORDER BY 1')->fetchAll(PDO::FETCH_COLUMN);
$f = [
    'sku'        => strtoupper(trim(isset($_GET['sku']) ? $_GET['sku'] : '')),
    'almacen_id' => isset($_GET['almacen_id']) ? $_GET['almacen_id'] : '',
    'tipo'       => isset($_GET['tipo']) ? $_GET['tipo'] : '',
    'desde'      => isset($_GET['desde']) ? $_GET['desde'] : '',
    'hasta'      => isset($_GET['hasta']) ? $_GET['hasta'] : '',
];
?>
<h1>Kardex · historial de movimientos</h1>
<form method="get" class="filtros-linea">
  <div><label for="sku">Código de barras / SKU</label>
    <input type="text" id="sku" name="sku" value="<?= e($f['sku']) ?>" placeholder="Escanea o escribe el SKU" autofocus></div>
  <div><label for="almacen_id">Almacén</label>
    <select id="almacen_id" name="almacen_id"><option value="">Todos</option><?= opciones_select($almacenes, 'almacen_id', 'nombre', $f['almacen_id']) ?></select></div>
  <div><label for="tipo">Tipo</label>
    <select id="tipo" name="tipo"><option value="">Todos</option>
      <?php foreach ($tipos as $t): ?><option value="<?= e($t) ?>" <?= $f['tipo'] === $t ? 'selected' : '' ?>><?= e($t) ?></option><?php endforeach; ?>
    </select></div>
  <div><label for="desde">Desde</label><input type="date" id="desde" name="desde" value="<?= e($f['desde']) ?>"></div>
  <div><label for="hasta">Hasta</label><input type="date" id="hasta" name="hasta" value="<?= e($f['hasta']) ?>"></div>
  <div class="botones-filtro"><button class="btn btn-primario" type="submit">Filtrar</button> <a class="btn" href="kardex.php">Limpiar</a></div>
</form>
<table id="tabla-movimientos" class="display ss" data-tabla="movimientos" style="width:100%">
  <thead><tr><th>Fecha</th><th>Almacén</th><th>SKU</th><th>Producto</th><th>Tipo</th><th>Cant.</th><th>Costo</th><th>Saldo</th><th>Documento</th><th>Observación</th></tr></thead>
</table>
<?php require __DIR__ . '/includes/footer.php'; ?>
