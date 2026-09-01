<?php
namespace Dashboard\Core;

class CsrfProtection
{
    private const TOKEN_NAME = 'csrf_token';
    private const TOKEN_LENGTH = 32;

    public static function ensureSession(): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
    }

    public static function generateToken(): string
    {
        self::ensureSession();
        
        if (!isset($_SESSION[self::TOKEN_NAME])) {
            $_SESSION[self::TOKEN_NAME] = bin2hex(random_bytes(self::TOKEN_LENGTH));
        }
        return $_SESSION[self::TOKEN_NAME];
    }

    public static function getToken(): string
    {
        return self::generateToken();
    }

    public static function getTokenField(): string
    {
        return '<input type="hidden" name="csrf_token" value="' . self::getToken() . '">';
    }

    public static function validate(?string $token): bool
    {
        self::ensureSession();
        
        if ($token === null || !isset($_SESSION[self::TOKEN_NAME])) {
            return false;
        }

        return hash_equals($_SESSION[self::TOKEN_NAME], $token);
    }

    public static function validateOrFail(?string $token): void
    {
        if (!self::validate($token)) {
            http_response_code(403);
            echo json_encode([
                'success' => false,
                'message' => 'Invalid CSRF token'
            ]);
            exit;
        }
    }

    public static function regenerate(): void
    {
        self::ensureSession();
        $_SESSION[self::TOKEN_NAME] = bin2hex(random_bytes(self::TOKEN_LENGTH));
    }
}
