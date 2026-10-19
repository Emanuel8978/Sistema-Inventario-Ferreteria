# Sistema de Inventario · Ferretería FerroAndes

Sistema web de inventario y control comercial para una ferretería con varias sedes (almacén central y tiendas), hecho con **PHP + MySQL**. Cada producto tiene un **código de barras (CODE128)** generado con JsBarcode y las tablas usan **DataTables**. Funciona con lectores de código de barras USB (escriben el código y envían Enter).

Incluye **todos los datos** del archivo `fuente_ferreteria` (15 tablas, ~160 000 registros del año 2025).

## Datos incluidos

| Tabla | Registros | Contenido |
|---|---:|---|
| productos | 108 | SKU (código de barras), precio, costo, categoría, proveedor |
| categorias / proveedores | 9 / 18 | |
| almacenes / empleados | 6 / 32 | Almacén central + 5 tiendas / personal |
| clientes | 400 | Personas, maestros de obra, constructoras, ferreterías |
| stock_actual / inventario_inicial | 648 / 648 | Stock por almacén, mínimos y máximos |
| ventas / detalle_venta | 15 686 / 52 902 | Boletas y facturas |
| ordenes_compra / detalle_orden_compra | 7 887 / 9 336 | Compras a proveedores |
| recepciones_compra / detalle_recepcion | 7 603 / 8 991 | Mercadería recibida |
| movimientos_inventario | 61 826 | Kardex: ventas, compras, mermas y conteos |

## Pantallas

- **Inventario** (`index.php`): resumen (productos, unidades, valor, stock bajo), filtro por almacén, búsqueda escaneando el código de barras, estado del stock y alta/edición/baja de productos (`form.php`).
- **Registrar movimiento** (`movimiento.php`): entrada, salida, merma y ajuste por conteo físico escaneando el código. Bloquea el stock negativo y guarda el movimiento en el kardex.
- **Kardex** (`kardex.php`): historial de los 61 826 movimientos con filtros por SKU, almacén, tipo y fechas.
- **Ventas** (`ventas.php`, `venta.php`): listado filtrable y detalle de cada comprobante con sus productos, descuentos, IGV y total.
- **Compras** (`compras.php`, `compra.php`): órdenes de compra, productos pedidos vs. recibidos y recepciones con guía de remisión.
- **Clientes** (`clientes.php`): clientes con número de compras y total comprado.
- **Catálogos** (`catalogos.php`): proveedores, empleados, almacenes y categorías.
- **Reportes** (`reportes.php`): ventas por mes, almacén y método de pago, top 10 de productos y reposición sugerida según stock mínimo/máximo.
- **Etiquetas** (`etiquetas.php`): hoja imprimible de códigos de barras por categoría.

Las tablas grandes (ventas, compras, kardex) se paginan en el servidor (`api_tabla.php`), por eso cargan rápido.

## Estructura

```
├── index.php  form.php  eliminar.php  etiquetas.php      Inventario y productos
├── movimiento.php  kardex.php                            Movimientos de stock
├── ventas.php  venta.php  compras.php  compra.php        Ventas y compras
├── clientes.php  catalogos.php  reportes.php
├── api.php                  Búsqueda de producto por código (JSON)
├── api_tabla.php            Datos paginados para DataTables (lista blanca)
├── importar_datos.php       Carga los CSV de data/ en MySQL (consola)
├── database.sql             Estructura de la base de datos
├── includes/                config.php, header.php, footer.php
├── scripts/app.js           Código de barras, DataTables y lector
├── styles/estilos.css
└── data/                    Los 15 CSV originales
```

## Instalación

Requisitos: PHP 7.4 o superior con `pdo_mysql`, y MySQL / MariaDB. Sirve XAMPP, Laragon o WAMP.

1. Copia la carpeta en `htdocs` (XAMPP) o `www` (Laragon).
2. Crea la base de datos y las tablas:
   ```bash
   mysql -u root -p < database.sql
   ```
3. Revisa las credenciales en `includes/config.php` (por defecto `root` sin contraseña) o crea `includes/config.local.php`:
   ```php
   <?php
   define('DB_USER', 'mi_usuario');
   define('DB_PASS', 'mi_clave');
   ```
4. Carga todos los datos (tarda unos segundos):
   ```bash
   php importar_datos.php
   ```
5. Abre `http://localhost/Sistema-Inventario-Ferreteria/`.

> jQuery, DataTables y JsBarcode se cargan desde CDN, por lo que se necesita conexión a internet.

## Uso con lector de código de barras

En **Inventario**, **Kardex** y **Registrar movimiento** deja el cursor en el campo de código y escanea la etiqueta. Sin lector puedes escribir el SKU (por ejemplo `FER-0001`) y presionar Enter.

## Notas

- Los movimientos que registres desde el sistema actualizan el stock y quedan en el kardex. Las ventas y compras importadas son un histórico: el sistema no las genera todavía (ver ideas).
- El sistema **no tiene login**: no lo publiques en internet tal cual.

## Ideas para seguir

- Registrar ventas y recepciones de compra desde el sistema (descontando/sumando stock automáticamente).
- Usuarios con roles (administrador, vendedor, almacenero).
- Exportar reportes a Excel / PDF.
