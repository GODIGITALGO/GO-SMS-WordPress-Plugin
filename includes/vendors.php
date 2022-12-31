<?php

if (!defined('ABSPATH')) {
    exit;
}

final class WPNotif_Vendor_Notifications
{
    public static $type = 'vendor';
    protected static $_instance = null;

    /**
     *  Constructor.
     */
    public function __construct()
    {
        $this->init_hooks();
    }

    private function init_hooks()
    {
        $this->vendor_notifications_hooks();
        add_filter('wpnotif_filter_' . self::$type . '_message', array($this, 'item_placeholder'), 10, 2);

        add_filter('wpnotif_' . self::$type . '_placeholder_args', array($this, 'advance_item_placeholder'), 10, 4);

        add_filter('wpnotif_additional_placeholder_args_order', array($this, 'add_vendor_data'), 10, 4);


        add_filter('wpnotif_notification_options_' . self::$type, array($this, 'notification_options'), 10);
        add_filter('wpnotif_notification_options_product', array($this, 'notification_options'), 10);

    }

    public function vendor_notifications_hooks()
    {
        add_action('wpnotif_order_status_change', array($this, 'order_trigger'), 10);
    }

    public static function instance()
    {
        if (is_null(self::$_instance)) {
            self::$_instance = new self();
        }
        return self::$_instance;
    }

    public function notification_options($values)
    {
        $values['identifier'] = 'vendor';
        $values['different_gateway_content'] = 'off';
        return $values;
    }

    public function get_vendor_id($item)
    {
        $vendor_id = false;
        $product_id = $item->get_product_id();

        if (function_exists('yith_get_vendor')) {
            $vendor = yith_get_vendor($item->get_product(), 'product');
            $vendor_id = $vendor->get_owner();
        }
        if (empty($vendor_id)) {
            $vendor_id = get_post_field('post_author', $product_id);
        }
        return $vendor_id;
    }

    public function order_trigger($order_id)
    {
        $vendors = array();
        $order = new WC_Order($order_id);
        foreach ($order->get_items() as $item_id => $item) {
            $vendor_id = $this->get_vendor_id($item);

            if (isset($vendors[$vendor_id])) {
                $vendors[$vendor_id][] = $item;
            } else {
                $vendors[$vendor_id] = array($item);
            }

        }
        $this->process($vendors, $order);
    }

    public function process($vendors, $order)
    {

        foreach ($vendors as $vendor_id => $items) {
            $vendor = get_user_by('ID', $vendor_id);

            if ($vendor == null) continue;

            $key = 'wc-' . $order->get_status();
            $notification_data = array('items' => $items, 'order' => $order, 'vendor' => $vendor);
            $this->notify($key, $vendor_id, self::$type, $notification_data);
        }
    }

    public function notify_stocks($key, $type, $product)
    {
        $product_id = $product->get_id();
        $vendor_id = get_post_field('post_author', $product_id);
        $this->notify($key, $vendor_id, $type, $product);
    }

    public function notify($key, $vendor_id, $data_type, $notification_data)
    {
        $notification_data = WPNotif::data_type($data_type, $notification_data, 2);
        $data = WPNotif_Handler::instance()->notify_user($vendor_id, $notification_data, $key, 1);

        if ($data == -10) {
            $fail = true;
        }

        if (WPNotif_Handler::isWhatsappEnabled()) {
            $data = WPNotif_Handler::instance()->notify_user($vendor_id, $notification_data, $key, 1001);
        }
    }

    public function add_vendor_data($value, $placeholder, $msg, $order)
    {

        if (strpos($placeholder, 'vendor-') === 0) {
            $meta_key = str_replace('vendor-', '', $placeholder);
            if ($meta_key === 'name') {
                $vendor_names = array();
                $vendors = array();
                foreach ($order->get_items() as $item) {
                    $vendor_id = $this->get_vendor_id($item);
                    if (in_array($vendor_id, $vendors)) {
                        continue;
                    }
                    $vendors[] = $vendor_id;
                    $vendor = get_user_by('ID', $vendor_id);
                    if (!empty($vendor)) {
                        $vendor_names[] = $vendor->display_name;
                    }
                }
                return implode(",", $vendor_names);
            }
        }

        return $value;
    }

    public function advance_item_placeholder($value, $placeholder, $msg, $data)
    {
        if (strpos($placeholder, 'vendor-') === 0) {
            $items = $data['items'];
            $meta_key = str_replace('vendor-', '', $placeholder);

            if ($meta_key === 'name') {
                if (!empty($data['vendor'])) {
                    return $data['vendor']->display_name;
                }
                return '';
            }

            $item_meta = array();
            foreach ($items as $item) {
                $item_value = $item->get_meta($meta_key);
                if (!empty($item_value)) {
                    $item_meta[] = $item->get_meta($meta_key);
                }
            }

            if (!empty($item_value)) {
                return implode(", ", $item_value);
            }
        }

        return $value;
    }

    public function item_placeholder($msg, $data)
    {
        $order = $data['order'];

        $items = $data['items'];
        $item_names = array();
        $item_details = array();
        $product_sku = array();
        $total_quantity = 0;
        $total = 0;

        $currency_code = $order->get_currency();
        $currency_symbol = wpn_get_currency_symbol($currency_code);

        $product_price_quantity = array();

        foreach ($items as $item) {
            $product = $item->get_product();

            $item_name = $item->get_name();
            $quantity = $item->get_quantity();

            $item_names[] = $item->get_name();
            $item_details[] = $item->get_name() . ' x ' . $quantity;
            $total_quantity += $quantity;

            $item_total = $item->get_total_tax() + $item->get_total();

            $total += $item_total;

            $product_sku[] = '(' . $item_name . ') ' . $product->get_sku();
            //$product_sku[] = $product->get_sku();

            $product_price_quantity_suffix = '';
            if ($quantity > 1) {
                $product_price_quantity_suffix = ' (x' . $quantity . ')';
            }
            $product_price_quantity[] = $item_name . $product_price_quantity_suffix . ' - ' . $currency_symbol . ' ' . $total;
        }

        $placeholder_values = array(
            '{{vendor-product-names}}' => implode(", ", $item_names),
            '{{vendor-total-items}}' => $total_quantity,
            '{{vendor-order-amount}}' => ceil($total),
            '{{vendor-order-items}}' => implode(", ", $item_details),
            '{{vendor-sku}}' => implode(", ", $product_sku),
            '{{vendor-order-items-price}}' => implode(", ", $product_price_quantity),
        );
        $msg = str_replace(array_keys($placeholder_values), $placeholder_values, $msg);

        return $msg;
    }
}