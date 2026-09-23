<?php
// shell.php - Renders the master layout with a mounting point for the SPA/Vanilla JS app.
// Variables available from DashboardController: $surface, $theme, $title

$content = sprintf(
    '<div id="app-root" data-surface="%s" data-theme-context="%s">
        <!-- The JS app will mount here and load the respective views -->
        <div class="loader-wrapper">
            %s
        </div>
    </div>',
    htmlspecialchars($surface),
    htmlspecialchars($theme),
    \App\Helpers\AssetHelper::illustration('motion/svg-anim/loader-spinner') // fallback
);

// Include the master layout
require __DIR__ . '/master.php';
