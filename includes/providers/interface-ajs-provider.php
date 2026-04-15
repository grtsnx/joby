<?php
/**
 * Interface for all job providers
 */
interface Joby_Provider_Interface {
    public function get_name();
    public function get_slug();
    public function get_supported_countries();
    public function has_credentials();
    public function fetch_jobs($country, $count, $page = 1);
}
