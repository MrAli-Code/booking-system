<?php
/**
 * WordPress Plugin Core Integration
 */

class BBS_WP_Core
{
    public static function init(): void
    {
        // Hook into WordPress init
        add_action('init', [self::class, 'registerPostTypes']);
        add_filter('wp_nav_menu_items', [self::class, 'addNavLinks'], 10, 2);
    }

    public static function registerPostTypes(): void
    {
        // Register any custom post types if needed
    }

    public static function addNavLinks(string $items, object $args): string
    {
        if ($args->theme_location === 'primary') {
            $items .= '<li><a href="' . home_url('/booking') . '">' . __('Book Appointment', 'booking-system') . '</a></li>';
            $items .= '<li><a href="' . home_url('/track') . '">' . __('Track Booking', 'booking-system') . '</a></li>';
        }
        return $items;
    }

    public static function getSetting(string $key, $default = null)
    {
        $settings = get_option('bbs_settings', []);
        return $settings[$key] ?? $default;
    }
}
