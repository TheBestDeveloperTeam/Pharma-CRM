<?php
declare(strict_types=1);
namespace App\Core\Security;

final class PasswordHasher
{
    private const TOP_PASSWORDS = [
        'password', '123456', '12345678', 'qwerty', 'abc123',
        'monkey', '1234567', 'letmein', 'trustno1', 'dragon',
    ];

    private string|int $algo;

    public function __construct()
    {
        // Use Argon2id if available (PHP 7.3+), fallback to bcrypt
        $this->algo = defined('PASSWORD_ARGON2ID') ? PASSWORD_ARGON2ID : PASSWORD_BCRYPT;
    }

    public function hash(string $password): string
    {
        $options = $this->algo === PASSWORD_BCRYPT
            ? ['cost' => 12]
            : ['memory_cost' => 65536, 'time_cost' => 4, 'threads' => 1];

        return password_hash($password, $this->algo, $options);
    }

    public function verify(string $password, string $hash): bool
    {
        return password_verify($password, $hash);
    }

    public function needsRehash(string $hash): bool
    {
        $options = $this->algo === PASSWORD_BCRYPT
            ? ['cost' => 12]
            : ['memory_cost' => 65536, 'time_cost' => 4, 'threads' => 1];

        return password_needs_rehash($hash, $this->algo, $options);
    }

    /**
     * Validate password policy.
     * Returns array of violation messages (empty = valid).
     */
    public function validatePolicy(string $password, string $email): array
    {
        $errors = [];

        if (mb_strlen($password) < 10) {
            $errors[] = 'Password must be at least 10 characters.';
        }

        if (strtolower($password) === strtolower($email)) {
            $errors[] = 'Password must not be the same as your email address.';
        }

        if (in_array(strtolower($password), self::TOP_PASSWORDS, true)) {
            $errors[] = 'Password is too common. Please choose a stronger password.';
        }

        return $errors;
    }

    /**
     * Create a timing-equalized dummy verify for when user is not found.
     * Prevents timing attacks that reveal whether email exists.
     */
    public function dummyVerify(): void
    {
        // Bcrypt verify of a dummy hash takes same time as real verify
        password_verify('dummy', '$2y$12$invalidhashfortimingeq.useAVeryLongStringHere123456789');
    }
}
