<?php

namespace WPNotif_Compatibility\GravityForms;

use GFFeedAddOn;
use GFForms;
use WPNotif;

if (!class_exists('GFForms')) {
    exit;
}

GFForms::include_feed_addon_framework();

class NotificationSettings extends GFFeedAddOn
{

    public static $notif_type = 'gravityforms';
    private static $_instance = null;
    protected $_slug = 'wpnotif_phone_notifications';
    protected $_version = '1.0';
    protected $_min_gravityforms_version = '1.9.16';
    protected $_title = 'WPNotif Phone Notifications';
    protected $_single_feed_submission = false;
    protected $_full_path = __FILE__;
    protected $_short_title = 'WPNotif';

    public static function get_instance()
    {

        if (self::$_instance == null) {
            self::$_instance = new self;
        }

        return self::$_instance;
    }


    public function init()
    {
        parent::init();
    }

    public function process_feed($feed, $entry, $form)
    {
        $feed_meta = $feed['meta'];
        $phone = $this->get_field_value($form, $entry, $feed_meta['mappedFields_phone']);

        if (empty($phone))
            return;


        $use_as_newsletter = $feed_meta['use_as_newsletter'];
        if (!empty($use_as_newsletter) && $use_as_newsletter == 1) {
            $group_id = $feed_meta['user_group'];
            if (!empty($group_id)) {
                $fname = $this->get_field_value($form, $entry, $feed_meta['mappedFields_first_name']);
                $lname = $this->get_field_value($form, $entry, $feed_meta['mappedFields_last_name']);
                $email = $this->get_field_value($form, $entry, $feed_meta['mappedFields_email']);

                \WPNotif_UserGroup_Import::instance()->add_user_to_group($group_id, $fname, $lname, $email, $phone, true);
            }
        }

        $admin_notification = $feed_meta['admin_notification'];
        $user_notification = $feed_meta['user_notification'];

        if (empty($admin_notification) && empty($user_notification) || !$feed_meta) {
            return;
        }


        $enable_sms = true;
        $enable_whatsapp = false;

        if (isset($feed_meta['route'])) {

            $route = $feed_meta['route'];
            if ($route == -1 || $route == 1) {
                $enable_sms = true;
            }
            if ($route == -1 || $route == 1001) {
                $enable_whatsapp = true;
            }

        } else {
            if (!empty($feed_meta['enable_whatsapp']) && $feed_meta['enable_whatsapp'] == 1) {
                $enable_whatsapp = true;
            }
        }

        $this->init_notification_hooks();


        $data = array();
        $data['form'] = $form;
        $data['entry'] = $entry;


        $data['admin_message'] = $admin_notification;
        $data['user_message'] = $user_notification;
        $notification_data = WPNotif::data_type(self::$notif_type, $data, 0);
        $notification_data['user_phone'] = $phone;


        WPNotif::notify(get_current_user_id(), null, $notification_data, $enable_sms, $enable_whatsapp);

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
            $form = $data['form'];
            $entry = $data['entry'];

            $field_id = str_replace($placeholer_prefix, '', $placeholder);
            $field_value = $this->get_field_value($form, $entry, $field_id);

            return $field_value;
        }

        return $value;
    }

    /**
     * Configures the settings which should be rendered on the feed edit page in the Form Settings > Simple Feed Add-On area.
     *
     * @return array
     */
    public function feed_settings_fields()
    {

        $groups = \WPNotif_NewsLetter::get_formated_usergroup_list(false);

        $fields = array();


        $fields[] = array(
            'label' => 'Name',
            'type' => 'text',
            'name' => 'feed_name',
            'class' => 'medium',
        );

        $fields[] = array(
            'label' => esc_html__('Admin Notification', 'wpnotif'),
            'type' => 'textarea',
            'name' => 'admin_notification',
            'class' => 'medium',
            'placeholder' => esc_attr__('Leave empty to disable', 'wpnotif'),
        );
        $fields[] = array(
            'label' => esc_html__('User Notification', 'wpnotif'),
            'type' => 'textarea',
            'name' => 'user_notification',
            'class' => 'medium',
            'placeholder' => esc_attr__('Leave empty to disable', 'wpnotif'),
        );


        $fields[] = array(
            'label' => esc_html__('Route', 'wpnotif'),
            'type' => 'select',
            'name' => 'route',
            'choices' => array(
                array(
                    'label' => esc_html__('SMS', 'wpnotif'),
                    'value' => 1,
                ),
                array(
                    'label' => esc_html__('WhatsApp', 'wpnotif'),
                    'value' => 1001,
                ),
                array(
                    'label' => esc_html__('Both', 'wpnotif'),
                    'value' => -1,
                )
            )
        );


        $fields[] = array(
            'label' => esc_html__('Use as Newsletter Subscription form', 'wpnotif'),
            'type' => 'select',
            'name' => 'use_as_newsletter',
            'class' => 'medium wpnotif_use_as_newsletter_inp',
            'choices' => array(
                array(
                    'label' => esc_html__('No', 'wpnotif'),
                    'value' => 0,
                ),
                array(
                    'label' => esc_html__('Yes', 'wpnotif'),
                    'value' => 1,
                )
            )
        );
        $fields[] = array(
            'label' => esc_html__('User Group', 'wpnotif'),
            'type' => 'select',
            'name' => 'user_group',
            'class' => 'medium wpnotif_use_newsletter',
            'choices' => $groups,
        );
        $fields[] = array(
            'name' => 'mappedFields',
            'label' => esc_html__('Select Field', 'wpnotif'),
            'type' => 'field_map',
            'field_map' => array(
                array(
                    'name' => 'first_name',
                    'label' => esc_html__('First Name', 'wpnotif'),
                    'required' => false,
                    'field_type' => array('name', 'text', 'hidden'),
                    'class' => 'wpnotif_use_newsletter',
                    'default_value' => $this->get_first_field_by_type('name', 3),
                ),
                array(
                    'name' => 'last_name',
                    'label' => esc_html__('Last Name', 'wpnotif'),
                    'required' => false,
                    'field_type' => array('name', 'text', 'hidden'),
                    'class' => 'wpnotif_use_newsletter',
                    'default_value' => $this->get_first_field_by_type('name', 3),
                ),
                array(
                    'name' => 'email',
                    'label' => esc_html__('Email', 'wpnotif'),
                    'required' => false,
                    'field_type' => array('email', 'hidden'),
                    'class' => 'wpnotif_use_newsletter',
                    'default_value' => $this->get_first_field_by_type('email'),
                ),
                array(
                    'name' => 'phone',
                    'label' => esc_html__('Phone', 'wpnotif'),
                    'required' => true,
                    'field_type' => 'wpnotif-phone',
                ),
            ),
        );

        $fields[] = array(
            'name' => 'feed_condition',
            'label' => __('Send notification only if', 'wpnotif'),
            'checkbox_label' => __('Enable', 'pods-gravity-forms'),
            'type' => 'feed_condition',
        );

        return array(
            array(
                'title' => esc_html__('Phone Notifications', 'wpnotif'),
                'fields' => $fields,
            ),
        );
    }

    public function feed_list_columns()
    {
        return array(
            'feed_name' => __('Name', 'wpnotif')
        );
    }

    public function get_column_value_feed_name($feed)
    {
        return '<b>' . rgars($feed, 'meta/feed_name') . '</b>';
    }

}