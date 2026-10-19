/* Sistema de Inventario con Código de Barras
   - Genera códigos CODE128 con JsBarcode
   - Tablas con búsqueda/orden/paginación con DataTables (en memoria y "server-side" para tablas grandes)
   - Lector de código de barras (los lectores USB actúan como teclado + Enter) */

function renderBarcodes() {
  document.querySelectorAll('svg.barcode').forEach(function (el) {
    var codigo = el.getAttribute('data-codigo');
    if (!codigo) return;
    try {
      JsBarcode(el, codigo, { format: 'CODE128', width: 1.6, height: 38, fontSize: 12, margin: 0, displayValue: true });
    } catch (e) { /* código inválido: se omite */ }
  });
}

var IDIOMA = {
  search: 'Buscar:', lengthMenu: 'Mostrar _MENU_ registros',
  info: 'Mostrando _START_ a _END_ de _TOTAL_', infoEmpty: 'Sin resultados',
  zeroRecords: 'No se encontraron registros', infoFiltered: '(filtrado de _MAX_)',
  processing: 'Cargando…',
  paginate: { first: 'Primero', last: 'Último', next: 'Siguiente', previous: 'Anterior' }
};

function esc(v) {
  return String(v == null ? '' : v).replace(/[&<>"']/g, function (c) {
    return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c];
  });
}
function soles(v) { return 'S/ ' + Number(v || 0).toLocaleString('es-PE', { minimumFractionDigits: 2, maximumFractionDigits: 2 }); }
function badge(texto, mapa) { return '<span class="badge badge-' + (mapa[texto] || 'verde') + '">' + esc(texto) + '</span>'; }
function derecha(r) { return { data: r, className: 'der' }; }

/* Definición de columnas de las tablas grandes (coinciden con api_tabla.php) */
var TABLAS_SS = {
  ventas: {
    order: [[2, 'desc']],
    columns: [
      { data: 'numero_comprobante', render: function (d, t, r) { return '<a href="venta.php?id=' + r.venta_id + '">' + esc(d) + '</a>'; } },
      { data: 'tipo_comprobante', render: esc },
      { data: 'fecha' },
      { data: 'cliente', render: esc },
      { data: 'almacen', render: esc },
      { data: 'vendedor', render: esc },
      { data: 'metodo_pago', render: esc },
      { data: 'estado', render: function (d) { return badge(d, { Emitida: 'verde', Anulada: 'rojo' }); } },
      { data: 'total', className: 'der', render: function (d) { return soles(d); } }
    ]
  },
  compras: {
    order: [[1, 'desc']],
    columns: [
      { data: 'orden_compra_id', render: function (d) { return '<a href="compra.php?id=' + d + '">OC-' + d + '</a>'; } },
      { data: 'fecha_orden' },
      { data: 'proveedor', render: esc },
      { data: 'almacen', render: esc },
      { data: 'fecha_esperada' },
      { data: 'condicion_pago', render: esc },
      { data: 'estado', render: function (d) { return badge(d, { 'Recibida': 'verde', 'Recibida parcial': 'amarillo', 'Pendiente': 'amarillo', 'Cancelada': 'rojo' }); } },
      { data: 'total', className: 'der', render: function (d) { return soles(d); } }
    ]
  },
  movimientos: {
    order: [[0, 'desc']],
    columns: [
      { data: 'fecha' },
      { data: 'almacen', render: esc },
      { data: 'codigo_sku', render: function (d) { return '<a href="kardex.php?sku=' + encodeURIComponent(d) + '">' + esc(d) + '</a>'; } },
      { data: 'producto', render: esc },
      { data: 'tipo_movimiento', render: esc },
      { data: 'cantidad', className: 'der', render: function (d) { return (d > 0 ? '+' : '') + d; } },
      { data: 'costo_unitario', className: 'der', render: function (d) { return soles(d); } },
      { data: 'saldo_posterior', className: 'der' },
      { data: 'documento', defaultContent: '', render: esc },
      { data: 'observacion', defaultContent: '', render: esc }
    ]
  }
};

function initTablasServidor() {
  var FILTROS = ['almacen_id', 'proveedor_id', 'estado', 'metodo', 'desde', 'hasta', 'sku', 'tipo'];
  document.querySelectorAll('table.ss').forEach(function (el) {
    var cfg = TABLAS_SS[el.getAttribute('data-tabla')];
    if (!cfg) return;
    var params = new URLSearchParams(window.location.search);
    $(el).DataTable({
      serverSide: true, processing: true, pageLength: 25, searchDelay: 400,
      order: cfg.order, columns: cfg.columns, language: IDIOMA,
      ajax: {
        url: 'api_tabla.php',
        data: function (d) {
          d.t = el.getAttribute('data-tabla');
          FILTROS.forEach(function (k) { if (params.get(k)) d[k] = params.get(k); });
        }
      }
    });
  });
}

/* Tablas simples (clientes, catálogos) */
function initTablaSimple() {
  var $t = $('#tabla-simple');
  if ($t.length) $t.DataTable({ pageLength: 25, language: IDIOMA });
}

function initTabla() {
  var $t = $('#tabla-productos');
  if (!$t.length) return null;
  var tabla = $t.DataTable({
    pageLength: 25,
    order: [[1, 'asc']],
    columnDefs: [{ orderable: false, targets: [0, 7] }],
    language: IDIOMA
  });
  return tabla;
}

function initScanner(tabla) {
  var scan = document.getElementById('scan');
  if (!scan || !tabla) return;
  scan.addEventListener('keydown', function (ev) {
    if (ev.key !== 'Enter') return;
    ev.preventDefault();
    var valor = scan.value.trim();
    if (!valor) { tabla.search('').draw(); return; }
    tabla.search(valor).draw();
    var filas = $('#tabla-productos tbody tr[data-sku]');
    if (filas.length === 1) {
      filas.addClass('resaltado');
      setTimeout(function () { filas.removeClass('resaltado'); }, 2000);
    }
    scan.select();   // listo para el siguiente escaneo
  });
}

function initFormProducto() {
  var sku = document.getElementById('codigo_sku');
  var nombre = document.getElementById('nombre');
  var precio = document.getElementById('precio_venta');
  var svg = document.getElementById('prev-barcode');
  if (!svg || !sku) return;

  function actualizar() {
    var codigo = sku.value.trim().toUpperCase() || 'FER-0000';
    document.getElementById('prev-nombre').textContent = nombre.value || 'Nombre del producto';
    document.getElementById('prev-precio').textContent = 'S/ ' + (parseFloat(precio.value) || 0).toFixed(2);
    svg.setAttribute('data-codigo', codigo);
    try { JsBarcode(svg, codigo, { format: 'CODE128', width: 1.8, height: 50, fontSize: 14, margin: 0 }); } catch (e) {}
  }
  [sku, nombre, precio].forEach(function (el) { el.addEventListener('input', actualizar); });
  actualizar();
}

function initMovimiento() {
  var codigo = document.getElementById('codigo');
  var form = document.getElementById('form-mov');
  if (!codigo || !form) return;
  var info = document.getElementById('info-producto');
  var almacen = document.getElementById('almacen_id');
  var cantidad = document.getElementById('cantidad');
  var tipo = document.getElementById('tipo');
  var etiquetaCant = document.getElementById('lbl-cantidad');

  function buscar() {
    var c = codigo.value.trim();
    if (!c) { info.classList.add('oculto'); return; }
    fetch('api.php?codigo=' + encodeURIComponent(c) + '&almacen_id=' + encodeURIComponent(almacen.value))
      .then(function (r) { return r.json(); })
      .then(function (d) {
        info.classList.remove('oculto', 'error');
        if (!d.ok) { info.classList.add('error'); info.textContent = d.error; return; }
        var p = d.producto;
        info.innerHTML = '';
        var t = document.createElement('strong'); t.textContent = p.nombre;
        var s = document.createElement('div');
        s.textContent = 'Stock en este almacén: ' + p.stock + ' ' + p.unidad_medida + ' (mínimo ' + p.stock_minimo + ')';
        info.appendChild(t); info.appendChild(s);
        cantidad.focus(); cantidad.select();
      })
      .catch(function () { info.classList.remove('oculto'); info.classList.add('error'); info.textContent = 'Error de conexión.'; });
  }

  // El lector envía Enter al terminar: buscamos en vez de enviar el formulario
  codigo.addEventListener('keydown', function (ev) {
    if (ev.key === 'Enter') { ev.preventDefault(); buscar(); }
  });
  almacen.addEventListener('change', buscar);
  tipo.addEventListener('change', function () {
    etiquetaCant.textContent = tipo.value === 'AJUSTE_CONTEO' ? 'Cantidad contada (stock final)' : 'Cantidad';
  });
  if (codigo.value) buscar();
}

document.addEventListener('DOMContentLoaded', function () {
  renderBarcodes();
  var tabla = initTabla();
  initTablasServidor();
  initTablaSimple();
  initScanner(tabla);
  initFormProducto();
  initMovimiento();
});
