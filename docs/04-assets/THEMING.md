# Pharma CRM — Theme & Token Contract

This document defines the single source of truth for the asset theme system. The application must support dynamically recoloring 4,000+ vector and motion assets (SVG/Lottie) at runtime without requiring individual asset manipulation.

## 1. Supported Themes
- `light`: Default, high visibility.
- `dark`: Low-light environments.
- `dim`: Mid-contrast (OLED friendly).
- `high-contrast`: WCAG AAA accessible.
- `print`: Monochrome fallback for reports.
- `tenant-{slug}`: White-labeled dynamic overrides.

## 2. Core Token Hierarchy

All colors must be bound to CSS custom properties. No hardcoded hex values are permitted in SVGs, except as the ultimate fallback in a `var()` statement.

### Brand Palette (Dynamic per Tenant)
```css
--color-brand-50: #eff6ff;
...
--color-brand-500: #3b82f6; /* Primary */
...
--color-brand-950: #172554;
```

### Semantic States
```css
--color-success-500: #10b981;
--color-warning-500: #f59e0b;
--color-danger-500:  #ef4444;
--color-info-500:    #0ea5e9;
```

### Icon & Asset Tokens
```css
--color-icon-default: var(--color-neutral-600);
--color-icon-muted:   var(--color-neutral-400);
--color-icon-strong:  var(--color-neutral-900);
--color-icon-inverse: #ffffff;
--color-icon-brand:   var(--color-brand-500);
```

### Gradients (Decorative & Hero)
```css
--color-gradient-mesh-1: linear-gradient(135deg, var(--color-brand-500), var(--color-accent-500));
```

## 3. Dynamic Color Strategy for 4,000+ Assets

### 3.1 Inline SVG & Sprites
- Icons inside `sprite.svg` must use `fill="currentColor"` or `stroke="currentColor"`.
- The parent HTML element consuming the sprite defines the `color` property.
- For multi-tone SVGs (illustrations), use direct CSS variable injection:
  `<path fill="var(--color-brand-500, #3b82f6)" />`

### 3.2 Lottie & Motion Assets (JSON)
- Lottie JSON files will use placeholders.
- A `color-bindings.json` runtime map will exist for every complex animation.
- A custom JavaScript runtime (`assets.js`) will traverse the Bodymovin JSON before rendering and replace placeholder color arrays (e.g., `[0.23, 0.51, 0.96]`) with the resolved computed value of the CSS token (e.g., `getComputedStyle(document.documentElement).getPropertyValue('--color-brand-500')`).

### 3.3 Theme Overrides
When a user switches themes, the `[data-theme="dark"]` attribute is applied to `<html>`. All CSS custom properties instantly re-calculate. SVGs using `var()` and `currentColor` update synchronously with zero JavaScript overhead. Lottie animations will re-trigger the color binding script on theme change.
