<?php
/**
 * Carga TODOS los CSV de la carpeta data/ en la base de datos.
 * Uso (solo por consola):   php importar_datos.php
 * Antes importa database.sql. Reemplaza el contenido de las tablas.
 */
if (PHP_SAPI !== 'cli') { http_response_code(403); exit("Ejecuta este script desde la línea de comandos.\n"); }
require __DIR__ . '/includes/config.php';
ini_set('memory_limit', '512M');

$pdo = db();

// Orden de carga: archivo CSV => tabla
$cargas = [
    'categorias.csv'            => 'categorias',
    'proveedores.csv'           => 'proveedores',
    'almacenes.csv'             => 'almacenes',
    'empleados.csv'             => 'empleados',
    'clientes.csv'              => 'clientes',
    'productos.csv'             => 'productos',
    'inventario_inicial.csv'    => 'inventario_inicial',
    'stock_actual.csv'          => 'stock',
    'ordenes_compra.csv'        => 'ordenes_compra',
    'detalle_orden_compra.csv'  => 'detalle_orden_compra',
    'recepciones_compra.csv'    => 'recepciones_compra',
    'detalle_recepcion.csv'     => 'detalle_recepcion',
    'ventas.csv'                => 'ventas',
    'detalle_venta.csv'         => 'detalle_venta',
    'movimientos_inventario.csv'=> 'movimientos_inventario',
];

$pdo->exec('SET FOREIGN_KEY_CHECKS = 0');
foreach (array_reverse(array_values($cargas)) as $t) {
    $pdo->exec("TRUNCATE TABLE `$t`");
}

$inicio = microtime(true);
foreach ($cargas as $archivo => $tabla) {
    $ruta = __DIR__ . '/data/' . $archivo;
    $fh = @fopen($ruta, 'r');
    if (!$fh) { fwrite(STDERR, "No se pudo abrir $ruta\n"); exit(1); }

    $cols = fgetcsv($fh);
    $cols[0] = preg_replace('/^\xEF\xBB\xBF/', '', $cols[0]);          // quitar BOM
    $cols = array_map('trim', $cols);
    $lista = implode(',', array_map(function ($c) { return "`$c`"; }, $cols));
    $fila_ph = '(' . implode(',', array_fill(0, count($cols), '?')) . ')';
    $n = 0;

    $lote = [];
    $volcar = function () use (&$lote, $pdo, $tabla, $lista, $fila_ph, $cols) {
        if (!$lote) return;
        $sql = "INSERT INTO `$tabla` ($lista) VALUES " . implode(',', array_fill(0, count($lote), $fila_ph));
        $vals = [];
        foreach ($lote as $f) { foreach ($f as $v) $vals[] = $v; }
        $pdo->prepare($sql)->execute($vals);
        $lote = [];
    };

    $pdo->beginTransaction();
    while (($fila = fgetcsv($fh)) !== false) {
        if ($fila === [null]) continue;
        $fila = array_slice(array_pad($fila, count($cols), null), 0, count($cols));
        $lote[] = array_map(function ($v) { return ($v === '' ? null : $v); }, $fila);
        $n++;
        if (count($lote) >= 400) $volcar();
    }
    $volcar();
    $pdo->commit();
    fclose($fh);
    echo str_pad($tabla, 24) . number_format($n) . " filas\n";
}
$pdo->exec('SET FOREIGN_KEY_CHECKS = 1');
printf("Importación completada en %.1f s.\n", microtime(true) - $inicio);
