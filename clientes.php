<?php
$pagina = 'clientes';
$titulo = 'Clientes';
require __DIR__ . '/includes/header.php';
$clientes = db()->query("SELECT c.cliente_id, c.tipo_documento, c.numero_documento, c.nombre_o_razon_social, c.tipo_cliente, c.email,
                                c.telefono, c.distrito, c.ciudad, c.fecha_registro,
                                COUNT(v.venta_id) AS compras, COALESCE(SUM(CASE WHEN v.estado = 'Emitida' THEN v.total END), 0) AS total
                         FROM clientes c LEFT JOIN ventas v ON v.cliente_id = c.cliente_id
                         GROUP BY c.cliente_id ORDER BY c.nombre_o_razon_social")->fetchAll();
?>
<h1>Clientes</h1>
<table id="tabla-simple" class="display" style="width:100%">
  <thead><tr><th>Documento</th><th>Cliente</th><th>Tipo</th><th>Teléfono</th><th>Email</th><th>Ciudad</th><th>Registro</th><th>N.º compras</th><th>Total comprado</th></tr></thead>
  <tbody>
  <?php foreach ($clientes as $c): ?>
    <tr>
      <td><?= e($c['tipo_documento']) ?> <?= e($c['numero_documento']) ?></td>
      <td><?= e($c['nombre_o_razon_social']) ?></td>
      <td><?= e($c['tipo_cliente']) ?></td>
      <td><?= e($c['telefono']) ?></td>
      <td><?= e($c['email']) ?></td>
      <td><?= e($c['distrito']) ?>, <?= e($c['ciudad']) ?></td>
      <td><?= e($c['fecha_registro']) ?></td>
      <td data-order="<?= (int)$c['compras'] ?>"><?= (int)$c['compras'] ?></td>
      <td data-order="<?= e($c['total']) ?>"><?= soles($c['total']) ?></td>
    </tr>
  <?php endforeach; ?>
  </tbody>
</table>
<?php require __DIR__ . '/includes/footer.php'; ?>
