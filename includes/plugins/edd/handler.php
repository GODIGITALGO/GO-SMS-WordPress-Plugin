<?php

namespace WPNotif_Compatibility\EDD;

use WPNotif;
use WPNotif_Handler;

if (!defined('ABSPATH')) {
    exit;
}

EDD::instance();

final class EDD
{
    const SLUG = 'wpnotif_edd';
    const NOTIF_SLUG = 'wpnotif_edd_sale_notif';
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
        require_once 'checkout.php';
        EDDCheckout::instance();
        add_action('edd_complete_purchase', [$this, 'edd_complete_purchase'], 10, 3);

        add_filter('wpnotif_notification_list_customer', array($this, 'add_wpnotif_admin_edd'));
        add_filter('wpnotif_notification_list_admin', array($this, 'add_wpnotif_admin_edd'));

        add_filter('wpnotif_notification_options_' . self::SLUG, array(&$this, 'notification_options'), 10);

        add_filter('wpnotif_filter_' . self::SLUG . '_message', [$this, 'update_placeholder'], 10, 2);

    }

    public static function instance()
    {
        if (is_null(self::$_instance)) {
            self::$_instance = new self();
        }

        return self::$_instance;
    }

    public function add_wpnotif_admin_edd($status)
    {
        if (!class_exists('EDD_Requirements_Check')) {
            return $status;
        }
        $add = array(
            self::NOTIF_SLUG => array(
                'label' => esc_attr__('Easy Digital Downloads sale', 'wpnotif'),
                'message' => '1',
                'placeholder' => '1',
            ),
        );

        return array_merge($status, $add);
    }

    /**
     * Runs **when** a purchase is marked as "complete".
     *
     * @param int $order_id Payment ID.
     * @param /EDD_Payment  $payment    EDD_Payment object containing all payment data.
     * @param /EDD_Customer $customer   EDD_Customer object containing all customer data.
     * @since 2.8 Added EDD_Payment and EDD_Customer object to action.
     *
     */
    public function edd_complete_purchase($order_id, $payment, $customer)
    {

        $edd_phone = wpn_edd_email_tag_phone($order_id);

        $user_id = 0;
        if (!empty($customer)) {
            $user_id = $customer->user_id;
        }

        $handler_instance = WPNotif_Handler::instance();
        $notif_key = self::NOTIF_SLUG;

        $data = array(
            'edd_order_id' => $order_id
        );
        $notification_data = WPNotif::data_type(self::SLUG, $data);
        $notification_data['user_phone'] = $edd_phone;
        $data = $handler_instance->notify_user($user_id, $notification_data, $notif_key, 1);

        if ($handler_instance->isWhatsappEnabled()) {
            $data = $handler_instance->notify_user($user_id, $notification_data, $notif_key, 1001);
        }

    }

    public function update_placeholder($message, $data)
    {
        $order_id = $data['edd_order_id'];
        $download_list = edd_email_tag_download_list_plain($order_id);
        $file_urls = str_replace("<br/>", "\n", edd_email_tag_file_urls($order_id));
        $email = edd_email_tag_user_email($order_id);
        $name = edd_email_tag_first_name($order_id);
        $full_name = edd_email_tag_fullname($order_id);
        $username = edd_email_tag_username($order_id);
        $billing_address = edd_email_tag_billing_address($order_id);
        $receipt_id = edd_email_tag_receipt_id($order_id);
        $discount_codes = edd_email_tag_discount_codes($order_id);
        $date = edd_email_tag_date($order_id);
        $subtotal = edd_email_tag_subtotal($order_id);
        $tax = edd_email_tag_tax($order_id);
        $price = edd_email_tag_price($order_id);
        $payment_method = edd_email_tag_payment_method($order_id);
        $buyer_ip = edd_email_tag_ip_address($order_id);
        $site_name = get_bloginfo();
        $edd_phone = wpn_edd_email_tag_phone($order_id);

        $receipt_link = esc_url(
            add_query_arg(
                array(
                    'payment_key' => urlencode(edd_get_payment_key($order_id)),
                    'edd_action' => 'view_receipt',
                ),
                home_url()
            )
        );

        $placeholder_values = array(
            '{{edd-download_list}}' => $download_list,
            '{{edd-file_urls}}' => $file_urls,
            '{{edd-name}}' => $name,
            '{{edd-fullname}}' => $full_name,
            '{{edd-username}}' => $username,
            '{{edd-user_email}}' => $email,
            '{{edd-billing_address}}' => $billing_address,
            '{{edd-date}}' => $date,
            '{{edd-subtotal}}' => $subtotal,
            '{{edd-tax}}' => $tax,
            '{{edd-price}}' => $price,
            '{{edd-payment_id}}' => $order_id,
            '{{edd-receipt_id}}' => $receipt_id,
            '{{edd-payment_method}}' => $payment_method,
            '{{edd-sitename}}' => $site_name,
            '{{edd-receipt_link}}' => $receipt_link,
            '{{edd-discount_codes}}' => $discount_codes,
            '{{edd-ip_address}}' => $buyer_ip,
            '{{edd-phone}}' => $edd_phone,
            '{{edd-wpnotif-phone}}' => $edd_phone,
        );

        $message = strtr($message, $placeholder_values);

        return $message;
    }

    public function notification_options($values)
    {
        $values['identifier'] = 'customer';
        $values['different_gateway_content'] = 'off';
        return $values;
    }

}

