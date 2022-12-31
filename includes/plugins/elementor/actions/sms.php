<?php

namespace WPNotif_Compatibility\ElementorForms;

if (!defined('ABSPATH')) {
    exit;
}

class SMSNotificationAction extends WPNotifNotificationAction
{

    public function register_settings_section($widget)
    {
        parent::addFields($widget, $this->get_name(), $this->get_label());

    }

    public function get_name()
    {
        return 'wpnotif_sms';
    }

    public function get_label()
    {
        return esc_html__('WPNotif SMS', 'wpnotif');
    }

    public function run($record, $ajax_handler)
    {
        $this->run_wpn_action($record, $ajax_handler, $this->get_name());
    }

}
