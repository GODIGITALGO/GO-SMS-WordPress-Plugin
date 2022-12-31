<?php

namespace WPNotif_Compatibility\WC\CART;

use WPNotif;
use WPNotif_Handler;

if (!defined('ABSPATH')) {
    exit;
}

CartNotifier::instance();

final class CartNotifier
{
    const SLUG = 'wpnotif_wc_abandoned_cart';
    const NOTIF_SLUG = 'wpnotif_wc_abandoned_cart_message';
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
        add_filter('wpnotif_notification_list_after_wc_status_customer', array($this, 'add_wpnotif_admin_abandoned_cart'));
        add_filter('wpnotif_notification_list_after_wc_status_admin', array($this, 'add_wpnotif_admin_abandoned_cart'));

        add_filter('wpnotif_notification_options_' . self::SLUG, array(&$this, 'notification_options'), 10);

        add_filter('wpnotif_filter_' . self::SLUG . '_message', [$this, 'update_placeholder'], 10, 2);

    }

    public function add_wpnotif_admin_abandoned_cart($status)
    {

        $add = array(
            self::NOTIF_SLUG => array(
                'label' => esc_attr__('Abandoned Cart Message', 'wpnotif'),
                'message' => '1',
                'placeholder' => '1',
            ),
        );

        return array_merge($status, $add);
    }

    public function notify_abandon_cart($cart)
    {

        if (empty($cart->session_value)) {
            return;
        }
        $cart = json_decode($cart->session_value, JSON_OBJECT_AS_ARRAY);

        if (empty($cart)) {
            return;
        }

        if (empty($cart['customer'])) {
            return;
        }

        $customer = $cart['customer'];
        $user_id = $cart['user_id'];

        $data = array('type' => 'cart', 'customer' => $customer);

        $phone = WPNotif_Handler::get_customer_mobile($user_id, $data, false);

        if (empty($phone)) {
            return;
        }

        $phone = $phone['countrycode'] . $phone['mobile'];

        $handler_instance = WPNotif_Handler::instance();
        $notif_key = self::NOTIF_SLUG;

        $data = array(
            'cart_content' => $cart
        );
        $notification_data = WPNotif::data_type(self::SLUG, $data);
        $notification_data['user_phone'] = $phone;
        $data = $handler_instance->notify_user($user_id, $notification_data, $notif_key, 1);

        if ($handler_instance->isWhatsappEnabled()) {
            $data = $handler_instance->notify_user($user_id, $notification_data, $notif_key, 1001);
        }

    }

    public static function instance()
    {
        if (is_null(self::$_instance)) {
            self::$_instance = new self();
        }

        return self::$_instance;
    }

    public function update_placeholder($message, $data)
    {

        $cart_content = $data['cart_content'];

        $customer = $cart_content['customer'];
        $first_name = $customer['first_name'];
        $last_name = $customer['last_name'];

        $totals = $cart_content['totals'];

        $discount = $totals['discount_total'];
        $sub_total = $totals['subtotal'];
        $tax = $totals['total_tax'];
        $shipping = $totals['shipping_total'];

        $fees_total = $totals['fee_total'];

        $total = $sub_total + $shipping + $tax + $fees_total - $discount;

        $items = $cart_content['items'];


        $total_items = 0;
        $product_names = array();
        $product_variations = array();
        $product_sku = array();

        foreach ($items as $item) {
            $item_meta = '';

            $product_id = $item['product_id'];
            $qty = $item['quantity'];
            $variation_id = $item['variation_id'];

            $product = new \WC_Product($product_id);

            if (!empty($variation_id)) {
                $variation = new \WC_Product_Variation($variation_id);
                $attributes = $variation->get_attribute_summary();

                $item_meta = ' (' . $attributes . ')';
            }
            $product_name = $product->get_name();

            $product_names[] = $product_name;
            $product_variations[] = $product_name . $item_meta;

            $total_items += $qty;

        }

        $placeholder_values = array(
            '{{wc-ac-product-names}}' => implode(", ", $product_names),
            '{{wc-ac-product-names-br}}' => implode(", " . PHP_EOL, $product_names),

            '{{wc-ac-product-names-variable}}' => implode(", ", $product_variations),
            '{{wc-ac-product-names-variable-br}}' => implode(", " . PHP_EOL, $product_variations),

            '{{wc-ac-total-products}}' => sizeof($items),
            '{{wc-ac-total-items}}' => $total_items,
            '{{wc-ac-order-amount}}' => $total,
            '{{wc-ac-discount}}' => $discount,
            '{{wc-ac-tax}}' => $tax,
            '{{wc-ac-subtotal}}' => $sub_total,
            '{{wc-ac-total}}' => $total,
            '{{wc-ac-shipping}}' => $shipping,
            '{{wc-ac-first-name}}' => $first_name,
            '{{wc-ac-last-name}}' => $last_name,
        );

        $message = strtr($message, $placeholder_values);

        return $message;
    }

    public function notification_options($values)
    {
        $values['identifier'] = 'customer';
        return $values;
    }

}

