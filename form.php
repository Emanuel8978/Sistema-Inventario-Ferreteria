<?php
require_once __DIR__ . '/includes/config.php';
$pdo = db();

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$producto = [
    'producto_id' => 0, 'codigo_sku' => '', 'nombre' => '', 'categoria_id' => '',
    'proveedor_id' => '', 'unidad_medida' => 'unidad', 'costo_estandar' => '0.00', 'precio_venta' => '0.00',
];
if ($id > 0) {
    $st = $pdo->prepare('SELECT * FROM productos WHERE producto_id = ?');
    $st->execute([$id]);
    $fila = $st->fetch();
    if (!$fila) { flash('error', 'El producto no existe.'); header('Location: index.php'); exit; }
    $producto = $fila;
}

$errores = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $id     = (int)(isset($_POST['producto_id']) ? $_POST['producto_id'] : 0);
    $sku    = strtoupper(trim(isset($_POST['codigo_sku']) ? $_POST['codigo_sku'] : ''));
    $nombre = trim(isset($_POST['nombre']) ? $_POST['nombre'] : '');
    $cat    = (int)(isset($_POST['categoria_id']) ? $_POST['categoria_id'] : 0);
    $prov   = (int)(isset($_POST['proveedor_id']) ? $_POST['proveedor_id'] : 0);
    $unidad = trim(isset($_POST['unidad_medida']) ? $_POST['unidad_medida'] : 'unidad');
    $costo  = (float)str_replace(',', '.', isset($_POST['costo_estandar']) ? $_POST['costo_estandar'] : '0');
    $precio = (float)str_replace(',', '.', isset($_POST['precio_venta']) ? $_POST['precio_venta'] : '0');

    // SKU automático si se deja vacío
    if ($sku === '') {
        $ultimo = $pdo->query("SELECT MAX(CAST(SUBSTRING(codigo_sku, 5) AS UNSIGNED)) FROM productos WHERE codigo_sku LIKE 'FER-%'")->fetchColumn();
        $sku = sprintf('FER-%04d', ((int)$ultimo) + 1);
    }
    if (!preg_match('/^[A-Z0-9\-\.]{3,40}$/', $sku)) $errores[] = 'El SKU solo admite letras, números, guion y punto (3 a 40 caracteres).';
    if ($nombre === '' || texto_len($nombre) > 150) $errores[] = 'El nombre es obligatorio (máx. 150 caracteres).';
    if ($costo < 0 || $precio < 0) $errores[] = 'Costo y precio no pueden ser negativos.';
    if ($unidad === '') $unidad = 'unidad';

    $dup = $pdo->prepare('SELECT producto_id FROM productos WHERE codigo_sku = ? AND producto_id <> ?');
    $dup->execute([$sku, $id]);
    if ($dup->fetch()) $errores[] = 'Ya existe otro producto con ese SKU.';

    if (!$errores) {
        if ($id > 0) {
            $pdo->prepare('UPDATE productos SET codigo_sku=?, nombre=?, categoria_id=?, proveedor_id=?, unidad_medida=?, costo_estandar=?, precio_venta=? WHERE producto_id=?')
                ->execute([$sku, $nombre, $cat ?: null, $prov ?: null, $unidad, $costo, $precio, $id]);
            flash('ok', 'Producto actualizado correctamente.');
        } else {
            $pdo->beginTransaction();
            $pdo->prepare('INSERT INTO productos (codigo_sku, nombre, categoria_id, proveedor_id, unidad_medida, costo_estandar, precio_venta) VALUES (?,?,?,?,?,?,?)')
                ->execute([$sku, $nombre, $cat ?: null, $prov ?: null, $unidad, $costo, $precio]);
            $nuevoId = (int)$pdo->lastInsertId();
            // Crear la fila de stock (en 0) para cada almacén
            $pdo->prepare('INSERT INTO stock (almacen_id, producto_id, cantidad, costo_promedio, ultima_actualizacion)
                           SELECT almacen_id, ?, 0, ?, NOW() FROM almacenes')->execute([$nuevoId, $costo]);
            $pdo->commit();
            flash('ok', 'Producto creado. Ya puedes imprimir su etiqueta de código de barras.');
        }
        header('Location: index.php');
        exit;
    }
    $producto = [
        'producto_id' => $id, 'codigo_sku' => $sku, 'nombre' => $nombre, 'categoria_id' => $cat,
        'proveedor_id' => $prov, 'unidad_medida' => $unidad, 'costo_estandar' => $costo, 'precio_venta' => $precio,
    ];
}

$categorias  = $pdo->query('SELECT categoria_id, nombre FROM categorias ORDER BY nombre')->fetchAll();
$proveedores = $pdo->query('SELECT proveedor_id, razon_social FROM proveedores ORDER BY razon_social')->fetchAll();

$pagina = 'form';
$titulo = $producto['producto_id'] ? 'Editar producto' : 'Nuevo producto';
require __DIR__ . '/includes/header.php';
?>
<h1><?= e($titulo) ?></h1>

<?php foreach ($errores as $err): ?>
  <div class="alerta alerta-error"><?= e($err) ?></div>
<?php endforeach; ?>

<div class="dos-columnas">
  <form method="post" class="formulario" autocomplete="off">
    <?= csrf_field() ?>
    <input type="hidden" name="producto_id" value="<?= (int)$producto['producto_id'] ?>">

    <label for="codigo_sku">SKU / Código de barras</label>
    <input type="text" id="codigo_sku" name="codigo_sku" value="<?= e($producto['codigo_sku']) ?>" maxlength="40" placeholder="Déjalo vacío para generar uno automático (FER-0109...)">

    <label for="nombre">Nombre del producto *</label>
    <input type="text" id="nombre" name="nombre" value="<?= e($producto['nombre']) ?>" maxlength="150" required>

    <div class="fila">
      <div>
        <label for="categoria_id">Categoría</label>
        <select id="categoria_id" name="categoria_id">
          <option value="">— Sin categoría —</option>
          <?php foreach ($categorias as $c): ?>
            <option value="<?= (int)$c['categoria_id'] ?>" <?= (int)$producto['categoria_id'] === (int)$c['categoria_id'] ? 'selected' : '' ?>><?= e($c['nombre']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div>
        <label for="proveedor_id">Proveedor</label>
        <select id="proveedor_id" name="proveedor_id">
          <option value="">— Sin proveedor —</option>
          <?php foreach ($proveedores as $pr): ?>
            <option value="<?= (int)$pr['proveedor_id'] ?>" <?= (int)$producto['proveedor_id'] === (int)$pr['proveedor_id'] ? 'selected' : '' ?>><?= e($pr['razon_social']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
    </div>

    <div class="fila tres">
      <div>
        <label for="unidad_medida">Unidad</label>
        <select id="unidad_medida" name="unidad_medida">
          <?php foreach (['unidad', 'kg', 'par', 'm'] as $u): ?>
            <option value="<?= $u ?>" <?= $producto['unidad_medida'] === $u ? 'selected' : '' ?>><?= $u ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div>
        <label for="costo_estandar">Costo (S/)</label>
        <input type="number" step="0.01" min="0" id="costo_estandar" name="costo_estandar" value="<?= e($producto['costo_estandar']) ?>">
      </div>
      <div>
        <label for="precio_venta">Precio de venta (S/)</label>
        <input type="number" step="0.01" min="0" id="precio_venta" name="precio_venta" value="<?= e($producto['precio_venta']) ?>">
      </div>
    </div>

    <div class="botones">
      <button type="submit" class="btn btn-primario">Guardar</button>
      <a class="btn" href="index.php">Cancelar</a>
    </div>
  </form>

  <aside class="vista-previa">
    <h3>Vista previa de la etiqueta</h3>
    <div class="etiqueta" id="etiqueta-previa">
      <div class="etiqueta-nombre" id="prev-nombre"><?= e($producto['nombre'] ?: 'Nombre del producto') ?></div>
      <svg id="prev-barcode" class="barcode-previa" data-codigo="<?= e($producto['codigo_sku']) ?>"></svg>
      <div class="etiqueta-precio" id="prev-precio">S/ <?= number_format((float)$producto['precio_venta'], 2) ?></div>
    </div>
    <button type="button" class="btn" onclick="window.print()">🖨 Imprimir etiqueta</button>
  </aside>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
