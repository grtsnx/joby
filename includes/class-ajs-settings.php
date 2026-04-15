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

            <div class="ajs-actions" style="margin-top: 20px;">
                <a href="<?php echo admin_url('edit.php?post_type=ajs_job'); ?>" class="button button-primary">View Synced Jobs</a>
            </div>
            
            <div class="ajs-card status-card <?php echo esc_attr($status); ?>">
                <h3>Sync Status: <span class="status-badge"><?php echo esc_html(ucfirst(str_replace('_', ' ', $status))); ?></span></h3>
                <?php if ( $status === 'in_progress' ) : ?>
                    <p>Tasks remaining: <strong><?php echo count($queue); ?></strong></p>
                    <div class="ajs-progress-container" style="background: #eee; height: 8px; border-radius: 4px; overflow: hidden; margin: 10px 0;">
                        <div class="ajs-progress-bar" style="width: 0%; height: 100%; background: var(--apple-accent); transition: width 0.3s ease;"></div>
                    </div>
                <?php else : ?>
                    <p>Last completed: <strong><?php echo $last_sync ? date('Y-m-d H:i:s', $last_sync) : 'Never'; ?></strong></p>
                    <?php if ($error) : ?>
                        <p style="color: #D70000; font-size: 13px;">⚠️ Last Error: <?php echo esc_html($error); ?></p>
                    <?php endif; ?>
                <?php endif; ?>
                
                <div class="ajs-sync-controls" style="display: flex; gap: 10px; margin-top: 15px;">
                    <button id="ajs-trigger-sync" class="button button-primary" <?php disabled($status, 'in_progress'); ?>>
                        <?php echo $status === 'in_progress' ? 'Syncing...' : 'Start Manual Sync'; ?>
                    </button>
                    <?php if ($status === 'in_progress') : ?>
                        <button id="ajs-cancel-sync" class="button button-secondary" style="color: #D70000; border-color: #D70000;">Stop Syncing</button>
                    <?php endif; ?>
                </div>
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

    public function fix_menu_icon_size() {
        ?>
        <style>
            #toplevel_page_joby-sync .wp-menu-image img { width: 20px !important; height: auto !important; padding-top: 7px !important; }
        </style>
        <?php
    }
}
