<?php
namespace App\Helpers;

class AssetHelper
{
    public static function icon(string $id, string $class = ''): string
    {
        return sprintf(
            '<svg class="icon %s"><use href="/assets/icons/sprite.svg#%s" /></svg>',
            htmlspecialchars($class),
            htmlspecialchars($id)
        );
    }

    public static function illustration(string $id, string $alt = '', string $class = 'illustration'): string
    {
        // For inline rendering to support var() passing via DOM
        $path = __DIR__ . '/../../public/assets/illustrations/' . $id . '.svg';
        if (file_exists($path)) {
            $svg = file_get_contents($path);
            // Replace <svg> with <svg class="...">
            $svg = preg_replace('/<svg([^>]+)>/i', '<svg$1 class="' . htmlspecialchars($class) . '">', $svg, 1);
            return $svg;
        }
        
        // Fallback to img tag if not found locally for inline
        return sprintf(
            '<img src="/assets/illustrations/%s.svg" alt="%s" class="%s">',
            htmlspecialchars($id),
            htmlspecialchars($alt),
            htmlspecialchars($class)
        );
    }
}
