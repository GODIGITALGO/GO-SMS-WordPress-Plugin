<?php

namespace WPNotif_Compatibility\ElementorForms;

use Elementor\Controls_Manager;

if (!defined('ABSPATH')) {
    exit;
}

class AddToUserGroup extends WPNotifNotificationAction
{

    public function get_label()
    {
        return esc_html__('WPNotif Newsletter', 'wpnotif');
    }

    public function register_settings_section($widget)
    {

        $formatted_group = array();
        $groups = \WPNotif_NewsLetter::get_formated_usergroup_list(false);
        foreach ($groups as $group) {
            $formatted_group[$group['value']] = $group['label'];
        }

        $widget->start_controls_section(
            'wpn_newsletter',
            [
                'label' => 'WPNotif Newsletter',
            ]
        );

        $widget->add_control(
            'wpn_use_as_newsletter',
            [
                'name' => 'use_as_newsletter',
                'label' => __('Use as Newsletter', 'wpnotif'),
                'type' => Controls_Manager::SELECT,
                'options' => array('0' => 'No', '1' => 'Yes'),
                'default' => '0'
            ]
        );

        $widget->add_control(
            'wpn_user_group',
            [
                'name' => 'user_group',
                'label' => __('Add to User Group', 'wpnotif'),
                'type' => Controls_Manager::SELECT2,
                'multiple' => true,
                'options' => $formatted_group,
            ]
        );


        $widget->end_controls_section();

    }

    public function on_export($element)
    {

    }

    public function run($record, $ajax_handler)
    {
        $this->run_wpn_action($record, $ajax_handler, $this->get_name());
    }

    public function get_name()
    {
        return 'wpnotif_newsletter';
    }

}
