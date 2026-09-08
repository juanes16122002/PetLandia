<?php
declare(strict_types=1);

/**
 * Helpers de respuesta para PetLandia
 */

/**
 * Envía una respuesta JSON y termina la ejecución.
 */
function json_response(array $data, int $statusCode = 200): void
{
    http_response_code($statusCode);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

/**
 * Respuesta de éxito.
 */
function json_success(string $message = 'OK', array $data = [], int $statusCode = 200): void
{
    json_response([
        'success' => true,
        'message' => $message,
        'data'    => $data
    ], $statusCode);
}

/**
 * Respuesta de error.
 */
function json_error(string $message = 'Error', array $errors = [], int $statusCode = 400): void
{
    json_response([
        'success' => false,
        'message' => $message,
        'errors'  => $errors
    ], $statusCode);
}

/**
 * Redirección simple.
 */
function redirect(string $url): void
{
    header('Location: ' . $url);
    exit;
}

/**
 * Redirección con mensaje flash (usando sesión).
 */
function redirect_with(string $url, string $type, string $message): void
{
    $_SESSION['flash'] = [
        'type'    => $type,    // success | error | warning | info
        'message' => $message
    ];
    redirect($url);
}

/**
 * Obtiene y limpia el mensaje flash.
 */
function get_flash(): ?array
{
    if (!empty($_SESSION['flash'])) {
        $flash = $_SESSION['flash'];
        unset($_SESSION['flash']);
        return $flash;
    }
    return null;
}