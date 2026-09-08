<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';

class Product
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::getConnection();
    }

    /**
     * Busca un producto por ID.
     */
    public function findById(int $id): ?array
    {
        $sql = "SELECT p.*, c.nombre AS categoria
                FROM productos p
                INNER JOIN categorias c ON c.id = p.categoria_id
                WHERE p.id = :id
                LIMIT 1";

        $stmt = $this->db->prepare($sql);
        $stmt->execute(['id' => $id]);

        $product = $stmt->fetch();
        return $product ?: null;
    }

    /**
     * Busca un producto por SKU.
     */
    public function findBySku(string $sku): ?array
    {
        $sql = "SELECT p.*, c.nombre AS categoria
                FROM productos p
                INNER JOIN categorias c ON c.id = p.categoria_id
                WHERE p.sku = :sku
                LIMIT 1";

        $stmt = $this->db->prepare($sql);
        $stmt->execute(['sku' => $sku]);

        $product = $stmt->fetch();
        return $product ?: null;
    }

    /**
     * Lista productos con filtros opcionales.
     * Filtros soportados: categoria_id, nombre, precio_min, precio_max, solo_disponibles, solo_activos
     */
    public function all(array $filters = [], int $limit = 12, int $offset = 0): array
    {
        $sql = "SELECT p.*, c.nombre AS categoria
                FROM productos p
                INNER JOIN categorias c ON c.id = p.categoria_id
                WHERE 1=1";

        $params = [];

        // Solo activos (por defecto true en catálogo)
        if (!isset($filters['solo_activos']) || $filters['solo_activos'] === true) {
            $sql .= " AND p.activo = 1";
        }

        // Solo disponibles (stock > 0 y disponible = 1)
        if (!empty($filters['solo_disponibles'])) {
            $sql .= " AND p.disponible = 1 AND p.stock > 0";
        }

        // Filtro por categoría
        if (!empty($filters['categoria_id'])) {
            $sql .= " AND p.categoria_id = :categoria_id";
            $params['categoria_id'] = (int) $filters['categoria_id'];
        }

        // Búsqueda por nombre
        if (!empty($filters['nombre'])) {
            $sql .= " AND p.nombre LIKE :nombre";
            $params['nombre'] = '%' . $filters['nombre'] . '%';
        }

        // Rango de precio
        if (isset($filters['precio_min']) && is_numeric($filters['precio_min'])) {
            $sql .= " AND p.precio >= :precio_min";
            $params['precio_min'] = (float) $filters['precio_min'];
        }

        if (isset($filters['precio_max']) && is_numeric($filters['precio_max'])) {
            $sql .= " AND p.precio <= :precio_max";
            $params['precio_max'] = (float) $filters['precio_max'];
        }

        // Orden
        $orderBy = $filters['order_by'] ?? 'p.nombre';
        $orderDir = strtoupper($filters['order_dir'] ?? 'ASC') === 'DESC' ? 'DESC' : 'ASC';

        $allowedOrder = ['p.nombre', 'p.precio', 'p.created_at', 'p.stock'];
        if (!in_array($orderBy, $allowedOrder, true)) {
            $orderBy = 'p.nombre';
        }

        $sql .= " ORDER BY {$orderBy} {$orderDir}";
        $sql .= " LIMIT :limit OFFSET :offset";

        $stmt = $this->db->prepare($sql);

        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value);
        }

        $stmt->bindValue('limit', $limit, PDO::PARAM_INT);
        $stmt->bindValue('offset', $offset, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll();
    }

    /**
     * Cuenta productos según filtros (para paginación).
     */
    public function count(array $filters = []): int
    {
        $sql = "SELECT COUNT(*) AS total
                FROM productos p
                WHERE 1=1";

        $params = [];

        if (!isset($filters['solo_activos']) || $filters['solo_activos'] === true) {
            $sql .= " AND p.activo = 1";
        }

        if (!empty($filters['solo_disponibles'])) {
            $sql .= " AND p.disponible = 1 AND p.stock > 0";
        }

        if (!empty($filters['categoria_id'])) {
            $sql .= " AND p.categoria_id = :categoria_id";
            $params['categoria_id'] = (int) $filters['categoria_id'];
        }

        if (!empty($filters['nombre'])) {
            $sql .= " AND p.nombre LIKE :nombre";
            $params['nombre'] = '%' . $filters['nombre'] . '%';
        }

        if (isset($filters['precio_min']) && is_numeric($filters['precio_min'])) {
            $sql .= " AND p.precio >= :precio_min";
            $params['precio_min'] = (float) $filters['precio_min'];
        }

        if (isset($filters['precio_max']) && is_numeric($filters['precio_max'])) {
            $sql .= " AND p.precio <= :precio_max";
            $params['precio_max'] = (float) $filters['precio_max'];
        }

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);

        $result = $stmt->fetch();
        return (int) ($result['total'] ?? 0);
    }

    /**
     * Crea un nuevo producto.
     * Retorna el ID del producto creado.
     */
    public function create(array $data): int
    {
        $sql = "INSERT INTO productos (
                    categoria_id,
                    nombre,
                    descripcion,
                    precio,
                    stock,
                    disponible,
                    imagen,
                    sku,
                    activo
                ) VALUES (
                    :categoria_id,
                    :nombre,
                    :descripcion,
                    :precio,
                    :stock,
                    :disponible,
                    :imagen,
                    :sku,
                    :activo
                )";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            'categoria_id' => $data['categoria_id'],
            'nombre'       => $data['nombre'],
            'descripcion'  => $data['descripcion'] ?? null,
            'precio'       => $data['precio'],
            'stock'        => $data['stock'] ?? 0,
            'disponible'   => $data['disponible'] ?? true,
            'imagen'       => $data['imagen'] ?? null,
            'sku'          => $data['sku'],
            'activo'       => $data['activo'] ?? true,
        ]);

        return (int) $this->db->lastInsertId();
    }

    /**
     * Actualiza un producto.
     */
    public function update(int $id, array $data): bool
    {
        $fields = [];
        $params = ['id' => $id];

        $allowed = [
            'categoria_id', 'nombre', 'descripcion', 'precio',
            'stock', 'disponible', 'imagen', 'sku', 'activo'
        ];

        foreach ($allowed as $field) {
            if (array_key_exists($field, $data)) {
                $fields[] = "{$field} = :{$field}";
                $params[$field] = $data[$field];
            }
        }

        if (empty($fields)) {
            return false;
        }

        $sql = "UPDATE productos SET " . implode(', ', $fields) . " WHERE id = :id";
        $stmt = $this->db->prepare($sql);

        return $stmt->execute($params);
    }

    /**
     * Actualiza solo el stock de un producto (para el rol de inventario).
     */
    public function updateStock(int $id, int $stock): bool
    {
        $sql = "UPDATE productos 
                SET stock = :stock,
                    disponible = IF(:disponible > 0, 1, 0)
                WHERE id = :id";

        $stmt = $this->db->prepare($sql);
        return $stmt->execute([
            'id'         => $id,
            'stock'      => $stock,
            'disponible' => $stock
        ]);
    }

    /**
     * Reduce el stock de un producto (usado al confirmar un pedido).
     * Retorna true si se pudo reducir, false si no hay stock suficiente.
     */
    public function decreaseStock(int $id, int $quantity): bool
    {
        $sql = "UPDATE productos 
                SET disponible = IF(stock - :c1 > 0, 1, 0),
                    stock = stock - :c2
                WHERE id = :id 
                  AND stock >= :c3
                  AND activo = 1";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            'c1' => $quantity,
            'c2' => $quantity,
            'c3' => $quantity,
            'id' => $id,
        ]);

        return $stmt->rowCount() > 0;
    }

    /**
     * Soft delete (desactivar producto).
     */
    public function deactivate(int $id): bool
    {
        $sql = "UPDATE productos SET activo = 0, disponible = 0 WHERE id = :id";
        $stmt = $this->db->prepare($sql);
        return $stmt->execute(['id' => $id]);
    }

    /**
     * Activa un producto.
     */
    public function activate(int $id): bool
    {
        $sql = "UPDATE productos SET activo = 1 WHERE id = :id";
        $stmt = $this->db->prepare($sql);
        return $stmt->execute(['id' => $id]);
    }

    /**
     * Verifica si un SKU ya existe.
     */
    public function skuExists(string $sku, ?int $excludeId = null): bool
    {
        $sql = "SELECT id FROM productos WHERE sku = :sku";

        if ($excludeId !== null) {
            $sql .= " AND id != :exclude_id";
        }

        $stmt = $this->db->prepare($sql);
        $params = ['sku' => $sku];

        if ($excludeId !== null) {
            $params['exclude_id'] = $excludeId;
        }

        $stmt->execute($params);
        return (bool) $stmt->fetch();
    }

    /**
     * Verifica si un producto tiene stock suficiente.
     */
    public function hasStock(int $id, int $quantity): bool
    {
        $sql = "SELECT stock FROM productos 
                WHERE id = :id AND activo = 1 AND disponible = 1
                LIMIT 1";

        $stmt = $this->db->prepare($sql);
        $stmt->execute(['id' => $id]);

        $product = $stmt->fetch();

        if (!$product) {
            return false;
        }

        return (int) $product['stock'] >= $quantity;
    }
}