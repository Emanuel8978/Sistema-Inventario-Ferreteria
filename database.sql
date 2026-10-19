-- Sistema de Inventario para Ferretería (FerroAndes)
-- Importar con:  mysql -u root -p < database.sql
CREATE DATABASE IF NOT EXISTS inventario_ferreteria
  CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE inventario_ferreteria;

-- ============ Catálogos ============
CREATE TABLE IF NOT EXISTS categorias (
  categoria_id INT UNSIGNED NOT NULL PRIMARY KEY,
  nombre       VARCHAR(100) NOT NULL,
  descripcion  VARCHAR(255) NULL
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS proveedores (
  proveedor_id       INT UNSIGNED NOT NULL PRIMARY KEY,
  razon_social       VARCHAR(150) NOT NULL,
  ruc                VARCHAR(11)  NULL,
  contacto           VARCHAR(100) NULL,
  telefono           VARCHAR(20)  NULL,
  email              VARCHAR(120) NULL,
  direccion          VARCHAR(200) NULL,
  ciudad             VARCHAR(80)  NULL,
  plazo_entrega_dias INT NULL,
  condicion_pago     VARCHAR(40)  NULL
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS almacenes (
  almacen_id     INT UNSIGNED NOT NULL PRIMARY KEY,
  nombre         VARCHAR(100) NOT NULL,
  tipo           VARCHAR(30)  NULL,
  direccion      VARCHAR(200) NULL,
  distrito       VARCHAR(80)  NULL,
  ciudad         VARCHAR(80)  NULL,
  telefono       VARCHAR(20)  NULL,
  responsable_id INT UNSIGNED NULL
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS empleados (
  empleado_id   INT UNSIGNED NOT NULL PRIMARY KEY,
  nombres       VARCHAR(80)  NOT NULL,
  apellidos     VARCHAR(100) NOT NULL,
  dni           VARCHAR(12)  NULL,
  cargo         VARCHAR(60)  NULL,
  almacen_id    INT UNSIGNED NULL,
  fecha_ingreso DATE NULL,
  email         VARCHAR(120) NULL,
  FOREIGN KEY (almacen_id) REFERENCES almacenes(almacen_id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS clientes (
  cliente_id            INT UNSIGNED NOT NULL PRIMARY KEY,
  tipo_documento        VARCHAR(10)  NOT NULL,
  numero_documento      VARCHAR(15)  NOT NULL,
  nombre_o_razon_social VARCHAR(150) NOT NULL,
  tipo_cliente          VARCHAR(40)  NULL,
  email                 VARCHAR(120) NULL,
  telefono              VARCHAR(20)  NULL,
  direccion             VARCHAR(200) NULL,
  distrito              VARCHAR(80)  NULL,
  ciudad                VARCHAR(80)  NULL,
  fecha_registro        DATE NULL,
  INDEX idx_doc (numero_documento)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS productos (
  producto_id    INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  codigo_sku     VARCHAR(40)  NOT NULL UNIQUE,   -- este valor es el código de barras (CODE128)
  nombre         VARCHAR(150) NOT NULL,
  categoria_id   INT UNSIGNED NULL,
  proveedor_id   INT UNSIGNED NULL,
  unidad_medida  VARCHAR(20)  NOT NULL DEFAULT 'unidad',
  costo_estandar DECIMAL(10,2) NOT NULL DEFAULT 0,
  precio_venta   DECIMAL(10,2) NOT NULL DEFAULT 0,
  activo         TINYINT(1)   NOT NULL DEFAULT 1,
  FOREIGN KEY (categoria_id) REFERENCES categorias(categoria_id) ON DELETE SET NULL,
  FOREIGN KEY (proveedor_id) REFERENCES proveedores(proveedor_id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- ============ Inventario ============
CREATE TABLE IF NOT EXISTS inventario_inicial (
  almacen_id     INT UNSIGNED NOT NULL,
  producto_id    INT UNSIGNED NOT NULL,
  fecha_corte    DATE NOT NULL,
  cantidad       INT NOT NULL,
  costo_unitario DECIMAL(10,2) NOT NULL,
  PRIMARY KEY (almacen_id, producto_id),
  FOREIGN KEY (almacen_id)  REFERENCES almacenes(almacen_id),
  FOREIGN KEY (producto_id) REFERENCES productos(producto_id)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS stock (            -- origen: stock_actual.csv
  almacen_id           INT UNSIGNED NOT NULL,
  producto_id          INT UNSIGNED NOT NULL,
  cantidad             INT NOT NULL DEFAULT 0,
  stock_minimo         INT NOT NULL DEFAULT 0,
  stock_maximo         INT NOT NULL DEFAULT 0,
  cantidad_en_pedido   INT NOT NULL DEFAULT 0,
  costo_promedio       DECIMAL(10,2) NOT NULL DEFAULT 0,
  ultima_actualizacion DATETIME NULL,
  PRIMARY KEY (almacen_id, producto_id),
  FOREIGN KEY (almacen_id)  REFERENCES almacenes(almacen_id) ON DELETE CASCADE,
  FOREIGN KEY (producto_id) REFERENCES productos(producto_id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS movimientos_inventario (
  movimiento_id    BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  fecha_movimiento DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  almacen_id       INT UNSIGNED NOT NULL,
  producto_id      INT UNSIGNED NOT NULL,
  tipo_movimiento  VARCHAR(30) NOT NULL,      -- VENTA, COMPRA, AJUSTE_MERMA, AJUSTE_CONTEO, ENTRADA, SALIDA
  cantidad         INT NOT NULL,              -- con signo: positiva suma, negativa resta
  costo_unitario   DECIMAL(10,2) NOT NULL DEFAULT 0,
  saldo_posterior  INT NOT NULL,
  documento_tipo   VARCHAR(20) NULL,          -- VENTA, RECEPCION, AJUSTE, MANUAL
  documento_id     INT UNSIGNED NULL,
  observacion      VARCHAR(255) NULL,
  INDEX idx_fecha (fecha_movimiento),
  INDEX idx_prod_alm (producto_id, almacen_id),
  INDEX idx_tipo (tipo_movimiento),
  FOREIGN KEY (almacen_id)  REFERENCES almacenes(almacen_id),
  FOREIGN KEY (producto_id) REFERENCES productos(producto_id)
) ENGINE=InnoDB;

-- ============ Compras ============
CREATE TABLE IF NOT EXISTS ordenes_compra (
  orden_compra_id        INT UNSIGNED NOT NULL PRIMARY KEY,
  proveedor_id           INT UNSIGNED NOT NULL,
  almacen_destino_id     INT UNSIGNED NOT NULL,
  empleado_id            INT UNSIGNED NULL,
  fecha_orden            DATE NOT NULL,
  fecha_entrega_esperada DATE NULL,
  estado                 VARCHAR(30) NOT NULL,   -- Pendiente, Recibida, Recibida parcial, Cancelada
  condicion_pago         VARCHAR(40) NULL,
  INDEX idx_fecha (fecha_orden),
  INDEX idx_estado (estado),
  FOREIGN KEY (proveedor_id)       REFERENCES proveedores(proveedor_id),
  FOREIGN KEY (almacen_destino_id) REFERENCES almacenes(almacen_id),
  FOREIGN KEY (empleado_id)        REFERENCES empleados(empleado_id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS detalle_orden_compra (
  detalle_id      INT UNSIGNED NOT NULL PRIMARY KEY,
  orden_compra_id INT UNSIGNED NOT NULL,
  producto_id     INT UNSIGNED NOT NULL,
  cantidad        INT NOT NULL,
  costo_unitario  DECIMAL(10,2) NOT NULL,
  FOREIGN KEY (orden_compra_id) REFERENCES ordenes_compra(orden_compra_id) ON DELETE CASCADE,
  FOREIGN KEY (producto_id)     REFERENCES productos(producto_id)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS recepciones_compra (
  recepcion_id    INT UNSIGNED NOT NULL PRIMARY KEY,
  orden_compra_id INT UNSIGNED NOT NULL,
  almacen_id      INT UNSIGNED NOT NULL,
  fecha_recepcion DATETIME NOT NULL,
  empleado_id     INT UNSIGNED NULL,
  guia_remision   VARCHAR(20) NULL,
  FOREIGN KEY (orden_compra_id) REFERENCES ordenes_compra(orden_compra_id) ON DELETE CASCADE,
  FOREIGN KEY (almacen_id)      REFERENCES almacenes(almacen_id),
  FOREIGN KEY (empleado_id)     REFERENCES empleados(empleado_id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS detalle_recepcion (
  detalle_id        INT UNSIGNED NOT NULL PRIMARY KEY,
  recepcion_id      INT UNSIGNED NOT NULL,
  producto_id       INT UNSIGNED NOT NULL,
  cantidad_recibida INT NOT NULL,
  FOREIGN KEY (recepcion_id) REFERENCES recepciones_compra(recepcion_id) ON DELETE CASCADE,
  FOREIGN KEY (producto_id)  REFERENCES productos(producto_id)
) ENGINE=InnoDB;

-- ============ Ventas ============
CREATE TABLE IF NOT EXISTS ventas (
  venta_id           INT UNSIGNED NOT NULL PRIMARY KEY,
  numero_comprobante VARCHAR(20) NOT NULL UNIQUE,
  tipo_comprobante   VARCHAR(20) NOT NULL,        -- Boleta / Factura
  cliente_id         INT UNSIGNED NOT NULL,
  almacen_id         INT UNSIGNED NOT NULL,
  empleado_id        INT UNSIGNED NULL,
  fecha_venta        DATETIME NOT NULL,
  metodo_pago        VARCHAR(30) NOT NULL,
  estado             VARCHAR(20) NOT NULL,        -- Emitida / Anulada
  subtotal           DECIMAL(12,2) NOT NULL,
  igv                DECIMAL(12,2) NOT NULL,
  total              DECIMAL(12,2) NOT NULL,
  INDEX idx_fecha (fecha_venta),
  INDEX idx_estado (estado),
  FOREIGN KEY (cliente_id)  REFERENCES clientes(cliente_id),
  FOREIGN KEY (almacen_id)  REFERENCES almacenes(almacen_id),
  FOREIGN KEY (empleado_id) REFERENCES empleados(empleado_id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS detalle_venta (
  detalle_id      INT UNSIGNED NOT NULL PRIMARY KEY,
  venta_id        INT UNSIGNED NOT NULL,
  producto_id     INT UNSIGNED NOT NULL,
  cantidad        INT NOT NULL,
  precio_unitario DECIMAL(10,2) NOT NULL,
  descuento_pct   DECIMAL(5,4) NOT NULL DEFAULT 0,   -- fracción: 0.06 = 6 %
  FOREIGN KEY (venta_id)    REFERENCES ventas(venta_id) ON DELETE CASCADE,
  FOREIGN KEY (producto_id) REFERENCES productos(producto_id)
) ENGINE=InnoDB;
