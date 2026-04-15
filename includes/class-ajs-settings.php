<?php
/**
 * Joby Settings Page
 */
class Joby_Settings {

    private static $instance = null;

    public static function get_instance() {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        add_action( 'admin_menu', array( $this, 'add_menu' ) );
        add_action( 'admin_init', array( $this, 'register_settings' ) );
        add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
        add_action( 'admin_head', array( $this, 'fix_menu_icon_size' ) );
        add_action( 'wp_ajax_ajs_trigger_sync', array( $this, 'handle_trigger_sync' ) );
        add_action( 'wp_ajax_ajs_cancel_sync', array( $this, 'handle_cancel_sync' ) );
        add_action( 'wp_ajax_ajs_check_updates', array( $this, 'handle_check_updates' ) );
        add_action( 'wp_ajax_ajs_get_logs', array( $this, 'handle_get_logs' ) );
        add_action( 'wp_ajax_ajs_clear_cache', array( $this, 'handle_clear_cache' ) );
    }

    public function add_menu() {
        add_menu_page(
            'Joby Sync',
            'Joby Sync',
            'manage_options',
            'joby-sync',
            array( $this, 'render_page' ),
            JOBY_URL . 'assets/icon.png'
        );
    }

    public function enqueue_assets($hook) {
        if ( 'toplevel_page_joby-sync' !== $hook ) return;
        
        wp_enqueue_style( 'ajs-admin-css', JOBY_URL . 'assets/admin.css', array(), JOBY_VERSION );
        wp_enqueue_script( 'ajs-admin-js', JOBY_URL . 'assets/admin.js', array( 'jquery' ), JOBY_VERSION, true );
        
        wp_localize_script( 'ajs-admin-js', 'ajs_vars', array(
            'ajax_url' => admin_url( 'admin-ajax.php' ),
            'nonce'    => wp_create_nonce( 'ajs_nonce' )
        ) );
    }

    public function register_settings() {
        register_setting( 'ajs_settings_group', 'ajs_provider' );
        register_setting( 'ajs_settings_group', 'ajs_app_id' );
        register_setting( 'ajs_settings_group', 'ajs_app_key' );
        register_setting( 'ajs_settings_group', 'ajs_countries' );
        register_setting( 'ajs_settings_group', 'ajs_auto_sync' );

        // Handle auto-sync on save if enabled
        if ( isset($_GET['settings-updated']) && $_GET['settings-updated'] ) {
            if ( get_option('ajs_auto_sync') === 'yes' ) {
                Joby_Sync_Engine::get_instance()->start_sync();
            }
        }
    }

    public function render_page() {
        // Clear update cache on settings page load so user sees latest version
        delete_site_transient( 'joby_update_check' );
        delete_site_transient( 'update_plugins' ); // Force WP to re-scan for updates
        
        $provider    = get_option( 'ajs_provider', 'adzuna' );
        $app_id      = get_option( 'ajs_app_id' );
        $app_key     = get_option( 'ajs_app_key' );
        $auto_sync   = get_option( 'ajs_auto_sync', 'no' );
        $countries   = get_option( 'ajs_countries', array() );
        $status      = get_option( 'ajs_sync_status', 'idle' );
        $queue       = get_option( 'ajs_sync_queue', array() );
        $last_sync   = get_option( 'ajs_last_sync_completed' );
        $error       = get_option( 'ajs_last_sync_error' );

        $all_providers = Joby_API::get_all_providers();
        ?>
        <div class="wrap ajs-admin-wrap">
            <div class="ajs-header">
                <img src="<?php echo JOBY_URL . 'assets/icon-white.png'; ?>" alt="Joby Sync" class="ajs-logo">
                <h1>Joby Sync Settings</h1>
            </div>

            <div class="ajs-actions" style="margin-top: 20px; margin-bottom: 30px; display: flex; gap: 10px;">
                <a href="<?php echo admin_url('edit.php?post_type=ajs_job'); ?>" class="button button-primary">View Synced Jobs</a>
                <button type="button" id="ajs-check-updates" class="button button-secondary">Check for Updates</button>
                <button type="button" id="ajs-clear-cache" class="button button-secondary">Clear Cache</button>
            </div>
            
            <div class="ajs-dashboard-grid">
                <?php 
                $stats = Joby_Sync_Engine::get_stats();
                $cards = array(
                    'Total Jobs' => $stats['total_jobs'],
                    'Regions'    => $stats['countries'],
                    'Last Sync'  => $stats['last_sync'] ? human_time_diff($stats['last_sync'], current_time('timestamp')) . ' ago' : 'Never'
                );
                foreach ($cards as $label => $val) : ?>
                    <div class="ajs-stat-card">
                        <span class="ajs-stat-label"><?php echo esc_html($label); ?></span>
                        <span class="ajs-stat-value"><?php echo esc_html($val); ?></span>
                    </div>
                <?php endforeach; ?>
            </div>

            <div class="ajs-main-grid">
                <div class="ajs-grid-left">
                    <div class="ajs-card status-card <?php echo esc_attr($status); ?>">
                        <h3>Real-time Sync Control</h3>
                        <div class="status-header" style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px;">
                            <span class="status-badge"><?php echo esc_html(ucfirst(str_replace('_', ' ', $status))); ?></span>
                            <?php if ( $status === 'in_progress' ) : ?>
                                <span class="tasks-count"><strong><?php echo count($queue); ?></strong> tasks left</span>
                            <?php endif; ?>
                        </div>

                        <?php if ( $status === 'in_progress' ) : ?>
                            <div class="ajs-progress-container" style="background: #eee; height: 10px; border-radius: 5px; overflow: hidden; margin: 15px 0;">
                                <div class="ajs-progress-bar" style="width: 0%; height: 100%; background: var(--ajs-accent); transition: width 0.3s ease;"></div>
                            </div>
                        <?php endif; ?>
                        
                        <div class="ajs-sync-controls" style="display: flex; gap: 10px; margin-top: 15px;">
                            <button id="ajs-trigger-sync" class="button button-primary" <?php disabled($status, 'in_progress'); ?>>
                                <?php echo $status === 'in_progress' ? 'Syncing...' : 'Start Manual Sync'; ?>
                            </button>
                            <?php if ($status === 'in_progress') : ?>
                                <button id="ajs-cancel-sync" class="button button-secondary" style="color: #D70000; border-color: #D70000;">Stop Syncing</button>
                            <?php endif; ?>
                        </div>

                        <?php if ($error && $status !== 'in_progress') : ?>
                            <p style="color: #D70000; font-size: 13px; margin-top: 15px;">⚠️ Last Error: <?php echo esc_html($error); ?></p>
                        <?php endif; ?>
                    </div>

            <form method="post" action="options.php" class="ajs-settings-form">
                <?php settings_fields( 'ajs_settings_group' ); ?>
                
                <div class="ajs-card">
                    <h3>General Configuration</h3>
                    <table class="form-table">
                        <tr>
                            <th>Job Provider</th>
                            <td>
                                <select name="ajs_provider" id="ajs_provider_select" class="regular-text" style="width: 100%;">
                                    <?php foreach ($all_providers as $p_slug => $p_obj) : ?>
                                        <option value="<?php echo $p_slug; ?>" <?php selected($provider, $p_slug); ?>><?php echo esc_html($p_obj->get_name()); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </td>
                        </tr>
                        <tr class="provider-field provider-adzuna" <?php echo $provider !== 'adzuna' ? 'style="display:none;"' : ''; ?>>
                            <th>App ID</th>
                            <td><input type="text" name="ajs_app_id" value="<?php echo esc_attr( $app_id ); ?>" class="regular-text" placeholder="e.g. 1234abcd"></td>
                        </tr>
                        <tr class="provider-field provider-adzuna" <?php echo $provider !== 'adzuna' ? 'style="display:none;"' : ''; ?>>
                            <th>App Key</th>
                            <td>
                                <input type="password" name="ajs_app_key" value="<?php echo esc_attr( $app_key ); ?>" class="regular-text" placeholder="Your secret key">
                                <p class="description"><a href="https://developer.adzuna.com/signup" target="_blank" rel="noopener noreferrer">Get your Adzuna API keys here →</a></p>
                            </td>
                        </tr>
                        <tr>
                            <th>Auto-Sync</th>
                            <td>
                                <label>
                                    <input type="checkbox" name="ajs_auto_sync" value="yes" <?php checked($auto_sync, 'yes'); ?>>
                                    Automatically start syncing after saving settings
                                </label>
                            </td>
                        </tr>
                    </table>
                </div>

                <div class="ajs-card">
                    <h3>Search Locations & Targets</h3>
                    <p class="description">Select the countries you want to pull jobs from. Only countries supported by your chosen provider will appear here.</p>
                    
                    <div id="ajs-country-library" style="margin-top: 15px;">
                        <table class="wp-list-table widefat fixed striped" id="ajs-countries-table">
                            <thead>
                                <tr>
                                    <th>Country</th>
                                    <th>Target Jobs</th>
                                    <th>Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if ( ! empty( $countries ) ) : foreach ( $countries as $index => $country ) : ?>
                                    <tr>
                                        <td>
                                            <select name="ajs_countries[<?php echo $index; ?>][code]" class="ajs-country-selector">
                                                <?php 
                                                $supported = Joby_API::get_provider($provider)->get_supported_countries();
                                                foreach ($supported as $code => $name) : ?>
                                                    <option value="<?php echo $code; ?>" <?php selected($country['code'], $code); ?>><?php echo esc_html($name); ?></option>
                                                <?php endforeach; ?>
                                            </select>
                                        </td>
                                        <td><input type="number" name="ajs_countries[<?php echo $index; ?>][count]" value="<?php echo esc_attr( $country['count'] ); ?>" step="50" min="50" style="width: 100%;"></td>
                                        <td><button type="button" class="button ajs-remove-row">Remove</button></td>
                                    </tr>
                                <?php endforeach; else : ?>
                                    <tr class="empty-row">
                                        <td colspan="3">Click "Add Location" to get started.</td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                            <tfoot>
                                <tr>
                                    <td colspan="3">
                                        <button type="button" id="ajs-add-country" class="button button-secondary">Add Location</button>
                                    </td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                </div>

                <?php submit_button('Save Settings'); ?>
            </form>
                </div> <!-- .ajs-grid-left -->

                <div class="ajs-grid-right">
                    <div class="ajs-card">
                        <h3>Sync Activity Logs</h3>
                        <span id="ajs-toggle-logs" class="ajs-log-toggle">Show Logs Console</span>
                        <div id="ajs-log-console" class="ajs-log-console" style="display: none;">
                            <div class="ajs-log-placeholder">Wait for sync to start...</div>
                        </div>
                    </div>

                    <div class="ajs-card">
                        <h3>System Info</h3>
                        <p style="font-size: 13px; color: #666;">
                            <strong>WP Version:</strong> <?php echo get_bloginfo('version'); ?><br>
                            <strong>PHP Version:</strong> <?php echo PHP_VERSION; ?><br>
                            <strong>Joby Version:</strong> <?php echo JOBY_VERSION; ?>
                        </p>
                    </div>
                </div> <!-- .ajs-grid-right -->
            </div> <!-- .ajs-main-grid -->

            <div class="ajs-footer" style="margin-top: 40px; padding-top: 20px; border-top: 1px solid #E5E5E5; color: #86868B; font-size: 13px; display: flex; justify-content: space-between;">
                <span>&copy; <?php echo date('Y'); ?> Joby Sync by Abolade Greatness. All rights reserved.</span>
                <span>Version <?php echo JOBY_VERSION; ?></span>
            </div>
        </div>

        <script id="ajs-row-template" type="text/template">
            <tr>
                <td>
                    <select name="ajs_countries[{{index}}][code]" class="ajs-country-selector">
                        <?php 
                        $supported = Joby_API::get_provider($provider)->get_supported_countries();
                        foreach ($supported as $code => $name) : ?>
                            <option value="<?php echo $code; ?>"><?php echo esc_html($name); ?></option>
                        <?php endforeach; ?>
                    </select>
                </td>
                <td><input type="number" name="ajs_countries[{{index}}][count]" value="100" step="50" min="50" style="width: 100%;"></td>
                <td><button type="button" class="button ajs-remove-row">Remove</button></td>
            </tr>
        </script>
        <?php
    }

    public function handle_trigger_sync() {
        check_ajax_referer( 'ajs_nonce', 'nonce' );
        if ( ! current_user_can( 'manage_options' ) ) wp_send_json_error( 'Permission denied' );

        Joby_Sync_Engine::get_instance()->start_sync();
        wp_send_json_success( 'Sync started' );
    }

    public function handle_cancel_sync() {
        check_ajax_referer( 'ajs_nonce', 'nonce' );
        if ( ! current_user_can( 'manage_options' ) ) wp_send_json_error( 'Permission denied' );

        update_option('ajs_sync_status', 'idle');
        update_option('ajs_sync_queue', array());
        update_option('ajs_last_sync_error', 'Sync cancelled by user.');
        
        wp_send_json_success( 'Sync cancelled' );
    }

    public function handle_check_updates() {
        check_ajax_referer( 'ajs_nonce', 'nonce' );
        if ( ! current_user_can( 'manage_options' ) ) wp_send_json_error( 'Permission denied' );

        delete_site_transient( 'joby_update_check' );
        delete_site_transient( 'update_plugins' );
        
        // Trigger WP to re-scan for updates immediately
        if (function_exists('wp_update_plugins')) {
            wp_update_plugins();
        }
        
        wp_send_json_success( 'Update check complete. Please refresh the page or check the Plugins menu.' );
    }

    public function handle_get_logs() {
        check_ajax_referer( 'ajs_nonce', 'nonce' );
        if ( ! current_user_can( 'manage_options' ) ) wp_send_json_error( 'Permission denied' );

        $logs = get_site_transient( 'ajs_sync_logs' );
        if ( ! is_array( $logs ) ) $logs = array();

        wp_send_json_success( array(
            'logs'   => $logs,
            'status' => get_option( 'ajs_sync_status', 'idle' ),
            'queue'  => count( get_option( 'ajs_sync_queue', array() ) )
        ) );
    }

    public function handle_clear_cache() {
        check_ajax_referer( 'ajs_nonce', 'nonce' );
        if ( ! current_user_can( 'manage_options' ) ) wp_send_json_error( 'Permission denied' );

        delete_site_transient( 'ajs_sync_logs' );
        delete_site_transient( 'joby_update_check' );
        delete_site_transient( 'update_plugins' );
        
        wp_send_json_success( 'All plugin caches and transients have been cleared.' );
    }

    public function fix_menu_icon_size() {
        ?>
        <style>
            #toplevel_page_joby-sync .wp-menu-image img { width: 20px !important; height: auto !important; padding-top: 7px !important; }
        </style>
        <?php
    }
}
