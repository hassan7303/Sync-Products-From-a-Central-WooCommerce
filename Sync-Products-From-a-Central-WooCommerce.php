<?php

/**
 * Plugin Name: Sync products from a central WooCommerce
 * Description:  Sync products from a central WooCommerce site, let the local store owner pick which products to show and send buyers to the main site to complete checkout. Class-based, production-minded single-file plugin.
 * Version:     1.0.0
 * Author:      hassan ali askari
 */

if (!defined('ABSPATH')) exit;

final class SPFCW_Plugin
{
    private static $instance = null;
    public $api;
    public $admin;

    public static function instance()
    {
        if (self::$instance === null) {
            self::$instance = new self();
            self::$instance->init();
        }
        return self::$instance;
    }

    private function init()
    {
        $this->define_constants();
        $this->includes();
        add_action('plugins_loaded', [$this, 'on_plugins_loaded']);
    }

    private function define_constants()
    {
        define('SPFCW_PATH', plugin_dir_path(__FILE__));
        define('SPFCW_URL', plugin_dir_url(__FILE__));
    }

    private function includes()
    {
        require_once SPFCW_PATH . 'includes/class-spfcw-api-client.php';
        require_once SPFCW_PATH . 'includes/class-spfcw-admin.php';
    }

    public function on_plugins_loaded()
    {
        $this->api   = new SPFCW_API_Client();
        $this->admin = new SPFCW_Admin($this->api);
    }
}

function SPFCW()
{
    return SPFCW_Plugin::instance();
}

SPFCW();
