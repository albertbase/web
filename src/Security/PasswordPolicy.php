<?php

namespace App\Security;

final class PasswordPolicy
{
    public const MIN_LENGTH = 8;
    public const REGEX = '/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[\W_]).+$/';
    public const MESSAGE = 'Password must be at least 8 characters and include an uppercase letter, a lowercase letter, a number, and a special character.';

    private function __construct()
    {
    }

    public static function isValid(string $password): bool
    {
        return strlen($password) >= self::MIN_LENGTH
            && preg_match(self::REGEX, $password) === 1;
    }
}
