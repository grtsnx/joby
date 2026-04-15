<?php
/**
 * Joby Sync Updater
 * Handles automated update checks via GitHub API
 */
class Joby_Updater {

    private $slug;
    private $plugin_file;
    private $github_repo;
    private $cache_key;

    public function __construct( $plugin_file, $github_repo ) {
        $this->plugin_file = $plugin_file;
        $this->slug        = dirname( plugin_basename( $plugin_file ) );
        $this->github_repo = $github_repo;
        $this->cache_key   = 'joby_update_check';

        // Check for updates periodically
        add_filter( 'pre_set_site_transient_update_plugins', array( $this, 'check_for_update' ) );
        
        // Show plugin information details modal
        add_filter( 'plugins_api', array( $this, 'plugin_info' ), 20, 3 );
    }

    /**
     * Get the latest release from GitHub
     */
    private function get_latest_github_release() {
        $cache = get_site_transient( $this->cache_key );
        if ( $cache ) return $cache;

        $url = "https://api.github.com/repos/{$this->github_repo}/releases/latest";
        
        $response = wp_remote_get( $url, array(
            'timeout' => 10,
            'headers' => array(
                'Accept' => 'application/vnd.github.v3+json',
                'User-Agent' => 'WordPress/' . get_bloginfo( 'version' ) . '; ' . get_bloginfo( 'url' )
            )
        ) );

        if ( is_wp_error( $response ) || wp_remote_retrieve_response_code( $response ) !== 200 ) {
            return false;
        }

        $data = json_decode( wp_remote_retrieve_body( $response ) );
        if ( ! $data || ! isset( $data->tag_name ) ) {
            return false;
        }

        // Cache for 12 hours
        set_site_transient( $this->cache_key, $data, 12 * HOUR_IN_SECONDS );
        
        return $data;
    }

    /**
     * Inject update information into WordPress
     */
    public function check_for_update( $transient ) {
        if ( empty( $transient->checked ) ) return $transient;

        $release = $this->get_latest_github_release();
        if ( ! $release ) return $transient;

        $new_version = ltrim( $release->tag_name, 'v' );
        
        // Compare versions
        if ( version_compare( JOBY_VERSION, $new_version, '<' ) ) {
            $obj = new stdClass();
            $obj->slug        = $this->slug;
            $obj->plugin      = plugin_basename( $this->plugin_file );
            $obj->new_version = $new_version;
            $obj->url         = $release->html_url;
            $obj->package     = $release->assets[0]->browser_download_url ?? $release->zipball_url;
            
            $transient->response[ $obj->plugin ] = $obj;
        }

        return $transient;
    }

    /**
     * Handle the "View details" modal info
     */
    public function plugin_info( $res, $action, $args ) {
        if ( $action !== 'plugin_information' ) return $res;
        if ( $this->slug !== $args->slug ) return $res;

        $release = $this->get_latest_github_release();
        if ( ! $release ) return $res;

        $res = new stdClass();
        $res->name           = 'Joby Sync';
        $res->slug           = $this->slug;
        $res->version        = ltrim( $release->tag_name, 'v' );
        $res->author         = 'Abolade Greatness';
        $res->homepage       = 'https://github.com/grtsnx/joby';
        $res->download_link  = $release->assets[0]->browser_download_url ?? $release->zipball_url;
        $res->tested         = '6.5';
        $res->requires       = '5.0';
        $res->last_updated   = $release->published_at;
        $res->sections       = array(
            'description' => 'Dynamically fetch thousands of jobs daily from a remote API. This update contains the latest improvements and architectural changes.',
            'changelog'   => $this->parse_markdown( $release->body )
        );

        return $res;
    }

    /**
     * Simple Markdown to HTML parser for changelog
     */
    private function parse_markdown( $text ) {
        if ( ! $text ) return 'View details on GitHub.';

        // Convert common GitHub Markdown to HTML
        $text = preg_replace('/### (.*)/', '<h4>$1</h4>', $text);
        $text = preg_replace('/## (.*)/', '<h3>$1</h3>', $text);
        $text = preg_replace('/\*\* (.*)\*\*/', '<strong>$1</strong>', $text);
        $text = preg_replace('/\* (.*)/', '<li>$1</li>', $text);
        
        // Wrap <li> in <ul>
        if (strpos($text, '<li>') !== false) {
            $text = '<ul>' . $text . '</ul>';
        }

        return wp_kses_post( wpautop( $text ) );
    }
}
