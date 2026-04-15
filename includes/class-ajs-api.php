<?php
/**
 * Joby API Factory
 * Manages different job providers
 */

require_once JOBY_PATH . 'includes/providers/interface-ajs-provider.php';
require_once JOBY_PATH . 'includes/providers/class-ajs-adzuna.php';
require_once JOBY_PATH . 'includes/providers/class-ajs-arbeitnow.php';

class Joby_API {
    
    public static function get_provider($slug = '') {
        if (empty($slug)) {
            $slug = get_option('ajs_provider', 'adzuna');
        }

        switch ($slug) {
            case 'arbeitnow':
                return new Joby_Provider_Arbeitnow();
            case 'adzuna':
            default:
                $app_id = get_option('ajs_app_id');
                $app_key = get_option('ajs_app_key');
                return new Joby_Provider_Adzuna($app_id, $app_key);
        }
    }

    public static function get_all_providers() {
        return array(
            'adzuna'    => new Joby_Provider_Adzuna(),
            'arbeitnow' => new Joby_Provider_Arbeitnow()
        );
    }
}
