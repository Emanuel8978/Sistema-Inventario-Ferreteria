<?php
require_once __DIR__ . '/config.php';
$pagina = isset($pagina) ? $pagina : '';
$titulo = isset($titulo) ? $titulo : 'Inventario';
$flash  = flash_get();
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= e($titulo) ?> · Ferretería FerroAndes</title>
  <link rel="stylesheet" href="https://cdn.datatables.net/1.13.8/css/jquery.dataTables.min.css">
  <link rel="stylesheet" href="styles/estilos.css">
</head>
<body>
<header class="topbar">
  <div class="brand">🔧 Ferretería FerroAndes <span>Inventario</span></div>
  <nav>
    <a href="index.php"       class="<?= $pagina === 'inicio' ? 'activo' : '' ?>">Inventario</a>
    <a href="movimiento.php"  class="<?= $pagina === 'movimiento' ? 'activo' : '' ?>">Registrar movimiento</a>
    <a href="kardex.php"      class="<?= $pagina === 'kardex' ? 'activo' : '' ?>">Kardex</a>
    <a href="ventas.php"      class="<?= $pagina === 'ventas' ? 'activo' : '' ?>">Ventas</a>
    <a href="compras.php"     class="<?= $pagina === 'compras' ? 'activo' : '' ?>">Compras</a>
    <a href="clientes.php"    class="<?= $pagina === 'clientes' ? 'activo' : '' ?>">Clientes</a>
    <a href="catalogos.php"   class="<?= $pagina === 'catalogos' ? 'activo' : '' ?>">Catálogos</a>
    <a href="reportes.php"    class="<?= $pagina === 'reportes' ? 'activo' : '' ?>">Reportes</a>
    <a href="etiquetas.php"   class="<?= $pagina === 'etiquetas' ? 'activo' : '' ?>">Etiquetas</a>
  </nav>
</header>
<main class="contenedor">
<?php if ($flash): ?>
  <div class="alerta alerta-<?= e($flash['tipo']) ?>"><?= e($flash['texto']) ?></div>
<?php endif; ?>
