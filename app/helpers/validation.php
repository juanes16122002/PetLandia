<?php
declare(strict_types=1);

/**
 * Helpers de validación para PetLandia
 */

/**
 * Verifica si un valor está vacío (después de trim).
 */
function is_empty(?string $value): bool
{
    return trim((string) $value) === '';
}

/**
 * Valida un correo electrónico.
 */
function is_valid_email(string $email): bool
{
    return (bool) filter_var($email, FILTER_VALIDATE_EMAIL);
}

/**
 * Valida longitud mínima.
 */
function has_min_length(string $value, int $min): bool
{
    return mb_strlen(trim($value)) >= $min;
}

/**
 * Valida longitud máxima.
 */
function has_max_length(string $value, int $max): bool
{
    return mb_strlen(trim($value)) <= $max;
}

/**
 * Valida que sea un número entero positivo.
 */
function is_positive_integer($value): bool
{
    return filter_var($value, FILTER_VALIDATE_INT) !== false
        && (int) $value > 0;
}

/**
 * Valida que sea un número decimal >= 0.
 */
function is_valid_price($value): bool
{
    return is_numeric($value) && (float) $value >= 0;
}

/**
 * Valida un teléfono simple (solo dígitos, espacios, +, -).
 */
function is_valid_phone(?string $phone): bool
{
    if ($phone === null || trim($phone) === '') {
        return true; // opcional
    }

    return (bool) preg_match('/^[\d\s\+\-\(\)]{7,20}$/', trim($phone));
}

/**
 * Valida contraseña (mínimo 8 caracteres).
 */
function is_valid_password(string $password): bool
{
    return has_min_length($password, 8);
}

/**
 * Clase simple para acumular errores de validación.
 */
class Validator
{
    private array $errors = [];

    public function required(string $field, ?string $value, string $message = null): self
    {
        if (is_empty($value)) {
            $this->errors[$field] = $message ?? "El campo {$field} es obligatorio.";
        }
        return $this;
    }

    public function email(string $field, string $value, string $message = null): self
    {
        if (!is_empty($value) && !is_valid_email($value)) {
            $this->errors[$field] = $message ?? "El correo electrónico no es válido.";
        }
        return $this;
    }

    public function min(string $field, string $value, int $min, string $message = null): self
    {
        if (!is_empty($value) && !has_min_length($value, $min)) {
            $this->errors[$field] = $message ?? "El campo {$field} debe tener al menos {$min} caracteres.";
        }
        return $this;
    }

    public function max(string $field, string $value, int $max, string $message = null): self
    {
        if (!is_empty($value) && !has_max_length($value, $max)) {
            $this->errors[$field] = $message ?? "El campo {$field} no puede superar los {$max} caracteres.";
        }
        return $this;
    }

    public function password(string $field, string $value, string $message = null): self
    {
        if (!is_empty($value) && !is_valid_password($value)) {
            $this->errors[$field] = $message ?? "La contraseña debe tener al menos 8 caracteres.";
        }
        return $this;
    }

    public function phone(string $field, ?string $value, string $message = null): self
    {
        if (!is_valid_phone($value)) {
            $this->errors[$field] = $message ?? "El teléfono no es válido.";
        }
        return $this;
    }

    public function fails(): bool
    {
        return !empty($this->errors);
    }

    public function errors(): array
    {
        return $this->errors;
    }

    public function firstError(): ?string
    {
        return $this->errors ? reset($this->errors) : null;
    }
}