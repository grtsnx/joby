<?php
/**
 * Adzuna Provider Implementation
 */
class Joby_Provider_Adzuna implements Joby_Provider_Interface {
    
    private $app_id;
    private $app_key;

    public function __construct($app_id = '', $app_key = '') {
        $this->app_id = $app_id;
        $this->app_key = $app_key;
    }

    public function get_name() { return 'Adzuna'; }
    public function get_slug() { return 'adzuna'; }
    public function has_credentials() { return true; }

    public function get_supported_countries() {
        return array(
            'at' => 'Austria',
            'au' => 'Australia',
            'be' => 'Belgium',
            'br' => 'Brazil',
            'ca' => 'Canada',
            'ch' => 'Switzerland',
            'de' => 'Germany',
            'es' => 'Spain',
            'fr' => 'France',
            'gb' => 'United Kingdom',
            'in' => 'India',
            'it' => 'Italy',
            'mx' => 'Mexico',
            'nl' => 'Netherlands',
            'nz' => 'New Zealand',
            'pl' => 'Poland',
            'sg' => 'Singapore',
            'us' => 'USA',
            'za' => 'South Africa'
        );
    }

    public function fetch_jobs($country, $count, $page = 1) {
        if (empty($this->app_id) || empty($this->app_key)) {
            return new WP_Error('missing_api_keys', 'Adzuna requires an App ID and App Key.');
        }

        $url = "https://api.adzuna.com/v1/api/jobs/{$country}/search/{$page}?" . http_build_query(array(
            'app_id'           => $this->app_id,
            'app_key'          => $this->app_key,
            'results_per_page' => $count,
            'content-type'    => 'application/json'
        ));

        $response = wp_remote_get($url, array('timeout' => 20));

        // Store diagnostic info
        $diag = array(
            'url'           => $url,
            'response_code' => wp_remote_retrieve_response_code($response),
            'message'       => wp_remote_retrieve_response_message($response),
            'time'          => current_time('mysql')
        );
        set_site_transient('ajs_last_api_response', $diag, HOUR_IN_SECONDS);

        if (is_wp_error($response)) return $response;
        
        $body = json_decode(wp_remote_retrieve_body($response));
        if (empty($body->results)) return array();

        $jobs = array();
        foreach ($body->results as $result) {
            $jobs[] = array(
                'id'          => $result->id,
                'title'       => $result->title,
                'description' => $result->description,
                'company'     => $result->company->display_name ?? '',
                'location'    => $result->location->display_name ?? '',
                'url'         => $result->redirect_url,
                'type'        => $result->contract_type ?? 'Full Time',
                'salary'      => $result->salary_min ?? '',
            );
        }

        return $jobs;
    }
}
