<?php

namespace WPNotif_Compatibility\ContactForm7;

use WPNotif;

if (!defined('ABSPATH')) {
    exit;
}


ContactForm7::instance();

final class ContactForm7
{
    public static $notif_type = 'contactform7';
    protected static $_instance = null;
    /* @var Field_Phone */
    public $field = null;
    /* @var NotificationSettings */
    public $notificationSettings = null;
    public $submit_confirm_status = 'mail_sent';

    /**
     *  Constructor.
     */
    public function __construct()
    {
        $this->init_hooks();
    }

    private function init_hooks()
    {

        require_once 'form_field.php';
        require_once 'form_settings.php';

        $this->field = Field_Phone::instance();
        $this->notificationSettings = NotificationSettings::instance();

        add_action('wpcf7_submit', array($this, 'send_phone_notifications'), 10, 2);
    }

    public static function instance()
    {
        if (is_null(self::$_instance)) {
            self::$_instance = new self();
        }

        return self::$_instance;
    }

    public function send_phone_notifications(\WPCF7_ContactForm $contact_form, $result)
    {

        if ($result['status'] == $this->submit_confirm_status) {

            $form_id = $result['contact_form_id'];
            $settings = $this->notificationSettings->get_settings($form_id);
            if (empty($settings))
                return;

            if ($submission = \WPCF7_Submission::get_instance()) {
                $data = array();
                $phone_field = $settings[$this->notificationSettings->get_field_type()];
                $admin_message = $settings['admin_message'];
                $user_message = $settings['user_message'];

                $user_phone = $submission->get_posted_data($phone_field);
                $use_as_newsletter = $settings['use_as_newsletter'];
                if (!empty($use_as_newsletter) && $use_as_newsletter == 1) {
                    $group = $settings['user_group'];

                    if (!empty($user_phone) && !empty($group)) {
                        $first_name = $settings['first_name'];
                        $last_name = $settings['last_name'];
                        $email = $settings['email'];
                        if (!empty($first_name)) {
                            $first_name = $submission->get_posted_data($first_name);
                        }
                        if (!empty($last_name)) {
                            $last_name = $submission->get_posted_data($last_name);
                        }
                        if (!empty($email)) {
                            $email = $submission->get_posted_data($email);
                        }

                        \WPNotif_UserGroup_Import::instance()->add_user_to_group($group, $first_name, $last_name, $email, $user_phone, true);
                    }
                }

                $enable_sms = true;
                $enable_whatsapp = false;

                if (isset($settings['route'])) {
                    $route = $settings['route'];
                    if ($route == -1 || $route == 1) {
                        $enable_sms = true;
                    }
                    if ($route == -1 || $route == 1001) {
                        $enable_whatsapp = true;
                    }
                } else {
                    if (!empty($settings['enable_whatsapp']) && $settings['enable_whatsapp'] == 1) {
                        $enable_whatsapp = true;
                    }
                }


                if (empty($user_phone) || (empty($admin_message) && empty($user_message))) {
                    return;
                }

                $this->init_notification_hooks();


                $data['admin_message'] = $admin_message;
                $data['user_message'] = $user_message;

                $data['submission'] = $submission;


                $notification_data = WPNotif::data_type(self::$notif_type, $data, 0);
                $notification_data['user_phone'] = $user_phone;

                WPNotif::notify(get_current_user_id(), null, $notification_data, $enable_sms, $enable_whatsapp);

            }
        }

    }

    public function init_notification_hooks()
    {
        add_filter('wpnotif_' . self::$notif_type . '_placeholder_args', array(&$this, 'update_placeholder'), 10, 4);
        add_filter('wpnotif_notification_options_' . self::$notif_type, array(&$this, 'notification_options'), 10);

    }

    public function notification_options($values)
    {
        $values['identifier'] = self::$notif_type;
        $values['different_gateway_content'] = 'off';
        return $values;
    }

    public function update_placeholder($value, $placeholder, $msg, $data)
    {
        $placeholer_prefix = self::$notif_type . '-';

        if (strpos($placeholder, $placeholer_prefix) === 0) {
            $submission = $data['submission'];
            $field_name = str_replace($placeholer_prefix, '', $placeholder);
            $field_value = $submission->get_posted_data($field_name);
            if (is_array($field_value)) {
                $field_value = implode(", ", $field_value);
            }
            return $field_value;
        }

        return $value;
    }
}