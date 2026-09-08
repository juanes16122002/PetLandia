<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';

class Category
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::getConnection();
    }

    /**
     * Busca una categoría por ID.
     */
    public function findById(int $id): ?array
    {
        $sql = "SELECT * FROM categorias WHERE id = :id LIMIT 1";
        $stmt = $this->db->prepare($sql);
        $stmt->execute(['id' => $id]);

        $category = $stmt->fetch();
        return $category ?: null;
    }

    /**
     * Busca una categoría por nombre.
     */
    public function findByName(string $name): ?array
    {
        $sql = "SELECT * FROM categorias WHERE nombre = :nombre LIMIT 1";
        $stmt = $this->db->prepare($sql);
        $stmt->execute(['nombre' => $name]);

        $category = $stmt->fetch();
        return $category ?: null;
    }

    /**
     * Lista todas las categorías.
     * Por defecto solo muestra las activas.
     */
    public function all(bool $onlyActive = true): array
    {
        $sql = "SELECT * FROM categorias";

        if ($onlyActive) {
            $sql .= " WHERE activo = 1";
        }

        $sql .= " ORDER BY nombre ASC";

        $stmt = $this->db->query($sql);
        return $stmt->fetchAll();
    }

    /**
     * Lista categorías con la cantidad de productos activos de cada una.
     * Útil para el menú de filtros del catálogo.
     */
    public function allWithProductCount(bool $onlyActive = true): array
    {
        $sql = "SELECT c.*,
                       COUNT(p.id) AS total_productos
                FROM categorias c
                LEFT JOIN productos p ON p.categoria_id = c.id 
                    AND p.activo = 1 
                    AND p.disponible = 1 
                    AND p.stock > 0";

        if ($onlyActive) {
            $sql .= " WHERE c.activo = 1";
        }

        $sql .= " GROUP BY c.id
                  ORDER BY c.nombre ASC";

        $stmt = $this->db->query($sql);
        return $stmt->fetchAll();
    }

    /**
     * Crea una nueva categoría.
     * Retorna el ID de la categoría creada.
     */
    public function create(array $data): int
    {
        $sql = "INSERT INTO categorias (nombre, descripcion, activo)
                VALUES (:nombre, :descripcion, :activo)";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            'nombre'      => $data['nombre'],
            'descripcion' => $data['descripcion'] ?? null,
            'activo'      => $data['activo'] ?? true,
        ]);

        return (int) $this->db->lastInsertId();
    }

    /**
     * Actualiza una categoría.
     */
    public function update(int $id, array $data): bool
    {
        $fields = [];
        $params = ['id' => $id];

        $allowed = ['nombre', 'descripcion', 'activo'];

        foreach ($allowed as $field) {
            if (array_key_exists($field, $data)) {
                $fields[] = "{$field} = :{$field}";
                $params[$field] = $data[$field];
            }
        }

        if (empty($fields)) {
            return false;
        }

        $sql = "UPDATE categorias SET " . implode(', ', $fields) . " WHERE id = :id";
        $stmt = $this->db->prepare($sql);

        return $stmt->execute($params);
    }

    /**
     * Soft delete (desactivar categoría).
     */
    public function deactivate(int $id): bool
    {
        $sql = "UPDATE categorias SET activo = 0 WHERE id = :id";
        $stmt = $this->db->prepare($sql);
        return $stmt->execute(['id' => $id]);
    }

    /**
     * Activa una categoría.
     */
    public function activate(int $id): bool
    {
        $sql = "UPDATE categorias SET activo = 1 WHERE id = :id";
        $stmt = $this->db->prepare($sql);
        return $stmt->execute(['id' => $id]);
    }

    /**
     * Verifica si un nombre de categoría ya existe.
     */
    public function nameExists(string $name, ?int $excludeId = null): bool
    {
        $sql = "SELECT id FROM categorias WHERE nombre = :nombre";

        if ($excludeId !== null) {
            $sql .= " AND id != :exclude_id";
        }

        $stmt = $this->db->prepare($sql);
        $params = ['nombre' => $name];

        if ($excludeId !== null) {
            $params['exclude_id'] = $excludeId;
        }

        $stmt->execute($params);
        return (bool) $stmt->fetch();
    }

    /**
     * Cuenta cuántos productos activos tiene una categoría.
     */
    public function countProducts(int $categoryId): int
    {
        $sql = "SELECT COUNT(*) AS total
                FROM productos
                WHERE categoria_id = :categoria_id
                  AND activo = 1";

        $stmt = $this->db->prepare($sql);
        $stmt->execute(['categoria_id' => $categoryId]);

        $result = $stmt->fetch();
        return (int) ($result['total'] ?? 0);
    }
}