<?php
namespace App\Core;

final class Csrf
{
    private const TOKEN_KEY = '_csrf_token';
    private const FORM_FIELD = '_csrf';
    private const HEADER_KEY = 'HTTP_X_CSRF_TOKEN';

    public static function token(): string
    {
        Auth::init();
        if (empty($_SESSION[self::TOKEN_KEY]) || !is_string($_SESSION[self::TOKEN_KEY])) {
            $_SESSION[self::TOKEN_KEY] = bin2hex(random_bytes(32));
        }

        return $_SESSION[self::TOKEN_KEY];
    }

    public static function fieldHtml(): string
    {
        $token = self::token();
        return '<input type="hidden" name="' . self::FORM_FIELD . '" value="' . htmlspecialchars($token, ENT_QUOTES, 'UTF-8') . '">';
    }

    public static function validateRequest(): bool
    {
        Auth::init();
        $sessionToken = $_SESSION[self::TOKEN_KEY] ?? '';
        if (!is_string($sessionToken) || $sessionToken === '') {
            return false;
        }

        $submitted = $_POST[self::FORM_FIELD] ?? ($_SERVER[self::HEADER_KEY] ?? '');
        if (!is_string($submitted) || $submitted === '') {
            return false;
        }

        return hash_equals($sessionToken, $submitted);
    }
}
