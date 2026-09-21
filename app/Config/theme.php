<?php
declare(strict_types=1);

// Theme configuration — maps surface prefixes to theme names (R09)
return [
    // Surface → CSS theme data-attribute value
    'surfaces' => [
        'super'  => 'theme-super',
        'admin'  => 'theme-admin',
        'sales'  => 'theme-sales',
        'portal' => 'theme-portal',
    ],

    // Surface → human-readable title prefix
    'titles' => [
        'super'  => 'Platform Admin',
        'admin'  => 'Franchise Admin',
        'sales'  => 'Sales Team',
        'portal' => 'Distributor Portal',
    ],

    // Surface → allowed roles
    'roles' => [
        'super'  => ['SUPER_ADMIN'],
        'admin'  => ['FRANCHISE_ADMIN'],
        'sales'  => ['SALES'],
        'portal' => ['DISTRIBUTOR'],
    ],

    // Default surface when none detected
    'default_surface' => 'admin',
];
