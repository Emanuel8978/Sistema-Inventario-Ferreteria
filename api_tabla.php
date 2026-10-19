<?php
/**
 * Endpoint "server-side" para DataTables (tablas grandes: ventas, compras, movimientos).
 * Solo se permiten las tablas, columnas y filtros definidos aquí (lista blanca).
 */
require_once __DIR__ . '/includes/config.php';
header('Content-Type: application/json; charset=utf-8');

$TABLAS = [
    'ventas' => [
        'from'   => 'ventas v
                     JOIN clientes c  ON c.cliente_id  = v.cliente_id
                     JOIN almacenes a ON a.almacen_id  = v.almacen_id
                     LEFT JOIN empleados e ON e.empleado_id = v.empleado_id',
        'select' => [
            'venta_id'           => 'v.venta_id',
            'numero_comprobante' => 'v.numero_comprobante',
            'tipo_comprobante'   => 'v.tipo_comprobante',
            'fecha'              => "DATE_FORMAT(v.fecha_venta, '%Y-%m-%d %H:%i')",
            'cliente'            => 'c.nombre_o_razon_social',
            'almacen'            => 'a.nombre',
            'vendedor'           => "CONCAT(e.nombres, ' ', e.apellidos)",
            'metodo_pago'        => 'v.metodo_pago',
            'estado'             => 'v.estado',
            'total'              => 'v.total',
        ],
        'orden'    => ['fecha' => 'v.fecha_venta', 'cliente' => 'c.nombre_o_razon_social', 'almacen' => 'a.nombre', 'vendedor' => 'e.apellidos'],
        'buscar'   => ['v.numero_comprobante', 'c.nombre_o_razon_social', 'c.numero_documento'],
        'filtros'  => [
            'almacen_id' => 'v.almacen_id = ?',
            'estado'     => 'v.estado = ?',
            'metodo'     => 'v.metodo_pago = ?',
            'desde'      => 'v.fecha_venta >= ?',
            'hasta'      => 'v.fecha_venta < DATE_ADD(?, INTERVAL 1 DAY)',
        ],
        'defecto'  => 'v.fecha_venta DESC, v.venta_id DESC',
    ],
    'compras' => [
        'from'   => 'ordenes_compra o
                     JOIN proveedores pr ON pr.proveedor_id = o.proveedor_id
                     JOIN almacenes a    ON a.almacen_id    = o.almacen_destino_id',
        'select' => [
            'orden_compra_id' => 'o.orden_compra_id',
            'fecha_orden'     => 'o.fecha_orden',
            'proveedor'       => 'pr.razon_social',
            'almacen'         => 'a.nombre',
            'fecha_esperada'  => 'o.fecha_entrega_esperada',
            'condicion_pago'  => 'o.condicion_pago',
            'estado'          => 'o.estado',
            'total'           => '(SELECT COALESCE(SUM(d.cantidad * d.costo_unitario), 0) FROM detalle_orden_compra d WHERE d.orden_compra_id = o.orden_compra_id)',
        ],
        'orden'    => ['proveedor' => 'pr.razon_social', 'almacen' => 'a.nombre', 'fecha_esperada' => 'o.fecha_entrega_esperada'],
        'buscar'   => ['pr.razon_social', 'o.orden_compra_id'],
        'filtros'  => [
            'almacen_id'   => 'o.almacen_destino_id = ?',
            'proveedor_id' => 'o.proveedor_id = ?',
            'estado'       => 'o.estado = ?',
        ],
        'defecto'  => 'o.fecha_orden DESC, o.orden_compra_id DESC',
    ],
    'movimientos' => [
        'from'   => 'movimientos_inventario m
                     JOIN productos p ON p.producto_id = m.producto_id
                     JOIN almacenes a ON a.almacen_id  = m.almacen_id',
        'select' => [
            'movimiento_id'   => 'm.movimiento_id',
            'fecha'           => "DATE_FORMAT(m.fecha_movimiento, '%Y-%m-%d %H:%i')",
            'almacen'         => 'a.nombre',
            'codigo_sku'      => 'p.codigo_sku',
            'producto'        => 'p.nombre',
            'tipo_movimiento' => 'm.tipo_movimiento',
            'cantidad'        => 'm.cantidad',
            'costo_unitario'  => 'm.costo_unitario',
            'saldo_posterior' => 'm.saldo_posterior',
            'documento'       => "CONCAT_WS(' #', m.documento_tipo, m.documento_id)",
            'observacion'     => 'm.observacion',
        ],
        'orden'    => ['fecha' => 'm.fecha_movimiento', 'almacen' => 'a.nombre', 'codigo_sku' => 'p.codigo_sku', 'producto' => 'p.nombre', 'documento' => 'm.documento_tipo'],
        'buscar'   => ['p.codigo_sku', 'p.nombre', 'm.tipo_movimiento', 'm.observacion'],
        'filtros'  => [
            'almacen_id' => 'm.almacen_id = ?',
            'sku'        => 'p.codigo_sku = ?',
            'tipo'       => 'm.tipo_movimiento = ?',
            'desde'      => 'm.fecha_movimiento >= ?',
            'hasta'      => 'm.fecha_movimiento < DATE_ADD(?, INTERVAL 1 DAY)',
        ],
        'defecto'  => 'm.fecha_movimiento DESC, m.movimiento_id DESC',
    ],
];

$t = isset($_GET['t']) ? $_GET['t'] : '';
if (!isset($TABLAS[$t])) { http_response_code(400); echo json_encode(['error' => 'Tabla no válida']); exit; }
$cfg = $TABLAS[$t];

try {
    $pdo   = db();
    $draw  = (int)(isset($_GET['draw']) ? $_GET['draw'] : 0);
    $start = max(0, (int)(isset($_GET['start']) ? $_GET['start'] : 0));
    $len   = (int)(isset($_GET['length']) ? $_GET['length'] : 25);
    if ($len < 1 || $len > 500) $len = 25;

    // Filtros fijos (lista blanca)
    $where = []; $params = [];
    foreach ($cfg['filtros'] as $param => $cond) {
        if (isset($_GET[$param]) && $_GET[$param] !== '') { $where[] = $cond; $params[] = $_GET[$param]; }
    }
    $sqlBase = ' FROM ' . $cfg['from'];
    $whereFijo = $where ? ' WHERE ' . implode(' AND ', $where) : '';

    // Búsqueda global
    $busqueda = '';
    $paramsBusq = $params;
    $q = trim(isset($_GET['search']['value']) ? $_GET['search']['value'] : '');
    if ($q !== '') {
        $or = [];
        foreach ($cfg['buscar'] as $col) { $or[] = "$col LIKE ?"; $paramsBusq[] = '%' . $q . '%'; }
        $busqueda = ($where ? ' AND ' : ' WHERE ') . '(' . implode(' OR ', $or) . ')';
    }

    // Orden (solo columnas permitidas)
    $orderSql = $cfg['defecto'];
    if (isset($_GET['order'][0]['column'], $_GET['columns'])) {
        $i   = (int)$_GET['order'][0]['column'];
        $key = isset($_GET['columns'][$i]['data']) ? $_GET['columns'][$i]['data'] : '';
        $dir = (isset($_GET['order'][0]['dir']) && $_GET['order'][0]['dir'] === 'asc') ? 'ASC' : 'DESC';
        if (isset($cfg['orden'][$key]))       $orderSql = $cfg['orden'][$key] . " $dir";
        elseif (isset($cfg['select'][$key]))  $orderSql = $cfg['select'][$key] . " $dir";
    }

    // Totales
    $stTotal = $pdo->prepare('SELECT COUNT(*)' . $sqlBase . $whereFijo);
    $stTotal->execute($params);
    $total = (int)$stTotal->fetchColumn();

    if ($q !== '') {
        $stF = $pdo->prepare('SELECT COUNT(*)' . $sqlBase . $whereFijo . $busqueda);
        $stF->execute($paramsBusq);
        $filtrado = (int)$stF->fetchColumn();
    } else {
        $filtrado = $total;
    }

    // Datos
    $campos = [];
    foreach ($cfg['select'] as $alias => $expr) { $campos[] = "$expr AS `$alias`"; }
    $sql = 'SELECT ' . implode(', ', $campos) . $sqlBase . $whereFijo . $busqueda
         . ' ORDER BY ' . $orderSql . ' LIMIT ' . $start . ', ' . $len;
    $st = $pdo->prepare($sql);
    $st->execute($paramsBusq);

    echo json_encode([
        'draw' => $draw, 'recordsTotal' => $total, 'recordsFiltered' => $filtrado,
        'data' => $st->fetchAll(),
    ], JSON_UNESCAPED_UNICODE);
} catch (Exception $ex) {
    http_response_code(500);
    echo json_encode(['draw' => (int)(isset($_GET['draw']) ? $_GET['draw'] : 0), 'data' => [], 'recordsTotal' => 0, 'recordsFiltered' => 0, 'error' => 'Error de base de datos']);
}
