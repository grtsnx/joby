<?php
/**
 * Arbeitnow Provider Implementation (Global Remote)
 */
class Joby_Provider_Arbeitnow implements Joby_Provider_Interface {
    
    public function get_name() { return 'Arbeitnow (Global Remote)'; }
    public function get_slug() { return 'arbeitnow'; }
    public function has_credentials() { return false; }

    public function get_supported_countries() {
        return array(
            'global' => 'Global (Remote)'
        );
    }

    public function fetch_jobs($country, $count, $page = 1) {
        // Arbeitnow doesn't use country codes in the same way, it's global remote
        $url = "https://www.arbeitnow.com/api/job-board-api";
        $response = wp_remote_get($url, array('timeout' => 20));

        if (is_wp_error($response)) return $response;
        
        $body = json_decode(wp_remote_retrieve_body($response));
        if (empty($body->data)) return array();

        // Arbeitnow returns a lot of jobs, we slice it to requested count
        $results = array_slice($body->data, 0, $count);

        $jobs = array();
        $jobs = array();
        foreach ($results as $result) {
            // Mapping Arbeitnow data to our professional taxonomies
            $nature = !empty($result->job_types) ? $result->job_types[0] : 'Remote';
            $category = !empty($result->tags) ? $result->tags[0] : 'General';

            $jobs[] = array(
                'id'          => md5($result->slug),
                'title'       => $result->title,
                'description' => $result->description, // Full HTML provided by Arbeitnow!
                'company'     => $result->company_name,
                'location'    => $result->location,
                'url'         => $result->url,
                'type'        => $nature,
                'category'    => $category,
                'salary'      => '', // Arbeitnow rarely provides numeric salary in a separate field
                'provider'    => 'arbeitnow'
            );
        }

        return $jobs;
    }
}
