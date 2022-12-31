<?php


if (!defined('ABSPATH')) {
    exit;
}

final class WPNotif_WC_Stock
{
    protected static $_instance = null;

    public $product_type = 'product';

    /**
     *  Constructor.
     */
    public function __construct()
    {
        $this->init_hooks();
    }

    private function init_hooks()
    {
        $this->wc_notifications_hooks();
    }

    public function wc_notifications_hooks()
    {
        if (!class_exists('WooCommerce')) {
            return;
        }
        add_filter('wpnotif_notification_list_admin', array($this, 'add_stock_notifications'));
        add_filter('wpnotif_notification_list_vendor', array($this, 'add_stock_notifications'));

        add_action('woocommerce_low_stock_notification', array($this, 'low_stock'));
        add_action('woocommerce_no_stock_notification', array($this, 'no_stock'));
        add_filter('wpnotif_filter_' . $this->product_type . '_message', array(
            $this,
            'update_product_placeholder'
        ), 10, 2);

    }

    public static function instance()
    {
        if (is_null(self::$_instance)) {
            self::$_instance = new self();
        }

        return self::$_instance;
    }

    public function update_product_placeholder($message, $product)
    {

        $placeholder_values = array(
            '{{product-name}}' => wp_strip_all_tags($product->get_formatted_name()),
            '{{product-quantity}}' => wp_strip_all_tags($product->get_stock_quantity()),
        );
        $message = str_replace(array_keys($placeholder_values), $placeholder_values, $message);

        return $message;
    }

    public function add_stock_notifications($notifications)
    {
        $notifications['low_stock'] = array(
            'label' => esc_attr__('Low Stock', 'wpnotif'),
            'message' => '1',
            'placeholder' => '1',
        );
        $notifications['no_stock'] = array(
            'label' => esc_attr__('No Stock', 'wpnotif'),
            'message' => '1',
            'placeholder' => '1',
        );

        return $notifications;
    }

    public function low_stock($product)
    {
        $key = 'low_stock';
        $this->notify_admin($product, $key);
        $this->notify_vendor($product, $key);
    }

    public function no_stock($product)
    {
        $key = 'no_stock';
        $this->notify_admin($product, $key);
        $this->notify_vendor($product, $key);
    }

    public function notify_admin($product, $key)
    {

        $notification_data = WPNotif::data_type($this->product_type, $product, 1);

        $data = WPNotif_Handler::instance()->notify_user(0, $notification_data, $key, 1);
        if ($data == -10) {
            $fail = true;
        }
        if (WPNotif_Handler::isWhatsappEnabled()) {
            $data = WPNotif_Handler::instance()->notify_user(0, $notification_data, $key, 1001);
        }
    }

    public function notify_vendor($product, $key)
    {

        WPNotif_Vendor_Notifications::instance()->notify_stocks($key, $this->product_type, $product);
    }
}