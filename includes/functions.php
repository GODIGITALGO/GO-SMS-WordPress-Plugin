<?php

use libphonenumber\NumberParseException;
use libphonenumber\PhoneNumberFormat;
use libphonenumber\PhoneNumberUtil;

defined('ABSPATH') || exit;

require_once plugin_dir_path(__FILE__) . 'basics.php';
require_once plugin_dir_path(__FILE__) . 'filters.php';
require_once plugin_dir_path(__FILE__) . 'gateway_functions.php';
require_once plugin_dir_path(__FILE__) . 'post_notifications.php';
require_once plugin_dir_path(__FILE__) . 'user_notifications.php';
require_once plugin_dir_path(__FILE__) . 'wc_stocks.php';
require_once plugin_dir_path(__FILE__) . 'vendors.php';
require_once plugin_dir_path(__FILE__) . 'admin_functions.php';
require_once plugin_dir_path(__FILE__) . 'core/installer.php';
require_once plugin_dir_path(__FILE__) . 'core/cron.php';

final class WPNotif_Handler
{

    protected static $_instance = null;

    public $update_web_db = true;

    public $order_note = false;


    public $post_notifications;
    public $user_notifications;
    public $wc_notifications;
    public $vendor_notifications;

    public $notify_admin = true;


    public static $sms = 1;
    public static $whatsapp = 1001;
    public static $notified_orders = array();


    public $sendSMS = true;
    public $sendWhatsapp = true;

    /**
     *  Constructor.
     */
    public function __construct()
    {
        $this->init_hooks();
        $this->post_notifications = WPNotif_Post_Notifications::instance();
        $this->user_notifications = WPNotif_User_Notifications::instance();
        $this->wc_notifications = WPNotif_WC_Stock::instance();
        $this->vendor_notifications = WPNotif_Vendor_Notifications::instance();
    }

    /**
     * Hook into actions and filters.
     */
    private function init_hooks()
    {

        add_action('wp_loaded', array($this, 'woocommerce_loaded'));

        add_action('wp_ajax_wpnotif_test_api', array($this, 'wpnotif_test_api'));
        add_action('wp_ajax_wpnotif_send_quick_sms', array($this, 'wpnotif_send_quick_sms_ajax'));

        add_action('wp_ajax_wpnotif_update_message', array($this, 'wpnotif_update_message'));


        add_action('woocommerce_review_order_before_submit', array($this, 'user_consent_checkout'));
        add_action('woocommerce_edit_account_form', array($this, 'user_consent_checkout'));

        add_action('woocommerce_checkout_update_order_meta', array($this, 'update_user_consent_checkout'));
        add_action('woocommerce_save_account_details', array($this, 'update_user_consent_user'));

        add_action('show_user_profile', array($this, 'user_profile_consent'), 105);
        add_action('edit_user_profile', array($this, 'user_profile_consent'), 105);
        add_action('personal_options_update', array($this, 'update_user_consent_admin'));
        add_action('edit_user_profile_update', array($this, 'update_user_consent_admin'));


        add_action('init', array($this, 'init'));

        add_action('admin_init', array($this, 'admin_init'));

        add_action('wpnotif_chck', array($this, 'wpnotif_chck'));

        add_action('init', array($this, 'wpnotif_load_gateways'), 5);

    }

    public function admin_init()
    {
        if (isset($_REQUEST['install_additional'])) {
            if (wp_verify_nonce($_REQUEST['nonce'], 'install_additional')) {
                if (!current_user_can('manage_options')) {
                    return;
                }
                wpnotif_install_addons('additional-gateways/additional-gateways.php', 'additional-gateways');
            }
        }
    }

    public static function instance()
    {
        if (is_null(self::$_instance)) {
            self::$_instance = new self();
        }

        return self::$_instance;
    }

    public function wpnotif_chck()
    {
    	return;
        if (!function_exists('get_plugin_data')) {
            /** @noinspection PhpIncludeInspection */
            require_once(ABSPATH . '/wp-admin/includes/plugin.php');
        }

        $code = get_option('wpnotif_purchasecode');


        $plugin_version = WPNotif::get_version();

        $type = 'wpnotif_license_type';

        $params = array(
            'json' => 1,
            'code' => $code,
            'request_site' => wpn_network_home_url(),
            'slug' => 'wpnotif',
            $type => get_option('wpnotif_license_type', 1),
            'version' => $plugin_version,
            'schedule' => 1
        );

        $u = "https://bridge.unitedover.com/updates/verify.php";
        $c = curl_init();
        curl_setopt($c, CURLOPT_URL, $u);
        curl_setopt($c, CURLOPT_POST, true);
        curl_setopt($c, CURLOPT_POSTFIELDS, $params);
        curl_setopt($c, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($c, CURLOPT_SSL_VERIFYPEER, false);
        $result = curl_exec($c);

        $http_status = curl_getinfo($c, CURLINFO_HTTP_CODE);

        $ds = 'wpnotif_dsb';

        if (!curl_errno($c)) {


            $pcf = 'wpnotif_purchasefail';

            if ($http_status == 200) {

                $response = json_decode($result);

                if (empty($response) || !isset($response->code)) {
                    return;
                }

                $result = $response->code;

                if ($result == -99) {
                    update_option($ds, 1);
                    delete_option('wpnotif_purchasecode');
                } else if ($result != 1) {
                    $check = get_option($pcf, 2);
                    if ($check == 2) {
                        delete_option('wpnotif_purchasecode');
                        delete_option($pcf);
                        delete_option($type);
                    } else {
                        update_option($pcf, 2);
                    }


                } else if ($result == 1) {
                    delete_option($pcf);
                    if (isset($response->type)) {
                        if ($response->type != -1) {
                            update_option($type, $response->type);
                        }
                    }
                }
            }
        }


        curl_close($c);


        WPNotif_NewsLetter_Handler::wpn_background_process();
    }

    public function wpnotif_load_gateways()
    {
        require('gateways.php');
    }

    public function update_user_consent_checkout($order_id)
    {
        $this->update_user_consent($order_id, 0);
    }

    public function update_user_consent($order_id, $user_id)
    {
        $date = sanitize_text_field(date("Y-m-d H:i:s"));
        $enable_whatsapp = -1;
        $enable_sms = -1;


        if (isset($_POST['recieve_notifications'])) {
            $enable_whatsapp = 1;
            $enable_sms = 1;
        } else {
            if (isset($_POST['sms_notifications'])) {
                $enable_sms = 1;
            }
            if (isset($_POST['whatsapp_notifications'])) {
                $enable_whatsapp = 1;
            }

        }
        if (is_user_logged_in()) {
            if ($user_id == 0) {
                $user_id = get_current_user_id();
            }
            update_user_meta($user_id, 'whatsapp_notifications', $enable_whatsapp);
            update_user_meta($user_id, 'sms_notifications', $enable_sms);
            update_user_meta($user_id, 'sms_notifications_time', $date);
            update_user_meta($user_id, 'whatsapp_notifications_time', $date);

        }
        if ($order_id != 0) {

            add_post_meta($order_id, 'whatsapp_notifications', $enable_whatsapp);
            add_post_meta($order_id, 'sms_notifications', $enable_sms);
            add_post_meta($order_id, 'sms_notifications_time', $date);
            add_post_meta($order_id, 'whatsapp_notifications_time', $date);
        }
    }

    public function update_user_consent_user($user_id)
    {
        $this->update_user_consent(0, $user_id);
    }

    public function update_user_consent_admin($user_id)
    {
        if (!current_user_can('edit_user', $user_id)) {
            return false;
        }
        $this->update_user_consent(0, $user_id);
    }

    public function user_consent_checkout()
    {
        $user_id = 0;
        if (is_user_logged_in()) {
            $user_id = get_current_user_id();
        }
        $this->user_consent_ui($user_id, false);
    }

    public function is_checkout()
    {
        if (class_exists('WooCommerce')) {
            return is_checkout();
        }
        return false;
    }

    public function user_consent_ui($user_id, $show_heading)
    {


        $user_consent_values = get_option('wpnotif_user_consent', array());
        if (empty($user_consent_values)) {
            return;
        }


        $notify_user = $user_consent_values['notify_user'];
        $notify_user_whatsapp = $user_consent_values['whatsapp_message'];
        $notify_user_combine = $user_consent_values['combine_both'];
        if (!$this->isEnable($notify_user) && !$this->isEnable($notify_user_whatsapp)) {
            return;
        }

        $default_checked = false;

        if ($this->isEnable($user_consent_values['tick_by_default'])) {
            $default_checked = true;
        }

        $whatsapp_notifications = '';
        $sms_notifications = '';
        $combine_notifications = '';


        if (is_user_logged_in()) {


            $whatsapp_notifications_consent = get_user_meta($user_id, 'whatsapp_notifications', true);

            $sms_notifications_consent = get_user_meta($user_id, 'sms_notifications', true);

            if ($whatsapp_notifications_consent == 1) {
                $whatsapp_notifications = 'checked';
            } else if ($default_checked && $whatsapp_notifications_consent != -1 && !is_admin() || ($default_checked && $this->is_checkout())) {
                $whatsapp_notifications = 'checked';
            }

            if ($sms_notifications_consent == 1) {
                $sms_notifications = 'checked';
            } else if ($default_checked && $sms_notifications != -1 && !is_admin() || ($default_checked && $this->is_checkout())) {
                $sms_notifications = 'checked';
            }


            if (!empty($sms_notifications) && !empty($whatsapp_notifications) || ($default_checked && $this->is_checkout())) {
                $combine_notifications = 'checked';
            }


        } else if ($default_checked && $this->is_checkout()) {
            $combine_notifications = 'checked';
            $sms_notifications = 'checked';
            $whatsapp_notifications = 'checked';
        }

        if ($show_heading) {
            echo '<h3>' . esc_attr__('WPNotif User Consent', 'wpnotif') . '</h3>';
        }
        if ($this->isEnable($notify_user_combine)) {
            ?>
            <div class="form-row form-row-wide" style="padding: 3px;">
                <label class="woocommerce-form__label woocommerce-form__label-for-checkbox checkbox">
                    <input class="woocommerce-form__input woocommerce-form__input-checkbox input-checkbox"
                           type="checkbox" name="recieve_notifications"
                           value="1" <?php echo $combine_notifications; ?>/>
                    <?php esc_attr_e($notify_user_combine['msg'], 'wpnotif'); ?>
                </label>
            </div>
            <?php
        } else {
            if ($this->isEnable($notify_user)) {
                ?>
                <div class="form-row form-row-wide" style="padding: 3px;">

                    <label class="woocommerce-form__label woocommerce-form__label-for-checkbox checkbox">
                        <input class="woocommerce-form__input woocommerce-form__input-checkbox input-checkbox"
                               type="checkbox" name="sms_notifications" value="1" <?php echo $sms_notifications; ?>/>
                        <?php esc_attr_e($notify_user['msg'], 'wpnotif'); ?>
                    </label>
                </div>
                <?php
            }
            if ($this->isEnable($notify_user_whatsapp)) {
                ?>
                <div class="form-row form-row-wide" style="padding: 3px;">

                    <label class="woocommerce-form__label woocommerce-form__label-for-checkbox checkbox">
                        <input class="woocommerce-form__input woocommerce-form__input-checkbox input-checkbox"
                               type="checkbox" name="whatsapp_notifications"
                               value="1" <?php echo $whatsapp_notifications; ?>/>
                        <?php esc_attr_e($notify_user_whatsapp['msg'], 'wpnotif'); ?>
                    </label>
                </div>
                <?php
            }

        }

    }

    public static function isEnable($value)
    {

        if ($value['enable'] == 'on') {
            return true;
        }

        return false;
    }

    public function user_profile_consent($user)
    {
        $this->user_consent_ui($user->ID, true);
    }

    public function wpnotif_update_message()
    {
        $nonce = $_REQUEST['wpnotif_nonce'];
        if (!wp_verify_nonce($nonce, 'wpnotif')) {
            return;
        }
        if (!WPNotif::can_notify_users()) {
            return;
        }
        global $wpdb;
        $tb = $wpdb->prefix . 'wpnotif_whatsapp_messages';

        $id = sanitize_text_field($_POST['id']);

        if ($id == -1) {
            $wpdb->query("TRUNCATE TABLE $tb");


        } else if ($id != 0) {
            $wpdb->delete($tb, array(
                'id' => $id,
            ), array(
                    '%d'
                )
            );
        }
        WPNotif::add_wp_footer();
        die();

    }

    public function init()
    {
        WPNotif_Handler::wpnotif_check();
    }

    public static function wpnotif_check()
    {
        if (!wp_next_scheduled('wpnotif_chck')) {
            wp_schedule_event(time(), 'daily', 'wpnotif_chck');
        }
    }

    public function woocommerce_loaded()
    {
        if (class_exists('WooCommerce')) {
            $statuses = wc_get_order_statuses();
            foreach ($statuses as $key => $status) {
                $key = str_replace('wc-', '', $key);
                add_action('woocommerce_order_status_' . $key, array($this, 'wc_order_status_change'), 20);
            }
            add_action('wpn_wc_notify_order', array($this, 'notify_order'), 20);

            add_action('woocommerce_new_order', array($this, 'wc_new_order'), 20, 2);
        }
    }

    public static function has_notified_order($order_id)
    {
        if (in_array($order_id, self::$notified_orders)) {
            return true;
        } else {
            self::$notified_orders[] = $order_id;
        }
        return false;
    }


    public function wc_new_order($order_id, $order)
    {
        if (is_admin()) {
            $this->order_status_change($order_id);
        }
        //$this->order_status_change($order_id);

        return $order_id;
    }

    public function wc_order_status_change($order_id)
    {
        $this->order_status_change($order_id);
    }

    public $notifiedOrders = array();

    public function order_status_change($order_id, $key = -1)
    {
        if (function_exists('is_admin') && is_admin()) {
            if (isset($_REQUEST['wpnotif_check_send_enabled'])) {
                if (!isset($_REQUEST['wpnotif_send_order_status']) && !isset($_POST['trigger_order_status'])) {
                    return -1;
                }
            }
        }
        if (in_array($order_id, $this->notifiedOrders)) {
            return -1;
        }
        try {
            $this->notifiedOrders[] = $order_id;
            $order = new WC_Order($order_id);
            return $this->notify_order($order, $key);
        } catch (Exception $e) {
        }
        return -10;
    }

    public function notify_order($order, $key = -1)
    {
        /*
         * to avoid duplicate messages for order and suborder
         */
        $parent_id = $order->get_parent_id();
        if (!empty($parent_id)) {
            $order = new WC_Order();

            $is_new = $order->get_meta('wpnotif_new_order_notified', true);
            if (empty($is_new)) {
                $order->add_meta_data('wpnotif_new_order_notified', 1);
                $order->save_meta_data();
                if (self::has_notified_order($parent_id)) {
                    return -10;
                }
            }
        }

        $order_id = $order->get_id();

        do_action('wpnotif_order_status_change', $order_id);

        return $this->process_message($order, $key);

    }


    public function process_message($order, $key = -1)
    {
        $notification_data = WPNotif::data_type('order', $order);
        $fail = false;
        if ($this->sendSMS) {
            $data = $this->notify_user(0, $notification_data, 'wc-' . $key, 1);
            if ($data == -10) {
                $fail = true;
            }
        }

        if ($this->isWhatsappEnabled() && $this->sendWhatsapp) {
            $data = $this->notify_user(0, $notification_data, 'wc-' . $key, 1001);
        }

        if ($data == -10 || !$data) {
            if ($fail) {
                return -10;
            }
        }

        return $data;
    }

    public function get_message_content($type, $data, $key, $values)
    {
        if (isset($data['data'])) {
            $data = $data['data'];
        }
        if (!is_object($data) && isset($data[$type])) {
            return $data[$type];
        } else if ($key != null && isset($values[$key]) && $values[$key]['enable'] == 'on') {
            $opt = $values[$key];
            $message = $opt['msg'];
            $label = '';
            if (!empty($opt['label'])) {
                $label = $opt['label'];
            }
            return array('message' => $message, 'label' => $label);
        }
        return '';
    }

    public function notify_user($user_id, $data, $key, $gateway)
    {
        $order = '';

        if ($data['type'] == 'order') {
            $order = $data['data'];
            if (is_object($order)) {
                $order_id = $order->get_id();
                if ($key == 'wc--1') {
                    $key = 'wc-' . $order->get_status();
                }
                $user_id = $order->get_customer_id();
            }
        }


        if ($this->notify_admin && $data['notify'] != '2') {
            $admin_values = get_option('wpnotif_admin_notifications');

            $msg_obj = $this->get_message_content('admin_message', $data, $key, $admin_values);
            if (!empty($msg_obj['message'])) {
                $msg = $msg_obj['message'];
            } else {
                $msg = $msg_obj;
            }

            $msg = apply_filters('wpnotif_admin_message', $msg, $data);
            $msg = apply_filters('wpnotif_admin_message_' . $data['type'], $msg, $data);

            if (!empty($msg)) {

                $admin_user_ids = $this->admin_user_ids();

                $admin_user_ids = apply_filters('wpnotif_admin_user_ids', $admin_user_ids, $data);
                $admin_user_ids = apply_filters('wpnotif_admin_user_ids_' . $data['type'], $admin_user_ids, $data);

                if (!empty($admin_user_ids)) {

                    foreach ($admin_user_ids as $admin_user_id) {
                        if (!empty($msg_obj['label'])) {
                            $label = $msg_obj['label'];
                            $locale = get_user_locale($admin_user_id);
                            $msg = apply_filters('wpml_translate_single_string', $msg, WPNotif::wpml_slug(), $label, $locale);
                        }

                        $msg = $this->parse_message($msg, $data, $user_id);

                        $countrycode = get_the_author_meta('digt_countrycode', $admin_user_id);
                        $mobile = get_the_author_meta('digits_phone_no', $admin_user_id);
                        if (empty($countrycode) || empty($mobile)) {

                            $phone = $this->get_customer_mobile($admin_user_id, $data, true);

                            if (is_array($phone)) {
                                $countrycode = $phone['countrycode'];
                                $mobile = $phone['mobile'];
                            }
                        }

                        if (!empty($countrycode) && !empty($mobile)) {
                            $this->send_notification($countrycode, $mobile, $msg, $gateway);
                        }
                    }

                }
            }
        }


        $msg = '';

        if ($data['notify'] == '1') {
            return;
        }


        if (is_object($order) && $data['type'] == 'order') {
            if ($this->get_user_consent($order, $gateway) != 1) {
                return;
            }
        }


        $options = array();
        $options['identifier'] = 'customer';
        $options['different_gateway_content'] = get_option('different_gateway_content');

        $options = apply_filters('wpnotif_notification_options_' . $data['type'], $options);

        if (isset($data['user_phone'])) {
            $user_mobile = WPNotif_Handler::parseMobile($data['user_phone']);
        } else {
            $user_mobile = $this->get_customer_mobile($user_id, $data);
        }

        if ($options['different_gateway_content'] == 'on') {
            $gateway_notifications = get_option('wpnotif_gateway_' . $options['identifier'] . '_notifications');
            if (!empty($gateway_notifications)) {


                $gateway_notifications = json_decode(stripslashes($gateway_notifications), true);

                if (is_array($user_mobile)) {
                    if ($gateway == 1) {
                        $gateway_to_use = $this->gatewaytoUse($user_mobile['countrycode']);
                    } else {
                        $gateway_to_use = $gateway;
                    }

                    if (isset($gateway_notifications['wpnotif_' . $gateway_to_use])) {
                        $customer_notification = $gateway_notifications['wpnotif_' . $gateway_to_use];

                        if (isset($customer_notification['key_' . $key])) {
                            $customer_notification = $customer_notification['key_' . $key];
                            if ($customer_notification['enable'] == 'on') {
                                $msg = $customer_notification['msg'];
                            }
                        }
                    }
                }


            }

        } else {

            $customer_values = get_option('wpnotif_' . $options['identifier'] . '_notifications');

            $msg_obj = $this->get_message_content('user_message', $data, $key, $customer_values);

            if(!empty($msg_obj['message'])) {
                $msg = $msg_obj['message'];
            }else{
                $msg = $msg_obj;
            }

            if (!empty($msg_obj['label'])) {
                $label = $msg_obj['label'];
                if (!empty($user_id)) {
                    $locale = get_user_locale($user_id);
                } else {
                    $locale = get_locale();
                }
                $msg = apply_filters('wpml_translate_single_string', $msg, WPNotif::wpml_slug(), $label, $locale);
            }
        }

        if (is_array($user_mobile) && !empty($msg) && !empty($user_mobile)) {
            $msg = $this->parse_message($msg, $data, $user_id);

            if ($gateway == 1001) {
                $msg = str_replace('\n', '%0A', $msg);
            }
            if (is_object($order)) {
                if (!$this->order_note) {
                    $this->order_note = true;
                    $order->add_order_note($msg);
                    $order->save();
                }
            }
            $this->send_notification($user_mobile['countrycode'], $user_mobile['mobile'], $msg, $gateway);

            return array($user_mobile['countrycode'] . $user_mobile['mobile'] => $msg);

        } else {
            return -10;
        }


    }

    public function admin_user_ids()
    {
        $admins = get_option('wpnotf_admin_user_role', array());
        if (empty($admins)) {
            return array();
        }

        return self::get_admin_ids($admins);
    }

    public static function get_admin_ids($admins)
    {
        $notify_ids = array();

        $notify_user_prefix = 'notify_user_';
        foreach ($admins as $key => $admin) {
            if (strpos($admin, $notify_user_prefix) === 0) {
                $notify_ids[] = self::str_replace_once($notify_user_prefix, '', $admin);
                unset($admins[$key]);
            }
        }


        if (!empty($admins)) {
            $admin_users = get_users(array('fields' => array('ID'), 'role__in' => $admins));
            foreach ($admin_users as $admin_user) {
                $notify_ids[] = $admin_user->ID;
            }
        }

        return $notify_ids;
    }

    public function parse_message($message, $data, $user_id = 0)
    {

        $message = stripslashes($message);


        $site_name = get_bloginfo();

        $message = str_replace('{{sitename}}', $site_name, $message);


        if ($data['type'] == 'order' || (is_array($data['data']) && isset($data['data']['order']))) {


            if (is_array($data['data']) && isset($data['data']['order'])) {
                $order = $data['data']['order'];
            } else {
                $order = $data['data'];
            }

            if (is_object($order)) {

                $hidden_order_itemmeta = apply_filters('woocommerce_hidden_order_itemmeta',
                    array('_qty', '_tax_class', '_product_id', '_variation_id', '_line_subtotal', '_line_subtotal_tax', '_line_total', '_line_tax', 'method_id', 'cost', '_reduced_stock'));

                $product_variations = array();
                $product_variations_qty = array();

                $products = array();
                $product_sku = array();

                $product_details = array();
                $product_price_quantity = array();

                $product_links = array();
                $product_name_links = array();

                $currency_code = $order->get_currency();
                $currency_symbol = wpn_get_currency_symbol($currency_code);

                foreach ($order->get_items() as $item) {

                    $item_name = $item['name'];
                    $quantity = $item['qty'];
                    $item_total = $item->get_total_tax() + $item->get_total();

                    $product = $item->get_product();

                    $product_sku_value = '';
                    $product_link = '';
                    if(!empty($product)){
                        $product_sku_value = $product->get_sku();
                        $product_link = $product->get_permalink();
                    }

                    /*if (!empty($item->get_variation_id())) {
                        $variation_id = $item->get_variation_id();
                        $variation = new WC_Product_Variation($variation_id);
                        $attributes = $variation->get_attribute_summary();

                        $product_variations[] = $item_name . ' (' . $attributes . ')';
                        $product_variations_qty[] = $item_name . ' (' . $attributes . ') x ' . $item['qty'];
                    }*/

                    $item_meta = array();
                    if ($meta_data = $item->get_formatted_meta_data('')) {
                        foreach ($meta_data as $meta_id => $meta) {
                            if (in_array($meta->key, $hidden_order_itemmeta, true)) {
                                continue;
                            }
                            $item_meta[] = wp_kses_post($meta->display_key) . ': ' . wp_kses_post(force_balance_tags($meta->display_value));
                        }
                        $item_meta = implode(" | ", $item_meta);
                    }

                    if (empty($item_meta) && !empty($item->get_variation_id())) {
                        $variation_id = $item->get_variation_id();
                        $variation = new WC_Product_Variation($variation_id);
                        $attributes = $variation->get_attribute_summary();

                        $item_meta = $attributes;
                    }

                    if (!empty($item_meta)) {
                        $item_meta = ' (' . $item_meta . ')';
                    } else {
                        $item_meta = '';
                    }
                    $product_variations[] = $item_name . $item_meta;
                    $product_variations_qty[] = $item_name . $item_meta . ' x ' . $quantity;

                    $products[] = $item_name;
                    $product_details[] = $item_name . ' x ' . $quantity;

                    $product_sku[] = '(' . $item_name . ') ' . $product_sku_value;


                    $product_price_quantity_suffix = '';
                    if ($quantity > 1) {
                        $product_price_quantity_suffix = ' (x' . $quantity . ')';
                    }
                    $product_price_quantity[] = $item_name . $product_price_quantity_suffix . ' - ' . $currency_symbol . ' ' . $item_total;

                    $product_links[] = $product_link;
                    $product_name_links[] = $item_name . ' ' . $product_link;
                }

                $refund_reason = '';
                if (!empty($order->get_refunds())) {
                    $refund_reason = $order->get_refunds()[0]->get_reason();
                }
                $order_id = $order->get_id();

                $items_name_collapse = '';
                if (sizeof($products) > 1) {
                    $total_item = sizeof($products) - 1;
                    if ($total_item == 1) {
                        $collapse_count = sprintf(esc_html__('and %s item', 'wpnotif'), $total_item);
                    } else {
                        $collapse_count = sprintf(esc_html__('and %s items', 'wpnotif'), $total_item);
                    }
                    $items_name_collapse = $products[0] . ' ' . $collapse_count;
                } else if (!empty($products)) {
                    $items_name_collapse = $products[0];
                }

                $order_created_date = $order->get_date_created();
                if (!empty($order_created_date)) {
                    $order_created_date = $order_created_date->format('Y F j, g:i a');
                } else {
                    $order_created_date = '';
                }

                $order_tax_totals = 0;
                foreach ($order->get_tax_totals() as $code => $tax) {
                    $order_tax_totals += $tax->amount;
                }

                $placeholder_values = array(
                    '{{wc-order-notes}}' => $order->get_customer_note(),
                    '{{wc-order}}' => $order->get_order_number(),
                    '{{wc-order-id}}' => $order_id,
                    '{{wc-order-date}}' => $order_created_date,
                    '{{wc-order-status}}' => wc_get_order_status_name($order->get_status()),
                    '{{wc-payment-method}}' => $order->get_payment_method(),
                    '{{wc-transaction-id}}' => $order->get_transaction_id(),
                    '{{wc-shipping-method}}' => $order->get_shipping_method(),

                    '{{wc-product-link}}' => implode(", ", $product_links),
                    '{{wc-product-name-link}}' => implode(", ", $product_name_links),

                    '{{wc-product-link-br}}' => implode(", " . PHP_EOL, $product_links),
                    '{{wc-product-name-link-br}}' => implode(", " . PHP_EOL, $product_name_links),

                    '{{wc-product-names}}' => implode(", ", $products),
                    '{{wc-sku}}' => implode(", ", $product_sku),
                    '{{wc-order-items-price}}' => implode(", ", $product_price_quantity),
                    '{{wc-product-names-variable}}' => implode(", ", $product_variations),
                    '{{wc-order-items-variable}}' => implode(", ", $product_variations_qty),
                    '{{wc-order-items}}' => implode(", ", $product_details),
                    '{{wc-all-order-items}}' => implode(", ", $product_details),

                    '{{wc-product-names-br}}' => implode(", " . PHP_EOL, $products),
                    '{{wc-sku-br}}' => implode(", " . PHP_EOL, $product_sku),
                    '{{wc-order-items-price-br}}' => implode(", " . PHP_EOL, $product_price_quantity),
                    '{{wc-product-names-variable-br}}' => implode(", " . PHP_EOL, $product_variations),
                    '{{wc-order-items-variable-br}}' => implode(", " . PHP_EOL, $product_variations_qty),
                    '{{wc-order-items-br}}' => implode(", " . PHP_EOL, $product_details),
                    '{{wc-all-order-items-br}}' => implode(", " . PHP_EOL, $product_details),


                    '{{wc-product-name-count}}' => $items_name_collapse,
                    '{{wc-total-products}}' => count($order->get_items()),
                    '{{wc-total-items}}' => $order->get_item_count(),
                    '{{wc-order-amount}}' => $order->get_total(),
                    '{{wc-discount}}' => $order->get_discount_total(),
                    '{{wc-tax}}' => $order_tax_totals,
                    '{{wc-order-amount-ex-tax}}' => $order->get_subtotal(),
                    '{{wc-shipping-cost}}' => $order->get_shipping_total(),
                    '{{wc-refund-amount}}' => $order->get_total_refunded(),
                    '{{wc-refund-reason}}' => $refund_reason,
                    '{{wc-billing-first-name}}' => $order->get_billing_first_name(),
                    '{{wc-billing-last-name}}' => $order->get_billing_last_name(),
                    '{{wc-billing-company}}' => $order->get_billing_company(),
                    '{{wc-billing-address-line-1}}' => $order->get_billing_address_1(),
                    '{{wc-billing-address-line-2}}' => $order->get_billing_address_2(),
                    '{{wc-billing-city}}' => $order->get_billing_city(),
                    '{{wc-billing-postcode}}' => $order->get_billing_postcode(),
                    '{{wc-billing-state}}' => $order->get_billing_state(),
                    '{{wc-billing-country}}' => $order->get_billing_country(),
                    '{{wc-billing-email}}' => $order->get_billing_email(),
                    '{{wc-billing-phone}}' => $order->get_billing_phone(),
                    '{{wc-shipping-first-name}}' => $order->get_shipping_first_name(),
                    '{{wc-shipping-last-name}}' => $order->get_shipping_last_name(),
                    '{{wc-shipping-company}}' => $order->get_shipping_company(),
                    '{{wc-shipping-address-line-1}}' => $order->get_shipping_address_1(),
                    '{{wc-shipping-address-line-2}}' => $order->get_shipping_address_2(),
                    '{{wc-shipping-city}}' => $order->get_shipping_city(),
                    '{{wc-shipping-postcode}}' => $order->get_shipping_postcode(),
                    '{{wc-shipping-state}}' => $order->get_shipping_state(),
                    '{{wc-shipping-country}}' => $order->get_shipping_country(),
                );


                $message = strtr($message, $placeholder_values);

                $user_id = $order->get_customer_id();

            }


        }
        $message = $this->replace_placeholders($message, $data, $user_id);

        $message = strip_tags($message);

        return $message;
    }

    public function basic_message_parse($msg)
    {
        $placeholder_values = array(
            '{{siteurl}}' => home_url(),
            '{{wordpress-url}}' => site_url(),
            '{{tagline}}' => get_option('blogdescription'),
            '{{privacy-policy}}' => get_privacy_policy_url(),
        );


        return strtr($msg, $placeholder_values);
    }

    public function replace_placeholders($msg, $data, $user_id)
    {

        $order = null;
        if ($data['type'] == 'order') {
            $order = $data['data'];
            $msg = apply_filters('wpnotif_filter_message', $msg, $order);
        } else {
            $msg = apply_filters('wpnotif_filter_' . $data['type'] . '_message', $msg, $data['data']);
        }

        if (is_array($data['data']) && isset($data['data']['order'])) {
            $order = $data['data']['order'];
        }


        $placeholders = array();


        $msg = $this->basic_message_parse($msg);

        $user = get_userdata($user_id);
        preg_match_all('/{{([^}]*)}}/', $msg, $placeholders);

        if (is_array($placeholders)) {
            $update = true;
            if (isset($placeholders[1])) {
                foreach ($placeholders[1] as $placeholder) {
                    $value = '';

                    if ($user_id != 0 && !is_wp_error($user) && strpos($placeholder, 'user-') === 0) {

                        $meta_key = $this->str_replace_once('user-', '', $placeholder);
                        $value = get_user_meta($user_id, $meta_key, true);
                        if (!$value) {
                            $value = '';
                        }

                    } else if ($user_id != 0 && !is_wp_error($user) && strpos($placeholder, 'wp-') === 0) {

                        $meta_key = $this->str_replace_once('wp-', '', $placeholder);
                        $meta_key = $this->get_user_meta_key($meta_key);

                        if ($meta_key == 'username') {
                            $value = $user->user_login;
                        } else if ($meta_key == 'display-name') {
                            $value = $user->display_name;
                        } else if ($meta_key == 'email') {
                            $value = $user->user_email;
                        } else if ($meta_key == 'user-website') {
                            $value = $user->user_url;
                        } else {
                            $value = get_user_meta($user_id, $meta_key, true);
                        }
                        if (!$value) {
                            $value = '';
                        }

                    } else if ($order != null && (strpos($placeholder, 'order-') === 0 ||
                            strpos($placeholder, 'orderitem-') === 0 ||
                            strpos($placeholder, 'post-') === 0)) {

                        $type = 0;

                        if (strpos($placeholder, 'orderitem-') === 0) {
                            $meta_key = $this->str_replace_once('orderitem-', '', $placeholder);

                            $order_item_value = array();
                            foreach ($order->get_items() as $item) {
                                $meta_value = $item->get_meta($meta_key, true);
                                if (!empty($meta_value)) {
                                    $order_item_value[] = $meta_value;
                                }
                            }

                            $value = implode(", ", $order_item_value);
                        } else {

                            if (strpos($placeholder, 'order-') === 0) {
                                $type = 1;
                                $meta_key = $this->str_replace_once('order-', '', $placeholder);
                            } else if (strpos($placeholder, 'post-') === 0) {
                                $type = 2;
                                $meta_key = $this->str_replace_once('post-', '', $placeholder);
                            }

                            $is_array = false;
                            if (strpos($meta_key, ':') !== false) {
                                $meta_key_array = explode(":", $meta_key, 2);
                                $meta_key = $meta_key_array[0];
                                $array_key = $meta_key_array[1];
                                $is_array = true;
                            }
                            if ($type == 1) {
                                $value = $order->get_meta($meta_key);
                            } else if ($type == 2) {
                                $value = get_post_meta($order->get_id(), $meta_key, !$is_array);

                            }

                            if (empty($value)) {
                                $value = '';
                            } else if ($is_array) {
                                if ($type == 2) {
                                    $value = $value[0];
                                }

                                $value = $value[0][$array_key];
                            }
                        }

                    } else {
                        $update = false;
                    }

                    if ($data['type'] == 'order') {
                        $value = apply_filters('wpnotif_placeholder_args', $value, $placeholder, $msg, $order);
                        $value = apply_filters('wpnotif_additional_placeholder_args_order', $value, $placeholder, $msg, $order);
                    } else {
                        $value = apply_filters('wpnotif_' . $data['type'] . '_placeholder_args', $value, $placeholder, $msg, $data['data']);
                        $value = apply_filters('wpnotif_additional_placeholder_args', $value, $placeholder, $msg, $data['data']);
                    }

                    if ($update || !empty($value)) {
                        $msg = str_replace('{{' . $placeholder . '}}', $value, $msg);
                    }

                }
            }
        }

        return $msg;
    }

    public function get_user_meta_key($placeholder)
    {
        $keys = array(
            'first-name' => 'first_name',
            'last-name' => 'last_name',
            'user-bio' => 'description',
        );

        if (array_key_exists($placeholder, $keys)) {
            return $keys[$placeholder];
        }

        return $placeholder;
    }

    public static function str_replace_once($str_pattern, $str_replacement, $string)
    {
        if (strpos($string, $str_pattern) !== false) {
            return substr_replace($string, $str_replacement, strpos($string, $str_pattern), strlen($str_pattern));
        }

        return $string;
    }

    public static function get_user_phone($user_id, $newsletter = false)
    {
        $phone_meta_keys = array('wpnotif_phone', 'digits_phone');
        $phone_meta_keys = apply_filters('user_phone_meta_keys', $phone_meta_keys);
        $user_phone = false;
        foreach ($phone_meta_keys as $phone_key) {
            $phone_value = get_user_meta($user_id, $phone_key, true);
            if (!empty($phone_value)) {
                $user_phone = $phone_value;
                break;
            }
        }

        if (empty($user_phone) && $newsletter) {
            $mob = self::get_customer_mobile($user_id, WPNotif::data_type('user_mobile', array()));
            if (empty($mob)) {
                return '';
            }
            return $mob['countrycode'] . $mob['mobile'];
        }

        return $user_phone;
    }


    public static function get_admin_mobile($user_id)
    {
        self::get_user_phone($user_id);
    }

    public static function get_customer_mobile($user_id, $data, $is_admin = false)
    {

        if ($user_id == 0 && $data['type'] != 'order' && $data['type'] != 'cart') {
            return null;
        }

        $order = '';
        if ($data['type'] == 'order') {
            $order = $data['data'];
        }

        if (($data['type'] != 'cart' && $data['type'] != 'order' && $user_id != 0) || $is_admin) {
            $digits_mobile = self::get_digits_mobile($user_id);
            if ($digits_mobile != null && !empty($digits_mobile)) {
                return $digits_mobile;
            }
            $billing_country = get_user_meta($user_id, 'billing_country', true);
            $billing_phone = ltrim(get_user_meta($user_id, 'billing_phone', true), '0');

        } else {
            if($data['type'] == 'cart'){
                $billing_country = $data['customer']['country'];
                $billing_phone = $data['customer']['phone'];
            }else {
                $billing_country = $order->get_billing_country();
                $billing_phone = ltrim($order->get_billing_phone(), '0');
            }
        }

        $billing_country_code = WPNotif_Handler::getCountryCode($billing_country, true);
        $parse_mobile = WPNotif_Handler::parseMobile($billing_country_code . $billing_phone);

        if (!$parse_mobile) {
            $parse_mobile = WPNotif_Handler::parseMobile($billing_phone);
        }

        if (!$parse_mobile) {

            return self::parseMobile(self::get_user_phone($user_id));

        }

        return $parse_mobile;


    }

    public static function get_digits_mobile($user_id)
    {
        $dig_countrycode = get_the_author_meta('digt_countrycode', $user_id);
        $dig_mobile = get_the_author_meta('digits_phone_no', $user_id);
        if (empty($dig_countrycode) || empty($dig_mobile)) {
            /*
             * WCF compatibility
             */
            $vendor_data = get_user_meta($user_id, 'wcfmmp_profile_settings', true);
            $user_phone = isset($vendor_data['phone']) ? esc_attr($vendor_data['phone']) : false;
            if (!empty($user_phone)) {
                return self::parseMobile($user_phone);
            }
            return null;
        } else {
            return array('countrycode' => $dig_countrycode, 'mobile' => $dig_mobile);
        }
    }

    /*
     * $returnDefault if true it will return default if country code is not found
     * */
    public static function getCountryCode($country, $returnDefault)
    {
        $country_codes = array(
            "AF" => "93",
            "AL" => "355",
            "DZ" => "213",
            "AS" => "1",
            "AD" => "376",
            "AO" => "244",
            "AI" => "1",
            "AQ" => "672",
            "AG" => "1",
            "AR" => "54",
            "AM" => "374",
            "AW" => "297",
            "AU" => "61",
            "AT" => "43",
            "AZ" => "994",
            "BS" => "1",
            "BH" => "973",
            "BD" => "880",
            "BB" => "1",
            "BY" => "375",
            "BE" => "32",
            "BZ" => "501",
            "BJ" => "229",
            "BM" => "1",
            "BT" => "975",
            "BO" => "591",
            "BA" => "387",
            "BW" => "267",
            "BR" => "55",
            "IO" => "246",
            "VG" => "1",
            "BN" => "673",
            "BG" => "359",
            "BF" => "226",
            "BI" => "257",
            "KH" => "855",
            "CM" => "237",
            "CA" => "1",
            "CV" => "238",
            "KY" => "1",
            "CF" => "236",
            "TD" => "235",
            "CL" => "56",
            "CN" => "86",
            "CX" => "61",
            "CC" => "61",
            "CO" => "57",
            "KM" => "269",
            "CK" => "682",
            "CR" => "506",
            "HR" => "385",
            "CU" => "53",
            "CW" => "599",
            "CY" => "357",
            "CZ" => "420",
            "CD" => "243",
            "DK" => "45",
            "DJ" => "253",
            "DM" => "1",
            "DO" => "1",
            "TL" => "670",
            "EC" => "593",
            "EG" => "20",
            "SV" => "503",
            "GQ" => "240",
            "ER" => "291",
            "EE" => "372",
            "ET" => "251",
            "FK" => "500",
            "FO" => "298",
            "FJ" => "679",
            "FI" => "358",
            "FR" => "33",
            "PF" => "689",
            "GA" => "241",
            "GM" => "220",
            "GE" => "995",
            "DE" => "49",
            "GH" => "233",
            "GI" => "350",
            "GR" => "30",
            "GL" => "299",
            "GD" => "1",
            "GU" => "1",
            "GT" => "502",
            "GG" => "44",
            "GN" => "224",
            "GW" => "245",
            "GY" => "592",
            "HT" => "509",
            "HN" => "504",
            "HK" => "852",
            "HU" => "36",
            "IS" => "354",
            "IN" => "91",
            "ID" => "62",
            "IR" => "98",
            "IQ" => "964",
            "IE" => "353",
            "IM" => "44",
            "IL" => "972",
            "IT" => "39",
            "CI" => "225",
            "JM" => "1",
            "JP" => "81",
            "JE" => "44",
            "JO" => "962",
            "KZ" => "7",
            "KE" => "254",
            "KI" => "686",
            "XK" => "383",
            "KW" => "965",
            "KG" => "996",
            "LA" => "856",
            "LV" => "371",
            "LB" => "961",
            "LS" => "266",
            "LR" => "231",
            "LY" => "218",
            "LI" => "423",
            "LT" => "370",
            "LU" => "352",
            "MO" => "853",
            "MK" => "389",
            "MG" => "261",
            "MW" => "265",
            "MY" => "60",
            "MV" => "960",
            "ML" => "223",
            "MT" => "356",
            "MH" => "692",
            "MR" => "222",
            "MU" => "230",
            "YT" => "262",
            "MX" => "52",
            "FM" => "691",
            "MD" => "373",
            "MC" => "377",
            "MN" => "976",
            "ME" => "382",
            "MS" => "1",
            "MA" => "212",
            "MZ" => "258",
            "MM" => "95",
            "NA" => "264",
            "NR" => "674",
            "NP" => "977",
            "NL" => "31",
            "AN" => "599",
            "NC" => "687",
            "NZ" => "64",
            "NI" => "505",
            "NE" => "227",
            "NG" => "234",
            "NU" => "683",
            "KP" => "850",
            "MP" => "1",
            "NO" => "47",
            "OM" => "968",
            "PK" => "92",
            "PW" => "680",
            "PS" => "970",
            "PA" => "507",
            "PG" => "675",
            "PY" => "595",
            "PE" => "51",
            "PH" => "63",
            "PN" => "64",
            "PL" => "48",
            "PT" => "351",
            "PR" => "1",
            "QA" => "974",
            "CG" => "242",
            "RE" => "262",
            "RO" => "40",
            "RU" => "7",
            "RW" => "250",
            "BL" => "590",
            "SH" => "290",
            "KN" => "1",
            "LC" => "1",
            "MF" => "590",
            "PM" => "508",
            "VC" => "1",
            "WS" => "685",
            "SM" => "378",
            "ST" => "239",
            "SA" => "966",
            "SN" => "221",
            "RS" => "381",
            "SC" => "248",
            "SL" => "232",
            "SG" => "65",
            "SX" => "1",
            "SK" => "421",
            "SI" => "386",
            "SB" => "677",
            "SO" => "252",
            "ZA" => "27",
            "KR" => "82",
            "SS" => "211",
            "ES" => "34",
            "LK" => "94",
            "SD" => "249",
            "SR" => "597",
            "SJ" => "47",
            "SZ" => "268",
            "SE" => "46",
            "CH" => "41",
            "SY" => "963",
            "TW" => "886",
            "TJ" => "992",
            "TZ" => "255",
            "TH" => "66",
            "TG" => "228",
            "TK" => "690",
            "TO" => "676",
            "TT" => "1",
            "TN" => "216",
            "TR" => "90",
            "TM" => "993",
            "TC" => "1",
            "TV" => "688",
            "VI" => "1",
            "UG" => "256",
            "UA" => "380",
            "AE" => "971",
            "GB" => "44",
            "US" => "1",
            "UY" => "598",
            "UZ" => "998",
            "VU" => "678",
            "VA" => "379",
            "VE" => "58",
            "VN" => "84",
            "WF" => "681",
            "EH" => "212",
            "YE" => "967",
            "ZM" => "260",
            "ZW" => "263"
        );

        if (isset($country_codes[$country]) && !empty($country)) {
            return $country_codes[$country];
        } else {
            if ($returnDefault) {
                $settings = WPNotif::plugin_settings(true);
                $default_code = $settings['default_countrycode'];
                if (!empty($default_code) && $default_code != -1) {
                    return $default_code;
                }
            }
            return '';
        }
    }

    public static function parseMobile($mobile, $international_formatting = false)
    {
        if (strpos($mobile, '+') !== 0) {
            $mobile = '+' . $mobile;
        }

        if (strpos($mobile, "+242") === 0 || strpos($mobile, "+225") === 0) {
            $check_zero = substr($mobile, 4, 1);
            if ($check_zero != '0') {
                $mobile = substr_replace($mobile, "0", 4, 0);
            }
        }

        $phoneUtil = PhoneNumberUtil::getInstance();
        try {
            $numberProto = $phoneUtil->parse($mobile);
            $isValid = $phoneUtil->isValidNumber($numberProto);
            if ($isValid) {
                $ccode = $numberProto->getCountryCode();
                $mob = $numberProto->getNationalNumber();

                if (strpos($ccode, '+') !== 0) {
                    $ccode = '+' . $ccode;
                }
                if ($international_formatting) {
                    return $phoneUtil->format($numberProto, PhoneNumberFormat::INTERNATIONAL);
                }

                return array('countrycode' => $ccode, 'mobile' => $mob);
            }

        } catch (NumberParseException $e) {
            return false;
        }

        return false;
    }

    public function send_notification($countrycode, $mobile, $message, $gateway, $details = array())
    {
        $message = trim($message);

        if (empty($message)) {
            return;
        }
        if ($gateway == 1) {
            $gateway = $this->gatewaytoUse($countrycode);
        }
        $status = $this->send_message($details, $gateway, $countrycode, $mobile, $message);

        return $status;
    }

    public function gatewaytoUse($countrycode)
    {


        $wpnotif_gateways = get_option('wpnotif_gateways', -1);
        if ($wpnotif_gateways == -1 || empty($wpnotif_gateways)) {
            return -1;
        } else {
            $countrycode = str_replace("+", "", $countrycode);
            $gateways = json_decode(stripslashes($wpnotif_gateways), true);
            $gatewayToUse = 0;
            foreach ($gateways as $gc => $values) {
                $gatewayCode = $values['gateway'];
                $countries = $values['countries'];
                if ($gatewayCode > 1000) {
                    continue;
                }
                if ($values['enable'] != 'on') {
                    continue;
                }

                if ($countries == 'all' && $gatewayToUse == 0) {
                    $gatewayToUse = $gatewayCode;
                } else if ($countries != 'all' && !empty($countries)) {
                    $countriesArray = explode(",", $values['ccodes']);
                    if (in_array($countrycode, $countriesArray)) {
                        $gatewayToUse = $gatewayCode;
                        break;
                    }
                }
            }

            return $gatewayToUse;

        }


    }

    public function send_message($details, $gateway, $countrycode, $mobile, $messagetemplate, $testCall = false)
    {
        if ($gateway == 0) {
            return;
        }

        if (!$testCall) {
            $code = get_option('wpnotif_purchasecode');
            if (empty($code)) {
                return false;
            }
        }

        return WPNotif_SMS_handler::send_sms($details, $gateway, $countrycode, $mobile, $messagetemplate, $testCall, $this->update_web_db);
    }

    public static function get_user_consent($order, $gateway = -1)
    {
        $user_consent_values = get_option('wpnotif_user_consent', array());
        $whatsapp_notifications = 0;
        $sms_notifications = 0;
        $check_consent = 0;
        $sms_consent = 1;
        $whatsapp_consent = 1;

        if (empty($user_consent_values)) {
            $whatsapp_notifications = 1;
            $sms_notifications = 1;
        } else {
            $notify_user = $user_consent_values['notify_user'];
            $notify_user_whatsapp = $user_consent_values['whatsapp_message'];


            if (!WPNotif_Handler::isEnable($notify_user) && !WPNotif_Handler::isEnable($notify_user_whatsapp)) {
                $check_consent = 0;
                $sms_notifications = 1;
                $whatsapp_notifications = 1;
            } else {

                $check_consent = 1;
                $customer_id = 0;
                if (is_object($order)) {
                    $customer_id = $order->get_customer_id();
                }
                if (!$customer_id || $customer_id == 0) {
                    $order_id = $order->get_id();

                    if (get_post_meta($order_id, 'whatsapp_notifications', true) == 1) {
                        $whatsapp_notifications = 1;
                    }
                    if (get_post_meta($order_id, 'sms_notifications', true) == 1) {
                        $sms_notifications = 1;
                    }
                } else {
                    if (get_user_meta($customer_id, 'whatsapp_notifications', true) == 1) {
                        $whatsapp_notifications = 1;
                    }
                    if (get_user_meta($customer_id, 'sms_notifications', true) == 1) {
                        $sms_notifications = 1;
                    }
                }

                if (!WPNotif_Handler::isEnable($notify_user)) {
                    $sms_notifications = 1;
                    $sms_consent = -1;
                }
                if (!WPNotif_Handler::isEnable($notify_user_whatsapp)) {
                    $whatsapp_notifications = 1;
                    $whatsapp_consent = -1;
                }
            }

        }
        if ($gateway == -1) {
            return array(
                'check_consent' => $check_consent,
                'sms_notifications' => $sms_notifications,
                'sms_consent' => $sms_consent,
                'whatsapp_consent' => $whatsapp_consent,
                'whatsapp_notifications' => $whatsapp_notifications,
            );
        } else if ($gateway == 1001) {
            return $whatsapp_notifications;
        } else {
            return $sms_notifications;
        }

    }

    public static function isAppEnabled()
    {
        $wpnotif_gateways = get_option('wpnotif_gateways', -1);
        if ($wpnotif_gateways == -1 || empty($wpnotif_gateways)) {
            return -1;
        } else {
            $gateways = json_decode(stripslashes($wpnotif_gateways), true);
            $whatsapp_gateway = $gateways['gc_901'];
            if ($whatsapp_gateway['enable'] == 'on') {
                return true;
            }
        }

        return false;
    }

    public static function isWhatsappEnabled()
    {
        return self::isGatewayEnabled(1001);
    }

    public static function isGatewayEnabled($gateway)
    {
        $wpnotif_gateways = get_option('wpnotif_gateways', -1);
        if ($wpnotif_gateways == -1 || empty($wpnotif_gateways)) {
            return false;
        } else {
            $gateways = json_decode(stripslashes($wpnotif_gateways), true);
            if (isset($gateways['gc_' . $gateway])) {
                $gateway = $gateways['gc_' . $gateway];
                if ($gateway['enable'] == 'on') {
                    return true;
                }
            }
        }

        return false;
    }

    public function wpnotif_send_quick_sms_ajax()
    {
        if (!current_user_can('manage_options') && !WPNotif::can_edit_orders()) {
            echo '0';
            die();
        }

        $nonce = $_REQUEST['wpnotif_nonce'];
        if (!wp_verify_nonce($nonce, 'wpnotif')) {
            wp_send_json_error(array('msg' => esc_attr__('Error', 'wpnotif')));

            die();
        }
        $message = $_POST['quick_message'];
        $whatsapp_message = $_POST['whatsapp_message'];

        if (!empty($_POST['post_notification'])) {
            WPNotif_Post_Notifications::instance()->ajax_post_notification();
            die();
        }

        $mobiles = $_POST['mobile'];
        if (!is_array($mobiles)) {
            $mobiles = array($mobiles);
        }
        if (empty($mobiles)) {
            wp_send_json_error(array('msg' => esc_attr__('Invalid Mobile Number', 'wpnotif')));
            die();
        }


        if (empty($message) && empty($whatsapp_message) && $_POST['trigger_order_status'] != 1) {
            wp_send_json_error(array('msg' => esc_attr__('Please enter a valid message!', 'wpnotif')));
            die();
        }


        $isWhatsappWebEnabled = false;
        $isWhatsappEnabled = false;

        $smsEnabled = false;
        if (isset($_REQUEST['route'])) {
            $route = $_REQUEST['route'];
            if (in_array($route, array('both', 'whatsapp'))) {
                $isWhatsappWebEnabled = $this->isWhatsappWebEnabled();
                $isWhatsappEnabled = $this->isWhatsappEnabled();
            }
            if (in_array($route, array('both', 'sms'))) {
                $smsEnabled = true;
            }
        }

        $this->sendSMS = $smsEnabled;
        $this->sendWhatsapp = $isWhatsappEnabled;

        if (!empty($message) || !empty($whatsapp_message)) {
            if (isset($_POST['post_id']) && $_POST['post_id'] != 0) {
                try {
                    $order_id = $_POST['post_id'];
                    $order = new WC_Order($order_id);
                    $notification_data = WPNotif::data_type('order', $order);

                    if (!empty($message)) {
                        $message = $this->parse_message($message, $notification_data, 0);
                    }

                    if (!empty($whatsapp_message)) {
                        $whatsapp_message = $this->parse_message($whatsapp_message, $notification_data, 0);
                    }
                } catch (Exception $e) {
                    $order = 0;
                }
            } else {
                $order = 0;
            }

        }

        $phoneUtil = PhoneNumberUtil::getInstance();
        $message_send = false;
        $phone_valid = false;
        $data = array();

        $mobile_count = count($mobiles);
        if ($mobile_count == 1 || $_POST['trigger_order_status'] == 1) {
            $this->update_web_db = false;
        }

        foreach ($mobiles as $mobile) {
            $mobile = trim($mobile);
            try {
                if (strpos($mobile, '+') !== 0) {
                    $mobile = '+' . $mobile;
                }

                $numberProto = $phoneUtil->parse($mobile);

                $isValid = $phoneUtil->isValidNumber($numberProto);
                if ($isValid) {
                    $gateway = $this->gatewaytoUse($numberProto->getCountryCode());
                    $phone_valid = true;
                    if ($_POST['trigger_order_status'] == 1) {
                        $this->notify_admin = false;
                        $order_status = self::str_replace_once("wc-", "", $_REQUEST['order_status']);
                        $data = $this->order_status_change(sanitize_text_field($_POST['post_id']), $order_status);
                        if ($data == -10) {
                            wp_send_json_error(array('msg' => esc_attr__('Error: Message template not found!', 'wpnotif')));
                        }

                        break;
                    } else {
                        if ($smsEnabled && !empty($message)) {
                            $status = $this->send_message(array(), $gateway, $numberProto->getCountryCode(), $numberProto->getNationalNumber(), $message);
                        }
                        if (!empty($whatsapp_message)) {
                            if ($isWhatsappEnabled) {
                                $this->send_whatsapp_message($numberProto->getCountryCode(), $numberProto->getNationalNumber(), $whatsapp_message);
                            }
                            if ($isWhatsappWebEnabled && $mobile_count == 1) {
                                $data[$numberProto->getCountryCode() . $numberProto->getNationalNumber()] = $whatsapp_message;
                            }
                        }
                    }

                }
            } catch (NumberParseException $e) {

            }
        }
        if (isset($order) && is_object($order)) {

            if (!$this->order_note) {
                $this->order_note = true;
                if ($smsEnabled && !empty($message)) {
                    $order->add_order_note($message);
                }
                if ($isWhatsappEnabled && !empty($whatsapp_message)) {
                    $order->add_order_note($whatsapp_message);
                }

                $order->save();
            }


        }
        if ($phone_valid) {
            wp_send_json_success(array('msg' => esc_attr__('Message Sent', 'wpnotif'), 'data' => $data));
            die();
        } else {
            wp_send_json_error(array('msg' => esc_attr__('Invalid Mobile Number', 'wpnotif')));
            die();
        }

    }

    public static function usingWhatsAppDesktop()
    {
        $whatsapp = get_option('wpnotif_whatsapp');
        return !empty($whatsapp['using_whatsapp_app_on_desktop']);
    }

    public static function isWhatsappWebEnabled()
    {
        if (WPNotif_Handler::isWhatsappEnabled()) {
            $whatsapp = get_option('wpnotif_whatsapp');
            $whatsapp_gateway = $whatsapp['whatsapp_gateway'];
            if ($whatsapp_gateway == 2) {
                return true;
            }
        }

        return false;
    }

    public function send_whatsapp_message($countrycode, $mobile, $messagetemplate, $testCall = false)
    {
        $messagetemplate = str_replace('\n', '%0A', $messagetemplate);

        $this->send_message(array(), 1001, $countrycode, $mobile, $messagetemplate, $testCall);
    }

    public function sanitize_mobile($mobile)
    {
        $pl = '';
        if (substr($mobile, 0, 1) == '+') {
            $pl = '+';
        }
        $mobile = $pl . preg_replace('/[\s+()-]+/', '', $mobile);

        return ltrim(sanitize_text_field($mobile), '0');
    }

    public function wpnotif_test_api()
    {

        if (!current_user_can('manage_options')) {
            echo '0';
            die();
        }

        $mobile = sanitize_text_field($_POST['digt_mobile']);
        $countrycode = sanitize_text_field($_POST['digt_countrycode']);
        if (empty($mobile) || !is_numeric($mobile) || empty($countrycode) || !is_numeric($countrycode)) {
            esc_attr_e('Invalid Mobile Number', 'wpnotif');
            die();
        }

        $gateway = sanitize_text_field($_POST['gateway']);

        if (!empty($_REQUEST['test_message'])) {
            $messagetemplate = $_REQUEST['test_message'];
        } else {
            $messagetemplate = 'Congrats, your API details are correct. - Bot WPNotif';
        }

        $result = $this->send_message(array(), $gateway, $countrycode, $mobile, $messagetemplate, true);
        if (!$result) {
            esc_attr_e('Error', 'wpnotif');
            die();
        }
        print_r($result);
        die();

    }

    public static function createwhatsapp_db()
    {
        global $wpdb;
        $tb = $wpdb->prefix . 'wpnotif_whatsapp_messages';
        if ($wpdb->get_var("SHOW TABLES LIKE '$tb'") != $tb) {
            $charset_collate = $wpdb->get_charset_collate();
            $sql = "CREATE TABLE $tb (
										id BIGINT UNSIGNED NOT NULL auto_increment,
		          						countrycode MEDIUMINT(8) NOT NULL,
		          						mobileno VARCHAR(20) NOT NULL,
		          						message TEXT NOT NULL,
		          						time datetime DEFAULT CURRENT_TIMESTAMP NOT NULL,
		          						PRIMARY KEY  (id)
	            						) $charset_collate;";

            require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
            dbDelta(array($sql));
        }

    }
}


if (!function_exists('UnitedOver_convertToUnicode')) {
//This function to convert messages to our special UNICODE, use it to convert message before send it through the API
    function UnitedOver_convertToUnicode($message)
    {
        $chrArray[0] = "،";
        $unicodeArray[0] = "060C";
        $chrArray[1] = "؛";
        $unicodeArray[1] = "061B";
        $chrArray[2] = "؟";
        $unicodeArray[2] = "061F";
        $chrArray[3] = "ء";
        $unicodeArray[3] = "0621";
        $chrArray[4] = "آ";
        $unicodeArray[4] = "0622";
        $chrArray[5] = "أ";
        $unicodeArray[5] = "0623";
        $chrArray[6] = "ؤ";
        $unicodeArray[6] = "0624";
        $chrArray[7] = "إ";
        $unicodeArray[7] = "0625";
        $chrArray[8] = "ئ";
        $unicodeArray[8] = "0626";
        $chrArray[9] = "ا";
        $unicodeArray[9] = "0627";
        $chrArray[10] = "ب";
        $unicodeArray[10] = "0628";
        $chrArray[11] = "ة";
        $unicodeArray[11] = "0629";
        $chrArray[12] = "ت";
        $unicodeArray[12] = "062A";
        $chrArray[13] = "ث";
        $unicodeArray[13] = "062B";
        $chrArray[14] = "ج";
        $unicodeArray[14] = "062C";
        $chrArray[15] = "ح";
        $unicodeArray[15] = "062D";
        $chrArray[16] = "خ";
        $unicodeArray[16] = "062E";
        $chrArray[17] = "د";
        $unicodeArray[17] = "062F";
        $chrArray[18] = "ذ";
        $unicodeArray[18] = "0630";
        $chrArray[19] = "ر";
        $unicodeArray[19] = "0631";
        $chrArray[20] = "ز";
        $unicodeArray[20] = "0632";
        $chrArray[21] = "س";
        $unicodeArray[21] = "0633";
        $chrArray[22] = "ش";
        $unicodeArray[22] = "0634";
        $chrArray[23] = "ص";
        $unicodeArray[23] = "0635";
        $chrArray[24] = "ض";
        $unicodeArray[24] = "0636";
        $chrArray[25] = "ط";
        $unicodeArray[25] = "0637";
        $chrArray[26] = "ظ";
        $unicodeArray[26] = "0638";
        $chrArray[27] = "ع";
        $unicodeArray[27] = "0639";
        $chrArray[28] = "غ";
        $unicodeArray[28] = "063A";
        $chrArray[29] = "ف";
        $unicodeArray[29] = "0641";
        $chrArray[30] = "ق";
        $unicodeArray[30] = "0642";
        $chrArray[31] = "ك";
        $unicodeArray[31] = "0643";
        $chrArray[32] = "ل";
        $unicodeArray[32] = "0644";
        $chrArray[33] = "م";
        $unicodeArray[33] = "0645";
        $chrArray[34] = "ن";
        $unicodeArray[34] = "0646";
        $chrArray[35] = "ه";
        $unicodeArray[35] = "0647";
        $chrArray[36] = "و";
        $unicodeArray[36] = "0648";
        $chrArray[37] = "ى";
        $unicodeArray[37] = "0649";
        $chrArray[38] = "ي";
        $unicodeArray[38] = "064A";
        $chrArray[39] = "ـ";
        $unicodeArray[39] = "0640";
        $chrArray[40] = "ً";
        $unicodeArray[40] = "064B";
        $chrArray[41] = "ٌ";
        $unicodeArray[41] = "064C";
        $chrArray[42] = "ٍ";
        $unicodeArray[42] = "064D";
        $chrArray[43] = "َ";
        $unicodeArray[43] = "064E";
        $chrArray[44] = "ُ";
        $unicodeArray[44] = "064F";
        $chrArray[45] = "ِ";
        $unicodeArray[45] = "0650";
        $chrArray[46] = "ّ";
        $unicodeArray[46] = "0651";
        $chrArray[47] = "ْ";
        $unicodeArray[47] = "0652";
        $chrArray[48] = "!";
        $unicodeArray[48] = "0021";
        $chrArray[49] = '"';
        $unicodeArray[49] = "0022";
        $chrArray[50] = "#";
        $unicodeArray[50] = "0023";
        $chrArray[51] = "$";
        $unicodeArray[51] = "0024";
        $chrArray[52] = "%";
        $unicodeArray[52] = "0025";
        $chrArray[53] = "&";
        $unicodeArray[53] = "0026";
        $chrArray[54] = "'";
        $unicodeArray[54] = "0027";
        $chrArray[55] = "(";
        $unicodeArray[55] = "0028";
        $chrArray[56] = ")";
        $unicodeArray[56] = "0029";
        $chrArray[57] = "*";
        $unicodeArray[57] = "002A";
        $chrArray[58] = "+";
        $unicodeArray[58] = "002B";
        $chrArray[59] = ",";
        $unicodeArray[59] = "002C";
        $chrArray[60] = "-";
        $unicodeArray[60] = "002D";
        $chrArray[61] = ".";
        $unicodeArray[61] = "002E";
        $chrArray[62] = "/";
        $unicodeArray[62] = "002F";
        $chrArray[63] = "0";
        $unicodeArray[63] = "0030";
        $chrArray[64] = "1";
        $unicodeArray[64] = "0031";
        $chrArray[65] = "2";
        $unicodeArray[65] = "0032";
        $chrArray[66] = "3";
        $unicodeArray[66] = "0033";
        $chrArray[67] = "4";
        $unicodeArray[67] = "0034";
        $chrArray[68] = "5";
        $unicodeArray[68] = "0035";
        $chrArray[69] = "6";
        $unicodeArray[69] = "0036";
        $chrArray[70] = "7";
        $unicodeArray[70] = "0037";
        $chrArray[71] = "8";
        $unicodeArray[71] = "0038";
        $chrArray[72] = "9";
        $unicodeArray[72] = "0039";
        $chrArray[73] = ":";
        $unicodeArray[73] = "003A";
        $chrArray[74] = ";";
        $unicodeArray[74] = "003B";
        $chrArray[75] = "<";
        $unicodeArray[75] = "003C";
        $chrArray[76] = "=";
        $unicodeArray[76] = "003D";
        $chrArray[77] = ">";
        $unicodeArray[77] = "003E";
        $chrArray[78] = "?";
        $unicodeArray[78] = "003F";
        $chrArray[79] = "@";
        $unicodeArray[79] = "0040";
        $chrArray[80] = "A";
        $unicodeArray[80] = "0041";
        $chrArray[81] = "B";
        $unicodeArray[81] = "0042";
        $chrArray[82] = "C";
        $unicodeArray[82] = "0043";
        $chrArray[83] = "D";
        $unicodeArray[83] = "0044";
        $chrArray[84] = "E";
        $unicodeArray[84] = "0045";
        $chrArray[85] = "F";
        $unicodeArray[85] = "0046";
        $chrArray[86] = "G";
        $unicodeArray[86] = "0047";
        $chrArray[87] = "H";
        $unicodeArray[87] = "0048";
        $chrArray[88] = "I";
        $unicodeArray[88] = "0049";
        $chrArray[89] = "J";
        $unicodeArray[89] = "004A";
        $chrArray[90] = "K";
        $unicodeArray[90] = "004B";
        $chrArray[91] = "L";
        $unicodeArray[91] = "004C";
        $chrArray[92] = "M";
        $unicodeArray[92] = "004D";
        $chrArray[93] = "N";
        $unicodeArray[93] = "004E";
        $chrArray[94] = "O";
        $unicodeArray[94] = "004F";
        $chrArray[95] = "P";
        $unicodeArray[95] = "0050";
        $chrArray[96] = "Q";
        $unicodeArray[96] = "0051";
        $chrArray[97] = "R";
        $unicodeArray[97] = "0052";
        $chrArray[98] = "S";
        $unicodeArray[98] = "0053";
        $chrArray[99] = "T";
        $unicodeArray[99] = "0054";
        $chrArray[100] = "U";
        $unicodeArray[100] = "0055";
        $chrArray[101] = "V";
        $unicodeArray[101] = "0056";
        $chrArray[102] = "W";
        $unicodeArray[102] = "0057";
        $chrArray[103] = "X";
        $unicodeArray[103] = "0058";
        $chrArray[104] = "Y";
        $unicodeArray[104] = "0059";
        $chrArray[105] = "Z";
        $unicodeArray[105] = "005A";
        $chrArray[106] = "[";
        $unicodeArray[106] = "005B";
        $char = "\ ";
        $chrArray[107] = trim($char);
        $unicodeArray[107] = "005C";
        $chrArray[108] = "]";
        $unicodeArray[108] = "005D";
        $chrArray[109] = "^";
        $unicodeArray[109] = "005E";
        $chrArray[110] = "_";
        $unicodeArray[110] = "005F";
        $chrArray[111] = "`";
        $unicodeArray[111] = "0060";
        $chrArray[112] = "a";
        $unicodeArray[112] = "0061";
        $chrArray[113] = "b";
        $unicodeArray[113] = "0062";
        $chrArray[114] = "c";
        $unicodeArray[114] = "0063";
        $chrArray[115] = "d";
        $unicodeArray[115] = "0064";
        $chrArray[116] = "e";
        $unicodeArray[116] = "0065";
        $chrArray[117] = "f";
        $unicodeArray[117] = "0066";
        $chrArray[118] = "g";
        $unicodeArray[118] = "0067";
        $chrArray[119] = "h";
        $unicodeArray[119] = "0068";
        $chrArray[120] = "i";
        $unicodeArray[120] = "0069";
        $chrArray[121] = "j";
        $unicodeArray[121] = "006A";
        $chrArray[122] = "k";
        $unicodeArray[122] = "006B";
        $chrArray[123] = "l";
        $unicodeArray[123] = "006C";
        $chrArray[124] = "m";
        $unicodeArray[124] = "006D";
        $chrArray[125] = "n";
        $unicodeArray[125] = "006E";
        $chrArray[126] = "o";
        $unicodeArray[126] = "006F";
        $chrArray[127] = "p";
        $unicodeArray[127] = "0070";
        $chrArray[128] = "q";
        $unicodeArray[128] = "0071";
        $chrArray[129] = "r";
        $unicodeArray[129] = "0072";
        $chrArray[130] = "s";
        $unicodeArray[130] = "0073";
        $chrArray[131] = "t";
        $unicodeArray[131] = "0074";
        $chrArray[132] = "u";
        $unicodeArray[132] = "0075";
        $chrArray[133] = "v";
        $unicodeArray[133] = "0076";
        $chrArray[134] = "w";
        $unicodeArray[134] = "0077";
        $chrArray[135] = "x";
        $unicodeArray[135] = "0078";
        $chrArray[136] = "y";
        $unicodeArray[136] = "0079";
        $chrArray[137] = "z";
        $unicodeArray[137] = "007A";
        $chrArray[138] = "{";
        $unicodeArray[138] = "007B";
        $chrArray[139] = "|";
        $unicodeArray[139] = "007C";
        $chrArray[140] = "}";
        $unicodeArray[140] = "007D";
        $chrArray[141] = "~";
        $unicodeArray[141] = "007E";
        $chrArray[142] = "©";
        $unicodeArray[142] = "00A9";
        $chrArray[143] = "®";
        $unicodeArray[143] = "00AE";
        $chrArray[144] = "÷";
        $unicodeArray[144] = "00F7";
        $chrArray[145] = "×";
        $unicodeArray[145] = "00F7";
        $chrArray[146] = "§";
        $unicodeArray[146] = "00A7";
        $chrArray[147] = " ";
        $unicodeArray[147] = "0020";
        $chrArray[148] = "\n";
        $unicodeArray[148] = "000D";
        $chrArray[149] = "\r";
        $unicodeArray[149] = "000A";

        $strResult = "";
        for ($i = 0; $i < strlen($message); $i++) {
            if (in_array(substr($message, $i, 1), $chrArray))
                $strResult .= $unicodeArray[array_search(substr($message, $i, 1), $chrArray)];
        }

        return $strResult;
    }

}

