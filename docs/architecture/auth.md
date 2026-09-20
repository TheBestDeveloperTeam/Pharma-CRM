# Authentication Architecture (OAuth 2.0 & JWT)

This document details the authentication and authorization design for the Pharma CRM project. The system uses a JWT-based Bearer authentication model heavily inspired by OAuth 2.0, with our own robust implementation for grant types, token lifecycle, and session management.

## 1. Authentication Design Overview

The CRM supports multiple client surfaces, each mapped to specific user roles and permissions.

### 1.1 Grant Types
- **password**: Used for initial login. Trades user credentials for an Access Token and a Refresh Token.
- **refresh_token**: Used to obtain a new Access Token without requiring the user to log in again. Inspired by RFC 6749.

### 1.2 Client Identity
The CRM identifies 4 distinct client surfaces in the `oauth_clients` table:
- `crm-super`: Super Administration surface (`SUPER_ADMIN` role).
- `crm-admin`: Franchise Administration surface (`FRANCHISE_ADMIN` role).
- `crm-sales`: Field Sales surface (`SALES` role).
- `crm-portal`: Distributor/B2B portal surface (`DISTRIBUTOR` role).

### 1.3 Core Endpoints
- **Revocation**: `POST /api/v1/oauth/revoke` - Revokes the current session and token family.
- **Introspection**: `GET /api/v1/auth/me` - Validates the token and returns the current user profile.

---

## 2. Token Specifications

### 2.1 Access Token (JWT)
The Access Token is a JSON Web Token (JWT) signed with HS256 (HMAC SHA-256).

**Header:**
```json
{
  "alg": "HS256",
  "typ": "JWT",
  "kid": "k1"
}
```

**Payload Claims:**
- `iss`: Issuer (`pharma-crm`).
- `aud`: Audience (matches the client surface, e.g., `admin`, `sales`).
- `sub`: Subject (the `user_ref`).
- `org`: Organization reference (`org_ref`).
- `frn`: Franchise reference (`franchise_ref`).
- `role`: User's system role.
- `scp`: Scope.
- `pty`: Party reference (`party_ref`) or `null`.
- `iat`: Issued at timestamp.
- `nbf`: Not before timestamp.
- `exp`: Expiration timestamp (iat + 900s).
- `jti`: JWT ID.
- `sid`: Session reference (`session_ref`).
- `typ`: Fixed as `"access"`.

**Properties:**
- **TTL**: 900 seconds (15 minutes).
- **Signature**: `hash_hmac('sha256', $header . '.' . $payload, $key)`
- **Key Ring**: Uses a Key ID (`kid`) for seamless key rotation.

### 2.2 Refresh Token
The Refresh Token is an opaque string used to issue new Access Tokens.

**Properties:**
- **Format**: 48 random bytes encoded in base64url.
- **Storage**: Stored in the database *only* as a SHA-256 hash. Never stored in plaintext.
- **TTL**: 14 days absolute, 8 hours idle.
- **Rotation**: Every usage of a refresh token issues a new refresh token and access token pair. The old refresh token is marked as `USED`.
- **Reuse Detection**: If a `USED` token is presented, the system detects a potential theft/replay attack. It instantly revokes the entire token family (`family_ref`) and triggers a `SECURITY` audit log.

---

## 3. Validation Pipeline

Every protected API request goes through this pipeline:

1. **Format Check**: The `Authorization` header must match exactly: `^Bearer [A-Za-z0-9\-_]+\.[A-Za-z0-9\-_]+\.[A-Za-z0-9\-_]+$`
2. **Algorithm Check**: The `alg` header must be exactly `"HS256"`. `"none"` and all other algorithms are strictly rejected.
3. **Signature Verification**: Execute `hash_equals()` to securely compare the generated signature against the token signature using the key selected by `kid`.
4. **Time & Origin Claims**: Verify `exp`, `nbf`, `iat` (allowing 60s leeway for clock drift). Verify `iss` is correct. Verify `aud` matches the current route surface. Verify `typ` is `"access"`.
5. **Session Verification**: Query `user_sessions`. Ensure the `session_ref` mapped to this token is neither revoked nor expired.
6. **Status Check**: Re-check user, franchise, and organization statuses. (This step caches results in `FileCache` for 30s to reduce DB load).
7. **Role Verification**: Ensure token claims (role, refs) equal the actual database row to prevent privilege escalation via stale tokens.
8. **Context Construction**: If all checks pass, build the immutable `TenantContext` object and proceed to the controller.

---

## 4. Client Token Handling (No Cookies)

The frontend application handles tokens securely without relying on cookies, relying on strict Cross-Site Scripting (XSS) prevention.

- **Access Token**: Stored *only* in a JS module-scoped variable (in-memory). It does not survive a page reload.
- **Refresh Token**: Stored in `sessionStorage` (tab-scoped, automatically cleared when the tab is closed).
- **Initialization**: On page load, `crm-ui.js` calls `POST /oauth/token` with `grant_type=refresh_token` using the token from `sessionStorage` to obtain a fresh Access Token.
- **Security Posture**: A strict Content Security Policy (CSP), specifically `script-src 'self'`, acts as the primary shield protecting the refresh token from XSS. All DOM manipulations strictly use `textContent` or `document.createElement`. The use of `innerHTML` is globally banned.
- **Multi-tab Coordination**: Uses `BroadcastChannel('crm-auth')` to propagate logout events across all open tabs.
- **Logout Flow**: Calls `POST /oauth/revoke` to revoke the entire session family on the server, then clears `sessionStorage`.

---

## 5. Login Rules & Mechanics

The login endpoint accepts:
```json
{
  "grant_type": "password",
  "client_id": "crm-admin",
  "email": "user@example.com",
  "password": "secretpassword",
  "franchise_code": "FRN-123"
}
```

**Rules:**
- `client_id` must be one of the 4 defined clients.
- `franchise_code` is mandatory for admin, sales, and portal clients. It is omitted for the super admin client.
- The user's role must strictly map to the requested client surface.
- **Password Verification**: Uses `password_verify()`. The system uses Argon2id if available, falling back to bcrypt (cost 12).
- **Timing Attacks**: If a user is not found, the system performs a dummy hash verification to equalize response times.
- **Generic Errors**: The API always returns generic error text (e.g., "Invalid credentials"). It never discloses whether an email or franchise code exists.
- **Brute Force Protection**: 
  - 5 failures per 15 minutes per `(email, tenant_key)`.
  - 20 failures per 15 minutes per IP address.
  - Exceeding limits triggers an HTTP 429 response with a `Retry-After` header.
- **Password Policy**: Minimum 10 characters, not present in the built-in top-1000 compromised passwords list, and not equal to the email address.

---

## 6. Key Ring Configuration

Configuration resides in the environment and config files:

```php
return [
  'iss'          => 'pharma-crm',
  'access_ttl'   => 900,
  'refresh_abs'  => 1209600,  // 14 days
  'refresh_idle' => 28800,    // 8 hours
  'active_kid'   => 'k1',
  'keys'         => [
      'k1' => env('JWT_KEY_K1'), 
      'k0' => env('JWT_KEY_K0')
  ], // k0 is accepted for verification, never used for signing new tokens
  'hash_algo'    => defined('PASSWORD_ARGON2ID') ? PASSWORD_ARGON2ID : PASSWORD_BCRYPT,
  'clients'      => [
    'crm-super'  => ['aud' => 'super',  'roles' => ['SUPER_ADMIN']],
    'crm-admin'  => ['aud' => 'admin',  'roles' => ['FRANCHISE_ADMIN']],
    'crm-sales'  => ['aud' => 'sales',  'roles' => ['SALES']],
    'crm-portal' => ['aud' => 'portal', 'roles' => ['DISTRIBUTOR']],
  ],
];
```

---

## 7. Session Lifecycle

- **Login**: Creates a `user_sessions` row (yielding a new `session_ref` and `family_ref`) and an `oauth_refresh_tokens` row.
- **Token Refresh**: Creates a new `session_ref` row tied to the same `family_ref`, and a new `oauth_refresh_tokens` row. The old refresh token's status is changed to `USED`.
- **Logout**: Revokes the current `session_ref` and by extension, all tokens in the `family_ref`.
- **Reuse Detection**: Presenting an old token marked as `USED` immediately revokes the entire `family_ref` and logs `REFRESH_REUSE_DETECTED`.

---

## 8. Password Reset Flow

1. `POST /auth/forgot-password` (email provided).
2. Generate 32 random bytes. Create a SHA-256 hash and store it in `password_resets`.
3. Dispatch an email containing the plaintext token as a link (one-time use).
4. `POST /auth/reset-password` (token, new_password provided).
5. Verify the token hash against the database, check the 1-hour expiry, update the user's password, and mark the token as used.

---

## 9. Key Rotation (Zero-Downtime)

The system supports seamless, zero-downtime JWT signing key rotation.

- A CLI command (`php cli/keys.php rotate`) promotes `k1` to `k0`, and generates a fresh key for `k1`.
- `k1` becomes the active signing key.
- `k0` is retained and accepted for signature verification for 15 minutes.
- Old tokens signed by the previous `k1` (now `k0`) will expire naturally within the 15-minute Access Token TTL.
- `k0` only accepts; it never signs.

---

## 10. Database Schema (Auth Related)

- **`users`**: Contains `tenant_key` (generated column: `IFNULL(franchise_ref,'PLATFORM')`), `locked_until`, `failed_login_count`.
- **`oauth_clients`**: `client_id`, `surface` (ENUM), `allowed_roles`, `status`.
- **`user_sessions`**: `session_ref`, `family_ref`, `user_ref`, `org_ref`, `franchise_ref`, `client_id`, `ip`, `abs_expires_at`, `revoked_at`.
- **`oauth_refresh_tokens`**: `token_hash` (CHAR 64), `session_ref`, `family_ref`, `status` (ENUM: ACTIVE, USED, REVOKED), `idle_expires_at`.
- **`login_attempts`**: `email`, `tenant_key`, `ip_address`, `outcome` (ENUM: SUCCESS, FAIL, LOCKED), `attempted_at`.
- **`password_resets`**: `token_hash` (CHAR 64), `user_ref`, `expires_at`, `used_at`.
- **`rate_limits`**: `bucket_key` (CHAR 64), `window_start` (INT UNSIGNED), `hits` (INT UNSIGNED).
