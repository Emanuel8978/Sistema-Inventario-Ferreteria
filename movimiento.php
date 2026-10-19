<?php
require_once __DIR__ . '/includes/config.php';
$pdo = db();
$almacenes = $pdo->query('SELECT almacen_id, nombre FROM almacenes ORDER BY almacen_id')->fetchAll();

// Tipos que se pueden registrar a mano
$TIPOS = [
    'ENTRADA'       => 'Entrada (ingreso de mercadería)',
    'SALIDA'        => 'Salida (consumo / traslado)',
    'AJUSTE_MERMA'  => 'Merma (rotura, daño, faltante)',
    'AJUSTE_CONTEO' => 'Ajuste por conteo físico (stock final)',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $alm  = (int)(isset($_POST['almacen_id']) ? $_POST['almacen_id'] : 0);
    $sku  = strtoupper(trim(isset($_POST['codigo']) ? $_POST['codigo'] : ''));
    $tipo = isset($_POST['tipo']) ? $_POST['tipo'] : '';
    $cant = (int)(isset($_POST['cantidad']) ? $_POST['cantidad'] : 0);
    $obs  = texto_cortar(trim(isset($_POST['observacion']) ? $_POST['observacion'] : ''), 255);

    try {
        if (!isset($TIPOS[$tipo])) throw new Exception('Tipo de movimiento inválido.');
        if ($tipo === 'AJUSTE_CONTEO' ? $cant < 0 : $cant <= 0) throw new Exception('La cantidad no es válida.');

        $pdo->beginTransaction();
        $a = $pdo->prepare('SELECT 1 FROM almacenes WHERE almacen_id = ?');
        $a->execute([$alm]);
        if (!$a->fetch()) throw new Exception('Almacén no válido.');

        $st = $pdo->prepare('SELECT producto_id, nombre FROM productos WHERE codigo_sku = ? AND activo = 1');
        $st->execute([$sku]);
        $prod = $st->fetch();
        if (!$prod) throw new Exception('No existe un producto activo con el código ' . $sku . '.');

        // Asegurar la fila de stock y bloquearla mientras dura la operación
        $pdo->prepare('INSERT IGNORE INTO stock (almacen_id, producto_id, cantidad, ultima_actualizacion) VALUES (?,?,0,NOW())')
            ->execute([$alm, $prod['producto_id']]);
        $s = $pdo->prepare('SELECT cantidad, costo_promedio FROM stock WHERE almacen_id = ? AND producto_id = ? FOR UPDATE');
        $s->execute([$alm, $prod['producto_id']]);
        $fila = $s->fetch();
        $actual = (int)$fila['cantidad'];
        $costo  = (float)$fila['costo_promedio'];

        if ($tipo === 'ENTRADA')                                 { $delta = $cant; }
        elseif ($tipo === 'SALIDA' || $tipo === 'AJUSTE_MERMA') { $delta = -$cant; }
        else                                                     { $delta = $cant - $actual; }   // conteo: la cantidad es el stock final
        $nuevo = $actual + $delta;
        if ($nuevo < 0) throw new Exception('Stock insuficiente: hay ' . $actual . ' unidades disponibles.');

        $pdo->prepare('UPDATE stock SET cantidad = ?, ultima_actualizacion = NOW() WHERE almacen_id = ? AND producto_id = ?')
            ->execute([$nuevo, $alm, $prod['producto_id']]);
        $docTipo = ($tipo === 'AJUSTE_MERMA' || $tipo === 'AJUSTE_CONTEO') ? 'AJUSTE' : 'MANUAL';
        $pdo->prepare('INSERT INTO movimientos_inventario (almacen_id, producto_id, tipo_movimiento, cantidad, costo_unitario, saldo_posterior, documento_tipo, observacion)
                       VALUES (?,?,?,?,?,?,?,?)')
            ->execute([$alm, $prod['producto_id'], $tipo, $delta, $costo, $nuevo, $docTipo, $obs !== '' ? $obs : null]);
        $pdo->commit();
        flash('ok', $TIPOS[$tipo] . ' registrada: ' . $prod['nombre'] . ' → stock ' . $nuevo . '.');
    } catch (Exception $ex) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        flash('error', $ex->getMessage());
    }
    header('Location: movimiento.php?almacen_id=' . $alm);
    exit;
}

$almSel = isset($_GET['almacen_id']) ? (int)$_GET['almacen_id'] : (int)$almacenes[0]['almacen_id'];
$codigoInicial = isset($_GET['codigo']) ? trim($_GET['codigo']) : '';

$ultimos = $pdo->query("SELECT m.fecha_movimiento, a.nombre AS almacen, p.codigo_sku, p.nombre, m.tipo_movimiento, m.cantidad, m.saldo_posterior, m.observacion
                        FROM movimientos_inventario m
                        JOIN productos p ON p.producto_id = m.producto_id
                        JOIN almacenes a ON a.almacen_id = m.almacen_id
                        WHERE m.documento_tipo = 'MANUAL' OR m.documento_tipo = 'AJUSTE'
                        ORDER BY m.fecha_movimiento DESC, m.movimiento_id DESC LIMIT 12")->fetchAll();

$pagina = 'movimiento';
$titulo = 'Registrar movimiento';
require __DIR__ . '/includes/header.php';
?>
<h1>Registrar movimiento</h1>
<div class="dos-columnas">
  <form method="post" class="formulario" id="form-mov" autocomplete="off">
    <?= csrf_field() ?>
    <label for="almacen_id">Almacén</label>
    <select id="almacen_id" name="almacen_id">
      <?php foreach ($almacenes as $a): ?>
        <option value="<?= (int)$a['almacen_id'] ?>" <?= $almSel === (int)$a['almacen_id'] ? 'selected' : '' ?>><?= e($a['nombre']) ?></option>
      <?php endforeach; ?>
    </select>

    <label for="codigo">Código de barras (escanea aquí)</label>
    <input type="text" id="codigo" name="codigo" value="<?= e($codigoInicial) ?>" required autofocus placeholder="Escanea o escribe el SKU y presiona Enter">
    <div id="info-producto" class="info-producto oculto"></div>

    <div class="fila">
      <div>
        <label for="tipo">Tipo</label>
        <select id="tipo" name="tipo">
          <?php foreach ($TIPOS as $k => $txt): ?><option value="<?= $k ?>"><?= e($txt) ?></option><?php endforeach; ?>
        </select>
      </div>
      <div>
        <label for="cantidad" id="lbl-cantidad">Cantidad</label>
        <input type="number" id="cantidad" name="cantidad" min="0" value="1" required>
      </div>
    </div>

    <label for="observacion">Observación (opcional)</label>
    <input type="text" id="observacion" name="observacion" maxlength="255">

    <div class="botones">
      <button type="submit" class="btn btn-primario">Registrar</button>
      <a class="btn" href="index.php">Volver</a>
    </div>
  </form>

  <aside>
    <h3>Últimos movimientos manuales y ajustes</h3>
    <table class="simple">
      <thead><tr><th>Fecha</th><th>Producto</th><th>Tipo</th><th>Cant.</th><th>Saldo</th></tr></thead>
      <tbody>
      <?php foreach ($ultimos as $m): ?>
        <tr title="<?= e($m['almacen'] . ($m['observacion'] ? ' — ' . $m['observacion'] : '')) ?>">
          <td><?= e(substr($m['fecha_movimiento'], 0, 16)) ?></td>
          <td><?= e($m['codigo_sku']) ?><br><small><?= e($m['nombre']) ?></small></td>
          <td><small><?= e($m['tipo_movimiento']) ?></small></td>
          <td><?= (int)$m['cantidad'] > 0 ? '+' : '' ?><?= (int)$m['cantidad'] ?></td>
          <td><?= (int)$m['saldo_posterior'] ?></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
    <p><a href="kardex.php">Ver el kardex completo →</a></p>
  </aside>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
