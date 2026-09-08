-- Datos iniciales de PetLandia.
-- ============================================================
-- PETLANDIA
-- DATOS INICIALES
-- ============================================================

USE petlandia;


-- ============================================================
-- ROLES
-- ============================================================

INSERT INTO roles (
    nombre,
    descripcion
)
VALUES
(
    'CLIENTE',
    'Cliente que puede comprar productos y consultar sus pedidos'
),
(
    'ADMINISTRADOR',
    'Administrador con acceso completo al sistema'
),
(
    'INVENTARIO',
    'Encargado de gestionar stock y disponibilidad'
);


-- ============================================================
-- CATEGORÍAS
-- ============================================================

INSERT INTO categorias (
    nombre,
    descripcion
)
VALUES
(
    'Alimento',
    'Alimentos para perros, gatos y otras mascotas'
),
(
    'Juguetes',
    'Juguetes y productos de entretenimiento'
),
(
    'Higiene',
    'Productos de higiene y cuidado'
),
(
    'Accesorios',
    'Accesorios para mascotas'
);


-- ============================================================
-- PRODUCTOS DE PRUEBA
-- ============================================================

INSERT INTO productos (
    categoria_id,
    nombre,
    descripcion,
    precio,
    stock,
    disponible,
    imagen,
    sku
)
VALUES
(
    1,
    'Alimento Premium para Perros 15kg',
    'Alimento premium para perros adultos.',
    34990.00,
    20,
    TRUE,
    NULL,
    'PET-ALI-001'
),
(
    2,
    'Pelota de Goma para Perros',
    'Pelota resistente para juegos y entretenimiento.',
    5990.00,
    30,
    TRUE,
    NULL,
    'PET-JUG-001'
),
(
    3,
    'Shampoo para Perros 500ml',
    'Shampoo especial para el cuidado del pelaje.',
    7990.00,
    15,
    TRUE,
    NULL,
    'PET-HIG-001'
),
(
    4,
    'Collar Ajustable para Perros',
    'Collar ajustable de alta resistencia.',
    9990.00,
    25,
    TRUE,
    NULL,
    'PET-ACC-001'
),
(
    4,
    'Correa para Perros 2m',
    'Correa resistente de dos metros.',
    12990.00,
    18,
    TRUE,
    NULL,
    'PET-ACC-002'
),
(
    2,
    'Juguete Mordedor para Perros',
    'Mordedor resistente para entretenimiento.',
    8990.00,
    22,
    TRUE,
    NULL,
    'PET-JUG-002'
),
(
    3,
    'Cepillo para Mascotas',
    'Cepillo para cuidado del pelaje.',
    6990.00,
    12,
    TRUE,
    NULL,
    'PET-HIG-002'
),
(
    1,
    'Alimento para Gatos 10kg',
    'Alimento completo para gatos adultos.',
    29990.00,
    15,
    TRUE,
    NULL,
    'PET-ALI-002'
);


-- ============================================================
-- FIN DE LOS DATOS INICIALES
-- ============================================================