<?php

if (!defined('ABSPATH')) {
    exit;
}

final class WPNotif_User_Notifications
{
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
        $this->user_notifications_hooks();
    }

    public function user_notifications_hooks()
    {
        add_action('wp_login', array($this, 'user_login'), 10, 2);
        add_action('password_reset', array($this, 'password_reset'), 10, 2);

        add_action('digits_new_user', array($this, 'user_register'), 10, 1);//digits oneclick user register
        add_action('register_new_user', array($this, 'user_register'), 10, 1);//wp user register
        add_action('woocommerce_created_customer', array($this, 'wc_user_register'), 10, 3);//wc user register

        add_filter('wpnotif_notification_list_customer', array($this, 'user_approval_status'));
        add_filter('wpnotif_notification_list_admin', array($this, 'user_approval_status'));

        add_action('digits_account_status_change', array($this, 'user_approval_status_change'), 10, 2);
    }

    public static function instance()
    {
        if (is_null(self::$_instance)) {
            self::$_instance = new self();
        }

        return self::$_instance;
    }

    public function user_approval_status_change($status, $user)
    {
        $key = $status;
        $user_id = $user->ID;
        $this->notify_user($user_id, $key);
    }

    public function user_approval_status($status)
    {
        if (!class_exists('Digits_Account_Approval')) {
            return $status;
        }
        $add = array(
            Digits_Account_Approval::PENDING => array(
                'label' => esc_attr__('User Approval Pending', 'wpnotif'),
                'message' => '1',
                'placeholder' => '1',
            ),
            Digits_Account_Approval::APPROVED => array(
                'label' => esc_attr__('User Approved', 'wpnotif'),
                'message' => '1',
                'placeholder' => '1',
            ),
            Digits_Account_Approval::REJECTED => array(
                'label' => esc_attr__('User Rejected', 'wpnotif'),
                'message' => '1',
                'placeholder' => '1',
            ),
            Digits_Account_Approval::DISABLED => array(
                'label' => esc_attr__('User Disabled', 'wpnotif'),
                'message' => '1',
                'placeholder' => '1',
            ),
        );

        return array_merge($status, $add);
    }

    public function user_login($user_login, $user)
    {
        if (!$user instanceof WP_User) {
            $user = get_user_by('user_login', $user_login);
        }
        $key = 'user_login';
        $user_id = $user->ID;
        $this->notify_user($user_id, $key);
    }

    public function password_reset($user, $new_pass)
    {
        $key = 'user_pass_change';
        $user_id = $user->ID;
        $this->notify_user($user_id, $key);
    }

    public function wc_user_register($user_id, $userdata, $password)
    {
        $this->user_register($user_id);
    }

    public function user_register($user_id)
    {
        do_action('wpnotif_new_user', $user_id);
        $key = 'user_registration';
        $this->notify_user($user_id, $key);
    }

    public function notify_user($user_id, $key)
    {

        $user = get_user_by('ID', $user_id);
        $notification_data = WPNotif::data_type('user', $user);
        $data = WPNotif_Handler::instance()->notify_user($user_id, $notification_data, $key, 1);

        if ($data == -10) {
            $fail = true;
        }

        if (WPNotif_Handler::isWhatsappEnabled()) {
            $data = WPNotif_Handler::instance()->notify_user($user_id, $notification_data, $key, 1001);
        }
    }
}