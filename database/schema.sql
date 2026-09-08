-- Estructura de la base de datos PetLandia.
-- ============================================================
-- PETLANDIA
-- SCHEMA DE BASE DE DATOS
-- MySQL 8+
-- ============================================================

CREATE DATABASE IF NOT EXISTS petlandia
CHARACTER SET utf8mb4
COLLATE utf8mb4_unicode_ci;

USE petlandia;


-- ============================================================
-- ROLES
-- ============================================================

CREATE TABLE roles (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,

    nombre VARCHAR(50) NOT NULL UNIQUE,

    descripcion VARCHAR(255) NULL,

    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;


-- ============================================================
-- USUARIOS
-- ============================================================

CREATE TABLE usuarios (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,

    rol_id INT UNSIGNED NOT NULL,

    nombre VARCHAR(100) NOT NULL,

    apellido VARCHAR(100) NOT NULL,

    email VARCHAR(150) NOT NULL UNIQUE,

    password_hash VARCHAR(255) NOT NULL,

    telefono VARCHAR(30) NULL,

    activo BOOLEAN NOT NULL DEFAULT TRUE,

    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,

    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
        ON UPDATE CURRENT_TIMESTAMP,

    CONSTRAINT fk_usuarios_roles
        FOREIGN KEY (rol_id)
        REFERENCES roles(id)
        ON UPDATE CASCADE
        ON DELETE RESTRICT,

    INDEX idx_usuarios_rol (rol_id),

    INDEX idx_usuarios_activo (activo)
) ENGINE=InnoDB;


-- ============================================================
-- CATEGORÍAS
-- ============================================================

CREATE TABLE categorias (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,

    nombre VARCHAR(100) NOT NULL UNIQUE,

    descripcion VARCHAR(255) NULL,

    activo BOOLEAN NOT NULL DEFAULT TRUE,

    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,

    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
        ON UPDATE CURRENT_TIMESTAMP,

    INDEX idx_categorias_activo (activo)
) ENGINE=InnoDB;


-- ============================================================
-- PRODUCTOS
-- ============================================================

CREATE TABLE productos (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,

    categoria_id INT UNSIGNED NOT NULL,

    nombre VARCHAR(150) NOT NULL,

    descripcion TEXT NULL,

    precio DECIMAL(12,2) NOT NULL,

    stock INT UNSIGNED NOT NULL DEFAULT 0,

    disponible BOOLEAN NOT NULL DEFAULT TRUE,

    imagen VARCHAR(255) NULL,

    sku VARCHAR(50) NOT NULL UNIQUE,

    activo BOOLEAN NOT NULL DEFAULT TRUE,

    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,

    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
        ON UPDATE CURRENT_TIMESTAMP,

    CONSTRAINT fk_productos_categoria
        FOREIGN KEY (categoria_id)
        REFERENCES categorias(id)
        ON UPDATE CASCADE
        ON DELETE RESTRICT,

    CONSTRAINT chk_productos_precio
        CHECK (precio >= 0),

    CONSTRAINT chk_productos_stock
        CHECK (stock >= 0),

    INDEX idx_productos_categoria (categoria_id),

    INDEX idx_productos_nombre (nombre),

    INDEX idx_productos_precio (precio),

    INDEX idx_productos_disponible (disponible),

    INDEX idx_productos_activo (activo),

    INDEX idx_productos_categoria_disponible (
        categoria_id,
        disponible,
        activo
    )
) ENGINE=InnoDB;


-- ============================================================
-- CARRITOS
-- ============================================================

CREATE TABLE carritos (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,

    usuario_id BIGINT UNSIGNED NOT NULL,

    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,

    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
        ON UPDATE CURRENT_TIMESTAMP,

    CONSTRAINT fk_carritos_usuario
        FOREIGN KEY (usuario_id)
        REFERENCES usuarios(id)
        ON UPDATE CASCADE
        ON DELETE CASCADE,

    UNIQUE KEY uk_carritos_usuario (usuario_id),

    INDEX idx_carritos_usuario (usuario_id)
) ENGINE=InnoDB;


-- ============================================================
-- DETALLE DEL CARRITO
-- ============================================================

CREATE TABLE carrito_detalle (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,

    carrito_id BIGINT UNSIGNED NOT NULL,

    producto_id BIGINT UNSIGNED NOT NULL,

    cantidad INT UNSIGNED NOT NULL,

    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,

    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
        ON UPDATE CURRENT_TIMESTAMP,

    CONSTRAINT fk_carrito_detalle_carrito
        FOREIGN KEY (carrito_id)
        REFERENCES carritos(id)
        ON UPDATE CASCADE
        ON DELETE CASCADE,

    CONSTRAINT fk_carrito_detalle_producto
        FOREIGN KEY (producto_id)
        REFERENCES productos(id)
        ON UPDATE CASCADE
        ON DELETE RESTRICT,

    CONSTRAINT chk_carrito_detalle_cantidad
        CHECK (cantidad > 0),

    UNIQUE KEY uk_carrito_producto (
        carrito_id,
        producto_id
    ),

    INDEX idx_carrito_detalle_carrito (carrito_id),

    INDEX idx_carrito_detalle_producto (producto_id)
) ENGINE=InnoDB;


-- ============================================================
-- PEDIDOS
-- ============================================================

CREATE TABLE pedidos (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,

    usuario_id BIGINT UNSIGNED NOT NULL,

    estado ENUM(
        'PENDIENTE',
        'PAGADO',
        'RECHAZADO'
    ) NOT NULL DEFAULT 'PENDIENTE',

    subtotal DECIMAL(12,2) NOT NULL,

    total DECIMAL(12,2) NOT NULL,

    direccion_envio VARCHAR(255) NULL,

    telefono_contacto VARCHAR(30) NULL,

    observaciones VARCHAR(500) NULL,

    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,

    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
        ON UPDATE CURRENT_TIMESTAMP,

    CONSTRAINT fk_pedidos_usuario
        FOREIGN KEY (usuario_id)
        REFERENCES usuarios(id)
        ON UPDATE CASCADE
        ON DELETE RESTRICT,

    CONSTRAINT chk_pedidos_subtotal
        CHECK (subtotal >= 0),

    CONSTRAINT chk_pedidos_total
        CHECK (total >= 0),

    INDEX idx_pedidos_usuario (usuario_id),

    INDEX idx_pedidos_estado (estado),

    INDEX idx_pedidos_fecha (created_at),

    INDEX idx_pedidos_usuario_estado (
        usuario_id,
        estado
    )
) ENGINE=InnoDB;


-- ============================================================
-- DETALLE DE PEDIDOS
-- ============================================================

CREATE TABLE pedido_detalle (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,

    pedido_id BIGINT UNSIGNED NOT NULL,

    producto_id BIGINT UNSIGNED NOT NULL,

    /*
      Guardamos información histórica del producto.
      Si posteriormente cambia el precio o nombre,
      el pedido antiguo conserva sus datos originales.
    */

    nombre_producto VARCHAR(150) NOT NULL,

    sku_producto VARCHAR(50) NOT NULL,

    precio_unitario DECIMAL(12,2) NOT NULL,

    cantidad INT UNSIGNED NOT NULL,

    subtotal DECIMAL(12,2) NOT NULL,

    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,

    CONSTRAINT fk_pedido_detalle_pedido
        FOREIGN KEY (pedido_id)
        REFERENCES pedidos(id)
        ON UPDATE CASCADE
        ON DELETE CASCADE,

    CONSTRAINT fk_pedido_detalle_producto
        FOREIGN KEY (producto_id)
        REFERENCES productos(id)
        ON UPDATE CASCADE
        ON DELETE RESTRICT,

    CONSTRAINT chk_pedido_detalle_precio
        CHECK (precio_unitario >= 0),

    CONSTRAINT chk_pedido_detalle_cantidad
        CHECK (cantidad > 0),

    CONSTRAINT chk_pedido_detalle_subtotal
        CHECK (subtotal >= 0),

    INDEX idx_pedido_detalle_pedido (pedido_id),

    INDEX idx_pedido_detalle_producto (producto_id)
) ENGINE=InnoDB;


-- ============================================================
-- PAGOS
-- ============================================================

CREATE TABLE pagos (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,

    pedido_id BIGINT UNSIGNED NOT NULL,

    metodo VARCHAR(50) NOT NULL DEFAULT 'TARJETA_SIMULADA',

    estado ENUM(
        'PENDIENTE',
        'APROBADO',
        'RECHAZADO'
    ) NOT NULL DEFAULT 'PENDIENTE',

    monto DECIMAL(12,2) NOT NULL,

    ultimos_digitos CHAR(4) NULL,

    codigo_respuesta VARCHAR(50) NULL,

    mensaje_respuesta VARCHAR(255) NULL,

    fecha_pago DATETIME NULL,

    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,

    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
        ON UPDATE CURRENT_TIMESTAMP,

    CONSTRAINT fk_pagos_pedido
        FOREIGN KEY (pedido_id)
        REFERENCES pedidos(id)
        ON UPDATE CASCADE
        ON DELETE RESTRICT,

    CONSTRAINT chk_pagos_monto
        CHECK (monto >= 0),

    UNIQUE KEY uk_pagos_pedido (pedido_id),

    INDEX idx_pagos_estado (estado),

    INDEX idx_pagos_fecha (fecha_pago)
) ENGINE=InnoDB;


