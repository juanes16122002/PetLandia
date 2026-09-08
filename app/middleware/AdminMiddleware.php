<?php
declare(strict_types=1);

/**
 * Middleware de administrador.
 * Verifica que el usuario esté autenticado y sea Administrador.
 */
class AdminMiddleware
{
    /**
     * Ejecuta la verificación de rol Administrador.
     * Si no cumple, detiene la ejecución con error 403.
     */
    public static function handle(): void
    {
        // Primero verificar autenticación
        AuthMiddleware::handle();

        $auth = auth();

        if (!$auth->isAdmin()) {
            json_error('Acceso denegado. Se requieren permisos de administrador.', [], 403);
        }
    }

    /**
     * Versión que retorna true/false.
     */
    public static function check(): bool
    {
        $auth = auth();
        return $auth->check() && $auth->isAdmin();
    }
}