<?php
declare(strict_types=1);
namespace App\Core;

use App\Core\Exceptions\ForbiddenException;

final class TenantContext
{
    public function __construct(
        public readonly string  $orgRef,
        public readonly ?string $franchiseRef,    // null for SUPER_ADMIN
        public readonly string  $userRef,
        public readonly string  $role,            // SUPER_ADMIN|FRANCHISE_ADMIN|SALES|DISTRIBUTOR
        public readonly string  $scope,           // PLATFORM|FRANCHISE|DISTRIBUTOR
        public readonly ?string $partyRef,        // set for DISTRIBUTOR users
        public readonly string  $requestId,
        public readonly ?string $impersonatorRef = null,
        /** @var array<int,string> normalized role refs/slugs */
        public readonly array   $roles = [],
        /** @var array<string,array<int,string>> module => actions */
        public readonly array   $permissions = [],
        /** @var array<string,string> module => ALL|TERRITORY|TEAM|OWN|NONE */
        public readonly array   $scopes = [],
        /** @var array<int,string> direct-report user refs */
        public readonly array   $teamUserRefs = [],
        /** @var array<int,string> assigned territory refs */
        public readonly array   $territoryRefs = [],
    ) {}

    public function isSuper(): bool          { return $this->role === 'SUPER_ADMIN'; }
    public function isSuperAdmin(): bool     { return $this->role === 'SUPER_ADMIN'; }
    public function isAdmin(): bool          { return $this->role === 'FRANCHISE_ADMIN'; }
    public function isFranchiseAdmin(): bool { return $this->role === 'FRANCHISE_ADMIN'; }
    public function isSales(): bool          { return $this->role === 'SALES'; }
    public function isDistributor(): bool    { return $this->role === 'DISTRIBUTOR'; }

    public function can(string $module, string $action): bool
    {
        return $this->isSuper() || in_array($action, $this->permissions[$module] ?? [], true);
    }

    public function scopeFor(string $module): string
    {
        return $this->scopes[$module] ?? 'NONE';
    }

    public static function get(): self
    {
        return Container::getInstance()->make(self::class);
    }

    public function requireFranchise(): string
    {
        if ($this->franchiseRef === null) {
            throw new ForbiddenException('FRANCHISE_REQUIRED');
        }
        return $this->franchiseRef;
    }
}
