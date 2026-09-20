<?php
declare(strict_types=1);
namespace App\Core\Security;

final class Jwt
{
    public function __construct(private array $keys, private string $activeKid, private string $iss) {}

    public function sign(array $claims): string
    {
        $h = ['alg' => 'HS256', 'typ' => 'JWT', 'kid' => $this->activeKid];
        $p = $claims + ['iss' => $this->iss];
        $seg = self::b64((string)json_encode($h, JSON_UNESCAPED_SLASHES)) . '.' . self::b64((string)json_encode($p, JSON_UNESCAPED_SLASHES));
        return $seg . '.' . self::b64(hash_hmac('sha256', $seg, $this->keys[$this->activeKid], true));
    }

    public function verify(string $jwt, ?string $aud = null): array
    {
        $parts = explode('.', $jwt);
        if (count($parts) !== 3) throw new \App\Core\Exceptions\UnauthorizedException('TOKEN_MALFORMED');
        [$h64, $p64, $s64] = $parts;
        $h = json_decode(self::unb64($h64), true);
        if (!is_array($h) || ($h['alg'] ?? '') !== 'HS256') throw new \App\Core\Exceptions\UnauthorizedException('TOKEN_ALG');
        $key = $this->keys[$h['kid'] ?? ''] ?? null;
        if ($key === null || $key === '') throw new \App\Core\Exceptions\UnauthorizedException('TOKEN_KID');
        $calc = hash_hmac('sha256', $h64 . '.' . $p64, $key, true);
        if (!hash_equals($calc, self::unb64($s64))) throw new \App\Core\Exceptions\UnauthorizedException('TOKEN_SIGNATURE');
        $c = json_decode(self::unb64($p64), true);
        if (!is_array($c)) throw new \App\Core\Exceptions\UnauthorizedException('TOKEN_PAYLOAD');
        $now = time();
        if (($c['typ'] ?? '') !== 'access') throw new \App\Core\Exceptions\UnauthorizedException('TOKEN_TYPE');
        if (($c['iss'] ?? '') !== $this->iss) throw new \App\Core\Exceptions\UnauthorizedException('TOKEN_ISS');
        if ($aud !== null && ($c['aud'] ?? '') !== $aud) throw new \App\Core\Exceptions\UnauthorizedException('TOKEN_AUD');
        if (($c['exp'] ?? 0) < $now - 60) throw new \App\Core\Exceptions\UnauthorizedException('TOKEN_EXPIRED');
        if (($c['nbf'] ?? 0) > $now + 60) throw new \App\Core\Exceptions\UnauthorizedException('TOKEN_NBF');
        return $c;
    }

    private static function b64(string $s): string
    {
        return rtrim(strtr(base64_encode($s), '+/', '-_'), '=');
    }

    private static function unb64(string $s): string
    {
        return base64_decode(strtr($s, '-_', '+/') . str_repeat('=', (4 - strlen($s) % 4) % 4)) ?: '';
    }
}
