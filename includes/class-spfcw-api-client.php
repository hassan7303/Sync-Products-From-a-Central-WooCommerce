<?php
if (!defined('ABSPATH')) exit;

class SPFCW_API_Client
{
    private $base_url;
    private $api_key;

    public function __construct()
    {
        $opts = get_option('spfcw_settings', []);
        $this->base_url = isset($opts['main_site_url']) ? untrailingslashit($opts['main_site_url']) : '';
        $this->api_key  = isset($opts['api_key']) ? $opts['api_key'] : '';
       
    }

    public function get_products()
    {
        if (!$this->base_url || !$this->api_key) {
            return new WP_Error('config', 'API not configured');
        }

        $url = $this->base_url . '/wp-json/spfcw/v1/products';
        $response = wp_remote_get($url, [
            'headers' => [
                'Authorization' => 'Bearer ' . $this->api_key,
                'Accept'        => 'application/json'
            ],
            'timeout' => 60
        ]);
       
        if (is_wp_error($response)) return $response;

        $body = json_decode(wp_remote_retrieve_body($response), true);
        return is_array($body) ? $body : [];
    }

    public function get_product($id)
    {
        $url = $this->base_url . '/wp-json/spfcw/v1/products/' . intval($id);
        $response = wp_remote_get($url, [
            'headers' => [
                'Authorization' => 'Bearer ' . $this->api_key,
                'Accept'        => 'application/json'
            ],
            'timeout' => 10
        ]);
        if (is_wp_error($response)) return $response;
        return json_decode(wp_remote_retrieve_body($response), true);
    }
}
