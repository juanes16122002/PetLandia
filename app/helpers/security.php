<?php
declare(strict_types=1);

/**
 * Helpers de seguridad para PetLandia
 */

/**
 * Hashea una contraseña de forma segura.
 */
function hash_password(string $password): string
{
    return password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);
}

/**
 * Verifica una contraseña contra su hash.
 */
function verify_password(string $password, string $hash): bool
{
    return password_verify($password, $hash);
}

/**
 * Escapa una cadena para prevenir XSS.
 */
function e(?string $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/**
 * Limpia y sanitiza un string de entrada.
 */
function sanitize_string(?string $value): string
{
    $value = trim((string) $value);
    $value = strip_tags($value);
    return $value;
}

/**
 * Genera un token CSRF.
 */
function generate_csrf_token(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }

    return $_SESSION['csrf_token'];
}

/**
 * Valida un token CSRF.
 */
function validate_csrf_token(?string $token): bool
{
    if (empty($token) || empty($_SESSION['csrf_token'])) {
        return false;
    }

    return hash_equals($_SESSION['csrf_token'], $token);
}

/**
 * Regenera el ID de sesión (previene session fixation).
 */
function regenerate_session(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        session_regenerate_id(true);
    }
}