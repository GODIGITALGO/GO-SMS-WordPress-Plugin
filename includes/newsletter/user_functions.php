<?php

if (!defined('ABSPATH')) {
    exit;
}

final class WPNotif_User_Functions
{
    const ACTION = 'WPNotif_User_Functions';
    const usergroup_otp_table = 'wpnotif_mobile_otp';
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
        add_action('wpnotif_load_verification_scripts', array($this, 'load_scripts'));

        add_action('wp_ajax_wpnotif_user_subscribe', array($this, 'ajax_user_subscribe'));
        add_action('wp_ajax_nopriv_wpnotif_user_subscribe', array($this, 'ajax_user_subscribe'));

        add_action('wp_ajax_wpnotif_send_otp', array($this, 'ajax_send_otp'));
        add_action('wp_ajax_nopriv_wpnotif_send_otp', array($this, 'ajax_send_otp'));

        add_filter('digits_allow_only_mobile_verfication', array($this, 'digits_allow_verification'), 10, 2);
    }

    public function digits_allow_verification($allow, $type)
    {
        if ($type == 101) {
            if ($this->ajax_verify_nonce()) {
                if (isset($_POST['gid'])) {
                    $countrycode = sanitize_text_field($_POST['wpnotif_countrycode']);
                    $mobile = sanitize_mobile_field_wpnotif($_POST['wpnotif_phone']);
                    $phone = $countrycode . $mobile;
                    $gid = sanitize_text_field($_POST['gid']);

                    if (WPNotif_UserGroups::instance()->check_if_user_exist_in_group($gid, $phone)) {
                        return new WP_Error('notice', esc_attr__('You are already subscribed!', 'wpnotif'));
                    }
                }

                return true;
            } else {
                return $allow;
            }
        } else {
            return $allow;
        }
    }

    public function ajax_verify_nonce()
    {

        $nonce = 'wpnotif_mobile_update_nounce';
        if (isset($_POST['wpnotif_firstname'])) {
            $nonce = $nonce . '_fname';
        }
        if (isset($_POST['wpnotif_lastname'])) {
            $nonce = $nonce . '_lname';
        }
        if (isset($_POST['wpnotif_email'])) {
            $nonce = $nonce . 'email';
        }

        if (!wp_verify_nonce($_POST['wpnotif_nounce'], $nonce)) {
            return false;
        } else {
            return true;
        }

    }

    public function is_verification_enabled()
    {
        $phone_verify = get_option('wpnotif_phone_verification', 'off');
        if ($phone_verify == 'on') {
            return true;
        } else {
            return false;
        }
    }

    public function ajax_send_otp()
    {
        if (!$this->ajax_verify_nonce() || !$this->is_verification_enabled() || function_exists('digits_version')) {
            wp_send_json_error(array('message' => esc_attr__('Error', 'wpnotif')));
            die();
        }


        $countrycode = sanitize_text_field($_POST['wpnotif_countrycode']);
        $mobile = sanitize_mobile_field_wpnotif($_POST['wpnotif_phone']);
        $phone = $countrycode . $mobile;
        $phone_obj = WPNotif_Handler::parseMobile($phone);

        if (!$phone_obj) {
            wp_send_json_error(array('message' => esc_attr__('Please enter a valid phone number!', 'wpnotif')));
            die();
        }

        if (isset($_POST['gid'])) {
            $gid = sanitize_text_field($_POST['gid']);
            if (WPNotif_UserGroups::instance()->check_if_user_exist_in_group($gid, $phone)) {
                wp_send_json_error(array('level' => 1, 'message' => esc_attr__('You are already subscribed!', 'wpnotif')));
            }
        }

        $status = $this->create_otp($countrycode, $mobile);

        if (!$status) {
            wp_send_json_error(array('message' => esc_attr__('Error', 'wpnotif')));
        } else {
            wp_send_json_success(array('message' => 'Done'));
        }
    }

    public function ajax_user_subscribe()
    {
        if (!$this->ajax_verify_nonce()) {
            wp_send_json_error(array('message' => esc_attr__("Error", 'wpnotif')));
        }

        $verified = false;

        $countrycode = sanitize_text_field($_POST['wpnotif_countrycode']);
        $mobile = sanitize_mobile_field_wpnotif($_POST['wpnotif_phone']);

        if (isset($_POST['gid'])) {
            $gid = sanitize_text_field($_POST['gid']);
            if (WPNotif_UserGroups::instance()->check_if_user_exist_in_group($gid, $countrycode . $mobile)) {
                wp_send_json_error(array('level' => 1, 'message' => esc_attr__('You are already subscribed!', 'wpnotif')));
            }
        }

        if ($this->is_verification_enabled()) {
            $verified = true;
            $otp = sanitize_mobile_field_wpnotif($_POST['wpnotif_otp']);


            if (function_exists('digits_version')) {
                if (!verifyOTP($countrycode, $mobile, $otp, true)) {
                    wp_send_json_error(array('message' => esc_attr__('Invalid OTP!', 'wpnotif')));
                    die();
                }
            } else {
                if (!$this->verifyOTP($countrycode, $mobile, $otp, true)) {
                    wp_send_json_error(array('message' => esc_attr__('Invalid OTP!', 'wpnotif')));
                    die();
                }
            }
        }


        $data = wpnotif_form_update_number(get_current_user_id(), $verified);
        if (is_wp_error($data)) {
            wp_send_json_error(array('message' => $data->get_error_message()));
        } else {
            wp_send_json_success(array('message' => esc_attr__('Thank you for subscribing', 'wpnotif')));
        }
    }

    public function load_scripts()
    {

        wp_register_script('wpnotif-subscribe', WPNotif::get_dir('/assets/js/subscribe.min.js'), array('wpnotif-frontend'), WPNotif::get_version(), 'all');
        $data = array(
            "Error" => esc_attr__("Error! Please try again later", "wpnotif"),
            "ajax_url" => admin_url('admin-ajax.php'),
            "submit" => esc_attr__("Submit OTP", "wpnotif"),
            "subscribe" => esc_attr__("Subscribe", "wpnotif"),
            "is_digits" => function_exists('digits_version') ? 1 : 0,
        );
        wp_localize_script('wpnotif-subscribe', 'wpn_sub', $data);

        wp_enqueue_script('wpnotif-subscribe');
    }

    public static function instance()
    {
        if (is_null(self::$_instance)) {
            self::$_instance = new self();
        }

        return self::$_instance;
    }

    function create_otp($countrycode, $mobileno)
    {


        if ($this->OTPexists($countrycode, $mobileno)) {
            return true;

        }

        $code = $this->get_otp();


        if (!$this->send_otp_sms($countrycode, $mobileno, $code)) {
            return false;
        }


        $mobileVerificationCode = md5($code);

        global $wpdb;
        $table_name = $wpdb->prefix . self::usergroup_otp_table;

        $db = $wpdb->replace($table_name, array(
            'countrycode' => $countrycode,
            'mobileno' => $mobileno,
            'otp' => $mobileVerificationCode,
            'time' => date("Y-m-d H:i:s", strtotime("now"))
        ), array(
                '%d',
                '%s',
                '%s',
                '%s'
            )
        );

        if (!$db) {
            return false;

        }


        return true;

    }

    function OTPexists($countrycode, $phone, $resend = false)
    {
        global $wpdb;
        $countrycode = filter_var($countrycode, FILTER_SANITIZE_NUMBER_INT);
        $phone = filter_var($phone, FILTER_SANITIZE_NUMBER_INT);
        $table_name = $wpdb->prefix . self::usergroup_otp_table;
        $usermerow = $wpdb->get_row(
            $wpdb->prepare(
                'SELECT * FROM ' . $table_name . '
        WHERE countrycode = %s AND mobileno= %s',
                $countrycode, $phone
            )
        );
        if ($usermerow) {
            $time = strtotime($usermerow->time);
            $current = strtotime("now");

            $t = 10;
            if ($resend) {
                $t = 20;
            }

            $diff = $current - $time;
            if ($diff > $t || $diff < 0) {
                $wpdb->delete($table_name, array(
                    'countrycode' => $countrycode,
                    'mobileno' => $phone
                ), array(
                        '%d',
                        '%d'
                    )
                );


                return $resend;
            }

            return true;
        } else {
            return false;
        }
    }

    function get_otp()
    {

        $otp_size = 6;

        $code = "";
        for ($i = 0; $i < $otp_size; $i++) {
            $code .= rand(0, 9);
        }

        return $code;

    }

    function send_otp_sms($countrycode, $mobile, $otp)
    {
        $template = esc_attr__("Your OTP for {NAME} is {OTP}", "wpnotif");

        $blog_name = get_option('blogname');
        $placeholders = array('%NAME%', '{NAME}', '%OTP%', '{OTP}');
        $values = array($blog_name, $blog_name, $otp, $otp);

        $template = str_replace($placeholders, $values, $template);

        $handler = WPNotif_Handler::instance();
        return $handler->send_notification($countrycode, $mobile, $template, 1, null);
    }

    function verifyOTP($countrycode, $phone, $otp, $deleteotp)
    {

        if (empty($otp)) {
            return false;
        }


        $countrycode = str_replace("+", "", $countrycode);
        global $wpdb;


        $countrycode = filter_var($countrycode, FILTER_SANITIZE_NUMBER_INT);
        $phone = filter_var($phone, FILTER_SANITIZE_NUMBER_INT);
        $otp = md5($otp);
        $table_name = $wpdb->prefix . self::usergroup_otp_table;
        $usermerow = $wpdb->get_row(
            $wpdb->prepare(
                'SELECT * FROM ' . $table_name . '
        WHERE countrycode = %s AND mobileno= %s AND otp=%s ORDER BY time DESC LIMIT 1',
                $countrycode, $phone, $otp
            )
        );


        if ($usermerow) {

            $time = strtotime($usermerow->time);
            $current = strtotime("now");

            if ($current - $time > 600) {
                $wpdb->delete($table_name, array(
                    'countrycode' => $countrycode,
                    'mobileno' => $phone
                ), array(
                        '%d',
                        '%s'
                    )
                );

                return false;
            }

            if ($deleteotp) {
                $wpdb->delete($table_name, array(
                    'countrycode' => $countrycode,
                    'mobileno' => $phone
                ), array(
                        '%d',
                        '%s'
                    )
                );
            }

            return true;
        } else {
            return false;
        }


    }

}