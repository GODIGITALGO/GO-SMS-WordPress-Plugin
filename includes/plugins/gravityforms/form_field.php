<?php

namespace WPNotif_Compatibility\GravityForms;

use GF_Field;
use WPNotif;
use WPNotif_Handler;

if (!class_exists('GFForms')) {
    exit;
}


class Field_Phone extends GF_Field
{

    public $type = 'wpnotif-phone';

    public function get_form_editor_field_title()
    {
        return esc_attr__('Phone (WPNotif)', 'wpnotif');
    }

    /**
     * Returns the field's form editor icon.
     *
     * This could be an icon url or a gform-icon class.
     *
     * @return string
     * @since 2.5
     *
     */
    public function get_form_editor_field_icon()
    {
        return 'gform-icon--phone';
    }

    function get_form_editor_field_settings()
    {
        return array(
            'conditional_logic_field_setting',
            'prepopulate_field_setting',
            'error_message_setting',
            'label_setting',
            'label_placement_setting',
            'admin_label_setting',
            'size_setting',
            'rules_setting',
            'visibility_setting',
            'default_value_setting',
            'placeholder_setting',
            'description_setting',
            'css_class_setting',
        );
    }

    public function is_conditional_logic_supported()
    {
        return true;
    }

    public function get_value_save_entry($value, $form, $input_name, $lead_id, $lead)
    {
        return $this->get_value_submission($value);
    }

    /**
     * Retrieve the field value on submission.
     *
     * @param array $field_values The dynamic population parameter names with their corresponding values to be populated.
     * @param bool|true $get_from_post_global_var Whether to get the value from the $_POST array as opposed to $field_values.
     *
     * @return array|string
     * @uses GFFormsModel::choice_value_match()
     * @uses GFFormsModel::get_parameter_value()
     *
     * @since  Unknown
     * @access public
     *
     */
    public function get_value_submission($field_values, $get_from_post_global_var = true)
    {

        $nationalNumber = $this->get_input_value_submission('input_' . $this->id, $this->inputName, $field_values, $get_from_post_global_var);
        if (empty($nationalNumber) && !is_array($field_values)) {
            $nationalNumber = $field_values;
        }
        if ($get_from_post_global_var && !empty($_POST['input_countrycode_' . $this->id])) {
            $countryCode = $_POST['input_countrycode_' . $this->id];
        } else {
            $countryCode = $this->get_input_value_submission('input_countrycode_' . $this->id, $this->inputName, $field_values, $get_from_post_global_var);
        }
        $phone = $countryCode . $nationalNumber;

        if (!$this->validate_phone($phone)) {
            return $nationalNumber;
        }

        return $phone;
    }


    public function validate($value, $form)
    {

        $requires_valid_number = !rgblank($value);

        $is_valid_number = $this->validate_phone($value);

        if ($requires_valid_number && !$is_valid_number) {
            $this->failed_validation = true;
            $this->validation_message = empty($this->errorMessage) ? $this->get_error_message() : $this->errorMessage;
        }

    }

    public function validate_phone($phone)
    {
        $parse_mobile = WPNotif_Handler::parseMobile($phone);
        return !empty($parse_mobile);
    }

    public function get_error_message()
    {
        $message = '';
        if ($this->failed_validation) {
            $message = esc_html__('Please enter a valid phone number', 'wpnotif');
        }

        return $message;
    }

    public function get_field_input($form, $value = '', $entry = null)
    {
        $is_entry_detail = $this->is_entry_detail();
        $is_form_editor = $this->is_form_editor();

        $form_id = $form['id'];
        $id = intval($this->id);
        $field_id = $is_entry_detail || $is_form_editor || $form_id == 0 ? "input_$id" : 'input_' . $form_id . "_$id";

        $size = $this->size;
        $disabled_text = $is_form_editor ? "disabled='disabled'" : '';
        $class_suffix = $is_entry_detail ? '_admin' : '';
        $class = $size . $class_suffix;

        $instruction = '';


        if (!$is_entry_detail && !$is_form_editor) {


            $message = $this->get_error_message();
            $validation_class = $this->failed_validation ? 'validation_message' : '';

            if (!$this->failed_validation && !empty($message) && empty($this->errorMessage)) {
                $instruction = "<div class='instruction $validation_class'>" . $message . '</div>';
            }

        }


        $countrycode = '';

        $parse_mobile = WPNotif_Handler::parseMobile($value);
        if ($parse_mobile) {
            $countrycode = $parse_mobile['countrycode'];
            $value = $parse_mobile['mobile'];
        }

        if (empty($countrycode)) {
            $countrycode = WPNotif::getDefaultCountryCode();
        }


        $placeholder_attribute = $this->get_field_placeholder_attribute();
        $required_attribute = $this->isRequired ? 'aria-required="true"' : '';
        $invalid_attribute = $this->failed_validation ? 'aria-invalid="true"' : 'aria-invalid="false"';
        $aria_describedby = $this->get_aria_describedby();

        $tabindex = $this->get_tabindex();


        $countrycode = esc_attr($countrycode);
        $field = "<div class='ginput_container ginput_container_mobile wpnotif_phone_field_container'>";

        $field .= '<div class="wpnotif_phonefield">';
        $field .= '<div class="wpnotif_countrycodecontainer">';
        $field .= sprintf('<input type="text" name="input_countrycode_%d"
                                   class="wpnotif_countrycode"
                                   value="%s" maxlength="6" size="3"
                                   placeholder="%s" />', $id, $countrycode, $countrycode);
        $field .= '</div>';

        $field .= "<input name='input_%d' id='%s' type='text' value='%s' class='wpnotif_phone %s' {$tabindex} %s %s %s %s %s/>";
        $field .= '</div>';

        $field .= "%s</div>";

        do_action('wpnotif_load_frontend_scripts');

        $input = sprintf($field, $id, $field_id, esc_attr($value), esc_attr($class), $disabled_text, $placeholder_attribute, $required_attribute, $invalid_attribute, $aria_describedby, $instruction);
        return $input;
    }

}