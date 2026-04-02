<?php

declare(strict_types=1);

namespace AgVote\Helper;

final class PasswordValidator {
    /**
     * Validate password strength.
     * Returns a French error message string if invalid, or null if valid.
     */
    public static function validate(string $password): ?string {
        if (strlen($password) < 8) {
            return 'Le mot de passe doit contenir au moins 8 caracteres.';
        }
        if (!preg_match('/[A-Z]/', $password)) {
            return 'Le mot de passe doit contenir au moins une lettre majuscule.';
        }
        if (!preg_match('/[0-9]/', $password)) {
            return 'Le mot de passe doit contenir au moins un chiffre.';
        }
        return null;
    }
}
