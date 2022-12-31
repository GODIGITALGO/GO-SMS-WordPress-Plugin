<?php

namespace WPNotif_Compatibility\ElementorForms;

if (!defined('ABSPATH')) {
    exit;
}

use ElementorPro\Modules\Forms\Classes;
use WPNotif;

class WPNotifNotificationAction extends \ElementorPro\Modules\Forms\Classes\Action_Base
{
    public static $notif_type = 'elementor';
    private $wpn_action_name = '';
    private $wpn_action_label = '';


    public function get_name()
    {
        return $this->wpn_action_name;
    }

    public function get_label()
    {
        return $this->wpn_action_label;
    }

    public function run($record, $ajax_handler)
    {
    }

    /**
     * process file and move it to uploads directory
     *
     * @param Classes\Form_Record $record
     * @param Classes\Ajax_Handler $ajax_handler
     */
    public function run_wpn_action(Classes\Form_Record $records, $ajax_handler, $type)
    {
        $data = array();
        $args = array();

        foreach ($records->get('fields') as $field) {

            if (!empty($field['value'])) {
                if ($field['type'] == 'wpn_field') {
                    $data[] = $field['value'];
                }
                if ($field['type'] == 'email') {
                    $args['email'] = $field['value'];
                }
                if (strpos($field['id'], 'name') !== false) {
                    $key = 'first_name';
                    if (strpos($field['id'], 'l') !== false) {
                        $key = 'last_name';
                    }
                    $args[$key] = $field['value'];
                }
            }
        }
        if (empty($data)) {
            return;
        }

        $settings = $records->get('form_settings');

        foreach ($data as $phone) {
            $result = $this->process_wpn_action($args, $settings, $phone, $records, $ajax_handler, $type);
        }

    }

    /**
     * @param array
     * @param string
     * @param string
     * @param Classes\Form_Record $record
     * @param Classes\Ajax_Handler $ajax_handler
     */
    public function process_wpn_action($args, $settings, $phone, Classes\Form_Record $records, $ajax_handler, $type)
    {
        if ($type == 'wpnotif_newsletter') {

            $wpn_use_as_newsletter = $settings['wpn_use_as_newsletter'];
            if ($wpn_use_as_newsletter != 1) {
                return false;
            }
            $defaults = array(
                'first_name' => null,
                'last_name' => null,
                'email' => '',
            );

            $args = wp_parse_args( $args, $defaults );

            $groups = $settings['wpn_user_group'];
            foreach($groups as $group) {

                $first_name = $args['first_name'];
                $last_name = $args['last_name'];
                $email = $args['email'];

                \WPNotif_UserGroup_Import::instance()->add_user_to_group($group, $first_name, $last_name, $email, $phone, true);
            }

        } else {
            $fields = array('admin_message', 'user_message');
            $data = array();
            foreach ($fields as $field) {
                $message = trim($settings[$type . $field]);
                $message = $records->replace_setting_shortcodes($message);
                if (!empty($message)) {
                    $data[$field] = $message;
                }
            }
            if (empty($data)) {
                return false;
            }
            $notification_data = WPNotif::data_type(self::$notif_type, $data, 0);
            $notification_data['user_phone'] = $phone;


            $type = str_replace("wpnotif_", "", $type);
            $enable_sms = false;
            $enable_whatsapp = false;
            if ($type == 'sms') {
                $enable_sms = true;
            } else if ($type == 'whatsapp') {
                $enable_whatsapp = true;
            }

            WPNotif::notify(get_current_user_id(), null, $notification_data, $enable_sms, $enable_whatsapp);

        }

        return true;
    }

    public function addFields($widget, $name, $label)
    {
        $widget->start_controls_section(
            $name,
            [
                'label' => $label,
            ]
        );
        $widget->add_control(
            $name . 'admin_message',
            [
                'label' => esc_html__('Admin Notification', 'wpnotif'),
                'type' => \Elementor\Controls_Manager::TEXTAREA,
                'placeholder' => 'Leave Empty To Disable',
                'description' => __('For placeholders, copy the shortcode that appears inside each field and paste it above.', 'wpnotif'),
                'render_type' => 'none',
            ]
        );

        $widget->add_control(
            $name . 'user_message',
            [
                'label' => esc_html__('User Notification', 'wpnotif'),
                'type' => \Elementor\Controls_Manager::TEXTAREA,
                'placeholder' => 'Leave Empty To Disable',
                'description' => __('For placeholders, copy the shortcode that appears inside each field and paste it above.', 'wpnotif'),
                'render_type' => 'none',
            ]
        );

        $widget->end_controls_section();

    }

    public function register_settings_section($widget)
    {

    }

    public function on_export($element)
    {

    }
}





