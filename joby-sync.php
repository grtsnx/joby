<?php

/**
 * Plugin Name: Joby Sync
 * Description: Dynamically fetch and sync jobs from a remote API across multiple countries.
 * Version: 3.7.0
 * Author: Abolade Greatness
 * Author URI: https://github.com/grtsnx/joby
 * License: GPL-2.0+
 */

if (! defined('ABSPATH')) {
    exit;
}

// Define constants
define('JOBY_VERSION', '3.7.0');
define('JOBY_PATH', plugin_dir_path(__FILE__));
define('JOBY_URL', plugin_dir_url(__FILE__));

// Include required files
require_once JOBY_PATH . 'includes/class-ajs-api.php';
require_once JOBY_PATH . 'includes/class-ajs-sync-engine.php';
require_once JOBY_PATH . 'includes/class-ajs-shortcodes.php';
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
        add_filter('manage_ajs_job_posts_columns', array($this, 'add_admin_columns'));
        add_action('manage_ajs_job_posts_custom_column', array($this, 'render_admin_columns'), 10, 2);
        add_filter('manage_edit-ajs_job_sortable_columns', array($this, 'make_columns_sortable'));
        new Joby_Shortcodes();

        // Initialize components
        Joby_Settings::get_instance();
        Joby_Sync_Engine::get_instance();

        // Initialize Update Engine (Checks GitHub for new releases)
        if ( is_admin() ) {
            new Joby_Updater( JOBY_PATH . 'joby-sync.php', 'grtsnx/joby' );
        }
    }

    /**
     * Register Custom Post Type and Taxonomies
     */
    public function register_cpt()
    {
        // Register Category Taxonomy
        register_taxonomy('ajs_category', 'ajs_job', array(
            'labels'       => array('name' => 'Job Categories', 'singular_name' => 'Job Category'),
            'rewrite'      => array('slug' => 'job-category'),
            'hierarchical' => true,
            'show_in_rest' => true,
        ));

        // Register Nature/Type Taxonomy
        register_taxonomy('ajs_type', 'ajs_job', array(
            'labels'       => array('name' => 'Job Nature', 'singular_name' => 'Job Nature'),
            'rewrite'      => array('slug' => 'job-nature'),
            'hierarchical' => true,
            'show_in_rest' => true,
        ));

        // Register Country Taxonomy
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
            'taxonomies'  => array('ajs_country', 'ajs_category', 'ajs_type'),
            'show_in_rest' => true,
        ));
    }

    /**
     * Define Admin Columns
     */
    public function add_admin_columns($columns)
    {
        $new_columns = array(
            'cb'           => $columns['cb'],
            'title'        => 'Title',
            'ajs_category' => 'Job Categories',
            'ajs_type'     => 'Job Nature',
            'ajs_company'  => 'Company',
            'ajs_location' => 'Job Location',
            'date'         => 'Date',
        );
        return $new_columns;
    }

    /**
     * Render Admin Column Content
     */
    public function render_admin_columns($column, $post_id)
    {
        switch ($column) {
            case 'ajs_category':
                echo get_the_term_list($post_id, 'ajs_category', '', ', ');
                break;
            case 'ajs_type':
                echo get_the_term_list($post_id, 'ajs_type', '', ', ');
                break;
            case 'ajs_company':
                echo esc_html(get_post_meta($post_id, '_ajs_company', true));
                break;
            case 'ajs_location':
                $location = get_post_meta($post_id, '_ajs_location_name', true);
                $country = get_the_term_list($post_id, 'ajs_country', '', ', ');
                echo esc_html($location ?: 'Remote');
                if ($country) echo ' (' . $country . ')';
                break;
        }
    }

    /**
     * Make Columns Sortable
     */
    public function make_columns_sortable($columns)
    {
        $columns['ajs_company'] = 'ajs_company';
        $columns['ajs_location'] = 'ajs_location';
        return $columns;
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

// Deactivation hook: Clean up cron events and reset state
register_deactivation_hook(__FILE__, function () {
    wp_clear_scheduled_hook('ajs_daily_sync_event');
    wp_clear_scheduled_hook('ajs_process_queue_event');
    update_option('ajs_sync_status', 'idle');
});