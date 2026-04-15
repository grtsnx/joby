<?php

/**
 * Joby API Client
 */
class Joby_API
{

    private $app_id;
    private $app_key;
    private $base_url = 'https://api.adzuna.com/v1/api/jobs/';

    public function __construct()
    {
        $this->app_id  = get_option('ajs_app_id');
        $this->app_key = get_option('ajs_app_key');
    }

    /**
     * Fetch jobs for a specific country and page
     * 
     * @param string $country_code 2-letter country code
     * @param int    $page          Page number
     * @return array|WP_Error
     */
    public function fetch_jobs($country_code, $page = 1)
    {
        if (! $this->app_id || ! $this->app_key) {
            return new WP_Error('missing_credentials', 'Remote API App ID or App Key is missing.');
        }

        $url = $this->base_url . strtolower($country_code) . '/search/' . $page;

        $params = array(
            'app_id'           => $this->app_id,
            'app_key'          => $this->app_key,
            'results_per_page' => 50,
            'content-type'     => 'application/json'
        );

        $request_url = add_query_arg($params, $url);

        $response = wp_remote_get($request_url, array('timeout' => 30));

        if (is_wp_error($response)) {
            return $response;
        }

        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);

        if (! $data || isset($data['error'])) {
            return new WP_Error('api_error', isset($data['display_message']) ? $data['display_message'] : 'Unknown API error.');
        }

        return $data;
    }
}
