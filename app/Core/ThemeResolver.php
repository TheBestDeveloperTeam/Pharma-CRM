<?php
declare(strict_types=1);
namespace App\Core;

/**
 * ThemeResolver — maps route prefixes to theme names and surface identifiers.
 * Uses theme.php config. No business logic. (R09)
 */
final class ThemeResolver
{
    /** @var array<string, string> surface → theme */
    private array $surfaces;

    /** @var array<string, string> surface → title prefix */
    private array $titles;

    /** @var array<string, string[]> surface → allowed roles */
    private array $roles;

    private string $defaultSurface;

    public function __construct()
    {
        $config = require dirname(__DIR__) . '/Config/theme.php';
        $this->surfaces      = $config['surfaces'] ?? [];
        $this->titles         = $config['titles'] ?? [];
        $this->roles          = $config['roles'] ?? [];
        $this->defaultSurface = $config['default_surface'] ?? 'admin';
    }

    /**
     * Detect the surface from a URL path.
     */
    public function detectSurface(string $path): string
    {
        $path = '/' . ltrim($path, '/');
        foreach (array_keys($this->surfaces) as $surface) {
            if (str_starts_with($path, '/' . $surface . '/') || $path === '/' . $surface) {
                return $surface;
            }
        }
        return $this->defaultSurface;
    }

    /**
     * Get the CSS theme name for a surface.
     */
    public function getTheme(string $surface): string
    {
        return $this->surfaces[$surface] ?? $this->surfaces[$this->defaultSurface] ?? 'theme-admin';
    }

    /**
     * Get the human-readable title prefix for a surface.
     */
    public function getTitle(string $surface): string
    {
        return $this->titles[$surface] ?? ucfirst($surface);
    }

    /**
     * Get allowed roles for a surface.
     * @return string[]
     */
    public function getAllowedRoles(string $surface): array
    {
        return $this->roles[$surface] ?? [];
    }

    /**
     * Check if a role is allowed on a surface.
     */
    public function isRoleAllowed(string $surface, string $role): bool
    {
        $allowed = $this->getAllowedRoles($surface);
        return empty($allowed) || in_array($role, $allowed, true);
    }

    /**
     * Get all registered surface names.
     * @return string[]
     */
    public function getSurfaces(): array
    {
        return array_keys($this->surfaces);
    }
}
