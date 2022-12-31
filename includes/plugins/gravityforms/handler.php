<?php

namespace WPNotif_Compatibility\GravityForms;


if (!defined('ABSPATH')) {
    exit;
}

GravityForms::instance();

final class GravityForms
{
    protected static $_instance = null;

    /**
     *  Constructor.
     */
    public function __construct()
    {
        add_action('gform_loaded', array($this, 'init_hooks'));
    }

    public function init_hooks()
    {
        if (!method_exists('GFForms', 'include_feed_addon_framework')) {
            return;
        }

        require_once 'form_field.php';
        require_once 'form_settings.php';

        \GF_Fields::register(new Field_Phone());
        \GFAddOn::register('\WPNotif_Compatibility\GravityForms\NotificationSettings');

    }

    public static function instance()
    {
        if (is_null(self::$_instance)) {
            self::$_instance = new self();
        }

        return self::$_instance;
    }

}
