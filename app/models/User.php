<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../helpers/security.php';

class User
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::getConnection();
    }

    /**
     * Busca un usuario por ID.
     */
    public function findById(int $id): ?array
    {
        $sql = "SELECT u.*, r.nombre AS rol
                FROM usuarios u
                INNER JOIN roles r ON r.id = u.rol_id
                WHERE u.id = :id
                LIMIT 1";

        $stmt = $this->db->prepare($sql);
        $stmt->execute(['id' => $id]);

        $user = $stmt->fetch();
        return $user ?: null;
    }

    /**
     * Busca un usuario por email.
     */
    public function findByEmail(string $email): ?array
    {
        $sql = "SELECT u.*, r.nombre AS rol
                FROM usuarios u
                INNER JOIN roles r ON r.id = u.rol_id
                WHERE u.email = :email
                LIMIT 1";

        $stmt = $this->db->prepare($sql);
        $stmt->execute(['email' => $email]);

        $user = $stmt->fetch();
        return $user ?: null;
    }

    /**
     * Verifica si un email ya existe.
     */
    public function emailExists(string $email, ?int $excludeId = null): bool
    {
        $sql = "SELECT id FROM usuarios WHERE email = :email";

        if ($excludeId !== null) {
            $sql .= " AND id != :exclude_id";
        }

        $stmt = $this->db->prepare($sql);
        $params = ['email' => $email];

        if ($excludeId !== null) {
            $params['exclude_id'] = $excludeId;
        }

        $stmt->execute($params);
        return (bool) $stmt->fetch();
    }

    /**
     * Crea un nuevo usuario.
     * Retorna el ID del usuario creado.
     */
    public function create(array $data): int
    {
        $sql = "INSERT INTO usuarios (
                    rol_id,
                    nombre,
                    apellido,
                    email,
                    password_hash,
                    telefono,
                    activo
                ) VALUES (
                    :rol_id,
                    :nombre,
                    :apellido,
                    :email,
                    :password_hash,
                    :telefono,
                    :activo
                )";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            'rol_id'        => $data['rol_id'],
            'nombre'        => $data['nombre'],
            'apellido'      => $data['apellido'],
            'email'         => $data['email'],
            'password_hash' => $data['password_hash'],
            'telefono'      => $data['telefono'] ?? null,
            'activo'        => $data['activo'] ?? true,
        ]);

        return (int) $this->db->lastInsertId();
    }

    /**
     * Actualiza datos de un usuario.
     */
    public function update(int $id, array $data): bool
    {
        $fields = [];
        $params = ['id' => $id];

        $allowed = ['nombre', 'apellido', 'email', 'telefono', 'activo', 'rol_id'];

        foreach ($allowed as $field) {
            if (array_key_exists($field, $data)) {
                $fields[] = "{$field} = :{$field}";
                $params[$field] = $data[$field];
            }
        }

        // Actualizar contraseña solo si se envía
        if (!empty($data['password'])) {
            $fields[] = "password_hash = :password_hash";
            $params['password_hash'] = hash_password($data['password']);
        }

        if (empty($fields)) {
            return false;
        }

        $sql = "UPDATE usuarios SET " . implode(', ', $fields) . " WHERE id = :id";

        $stmt = $this->db->prepare($sql);
        return $stmt->execute($params);
    }

    /**
     * Soft delete (desactivar usuario).
     */
    public function deactivate(int $id): bool
    {
        $sql = "UPDATE usuarios SET activo = 0 WHERE id = :id";
        $stmt = $this->db->prepare($sql);
        return $stmt->execute(['id' => $id]);
    }

    /**
     * Activa un usuario.
     */
    public function activate(int $id): bool
    {
        $sql = "UPDATE usuarios SET activo = 1 WHERE id = :id";
        $stmt = $this->db->prepare($sql);
        return $stmt->execute(['id' => $id]);
    }

    /**
     * Obtiene el ID de un rol por su nombre.
     */
    public function getRoleIdByName(string $roleName): ?int
    {
        $sql = "SELECT id FROM roles WHERE nombre = :nombre LIMIT 1";
        $stmt = $this->db->prepare($sql);
        $stmt->execute(['nombre' => $roleName]);

        $result = $stmt->fetch();
        return $result ? (int) $result['id'] : null;
    }

    /**
     * Lista usuarios (útil para panel admin).
     */
    public function all(int $limit = 50, int $offset = 0): array
    {
        $sql = "SELECT u.id, u.nombre, u.apellido, u.email, u.telefono,
                       u.activo, u.created_at, r.nombre AS rol
                FROM usuarios u
                INNER JOIN roles r ON r.id = u.rol_id
                ORDER BY u.created_at DESC
                LIMIT :limit OFFSET :offset";

        $stmt = $this->db->prepare($sql);
        $stmt->bindValue('limit', $limit, PDO::PARAM_INT);
        $stmt->bindValue('offset', $offset, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll();
    }

    /**
     * Verifica si el usuario tiene un rol específico.
     */
    public function hasRole(array $user, string $roleName): bool
    {
        return isset($user['rol']) && strtoupper($user['rol']) === strtoupper($roleName);
    }

    /**
     * Verifica si el usuario es administrador.
     */
    public function isAdmin(array $user): bool
    {
        return $this->hasRole($user, 'ADMINISTRADOR');
    }

    /**
     * Verifica si el usuario es encargado de inventario.
     */
    public function isInventory(array $user): bool
    {
        return $this->hasRole($user, 'INVENTARIO');
    }

    /**
     * Verifica si el usuario es cliente.
     */
    public function isClient(array $user): bool
    {
        return $this->hasRole($user, 'CLIENTE');
    }
}