<?php
$pagina = 'catalogos';
$titulo = 'Catálogos';
require __DIR__ . '/includes/header.php';
$pdo = db();
$vista = isset($_GET['v']) ? $_GET['v'] : 'proveedores';

$defs = [
  'proveedores' => ['Proveedores',
    'SELECT razon_social, ruc, contacto, telefono, email, ciudad, plazo_entrega_dias, condicion_pago FROM proveedores ORDER BY razon_social',
    ['razon_social' => 'Razón social', 'ruc' => 'RUC', 'contacto' => 'Contacto', 'telefono' => 'Teléfono', 'email' => 'Email', 'ciudad' => 'Ciudad', 'plazo_entrega_dias' => 'Plazo (días)', 'condicion_pago' => 'Pago']],
  'empleados' => ['Empleados',
    "SELECT CONCAT(e.nombres, ' ', e.apellidos) AS nombre, e.dni, e.cargo, a.nombre AS almacen, e.fecha_ingreso, e.email FROM empleados e LEFT JOIN almacenes a ON a.almacen_id = e.almacen_id ORDER BY e.empleado_id",
    ['nombre' => 'Nombre', 'dni' => 'DNI', 'cargo' => 'Cargo', 'almacen' => 'Almacén', 'fecha_ingreso' => 'Ingreso', 'email' => 'Email']],
  'almacenes' => ['Almacenes y tiendas',
    "SELECT a.nombre, a.tipo, a.direccion, a.distrito, a.ciudad, a.telefono, CONCAT(e.nombres, ' ', e.apellidos) AS responsable FROM almacenes a LEFT JOIN empleados e ON e.empleado_id = a.responsable_id ORDER BY a.almacen_id",
    ['nombre' => 'Nombre', 'tipo' => 'Tipo', 'direccion' => 'Dirección', 'distrito' => 'Distrito', 'ciudad' => 'Ciudad', 'telefono' => 'Teléfono', 'responsable' => 'Responsable']],
  'categorias' => ['Categorías',
    'SELECT c.nombre, c.descripcion, COUNT(p.producto_id) AS productos FROM categorias c LEFT JOIN productos p ON p.categoria_id = c.categoria_id AND p.activo = 1 GROUP BY c.categoria_id ORDER BY c.nombre',
    ['nombre' => 'Categoría', 'descripcion' => 'Descripción', 'productos' => 'Productos']],
];
if (!isset($defs[$vista])) $vista = 'proveedores';
list($tituloVista, $sql, $cols) = $defs[$vista];
$filas = $pdo->query($sql)->fetchAll();
?>
<h1>Catálogos</h1>
<div class="pestanas">
  <?php foreach ($defs as $k => $d): ?>
    <a href="catalogos.php?v=<?= $k ?>" class="<?= $k === $vista ? 'activa' : '' ?>"><?= e($d[0]) ?></a>
  <?php endforeach; ?>
</div>
<table id="tabla-simple" class="display" style="width:100%">
  <thead><tr><?php foreach ($cols as $c): ?><th><?= e($c) ?></th><?php endforeach; ?></tr></thead>
  <tbody>
  <?php foreach ($filas as $f): ?>
    <tr><?php foreach ($cols as $k => $c): ?><td><?= e($f[$k]) ?></td><?php endforeach; ?></tr>
  <?php endforeach; ?>
  </tbody>
</table>
<?php require __DIR__ . '/includes/footer.php'; ?>
