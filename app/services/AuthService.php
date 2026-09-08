<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../models/User.php';
require_once __DIR__ . '/../helpers/security.php';
require_once __DIR__ . '/../helpers/validation.php';

class AuthService
{
    private User $userModel;

    public function __construct()
    {
        $this->userModel = new User();
        $this->startSession();
    }

    /**
     * Inicia la sesión de forma segura.
     */
    private function startSession(): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_name(SESSION_NAME);
            session_start([
                'cookie_httponly' => true,
                'cookie_samesite' => 'Lax',
                'use_strict_mode' => true,
            ]);
        }
    }

    /**
     * Registra un nuevo cliente.
     */
    public function register(array $data): array
    {
        // 1. Validar datos
        $validator = new Validator();
        $validator
            ->required('nombre', $data['nombre'] ?? null)
            ->min('nombre', $data['nombre'] ?? '', 2)
            ->max('nombre', $data['nombre'] ?? '', 100)
            ->required('apellido', $data['apellido'] ?? null)
            ->min('apellido', $data['apellido'] ?? '', 2)
            ->max('apellido', $data['apellido'] ?? '', 100)
            ->required('email', $data['email'] ?? null)
            ->email('email', $data['email'] ?? '')
            ->required('password', $data['password'] ?? null)
            ->password('password', $data['password'] ?? '')
            ->phone('telefono', $data['telefono'] ?? null);

        // Confirmar contraseña
        if (($data['password'] ?? '') !== ($data['password_confirmation'] ?? '')) {
            return [
                'success' => false,
                'message' => 'Las contraseñas no coinciden.',
                'errors'  => ['password_confirmation' => 'Las contraseñas no coinciden.']
            ];
        }

        if ($validator->fails()) {
            return [
                'success' => false,
                'message' => 'Datos inválidos.',
                'errors'  => $validator->errors()
            ];
        }

        // 2. Verificar que el email no exista
        if ($this->userModel->emailExists($data['email'])) {
            return [
                'success' => false,
                'message' => 'El correo electrónico ya está registrado.',
                'errors'  => ['email' => 'Este correo ya está en uso.']
            ];
        }

        // 3. Obtener rol CLIENTE
        $roleId = $this->userModel->getRoleIdByName('CLIENTE');

        if (!$roleId) {
            return [
                'success' => false,
                'message' => 'Error interno: rol de cliente no encontrado.'
            ];
        }

        // 4. Crear usuario
        try {
            $userId = $this->userModel->create([
                'rol_id'        => $roleId,
                'nombre'        => sanitize_string($data['nombre']),
                'apellido'      => sanitize_string($data['apellido']),
                'email'         => strtolower(trim($data['email'])),
                'password_hash' => hash_password($data['password']),
                'telefono'      => !empty($data['telefono']) ? sanitize_string($data['telefono']) : null,
                'activo'        => true
            ]);

            return [
                'success' => true,
                'message' => 'Registro exitoso. Ya puedes iniciar sesión.',
                'user_id' => $userId
            ];

        } catch (Exception $e) {
            error_log('Error en registro: ' . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Ocurrió un error al registrar el usuario.'
            ];
        }
    }

    /**
     * Inicia sesión de un usuario.
     */
    public function login(string $email, string $password): array
    {
        $email = strtolower(trim($email));

        // Validaciones básicas
        if (is_empty($email) || is_empty($password)) {
            return [
                'success' => false,
                'message' => 'Correo y contraseña son obligatorios.'
            ];
        }

        if (!is_valid_email($email)) {
            return [
                'success' => false,
                'message' => 'El correo electrónico no es válido.'
            ];
        }

        // Buscar usuario
        $user = $this->userModel->findByEmail($email);

        if (!$user) {
            return [
                'success' => false,
                'message' => 'Credenciales incorrectas.'
            ];
        }

        // Verificar si está activo
        if (!(bool) $user['activo']) {
            return [
                'success' => false,
                'message' => 'Tu cuenta está desactivada. Contacta al administrador.'
            ];
        }

        // Verificar contraseña
        if (!verify_password($password, $user['password_hash'])) {
            return [
                'success' => false,
                'message' => 'Credenciales incorrectas.'
            ];
        }

        // Iniciar sesión
        $this->createSession($user);

        return [
            'success' => true,
            'message' => 'Inicio de sesión exitoso.',
            'user'    => $this->publicUserData($user)
        ];
    }

    /**
     * Cierra la sesión del usuario.
     */
    public function logout(): void
    {
        $_SESSION = [];

        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(
                session_name(),
                '',
                time() - 42000,
                $params['path'],
                $params['domain'],
                $params['secure'],
                $params['httponly']
            );
        }

        session_destroy();
    }

    /**
     * Crea la sesión del usuario autenticado.
     */
    private function createSession(array $user): void
    {
        regenerate_session();

        $_SESSION['user'] = [
            'id'       => (int) $user['id'],
            'nombre'   => $user['nombre'],
            'apellido' => $user['apellido'],
            'email'    => $user['email'],
            'rol'      => $user['rol'],
            'rol_id'   => (int) $user['rol_id']
        ];

        $_SESSION['authenticated'] = true;
        $_SESSION['login_time']    = time();
    }

    /**
     * Verifica si hay un usuario autenticado.
     */
    public function check(): bool
    {
        return !empty($_SESSION['authenticated']) && !empty($_SESSION['user']['id']);
    }

    /**
     * Obtiene el usuario actualmente autenticado.
     */
    public function user(): ?array
    {
        if (!$this->check()) {
            return null;
        }

        return $_SESSION['user'];
    }

    /**
     * Obtiene solo el ID del usuario autenticado.
     */
    public function id(): ?int
    {
        $user = $this->user();
        return $user ? (int) $user['id'] : null;
    }

    /**
     * Verifica si el usuario autenticado tiene un rol específico.
     */
    public function hasRole(string $role): bool
    {
        $user = $this->user();
        if (!$user) {
            return false;
        }

        return strtoupper($user['rol']) === strtoupper($role);
    }

    /**
     * Verifica si es Administrador.
     */
    public function isAdmin(): bool
    {
        return $this->hasRole('ADMINISTRADOR');
    }

    /**
     * Verifica si es Encargado de Inventario.
     */
    public function isInventory(): bool
    {
        return $this->hasRole('INVENTARIO');
    }

    /**
     * Verifica si es Cliente.
     */
    public function isClient(): bool
    {
        return $this->hasRole('CLIENTE');
    }

    /**
     * Devuelve solo los datos públicos del usuario (sin password_hash).
     */
    private function publicUserData(array $user): array
    {
        return [
            'id'       => (int) $user['id'],
            'nombre'   => $user['nombre'],
            'apellido' => $user['apellido'],
            'email'    => $user['email'],
            'rol'      => $user['rol'],
            'telefono' => $user['telefono'] ?? null
        ];
    }

    /**
     * Requiere que el usuario esté autenticado.
     * Si no lo está, devuelve error (útil en APIs).
     */
    public function requireAuth(): void
    {
        if (!$this->check()) {
            json_error('No autenticado. Inicia sesión.', [], 401);
        }
    }

    /**
     * Requiere que el usuario sea Administrador.
     */
    public function requireAdmin(): void
    {
        $this->requireAuth();

        if (!$this->isAdmin()) {
            json_error('No tienes permisos de administrador.', [], 403);
        }
    }

    /**
     * Requiere que el usuario sea Administrador o Encargado de Inventario.
     */
    public function requireInventoryAccess(): void
    {
        $this->requireAuth();

        if (!$this->isAdmin() && !$this->isInventory()) {
            json_error('No tienes permisos para gestionar inventario.', [], 403);
        }
    }
}