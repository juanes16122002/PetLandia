<?php
declare(strict_types=1);

require_once __DIR__ . '/../services/AuthService.php';
require_once __DIR__ . '/../helpers/response.php';
require_once __DIR__ . '/../helpers/security.php';

class AuthController
{
    private AuthService $authService;

    public function __construct()
    {
        $this->authService = new AuthService();
    }

    /**
     * POST /api/auth.php?action=register
     */
    public function register(): void
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            json_error('Método no permitido.', [], 405);
        }

        CsrfMiddleware::handle();

        $data = json_input();

        $result = $this->authService->register($data);

        if ($result['success']) {
            json_success($result['message'], [
                'user_id'    => $result['user_id'] ?? null,
                'csrf_token' => CsrfMiddleware::generate()
            ], 201);
        }

        json_error(
            $result['message'],
            $result['errors'] ?? [],
            422
        );
    }

    /**
     * POST /api/auth.php?action=login
     */
    public function login(): void
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            json_error('Método no permitido.', [], 405);
        }

        CsrfMiddleware::handle();

        $data = json_input();

        $email    = $data['email'] ?? '';
        $password = $data['password'] ?? '';

        $result = $this->authService->login($email, $password);

        if ($result['success']) {
            json_success($result['message'], [
                'user'       => $result['user'],
                'csrf_token' => CsrfMiddleware::generate()
            ]);
        }

        json_error($result['message'], [], 401);
    }

    /**
     * POST /api/auth.php?action=logout
     */
    public function logout(): void
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            json_error('Método no permitido.', [], 405);
        }

        CsrfMiddleware::handle();

        $this->authService->logout();
        json_success('Sesión cerrada correctamente.');
    }

    /**
     * GET /api/auth.php?action=me
     * Devuelve el usuario autenticado actual.
     */
    public function me(): void
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
            json_error('Método no permitido.', [], 405);
        }

        if (!$this->authService->check()) {
            json_error('No autenticado.', [], 401);
        }

        json_success('Usuario autenticado.', [
            'user'       => $this->authService->user(),
            'csrf_token' => CsrfMiddleware::generate()
        ]);
    }

    /**
     * GET /api/auth.php?action=check
     * Solo verifica si hay sesión activa.
     */
    public function check(): void
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
            json_error('Método no permitido.', [], 405);
        }

        json_success('Estado de autenticación.', [
            'authenticated' => $this->authService->check(),
            'user'          => $this->authService->user(),
            'csrf_token'    => CsrfMiddleware::generate()
        ]);
    }

    /**
     * GET /api/auth.php?action=csrf
     * Devuelve el token CSRF de la sesión (público, para el login/register).
     */
    public function csrf(): void
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
            json_error('Método no permitido.', [], 405);
        }

        json_success('Token CSRF generado.', [
            'csrf_token' => CsrfMiddleware::generate()
        ]);
    }

}