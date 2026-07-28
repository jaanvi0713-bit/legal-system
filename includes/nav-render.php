<?php
/**
 * Render grouped sidebar navigation links.
 *
 * @param array<int, array{label?: string, items: array<int, array{0: string, 1: string, 2: string, 3: string}>}> $sections
 * @param array<int, string>|null $allowedModules
 */
function render_sidebar_nav(array $sections, ?array $allowedModules, string $activeNav, string $portalBase): void
{
    foreach ($sections as $section) {
        $visibleItems = [];
        foreach ($section['items'] as $item) {
            [, , $key] = $item;
            if ($allowedModules !== null && !in_array($key, $allowedModules, true)) {
                continue;
            }
            $visibleItems[] = $item;
        }
        if (!$visibleItems) {
            continue;
        }

        echo '<div class="nav-group">';
        if (!empty($section['label'])) {
            echo '<div class="nav-section">' . __e($section['label']) . '</div>';
        }

        foreach ($visibleItems as [$href, $labelKey, $key, $icon]) {
            $active = $activeNav === $key ? 'active' : '';
            echo '<a class="nav-link ' . $active . '" href="' . e($portalBase . '/' . $href) . '">';
            echo '<span class="nav-icon">' . nav_icon($icon) . '</span>';
            echo '<span class="nav-label">' . __e($labelKey) . '</span>';
            echo '</a>';
        }
        echo '</div>';
    }
}
