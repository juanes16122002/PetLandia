<?php
declare(strict_types=1);

/**
 * Middleware de inventario.
 * Permite acceso a Administradores y Encargados de Inventario.
 */
class InventoryMiddleware
{
    /**
     * Ejecuta la verificación de acceso a inventario.
     * Si no cumple, detiene la ejecución con error 403.
     */
    public static function handle(): void
    {
        // Primero verificar autenticación
        AuthMiddleware::handle();

        $auth = auth();

        if (!$auth->isAdmin() && !$auth->isInventory()) {
            json_error(
                'Acceso denegado. Se requieren permisos de administrador o encargado de inventario.',
                [],
                403
            );
        }
    }

    /**
     * Versión que retorna true/false.
     */
    public static function check(): bool
    {
        $auth = auth();
        return $auth->check() && ($auth->isAdmin() || $auth->isInventory());
    }
}