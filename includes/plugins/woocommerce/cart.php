<?php

namespace WPNotif_Compatibility\WC\CART;

use WPNotif;

if (!defined('ABSPATH')) {
    exit;
}

require_once 'abandoned_cart.php';

WCCart::instance();

final class WCCart
{
    const table = 'wpnotif_wc_cart_sessions';
    const pending_status = 'pending';
    const trigger_status = 'triggered';
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
        add_action('woocommerce_cart_emptied', array($this, 'destroy_cart_session'));


        add_action('woocommerce_add_to_cart', array($this, 'woocommerce_cart_updated'), PHP_INT_MAX);
        add_action('woocommerce_cart_item_removed', array($this, 'woocommerce_cart_updated'), PHP_INT_MAX);
        add_action('woocommerce_cart_item_restored', array($this, 'woocommerce_cart_updated'), PHP_INT_MAX);
        add_action('woocommerce_after_cart_item_quantity_update', array($this, 'woocommerce_cart_updated'), PHP_INT_MAX);
        add_action('woocommerce_calculate_totals', array($this, 'woocommerce_calculate_totals'), PHP_INT_MAX);

        add_action('wpnotif_activated', array($this, 'init_db'));

        add_action('wpnotif_abandoned_cart', array($this, 'abandoned_cart'));

    }

    public function abandoned_cart($minimum_hours)
    {
        global $wpdb;

        $pending_status = self::pending_status;
        $minimum_time = time() - $minimum_hours * 60 * 60;

        $tb = $this->get_table_name();

        $sql = "SELECT * FROM $tb WHERE notif_status = %s and time < %d";

        $carts = $wpdb->get_results(
            $wpdb->prepare($sql, $pending_status, $minimum_time)
        );
        foreach ($carts as $cart) {
            $session_key = $cart->session_key;
            $this->update_status($session_key, self::trigger_status);

            CartNotifier::instance()->notify_abandon_cart($cart);
        }
    }

    private function get_table_name()
    {
        global $wpdb;
        return $wpdb->prefix . self::table;;
    }

    public function update_status($session_key, $notif_status)
    {
        global $wpdb;

        $where = array('session_key' => $session_key);
        $data = array('notif_status' => $notif_status);

        return $wpdb->update($this->get_table_name(), $data, $where);
    }

    public static function instance()
    {
        if (is_null(self::$_instance)) {
            self::$_instance = new self();
        }

        return self::$_instance;
    }

    public function woocommerce_calculate_totals()
    {
        $this->process_cart(false);
    }

    public function process_cart($update_status)
    {
        $cart = WC()->cart;

        $session = WC()->session;
        $session_key = $session->get_customer_id();

        $customer = $session->get('customer');

        $totals = $cart->get_totals();
        $items = $cart->get_cart_contents();

        $total = $cart->get_total('float');

        if ($cart->is_empty()) {
            $this->delete_cart($session_key);
        } else {
            $cart_data = array(
                'items' => $items,
                'totals' => $totals,
                'customer' => $customer,
                'user_id' => get_current_user_id(),
                'total_value' => $total,
            );

            $data = array(
                'session_key' => $session_key,
                'session_value' => json_encode($cart_data),
                'session_expiry' => $this->get_session_expiry(),
                'time' => time(),
            );

            if ($update_status) {
                $data['notif_status'] = self::pending_status;
            }

            $this->add_cart_data($data);
        }

    }

    private function delete_cart($session_key)
    {
        if (empty($session_key)) {
            return;
        }
        global $wpdb;
        $wpdb->delete($this->get_table_name(), array('session_key' => $session_key));
    }

    private function get_session_expiry()
    {
        return time() + intval(apply_filters('wc_session_expiration', 60 * 60 * 48));
    }

    private function add_cart_data($data)
    {
        global $wpdb;

        return $wpdb->replace($this->get_table_name(), $data);
    }

    public function woocommerce_cart_updated()
    {
        $this->process_cart(true);
    }

    public function destroy_cart_session()
    {
        $session = WC()->session;
        $session_key = $session->get_customer_id();
        $this->delete_cart($session_key);
    }

    public function init_db()
    {
        global $wpdb;
        $tb = $this->get_table_name();
        if ($wpdb->get_var("SHOW TABLES LIKE '$tb'") != $tb) {
            $charset_collate = $wpdb->get_charset_collate();
            $sql = "CREATE TABLE $tb (
                                    session_id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                                    session_key char(32) NOT NULL,
                                    session_value longtext NOT NULL,
                                    session_expiry BIGINT UNSIGNED NOT NULL,
                                    notif_status VARCHAR (100) NOT NULL,
                                    time BIGINT UNSIGNED NOT NULL,
                                    PRIMARY KEY  (session_id),
                                    UNIQUE KEY session_key (session_key)
                                    ) $charset_collate;";

            require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
            dbDelta(array($sql));
        }

    }

}
