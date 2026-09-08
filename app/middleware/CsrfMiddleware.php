<?php
declare(strict_types=1);

/**
 * Middleware de protección CSRF.
 * Produce el token cuando hace falta y valida que la petición
 * provenga de nuestro propio frontend en operaciones de mutación.
 */
class CsrfMiddleware
{
    /**
     * Obtiene (y crea si no existe) el token CSRF de la sesión actual.
     */
    public static function token(): ?string
    {
        $token = $_SESSION['csrf_token'] ?? null;

        if (empty($token)) {
            return null;
        }

        return $token;
    }

    /**
     * Genera un token CSRF para la sesión si aún no existe.
     */
    public static function generate(): string
    {
        if (empty($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }

        return $_SESSION['csrf_token'];
    }

    /**
     * Ejecuta la validación CSRF.
     * Rechaza (419) si la petición es una mutación y no trae un token válido.
     */
    public static function handle(): void
    {
        if (!self::isMutation()) {
            return;
        }

        $token = self::retrieveToken();

        if (empty($token) || !validate_csrf_token($token)) {
            json_error(
                'Token CSRF inválido o ausente. Recarga la página y vuelve a intentarlo.',
                [],
                419
            );
        }
    }

    /**
     * Determina si la petición es una mutación (requiere token CSRF).
     */
    private static function isMutation(): bool
    {
        $method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');

        return in_array($method, ['POST', 'PUT', 'PATCH', 'DELETE'], true);
    }

    /**
     * Extrae el token del header, del body o de los parámetros de la URL.
     */
    private static function retrieveToken(): ?string
    {
        // Header X-CSRF-Token (recomendado para JSON)
        $fromHeader = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? null;

        if (!empty($fromHeader)) {
            return $fromHeader;
        }

        // Campo de formulario o body JSON
        if (!empty($_POST['csrf_token'])) {
            return $_POST['csrf_token'];
        }

        // Body JSON no urlencoded
        $contentType = $_SERVER['CONTENT_TYPE'] ?? '';
        if (stripos($contentType, 'application/json') !== false) {
            $json = file_get_contents('php://input');
            $data = json_decode($json, true);
            if (is_array($data) && !empty($data['csrf_token'])) {
                return $data['csrf_token'];
            }
        }

        // Query string (conveniencia)
        return $_GET['csrf_token'] ?? null;
    }
}
