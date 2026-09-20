<?php

declare(strict_types=1);

namespace App\Domain\Users;

/**
 * AuthUser — Read-only authenticated user value object.
 * Injected into Request by AuthMiddleware.
 */
class AuthUser
{
    private array $roles       = [];
    private array $permissions = [];

    public function __construct(private readonly array $data)
    {
        if (isset($data['role_slugs']) && is_string($data['role_slugs'])) {
            $this->roles = explode(',', $data['role_slugs']);
        } else {
            $this->roles = $data['roles'] ?? [];
        }
        $this->permissions = $data['permissions'] ?? [];
    }

    public function getId(): int         { return (int) $this->data['id']; }
    public function getName(): string    { return $this->data['name'] ?? ''; }
    public function getEmail(): string   { return $this->data['email'] ?? ''; }
    public function getStatus(): string  { return $this->data['status'] ?? 'active'; }

    public function hasRole(string $role): bool
    {
        return in_array(strtolower($role), array_map('strtolower', $this->roles), true);
    }

    public function hasPermission(string $permission): bool
    {
        return in_array($permission, $this->permissions, true);
    }

    public function isAdmin(): bool
    {
        return $this->hasRole('admin');
    }

    public function toArray(): array
    {
        return [
            'id'          => $this->getId(),
            'name'        => $this->getName(),
            'email'       => $this->getEmail(),
            'roles'       => $this->roles,
            'permissions' => $this->permissions,
        ];
    }
}
