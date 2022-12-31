<?php

namespace WPNotif_Compatibility\GravityForms;


use WPNotif_Compatibility\WPForms\FormSettings;
use WPNotif_Compatibility\WPForms\WPForms_Field_Phone;

if (!defined('ABSPATH')) {
    exit;
}

WPForms::instance();

final class WPForms
{
    protected static $_instance = null;

    /**
     *  Constructor.
     */
    public function __construct()
    {
        add_action('wpforms_loaded', array($this, 'init_hooks'));
        add_action('wpforms_builder_init', array($this, 'load_settings'));
    }

    public static function instance()
    {
        if (is_null(self::$_instance)) {
            self::$_instance = new self();
        }

        return self::$_instance;
    }

    public function load_settings($view)
    {

    }

    public function init_hooks()
    {
        require_once 'form_field.php';
        require_once 'form_settings.php';
        FormSettings::instance();
        new WPForms_Field_Phone();
    }

}
