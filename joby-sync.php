<?php

/**
 * Plugin Name: Joby Sync
 * Description: Dynamically fetch and sync jobs from a remote API across multiple countries.
 * Version: 2.1.1
 * Author: Abolade Greatness
 * Author URI: https://github.com/grtsnx/joby
 * License: GPL-2.0+
 */

if (! defined('ABSPATH')) {
    exit;
}

// Define constants
define('JOBY_VERSION', '2.1.1');
define('JOBY_PATH', plugin_dir_path(__FILE__));
define('JOBY_URL', plugin_dir_url(__FILE__));

// Include required files
require_once JOBY_PATH . 'includes/class-ajs-api.php';
require_once JOBY_PATH . 'includes/class-ajs-sync-engine.php';
require_once JOBY_PATH . 'includes/class-ajs-settings.php';
require_once JOBY_PATH . 'includes/class-ajs-updater.php';

/**
 * Main Plugin Class
 */
class Joby_Sync
{

    private static $instance = null;

    public static function get_instance()
    {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct()
    {
        add_action('init', array($this, 'register_cpt'));
        add_action('admin_init', array($this, 'activation_redirect'));

        // Initialize components
        Joby_Settings::get_instance();
        Joby_Sync_Engine::get_instance();

        // Initialize Update Engine (Checks GitHub for new releases)
        if ( is_admin() ) {
            new Joby_Updater( JOBY_PATH . 'joby-sync.php', 'grtsnx/joby' );
        }
    }

    /**
     * Register Custom Post Type and Taxonomy
     */
    public function register_cpt()
    {
        // Register Taxonomy
        register_taxonomy('ajs_country', 'ajs_job', array(
            'label'        => 'Job Country',
            'rewrite'      => array('slug' => 'job-country'),
            'hierarchical' => true,
            'show_in_rest' => true,
        ));

        // Register CPT
        register_post_type('ajs_job', array(
            'labels'      => array(
                'name'          => 'Joby Jobs',
                'singular_name' => 'Joby Job',
            ),
            'public'      => true,
            'has_archive' => true,
            'menu_icon'   => 'dashicons-businessman',
            'supports'    => array('title', 'editor', 'thumbnail', 'excerpt', 'custom-fields'),
            'taxonomies'  => array('ajs_country'),
            'show_in_rest' => true,
        ));
    }

    /**
     * Redirect to settings on activation if keys are missing
     */
    public function activation_redirect()
    {
        if (get_option('ajs_do_activation_redirect', false)) {
            delete_option('ajs_do_activation_redirect');
            if (! get_option('ajs_app_id') || ! get_option('ajs_app_key')) {
                wp_safe_redirect(admin_url('admin.php?page=joby-sync'));
                exit;
            }
        }
    }
}

// Initialize the plugin
function ajs_init()
{
    Joby_Sync::get_instance();
}
add_action('plugins_loaded', 'ajs_init');

// Activation hook
register_activation_hook(__FILE__, function () {
    update_option('ajs_do_activation_redirect', true);
    
    // Default to Arbeitnow for immediate global availability
    if ( ! get_option('ajs_provider') ) {
        update_option('ajs_provider', 'arbeitnow');
    }

    // Set default countries if none exist
    if (! get_option('ajs_countries')) {
        $defaults = array(
            array('name' => 'Nigeria', 'code' => 'ng', 'count' => 1000),
            array('name' => 'USA', 'code' => 'us', 'count' => 1000),
            array('name' => 'Canada', 'code' => 'ca', 'count' => 1000),
            array('name' => 'UK', 'code' => 'gb', 'count' => 1000),
            array('name' => 'Germany', 'code' => 'de', 'count' => 1000),
            array('name' => 'Russia', 'code' => 'ru', 'count' => 1000),
            array('name' => 'South Africa', 'code' => 'za', 'count' => 1000),
            array('name' => 'France', 'code' => 'fr', 'count' => 1000),
            array('name' => 'India', 'code' => 'in', 'count' => 1000),
            array('name' => 'Indonesia', 'code' => 'id', 'count' => 1000),
            array('name' => 'Kenya', 'code' => 'ke', 'count' => 1000),
            array('name' => 'Egypt', 'code' => 'eg', 'count' => 1000),
            array('name' => 'Ghana', 'code' => 'gh', 'count' => 500),
        );
        update_option('ajs_countries', $defaults);
    }
});