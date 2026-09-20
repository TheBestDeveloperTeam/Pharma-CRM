<?php
return [
    'name'  => 'agent-theme',
    'scope' => 'design',
    'group' => 'accessibility',
    'steps' => [
        function() {
            // Relative luminance function per WCAG 2.1
            $lum = function(string $hex): float {
                $hex = ltrim($hex, '#');
                $r = hexdec(substr($hex, 0, 2)) / 255;
                $g = hexdec(substr($hex, 2, 2)) / 255;
                $b = hexdec(substr($hex, 4, 2)) / 255;

                $adj = fn($c) => ($c <= 0.04045) ? ($c / 12.92) : pow(($c + 0.055) / 1.055, 2.4);
                return 0.2126 * $adj($r) + 0.7152 * $adj($g) + 0.0722 * $adj($b);
            };

            $contrast = function(string $hex1, string $hex2) use ($lum): float {
                $l1 = $lum($hex1);
                $l2 = $lum($hex2);
                $lighter = max($l1, $l2);
                $darker  = min($l1, $l2);
                return ($lighter + 0.05) / ($darker + 0.05);
            };

            // Themes text/background pairs
            $themes = [
                'theme-super'  => ['bg' => '#0F1B4C', 'tx' => '#FFFFFF', 'card' => '#16235F'],
                'theme-admin'  => ['bg' => '#F4FBF8', 'tx' => '#0B1F1A', 'card' => '#FFFFFF'],
                'theme-sales'  => ['bg' => '#F5F8FF', 'tx' => '#0B1730', 'card' => '#FFFFFF'],
                'theme-portal' => ['bg' => '#F7F5FF', 'tx' => '#1A1030', 'card' => '#FFFFFF'],
            ];

            foreach ($themes as $name => $colors) {
                $cBg = $contrast($colors['bg'], $colors['tx']);
                $cCard = $contrast($colors['card'], $colors['tx']);

                \Tests\Support\Assert::true($cBg >= 4.5, "Theme $name bg contrast $cBg must be >= 4.5");
                \Tests\Support\Assert::true($cCard >= 4.5, "Theme $name card contrast $cCard must be >= 4.5");
            }
        },
    ],
    'cleanup' => function() {},
];
