<?php
if (!defined('ABSPATH')) {
    exit;
}

class CS_ADVANCE_SHIPPING_TRACKING_PROVIDER
{
    private static $instance;

    public static function get_instance()
    {

        if (null === self::$instance) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    public function getPaypalProvider($tsSlug)
    {
        try {
            global $wpdb;
            return $wpdb->get_row($wpdb->prepare('SELECT paypal_slug, provider_url FROM %1s WHERE ts_slug = %s', $wpdb->prefix . 'cs_woo_shippment_provider', $tsSlug));;
        } catch (\Exception $e) {
            csPaypalDebugLog($e->getMessage(), 'CS_ADVANCE_SHIPPING_TRACKING_PROVIDER::getPaypalProvider Exception');
            return false;
        }
    }
}
