<?php

namespace WPNotif_Compatibility\ElementorForms;


use ElementorPro\Plugin;

if (!defined('ABSPATH')) {
    exit;
}

ElementorForms::instance();

final class ElementorForms
{
    protected static $_instance = null;
    public static $SLUG = 'elementor';

    protected $_slug = 'wpnotif-phone';

    /**
     *  Constructor.
     */
    public function __construct()
    {
        $this->init_hooks();
    }

    private function init_hooks()
    {
        add_action('elementor_pro/init', array($this, 'init_elementor'));
        add_filter('wpnotif_notification_options_elementor', array(&$this, 'notification_options'), 10);
    }

    public function notification_options($values)
    {
        $values['identifier'] = 'elementor';
        $values['different_gateway_content'] = 'off';
        return $values;
    }

    public function init_elementor(){
        require_once plugin_dir_path(__FILE__) . 'field.php';
        require_once plugin_dir_path(__FILE__) . 'actions/action.php';
        require_once plugin_dir_path(__FILE__) . 'actions/sms.php';
        require_once plugin_dir_path(__FILE__) . 'actions/whatsapp.php';
        require_once plugin_dir_path(__FILE__) . 'actions/add_to_group.php';

        $phone_field = new WPNotifField();

        $add_to_group = new AddToUserGroup();
        $whatsapp_action = new WPNotifWhatsAPPNotificationAction();
        $sms_action = new SMSNotificationAction();

        Plugin::instance()->modules_manager->get_modules( 'forms' )
            ->add_form_action( $sms_action->get_name(), $sms_action );

        Plugin::instance()->modules_manager->get_modules( 'forms' )
            ->add_form_action( $whatsapp_action->get_name(), $whatsapp_action );

        Plugin::instance()->modules_manager->get_modules( 'forms' )
            ->add_form_action( $add_to_group->get_name(), $add_to_group );
    }
    
    public static function instance()
    {
        if (is_null(self::$_instance)) {
            self::$_instance = new self();
        }

        return self::$_instance;
    }

    public function add_field($field_types)
    {
        $field_types[$this->_slug] = __('WPNotif Phone', 'wpnotif');
        return $field_types;
    }


}
