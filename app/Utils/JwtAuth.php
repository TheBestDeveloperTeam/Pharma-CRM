<?php
namespace App\Utils;

use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Exception;

class JwtAuth
{
    private static function getSecret(): string
    {
        return $_ENV['JWT_SECRET'] ?? 'fallback_secret_do_not_use_in_prod';
    }

    public static function generateToken(array $payload, int $expiresIn = 900): string
    {
        $time = time();
        $token = [
            'iat' => $time,
            'exp' => $time + $expiresIn,
            'iss' => $_ENV['APP_URL'] ?? 'http://localhost',
        ];

        $token = array_merge($token, $payload);
        return JWT::encode($token, self::getSecret(), 'HS256');
    }

    public static function decodeToken(string $jwt): array
    {
        try {
            $decoded = JWT::decode($jwt, new Key(self::getSecret(), 'HS256'));
            return (array) $decoded;
        } catch (Exception $e) {
            return []; // Invalid token
        }
    }
}
