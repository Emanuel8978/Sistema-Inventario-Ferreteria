<?php
$pagina = 'ventas';
$titulo = 'Ventas';
require __DIR__ . '/includes/header.php';
$pdo = db();
$almacenes = $pdo->query('SELECT almacen_id, nombre FROM almacenes ORDER BY almacen_id')->fetchAll();
$metodos = $pdo->query('SELECT DISTINCT metodo_pago FROM ventas ORDER BY 1')->fetchAll(PDO::FETCH_COLUMN);
$f = [
    'almacen_id' => isset($_GET['almacen_id']) ? $_GET['almacen_id'] : '',
    'estado'     => isset($_GET['estado']) ? $_GET['estado'] : '',
    'metodo'     => isset($_GET['metodo']) ? $_GET['metodo'] : '',
    'desde'      => isset($_GET['desde']) ? $_GET['desde'] : '',
    'hasta'      => isset($_GET['hasta']) ? $_GET['hasta'] : '',
];
$res = $pdo->query("SELECT COUNT(*) n, COALESCE(SUM(CASE WHEN estado='Emitida' THEN total END),0) t, SUM(estado='Anulada') an FROM ventas")->fetch();
?>
<h1>Ventas</h1>
<section class="tarjetas">
  <div class="tarjeta"><span class="num"><?= number_format($res['n']) ?></span><span>Comprobantes</span></div>
  <div class="tarjeta"><span class="num"><?= soles($res['t']) ?></span><span>Total vendido (emitidas)</span></div>
  <div class="tarjeta"><span class="num"><?= number_format($res['an']) ?></span><span>Anuladas</span></div>
</section>
<form method="get" class="filtros-linea">
  <div><label for="almacen_id">Almacén</label>
    <select id="almacen_id" name="almacen_id"><option value="">Todos</option><?= opciones_select($almacenes, 'almacen_id', 'nombre', $f['almacen_id']) ?></select></div>
  <div><label for="estado">Estado</label>
    <select id="estado" name="estado"><option value="">Todos</option>
      <option value="Emitida" <?= $f['estado'] === 'Emitida' ? 'selected' : '' ?>>Emitida</option>
      <option value="Anulada" <?= $f['estado'] === 'Anulada' ? 'selected' : '' ?>>Anulada</option></select></div>
  <div><label for="metodo">Método de pago</label>
    <select id="metodo" name="metodo"><option value="">Todos</option>
      <?php foreach ($metodos as $m): ?><option value="<?= e($m) ?>" <?= $f['metodo'] === $m ? 'selected' : '' ?>><?= e($m) ?></option><?php endforeach; ?></select></div>
  <div><label for="desde">Desde</label><input type="date" id="desde" name="desde" value="<?= e($f['desde']) ?>"></div>
  <div><label for="hasta">Hasta</label><input type="date" id="hasta" name="hasta" value="<?= e($f['hasta']) ?>"></div>
  <div class="botones-filtro"><button class="btn btn-primario" type="submit">Filtrar</button> <a class="btn" href="ventas.php">Limpiar</a></div>
</form>
<table id="tabla-ventas" class="display ss" data-tabla="ventas" style="width:100%">
  <thead><tr><th>Comprobante</th><th>Tipo</th><th>Fecha</th><th>Cliente</th><th>Almacén</th><th>Vendedor</th><th>Pago</th><th>Estado</th><th>Total</th></tr></thead>
</table>
<?php require __DIR__ . '/includes/footer.php'; ?>
