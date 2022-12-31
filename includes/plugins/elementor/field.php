<?php

namespace WPNotif_Compatibility\ElementorForms;

use Elementor\Widget_Base;
use ElementorPro\Modules\Forms\Classes;
use ElementorPro\Modules\Forms\Fields\Field_Base;
use ElementorPro\Plugin;
use WPNotif;
use WPNotif_Handler;

if (!defined('ABSPATH')) {
    exit;
}

class WPNotifField extends Field_Base
{

    public function __construct()
    {
        $type = $this->get_type();
        parent::__construct();

        add_action('elementor/editor/before_enqueue_scripts', [$this, 'enqueue_scripts']);

        add_action("elementor_pro/forms/process/{$type}", [$this, 'process_field'], 10, 3);

    }

    public function get_type()
    {
        return 'wpn_field';
    }

    public function enqueue_scripts()
    {
        wp_enqueue_script('wpnotif-elementor-field',
            WPNotif::get_dir('/includes/plugins/elementor/admin.min.js'), array(),
            WPNotif::get_version());
    }

    public function get_name()
    {
        return esc_html__('WPNotif Phone Field', 'wpnotif');
    }

    /**
     * @param $item
     * @param $item_index
     * @param $form Widget_Base
     */
    public function render($item, $item_index, $form)
    {

        $countrycode = WPNotif::getDefaultCountryCode();

        $form->add_render_attribute('input' . $item_index, 'class', 'elementor-field-textual wpnotif-phone-field');
        $form->set_render_attribute('input' . $item_index, 'type', 'tel');
        $attrs = $form->get_render_attribute_string('input' . $item_index);

        $cc_class = $form->get_render_attributes('input' . $item_index, 'class');
        $cc_class[] = 'wpnotif_countrycode';

        $countrycode = esc_attr($countrycode);
        $field = "<div class='elementor_container elementor_container_mobile wpnotif_phone_field_container'>";

        $id = $item['custom_id'];
        $field .= '<div class="wpnotif_phonefield">';
        $field .= '<div class="wpnotif_countrycodecontainer">';
        $field .= sprintf('<input type="text" name="countrycode_%s"
                                   class="%s"
                                   value="%s" maxlength="6" size="3"
                                   placeholder="%s" />', $id, implode(" ", $cc_class), $countrycode, $countrycode);
        $field .= '</div>';

        $field .= "<input {$attrs} />";
        $field .= '</div>';

        $field .= "</div>";

        do_action('wpnotif_load_frontend_scripts');

        echo $field;
    }

    public function validation($field, Classes\Form_Record $record, Classes\Ajax_Handler $ajax_handler)
    {
        $countrycode = $_REQUEST['countrycode_' . $field['id']];
        $phone_no = $field['value'];

        $phone = $countrycode . $phone_no;
        $parse_mobile = WPNotif_Handler::parseMobile($phone);
        if (empty($parse_mobile)) {
            $ajax_handler->add_error($field['id'], __('Please enter a valid number', 'wpnotif'));
        }
    }

    /**
     * process file and move it to uploads directory
     *
     * @param array $field
     * @param Classes\Form_Record $record
     * @param Classes\Ajax_Handler $ajax_handler
     */
    public function process_field($field, Classes\Form_Record $record, Classes\Ajax_Handler $ajax_handler)
    {
        if (!isset($_REQUEST['countrycode_' . $field['id']])) {
            return;
        }

        $countrycode = $_REQUEST['countrycode_' . $field['id']];
        $phone_no = $field['value'];
        $phone = $countrycode . $phone_no;

        $record->update_field($field['id'], 'value', $phone);
    }

    /**
     * @param Widget_Base $widget
     */
    public function update_controls($widget)
    {
        $elementor = Plugin::elementor();

        $control_data = $elementor->controls_manager->get_control_from_stack($widget->get_unique_name(), 'form_fields');

        if (is_wp_error($control_data)) {
            return;
        }

        $widget->update_control('form_fields', $control_data);
    }

}