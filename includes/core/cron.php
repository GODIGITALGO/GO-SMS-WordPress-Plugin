<?php


if (!defined('ABSPATH')) {
    exit;
}

WPNotif_Notification_Cron_Handler::instance();

final class WPNotif_Notification_Cron_Handler
{

    const t = 0.5;
    const CRON_INTERVAL = 10;
    const CRON_JOB = 'wpnotif_notification_cron';
    const TYPE = 'wpn_notification';
    protected static $_instance = null;
    const DATE_FORMAT = 'Y F j, g:i a';
    const PENDING_ORDER_KEY = 'wpn_pending_order_notified';

    public function __construct()
    {
        $this->init_hooks();
    }

    private function init_hooks()
    {
        add_action('init', array($this, 'schedule'));
        add_action('wpnotif_activated', array($this, 'activated'));
        add_action('wpnotif_deactivated', array($this, 'deactivated'));

        add_action(self::CRON_JOB, array($this, 'wp_execute'));

    }

    /**
     *  Constructor.
     */
    public static function instance()
    {
        if (is_null(self::$_instance)) {
            self::$_instance = new self();
        }

        return self::$_instance;
    }

    public function activated()
    {
        $this->schedule();
    }

    public function schedule()
    {
        if (!wp_next_scheduled(self::CRON_JOB)) {
            wp_schedule_event(time(), 'hourly', self::CRON_JOB);
        }
    }

    public function deactivated()
    {
        $timestamp = wp_next_scheduled(self::CRON_JOB);
        wp_unschedule_event($timestamp, self::CRON_JOB);
    }


    public function wp_execute()
    {
        if (!class_exists('WooCommerce')) {
            return;
        }

        $this->pending_payment();
        $this->abandon_cart();
    }

    public function abandon_cart()
    {
        $time = abs(get_option('wpnotif_abandon_cart_notification_time', 2));
        if (empty($time)) {
            return;
        }

        do_action('wpnotif_abandoned_cart', $time);
    }

    public function pending_payment()
    {
        $time = abs(get_option('wpnotif_pending_payment_notification_time', 2));

        if (empty($time)) {
            return;
        }
        $time1 = '-' . ($time) . ' hours';
        $time2 = '-' . ($time - self::t) . ' hours';

        $query = new WC_Order_Query(array(
                'limit' => -1,
                'post_type' => array('shop_order'),
                'post_status' => array('wc-pending'),
                'date_created' => strtotime($time1) . '...' . strtotime($time2)
            )
        );
        $orders = $query->get_orders();

        if (empty($orders)) {
            return;
        }

        foreach ($orders as $order) {
            $check_if_notified = $order->get_meta(self::PENDING_ORDER_KEY, true);
            if (empty($check_if_notified) && $order->get_status() === 'wc-pending') {
                $order->update_meta_data(self::PENDING_ORDER_KEY, time());
                $order->save_meta_data();

                do_action('wpn_wc_notify_order', $order);
            }
        }
    }

}