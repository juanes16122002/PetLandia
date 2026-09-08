<?php
declare(strict_types=1);

/**
 * Middleware de autenticación.
 * Verifica que el usuario esté logueado.
 */
class AuthMiddleware
{
    /**
     * Ejecuta la verificación de autenticación.
     * Si no está autenticado, detiene la ejecución con error 401.
     */
    public static function handle(): void
    {
        $auth = auth();

        if (!$auth->check()) {
            json_error('No autenticado. Debes iniciar sesión.', [], 401);
        }
    }

    /**
     * Versión que retorna true/false en lugar de detener la ejecución.
     * Útil cuando quieres manejar el error manualmente.
     */
    public static function check(): bool
    {
        return auth()->check();
    }
}