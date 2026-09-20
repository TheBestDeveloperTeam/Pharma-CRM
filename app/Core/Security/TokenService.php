<?php
declare(strict_types=1);
namespace App\Core\Security;

use App\Core\{Database, RefGenerator, Transaction};
use App\Core\Exceptions\UnauthorizedException;

final class TokenService
{
    public function __construct(
        private \PDO $pdo,
        private Jwt $jwt
    ) {}

    /**
     * Issue an initial access & refresh token pair for a user.
     */
    public function issue(array $user, string $clientId, string $surface, string $ipAddress, string $userAgent): array
    {
        $sessionRef = RefGenerator::make('SES');
        $familyRef  = RefGenerator::make('FAM');
        $rawRt      = bin2hex(random_bytes(24)); // 48 chars
        $rtHash     = hash('sha256', $rawRt);

        $now = time();
        $absExp  = $now + (int)\App\Support\Config::get('auth.refresh_abs', 1209600);
        $idleExp = $now + (int)\App\Support\Config::get('auth.refresh_idle', 28800);
        $jwtExp  = $now + (int)\App\Support\Config::get('auth.access_ttl', 900);

        $claims = [
            'sub'  => $user['user_ref'],
            'aud'  => $surface,
            'org'  => $user['org_ref'],
            'frn'  => $user['franchise_ref'] ?? null,
            'role' => $user['role'],
            'scp'  => $user['role'] === 'SUPER_ADMIN' ? 'PLATFORM' : 'FRANCHISE',
            'pty'  => $user['party_ref'] ?? null,
            'sid'  => $sessionRef,
            'typ'  => 'access',
            'exp'  => $jwtExp,
            'nbf'  => $now - 1,
            'iat'  => $now,
            'jti'  => RefGenerator::make('KEY'),
        ];

        $accessToken = $this->jwt->sign($claims);

        $tx = new Transaction($this->pdo);
        $tx->run(function() use ($sessionRef, $familyRef, $user, $clientId, $ipAddress, $userAgent, $now, $absExp, $idleExp, $rtHash) {
            $stmtSession = $this->pdo->prepare(
                "INSERT INTO user_sessions 
                    (session_ref, family_ref, user_ref, org_ref, franchise_ref, client_id, ip_address, user_agent, issued_at, abs_expires_at)
                 VALUES 
                    (:sref, :fref, :uref, :oref, :frref, :cid, :ip, :ua, :issued, :abs_exp)"
            );
            $stmtSession->execute([
                ':sref'    => $sessionRef,
                ':fref'    => $familyRef,
                ':uref'    => $user['user_ref'],
                ':oref'    => $user['org_ref'],
                ':frref'   => $user['franchise_ref'] ?? null,
                ':cid'     => $clientId,
                ':ip'      => $ipAddress,
                ':ua'      => substr($userAgent, 0, 255),
                ':issued'  => date('Y-m-d H:i:s', $now),
                ':abs_exp' => date('Y-m-d H:i:s', $absExp),
            ]);

            $stmtRt = $this->pdo->prepare(
                "INSERT INTO oauth_refresh_tokens
                    (token_hash, session_ref, family_ref, user_ref, status, issued_at, idle_expires_at, abs_expires_at)
                 VALUES
                    (:thash, :sref, :fref, :uref, 'ACTIVE', :issued, :idle_exp, :abs_exp)"
            );
            $stmtRt->execute([
                ':thash'    => $rtHash,
                ':sref'     => $sessionRef,
                ':fref'     => $familyRef,
                ':uref'     => $user['user_ref'],
                ':issued'   => date('Y-m-d H:i:s', $now),
                ':idle_exp' => date('Y-m-d H:i:s', $idleExp),
                ':abs_exp'  => date('Y-m-d H:i:s', $absExp),
            ]);
        });

        return [
            'access_token'  => $accessToken,
            'refresh_token' => $rawRt,
            'expires_in'    => (int)\App\Support\Config::get('auth.access_ttl', 900),
            'token_type'    => 'Bearer',
        ];
    }

    /**
     * Rotate refresh token and issue new access token. Detect reuse.
     */
    public function refresh(string $rawRt, string $surface, string $ipAddress, string $userAgent): array
    {
        $hash = hash('sha256', $rawRt);

        $stmt = $this->pdo->prepare("SELECT * FROM oauth_refresh_tokens WHERE token_hash = :hash LIMIT 1");
        $stmt->execute([':hash' => $hash]);
        $tokenRow = $stmt->fetch(\PDO::FETCH_ASSOC);

        if (!$tokenRow) {
            throw new UnauthorizedException('INVALID_REFRESH_TOKEN', 'Invalid refresh token.');
        }

        $familyRef = $tokenRow['family_ref'];

        // Token reuse detection!
        if ($tokenRow['status'] === 'USED' || $tokenRow['status'] === 'REVOKED') {
            $stmtRevokeSessions = $this->pdo->prepare(
                "UPDATE user_sessions SET revoked_at = NOW(), revoke_reason = 'REFRESH_REUSE' WHERE family_ref = :f"
            );
            $stmtRevokeSessions->execute([':f' => $familyRef]);

            $stmtRevokeTokens = $this->pdo->prepare(
                "UPDATE oauth_refresh_tokens SET status = 'REVOKED' WHERE family_ref = :f"
            );
            $stmtRevokeTokens->execute([':f' => $familyRef]);

            throw new UnauthorizedException('REFRESH_TOKEN_REUSED', 'Token reuse detected. All sessions in family revoked.');
        }

        $now = time();
        if (strtotime($tokenRow['idle_expires_at']) < $now || strtotime($tokenRow['abs_expires_at']) < $now) {
            throw new UnauthorizedException('REFRESH_TOKEN_EXPIRED', 'Refresh token has expired.');
        }

        // Fetch User and Session
        $stmtUser = $this->pdo->prepare("SELECT * FROM users WHERE user_ref = :u LIMIT 1");
        $stmtUser->execute([':u' => $tokenRow['user_ref']]);
        $user = $stmtUser->fetch(\PDO::FETCH_ASSOC);

        if (!$user || $user['status'] !== 'ACTIVE') {
            throw new UnauthorizedException('USER_INACTIVE', 'User is not active.');
        }

        // Mark old token USED and issue new token pair under same family
        $newSessionRef = RefGenerator::make('SES');
        $newRawRt      = bin2hex(random_bytes(24));
        $newRtHash     = hash('sha256', $newRawRt);

        $absExp  = strtotime($tokenRow['abs_expires_at']); // keep original absolute expiry
        $idleExp = $now + (int)\App\Support\Config::get('auth.refresh_idle', 28800);
        $jwtExp  = $now + (int)\App\Support\Config::get('auth.access_ttl', 900);

        $claims = [
            'sub'  => $user['user_ref'],
            'aud'  => $surface,
            'org'  => $user['org_ref'],
            'frn'  => $user['franchise_ref'] ?? null,
            'role' => $user['role'],
            'scp'  => $user['role'] === 'SUPER_ADMIN' ? 'PLATFORM' : 'FRANCHISE',
            'pty'  => $user['party_ref'] ?? null,
            'sid'  => $newSessionRef,
            'typ'  => 'access',
            'exp'  => $jwtExp,
            'nbf'  => $now - 1,
            'iat'  => $now,
            'jti'  => RefGenerator::make('KEY'),
        ];

        $newAccessToken = $this->jwt->sign($claims);

        $tx = new Transaction($this->pdo);
        $tx->run(function() use ($tokenRow, $newSessionRef, $familyRef, $user, $ipAddress, $userAgent, $now, $absExp, $idleExp, $newRtHash) {
            // 1. Mark current USED
            $this->pdo->prepare("UPDATE oauth_refresh_tokens SET status = 'USED', used_at = NOW() WHERE id = :id")
                ->execute([':id' => $tokenRow['id']]);

            // 2. Insert new session
            $stmtSession = $this->pdo->prepare(
                "INSERT INTO user_sessions 
                    (session_ref, family_ref, user_ref, org_ref, franchise_ref, client_id, ip_address, user_agent, issued_at, abs_expires_at)
                 VALUES 
                    (:sref, :fref, :uref, :oref, :frref, 'refresh_grant', :ip, :ua, :issued, :abs_exp)"
            );
            $stmtSession->execute([
                ':sref'    => $newSessionRef,
                ':fref'    => $familyRef,
                ':uref'    => $user['user_ref'],
                ':oref'    => $user['org_ref'],
                ':frref'   => $user['franchise_ref'] ?? null,
                ':ip'      => $ipAddress,
                ':ua'      => substr($userAgent, 0, 255),
                ':issued'  => date('Y-m-d H:i:s', $now),
                ':abs_exp' => date('Y-m-d H:i:s', $absExp),
            ]);

            // 3. Insert new refresh token
            $stmtRt = $this->pdo->prepare(
                "INSERT INTO oauth_refresh_tokens
                    (token_hash, session_ref, family_ref, user_ref, status, issued_at, idle_expires_at, abs_expires_at)
                 VALUES
                    (:thash, :sref, :fref, :uref, 'ACTIVE', :issued, :idle_exp, :abs_exp)"
            );
            $stmtRt->execute([
                ':thash'    => $newRtHash,
                ':sref'     => $newSessionRef,
                ':fref'     => $familyRef,
                ':uref'     => $user['user_ref'],
                ':issued'   => date('Y-m-d H:i:s', $now),
                ':idle_exp' => date('Y-m-d H:i:s', $idleExp),
                ':abs_exp'  => date('Y-m-d H:i:s', $absExp),
            ]);
        });

        return [
            'access_token'  => $newAccessToken,
            'refresh_token' => $newRawRt,
            'expires_in'    => (int)\App\Support\Config::get('auth.access_ttl', 900),
            'token_type'    => 'Bearer',
        ];
    }

    /**
     * Revoke session and associated tokens upon logout.
     */
    public function revokeSession(string $sessionRef): void
    {
        $stmt = $this->pdo->prepare(
            "UPDATE user_sessions SET revoked_at = NOW(), revoke_reason = 'LOGOUT' WHERE session_ref = :s"
        );
        $stmt->execute([':s' => $sessionRef]);

        $stmtTokens = $this->pdo->prepare(
            "UPDATE oauth_refresh_tokens SET status = 'REVOKED' WHERE session_ref = :s"
        );
        $stmtTokens->execute([':s' => $sessionRef]);
    }
}
